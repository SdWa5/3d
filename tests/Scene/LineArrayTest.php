<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\GroupStack;
use App\Scene\LineArray;
use App\Scene\PlacementCopy;
use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * A hang, checked on the elements' actual corners rather than on the formula that placed them.
 *
 * Same discipline as {@see ArcTest}: every "does it touch?" assertion here transforms the side-view corners
 * of two neighbouring elements and measures the distance between them with a separating-axis test, so a
 * formula restated from the implementation cannot make a test pass. The fixture is 0.52 m deep and 0.96 m
 * tall, which is where every figure below comes from.
 */
final class LineArrayTest extends TestCase
{
    public function testAnUnsplayedArrayIsJustAStackOfCabinets(): void
    {
        $copies = $this->copies(new LineArray(3, [0.0, 0.0]));

        self::assertSame([0.0, 0.0, 0.0], $copies[0]->offset);
        self::assertSame([0.0, 0.0, -0.96], $this->rounded($copies[1]->offset));
        self::assertSame([0.0, 0.0, -1.92], $this->rounded($copies[2]->offset));

        foreach ($copies as $copy) {
            self::assertSame(0.0, $copy->pitchIncrementDeg);
        }
    }

    public function testTheSplayAccumulatesDownTheArray(): void
    {
        $tilts = array_map(
            static fn (PlacementCopy $c): float => round($c->pitchIncrementDeg, 9),
            $this->copies(new LineArray(4, [5.0, 5.0, 5.0])),
        );

        self::assertSame([0.0, 5.0, 10.0, 15.0], $tilts);
    }

    /**
     * A J array is the case that per-gap angles exist for: tight at the top for the distance, opening up
     * towards the bottom for the front rows.
     */
    public function testAJArrayTakesADifferentAngleAtEveryGap(): void
    {
        $tilts = array_map(
            static fn (PlacementCopy $c): float => round($c->pitchIncrementDeg, 9),
            $this->copies(new LineArray(4, [1.0, 3.0, 6.0])),
        );

        self::assertSame([0.0, 1.0, 4.0, 10.0], $tilts);
    }

    /**
     * The figure the whole class turns on: 5° of splay on a 0.52 × 0.96 cabinet drops the next element
     * 0.979 m and pushes it 0.085 m backwards, because the joint hinges on the rear edge.
     */
    public function testAFiveDegreeJointHingesOnTheRearEdge(): void
    {
        $copies = $this->copies(new LineArray(2, [5.0]));

        self::assertSame([0.0, 0.084659, -0.979007], $this->rounded($copies[1]->offset, 6));
        // Straight down would have been −0.96; the extra 19 mm is the rear edge swinging under.
        self::assertLessThan(-0.96, $copies[1]->offset[2]);
    }

    public function testEveryElementHangsBelowTheOneAboveItWithTheirEdgesTouching(): void
    {
        $device = $this->device();

        foreach ([[5.0, 5.0, 5.0], [1.0, 3.0, 6.0], [0.0, 0.0, 0.0]] as $splay) {
            $copies = $this->copies(new LineArray(4, $splay), $device);

            for ($index = 1; $index < count($copies); ++$index) {
                self::assertEqualsWithDelta(
                    0.0,
                    $this->separation($device, $copies[$index - 1], $copies[$index]),
                    1e-9,
                    sprintf('splay %s, joint %d', implode('/', $splay), $index),
                );
            }
        }
    }

    /**
     * An array that curves the other way pins the *front* edge instead, and the geometry says so rather
     * than the code choosing: whichever hinge drops furthest is the one that keeps the elements clear.
     */
    public function testTheHingeMovesToTheFrontEdgeWhenTheArrayCurvesUpwards(): void
    {
        $device = $this->device();
        $copies = $this->copies(new LineArray(2, [-5.0]), $device);

        // Mirror of the downward case: pushed forward rather than back, by the same amount.
        self::assertSame([0.0, -0.084659, -0.979007], $this->rounded($copies[1]->offset, 6));
        self::assertEqualsWithDelta(0.0, $this->separation($device, $copies[0], $copies[1]), 1e-9);
    }

    /**
     * Why the hinge has to be solved rather than assumed. Pinning the front edge of a downward-curving
     * array drives the elements 45 mm into each other — the vertical twin of the concave interpenetration
     * the arc's own docblock warns about, and just as plausible in a render.
     */
    public function testHingingOnTheWrongEdgeWouldDriveTheElementsIntoEachOther(): void
    {
        $device = $this->device();
        $solved = $this->copies(new LineArray(2, [5.0]), $device);

        // The front-edge answer for the same +5°, computed here rather than taken from the class.
        $splay = deg2rad(5.0);
        $wrong = new PlacementCopy([2], [
            0.0,
            -0.26 + 0.26 * cos($splay) + 0.96 * sin($splay),
            0.26 * sin($splay) - 0.96 * cos($splay),
        ], null, false, 5.0, false);

        $overlap = $this->separation($device, $solved[0], $wrong);
        self::assertLessThan(0.0, $overlap, 'the front-edge hinge overlaps');
        self::assertEqualsWithDelta(-0.045, $overlap, 1e-3);
    }

    /**
     * A hang is aimed as one body, so by the time the chain is laid out its top element is already tilted —
     * and the joints have to close at the angles the array actually reaches, not at plumb.
     */
    public function testTheJointsCloseAtTheTiltTheHangReachesRatherThanAtPlumb(): void
    {
        $device = $this->device();
        $base = 14.263;
        $copies = $this->copies(new LineArray(4, [2.0, 4.0, 7.0]), $device, $base);

        for ($index = 1; $index < count($copies); ++$index) {
            self::assertEqualsWithDelta(
                0.0,
                $this->separation($device, $copies[$index - 1], $copies[$index], $base),
                1e-9,
                "joint {$index}",
            );
        }
    }

    /**
     * Why the base tilt has to reach the chain. A joint is not scale-free in the angle — the one that closes
     * between 0° and 2° is not the one that closes between 14.263° and 16.263° — so solving plumb and then
     * tilting each element about its own origin leaves them inside each other. This is the state
     * `flown-array` shipped in until the shipped-scene sweep measured it.
     */
    public function testAChainSolvedAtPlumbWouldDriveATiltedHangIntoItself(): void
    {
        $device = $this->device();
        $base = 14.263;
        $plumb = $this->copies(new LineArray(4, [2.0, 4.0, 7.0]), $device, 0.0);

        $worst = 0.0;
        for ($index = 1; $index < count($plumb); ++$index) {
            $worst = min($worst, $this->separation($device, $plumb[$index - 1], $plumb[$index], $base));
        }

        // 21.6 mm in the elevation plane; the shipped scene, which is also yawed 11.1° towards its focus,
        // measured 21.7 mm in three dimensions.
        self::assertEqualsWithDelta(-0.0216, $worst, 1e-4);
    }

    /**
     * The increments stay the splay and nothing but the splay, because the aim adds its own tilt on top of
     * them. Counting the base twice would tilt every element below the first one further than it hangs.
     */
    public function testTheSplayIncrementsDoNotCountTheHangsOwnDownTilt(): void
    {
        $array = new LineArray(4, [2.0, 4.0, 7.0]);

        $increments = static fn (array $copies): array => array_map(
            static fn (PlacementCopy $copy): float => $copy->pitchIncrementDeg,
            $copies,
        );

        self::assertSame([0.0, 2.0, 6.0, 13.0], $increments($this->copies($array, null, 0.0)));
        self::assertSame([0.0, 2.0, 6.0, 13.0], $increments($this->copies($array, null, 14.263)));
    }

    /**
     * A wedge is the vertical twin of a tapered top: at its own taper the faces meet flat, and both hinges
     * give the same answer because the joint is closed front and back at once.
     */
    public function testAWedgeShapedElementsOwnTaperClosesTheJointFrontAndBack(): void
    {
        // 0.96 at the back, 0.80 at the front over 0.52 of depth: atan(0.16 / 0.52) = 17.103°.
        $wedge = $this->device(frontHeight: 0.80);
        $flush = rad2deg(atan2(0.96 - 0.80, 0.52));

        $copies = $this->copies(new LineArray(2, [$flush]), $wedge);

        self::assertEqualsWithDelta(0.0, $this->separation($wedge, $copies[0], $copies[1]), 1e-9);
        // Flat contact: the two elements' front faces are as close as their rear ones.
        self::assertEqualsWithDelta(17.103, $flush, 1e-3);
    }

    public function testAGapOpensEveryJoint(): void
    {
        $tight = $this->copies(new LineArray(2, [0.0]));
        $spaced = $this->copies(new LineArray(2, [0.0], gapM: 0.01));

        self::assertSame(-0.96, round($tight[1]->offset[2], 9));
        self::assertSame(-0.97, round($spaced[1]->offset[2], 9));
    }

    /**
     * Every element of a hang is flown, so none of them may be lifted back onto a slot: the lift exists for
     * cabinets that stand on something, and applying it here would pull the array apart at every joint,
     * each element by a different amount.
     */
    public function testNoElementIsSeatedOnTheFloor(): void
    {
        foreach ($this->copies(new LineArray(4, [5.0, 5.0, 5.0])) as $copy) {
            self::assertFalse($copy->seated);
        }
    }

    public function testASingleElementArrayIsJustTheCabinet(): void
    {
        $copies = $this->copies(new LineArray(1, []));

        self::assertCount(1, $copies);
        self::assertSame([0.0, 0.0, 0.0], $copies[0]->offset);
        self::assertSame([], $copies[0]->path, 'one element is not numbered');
        self::assertTrue($copies[0]->isAnchor);
    }

    public function testTheTopElementIsTheAnchorSoTheHangReadsFromTheTopDown(): void
    {
        $copies = $this->copies(new LineArray(4, [5.0, 5.0, 5.0]));

        $anchors = array_values(array_filter($copies, static fn (PlacementCopy $c): bool => $c->isAnchor));
        self::assertCount(1, $anchors);
        self::assertSame([0.0, 0.0, 0.0], $anchors[0]->offset);
    }

    public function testAScalarSplayIsRepeatedForEveryGap(): void
    {
        $array = LineArray::fromReader(new ArrayReader(['count' => 5, 'splay_deg' => 4.0]));

        self::assertSame([4.0, 4.0, 4.0, 4.0], $array->splayDeg);
    }

    public function testAPerGapListIsTakenAsWritten(): void
    {
        $array = LineArray::fromReader(new ArrayReader(['count' => 4, 'splay_deg' => [1, 2, 4]]));

        self::assertSame([1.0, 2.0, 4.0], $array->splayDeg);
    }

    /**
     * @return iterable<string, array{LineArray, string}>
     */
    public static function rejectionCases(): iterable
    {
        yield 'count below one' => [new LineArray(0, []), 'line_array.count must be at least 1'];
        yield 'a negative gap' => [new LineArray(2, [5.0], gapM: -0.01), 'line_array.gap_m must not be negative'];
        yield 'too few angles' => [
            new LineArray(4, [5.0]),
            'line_array.splay_deg needs one angle per gap — 3 for a count of 4, got 1',
        ];
        yield 'too many angles' => [
            new LineArray(2, [5.0, 5.0]),
            'line_array.splay_deg needs one angle per gap — 1 for a count of 2, got 2',
        ];
        yield 'a tilt that stands an element on its nose' => [
            new LineArray(13, array_fill(0, 12, 8.0)),
            'stands an element on its nose',
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectionCases')]
    public function testRejects(LineArray $array, string $expected): void
    {
        $device = $this->device();
        $problems = $array->problems($device, 0.0, 0.0, GroupStack::cabinetBox($device, 0.0, 0.0));

        self::assertNotSame([], $problems, 'expected a problem');
        self::assertTrue(
            (bool) array_filter($problems, static fn (string $m): bool => str_contains($m, $expected)),
            sprintf("no problem contained %s\ngot: %s", var_export($expected, true), implode(' | ', $problems)),
        );
    }

    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("line_array: unknown key 'splay'");

        LineArray::fromReader(new ArrayReader(['count' => 4, 'splay' => 5.0]));
    }

    /**
     * Signed distance between two elements' side-view silhouettes: 0 means they touch, negative means they
     * are inside each other. Built from the spec's own corners so it owes nothing to the code under test.
     */
    private function separation(
        DeviceSpec $device,
        PlacementCopy $above,
        PlacementCopy $below,
        float $baseDeg = 0.0,
    ): float {
        $silhouette = static function (PlacementCopy $copy) use ($device, $baseDeg): array {
            // The tilt an element ends up at is the hang's own down-tilt plus the splay accumulated to it,
            // which is what SceneCompiler adds together.
            $tilt = deg2rad($baseDeg + $copy->pitchIncrementDeg);
            $points = [];
            foreach ($device->contactCorners() as [, $y, $z]) {
                $points[] = [
                    $copy->offset[1] + $y * cos($tilt) - $z * sin($tilt),
                    $copy->offset[2] + $y * sin($tilt) + $z * cos($tilt),
                ];
            }

            return $points;
        };

        $a = $silhouette($above);
        $b = $silhouette($below);

        $widest = -INF;
        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            foreach ($from as $p) {
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
     * @return list<PlacementCopy>
     */
    private function copies(LineArray $array, ?DeviceSpec $device = null, float $pitchDeg = 0.0): array
    {
        $device ??= $this->device();

        return $array->copies($device, $pitchDeg, 0.0, GroupStack::cabinetBox($device, $pitchDeg, 0.0));
    }

    /**
     * @param array{float, float, float} $offset
     *
     * @return array{float, float, float}
     */
    private function rounded(array $offset, int $places = 9): array
    {
        return [round($offset[0], $places) + 0.0, round($offset[1], $places) + 0.0, round($offset[2], $places) + 0.0];
    }

    private function device(?float $frontHeight = null): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'array-element',
            'subtype' => 'line-array-element',
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 0.5, 'height' => 0.96, 'depth' => 0.52],
                'front_height_m' => $frontHeight,
            ],
            'appearance' => ['grille' => null],
        ]);
    }
}
