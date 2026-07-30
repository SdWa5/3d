<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;
use App\Spec\Violation;

/**
 * Turns a scene's placements into absolute positions.
 *
 * All the arithmetic lives here rather than in the Blender script, so stacking heights, repetition
 * and the checks around them are unit-testable without Blender — and so a scene that does not add up
 * is caught before a single model is loaded.
 */
final class SceneCompiler
{
    /**
     * @param array<string, DeviceSpec> $devicesById
     */
    public function __construct(private readonly array $devicesById)
    {
    }

    /**
     * @return array{placed: list<PlacedDevice>, violations: list<Violation>}
     */
    public function compile(SceneSpec $scene): array
    {
        // Where the rig stands, worked out before any orientation exists. Aiming needs the focus
        // point, the focus point needs the rig's front face, and the front face must not depend on
        // aiming — otherwise the two would chase each other.
        $focusPoint = $scene->focus->point($this->frontCentre($scene));

        $placed = [];
        $violations = [];
        /** @var array<string, PlacedDevice> $byId last repeat of each placement, for `on` */
        $byId = [];

        $add = static function (string $message) use ($scene, &$violations): void {
            $violations[] = new Violation($scene->sourcePath, $message);
        };

        foreach ($scene->placements as $placement) {
            if (isset($byId[$placement->id])) {
                $add("duplicate placement id '{$placement->id}'");
                continue;
            }

            $device = $this->devicesById[$placement->deviceId] ?? null;
            if ($device === null) {
                $add("placement '{$placement->id}' references unknown device '{$placement->deviceId}'");
                continue;
            }

            $base = $this->resolveBase($placement, $device, $byId, $add);
            if ($base === null) {
                continue;
            }

            $target = $placement->aimAt ?? ($placement->aimAtFocus ? $focusPoint : null);
            // An arc's radius depends on how far the cabinets are tilted, and the tilt depends on where
            // they stand — so the arc is solved at the tilt of its anchor, which stands on `at` facing
            // straight ahead. Across a three-wide arc the individual tilts differ by 0.03°.
            $pitch = $target === null
                ? $placement->pitchDeg
                : Orientation::pitchTowards($base, $target, $device->dimensions->height, 0.0, $placement->rollDeg);

            $problems = $this->validate($placement, $device, $pitch);
            if ($problems !== []) {
                foreach ($problems as $problem) {
                    $add("placement '{$placement->id}': {$problem}");
                }
                continue;
            }

            $numbered = $placement->copyCount() > 1;
            foreach ($this->copies($placement, $device, $pitch) as $copy) {
                $id = $numbered ? sprintf('%s-%d', $placement->id, $copy->index + 1) : $placement->id;

                $position = [
                    $base[0] + $copy->offset[0],
                    $base[1] + $copy->offset[1],
                    $base[2] + $copy->offset[2],
                ];

                // Aim is resolved per copy, so a repeated row of tops each turns towards the target
                // rather than all sharing the first one's angle. An arc has already decided its yaw,
                // so there the aim contributes the down-tilt only.
                if ($target === null) {
                    $orientation = new Orientation(
                        $placement->pitchDeg,
                        $placement->rollDeg,
                        $copy->yawDeg ?? $placement->yawDeg,
                    );
                } elseif ($copy->yawDeg === null) {
                    $orientation = Orientation::aimedAt(
                        $position,
                        $target,
                        $device->dimensions->height,
                        $placement->rollDeg,
                    );
                } else {
                    $orientation = new Orientation(
                        Orientation::pitchTowards(
                            $position,
                            $target,
                            $device->dimensions->height,
                            $copy->yawDeg,
                            $placement->rollDeg,
                        ),
                        $placement->rollDeg,
                        $copy->yawDeg,
                    );
                }

                $entry = new PlacedDevice($id, $device, $position, $orientation);

                $placed[] = $entry;
                // `on` refers to the placement as a whole, so one copy has to stand for it: the last of
                // a repeated row, and the middle of an arc, which is the one sitting on `at`.
                if ($copy->isAnchor) {
                    $byId[$placement->id] = $entry;
                }
            }
        }

        return ['placed' => $placed, 'violations' => $violations];
    }

    /**
     * Everything wrong with a placement, as messages without its `placement '<id>': ` prefix.
     *
     * Pure, so both the placing loop and the front-face walk can call it and neither has to guard
     * against geometry that does not resolve.
     *
     * @return list<string>
     */
    private function validate(Placement $placement, DeviceSpec $device, float $pitchDeg): array
    {
        $messages = [];

        if ($placement->arc !== null && $placement->repeatStep !== null) {
            $messages[] = 'use either `repeat` or `arc`, not both';
        }
        if ($placement->arc !== null && $placement->yawDeg !== 0.0) {
            $messages[] = 'the arc already sets yaw — remove yaw_deg';
        }
        if ($placement->arc !== null && fmod(abs($placement->rollDeg), 180.0) !== 0.0) {
            $messages[] = sprintf(
                'an arc needs the cabinet upright or turned over — roll_deg %s puts its width on the vertical',
                $placement->rollDeg,
            );
        }
        if ($placement->arc === null && $placement->repeatCount < 1) {
            $messages[] = 'repeat.count must be at least 1';
        }
        if ($placement->arc === null && $placement->repeatCount > 1 && $placement->repeatStep === null) {
            $messages[] = 'repeat.count > 1 needs a repeat.step';
        }
        if ($placement->aimAt !== null && $placement->aimAtFocus) {
            $messages[] = 'use either `aim: focus` or `aim_at`, not both';
        }
        if (
            ($placement->aimAt !== null || $placement->aimAtFocus)
            && ($placement->yawDeg !== 0.0 || $placement->pitchDeg !== 0.0)
        ) {
            // An arc's yaw does not live in `yaw_deg`, so this stays the same rule it always was.
            $messages[] = 'aiming already sets yaw and pitch — remove yaw_deg/pitch_deg';
        }

        if ($messages !== []) {
            return $messages;
        }

        return $placement->arc?->problems($device, $pitchDeg) ?? [];
    }

    /**
     * The cabinets a placement produces. The only place expansion happens.
     *
     * @return list<PlacementCopy>
     */
    private function copies(Placement $placement, DeviceSpec $device, float $pitchDeg): array
    {
        if ($placement->arc !== null) {
            return $placement->arc->seats($device, $pitchDeg);
        }

        $step = $placement->repeatStep ?? [0.0, 0.0, 0.0];
        $last = $placement->repeatCount - 1;

        $copies = [];
        for ($index = 0; $index <= $last; ++$index) {
            $copies[] = new PlacementCopy(
                $index,
                [$step[0] * $index, $step[1] * $index, $step[2] * $index],
                null,
                $index === $last,
            );
        }

        return $copies;
    }

    /**
     * The rig's x centre and the y of its front face.
     *
     * Applies every rotation that does **not** depend on the focus — an arc's yaw, an explicitly stated
     * yaw, roll, and pitch where the placement is not aimed. Anything that did depend on aiming would be
     * circular, because the focus point is derived from this and aiming is derived from the focus point.
     *
     * Getting this wrong is not cosmetic: a concave arc's outer cabinets stand well in front of its
     * middle one, and treating them as unrotated puts the front face 62 mm too far back, which lands the
     * focus that much further out than the scene asked for. The arc is measured here at zero tilt, so
     * there is a couple of centimetres of slack left in the other direction — it does not compound,
     * because the focus only feeds back into the tilt.
     *
     * @return array{float, float}
     */
    private function frontCentre(SceneSpec $scene): array
    {
        /** @var array<string, array{float, float}> $ground */
        $ground = [];
        $minX = $minY = INF;
        $maxX = -INF;

        foreach ($scene->placements as $placement) {
            $device = $this->devicesById[$placement->deviceId] ?? null;
            if ($device === null) {
                continue;
            }

            $base = $placement->at;
            if ($base === null && $placement->on !== null) {
                $base = $ground[$placement->on] ?? null;
            }
            if ($base === null) {
                continue;
            }

            $aimed = $placement->aimAt !== null || $placement->aimAtFocus;
            $pitch = $aimed ? 0.0 : $placement->pitchDeg;
            $yaw = $aimed ? 0.0 : $placement->yawDeg;

            // A placement the compiler is going to reject contributes nothing, and skipping it here is
            // what lets `copies()` assume the geometry resolves.
            if ($this->validate($placement, $device, $pitch) !== []) {
                continue;
            }

            foreach ($this->copies($placement, $device, $pitch) as $copy) {
                $x = $base[0] + $copy->offset[0];
                $y = $base[1] + $copy->offset[1];

                $box = (new PlacedDevice(
                    $placement->id,
                    $device,
                    [$x, $y, 0.0],
                    new Orientation($pitch, $placement->rollDeg, $copy->yawDeg ?? $yaw),
                ))->worldBox();

                $minX = min($minX, $box['min'][0]);
                $maxX = max($maxX, $box['max'][0]);
                $minY = min($minY, $box['min'][1]);

                if ($copy->isAnchor) {
                    $ground[$placement->id] = [$x, $y];
                }
            }
        }

        if ($minX === INF) {
            return [0.0, 0.0];
        }

        return [($minX + $maxX) / 2, $minY];
    }

    /**
     * Where the first (or only) copy of a placement sits.
     *
     * @param array<string, PlacedDevice> $byId
     * @param callable(string):void $add
     * @return array{float, float, float}|null
     */
    private function resolveBase(Placement $placement, DeviceSpec $device, array $byId, callable $add): ?array
    {
        if ($placement->on === null) {
            if ($placement->at === null) {
                $add("placement '{$placement->id}': needs either `at` or `on`");

                return null;
            }

            return [$placement->at[0], $placement->at[1], 0.0];
        }

        $support = $byId[$placement->on] ?? null;
        if ($support === null) {
            $add("placement '{$placement->id}': `on: {$placement->on}` must name an earlier placement");

            return null;
        }

        // Stacking is why heights never have to be written into a scene: the supported cabinet
        // starts exactly where the one below it ends, whatever the specs say today.
        $x = $placement->at[0] ?? $support->position[0];
        $y = $placement->at[1] ?? $support->position[1];

        return [$x, $y, $support->topZ()];
    }
}
