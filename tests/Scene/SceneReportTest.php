<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Scene\SceneReport;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class SceneReportTest extends TestCase
{
    public function testTotalsCountEveryPlacedCabinet(): void
    {
        $sub = $this->device('sub', quantity: 4, weight: 80.0);
        $placed = [
            $this->at($sub, [0.0, 0.0, 0.0]),
            $this->at($sub, [0.7, 0.0, 0.0]),
            $this->at($sub, [0.0, 0.0, 0.6]),
        ];

        $summary = (new SceneReport())->summarise($placed);

        self::assertSame(3, $summary['cabinets']);
        self::assertSame(240.0, $summary['total_weight_kg']);
        self::assertSame(['sub' => ['count' => 3, 'weight_kg' => 240.0]], $summary['by_device']);
        self::assertSame(1.2, $summary['tallest_stack_m'], '0.6 m cabinet on a 0.6 m cabinet');
    }

    public function testFootprintUsesCabinetExtentNotJustCentres(): void
    {
        $sub = $this->device('sub', quantity: 4, weight: 80.0);
        $placed = [$this->at($sub, [0.0, 0.0, 0.0]), $this->at($sub, [1.0, 0.0, 0.0])];

        $summary = (new SceneReport())->summarise($placed);

        // Centres 1.0 apart, each 0.6 wide → 1.6 m of floor, not 1.0.
        self::assertEqualsWithDelta(1.6, $summary['footprint_m'][0], 1e-9);
        self::assertEqualsWithDelta(1.0, $summary['footprint_m'][1], 1e-9);
    }

    public function testFootprintUsesTheRolledExtent(): void
    {
        $device = SpecFactory::spec([
            'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 1.2, 'depth' => 1.0]],
        ]);
        $upright = new PlacedDevice('a', $device, [0.0, 0.0, 0.0], new Orientation());
        $sideways = new PlacedDevice('a', $device, [0.0, 0.0, 0.0], new Orientation(0.0, 90.0));

        $report = new SceneReport();

        self::assertEqualsWithDelta(0.6, $report->summarise([$upright])['footprint_m'][0], 1e-9);
        self::assertEqualsWithDelta(1.2, $report->summarise([$sideways])['footprint_m'][0], 1e-9);
    }

    public function testFlagsUsingMoreCabinetsThanWeOwn(): void
    {
        // Cheap to catch here, expensive to discover on site.
        $sub = $this->device('sub', quantity: 2, weight: 80.0);
        $placed = [
            $this->at($sub, [0.0, 0.0, 0.0]),
            $this->at($sub, [0.7, 0.0, 0.0]),
            $this->at($sub, [1.4, 0.0, 0.0]),
        ];

        $summary = (new SceneReport())->summarise($placed);

        self::assertSame(['sub' => ['used' => 3, 'owned' => 2]], $summary['over_inventory']);
    }

    public function testSplitsWeightByOwnerSoBorrowedGearIsVisible(): void
    {
        $ours = $this->device('sub', quantity: 4, weight: 80.0);
        $theirs = $this->device('borrowed', quantity: 4, weight: 50.0, owner: 'sepp');
        $placed = [$this->at($ours, [0.0, 0.0, 0.0]), $this->at($theirs, [1.0, 0.0, 0.0])];

        $summary = (new SceneReport())->summarise($placed);

        self::assertSame(
            ['sdwa5' => ['count' => 1, 'weight_kg' => 80.0], 'sepp' => ['count' => 1, 'weight_kg' => 50.0]],
            $summary['by_owner'],
        );
    }

    public function testListsUnmeasuredDevicesOnceEach(): void
    {
        $estimated = $this->device('sub', quantity: 4, weight: 80.0, provenance: 'estimated');
        $placed = [$this->at($estimated, [0.0, 0.0, 0.0]), $this->at($estimated, [1.0, 0.0, 0.0])];

        $summary = (new SceneReport())->summarise($placed);

        self::assertSame(['sub'], $summary['unmeasured_devices']);
    }

    public function testEmptySceneReportsZeroesRatherThanInfinity(): void
    {
        $summary = (new SceneReport())->summarise([]);

        self::assertSame(0, $summary['cabinets']);
        self::assertSame([0.0, 0.0], $summary['footprint_m']);
    }

    private function device(
        string $id,
        int $quantity,
        float $weight,
        string $owner = 'sdwa5',
        string $provenance = 'measured',
    ): DeviceSpec {
        return SpecFactory::spec([
            'id' => $id,
            'subtype' => 'sub',
            'quantity' => $quantity,
            'owner' => $owner,
            'provenance' => $provenance,
            'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 0.6, 'depth' => 1.0]],
            'physical' => ['weight_kg' => $weight],
        ]);
    }

    /**
     * @param array{float, float, float} $position
     */
    private function at(DeviceSpec $device, array $position): PlacedDevice
    {
        return new PlacedDevice($device->id.'-'.$position[0].'-'.$position[2], $device, $position, new Orientation());
    }

    /**
     * What a truss actually has to carry. Grouped by the hang rather than by the placement, so two hangs off
     * one bar add up — which is the number somebody checks against a capacity.
     */
    public function testWeightIsGroupedByFlyPointAndSharedBarsAddUp(): void
    {
        $top = SpecFactory::spec(['id' => 'top', 'physical' => ['weight_kg' => 68.0]]);

        $summary = (new SceneReport())->summarise([
            new PlacedDevice('left-1', $top, [-3.0, 0.0, 6.0], new Orientation(), false, null, 'main-bar'),
            new PlacedDevice('left-2', $top, [-3.0, 0.0, 5.0], new Orientation(), false, null, 'main-bar'),
            new PlacedDevice('right-1', $top, [3.0, 0.0, 6.0], new Orientation(), false, null, 'main-bar'),
            new PlacedDevice('spare', $top, [0.0, 0.0, 4.0], new Orientation(), false, null, 'side-bar'),
        ]);

        self::assertSame([
            'main-bar' => ['count' => 3, 'weight_kg' => 204.0],
            'side-bar' => ['count' => 1, 'weight_kg' => 68.0],
        ], $summary['by_fly_point']);

        // The line is laid out like the `owner` lines it sits under, so the two columns line up.
        $lines = (new SceneReport())->lines([
            new PlacedDevice('left-1', $top, [-3.0, 0.0, 6.0], new Orientation(), false, null, 'main-bar'),
            new PlacedDevice('left-2', $top, [-3.0, 0.0, 5.0], new Orientation(), false, null, 'main-bar'),
            new PlacedDevice('right-1', $top, [3.0, 0.0, 6.0], new Orientation(), false, null, 'main-bar'),
        ]);
        $points = array_values(array_filter($lines, static fn (string $l): bool => str_contains($l, 'point ')));
        self::assertCount(1, $points);
        self::assertSame(
            sprintf('  point %-20s %2d cabinets, %.1f kg', 'main-bar', 3, 204.0),
            $points[0],
        );
    }

    /**
     * Nothing flown means the grouping is absent rather than empty-but-printed, so every ground-stacked
     * scene's report reads exactly as it always did.
     */
    public function testAGroundOnlySceneReportsNoFlyPointsAndPrintsNoPointLines(): void
    {
        $sub = SpecFactory::spec(['id' => 'sub', 'physical' => ['weight_kg' => 80.0]]);
        $placed = [new PlacedDevice('a', $sub, [0.0, 0.0, 0.0], new Orientation())];

        self::assertSame([], (new SceneReport())->summarise($placed)['by_fly_point']);
        foreach ((new SceneReport())->lines($placed) as $line) {
            self::assertStringNotContainsString('point ', $line);
        }
    }
}
