<?php

declare(strict_types=1);

namespace App\Load;

use App\Spec\DeviceSpec;

/**
 * Turns "these fourteen things go in the Movano" into fourteen places inside its bay.
 *
 * **The load plan assigns and this places, and the two are different problems.** {@see LoadPlanner} answers which
 * vehicle each device rides in, which is a question about mass and needs no geometry beyond a volume. Where each one
 * *stands* is 3D packing, which is NP-hard and which this deliberately does not attempt to solve well. What it does
 * is apply one stated rule so a pack can be **looked at**, which is the whole reason it exists.
 *
 * **THE RULE, WRITTEN DOWN SO THE PICTURE CAN BE ARGUED WITH.** Two passes over the bay:
 *
 * 1. **The floor, heaviest first.** Units are laid across the bay's width in rows, each row advancing along the
 *    depth by the deepest thing in it. Heaviest-first comes from the plan and is what puts the mass low without a
 *    rule of its own — a 90 kg SKRAM takes floor before a 20 kg top ever asks for it.
 * 2. **Then columns.** Whatever has no floor left is stacked on a column whose footprint can hold it and whose
 *    height still leaves room under the bay roof. Directly on top, so a column is a column: `on:` reads the
 *    supporting cabinet's own height from its spec, which is why no z is ever written into a scene.
 *
 * **What this is not.** It does not rotate anything, it does not interleave shapes, it leaves the air between a
 * horn flare and its neighbour unused, and it packs a trapezoid as though it were its bounding box. A real pack is
 * tighter than this and a real packer would be a different program. Anything this rule cannot place is reported as
 * **overflow** rather than squeezed in, because a diagram that quietly drops a cabinet is worse than one that says
 * it ran out of room.
 *
 * **The arch width binds the floor and not the columns**, which is the one piece of real geometry in here. On the
 * Movano the bay is 1.765 m wide and 1.380 m between the wheel arches, so the floor row is 385 mm narrower than
 * anything stacked above arch height. Ignoring that is how a diagram promises floor space that does not exist.
 */
final class PackLayout
{
    /** Air left between neighbours, so a picture reads as separate cabinets rather than one welded mass. */
    private const GAP_M = 0.02;

    /**
     * Where each unit of a plan's load stands, in the bay's own frame.
     *
     * @return array{
     *     placed: list<array{id: string, device: DeviceSpec, at: array{float, float}, on: ?string}>,
     *     overflow: list<DeviceSpec>
     * }
     */
    public function forPlan(LoadPlan $plan): array
    {
        $bay = $plan->vehicle->vehicle?->loadBay;
        if (null === $bay) {
            // **An open bed: no width, no depth, no roof to stack under.** The trailer is this case. Everything goes
            // in one row along the vehicle's own footprint, which is a diagram of "it is on the trailer" and makes
            // no claim about how. See {@see LoadPlan::exceedsTheBay} for why a bayless vehicle gets no space answer
            // either.
            return $this->openBed($plan);
        }

        $arches = $plan->vehicle->vehicle?->widthBetweenArchesM ?? $bay->width;
        // The bay is drawn flush to one end of the vehicle by `blender/lib/bay.py`, so the layout has to agree with
        // the picture: same near edge, same centre line.
        $near = $plan->vehicle->dimensions->depth / 2.0 - $bay->depth;

        // **Prefixed by the vehicle, because a placement id has to be unique across the whole scene.** Numbering
        // per bin produced a `flexy-folded-horn-hybrid-3` in both vans and the compiler refused the scene outright,
        // which is the right refusal — `on:` names a placement, and two placements with one name is ambiguous about
        // what is standing on what.
        $prefix = $plan->vehicle->id.'-';
        $units = self::units($plan);
        $columns = [];
        $overflow = [];
        $placed = [];

        $cursorX = -$arches / 2.0;
        $cursorY = $near;
        $rowDepth = 0.0;
        $index = 0;

        foreach ($units as $unit) {
            $width = $unit->dimensions->width;
            $depth = $unit->dimensions->depth;

            if ($cursorX + $width > $arches / 2.0 + 1e-9) {
                $cursorX = -$arches / 2.0;
                $cursorY += $rowDepth + self::GAP_M;
                $rowDepth = 0.0;
            }

            if ($cursorY + $depth > $near + $bay->depth + 1e-9) {
                // Floor exhausted. Everything from here on either finds a column or overflows.
                $stacked = self::stackOn($columns, $unit, $bay->height);
                if (null === $stacked) {
                    $overflow[] = $unit;
                    continue;
                }
                $id = sprintf('%s%s-%d', $prefix, $unit->id, ++$index);
                $placed[] = ['id' => $id, 'device' => $unit, 'at' => $stacked['at'], 'on' => $stacked['on']];
                $columns[$stacked['column']]['top'] = $id;
                $columns[$stacked['column']]['height'] += $unit->dimensions->height;
                continue;
            }

            $id = sprintf('%s%s-%d', $prefix, $unit->id, ++$index);
            $at = [$cursorX + $width / 2.0, $cursorY + $depth / 2.0];
            $placed[] = ['id' => $id, 'device' => $unit, 'at' => $at, 'on' => null];
            $columns[] = [
                'at' => $at,
                'width' => $width,
                'depth' => $depth,
                'height' => $unit->dimensions->height,
                'top' => $id,
            ];

            $cursorX += $width + self::GAP_M;
            $rowDepth = max($rowDepth, $depth);
        }

        return ['placed' => $placed, 'overflow' => $overflow];
    }

    /**
     * A column that can take this unit: wide and deep enough for it, with room under the roof.
     *
     * **Shortest fit**, which keeps a pack level rather than growing one tower while the rest of the bay stays a
     * single layer high. The docblock here claimed "widest fit, ties to the shortest" for its first version, and the
     * code has never done anything but pick the shortest — there is no widest-fit logic in it at all. Widest fit
     * would reserve a big column for a big cabinet, which sounds prudent and buys nothing here: units arrive
     * heaviest first and the heavy ones are mostly the big ones, so they take their floor before anything small is
     * offered a column.
     *
     * @param list<array{at: array{float, float}, width: float, depth: float, height: float, top: string}> $columns
     *
     * @return array{column: int, at: array{float, float}, on: string}|null
     */
    private static function stackOn(array $columns, DeviceSpec $unit, float $roof): ?array
    {
        $best = null;
        foreach ($columns as $i => $column) {
            if ($column['width'] + 1e-9 < $unit->dimensions->width) {
                continue;
            }
            if ($column['depth'] + 1e-9 < $unit->dimensions->depth) {
                continue;
            }
            if ($column['height'] + $unit->dimensions->height > $roof + 1e-9) {
                continue;
            }
            if (null === $best || $column['height'] < $columns[$best]['height']) {
                $best = $i;
            }
        }

        return null === $best
            ? null
            : ['column' => $best, 'at' => $columns[$best]['at'], 'on' => $columns[$best]['top']];
    }

    /**
     * An open bed: one row along the vehicle's own footprint, and nothing stacked.
     *
     * @return array{
     *     placed: list<array{id: string, device: DeviceSpec, at: array{float, float}, on: ?string}>,
     *     overflow: list<DeviceSpec>
     * }
     */
    private function openBed(LoadPlan $plan): array
    {
        $placed = [];
        $overflow = [];
        $cursor = -$plan->vehicle->dimensions->depth / 2.0;
        $index = 0;

        foreach (self::units($plan) as $unit) {
            $depth = $unit->dimensions->depth;
            if ($cursor + $depth > $plan->vehicle->dimensions->depth / 2.0 + 1e-9) {
                $overflow[] = $unit;
                continue;
            }
            $placed[] = [
                'id' => sprintf('%s%s-%d', $plan->vehicle->id.'-', $unit->id, ++$index),
                'device' => $unit,
                'at' => [0.0, $cursor + $depth / 2.0],
                'on' => null,
            ];
            $cursor += $depth + self::GAP_M;
        }

        return ['placed' => $placed, 'overflow' => $overflow];
    }

    /**
     * A plan's items as individual units, in the order the planner ranked them — heaviest first.
     *
     * @return list<DeviceSpec>
     */
    private static function units(LoadPlan $plan): array
    {
        $units = [];
        foreach ($plan->items as ['spec' => $spec, 'count' => $count]) {
            for ($i = 0; $i < $count; ++$i) {
                $units[] = $spec;
            }
        }

        return $units;
    }
}
