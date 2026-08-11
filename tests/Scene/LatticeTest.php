<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Axis;
use App\Scene\GroupStack;
use App\Scene\Lattice;
use App\Scene\PlacementCopy;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The grid that works its own spacing out.
 *
 * The fixture is a 0.5 × 0.96 × 0.52 cabinet, so every spacing below is readable at a glance: 0.500 m
 * across, 0.520 m front to back, 0.960 m up — and 0.960 across once it is on its side.
 */
final class LatticeTest extends TestCase
{
    public function testARowIsSpacedByTheCabinetsOwnWidthAndCentredOnTheAnchor(): void
    {
        $copies = $this->copies(new Lattice([3, 1, 1]));

        self::assertSame([-0.5, 0.0, 0.0], $copies[0]->offset);
        self::assertSame([0.0, 0.0, 0.0], $copies[1]->offset);
        self::assertSame([0.5, 0.0, 0.0], $copies[2]->offset);
    }

    /**
     * The number `full-rig.yaml` carries by hand. Seven 591 mm Flexys with a 20 mm working gap step
     * 0.611 m, which is exactly the `step` that scene states — and the wall comes out 4.257 m wide,
     * centred on `at` instead of started from its left edge.
     */
    public function testAnAutoSpacedRowOfSevenLandsWhereTheHandComputedRowDid(): void
    {
        $flexy = $this->device(width: 0.591, height: 0.763, depth: 0.964);
        $copies = $this->copies(new Lattice([7, 1, 1], gapM: [0.02, 0.02, 0.0]), $flexy);

        $x = array_map(static fn (PlacementCopy $c): float => round($c->offset[0], 9), $copies);
        self::assertSame([-1.833, -1.222, -0.611, 0.0, 0.611, 1.222, 1.833], $x);
        self::assertEqualsWithDelta(4.257, 1.833 * 2 + 0.591, 1e-9, 'the wall is still 4.257 m wide');
    }

    /**
     * x and y are centred so a single cabinet can be swapped for a group without moving; z cannot be,
     * because the base is the floor or the top of whatever the placement stands on.
     */
    public function testItIsCentredOnItsAnchorInXAndYButStacksUpwardsInZ(): void
    {
        $copies = $this->copies(new Lattice([2, 2, 2]));

        $offsets = array_map(static fn (PlacementCopy $c): array => array_map(
            static fn (float $v): float => round($v, 9),
            $c->offset,
        ), $copies);

        // x straddles 0 at ±0.25, y at ±0.26 — and z runs 0 then 0.96, never below the base.
        self::assertSame([-0.25, -0.26, 0.0], $offsets[0]);
        self::assertSame([-0.25, -0.26, 0.96], $offsets[1]);
        self::assertSame([0.25, 0.26, 0.96], $offsets[7]);
        self::assertSame(0.0, min(array_map(static fn (array $o): float => $o[2], $offsets)));
    }

    /**
     * `on:` reads the anchor's own top, so a lattice has to anchor on its top tier — anchoring the bottom
     * one would bury whatever stacks on the placement inside the lattice.
     */
    public function testItAnchorsOnTheMiddleCellOfItsTopTier(): void
    {
        $copies = $this->copies(new Lattice([3, 1, 2]));

        $anchors = array_values(array_filter($copies, static fn (PlacementCopy $c): bool => $c->isAnchor));
        self::assertCount(1, $anchors, 'exactly one anchor, which is what makes nesting need no arbitration');
        self::assertSame([0.0, 0.0, 0.96], $anchors[0]->offset);
    }

    public function testAnEvenCountStraddlesTheAnchorAndAnOddOneSitsOnIt(): void
    {
        self::assertSame(0.0, $this->copies(new Lattice([3, 1, 1]))[1]->offset[0]);

        $even = $this->copies(new Lattice([2, 1, 1]));
        self::assertSame(-0.25, $even[0]->offset[0]);
        self::assertSame(0.25, $even[1]->offset[0]);
    }

    /**
     * A gap on x is air beside a cabinet, which is normal; a gap on z is air *under* one, which nobody
     * wants by accident. So the scalar form leaves z alone and air underneath has to be named.
     */
    public function testAScalarGapNeverPutsAirUnderACabinet(): void
    {
        $scalar = Lattice::fromReader($this->reader(['count' => [2, 2, 2], 'gap_m' => 0.02]));
        self::assertSame([0.02, 0.02, 0.0], $scalar->gapM);

        $named = Lattice::fromReader($this->reader(['count' => [2, 2, 2], 'gap_m' => [0.02, 0.0, 0.10]]));
        self::assertSame([0.02, 0.0, 0.10], $named->gapM);

        $copies = $this->copies($scalar);
        self::assertSame(0.96, round($copies[1]->offset[2], 9), 'the upper tier still rests on the lower one');
    }

    public function testAStatedStepOverridesTheDerivedSpacingOnThatAxisOnly(): void
    {
        // End fire: the spacing along y is a decision about frequency, not about geometry, so it is
        // stated — while x and z still need nothing said about them.
        $copies = $this->copies(new Lattice([2, 3, 1], stepM: [0.0, 1.20, 0.0]));

        self::assertSame(-0.25, round($copies[0]->offset[0], 9), 'x is still derived');
        $y = array_values(array_unique(array_map(static fn (PlacementCopy $c): float => round($c->offset[1], 9), $copies)));
        self::assertSame([-1.2, 0.0, 1.2], $y);
    }

    /**
     * The point of handing the cell box down rather than letting each group measure a cabinet: a lattice
     * of fans spaces itself on the fan.
     */
    public function testALatticeOfArcsSpacesItselfOnTheWholeFansExtentNotOneCabinets(): void
    {
        $device = $this->device(backWidth: 0.345);
        $fan = new \App\Scene\Arc(\App\Scene\ArcMode::Convex, 3);
        $stack = new GroupStack([$fan, new Lattice([2, 1, 1])]);

        $copies = $stack->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));
        self::assertCount(6, $copies);

        // A three-wide convex fan of this cabinet is 1.4565 m across, so two of them stand that far
        // apart — not the 0.5 m one cabinet would have given.
        $spacing = $copies[3]->offset[0] - $copies[0]->offset[0];
        self::assertEqualsWithDelta(1.456540418, $spacing, 1e-9);
        self::assertGreaterThan(1.0, $spacing, 'spaced on the fan, not on a single cabinet');
    }

    /**
     * Both of the alternating rows the TODO asked for, from one mechanism: the cycle value goes into the
     * group's rotation, `roll_deg` into the placement's, and the two compose.
     */
    public function testACycledRollTurnsAlternateCellsOver(): void
    {
        $lattice = new Lattice([4, 1, 1], rollCycle: [0.0, 180.0]);
        $rolls = array_map(
            static fn (PlacementCopy $c): float => $c->rotation?->rollDeg ?? 0.0,
            $this->copies($lattice),
        );

        self::assertSame([0.0, 180.0, 0.0, 180.0], $rolls);
    }

    public function testACycleRunsAlongTheOnlyAxisThatHasMoreThanOneCell(): void
    {
        // Two tiers, one column: the cycle can only mean the tiers, so it needs no cycle_axis.
        $lattice = new Lattice([1, 1, 2], rollCycle: [180.0, 0.0]);

        self::assertSame([], $lattice->problems($this->device(), 0.0, 0.0, $this->cellBox()));

        $rolls = array_map(
            static fn (PlacementCopy $c): float => $c->rotation?->rollDeg ?? 0.0,
            $this->copies($lattice),
        );
        self::assertSame([180.0, 0.0], $rolls, 'the lower tier is the one turned over');
    }

    public function testACycleWithTwoOpenAxesHasToSayWhichOne(): void
    {
        // A cycle down x instead of z on a seven-by-two wall turns every column over instead of every
        // tier — fourteen cabinets wrong, and it renders perfectly plausibly.
        $problems = (new Lattice([7, 1, 2], rollCycle: [180.0, 0.0]))
            ->problems($this->device(), 0.0, 0.0, $this->cellBox());

        self::assertCount(1, $problems);
        self::assertStringContainsString('needs a cycle_axis', $problems[0]);
        self::assertStringContainsString('x and z', $problems[0]);

        $named = new Lattice([7, 1, 2], rollCycle: [180.0, 0.0], cycleAxis: Axis::Z);
        self::assertSame([], $named->problems($this->device(), 0.0, 0.0, $this->cellBox()));
    }

    /**
     * Mixing a quarter turn into the cycle changes how much room a cell needs, and the spacing stays
     * uniform on the widest of them — roomier than necessary for the cabinets that are upright, which is
     * the price of a grid staying a grid. The two real cycles, `[0, 180]` and `[90, 270]`, both leave the
     * extent alone, so the price is never actually paid.
     */
    public function testSpacingIsUniformOnTheWidestOrientationInTheCycle(): void
    {
        $flat = $this->copies(new Lattice([2, 1, 1], rollCycle: [0.0, 180.0]));
        self::assertSame(0.5, round($flat[1]->offset[0] - $flat[0]->offset[0], 9));

        $mixed = $this->copies(new Lattice([2, 1, 1], rollCycle: [0.0, 90.0]));
        self::assertSame(0.96, round($mixed[1]->offset[0] - $mixed[0]->offset[0], 9), 'the cabinet on its side sets it');
    }

    /**
     * Turning a centred row over leaves every cabinet where it was, because a centred row is symmetric
     * about its own axis. That is not luck — it is why `at` is the middle of a group rather than its edge,
     * and it is what makes a cycle on an outer lattice safe.
     */
    public function testRollingACentredRowOverLeavesEveryCabinetWhereItWas(): void
    {
        $device = $this->device();
        $row = new Lattice([7, 1, 1]);
        $twoTiers = new Lattice([1, 1, 2], rollCycle: [180.0, 0.0], cycleAxis: Axis::Z);

        $plain = (new GroupStack([$row]))->copies($device, 0.0, 0.0, $this->cellBox());
        $cycled = (new GroupStack([$row, $twoTiers]))->copies($device, 0.0, 0.0, $this->cellBox());

        // The lower tier is the row turned over. Rolling it mirrors it, so its cabinets swap places
        // left for right — and because the row is centred, the *wall* is identical: every position one
        // of them stood at is still occupied.
        $lower = array_values(array_filter($cycled, static fn (PlacementCopy $c): bool => $c->path[0] === 1));
        self::assertCount(7, $lower);

        $upright = array_map(static fn (PlacementCopy $c): float => round($c->offset[0], 9), $plain);
        $rolled = array_map(static fn (PlacementCopy $c): float => round($c->offset[0], 9), $lower);
        sort($upright);
        sort($rolled);
        self::assertSame($upright, $rolled);
        // Stated the other way, to be explicit that the mirroring is real and not a rounding artefact.
        self::assertSame(1.5, $rolled[count($rolled) - 1]);
        self::assertSame(-1.5, $lower[0]->offset[0] * -1);
    }

    public function testARowIsALatticeWithOneOpenAxis(): void
    {
        $row = Lattice::rowFromReader($this->reader(['count' => 7, 'gap_m' => 0.02]));

        self::assertSame([7, 1, 1], $row->count);
        self::assertSame([0.02, 0.0, 0.0], $row->gapM);
        self::assertSame(Axis::X, $row->cycleAxis);
    }

    public function testARowCanRunFrontToBackInstead(): void
    {
        $row = Lattice::rowFromReader($this->reader(['count' => 3, 'axis' => 'y']));

        self::assertSame([1, 3, 1], $row->count);
        $y = array_map(static fn (PlacementCopy $c): float => round($c->offset[1], 9), $this->copies($row));
        self::assertSame([-0.52, 0.0, 0.52], $y);
    }

    public function testACountOfOneOnEveryAxisIsJustTheCabinet(): void
    {
        $copies = $this->copies(new Lattice([1, 1, 1]));

        self::assertCount(1, $copies);
        self::assertSame([0.0, 0.0, 0.0], $copies[0]->offset);
        self::assertSame([], $copies[0]->path, 'a single cabinet is not numbered');
        self::assertTrue($copies[0]->isAnchor);
    }

    public function testOnlyAxesWithMoreThanOneCellShowUpInTheId(): void
    {
        // 7 along x, flat in y, 2 in z: the id says which column and which tier, and nothing about y.
        $paths = array_map(static fn (PlacementCopy $c): array => $c->path, $this->copies(new Lattice([7, 1, 2])));

        self::assertSame([1, 1], $paths[0]);
        self::assertSame([1, 2], $paths[1]);
        self::assertSame([7, 2], $paths[13]);
    }

    /**
     * @return iterable<string, array{Lattice, string}>
     */
    public static function rejectionCases(): iterable
    {
        yield 'a count below one' => [new Lattice([0, 1, 1]), 'lattice.count must be at least 1 on every axis'];
        yield 'a negative gap' => [new Lattice([2, 1, 1], gapM: [-0.01, 0.0, 0.0]), 'lattice.gap_m must not be negative'];
        yield 'a negative step' => [new Lattice([2, 1, 1], stepM: [-1.0, 0.0, 0.0]), 'lattice.step_m must not be negative'];
        yield 'a cycle that is not a quarter turn' => [
            new Lattice([2, 1, 1], rollCycle: [0.0, 45.0]),
            'lattice.roll_cycle must be quarter turns, got 45',
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectionCases')]
    public function testRejects(Lattice $lattice, string $expected): void
    {
        $problems = $lattice->problems($this->device(), 0.0, 0.0, $this->cellBox());

        self::assertNotSame([], $problems, 'expected a problem');
        self::assertTrue(
            (bool)array_filter($problems, static fn (string $m): bool => str_contains($m, $expected)),
            sprintf("no problem contained %s\ngot: %s", var_export($expected, true), implode(' | ', $problems)),
        );
    }

    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("lattice: unknown key 'gap'");

        Lattice::fromReader($this->reader(['count' => [2, 1, 1], 'gap' => 0.02]));
    }

    public function testReadingRejectsAFractionalCount(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('expected whole numbers');

        Lattice::fromReader($this->reader(['count' => [2.5, 1, 1]]));
    }

    public function testACountMayNameOneTwoOrThreeAxes(): void
    {
        self::assertSame([7, 1, 1], Lattice::fromReader($this->reader(['count' => [7]]))->count);
        self::assertSame([7, 2, 1], Lattice::fromReader($this->reader(['count' => [7, 2]]))->count);
        self::assertSame([7, 2, 3], Lattice::fromReader($this->reader(['count' => [7, 2, 3]]))->count);
    }

    /**
     * @return list<PlacementCopy>
     */
    private function copies(Lattice $lattice, ?DeviceSpec $device = null): array
    {
        $device ??= $this->device();

        return $lattice->copies($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));
    }

    /**
     * A **mirrored** row: the half past the middle rolled one way, the half before it the other.
     *
     * The fixture is 0.5 wide and 0.96 tall, so on its side it is 0.96 across. Six of them with a 20 mm gap
     * come to `6 × 0.96 + 5 × 0.02 = 5.860 m` — and that is the whole point: **the same envelope a row of six
     * takes with any other pattern of quarter turns.** Only the handedness changes.
     */
    public function testAMirroredRowRollsItsHalvesOppositeWays(): void
    {
        $copies = $this->copies(new Lattice([6, 1, 1], [0.02, 0.0, 0.0], rollMirror: 90.0, cycleAxis: Axis::X));

        $rolls = array_map(static fn (PlacementCopy $c): float => $c->rotation?->rollDeg ?? 0.0, $copies);
        self::assertSame([270.0, 270.0, 270.0, 90.0, 90.0, 90.0], $rolls);
    }

    /**
     * And the seam between the halves is **the gap**, not a whole cabinet.
     *
     * This is the reason `roll_mirror` exists rather than a hand-written `roll_cycle`. A rolled cabinet is not
     * centred on its own origin — at 90 its body is entirely to the right of it, at 270 entirely to the left —
     * so spacing origins uniformly across a mirrored row opens the seam by a full 960 mm while every other
     * joint stays at 20 mm. Laying the *bodies* out at a uniform pitch instead puts each origin wherever its
     * own body needs it, and the seam comes out of the gap with nothing stated.
     */
    public function testTheSeamBetweenTheMirroredHalvesIsJustTheGap(): void
    {
        $copies = $this->copies(new Lattice([6, 1, 1], [0.02, 0.0, 0.0], rollMirror: 90.0, cycleAxis: Axis::X));

        // Body spans: rolled 270 runs from origin − 0.96 to the origin, rolled 90 from the origin onwards.
        $left = $copies[2]->offset[0];              // the innermost cabinet of the left half, body ends here
        $right = $copies[3]->offset[0];             // the innermost of the right half, body starts here
        self::assertEqualsWithDelta(0.02, $right - $left, 1e-9, 'the seam is the working gap');

        // Within a half it is a whole cabinet plus the gap, as it has to be — both bodies fall the same way.
        self::assertEqualsWithDelta(0.98, $copies[2]->offset[0] - $copies[1]->offset[0], 1e-9);

        // And the row still measures what any row of six 0.96 m cabinets measures.
        $lo = $copies[0]->offset[0] - 0.96;
        $hi = $copies[5]->offset[0] + 0.96;
        self::assertEqualsWithDelta(6 * 0.96 + 5 * 0.02, $hi - $lo, 1e-9);
        self::assertEqualsWithDelta(0.0, $lo + $hi, 1e-9, 'and stays centred on the placement');
    }

    /**
     * An odd count cannot be mirrored exactly, so the middle cabinet joins the right-hand half.
     *
     * Picking a side beats refusing — the row is then lopsided by one cabinet, which is a fact about the
     * inventory rather than a fault in the layout.
     */
    public function testAnOddMirroredRowPutsTheExtraCabinetOnTheRight(): void
    {
        $copies = $this->copies(new Lattice([5, 1, 1], [0.02, 0.0, 0.0], rollMirror: 90.0, cycleAxis: Axis::X));

        $rolls = array_map(static fn (PlacementCopy $c): float => $c->rotation?->rollDeg ?? 0.0, $copies);
        self::assertSame([270.0, 270.0, 90.0, 90.0, 90.0], $rolls);
    }

    /**
     * Only a quarter turn moves the body off to one side, which is what a mirror is made of — 0 and 180 leave
     * it centred and would mirror nothing.
     */
    public function testAMirrorNeedsAQuarterTurn(): void
    {
        $problems = (new Lattice([6, 1, 1], rollMirror: 45.0, cycleAxis: Axis::X))
            ->problems($this->device(), 0.0, 0.0, $this->cellBox());

        self::assertStringContainsString('roll_mirror must be 90 or 270', implode("\n", $problems));
    }

    /** A stated step would be applied to the origins, which is exactly what opens the seam. */
    public function testAMirrorRefusesAStatedStepBecauseItDerivesItsOwnSpacing(): void
    {
        $problems = (new Lattice([6, 1, 1], [0.02, 0.0, 0.0], [0.7, 0.0, 0.0], rollMirror: 90.0, cycleAxis: Axis::X))
            ->problems($this->device(), 0.0, 0.0, $this->cellBox());

        self::assertStringContainsString('derives its own spacing', implode("\n", $problems));
    }

    /** Two keys deciding the same cells' roll is a contradiction, not a composition. */
    public function testAMirrorAndACycleTogetherAreRefused(): void
    {
        $problems = (new Lattice([6, 1, 1], rollCycle: [90.0, 270.0], rollMirror: 90.0, cycleAxis: Axis::X))
            ->problems($this->device(), 0.0, 0.0, $this->cellBox());

        self::assertStringContainsString('both roll_cycle and roll_mirror', implode("\n", $problems));
    }

    /**
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    private function cellBox(): array
    {
        return GroupStack::cabinetBox($this->device(), 0.0, 0.0);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function reader(array $data): \App\Spec\ArrayReader
    {
        return new \App\Spec\ArrayReader($data);
    }

    private function device(
        float $width = 0.5,
        float $height = 0.96,
        float $depth = 0.52,
        ?float $backWidth = null,
    ): DeviceSpec {
        return SpecFactory::spec([
            'id' => 'lattice-cabinet',
            'geometry' => [
                'shape' => $backWidth === null ? 'box' : 'trapezoid',
                'dimensions_m' => ['width' => $width, 'height' => $height, 'depth' => $depth],
                'back_width_m' => $backWidth,
            ],
            'appearance' => ['grille' => null],
        ]);
    }
}
