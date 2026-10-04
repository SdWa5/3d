<?php

declare(strict_types=1);

namespace App\Load;

use App\Scene\Orientation;
use App\Scene\PlacedDevice;
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
 * **A unit is turned only when it does not fit as it stands** (LOAD-6). Each pass tries the unit in its spec
 * orientation first, then in the other axis-aligned turns, lowest first and then shallowest along the bay. A 2 m truss
 * across a 1.38 m floor therefore lies along the bay, while a cabinet that fits upright stays upright. A unit whose
 * spec says `transport: { upright: true }` is only ever turned about the vertical. **An open bed lays everything as low
 * as it goes**, since nothing stacks there and a tall thing standing on a trailer is the worse picture.
 *
 * **Nothing moves until a place is found.** The first version advanced the row cursor before it knew whether the unit
 * fitted, so one unit that overflowed abandoned the rest of its row to every unit after it.
 *
 * **What this is not.** It does not interleave shapes, it leaves the air between a horn flare and its neighbour
 * unused, and it packs a trapezoid as though it were its bounding box. A real pack is tighter than this and a real
 * packer would be a different program. Anything this rule cannot place is reported as
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
     * How much a stacked unit may reach past its column on each side: half the gap, so two neighbours that both
     * overhang still do not touch. The floor keeps the same half gap off every wall of the bay, so an overhang never
     * leaves it either. Without it an 0.600 m Achenbach could not stand on an 0.591 m Flexy, and the Movano lost
     * three units to 9 mm.
     */
    private const OVERHANG_M = self::GAP_M / 2.0;

    /**
     * The six axis-aligned turns, the spec orientation first. Pitch and roll are never combined, because the two
     * together only repeat a box these already give.
     *
     * @var list<array{float, float, float}> pitch, roll, yaw in degrees
     */
    private const TURNS = [[0.0, 0.0, 0.0], [0.0, 0.0, 90.0], [0.0, 90.0, 0.0], [0.0, 90.0, 90.0], [90.0, 0.0, 0.0], [90.0, 0.0, 90.0]];

    /**
     * Where each unit of a plan's load stands, in the bay's own frame.
     *
     * `at` is the unit's origin, which is what a scene writes, and `box` is the space it fills once turned. The two
     * differ for a unit on its side, because a turn of 90° about the bottom-centre origin moves the box off it.
     *
     * @return array{
     *     placed: list<array{id: string, device: DeviceSpec, at: array{float, float}, on: ?string, turn: Orientation, box: array{min: array{float, float, float}, max: array{float, float, float}}}>,
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

        $arches = $plan->vehicle->vehicle->widthBetweenArchesM ?? $bay->width;
        // The bay is drawn flush to one end of the vehicle by `blender/lib/bay.py`, so the layout has to agree with
        // the picture: same near edge, same centre line.
        $near = $plan->vehicle->dimensions->depth / 2.0 - $bay->depth + self::OVERHANG_M;
        $far = $near + $bay->depth - 2.0 * self::OVERHANG_M;
        $left = -$arches / 2.0 + self::OVERHANG_M;

        // **Prefixed by the vehicle, because a placement id has to be unique across the whole scene.** Numbering
        // per bin produced a `flexy-folded-horn-hybrid-3` in both vans and the compiler refused the scene outright,
        // which is the right refusal — `on:` names a placement, and two placements with one name is ambiguous about
        // what is standing on what.
        $prefix = $plan->vehicle->id.'-';
        $columns = [];
        $overflow = [];
        $placed = [];

        $cursorX = $left;
        $cursorY = $near;
        $rowDepth = 0.0;
        $index = 0;

        foreach (self::units($plan) as $unit) {
            $poses = self::poses($unit);

            // **Standing as the spec has it, anywhere, before turned.** The floor and then a column with the spec
            // orientation, and only then the same two with every other turn. Turning first let a seventh Flexy lie
            // across the Ducato's last row, which was the row both amp racks needed.
            foreach ([[$poses[0]], array_slice($poses, 1)] as $pass) {
                $floor = self::onFloor($pass, $cursorX, $cursorY, $rowDepth, $left, $far, $bay->height);
                if (null !== $floor) {
                    ['pose' => $pose, 'spot' => [$x, $y, $newRow]] = $floor;
                    [$width, $depth, $height] = $pose['size'];
                    if ($newRow) {
                        $cursorY = $y;
                        $rowDepth = 0.0;
                    }
                    $centre = [$x + $width / 2.0, $y + $depth / 2.0];
                    $id = sprintf('%s%s-%d', $prefix, $unit->id, ++$index);
                    $placed[] = self::entry($id, $unit, $pose, $centre, null, 0.0);
                    $columns[] = ['at' => $centre, 'width' => $width, 'depth' => $depth, 'height' => $height, 'top' => $id];
                    $cursorX = $x + $width + self::GAP_M;
                    $rowDepth = max($rowDepth, $depth);
                    continue 2;
                }

                $stacked = self::stackOn($columns, $pass, $bay->height);
                if (null !== $stacked) {
                    $column = $columns[$stacked['column']];
                    $id = sprintf('%s%s-%d', $prefix, $unit->id, ++$index);
                    $placed[] = self::entry($id, $unit, $stacked['pose'], $column['at'], $column['top'], $column['height']);
                    $columns[$stacked['column']]['top'] = $id;
                    $columns[$stacked['column']]['height'] += $stacked['pose']['size'][2];
                    continue 2;
                }
            }

            $overflow[] = $unit;
        }

        return ['placed' => $placed, 'overflow' => $overflow];
    }

    /**
     * The best floor spot for one of these poses, against the row as it stands or a fresh row behind it.
     *
     * Nothing moves here, so a unit that fits nowhere leaves the row open for the next one. Within the poses the
     * current row beats a fresh one, then the lower box, then the shallower one along the bay.
     *
     * @param list<array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}}> $poses
     *
     * @return array{pose: array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}}, spot: array{float, float, bool}}|null
     */
    private static function onFloor(array $poses, float $cursorX, float $cursorY, float $rowDepth, float $left, float $far, float $roof): ?array
    {
        $best = null;
        foreach ($poses as $pose) {
            [$width, $depth, $height] = $pose['size'];
            if ($width > -2.0 * $left + 1e-9 || $height > $roof + 1e-9) {
                continue;
            }
            if ($cursorX + $width <= -$left + 1e-9 && $cursorY + $depth <= $far + 1e-9) {
                $spot = [$cursorX, $cursorY, false];
            } else {
                $y = $cursorY + $rowDepth + ($rowDepth > 0.0 ? self::GAP_M : 0.0);
                if ($y + $depth > $far + 1e-9) {
                    continue;
                }
                $spot = [$left, $y, true];
            }
            $rank = [$spot[2] ? 1 : 0, $height, $depth];
            if (null === $best || $rank < $best['rank']) {
                $best = ['rank' => $rank, 'pose' => $pose, 'spot' => $spot];
            }
        }

        return null === $best ? null : ['pose' => $best['pose'], 'spot' => $best['spot']];
    }

    /**
     * The turns a unit may take, each with the box it fills and where that box's centre sits from the origin.
     *
     * Read off {@see PlacedDevice} rather than swapped by hand, so a trapezoid or a bottom-centre origin turns exactly
     * as the scene compiler will turn it. Turns that fill the same box as an earlier one are dropped.
     *
     * @return list<array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}}>
     */
    private static function poses(DeviceSpec $unit): array
    {
        $poses = [];
        $seen = [];
        foreach (self::TURNS as [$pitch, $roll, $yaw]) {
            if ($unit->isUpright() && (0.0 !== $pitch || 0.0 !== $roll)) {
                continue;
            }
            $turn = new Orientation($pitch, $roll, $yaw);
            ['min' => $min, 'max' => $max] = (new PlacedDevice('', $unit, [0.0, 0.0, 0.0], $turn))->box();
            $size = [$max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]];
            $key = vsprintf('%.4f/%.4f/%.4f', $size);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $poses[] = ['turn' => $turn, 'size' => $size, 'offset' => [($min[0] + $max[0]) / 2.0, ($min[1] + $max[1]) / 2.0]];
        }

        return $poses;
    }

    /**
     * One placed unit, its box centred on `$centre` and standing at `$floor`.
     *
     * @param array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}} $pose
     * @param array{float, float} $centre
     *
     * @return array{id: string, device: DeviceSpec, at: array{float, float}, on: ?string, turn: Orientation, box: array{min: array{float, float, float}, max: array{float, float, float}}}
     */
    private static function entry(string $id, DeviceSpec $unit, array $pose, array $centre, ?string $on, float $floor): array
    {
        [$width, $depth, $height] = $pose['size'];

        return [
            'id' => $id,
            'device' => $unit,
            'at' => [$centre[0] - $pose['offset'][0], $centre[1] - $pose['offset'][1]],
            'on' => $on,
            'turn' => $pose['turn'],
            'box' => [
                'min' => [$centre[0] - $width / 2.0, $centre[1] - $depth / 2.0, $floor],
                'max' => [$centre[0] + $width / 2.0, $centre[1] + $depth / 2.0, $floor + $height],
            ],
        ];
    }

    /**
     * A column that can take this unit in one of its turns: wide and deep enough for it within the overhang, with room
     * under the roof.
     *
     * **The shortest fit**, which keeps a pack level rather than growing one tower
     * while the rest of the bay stays a single layer high. Widest fit would reserve a big column for a big cabinet,
     * which sounds prudent and buys nothing here: units arrive heaviest first and the heavy ones are mostly the big
     * ones, so they take their floor before anything small is offered a column.
     *
     * @param list<array{at: array{float, float}, width: float, depth: float, height: float, top: string}> $columns
     * @param list<array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}}> $poses
     *
     * @return array{column: int, pose: array{turn: Orientation, size: array{float, float, float}, offset: array{float, float}}}|null
     */
    private static function stackOn(array $columns, array $poses, float $roof): ?array
    {
        $best = null;
        foreach ($poses as $pose) {
            [$width, $depth, $height] = $pose['size'];
            foreach ($columns as $i => $column) {
                if ($column['width'] + 2.0 * self::OVERHANG_M + 1e-9 < $width || $column['depth'] + 2.0 * self::OVERHANG_M + 1e-9 < $depth) {
                    continue;
                }
                if ($column['height'] + $height > $roof + 1e-9) {
                    continue;
                }
                if (null === $best || $column['height'] < $best['height']) {
                    $best = ['height' => $column['height'], 'column' => $i, 'pose' => $pose];
                }
            }
        }

        return null === $best ? null : ['column' => $best['column'], 'pose' => $best['pose']];
    }

    /**
     * An open bed: one row along the vehicle's own footprint, nothing stacked, and each unit laid as low as it goes
     * without reaching past the sides.
     *
     * @return array{
     *     placed: list<array{id: string, device: DeviceSpec, at: array{float, float}, on: ?string, turn: Orientation, box: array{min: array{float, float, float}, max: array{float, float, float}}}>,
     *     overflow: list<DeviceSpec>
     * }
     */
    private function openBed(LoadPlan $plan): array
    {
        $placed = [];
        $overflow = [];
        $width = $plan->vehicle->dimensions->width;
        $end = $plan->vehicle->dimensions->depth / 2.0;
        $cursor = -$end;
        $index = 0;

        foreach (self::units($plan) as $unit) {
            $best = null;
            foreach (self::poses($unit) as $pose) {
                [$across, $along, $height] = $pose['size'];
                if ($across > $width + 1e-9 || $cursor + $along > $end + 1e-9) {
                    continue;
                }
                $rank = [$height, $along];
                if (null === $best || $rank < $best['rank']) {
                    $best = ['rank' => $rank, 'pose' => $pose];
                }
            }
            if (null === $best) {
                $overflow[] = $unit;
                continue;
            }
            $along = $best['pose']['size'][1];
            $id = sprintf('%s%s-%d', $plan->vehicle->id.'-', $unit->id, ++$index);
            $placed[] = self::entry($id, $unit, $best['pose'], [0.0, $cursor + $along / 2.0], null, 0.0);
            $cursor += $along + self::GAP_M;
        }

        return ['placed' => $placed, 'overflow' => $overflow];
    }

    /**
     * A plan's items as individual units, in the order the planner ranked them — heaviest first.
     *
     * **Each unit is the device as it travels**, {@see DeviceSpec::packed()}, so every size read below is the
     * transport box. A folding stand packs at the 1.75 m it folds to rather than the 4 m it stands.
     *
     * @return list<DeviceSpec>
     */
    private static function units(LoadPlan $plan): array
    {
        $units = [];
        foreach ($plan->items as ['spec' => $spec, 'count' => $count]) {
            for ($i = 0; $i < $count; ++$i) {
                $units[] = $spec->packed();
            }
        }

        return $units;
    }
}
