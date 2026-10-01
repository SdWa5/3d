<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Interpenetration;
use App\Scene\PlacedDevice;
use App\Scene\PlacementChecks;
use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Scene\StackBackdrop;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * PSL's deco panel on our truss, behind the rig, under the next event's 4 m ceiling.
 *
 * Run against the real truss and tower specs, because the case is about their numbers: a 0.258 m deep F33, a 4 m
 * wind-up rated 85 kg, and five 9.3 kg segments.
 */
final class StackBackdropTest extends TestCase
{
    private const OURS = 'truss-f33-2m:5:truss-tower-4m';

    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    public function testTheStatedFormRoundTrips(): void
    {
        $backdrop = StackBackdrop::parse(self::OURS, $this->devices);

        self::assertInstanceOf(StackBackdrop::class, $backdrop);
        self::assertSame(self::OURS, $backdrop->stated());
        self::assertSame(10.0, $backdrop->spanM());
    }

    public function testAnythingButATrussOnTowersIsRefused(): void
    {
        self::assertIsString(StackBackdrop::parse('truss-tower-4m:5:truss-tower-4m', $this->devices));
        self::assertIsString(StackBackdrop::parse('truss-f33-2m:5:truss-f33-2m', $this->devices));
        self::assertIsString(StackBackdrop::parse('truss-f33-2m:0:truss-tower-4m', $this->devices));
        self::assertIsString(StackBackdrop::parse('truss-f33-2m:5', $this->devices));
    }

    /** The 4 m ceiling leaves 3.742 m under a 0.258 m truss, and with no ceiling the towers stand at full height. */
    public function testTheTrussTopsOutAtTheCeiling(): void
    {
        $backdrop = $this->ours();

        self::assertEqualsWithDelta(3.742, $backdrop->flyHeightM(4.0), 1e-9);
        self::assertSame(4.0, $backdrop->flyHeightM(null));
        self::assertSame(4.0, $backdrop->flyHeightM(13.0));
    }

    /** Under the 4 m ceiling the truss already touches, the panel's top stays at the truss's top. */
    public function testTheCeilingHoldsThePanelAtTheTrussTop(): void
    {
        self::assertEqualsWithDelta(4.0, $this->ours()->panelTopM($this->devices['deco-panel-8x2-03'], 4.0), 1e-9);
    }

    /** A 5 m room leaves the truss at its full 4.258 m, and the panel rises to the ceiling, 0.742 m above it. */
    public function testThePanelRisesToAHigherCeiling(): void
    {
        self::assertEqualsWithDelta(5.0, $this->ours()->panelTopM($this->devices['deco-panel-8x2-03'], 5.0), 1e-9);
    }

    /** With no ceiling half of the 2.026 m panel stands above the truss's 4.258 m, and no more. */
    public function testAtMostHalfThePanelStandsAboveTheTruss(): void
    {
        $panel = $this->devices['deco-panel-8x2-03'];

        self::assertEqualsWithDelta(4.258 + 1.013, $this->ours()->panelTopM($panel, null), 1e-9);
        self::assertEqualsWithDelta(4.258 + 1.013, $this->ours()->panelTopM($panel, 13.0), 1e-9);
    }

    public function testAPanelReachingTheFloorIsRefused(): void
    {
        $tall = $this->deco(['geometry' => ['dimensions_m' => ['width' => 10.0, 'height' => 4.5, 'depth' => 0.05]]]);

        self::assertStringContainsString('top at 4.000 m would reach 0.500 m below the floor', (string) $this->ours()->problem($tall, 4.0));
    }

    /** 46.5 kg of truss and the 24.3 kg panel estimate are 35.4 kg a tower, well under half the 85 kg rating. */
    public function testThePanelIsWellInsideTheTowerRating(): void
    {
        $backdrop = $this->ours();
        $panel = $this->devices['deco-panel-8x2-03'];

        self::assertEqualsWithDelta(35.4, $backdrop->towerLoadKg($panel), 1e-9);
        self::assertNull($backdrop->problem($panel, 4.0));
    }

    public function testAPanelOverTheTowerRatingIsRefused(): void
    {
        $heavy = $this->deco(['physical' => ['weight_kg' => 124.0]]);

        self::assertStringContainsString('over its 85.0 kg rating', (string) $this->ours()->problem($heavy, 4.0));
    }

    public function testAPanelWiderThanTheTrussIsRefused(): void
    {
        $wide = $this->deco(['geometry' => ['dimensions_m' => ['width' => 10.5, 'height' => 3.03, 'depth' => 0.05]]]);

        self::assertStringContainsString('spans 10.000 m', (string) $this->ours()->problem($wide, 4.0));
    }

    /** A 2.4 m room would need the truss at 2.142 m, and our stands crank no lower than 2.225 m. */
    public function testACeilingBelowTheCollapsedStandIsRefused(): void
    {
        self::assertStringContainsString(
            'below the 2.225 m it cranks down to',
            (string) $this->ours()->problem($this->devices['deco-panel-8x2-03'], 2.4),
        );
    }

    /** The clearance behind the rig is half the spread the tower spec draws its legs to. */
    public function testTheClearanceIsHalfTheTowersSpread(): void
    {
        $mast = $this->devices['truss-tower-4m']->mast;

        self::assertNotNull($mast);
        self::assertEqualsWithDelta($mast->baseSpread / 2, StackBackdrop::OUTRIGGER_CLEARANCE_M, 1e-9);
    }

    public function testMoreSegmentsThanWeOwnAreRefused(): void
    {
        $six = StackBackdrop::parse('truss-f33-2m:6:truss-tower-4m', $this->devices);

        self::assertInstanceOf(StackBackdrop::class, $six);
        self::assertStringContainsString('5 exist', (string) $six->problem($this->devices['deco-panel-8x2-03'], 4.0));
    }

    /**
     * **The whole backdrop compiled**: the towers cranked to the truss's underside, the truss's top and the panel's
     * top at the ceiling, the panel flush on the truss front, and nothing inside anything or hanging in the air.
     */
    public function testTheWrittenBackdropCompilesUnderTheCeiling(): void
    {
        $panel = $this->devices['deco-panel-8x2-03'];
        $lines = $this->ours()->yaml($panel, -0.3, 0.5, 4.0);
        $scene = SceneSpec::fromArray(
            Yaml::parse("id: backdrop\nname: Backdrop\nplacements:\n".implode("\n", $lines)),
            'test',
        );

        $result = (new SceneCompiler($this->devices))->compile($scene);

        self::assertSame([], $result['violations']);
        $byId = [];
        foreach ($result['placed'] as $entry) {
            $byId[$entry->placementId] = $entry;
        }
        self::assertCount(8, $byId);
        self::assertEqualsWithDelta(3.742, $byId['backdrop-tower-left']->topZ(), 1e-6);
        self::assertEqualsWithDelta(3.742, $byId['backdrop-tower-right']->topZ(), 1e-6);
        self::assertEqualsWithDelta(3.742 / 4.0, $byId['backdrop-tower-left']->scaleZ, 1e-9);
        self::assertEqualsWithDelta(4.0, $byId['backdrop-truss-1']->topZ(), 1e-6);
        self::assertEqualsWithDelta(4.0, $byId['backdrop-deco']->topZ(), 1e-6);
        // 2.026 m of panel under a 4 m ceiling leaves 1.974 m below it.
        self::assertEqualsWithDelta(1.974, $byId['backdrop-deco']->worldBox()['min'][2], 1e-6);

        $truss = $this->extentOf(array_filter($result['placed'], static fn (PlacedDevice $p): bool => str_starts_with($p->placementId, 'backdrop-truss')));
        self::assertEqualsWithDelta(-5.3, $truss['min'][0], 1e-6);
        self::assertEqualsWithDelta(4.7, $truss['max'][0], 1e-6);
        // Flush on the front face, and the front face 0.605 m behind the rig: 0.8 m to the towers, less half the
        // truss and the whole panel.
        self::assertEqualsWithDelta($truss['min'][1], $byId['backdrop-deco']->worldBox()['max'][1], 1e-6);
        self::assertEqualsWithDelta(0.5 + 0.8 - 0.145 - 0.05, $byId['backdrop-deco']->worldBox()['min'][1], 1e-6);

        self::assertSame([], PlacementChecks::floatingFaults($result['placed']));
        self::assertSame([], Interpenetration::faults($result['placed'], PlacementChecks::CONTACT_TOLERANCE_M));
    }

    /** Raised above the truss, the panel still hangs in contact with it, so nothing reads it as floating. */
    public function testAPanelAboveTheTrussCompiles(): void
    {
        $lines = $this->ours()->yaml($this->devices['deco-panel-8x2-03'], 0.0, 0.5, 5.0);
        $scene = SceneSpec::fromArray(
            Yaml::parse("id: backdrop\nname: Backdrop\nplacements:\n".implode("\n", $lines)),
            'test',
        );

        $result = (new SceneCompiler($this->devices))->compile($scene);

        self::assertSame([], $result['violations']);
        $byId = [];
        foreach ($result['placed'] as $entry) {
            $byId[$entry->placementId] = $entry;
        }
        self::assertEqualsWithDelta(4.258, $byId['backdrop-truss-1']->topZ(), 1e-6);
        self::assertEqualsWithDelta(5.0, $byId['backdrop-deco']->topZ(), 1e-6);
        self::assertSame([], PlacementChecks::floatingFaults($result['placed']));
        self::assertSame([], Interpenetration::faults($result['placed'], PlacementChecks::CONTACT_TOLERANCE_M));
    }

    /** Without a ceiling the towers stand at full extension and the scene says nothing about cranking them. */
    public function testFullExtensionWritesNoCrank(): void
    {
        $lines = implode("\n", $this->ours()->yaml($this->devices['deco-panel-8x2-03'], 0.0, 0.5, null));

        self::assertStringNotContainsString('extend_to_m', $lines);
        self::assertStringContainsString('height_m: 4', $lines);
    }

    private function ours(): StackBackdrop
    {
        $backdrop = StackBackdrop::parse(self::OURS, $this->devices);
        self::assertInstanceOf(StackBackdrop::class, $backdrop);

        return $backdrop;
    }

    /** @param array<string, mixed> $overrides */
    private function deco(array $overrides): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'panel',
            'category' => 'other',
            'subtype' => 'deco',
            'build' => 'original',
            'clone_of' => null,
            'audio' => null,
            'geometry' => ['dimensions_m' => ['width' => 10.0, 'height' => 3.03, 'depth' => 0.05]],
            'physical' => ['weight_kg' => 45.45],
            ...$overrides,
        ]);
    }

    /**
     * @param array<PlacedDevice> $placed
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    private function extentOf(array $placed): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            for ($axis = 0; $axis < 3; ++$axis) {
                $min[$axis] = min($min[$axis], $box['min'][$axis]);
                $max[$axis] = max($max[$axis], $box['max'][$axis]);
            }
        }

        /** @var array{float, float, float} $min */
        /** @var array{float, float, float} $max */
        return ['min' => $min, 'max' => $max];
    }
}
