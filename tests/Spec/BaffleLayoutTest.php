<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\BaffleFeature;
use App\Spec\InvalidSpecException;
use App\Spec\Provenance;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The baffle layout: what a spec says about the openings on a cabinet's front, and what the build plan
 * hands to bpy. The unit conversions matter most — the spec talks in inches because that is how the
 * audio world names throats and drivers, and everything downstream is metres.
 */
final class BaffleLayoutTest extends TestCase
{
    public function testASpecWithoutALayoutHasNone(): void
    {
        self::assertNull(SpecFactory::spec()->layout);
    }

    public function testFeaturesLoadWithTheirProvenanceAndInset(): void
    {
        $layout = $this->layout();

        self::assertSame(Provenance::Estimated, $layout->provenance);
        self::assertSame(0.018, $layout->insetM);
        self::assertCount(3, $layout->features);
    }

    public function testInchSizesBecomeMetres(): void
    {
        $layout = $this->layout();

        // 2" throat and a 12" cone behind it.
        self::assertSame(0.0508, $layout->feature('horn')->throatM());
        self::assertEqualsWithDelta(0.3048, $layout->feature('horn')->coneDiameterM(), 1e-9);
        self::assertEqualsWithDelta(0.4572, $layout->feature('woofer')->coneDiameterM(), 1e-9);
    }

    public function testAConeHasNoThroatAndTakesItsDiameterAsItsOpening(): void
    {
        $cone = $this->layout()->feature('woofer');

        self::assertTrue($cone->isCone());
        self::assertNull($cone->throatM());
        self::assertEqualsWithDelta([0.4572, 0.4572], $cone->openingM(), 1e-9);
    }

    public function testAHornDefaultsToAFourSidedLinearFlare(): void
    {
        // Defaults chosen so a layout written before these fields existed builds the same geometry.
        $horn = $this->layout()->feature('horn');

        self::assertSame(BaffleFeature::PYRAMID, $horn->profile);
        self::assertSame(BaffleFeature::LINEAR, $horn->flare);
        self::assertNull($horn->sides);
        self::assertSame(4, $horn->wallCount());
    }

    public function testTheThroatDefaultsToTheMouthsShape(): void
    {
        $horn = $this->layout()->feature('horn');

        self::assertNull($horn->throatProfile);
        self::assertSame(BaffleFeature::PYRAMID, $horn->throatProfileOrMouth());
    }

    public function testAStraightEdgedMouthCanHaveARoundThroat(): void
    {
        // What a compression-driver horn actually is: the throat is a round bolt flange, the mouth is not.
        $horn = $this->layout([
            ['id' => 'cd', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.45, 0.2],
                'throat_in' => 2.0, 'depth_m' => 0.18,
                'profile' => 'pyramid', 'throat_profile' => 'elliptical'],
        ])->feature('cd');

        self::assertSame(BaffleFeature::PYRAMID, $horn->profile);
        self::assertSame(BaffleFeature::ELLIPTICAL, $horn->throatProfileOrMouth());
        // Still four walls to count, because the mouth end has them.
        self::assertSame(4, $horn->wallCount());
        self::assertSame('elliptical', $horn->toArray()['throat_profile']);
    }

    public function testWallsAreCountedWhenOnlyTheThroatHasThem(): void
    {
        $horn = $this->layout([
            ['id' => 'cd', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.45, 0.2],
                'throat_in' => 2.0, 'depth_m' => 0.18,
                'profile' => 'elliptical', 'throat_profile' => 'pyramid', 'sides' => 6],
        ])->feature('cd');

        self::assertSame(6, $horn->wallCount());
    }

    public function testAnEllipticalMouthHasNoWallsToCount(): void
    {
        $layout = $this->layout([
            ['id' => 'oval', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.2],
                'throat_in' => 1.4, 'depth_m' => 0.1, 'profile' => 'elliptical'],
        ]);

        // Elliptical at both ends, so there is nothing to count.
        self::assertNull($layout->feature('oval')->wallCount());
    }

    public function testTheBuildPlanCarriesShapeAndMetresOnly(): void
    {
        $feature = $this->layout([
            ['id' => 'oct', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.36, 0.28],
                'throat_in' => 1.4, 'depth_m' => 0.18,
                'profile' => 'pyramid', 'sides' => 8, 'flare' => 'exponential'],
        ])->feature('oct')->toArray();

        self::assertSame(8, $feature['sides']);
        self::assertSame('exponential', $feature['flare']);
        self::assertSame('pyramid', $feature['profile']);
        // bpy does no unit arithmetic: inches never reach it.
        self::assertEqualsWithDelta(0.03556, $feature['throat_m'], 1e-9);
        self::assertArrayNotHasKey('throat_in', $feature);
    }

    public function testAConeReportsNoWallShapeAtAll(): void
    {
        // A driver cone is round with a straight profile; emitting a mouth shape for it would invite the
        // builder to honour a field the spec never meant.
        $feature = $this->layout()->feature('woofer')->toArray();

        self::assertNull($feature['profile']);
        self::assertNull($feature['sides']);
        self::assertNull($feature['flare']);
    }

    public function testAHornIsNotJoinedToAnythingUnlessItSaysSo(): void
    {
        // The default has to leave every layout written before `join` existed building what it always did.
        $horn = $this->layout()->feature('horn');

        self::assertNull($horn->join);
        self::assertNull($horn->toArray()['join']);
    }

    public function testTwoHornsCanShareOneMouth(): void
    {
        $layout = $this->layout([
            ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 10.0, 'depth_m' => 0.2],
            ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 10.0, 'depth_m' => 0.2,
                'join' => ['with' => 'lf-up', 'depth_m' => 0.045]],
        ]);

        $join = $layout->feature('lf-lo')->join;
        self::assertNotNull($join);
        self::assertSame('lf-up', $join->with);
        self::assertSame(0.045, $join->depthM);
        // The relation lives on one side only: the earlier horn knows nothing about it.
        self::assertNull($layout->feature('lf-up')->join);
        self::assertSame(
            ['with' => 'lf-up', 'depth_m' => 0.045],
            $layout->feature('lf-lo')->toArray()['join'],
        );
    }

    public function testAJoinRejectsAMisspelledField(): void
    {
        // Neither field has a default, so a typo would build a different cabinet with nothing to see.
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("join: unknown key 'depth'");

        $this->layout([
            ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 10.0, 'depth_m' => 0.2],
            ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 10.0, 'depth_m' => 0.2,
                'join' => ['with' => 'lf-up', 'depth' => 0.045]],
        ]);
    }

    public function testACellAndAFinCarryTheirOwnFieldsIntoThePlan(): void
    {
        $layout = $this->layout([
            ['id' => 'slot', 'kind' => 'cell', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.5], 'depth_m' => 0.4,
                'color' => '#22406e'],
            ['id' => 'brace', 'kind' => 'fin', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.02, 0.5], 'depth_m' => 0.2,
                'angle_deg' => 20.0, 'setback_m' => 0.03, 'color' => '#78b06e'],
        ]);

        $slot = $layout->feature('slot')->toArray();
        self::assertSame('cell', $slot['kind']);
        self::assertSame([0.3, 0.5], $slot['mouth_m']);
        self::assertSame('#22406e', $slot['color']);
        // A cell's back wall is square to the baffle unless it states a tilt.
        self::assertSame(0.0, $slot['angle_deg']);
        self::assertNull($slot['throat_m']);

        $brace = $layout->feature('brace')->toArray();
        self::assertSame(20.0, $brace['angle_deg']);
        self::assertSame(0.03, $brace['setback_m']);
        self::assertSame('#78b06e', $brace['color']);
    }

    public function testAFinThatStatesNoTurnIsSquareToTheBaffle(): void
    {
        $fin = $this->layout([
            ['id' => 'fin', 'kind' => 'fin', 'at_m' => [0.1, 0.0], 'mouth_m' => [0.02, 0.4], 'depth_m' => 0.3],
        ])->feature('fin');

        self::assertSame(0.0, $fin->toArray()['angle_deg']);
        self::assertSame(0.0, $fin->toArray()['setback_m']);
        $footprint = $fin->finFootprint();
        self::assertNotNull($footprint);
        self::assertEqualsWithDelta([0.09, 0.11], $footprint['x'], 1e-9);
        self::assertEqualsWithDelta([-0.2, 0.2], $footprint['z'], 1e-9);
        self::assertEqualsWithDelta(0.3, $footprint['reach'], 1e-9);
    }

    public function testATurnedFinSwingsItsBackTowardsPositiveXAndStaysBehindTheBaffle(): void
    {
        // 30 degrees on a 0.2 m deep, 0.02 m thick vertical plate: the back moves 0.1 m to +x, and the
        // front corner that would poke out by 0.005 m is shifted back, so the reach grows by that much.
        $fin = $this->layout([
            ['id' => 'fin', 'kind' => 'fin', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.02, 0.4], 'depth_m' => 0.2,
                'angle_deg' => 30.0, 'setback_m' => 0.01],
        ])->feature('fin');

        $footprint = $fin->finFootprint();
        self::assertNotNull($footprint);
        self::assertEqualsWithDelta(-0.01 * cos(M_PI / 6), $footprint['x'][0], 1e-9);
        self::assertEqualsWithDelta(0.2 * sin(M_PI / 6) + 0.01 * cos(M_PI / 6), $footprint['x'][1], 1e-9);
        self::assertEqualsWithDelta(0.01 + 0.2 * cos(M_PI / 6) + 0.02 * sin(M_PI / 6), $footprint['reach'], 1e-9);
    }

    public function testAWideFinIsHorizontalAndTurnsTowardsPositiveZ(): void
    {
        $fin = $this->layout([
            ['id' => 'shelf', 'kind' => 'fin', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.02], 'depth_m' => 0.2,
                'angle_deg' => 30.0],
        ])->feature('shelf');

        $footprint = $fin->finFootprint();
        self::assertNotNull($footprint);
        self::assertEqualsWithDelta([-0.2, 0.2], $footprint['x'], 1e-9);
        self::assertGreaterThan(0.1 + 0.09, $footprint['z'][1]);
    }

    public function testAFeatureRejectsAMisspelledField(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("unknown key 'colour'");

        $this->layout([
            ['id' => 'fin', 'kind' => 'fin', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.02, 0.4], 'depth_m' => 0.2,
                'colour' => '#ffffff'],
        ]);
    }

    public function testANestedFeatureHasNoPositionOfItsOwn(): void
    {
        $plug = $this->layout()->feature('plug');

        self::assertSame('horn', $plug->inside);
        self::assertNull($plug->at);
    }

    /**
     * @param list<array<string, mixed>>|null $features
     */
    private function layout(?array $features = null): \App\Spec\BaffleLayout
    {
        $spec = SpecFactory::spec(['audio' => [
            'drivers' => [['size_in' => 12, 'type' => 'woofer', 'count' => 1]],
            'layout' => [
                'provenance' => 'estimated',
                'inset_m' => 0.018,
                'features' => $features ?? [
                    ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.2],
                        'throat_in' => 2.0, 'driver_in' => 12, 'depth_m' => 0.15],
                    ['id' => 'plug', 'kind' => 'horn', 'inside' => 'horn', 'mouth_m' => [0.08, 0.08],
                        'throat_in' => 1.0, 'depth_m' => 0.04],
                    ['id' => 'woofer', 'kind' => 'cone', 'at_m' => [0.0, -0.15], 'diameter_in' => 18,
                        'depth_m' => 0.1],
                ],
            ],
        ]]);

        self::assertNotNull($spec->layout);

        return $spec->layout;
    }
}
