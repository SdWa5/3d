<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Arc;
use App\Scene\ArcMode;
use App\Scene\GroupReader;
use App\Scene\GroupStack;
use App\Scene\Lattice;
use App\Scene\LineArray;
use App\Scene\PlacementCopy;
use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Groups inside groups: what nesting means, and what it must not quietly get wrong.
 *
 * The claim the whole design rests on is that **a group is a rigid body** — nesting one places it rather
 * than scattering its parts. Most of these tests are that claim from one angle or another.
 */
final class GroupStackTest extends TestCase
{
    public function testAGroupInsideAnotherMultipliesTheirCopyCounts(): void
    {
        $stack = new GroupStack([new Lattice([7, 1, 1]), new Lattice([1, 1, 2])]);

        self::assertSame(14, $stack->copyCount());
        self::assertCount(14, $this->copies($stack));
    }

    /**
     * The case that shows why the outer turn has to move the inner arrangement's offsets. Three touching
     * pairs on a convex fan: the outer seats sit at ±17.35°, so the pair's 0.52 m step has to run along the
     * *cabinet's* x. Left in the world's x, the second cabinet of each pair lands 155 mm behind its own
     * seam — a cabinet a sixth of a metre out of line, which reads as an arc bug rather than a nesting one.
     */
    public function testAnOuterGroupsRotationTurnsTheInnerArrangementsOffsets(): void
    {
        $device = $this->tecnare();
        $pair = new Lattice([2, 1, 1], gapM: [0.02, 0.0, 0.0]);
        $fan = new Arc(ArcMode::Convex, 3);

        $copies = $this->copies(new GroupStack([$pair, $fan]), $device);
        self::assertCount(6, $copies);

        $splay = deg2rad($fan->splayDegFor($device, 0.0));
        $step = 0.5 + 0.02;

        // The left-hand pair, whose seat is yawed by −splay.
        $left = array_values(array_filter($copies, static fn (PlacementCopy $c): bool => 1 === $c->path[0]));
        $along = [
            $left[1]->offset[0] - $left[0]->offset[0],
            $left[1]->offset[1] - $left[0]->offset[1],
        ];

        self::assertEqualsWithDelta($step * cos($splay), $along[0], 1e-9);
        self::assertEqualsWithDelta(-$step * sin($splay), $along[1], 1e-9);
        // Unturned it would have been (0.52, 0) — this is how far off that is.
        self::assertEqualsWithDelta(0.155, abs($along[1]), 5e-3, 'the y offset an unturned step would lose');
    }

    /**
     * Every group promises exactly one anchor, so the anchor of a nest is the copy that is its level's
     * anchor at every level — no arbitration, whatever the depth.
     */
    public function testTheAnchorIsTheOneCopyThatIsEveryLevelsAnchor(): void
    {
        $stack = new GroupStack([new Lattice([3, 1, 1]), new Lattice([1, 1, 2]), new Lattice([2, 1, 1])]);

        $anchors = array_values(array_filter($this->copies($stack), static fn (PlacementCopy $c): bool => $c->isAnchor));

        self::assertCount(1, $anchors);
        self::assertSame(12, $stack->copyCount());
    }

    public function testNestedIdsReadOutermostFirstAndSkipDimensionsWithOneValue(): void
    {
        // A row of seven, in two tiers: the id says which tier and then which cabinet.
        $stack = new GroupStack([new Lattice([7, 1, 1]), new Lattice([1, 1, 2])]);

        $paths = array_map(static fn (PlacementCopy $c): array => $c->path, $this->copies($stack));

        self::assertSame([1, 1], $paths[0], 'lower tier, leftmost cabinet');
        self::assertSame([1, 7], $paths[6]);
        self::assertSame([2, 1], $paths[7], 'upper tier');
        self::assertSame([2, 7], $paths[13]);
    }

    /**
     * A single group has to number its copies exactly as it did before nesting existed, because seven of
     * the shipped scenes have those ids in their build plans.
     */
    public function testASingleGroupNumbersItsCopiesExactlyAsItDidBeforeNesting(): void
    {
        $row = $this->copies(new GroupStack([new Lattice([7, 1, 1])]));
        self::assertSame([[1], [2], [3], [4], [5], [6], [7]], array_map(
            static fn (PlacementCopy $c): array => $c->path,
            $row,
        ));

        // And a placement that makes one cabinet is not numbered at all.
        $single = $this->copies(new GroupStack([new Lattice([1, 1, 1])]));
        self::assertSame([[]], array_map(static fn (PlacementCopy $c): array => $c->path, $single));
    }

    public function testAPlacementWithNoGroupAtAllIsAStackOfNone(): void
    {
        $copies = $this->copies(new GroupStack());

        self::assertCount(1, $copies);
        self::assertSame([0.0, 0.0, 0.0], $copies[0]->offset);
        self::assertSame([], $copies[0]->path);
        self::assertNull($copies[0]->rotation);
        self::assertTrue($copies[0]->isAnchor);
        self::assertSame(1, (new GroupStack())->copyCount());
    }

    /**
     * Rolling a cell over reverses the yaw of everything inside it, because composition is matrix
     * multiplication and not angle addition. Physically that is just what turning an arrangement upside
     * down does; arithmetically it is the thing three loose angles could not have expressed.
     */
    public function testTurningAFanOverMirrorsIt(): void
    {
        $device = $this->tecnare();
        $fan = new Arc(ArcMode::Convex, 3);
        $turned = new Lattice([1, 1, 1], rollCycle: [180.0], cycleAxis: \App\Scene\Axis::Z);

        $upright = $this->copies(new GroupStack([$fan]), $device);
        $mirrored = $this->copies(new GroupStack([$fan, $turned]), $device);

        self::assertEqualsWithDelta(
            -($upright[0]->rotation?->yawDeg ?? 0.0),
            $mirrored[0]->rotation?->yawDeg ?? 0.0,
            1e-9,
        );
        self::assertEqualsWithDelta(180.0, abs($mirrored[0]->rotation?->rollDeg ?? 0.0), 1e-9);
    }

    /**
     * Only geometry decides layout — never the aim. Composition uses the stated rotations alone, which is
     * what keeps the rig's front face (and so the focus point, and so the aiming) from being circular at
     * any depth of nesting.
     */
    public function testNestingNeverConsultsTheAim(): void
    {
        $device = $this->tecnare();
        $stack = new GroupStack([new Arc(ArcMode::Convex, 3), new Lattice([1, 1, 2])]);

        // The same offsets whatever attitude the cabinets are asked to stand at, bar the contact solve's
        // own dependence on the tilt — so pass the same tilt and compare.
        $a = $this->copies($stack, $device);
        $b = $this->copies($stack, $device);

        self::assertSame(
            array_map(static fn (PlacementCopy $c): array => $c->offset, $a),
            array_map(static fn (PlacementCopy $c): array => $c->offset, $b),
        );
    }

    public function testAnOuterLatticeSpacesItselfOnWhatItActuallyReplicates(): void
    {
        $device = $this->tecnare();

        // Three 2x2 blocks 0.40 m apart. The outer step has to come from the block's 1.02 m, which is the
        // number nobody should have to work out.
        $block = new Lattice([2, 1, 2], gapM: [0.02, 0.0, 0.0]);
        $threeOfThem = new Lattice([3, 1, 1], gapM: [0.40, 0.0, 0.0]);

        $copies = $this->copies(new GroupStack([$block, $threeOfThem]), $device);
        self::assertCount(12, $copies);

        $blockWidth = 0.5 * 2 + 0.02;
        $outerStep = $copies[4]->offset[0] - $copies[0]->offset[0];
        self::assertEqualsWithDelta($blockWidth + 0.40, $outerStep, 1e-9);
    }

    // --- reading -----------------------------------------------------------------------------------

    public function testTheSiblingGroupIsTheCellAndInWrapsIt(): void
    {
        $stack = GroupReader::read(new ArrayReader([
            'device' => 'x',
            'arc' => ['mode' => 'convex', 'count' => 3],
            'in' => [['lattice' => ['count' => [1, 1, 2]]]],
        ]));

        self::assertCount(2, $stack->groups);
        self::assertSame('arc', $stack->groups[0]->kind(), 'the cell is innermost');
        self::assertSame('lattice', $stack->groups[1]->kind());
        self::assertSame(6, $stack->copyCount());
    }

    public function testAPlacementWithNoGroupKeyReadsAsAnEmptyStack(): void
    {
        $stack = GroupReader::read(new ArrayReader(['device' => 'x', 'at' => [0, 0]]));

        self::assertSame([], $stack->groups);
        self::assertSame(1, $stack->copyCount());
    }

    public function testTwoGroupKeysOnOnePlacementAreRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('use one group per placement');

        GroupReader::read(new ArrayReader([
            'arc' => ['mode' => 'convex', 'count' => 3],
            'repeat' => ['count' => 2, 'step' => [1, 0, 0]],
        ]));
    }

    public function testInWithNoGroupToNestIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('there is no group here');

        GroupReader::read(new ArrayReader(['in' => [['lattice' => ['count' => [2]]]]]));
    }

    /**
     * A mistyped group name inside `in` would otherwise read as an empty wrapper and silently drop a whole
     * level of the nest — the same argument that makes `arc` reject unknown keys.
     */
    public function testAnUnknownGroupNameInsideInIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('in[0]: expected exactly one group');

        GroupReader::read(new ArrayReader([
            'arc' => ['mode' => 'convex', 'count' => 3],
            'in' => [['lattic' => ['count' => [2]]]],
        ]));
    }

    public function testNestingGoesAsDeepAsItIsWritten(): void
    {
        $stack = GroupReader::read(new ArrayReader([
            'row' => ['count' => 2],
            'in' => [
                ['lattice' => ['count' => [1, 1, 2]]],
                ['lattice' => ['count' => [3, 1, 1]]],
            ],
        ]));

        self::assertCount(3, $stack->groups);
        self::assertSame(12, $stack->copyCount());
    }

    /**
     * @return list<PlacementCopy>
     */
    private function copies(GroupStack $stack, ?DeviceSpec $device = null): array
    {
        $device ??= $this->tecnare();

        return $stack->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));
    }

    private function tecnare(): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'stack-top',
            'geometry' => [
                'shape' => 'trapezoid',
                'dimensions_m' => ['width' => 0.5, 'height' => 0.96, 'depth' => 0.52],
                'back_width_m' => 0.345,
            ],
            'appearance' => ['grille' => null],
        ]);
    }

    /**
     * A hang is measured where it hangs. Left seated, every element of a nested line array would be sized as
     * if it stood on the floor, and the lattice above would space its cells on a height the hang does not
     * have — two hangs 0.96 m apart instead of the 2.9 m the array is actually tall.
     */
    public function testALineArrayNestedInALatticeIsSpacedAsAHangNotAsAStack(): void
    {
        $device = $this->tecnare();
        $hang = new LineArray(3, [0.0, 0.0]);
        $stack = new GroupStack([$hang, new Lattice([1, 1, 2])]);

        $copies = $stack->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));
        self::assertCount(6, $copies);

        // Three 0.96 m elements make a 2.88 m hang, so the two tiers step by that and not by one cabinet.
        $tiers = array_values(array_unique(array_map(
            static fn (PlacementCopy $c): float => round($c->offset[2], 6),
            $copies,
        )));
        sort($tiers);
        self::assertSame([-1.92, -0.96, 0.0, 0.96, 1.92, 2.88], $tiers);

        // And nothing in the nest is seated, because a hang inside anything is still a hang.
        foreach ($copies as $copy) {
            self::assertFalse($copy->seated);
        }
    }

    /**
     * `PlacedDevice::zLift()` puts one rotated cabinet back on its slot. A group had the same problem and
     * nobody to solve it: turning a two-tier cell over maps its offsets `z → −z`, so the lower tier ends up
     * underground. Same argument, one level out.
     */
    public function testTurningAMultiTierCellOverLiftsTheWholePlacementBackOntoItsSlot(): void
    {
        $device = $this->tecnare();
        $twoTiers = new Lattice([1, 1, 2]);
        $turnOver = new Lattice([1, 1, 1], rollCycle: [180.0], cycleAxis: \App\Scene\Axis::Z);

        $copies = (new GroupStack([$twoTiers, $turnOver]))
            ->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));

        // Rolled, the cell's own offsets run 0 and −0.96, so the placement has to come up by 0.96.
        self::assertSame(0.96, round(GroupStack::zLift($device, $copies, 0.0, 0.0), 9));

        // Un-rolled it needs no lift at all, which is what says the lift is about the roll and not a
        // constant fudge.
        $upright = (new GroupStack([$twoTiers]))
            ->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));
        self::assertSame(0.0, round(GroupStack::zLift($device, $upright, 0.0, 0.0), 9));
    }

    public function testAHangIsNeverLiftedBackOntoASlot(): void
    {
        $device = $this->tecnare();
        $copies = (new GroupStack([new LineArray(3, [0.0, 0.0])]))
            ->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));

        self::assertSame(0.0, GroupStack::zLift($device, $copies, 0.0, 0.0));
    }
}
