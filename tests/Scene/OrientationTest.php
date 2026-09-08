<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Orientation;
use PHPUnit\Framework\TestCase;

/**
 * Guards the one thing every other number in the library is built on: which way a cabinet is turned.
 *
 * The class composes `Rz(yaw)·Rx(pitch)·Ry(roll)` — roll in the cabinet's own frame, then the down-tilt,
 * then the aim — while Blender's `rotation_euler` is `Rz·Ry·Rx`. The tests here pin both halves of that:
 * that the conversion between them is exact, and that it returns the angles it always did for the rolls
 * every committed scene actually uses.
 */
final class OrientationTest extends TestCase
{
    private const TOLERANCE = 1e-9;

    /**
     * The regression fence for every committed scene. `Rx(p)·Ry(180) === Ry(180)·Rx(−p)` is an identity,
     * so a cabinet that is upright or turned over comes back out as the angles that were written down —
     * exactly, not to within a rounding error.
     */
    public function testRollZeroAndRollOneEightyStillProduceTheEulerTripleTheyAlwaysDid(): void
    {
        foreach ([0.0, 1.13, 1.19, 4.4, 22.0, -3.0] as $pitch) {
            foreach ([-9.92, 0.0, 17.35] as $yaw) {
                self::assertSame(
                    [$pitch, 0.0, $yaw],
                    (new Orientation($pitch, 0.0, $yaw))->eulerXYZ(),
                    "upright at pitch {$pitch}, yaw {$yaw}",
                );
                self::assertSame(
                    [0.0 === $pitch ? 0.0 : -$pitch, 180.0, $yaw],
                    (new Orientation($pitch, 180.0, $yaw))->eulerXYZ(),
                    "turned over at pitch {$pitch}, yaw {$yaw}",
                );
            }
        }
    }

    /**
     * The oracle that makes the branch rule in `eulerXYZ()` safe to touch: whatever triple comes out,
     * feeding it back through Blender's order has to rebuild the rotation it was factored from.
     */
    public function testTheEulerTripleReproducesTheRotationItCameFrom(): void
    {
        foreach ([-30.0, -4.4, 0.0, 1.13, 4.4, 22.0, 45.0, 89.0] as $pitch) {
            foreach ([0.0, 30.0, 45.0, 90.0, 135.0, 180.0, 270.0, -90.0] as $roll) {
                foreach ([0.0, -9.92, 17.35, 90.0, -170.0, 180.0] as $yaw) {
                    $orientation = new Orientation($pitch, $roll, $yaw);
                    [$a, $b, $c] = $orientation->eulerXYZ();

                    self::assertMatrixSame(
                        $orientation->matrix(),
                        self::blenderMatrix($a, $b, $c),
                        sprintf('pitch %s, roll %s, yaw %s', $pitch, $roll, $yaw),
                    );
                }
            }
        }
    }

    /**
     * Roll turns a cabinet about its own front-to-back axis, which leaves that axis alone — so a cabinet
     * on its side points exactly where an upright one does. That is the whole reason for composing the
     * rotation in this order, and it is what makes `roll_deg: 90` aimable at all.
     */
    public function testACabinetOnItsSideStillPointsWhereItIsAimed(): void
    {
        $upright = (new Orientation(10.0, 0.0, 25.0))->apply([0.0, -1.0, 0.0]);

        foreach ([90.0, 180.0, 270.0, -90.0, 45.0] as $roll) {
            $rolled = (new Orientation(10.0, $roll, 25.0))->apply([0.0, -1.0, 0.0]);

            for ($axis = 0; $axis < 3; ++$axis) {
                self::assertEqualsWithDelta($upright[$axis], $rolled[$axis], self::TOLERANCE, "roll {$roll}");
            }
        }
    }

    /**
     * Stated pitch used to mean the opposite thing on a rolled cabinet than aimed pitch did: in Blender's
     * order the roll is applied after the tilt and turns nose-down into nose-up, so `pitch_deg: 5` with
     * `roll_deg: 180` aimed a cabinet at the ceiling while `aim:` on the same cabinet aimed it at the
     * floor. Nose-down is now nose-down whichever way up the cabinet is.
     */
    public function testARolledCabinetTiltsNoseDownJustLikeAnUprightOne(): void
    {
        foreach ([0.0, 90.0, 180.0, 270.0] as $roll) {
            $direction = (new Orientation(5.0, $roll, 0.0))->apply([0.0, -1.0, 0.0]);

            self::assertLessThan(0.0, $direction[2], "roll {$roll} should tilt the nose down");
            self::assertEqualsWithDelta(-sin(deg2rad(5.0)), $direction[2], self::TOLERANCE, "roll {$roll}");
        }
    }

    /**
     * A cabinet on its side with no tilt sits exactly on euler XYZ's gimbal lock — its middle angle is
     * ±90°, where the outer two axes coincide and only their difference is determined. Blender does not
     * care, but the triple is discontinuous in the pitch there, and somebody reading a build plan should
     * find that written down rather than discover it.
     */
    public function testACabinetOnItsSideLandsOnBlendersGimbalLock(): void
    {
        self::assertSame([0.0, 90.0, 25.0], self::rounded((new Orientation(0.0, 90.0, 25.0))->eulerXYZ()));
        self::assertSame([0.0, -90.0, 25.0], self::rounded((new Orientation(0.0, 270.0, 25.0))->eulerXYZ()));

        // A hair of tilt swings the triple right across: the rotation moves by 0.1°, its factorisation
        // by ninety.
        self::assertSame([90.0, 89.9, 115.0], self::rounded((new Orientation(0.1, 90.0, 25.0))->eulerXYZ()));
        self::assertSame([-90.0, 89.9, -65.0], self::rounded((new Orientation(-0.1, 90.0, 25.0))->eulerXYZ()));
    }

    /**
     * `aim_at` decides yaw and pitch together; roll is carried through untouched, because it cannot
     * change where the cabinet points.
     */
    public function testAimingSetsYawAndPitchAndLeavesTheRollAlone(): void
    {
        $aimed = Orientation::aimedAt([0.0, 0.0, 2.0], [0.0, -10.0, 1.8], 0.96, 180.0);

        self::assertSame(180.0, $aimed->rollDeg);
        self::assertSame(0.0, $aimed->yawDeg);
        // 2.0 + 0.96/2 = 2.48 m up, 10 m out, so the nose comes down by atan(0.68/10).
        self::assertEqualsWithDelta(rad2deg(atan2(0.68, 10.0)), $aimed->pitchDeg, self::TOLERANCE);
    }

    public function testTheDownTiltTowardsATargetIsTheSameWhicheverWayUpTheCabinetIs(): void
    {
        $from = [0.0, 0.0, 2.0];
        $target = [1.0, -8.0, 1.8];

        $upright = Orientation::aimedAt($from, $target, 0.96, 0.0);
        $rolled = Orientation::aimedAt($from, $target, 0.96, 90.0);

        self::assertSame($upright->pitchDeg, $rolled->pitchDeg);
        self::assertSame($upright->yawDeg, $rolled->yawDeg);
    }

    /**
     * A target nearly abeam would otherwise drive the tilt towards vertical, which is a cabinet pointing
     * somewhere else entirely rather than a tilt worth computing. The cap is on the target's bearing
     * relative to the cabinet's own yaw, and roll does not move the yaw axis, so it bites the same way
     * for a cabinet on its side.
     */
    public function testTheOffAxisCapBitesTheSameWayForACabinetOnItsSide(): void
    {
        // 85° off the cabinet's own axis, past the 80° cap.
        $abeam = Orientation::pitchTowards([0.0, 0.0, 3.0], [10.0, -0.875, 0.0], 0.96, 0.0);

        $expected = rad2deg(atan2(3.48, sqrt(10.0 ** 2 + 0.875 ** 2) * cos(deg2rad(Orientation::MAX_OFF_AXIS_DEG))));
        self::assertEqualsWithDelta($expected, $abeam, self::TOLERANCE);
    }

    public function testTheBuildPlanCarriesBothTheStatedAnglesAndBlendersOwnOrder(): void
    {
        $plan = (new Orientation(4.4, 180.0, -8.41))->toArray();

        self::assertSame(4.4, $plan['pitch_deg']);
        self::assertSame(180.0, $plan['roll_deg']);
        self::assertSame(-8.41, $plan['yaw_deg']);
        self::assertSame([-4.4, 180.0, -8.41], $plan['rotation_euler_deg']);
    }

    /**
     * A yaw of `-0.0` serialises as `-0` into the build plan, so a concave arc's middle cabinet would
     * show up as a diff every time a scene is rebuilt.
     */
    public function testNegativeZeroNeverReachesTheBuildPlan(): void
    {
        $plan = (new Orientation(0.0, 180.0, -0.0))->toArray();

        self::assertSame(0.0, $plan['yaw_deg']);
        self::assertSame(0.0, $plan['rotation_euler_deg'][0]);
        self::assertStringNotContainsString('-0', json_encode($plan, JSON_THROW_ON_ERROR));
    }

    /**
     * The round trip that makes {@see Orientation::after} usable: any rotation these three angles can
     * describe has to come back out of its own matrix.
     */
    public function testTheThreeAnglesAreRecoveredFromTheirOwnMatrix(): void
    {
        foreach ([-45.0, -4.4, 0.0, 1.13, 30.0, 89.0] as $pitch) {
            foreach ([0.0, 45.0, 90.0, 180.0, 270.0, -90.0] as $roll) {
                foreach ([0.0, -9.92, 17.35, 90.0, 180.0] as $yaw) {
                    $original = new Orientation($pitch, $roll, $yaw);
                    $recovered = Orientation::fromMatrix($original->matrix());

                    self::assertNotNull($recovered);
                    self::assertMatrixSame(
                        $original->matrix(),
                        $recovered->matrix(),
                        sprintf('pitch %s, roll %s, yaw %s', $pitch, $roll, $yaw),
                    );
                }
            }
        }
    }

    /**
     * Composition is matrix multiplication, and the case that proves angle addition would not do: rolling
     * a group over reverses the yaw of everything inside it.
     */
    public function testRollingAGroupOverReversesTheYawOfEverythingInsideIt(): void
    {
        $rolledGroup = new Orientation(0.0, 180.0, 0.0);
        $seatInside = new Orientation(0.0, 0.0, 17.35);

        $composed = $rolledGroup->after($seatInside);

        self::assertNotNull($composed);
        self::assertEqualsWithDelta(-17.35, $composed->yawDeg, self::TOLERANCE);
        self::assertEqualsWithDelta(180.0, abs($composed->rollDeg), self::TOLERANCE);
        // Adding the angles would have left the seat turned the other way — into its neighbour.
        self::assertNotEqualsWithDelta(17.35, $composed->yawDeg, self::TOLERANCE);
    }

    public function testComposingWithNoTurnAtAllChangesNothing(): void
    {
        $rotation = new Orientation(4.4, 180.0, -8.41);
        $identity = new Orientation();

        foreach ([$rotation->after($identity), $identity->after($rotation)] as $composed) {
            self::assertNotNull($composed);
            self::assertMatrixSame($rotation->matrix(), $composed->matrix(), 'composed with the identity');
        }
    }

    /**
     * A group's turn applies outside the cabinet's own attitude, not inside it — which is what makes an
     * arc seat's yaw a turn in the world rather than a turn within an already-tilted frame.
     */
    public function testCompositionAppliesTheOuterTurnInTheWorldAndTheInnerOneInTheCell(): void
    {
        $seatYaw = new Orientation(0.0, 0.0, 30.0);
        $cabinetAttitude = new Orientation(10.0, 0.0, 0.0);

        $composed = $seatYaw->after($cabinetAttitude);

        self::assertNotNull($composed);
        // Yaw about Z then pitch about X is what the compiler already produced for an aimed arc seat.
        self::assertEqualsWithDelta(10.0, $composed->pitchDeg, self::TOLERANCE);
        self::assertEqualsWithDelta(30.0, $composed->yawDeg, self::TOLERANCE);
    }

    /**
     * A cabinet standing on end is where the roll and the yaw become the same turn. There is no reason to
     * prefer one of the infinitely many splits, so the answer is that there is no answer.
     */
    public function testARotationThatCannotBeSplitIntoAnglesIsRefusedRatherThanApproximated(): void
    {
        self::assertNull(Orientation::fromMatrix((new Orientation(90.0, 0.0, 0.0))->matrix()));
        self::assertNull(Orientation::fromMatrix((new Orientation(-90.0, 45.0, 20.0))->matrix()));
        self::assertNotNull(Orientation::fromMatrix((new Orientation(89.9, 45.0, 20.0))->matrix()));
    }

    /**
     * Blender's own order: `Rz(c)·Ry(b)·Rx(a)`.
     *
     * @return array{array{float, float, float}, array{float, float, float}, array{float, float, float}}
     */
    private static function blenderMatrix(float $aDeg, float $bDeg, float $cDeg): array
    {
        $a = deg2rad($aDeg);
        $b = deg2rad($bDeg);
        $c = deg2rad($cDeg);

        $rx = [[1.0, 0.0, 0.0], [0.0, cos($a), -sin($a)], [0.0, sin($a), cos($a)]];
        $ry = [[cos($b), 0.0, sin($b)], [0.0, 1.0, 0.0], [-sin($b), 0.0, cos($b)]];
        $rz = [[cos($c), -sin($c), 0.0], [sin($c), cos($c), 0.0], [0.0, 0.0, 1.0]];

        return self::multiply($rz, self::multiply($ry, $rx));
    }

    /**
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $left
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $right
     *
     * @return array{array{float, float, float}, array{float, float, float}, array{float, float, float}}
     */
    private static function multiply(array $left, array $right): array
    {
        $out = [];
        for ($row = 0; $row < 3; ++$row) {
            for ($column = 0; $column < 3; ++$column) {
                $out[$row][$column] = $left[$row][0] * $right[0][$column]
                    + $left[$row][1] * $right[1][$column]
                    + $left[$row][2] * $right[2][$column];
            }
        }

        /** @var array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $out */
        return $out;
    }

    /**
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $expected
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $actual
     */
    private static function assertMatrixSame(array $expected, array $actual, string $message): void
    {
        for ($row = 0; $row < 3; ++$row) {
            for ($column = 0; $column < 3; ++$column) {
                self::assertEqualsWithDelta(
                    $expected[$row][$column],
                    $actual[$row][$column],
                    self::TOLERANCE,
                    sprintf('%s — m[%d][%d]', $message, $row, $column),
                );
            }
        }
    }

    /**
     * @param array{float, float, float} $angles
     *
     * @return array{float, float, float}
     */
    private static function rounded(array $angles): array
    {
        return [round($angles[0], 6), round($angles[1], 6), round($angles[2], 6)];
    }
}
