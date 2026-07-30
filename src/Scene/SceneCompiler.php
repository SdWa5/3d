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

            if ($placement->repeatCount < 1) {
                $add("placement '{$placement->id}': repeat.count must be at least 1");
                continue;
            }
            if ($placement->repeatCount > 1 && $placement->repeatStep === null) {
                $add("placement '{$placement->id}': repeat.count > 1 needs a repeat.step");
                continue;
            }

            if ($placement->aimAt !== null && $placement->aimAtFocus) {
                $add("placement '{$placement->id}': use either `aim: focus` or `aim_at`, not both");
                continue;
            }
            if (
                ($placement->aimAt !== null || $placement->aimAtFocus)
                && ($placement->yawDeg !== 0.0 || $placement->pitchDeg !== 0.0)
            ) {
                $add("placement '{$placement->id}': aiming already sets yaw and pitch — remove yaw_deg/pitch_deg");
                continue;
            }

            $base = $this->resolveBase($placement, $device, $byId, $add);
            if ($base === null) {
                continue;
            }

            $step = $placement->repeatStep ?? [0.0, 0.0, 0.0];
            for ($index = 0; $index < $placement->repeatCount; ++$index) {
                $id = $placement->repeatCount > 1
                    ? sprintf('%s-%d', $placement->id, $index + 1)
                    : $placement->id;

                $position = [
                    $base[0] + $step[0] * $index,
                    $base[1] + $step[1] * $index,
                    $base[2] + $step[2] * $index,
                ];

                // Aim is resolved per copy, so a repeated row of tops each turns towards the target
                // rather than all sharing the first one's angle.
                $target = $placement->aimAt ?? ($placement->aimAtFocus ? $focusPoint : null);
                $orientation = $target === null
                    ? new Orientation($placement->pitchDeg, $placement->rollDeg, $placement->yawDeg)
                    : Orientation::aimedAt(
                        $position,
                        $target,
                        $device->dimensions->height,
                        $placement->rollDeg,
                    );

                $entry = new PlacedDevice($id, $device, $position, $orientation);

                $placed[] = $entry;
                // `on` refers to the placement as a whole; the last repeat is the useful anchor.
                $byId[$placement->id] = $entry;
            }
        }

        return ['placed' => $placed, 'violations' => $violations];
    }

    /**
     * The rig's x centre and the y of its front face, from ground positions and unrotated depths only.
     *
     * Deliberately independent of orientation: the focus point is derived from this, and aiming is
     * derived from the focus point, so anything here that depended on aiming would be circular.
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

            $step = $placement->repeatStep ?? [0.0, 0.0, 0.0];
            $copies = max(1, $placement->repeatCount);
            for ($index = 0; $index < $copies; ++$index) {
                $x = $base[0] + $step[0] * $index;
                $y = $base[1] + $step[1] * $index;
                $minX = min($minX, $x - $device->dimensions->width / 2);
                $maxX = max($maxX, $x + $device->dimensions->width / 2);
                $minY = min($minY, $y - $device->dimensions->depth / 2);
                $ground[$placement->id] = [$x, $y];
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
