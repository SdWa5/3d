<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\BaffleFeature;
use App\Spec\Castors;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Spec\SpecValidator;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The feature kinds and keys added for the realistic fronts of 0.135.0: grilles, plugs, a nested horn's
 * setback, a blended throat, turned and mitred fins, tilted cells with a cone on their wall, a front colour
 * and castors. Each is checked for what it carries into the plan and for what the validator refuses.
 *
 * The factory cabinet is 0.8 x 0.6 x 0.45 m.
 */
final class BaffleFeatureKindsTest extends TestCase
{
    private SpecValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SpecValidator('/project');
    }

    public function testTheWholeSetIsValidTogether(): void
    {
        self::assertSame([], $this->validate(self::spec(self::validFeatures())));
    }

    public function testARoundGrilleTakesItsDiameterAsItsOpeningAndSaysItIsRound(): void
    {
        $grille = self::feature(self::spec(self::validFeatures()), 'grille-cone');

        self::assertTrue($grille->isGrille());
        self::assertEqualsWithDelta([0.254, 0.254], $grille->openingM(), 1e-9);
        self::assertTrue($grille->toArray()['round']);
        // On a cell's back wall it stands off the wall by itself, so it states no setback of its own.
        self::assertNull($grille->toArray()['setback_m']);
    }

    public function testARoundGrilleCarriesItsDomeAndRimAndASquareOneNeither(): void
    {
        $round = self::feature(self::spec(self::validFeatures()), 'grille-cone')->toArray();
        self::assertSame(0.02, $round['dome_m']);
        self::assertSame(0.015, $round['rim_m']);
        self::assertSame('#141414', $round['rim_color']);

        $square = self::feature(self::spec(self::validFeatures()), 'grille-front')->toArray();
        self::assertNull($square['dome_m']);
        self::assertNull($square['rim_m']);
        self::assertNull($square['rim_color']);
    }

    public function testASquareGrilleOnTheBaffleStandsAtItsSetback(): void
    {
        $grille = self::feature(self::spec(self::validFeatures()), 'grille-front');

        self::assertFalse($grille->toArray()['round']);
        self::assertSame(0.001, $grille->toArray()['setback_m']);
        self::assertSame([0.3, 0.2], $grille->toArray()['mouth_m']);
    }

    public function testAPlugIsADomeTheSizeOfItsDiameter(): void
    {
        $plug = self::feature(self::spec(self::validFeatures()), 'plug');

        self::assertTrue($plug->isPlug());
        self::assertEqualsWithDelta(7 * 0.0254, $plug->toArray()['cone_diameter_m'], 1e-9);
        self::assertNull($plug->toArray()['round']);
    }

    public function testANestedHornCarriesItsSetbackAndOneWithoutStaysAtTheThroat(): void
    {
        $spec = self::spec(self::validFeatures());

        self::assertSame(0.05, self::feature($spec, 'hf-forward')->toArray()['setback_m']);
        self::assertNull(self::feature($spec, 'hf-throat')->toArray()['setback_m']);
        // A top-level horn has nothing to stand back from.
        self::assertNull(self::feature($spec, 'mid')->toArray()['setback_m']);
    }

    public function testASetBackHornMayStandOffItsHostsAxis(): void
    {
        $high = self::feature(self::spec(self::validFeatures()), 'hf-high');

        self::assertSame([-0.15, 0.24], $high->toArray()['at_m']);
        self::assertSame(0.02, $high->toArray()['setback_m']);
    }

    public function testAThroatBlendTravelsOnAHornOnly(): void
    {
        $spec = self::spec(self::validFeatures());

        self::assertSame(0.04, self::feature($spec, 'mid')->toArray()['throat_blend_m']);
        self::assertNull(self::feature($spec, 'slot')->toArray()['throat_blend_m']);
    }

    public function testATiltedCellsBackWallLiesDeeperAtOneEnd(): void
    {
        $cell = self::feature(self::spec(self::validFeatures()), 'slot');

        self::assertSame('pitch', $cell->toArray()['turn']);
        $depths = $cell->cellBackDepths();
        self::assertNotNull($depths);
        self::assertEqualsWithDelta([0.2 - 0.15 * tan(M_PI / 6), 0.2 + 0.15 * tan(M_PI / 6)], $depths, 1e-9);
    }

    public function testAMitredFinIsAParallelogramWithItsFrontEdgeOnAt(): void
    {
        $fin = self::feature(self::spec(self::validFeatures()), 'zig');

        self::assertTrue($fin->mitred());
        self::assertTrue($fin->toArray()['mitre']);
        $footprint = $fin->finFootprint();
        self::assertNotNull($footprint);
        // The back edge lands depth * sin(angle) to the side of the front one.
        self::assertEqualsWithDelta(0.3 + 0.1 * sin(M_PI / 4), $footprint['x'][1], 1e-9);
    }

    public function testCastorsCarryTheirProtrusionIntoThePlanAndTheMetadata(): void
    {
        $spec = self::spec(self::validFeatures(), ['castors' => ['face' => 'back', 'diameter_m' => 0.1, 'color' => '#1f4fa8', 'locking' => 2]]);

        self::assertNotNull($spec->castors);
        $castors = $spec->castors->toArray();
        self::assertSame(Castors::COUNT, $castors['count']);
        self::assertEqualsWithDelta(0.128, $castors['protrusion_m'], 1e-9);
        self::assertSame($castors, $spec->toMetadataArray()['castors']);
        self::assertSame([], $this->validate($spec));
    }

    public function testCastorsRejectAMisspelledField(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("unknown key 'colour'");

        self::spec(self::validFeatures(), ['castors' => ['face' => 'back', 'diameter_m' => 0.1, 'colour' => '#1f4fa8']]);
    }

    /**
     * @param list<array<string, mixed>> $features
     * @param array<string, mixed>|null $castors
     */
    #[DataProvider('rejectionCases')]
    public function testRejects(array $features, string $expected, ?array $castors = null, ?string $frontColor = '#f2f2f0'): void
    {
        $messages = $this->validate(self::spec($features, null === $castors ? [] : ['castors' => $castors], $frontColor));

        self::assertNotEmpty(array_filter($messages, static fn (string $message): bool => str_contains($message, $expected)), implode("\n", $messages));
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>, 1: string, 2?: array<string, mixed>|null, 3?: string|null}>
     */
    public static function rejectionCases(): iterable
    {
        $cell = ['id' => 'slot', 'kind' => 'cell', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.3], 'depth_m' => 0.2];
        $horn = ['id' => 'mid', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.4, 0.3], 'throat_in' => 2.0, 'depth_m' => 0.2];

        yield 'a grille thicker than a sheet' => [
            [['id' => 'g', 'kind' => 'grille', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.3], 'depth_m' => 0.02]],
            'a grille is a sheet',
        ];
        yield 'a grille both round and square' => [
            [['id' => 'g', 'kind' => 'grille', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.3], 'diameter_in' => 10, 'depth_m' => 0.002]],
            'either mouth_m for a rectangle or diameter_in',
        ];
        yield 'a grille inside a horn' => [
            [$horn, ['id' => 'g', 'kind' => 'grille', 'inside' => 'mid', 'diameter_in' => 4, 'depth_m' => 0.002]],
            'a grille can only sit inside a cell',
        ];
        yield 'a grille too big for its cell wall' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 15, 'depth_m' => 0.002]],
            'does not fit the',
        ];
        yield 'a dome on a square grille' => [
            [['id' => 'g', 'kind' => 'grille', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.3], 'depth_m' => 0.002, 'dome_m' => 0.01]],
            'dome_m only applies to a round grille',
        ];
        yield 'a rim on a cone' => [
            [['id' => 'c', 'kind' => 'cone', 'at_m' => [0.0, 0.0], 'diameter_in' => 10, 'depth_m' => 0.08, 'rim_m' => 0.01]],
            'rim_m only applies to a round grille',
        ];
        yield 'a dome deeper than a pressed sheet' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 8, 'depth_m' => 0.002, 'dome_m' => 0.05]],
            'dome_m must be above 0 and at most 0.03',
        ];
        yield 'a rim as wide as the grille' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 8, 'depth_m' => 0.002, 'rim_m' => 0.11]],
            "below the grille's radius",
        ];
        yield 'a rim colour without a rim' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 8, 'depth_m' => 0.002, 'rim_color' => '#141414']],
            'rim_color needs rim_m',
        ];
        yield 'a malformed rim colour' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 8, 'depth_m' => 0.002, 'rim_m' => 0.01,
                'rim_color' => 'black']],
            "rim_color 'black' must be a #rrggbb hex colour",
        ];
        yield 'a grille on a cell wall with a setback' => [
            [$cell, ['id' => 'g', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 8, 'depth_m' => 0.002, 'setback_m' => 0.01]],
            'setback_m only applies to',
        ];
        yield 'a nested horn placed off axis without a setback' => [
            [$horn, ['id' => 'hf', 'kind' => 'horn', 'inside' => 'mid', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0, 'depth_m' => 0.05]],
            'which only a horn set back with setback_m takes',
        ];
        yield 'a set-back horn placed past its host\'s mouth' => [
            [$horn, ['id' => 'hf', 'kind' => 'horn', 'inside' => 'mid', 'at_m' => [0.0, 0.14], 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0,
                'depth_m' => 0.05, 'setback_m' => 0.02]],
            'past the z edge of the horn it sits inside',
        ];
        yield 'a plug on the baffle' => [
            [['id' => 'p', 'kind' => 'plug', 'at_m' => [0.0, 0.0], 'diameter_in' => 4, 'depth_m' => 0.05]],
            'so it needs `inside`',
        ];
        yield 'a plug with a mouth' => [
            [$horn, ['id' => 'p', 'kind' => 'plug', 'inside' => 'mid', 'mouth_m' => [0.1, 0.1], 'diameter_in' => 4, 'depth_m' => 0.05]],
            'a plug is round',
        ];
        yield 'a plug deeper than its horn' => [
            [$horn, ['id' => 'p', 'kind' => 'plug', 'inside' => 'mid', 'diameter_in' => 4, 'depth_m' => 0.3]],
            'deeper than the horn it sits inside',
        ];
        yield 'a nested horn set back past its host throat' => [
            [$horn, ['id' => 'hf', 'kind' => 'horn', 'inside' => 'mid', 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0, 'depth_m' => 0.05, 'setback_m' => 0.18]],
            'reaches past the throat of the horn it sits inside',
        ];
        yield 'a setback on a top-level horn' => [
            [[...$horn, 'setback_m' => 0.02]],
            'setback_m only applies to',
        ];
        yield 'a negative setback on a grille' => [
            [['id' => 'g', 'kind' => 'grille', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.3, 0.3], 'depth_m' => 0.002, 'setback_m' => -0.01]],
            'setback_m must not be negative',
        ];
        yield 'a throat blend deeper than the horn' => [
            [[...$horn, 'throat_blend_m' => 0.3]],
            'throat_blend_m must be above 0 and at most depth_m',
        ];
        yield 'a throat blend on a cell' => [
            [[...$cell, 'throat_blend_m' => 0.05]],
            'throat_blend_m only applies to a horn',
        ];
        yield 'a mitre on an unturned fin' => [
            [$cell, ['id' => 'f', 'kind' => 'fin', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.02, 0.3], 'depth_m' => 0.1, 'mitre' => true]],
            'mitre needs a turned plate',
        ];
        yield 'an unknown turn' => [
            [[...$cell, 'angle_deg' => 10, 'turn' => 'roll']],
            "turn 'roll' must be one of",
        ];
        yield 'a cell wall tilted out of the front' => [
            [[...$cell, 'depth_m' => 0.05, 'angle_deg' => 45]],
            'its back wall comes out',
        ];
        yield 'a front colour that is not hex' => [
            [$cell],
            "appearance.front_color 'white' must be a #rrggbb hex colour",
            null,
            'white',
        ];
        yield 'castors under the cabinet' => [
            [$cell],
            "physical.castors.face 'bottom' must be one of",
            ['face' => 'bottom', 'diameter_m' => 0.1],
        ];
        yield 'more locking castors than wheels' => [
            [$cell],
            'physical.castors.locking must be between 0 and 4',
            ['face' => 'back', 'diameter_m' => 0.1, 'locking' => 5],
        ];
        yield 'castors too big for their face' => [
            [$cell],
            'do not fit the left face',
            ['face' => 'left', 'diameter_m' => 0.18],
        ];
    }

    /**
     * Every new kind and key at once, in a layout that validates.
     *
     * @return list<array<string, mixed>>
     */
    private static function validFeatures(): array
    {
        return [
            ['id' => 'mid', 'kind' => 'horn', 'at_m' => [-0.15, 0.12], 'mouth_m' => [0.4, 0.3], 'throat_in' => 4.0, 'driver_in' => 6,
                'depth_m' => 0.2, 'throat_blend_m' => 0.04],
            ['id' => 'plug', 'kind' => 'plug', 'inside' => 'mid', 'diameter_in' => 7, 'depth_m' => 0.06],
            ['id' => 'hf-forward', 'kind' => 'horn', 'inside' => 'mid', 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0, 'depth_m' => 0.05,
                'setback_m' => 0.05],
            ['id' => 'hf-throat', 'kind' => 'horn', 'inside' => 'mid', 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0, 'depth_m' => 0.05],
            ['id' => 'hf-high', 'kind' => 'horn', 'inside' => 'mid', 'at_m' => [-0.15, 0.24], 'mouth_m' => [0.1, 0.05], 'throat_in' => 1.0,
                'depth_m' => 0.05, 'setback_m' => 0.02],
            ['id' => 'slot', 'kind' => 'cell', 'at_m' => [0.2, -0.1], 'mouth_m' => [0.3, 0.3], 'depth_m' => 0.2, 'angle_deg' => 30, 'turn' => 'pitch'],
            ['id' => 'cone', 'kind' => 'cone', 'inside' => 'slot', 'diameter_in' => 10, 'depth_m' => 0.08],
            ['id' => 'grille-cone', 'kind' => 'grille', 'inside' => 'slot', 'diameter_in' => 10, 'depth_m' => 0.002, 'dome_m' => 0.02,
                'rim_m' => 0.015, 'rim_color' => '#141414'],
            ['id' => 'grille-front', 'kind' => 'grille', 'at_m' => [-0.15, -0.15], 'mouth_m' => [0.3, 0.2], 'depth_m' => 0.0015,
                'setback_m' => 0.001, 'color' => '#2b2b2b'],
            ['id' => 'zig', 'kind' => 'fin', 'at_m' => [0.3, -0.1], 'mouth_m' => [0.01, 0.2], 'depth_m' => 0.1, 'angle_deg' => 45,
                'mitre' => true],
        ];
    }

    /**
     * @param list<array<string, mixed>> $features
     * @param array<string, mixed> $physical merged over the factory's physical section
     */
    private static function spec(array $features, array $physical = [], ?string $frontColor = '#f2f2f0'): DeviceSpec
    {
        $base = SpecFactory::specArray();

        return SpecFactory::spec([
            'appearance' => [...$base['appearance'], 'front_color' => $frontColor],
            'physical' => [...$base['physical'], ...$physical],
            'audio' => ['drivers' => $base['audio']['drivers'], 'layout' => ['provenance' => 'estimated', 'inset_m' => 0.0, 'features' => $features]],
        ]);
    }

    private static function feature(DeviceSpec $spec, string $id): BaffleFeature
    {
        $feature = $spec->layout?->feature($id);
        self::assertNotNull($feature);

        return $feature;
    }

    /**
     * @return list<string>
     */
    private function validate(DeviceSpec $spec): array
    {
        return array_map(static fn ($violation): string => $violation->message, $this->validator->validate([$spec]));
    }
}
