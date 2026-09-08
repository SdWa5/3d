<?php

declare(strict_types=1);

namespace App\Tests\Load;

use App\Load\LoadPlan;
use App\Load\PackLayout;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Turning an assignment into positions, which is a different problem from the assignment.
 *
 * **What is worth pinning is the rule's honesty, not its quality.** The rule is deliberately weak — no rotation, no
 * interleaving, bounding boxes only — so a test asserting it packs *well* would be asserting the wrong thing. What
 * must hold is that it never puts two things in one place, never silently drops a unit, and respects the two pieces
 * of real geometry it does know: the bay it has to stay inside and the wheel arches that narrow the floor.
 */
final class PackLayoutTest extends TestCase
{
    /**
     * **Nothing is silently dropped.** Every unit is either placed or named as overflow, which is the arithmetic the
     * picture's honesty rests on: a diagram missing a cabinet looks exactly like a diagram of a smaller load.
     */
    public function testEveryUnitIsEitherPlacedOrNamedAsOverflow(): void
    {
        $plan = new LoadPlan(
            self::van(bay: [1.8, 2.0, 4.0], arches: null),
            [['spec' => self::cargo('box', 0.6, 0.6, 0.8), 'count' => 30]],
            payloadKg: 5000.0,
            bayM3: 14.4,
        );

        ['placed' => $placed, 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        self::assertSame(30, count($placed) + count($overflow));
    }

    /**
     * **Two units never occupy one place.** Asserted on the footprints rather than on the rule's internals, because
     * the rule may change and this may not.
     */
    public function testNoTwoUnitsShareAPlace(): void
    {
        $plan = new LoadPlan(
            self::van(bay: [1.8, 2.0, 4.0], arches: null),
            [['spec' => self::cargo('box', 0.6, 0.6, 0.8), 'count' => 12]],
            payloadKg: 5000.0,
            bayM3: 14.4,
        );

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);

        $seen = [];
        foreach ($placed as $entry) {
            // A stacked unit shares its x and y with the column below it on purpose, and its `on` is what separates
            // them in height — so the key is the footprint *and* what it stands on.
            $key = sprintf('%.4f/%.4f/%s', $entry['at'][0], $entry['at'][1], $entry['on'] ?? 'floor');
            self::assertArrayNotHasKey($key, $seen, 'two units in one place');
            $seen[$key] = true;
        }
    }

    /**
     * **The wheel arches bind the floor**, which is the one piece of real geometry the rule knows. A 1.8 m bay with
     * 1.2 m between the arches has 1.2 m of usable floor width, and a diagram that used the full bay width would
     * promise floor space that is not there.
     */
    public function testTheFloorRowStaysBetweenTheWheelArches(): void
    {
        $plan = new LoadPlan(
            self::van(bay: [1.8, 2.0, 4.0], arches: 1.2),
            [['spec' => self::cargo('box', 0.5, 0.5, 0.5), 'count' => 12]],
            payloadKg: 5000.0,
            bayM3: 14.4,
        );

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);

        foreach ($placed as $entry) {
            if (null !== $entry['on']) {
                continue;
            }
            $half = $entry['device']->dimensions->width / 2.0;
            self::assertLessThanOrEqual(0.6 + 1e-6, abs($entry['at'][0]) + $half, 'a floor unit is outside the arches');
        }
    }

    /**
     * **Nothing is placed outside the bay's depth**, which is the other half of staying inside the van.
     */
    public function testNothingIsPlacedBeyondTheBay(): void
    {
        $vehicle = self::van(bay: [1.8, 2.0, 4.0], arches: null);
        $plan = new LoadPlan($vehicle, [['spec' => self::cargo('box', 0.6, 0.6, 0.8), 'count' => 20]], 5000.0, 14.4);

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);

        $near = $vehicle->dimensions->depth / 2.0 - 4.0;
        foreach ($placed as $entry) {
            $half = $entry['device']->dimensions->depth / 2.0;
            self::assertGreaterThanOrEqual($near - 1e-6, $entry['at'][1] - $half);
            self::assertLessThanOrEqual($near + 4.0 + 1e-6, $entry['at'][1] + $half);
        }
    }

    /**
     * **A stacked unit names what it stands on**, because that is where its height comes from. No z is ever written
     * into a scene — measure a cabinet and every pack corrects itself.
     */
    public function testAStackedUnitNamesItsSupport(): void
    {
        // A shallow bay so the floor runs out quickly and stacking has to happen.
        $plan = new LoadPlan(
            self::van(bay: [1.3, 2.0, 1.3], arches: null),
            [['spec' => self::cargo('box', 0.6, 0.6, 0.6), 'count' => 8]],
            payloadKg: 5000.0,
            bayM3: 3.4,
        );

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);
        $ids = array_column($placed, 'id');
        $stacked = array_values(array_filter($placed, static fn (array $e): bool => null !== $e['on']));

        self::assertNotSame([], $stacked, 'a shallow bay should force stacking');
        foreach ($stacked as $entry) {
            self::assertContains($entry['on'], $ids, 'a support has to be a placement in the same scene');
        }
    }

    /**
     * **Nothing is stacked past the bay roof.** The one hard constraint in the layout: a column that grew through
     * the ceiling would be a picture of a van that cannot be shut.
     */
    public function testNoColumnGrowsThroughTheRoof(): void
    {
        $plan = new LoadPlan(
            self::van(bay: [1.3, 1.0, 1.3], arches: null),
            [['spec' => self::cargo('box', 0.6, 0.6, 0.6), 'count' => 20]],
            payloadKg: 5000.0,
            bayM3: 1.7,
        );

        ['placed' => $placed, 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        // A 1.0 m roof takes one 0.6 m unit per column and no more, so every placement is on the floor.
        foreach ($placed as $entry) {
            self::assertNull($entry['on'], 'a 0.6 m box cannot be stacked twice under a 1.0 m roof');
        }
        self::assertNotSame([], $overflow, 'and the rest have to be reported rather than squeezed in');
    }

    /**
     * An open bed — the trailer — lays everything in one row and stacks nothing, because there is no roof to stack
     * under and no bay to stay inside.
     */
    public function testAnOpenBedLaysEverythingInOneRow(): void
    {
        $plan = new LoadPlan(self::trailer(), [['spec' => self::cargo('crate', 0.8, 1.2, 1.0), 'count' => 2]], 550.0, null);

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);

        foreach ($placed as $entry) {
            self::assertNull($entry['on'], 'nothing is stacked on an open bed');
            self::assertSame(0.0, $entry['at'][0], 'and it runs down the centre line');
        }
    }

    /**
     * @param list<float> $bay width, height, depth
     */
    private static function van(array $bay, ?float $arches): DeviceSpec
    {
        $block = ['permitted_gross_kg' => 3500.0, 'load_bay_m' => [
            'width' => $bay[0], 'height' => $bay[1], 'depth' => $bay[2],
        ]];
        if (null !== $arches) {
            $block['load_bay_m']['width_between_arches'] = $arches;
        }

        return self::vehicle('test-van', 'van', $block, [2.0, 2.5, 5.0]);
    }

    private static function trailer(): DeviceSpec
    {
        return self::vehicle('test-trailer', 'trailer', ['permitted_gross_kg' => 750.0], [1.5, 1.0, 3.0]);
    }

    /**
     * @param array<string, mixed> $block
     * @param list<float> $outer
     */
    private static function vehicle(string $id, string $subtype, array $block, array $outer): DeviceSpec
    {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'category' => 'vehicle',
            'subtype' => $subtype,
            'quantity' => 1,
            'build' => 'original',
            'clone_of' => null,
            'provenance' => 'estimated',
            'audio' => null,
            'geometry' => [
                'shape' => 'load-bay',
                'dimensions_m' => ['width' => $outer[0], 'height' => $outer[1], 'depth' => $outer[2]],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => 2000.0, 'handles' => []],
            'vehicle' => $block,
        ]), '/tmp/'.$id.'.yaml');
    }

    private static function cargo(string $id, float $w, float $h, float $d): DeviceSpec
    {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'quantity' => 1,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => $w, 'height' => $h, 'depth' => $d],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => 50.0, 'handles' => []],
        ]), '/tmp/'.$id.'.yaml');
    }
}
