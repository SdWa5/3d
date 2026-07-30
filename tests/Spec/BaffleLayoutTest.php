<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\BaffleFeature;
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

        self::assertNotNull($layout);
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
