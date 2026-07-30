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

            $base = $this->resolveBase($placement, $device, $byId, $add);
            if ($base === null) {
                continue;
            }

            $step = $placement->repeatStep ?? [0.0, 0.0, 0.0];
            for ($index = 0; $index < $placement->repeatCount; ++$index) {
                $id = $placement->repeatCount > 1
                    ? sprintf('%s-%d', $placement->id, $index + 1)
                    : $placement->id;

                $entry = new PlacedDevice($id, $device, [
                    $base[0] + $step[0] * $index,
                    $base[1] + $step[1] * $index,
                    $base[2] + $step[2] * $index,
                ], $placement->yawDeg, $placement->rollDeg);

                $placed[] = $entry;
                // `on` refers to the placement as a whole; the last repeat is the useful anchor.
                $byId[$placement->id] = $entry;
            }
        }

        return ['placed' => $placed, 'violations' => $violations];
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
