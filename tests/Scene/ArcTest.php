<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Arc;
use App\Scene\ArcMode;
use App\Scene\Orientation;
use App\Scene\PlacementCopy;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Arc geometry, checked on the cabinets' actual corners rather than on the formulas that placed them.
 *
 * That distinction is the whole value of this file. Every "does it touch?" assertion here transforms the
 * plan-view corners of two neighbouring cabinets and measures the distance between them, so a formula
 * restated from the implementation cannot make a test pass. It is what catches the mistake this feature
 * was written with first: `w / (2·sin(θ/2))` is the radius to a cabinet's *corners*, while contact
 * between flat faces is set by `w / (2·tan(θ/2))` — 1.1% apart, which leaves a 3.8 mm gap down the whole
 * seam and looks completely right in a render.
 */
final class ArcTest extends TestCase
{
    /** Splay of a flush convex Tecnare with no grille frame: 2·atan(((0.5 − 0.345)/2) / 0.52). */
    private const FLUSH_DEG = 16.953756448932;

    protected function setUp(): void
    {
        // Not decoration: these tests assert on absolute distances, so they need real proportions.
        self::assertSame(0.345, $this->tecnare()->backWidth);
    }

    public function testTheTightestConvexArcIsTheCabinetsOwnTaper(): void
    {
        $arc = new Arc(ArcMode::Convex, 3);
        $device = $this->tecnare(grilleInset: null);

        self::assertEqualsWithDelta(self::FLUSH_DEG, $arc->splayDegFor($device, 0.0), 1e-9);

        // Independent of the trigonometry above: at the flush angle the two contact radii are r·d/Δw,
        // which is where the taper-as-splay-angle claim actually comes from.
        $expectedFront = 0.500 * 0.520 / (0.500 - 0.345);
        self::assertEqualsWithDelta($expectedFront, $arc->frontRadiusM($device, 0.0), 1e-9);
        self::assertEqualsWithDelta($expectedFront - 0.520 / 2, $arc->centreRadiusM($device, 0.0), 1e-9);
    }

    public function testAFlushConvexArcTouchesAlongTheWholeSideFace(): void
    {
        // Both ends at once — the one angle where that happens, and the reason it is the default.
        $device = $this->tecnare(grilleInset: null);
        $seats = (new Arc(ArcMode::Convex, 3))->seats($device, 0.0);

        foreach ([0.0, 0.960] as $z) {
            self::assertEqualsWithDelta(0.0, $this->gapAt($device, $seats, 'front', 0.0, $z), 1e-9);
            self::assertEqualsWithDelta(0.0, $this->gapAt($device, $seats, 'back', 0.0, $z), 1e-9);
        }
    }

    public function testTheCornerRadiusWouldLeaveAGapDownTheSeam(): void
    {
        // The bug this feature was written with. Kept as a test because both radii look plausible and
        // only one of them makes the cabinets meet.
        $device = $this->tecnare(grilleInset: null);
        $splay = deg2rad(self::FLUSH_DEG);

        $apothem = 0.345 / (2 * tan($splay / 2)) + 0.520 / 2;
        $cornerCircle = 0.345 / (2 * sin($splay / 2)) + 0.520 / 2;

        self::assertEqualsWithDelta($apothem, (new Arc(ArcMode::Convex, 3))->centreRadiusM($device, 0.0), 1e-9);
        self::assertEqualsWithDelta(0.012783, $cornerCircle - $apothem, 1e-6);
        // What that 12.8 mm of radius does at the seam.
        self::assertEqualsWithDelta(0.003769, 2 * sin($splay / 2) * ($cornerCircle - $apothem), 1e-6);
    }

    public function testTheGrilleFrameWidensTheFlushAngle(): void
    {
        // The frame is a full-width slab across the front, so the taper only runs over depth − inset.
        // Ignore it and the built meshes overlap by 3.5 mm at what looks like the right angle.
        $arc = new Arc(ArcMode::Convex, 3);

        self::assertEqualsWithDelta(17.348216, $arc->splayDegFor($this->tecnare(), 0.0), 1e-6);
        self::assertGreaterThan(
            $arc->splayDegFor($this->tecnare(grilleInset: null), 0.0),
            $arc->splayDegFor($this->tecnare(), 0.0),
        );
    }

    public function testAWiderConvexArcKeepsTheBackEdgesTouching(): void
    {
        $device = $this->tecnare();
        $seats = (new Arc(ArcMode::Convex, 3, splayDeg: 25.0))->seats($device, 0.0);

        self::assertEqualsWithDelta(0.0, $this->gapAt($device, $seats, 'back', 0.0, 0.0), 1e-9);
        self::assertEqualsWithDelta(0.073771, $this->gapAt($device, $seats, 'frame', 0.0, 0.0), 1e-6);
    }

    public function testATighterConvexArcMovesContactToTheFrontsWithoutOverlapping(): void
    {
        // Tighter than the taper is still buildable — the fronts meet and a V opens behind — so it is
        // allowed rather than refused. It simply is not what "back edges touching" means.
        $device = $this->tecnare();
        $seats = (new Arc(ArcMode::Convex, 3, splayDeg: 12.0))->seats($device, 0.0);

        self::assertEqualsWithDelta(0.0, $this->gapAt($device, $seats, 'front', 0.0, 0.0), 1e-9);
        self::assertGreaterThan(0.0, $this->gapAt($device, $seats, 'back', 0.0, 0.0));
    }

    public function testAConcaveArcTouchesAtTheFrontAtAnyAngle(): void
    {
        $device = $this->tecnare();

        foreach ([9.5, self::FLUSH_DEG, 40.0] as $splay) {
            $seats = (new Arc(ArcMode::Concave, 3, splayDeg: $splay))->seats($device, 0.0);

            self::assertEqualsWithDelta(0.0, $this->gapAt($device, $seats, 'frame', 0.0, 0.0), 1e-9);
            // And always wide open behind, which is what a concave cluster looks like from the back.
            self::assertGreaterThan(0.2, $this->gapAt($device, $seats, 'back', 0.0, 0.0));
        }
    }

    public function testAConcaveArcIsWideOpenBehind(): void
    {
        $device = $this->tecnare();
        $seats = (new Arc(ArcMode::Concave, 3, splayDeg: self::FLUSH_DEG))->seats($device, 0.0);

        self::assertEqualsWithDelta(0.306613, $this->gapAt($device, $seats, 'back', 0.0, 0.0), 1e-6);
    }

    public function testTiltingLeavesTheCornersTouchingAndNothingOverlapping(): void
    {
        // The point of solving contact on the tilted outline. Solved flat, a concave cluster at this
        // tilt interpenetrates by 20.6 mm — cabinets through each other, in a render nobody would
        // question. Here contact moves to one corner and everything else opens up.
        $device = $this->tecnare();

        foreach ([ArcMode::Convex, ArcMode::Concave] as $mode) {
            $arc = new Arc($mode, 3, splayDeg: ArcMode::Convex === $mode ? null : self::FLUSH_DEG);
            $seats = $arc->seats($device, 4.4);

            $gaps = [];
            foreach (['frame', 'front', 'back'] as $edge) {
                foreach ([0.0, 0.960] as $z) {
                    $gaps[] = $this->gapAt($device, $seats, $edge, 4.4, $z);
                }
            }

            self::assertEqualsWithDelta(0.0, min($gaps), 1e-9, "{$mode->value}: nothing touches");
            self::assertGreaterThan(0.02, max($gaps), "{$mode->value}: the seam should open up");
        }
    }

    public function testATiltedArcNeedsMoreRoomThanAFlatOne(): void
    {
        $device = $this->tecnare();
        $arc = new Arc(ArcMode::Concave, 3, splayDeg: self::FLUSH_DEG);

        // The front-top edge swings forward by about h·sin(pitch), and the arc has to grow to match.
        self::assertEqualsWithDelta(
            0.960 * sin(deg2rad(4.4)) - (0.520 / 2) * (1 - cos(deg2rad(4.4))),
            $arc->centreRadiusM($device, 4.4) - $arc->centreRadiusM($device, 0.0),
            1e-9,
        );
    }

    public function testTheMiddleCabinetSitsOnTheAnchor(): void
    {
        // What lets a single top be swapped for a group without the middle one moving.
        $seats = (new Arc(ArcMode::Convex, 3))->seats($this->tecnare(), 0.0);

        self::assertSame([0.0, 0.0, 0.0], $seats[1]->offset);
        self::assertSame(0.0, $seats[1]->rotation?->yawDeg);
        self::assertTrue($seats[1]->isAnchor);
        self::assertFalse($seats[0]->isAnchor);
    }

    public function testAnEvenArcStraddlesTheAnchor(): void
    {
        $seats = (new Arc(ArcMode::Convex, 2))->seats($this->tecnare(), 0.0);

        self::assertCount(2, $seats);
        self::assertEqualsWithDelta(-$seats[1]->offset[0], $seats[0]->offset[0], 1e-12);
        self::assertEqualsWithDelta($seats[1]->offset[1], $seats[0]->offset[1], 1e-12);
        self::assertEqualsWithDelta(-$seats[1]->rotation?->yawDeg, $seats[0]->rotation?->yawDeg, 1e-12);
        // Nothing sits on `at` itself; the left cabinet anchors `on:`.
        self::assertTrue($seats[0]->isAnchor);
    }

    public function testAPairsSeamLandsExactlyOnTheAnchorsAxis(): void
    {
        // A symmetry oracle a three-cabinet arc cannot give: the touching corners must be at x = 0.
        $device = $this->tecnare();
        $seats = (new Arc(ArcMode::Convex, 2))->seats($device, 0.0);

        $left = $this->corners($device, $seats[0], 0.0, 0.0);
        $right = $this->corners($device, $seats[1], 0.0, 0.0);

        self::assertEqualsWithDelta(0.0, $left['back-r'][0], 1e-12);
        self::assertEqualsWithDelta(0.0, $right['back-l'][0], 1e-12);
    }

    public function testConvexBowsBackwardsAndConcaveForwards(): void
    {
        $device = $this->tecnare();

        $convex = (new Arc(ArcMode::Convex, 3))->seats($device, 0.0);
        $concave = (new Arc(ArcMode::Concave, 3, splayDeg: 20.0))->seats($device, 0.0);

        self::assertGreaterThan(0.0, $convex[0]->offset[1], 'convex ends sit behind the middle');
        self::assertLessThan(0.0, $concave[0]->offset[1], 'concave ends sit in front of the middle');
        // And the outer cabinets turn opposite ways: outward in convex, inward in concave.
        self::assertLessThan(0.0, $convex[0]->rotation?->yawDeg);
        self::assertGreaterThan(0.0, $concave[0]->rotation?->yawDeg);
    }

    public function testEveryCabinetsFrontFaceLiesOnTheStatedRadius(): void
    {
        foreach ([ArcMode::Convex, ArcMode::Concave] as $mode) {
            $device = $this->tecnare();
            $arc = new Arc($mode, 4, radiusM: 3.0);
            $radius = $arc->frontRadiusM($device, 0.0);
            $centre = [0.0, $mode->sign() * $arc->centreRadiusM($device, 0.0)];

            self::assertEqualsWithDelta(3.0, $radius, 1e-9, 'the stated radius is the front face');

            foreach ($arc->seats($device, 0.0) as $seat) {
                $front = (new Orientation(0.0, 0.0, $seat->rotation?->yawDeg))->apply([0.0, -0.520 / 2, 0.0]);
                $distance = sqrt(
                    ($seat->offset[0] + $front[0] - $centre[0]) ** 2
                    + ($seat->offset[1] + $front[1] - $centre[1]) ** 2,
                );
                self::assertEqualsWithDelta($radius, $distance, 1e-9, "{$mode->value} seat {$seat->path[0]}");
            }
        }
    }

    public function testAStatedRadiusResolvesToTheTightestSplayItAllows(): void
    {
        $device = $this->tecnare();

        // A *larger* convex radius is a *flatter* arc, which is the least intuitive thing here.
        $tight = (new Arc(ArcMode::Convex, 3, radiusM: 1.65))->splayDegFor($device, 0.0);
        $flat = (new Arc(ArcMode::Convex, 3, radiusM: 3.0))->splayDegFor($device, 0.0);

        self::assertGreaterThan($flat, $tight);
        self::assertEqualsWithDelta(9.565368, $flat, 1e-6);
    }

    public function testASingleCabinetArcNeedsNoAngleAtAll(): void
    {
        // So a group can be scaled down to one without the scene having to change anything else.
        $arc = new Arc(ArcMode::Concave, 1);
        $box = $this->tecnare(backWidth: null);

        self::assertSame([], $arc->problems($box, 0.0));

        $seats = $arc->seats($box, 0.0);
        self::assertCount(1, $seats);
        self::assertSame([0.0, 0.0, 0.0], $seats[0]->offset);
        self::assertSame(0.0, $seats[0]->rotation?->yawDeg);
    }

    /**
     * A cabinet with nothing to taper — a plain box, or a trapezoid whose back is as wide as its front —
     * used to be refused for having no angle to derive. It has one: zero. Two boxes side by side are
     * already in full face contact, which is the tightest convex arrangement there is, and the arc lays
     * them out as a row spaced by their own width.
     */
    public function testAConvexArcOfPlainBoxesIsAStraightRowOfThem(): void
    {
        foreach ([self::device(backWidth: null), self::device(backWidth: 0.5)] as $untapered) {
            $arc = new Arc(ArcMode::Convex, 3);

            self::assertSame([], $arc->problems($untapered, 0.0));
            self::assertSame(0.0, $arc->splayDegFor($untapered, 0.0));
            self::assertTrue($arc->isStraight($untapered, 0.0));

            $seats = $arc->seats($untapered, 0.0);
            self::assertSame([-0.5, 0.0, 0.0], $seats[0]->offset);
            self::assertSame([0.0, 0.0, 0.0], $seats[1]->offset);
            self::assertSame([0.5, 0.0, 0.0], $seats[2]->offset);
            self::assertSame(0.0, $seats[1]->rotation?->yawDeg);
        }
    }

    /**
     * `splay_deg: 0` states the same thing outright, and a row is the arc of infinite radius — so the
     * radius says so rather than reporting some large number that happens not to overflow.
     */
    public function testAZeroSplayArcIsAStraightRowSpacedByTheCabinetsOwnWidth(): void
    {
        $device = $this->tecnare();
        $arc = new Arc(ArcMode::Convex, 3, splayDeg: 0.0);

        self::assertSame([], $arc->problems($device, 0.0));
        self::assertInfinite($arc->centreRadiusM($device, 0.0));

        foreach ($arc->seats($device, 0.0) as $index => $seat) {
            self::assertSame(($index - 1) * 0.5, $seat->offset[0], "seat {$index} x");
            self::assertSame(0.0, $seat->offset[1], "seat {$index} y — a row does not bow");
            self::assertSame(0.0, $seat->rotation?->yawDeg, "seat {$index} yaw — a row does not turn");
        }
    }

    /**
     * The oracle that makes a row the same solve as an arc rather than a parallel one: as the splay
     * closes, the arc's spacing along the seam converges on the cabinet's own width.
     */
    public function testTheSpacingOfAStraightRowIsTheLimitOfTheArcsSpacing(): void
    {
        $device = $this->tecnare();

        $spacing = (new Arc(ArcMode::Convex, 3, splayDeg: 0.0))->seats($device, 0.0)[2]->offset[0]
            - (new Arc(ArcMode::Convex, 3, splayDeg: 0.0))->seats($device, 0.0)[1]->offset[0];
        self::assertSame(0.5, $spacing);

        // Closing the splay by a factor of ten takes the arc's spacing ten times closer to the row's, so
        // the row is the limit rather than merely a similar number: 0.4957 at 1°, 0.49996 at 0.01°.
        $previous = null;
        foreach ([1.0, 0.1, 0.01, 0.001] as $tiny) {
            $radius = (new Arc(ArcMode::Convex, 3, splayDeg: $tiny))->centreRadiusM($device, 0.0);
            $error = abs($spacing - $radius * deg2rad($tiny));

            self::assertLessThan(0.005 * $tiny, $error, "splay {$tiny} should be within 0.005·splay");
            if (null !== $previous) {
                self::assertEqualsWithDelta(0.1, $error / $previous, 0.01, "splay {$tiny} error shrank tenfold");
            }
            $previous = $error;
        }
    }

    /**
     * A cabinet on its side touches its neighbour top to bottom, so the row is spaced by the cabinet's
     * height. `width` is only ever a stand-in for this, and it stops being one the moment anything is
     * turned.
     */
    public function testAStraightRowOfCabinetsOnTheirSidesIsSpacedByTheirHeight(): void
    {
        $device = $this->tecnare();
        $arc = new Arc(ArcMode::Convex, 4);

        self::assertTrue($arc->isStraight($device, 0.0, 90.0));
        self::assertSame(0.0, $arc->splayDegFor($device, 0.0, 90.0));

        $x = array_map(static fn (PlacementCopy $s): float => $s->offset[0], $arc->seats($device, 0.0, 90.0));
        self::assertSame([-1.44, -0.48, 0.48, 1.44], $x, 'four cabinets 0.960 m apart, centred on `at`');
    }

    /**
     * Every sub row in this repository states a 0.611 m step for a 0.591 m cabinet — a 20 mm working gap,
     * written into a number by hand. `gap_m` is that gap said out loud.
     */
    public function testAGapOpensEveryJoint(): void
    {
        $device = $this->tecnare();

        $flush = new Arc(ArcMode::Convex, 3, splayDeg: 0.0);
        $spaced = new Arc(ArcMode::Convex, 3, splayDeg: 0.0, gapM: 0.02);

        self::assertSame(0.5, $flush->seats($device, 0.0)[2]->offset[0]);
        self::assertSame(0.52, $spaced->seats($device, 0.0)[2]->offset[0]);

        // A gap opens a splayed seam too, measured across it rather than along x.
        $tight = new Arc(ArcMode::Convex, 3, splayDeg: 25.0);
        $roomy = new Arc(ArcMode::Convex, 3, splayDeg: 25.0, gapM: 0.02);
        self::assertGreaterThan(
            $tight->centreRadiusM($device, 0.0),
            $roomy->centreRadiusM($device, 0.0),
        );
    }

    /**
     * Contact is solved on the plan outline of the *turned* cabinet, so it holds at every quarter turn —
     * one code path for upright, turned over, and on its side. Measured on the cabinets' own corners with
     * a separating-axis test, because at a quarter turn the seam meets edge to edge with the two faces
     * offset along each other and no two corners coincide.
     */
    public function testAnArcTouchesAlongItsSeamAtEveryQuarterTurn(): void
    {
        $device = $this->tecnare();

        foreach ([0.0, 90.0, 180.0, 270.0] as $roll) {
            foreach ([ArcMode::Convex, ArcMode::Concave] as $mode) {
                $arc = new Arc($mode, 3, splayDeg: 25.0);
                $seats = $arc->seats($device, 0.0, $roll);

                self::assertEqualsWithDelta(
                    0.0,
                    $this->separation($device, $seats[0], $seats[1], 0.0, $roll),
                    1e-9,
                    sprintf('roll %s, %s', $roll, $mode->value),
                );
            }
        }
    }

    /**
     * Turning a cabinet over leaves its plan outline alone, so it leaves the flush angle alone too.
     */
    public function testTurningACabinetOverDoesNotChangeItsFlushAngle(): void
    {
        $device = $this->tecnare();
        $arc = new Arc(ArcMode::Convex, 3);

        self::assertEqualsWithDelta(17.348216263793, $arc->splayDegFor($device, 0.0, 0.0), 1e-9);
        self::assertEqualsWithDelta(17.348216263793, $arc->splayDegFor($device, 0.0, 180.0), 1e-9);
        self::assertEqualsWithDelta(1.390709677419, $arc->centreRadiusM($device, 0.0, 0.0), 1e-9);
        self::assertEqualsWithDelta(1.390709677419, $arc->centreRadiusM($device, 0.0, 180.0), 1e-9);
    }

    /**
     * A *tilted* upside-down arc is a different arc, and it used to be solved as if it were the right way
     * up: tilt swings the front-top edge forward, and turning the cabinet over swings it the other way.
     * Solved flat the mirrored arc came out 73.7 mm too tight — the cabinets driven into each other, in a
     * render that looks entirely plausible. No committed scene tilts a rolled row, which is why nothing
     * caught it.
     */
    public function testTiltingAnUpsideDownArcOpensItByADifferentAmountThanARightWayUpOne(): void
    {
        $device = $this->tecnare();
        $arc = new Arc(ArcMode::Convex, 3);

        $upright = $arc->centreRadiusM($device, 4.4, 0.0);
        $turnedOver = $arc->centreRadiusM($device, 4.4, 180.0);

        self::assertEqualsWithDelta(1.386610911419, $upright, 1e-9);
        self::assertEqualsWithDelta(1.460261178423, $turnedOver, 1e-9);
        self::assertEqualsWithDelta(0.073650, $turnedOver - $upright, 1e-6);

        // Both still meet exactly on their own corners, which is the point of solving them separately.
        foreach ([0.0, 180.0] as $roll) {
            $seats = $arc->seats($device, 4.4, $roll);
            self::assertEqualsWithDelta(
                0.0,
                $this->separation($device, $seats[0], $seats[1], 4.4, $roll),
                1e-9,
                "roll {$roll}",
            );
        }
    }

    /**
     * @return iterable<string, array{Arc, DeviceSpec, string}>
     */
    public static function rejectionCases(): iterable
    {
        $tapered = self::device();

        yield 'count below one' => [
            new Arc(ArcMode::Convex, 0), $tapered, 'arc.count must be at least 1',
        ];
        yield 'both size fields' => [
            new Arc(ArcMode::Convex, 3, splayDeg: 20.0, radiusM: 2.0), $tapered,
            'use either `arc.splay_deg` or `arc.radius_m`, not both',
        ];
        yield 'negative splay' => [
            new Arc(ArcMode::Convex, 3, splayDeg: -3.0), $tapered,
            'arc.splay_deg must be at least 0 and less than 180, got -3',
        ];
        yield 'splay at half a turn' => [
            new Arc(ArcMode::Convex, 3, splayDeg: 180.0), $tapered,
            'arc.splay_deg must be at least 0 and less than 180',
        ];
        yield 'a negative gap' => [
            new Arc(ArcMode::Convex, 3, gapM: -0.01), $tapered,
            'arc.gap_m must not be negative, got -0.01',
        ];
        yield 'zero radius' => [
            new Arc(ArcMode::Convex, 3, radiusM: 0.0), $tapered,
            'arc.radius_m must be greater than 0',
        ];
        yield 'radius inside the cabinet' => [
            new Arc(ArcMode::Convex, 3, radiusM: 0.4), $tapered,
            'leaves the centre of curvature inside the cabinet',
        ];
        yield 'wrapping past a full circle' => [
            new Arc(ArcMode::Convex, 5, splayDeg: 90.0), $tapered,
            '5 cabinets at 90.00° wrap past a full circle — arc.splay_deg must not exceed 72.00°',
        ];
        yield 'concave has no tightest angle' => [
            new Arc(ArcMode::Concave, 3), $tapered,
            'a concave arc has no tightest angle — its front edges touch at any angle',
        ];
        yield 'concave says what to try instead' => [
            new Arc(ArcMode::Concave, 3), $tapered,
            "(try 60.00°, the cabinet's own horizontal coverage)",
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectionCases')]
    public function testRejects(Arc $arc, DeviceSpec $device, string $expected): void
    {
        $problems = $arc->problems($device, 0.0);

        self::assertNotSame([], $problems, 'expected a problem');
        self::assertTrue(
            (bool) array_filter($problems, static fn (string $m): bool => str_contains($m, $expected)),
            sprintf("no problem contained %s\ngot: %s", var_export($expected, true), implode(' | ', $problems)),
        );
    }

    public function testAValidArcHasNoProblems(): void
    {
        self::assertSame([], (new Arc(ArcMode::Convex, 3))->problems($this->tecnare(), 4.4));
        self::assertSame([], (new Arc(ArcMode::Concave, 3, splayDeg: 60.0))->problems($this->tecnare(), 0.0));
        self::assertSame([], (new Arc(ArcMode::Convex, 21, splayDeg: 360 / 21))->problems($this->tecnare(), 0.0));
    }

    public function testReadingRejectsAnUnknownMode(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches('/unknown value .outward./');

        Arc::fromReader(new \App\Spec\ArrayReader(['mode' => 'outward', 'count' => 3]));
    }

    public function testReadingRejectsAMistypedSizeKey(): void
    {
        // `step` instead of `step_deg` would otherwise fall back to the default angle and silently move
        // every cabinet in the group.
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches("/unknown key 'step'/");

        Arc::fromReader(new \App\Spec\ArrayReader(['mode' => 'convex', 'count' => 3, 'step' => 10]));
    }

    public function testReadingRequiresACount(): void
    {
        $this->expectException(InvalidSpecException::class);

        Arc::fromReader(new \App\Spec\ArrayReader(['mode' => 'convex']));
    }

    /**
     * Distance between the touching corners of two neighbouring cabinets, measured on their actual
     * transformed geometry. Zero means they meet; anything above zero is a gap.
     *
     * @param list<PlacementCopy> $seats
     */
    private function gapAt(DeviceSpec $device, array $seats, string $edge, float $pitchDeg, float $z): float
    {
        $left = $this->corners($device, $seats[count($seats) - 2], $pitchDeg, $z);
        $right = $this->corners($device, $seats[count($seats) - 1], $pitchDeg, $z);

        return sqrt(
            ($left["{$edge}-r"][0] - $right["{$edge}-l"][0]) ** 2
            + ($left["{$edge}-r"][1] - $right["{$edge}-l"][1]) ** 2,
        );
    }

    /**
     * Signed distance between two seats' plan-view silhouettes: 0 means they touch, positive means clear
     * air, negative means they are inside each other by that much.
     *
     * {@see gapAt} names the corners it measures, which is the clearer assertion whenever the seam meets
     * corner to corner. It cannot speak for a quarter-turned cabinet, where the two faces meet offset
     * along each other and no corner lands on a corner — hence a separating-axis test, computed from the
     * spec's own corners and {@see Orientation}, so it still owes nothing to the code under test.
     */
    private function separation(
        DeviceSpec $device,
        PlacementCopy $left,
        PlacementCopy $right,
        float $pitchDeg,
        float $rollDeg,
    ): float {
        $silhouette = static function (PlacementCopy $seat) use ($device, $pitchDeg, $rollDeg): array {
            $orientation = new Orientation($pitchDeg, $rollDeg, $seat->rotation->yawDeg ?? 0.0);
            $points = [];
            foreach ($device->contactCorners() as $corner) {
                $rotated = $orientation->apply($corner);
                $points[] = [$seat->offset[0] + $rotated[0], $seat->offset[1] + $rotated[1]];
            }

            return $points;
        };

        $a = $silhouette($left);
        $b = $silhouette($right);

        // For convex shapes the widest gap over every edge normal *is* the distance between them.
        $widest = -INF;
        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            foreach ($from as $i => $p) {
                foreach ($from as $q) {
                    $length = hypot($q[0] - $p[0], $q[1] - $p[1]);
                    if ($length < 1e-12) {
                        continue;
                    }
                    $normal = [($q[1] - $p[1]) / $length, -($q[0] - $p[0]) / $length];

                    $fromMax = -INF;
                    foreach ($from as $v) {
                        $fromMax = max($fromMax, $v[0] * $normal[0] + $v[1] * $normal[1]);
                    }
                    $toMin = INF;
                    foreach ($to as $v) {
                        $toMin = min($toMin, $v[0] * $normal[0] + $v[1] * $normal[1]);
                    }
                    $widest = max($widest, $toMin - $fromMax);
                }
            }
        }

        return $widest;
    }

    /**
     * A seat's plan-view corners in the arc's own frame, at height $z.
     *
     * @return array<string, array{float, float}>
     */
    private function corners(DeviceSpec $device, PlacementCopy $seat, float $pitchDeg, float $z): array
    {
        $depth = $device->dimensions->depth;
        $halfFront = $device->dimensions->width / 2;
        $halfBack = ($device->backWidth ?? $device->dimensions->width) / 2;
        $inset = $device->grilleInset ?? 0.0;

        $orientation = new Orientation($pitchDeg, 0.0, $seat->rotation->yawDeg ?? 0.0);
        $corners = [];
        foreach ([
            'frame-l' => [-$halfFront, -$depth / 2],
            'frame-r' => [$halfFront, -$depth / 2],
            'front-l' => [-$halfFront, -$depth / 2 + $inset],
            'front-r' => [$halfFront, -$depth / 2 + $inset],
            'back-l' => [-$halfBack, $depth / 2],
            'back-r' => [$halfBack, $depth / 2],
        ] as $name => [$x, $y]) {
            $rotated = $orientation->apply([$x, $y, $z]);
            $corners[$name] = [$seat->offset[0] + $rotated[0], $seat->offset[1] + $rotated[1]];
        }

        return $corners;
    }

    private function tecnare(?float $backWidth = 0.345, ?float $grilleInset = 0.012): DeviceSpec
    {
        return self::device($backWidth, $grilleInset);
    }

    private static function device(?float $backWidth = 0.345, ?float $grilleInset = 0.012): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'arc-top',
            'geometry' => [
                'shape' => null === $backWidth ? 'box' : 'trapezoid',
                'dimensions_m' => ['width' => 0.5, 'height' => 0.96, 'depth' => 0.52],
                'back_width_m' => $backWidth,
            ],
            'appearance' => [
                'color' => '#111111',
                'grille' => null === $grilleInset ? null : ['inset_m' => $grilleInset, 'color' => '#0a0a0a'],
            ],
            'audio' => ['coverage_deg' => ['horizontal' => 60, 'vertical' => 40]],
        ]);
    }
}
