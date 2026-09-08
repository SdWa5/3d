<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Outline;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The silhouette every contact solve runs on.
 *
 * Most of what this class has to get right is not the hull algorithm but which points go into it, and
 * what happens to them when the cabinet is turned — so the tests here are mostly about a Tecnare-shaped
 * cabinet at the quarter turns, with figures worked out from its dimensions by hand.
 */
final class OutlineTest extends TestCase
{
    public function testTheGrilleFrameAndTheTaperAreBothOnTheOutline(): void
    {
        // 0.5 wide at the front, 0.345 at the back, 0.52 deep, with a 12 mm grille frame standing proud.
        // The frame is full width, so the outline is six-sided: it runs straight out to ±0.25 for the
        // first 12 mm and only then starts tapering in.
        $outline = Outline::plan($this->tecnare(), 0.0);

        self::assertSame([
            [-0.25, -0.26],
            [0.25, -0.26],
            [0.25, -0.248],
            [0.1725, 0.26],
            [-0.1725, 0.26],
            [-0.25, -0.248],
        ], $outline->points);
    }

    public function testWithoutAGrilleFrameItIsThePlainTrapezoid(): void
    {
        $outline = Outline::plan($this->tecnare(grilleInset: null), 0.0);

        self::assertSame([
            [-0.25, -0.26],
            [0.25, -0.26],
            [0.1725, 0.26],
            [-0.1725, 0.26],
        ], $outline->points);
    }

    /**
     * The reason an arc can be rolled at all. On its side the taper runs vertically, where the plan view
     * cannot see it, and what is left is a plain rectangle as wide as the cabinet is tall.
     */
    public function testACabinetOnItsSideHasARectangularPlanOutline(): void
    {
        foreach ([90.0, 270.0] as $roll) {
            $outline = Outline::plan($this->tecnare(), 0.0, $roll);

            self::assertCount(4, $outline->points, "roll {$roll}");
            self::assertEqualsWithDelta(0.960, $outline->widthX(), 1e-9, "roll {$roll}: the height is now the width");

            $ys = array_map(static fn (array $p): float => round($p[1], 9), $outline->points);
            self::assertSame([-0.26, 0.26], array_values(array_unique($ys)), "roll {$roll}: still 0.52 deep");
        }
    }

    /**
     * Twelve corners go in — three cross-sections, both flanks, top and bottom. A quarter turn maps them
     * onto four distinct points, and a hull that could not see that would come out degenerate. This is
     * the case that pushed the class into snapping coordinates before comparing them: a corner that
     * should land on zero lands on 4.6e-17 instead, because `cos(270°)` is not exactly zero in binary.
     */
    public function testTwelveCornersCollapseOntoFourHullVerticesAtAQuarterTurn(): void
    {
        self::assertCount(12, $this->tecnare()->contactCorners());
        self::assertCount(4, Outline::plan($this->tecnare(), 0.0, 90.0)->points);
    }

    /**
     * Tilt swings the front-top edge forward into a full-width prow, which is a real vertex and the
     * reason a tilted concave cluster solved flat drives its cabinets into each other.
     */
    public function testTiltAddsTheProwTheFrontTopEdgeSwingsInto(): void
    {
        $flat = Outline::plan($this->tecnare(), 0.0);
        $tilted = Outline::plan($this->tecnare(), 4.4);

        self::assertCount(6, $flat->points);
        self::assertCount(6, $tilted->points);

        // The frame's top corner reaches h·sin(4.4°) = 73.7 mm further forward than its foot — and the
        // foot itself swings back to 0.26·cos(4.4°), because tilting rotates the whole cabinet about x.
        $frontmost = min(array_map(static fn (array $p): float => $p[1], $tilted->points));
        self::assertEqualsWithDelta(
            -0.26 * cos(deg2rad(4.4)) - 0.96 * sin(deg2rad(4.4)),
            $frontmost,
            1e-9,
        );

        // Tilt does not change how wide the cabinet is in plan; it rotates about the x axis.
        self::assertSame($flat->widthX(), $tilted->widthX());
    }

    public function testTheWidthIsTheCabinetsOwnWidthUntilSomethingTurnsIt(): void
    {
        $device = $this->tecnare();

        self::assertSame(0.5, Outline::plan($device, 0.0)->widthX());
        self::assertSame(0.5, Outline::plan($device, 0.0, 180.0)->widthX());
        self::assertEqualsWithDelta(0.96, Outline::plan($device, 0.0, 90.0)->widthX(), 1e-9);
        // Halfway over, it is the diagonal of the width and the height.
        self::assertEqualsWithDelta(
            (0.5 + 0.96) / sqrt(2),
            Outline::plan($device, 0.0, 45.0)->widthX(),
            1e-9,
        );
    }

    public function testAGapWidensTheOutlineByHalfOfItselfOnEachSide(): void
    {
        $outline = Outline::plan($this->tecnare(), 0.0);

        self::assertSame(0.5, $outline->dilatedX(0.0)->widthX());
        self::assertEqualsWithDelta(0.52, $outline->dilatedX(0.02)->widthX(), 1e-9);
        // The depth is untouched: a working gap is air beside a cabinet, not in front of it.
        $ys = array_map(static fn (array $p): float => $p[1], $outline->dilatedX(0.02)->points);
        self::assertEqualsWithDelta(-0.26, min($ys), 1e-9);
        self::assertEqualsWithDelta(0.26, max($ys), 1e-9);
    }

    /**
     * The flanks are what two neighbours in a row or an arc present to each other, and which way each
     * hull edge faces has to be read off the winding rather than guessed.
     */
    public function testTheFlanksAreTheEdgesFacingLeftAndRight(): void
    {
        $outline = Outline::plan($this->tecnare(), 0.0);

        // The frame's 12 mm side, and the tapered side behind it — on each flank.
        self::assertCount(2, $outline->facingEdges(1.0));
        self::assertCount(2, $outline->facingEdges(-1.0));
        // Front and back faces run across the cabinet, so they are on neither flank.
        self::assertCount(6, $outline->edges());
    }

    /**
     * Side view, which is where a line array's elements meet. The taper is invisible here for the same
     * reason it is invisible in plan for a cabinet on its side.
     */
    public function testTheElevationIsTheCabinetSeenFromTheSide(): void
    {
        $outline = Outline::elevation($this->tecnare());

        // 0.52 deep by 0.96 high, front at −Y and the floor at z = 0.
        self::assertSame(0.52, $outline->widthX());
        $zs = array_map(static fn (array $p): float => $p[1], $outline->points);
        self::assertSame(0.0, min($zs));
        self::assertSame(0.96, max($zs));
    }

    public function testAWedgeIsTaperedInTheElevationRatherThanInPlan(): void
    {
        // front_height_m below height is a cabinet whose top slopes back — the vertical twin of a taper.
        $wedge = $this->tecnare(grilleInset: null, frontHeight: 0.80);

        self::assertCount(4, Outline::plan($wedge, 0.0)->points, 'plan view is unchanged by a sloping top');
        $elevation = Outline::elevation($wedge);
        $zs = array_map(static fn (array $p): float => $p[1], $elevation->points);
        self::assertSame(0.80, max(array_map(
            static fn (array $p): float => $p[0] < 0.0 ? $p[1] : 0.0,
            $elevation->points,
        )), 'the front is only 0.80 m tall');
        self::assertSame(0.96, max($zs));
    }

    private function tecnare(?float $grilleInset = 0.012, ?float $frontHeight = null): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'outline-top',
            'geometry' => [
                'shape' => 'trapezoid',
                'dimensions_m' => ['width' => 0.5, 'height' => 0.96, 'depth' => 0.52],
                'back_width_m' => 0.345,
                'front_height_m' => $frontHeight,
            ],
            'appearance' => [
                'grille' => null === $grilleInset ? null : ['inset_m' => $grilleInset, 'color' => '#0a0a0a'],
            ],
        ]);
    }
}
