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
 * **What is worth pinning is the rule's honesty, not its quality.** The rule is deliberately weak — no interleaving,
 * bounding boxes only — so a test asserting it packs *well* would be asserting the wrong thing. What
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
     * **Two units never occupy one place.** Asserted on the boxes the units fill, turned and stacked, rather than on
     * the rule's internals, because the rule may change and this may not.
     */
    public function testNoTwoUnitsShareAPlace(): void
    {
        $plan = new LoadPlan(
            self::van(bay: [1.8, 2.0, 4.0], arches: 1.4),
            [
                ['spec' => self::cargo('truss', 2.0, 0.26, 0.29), 'count' => 3],
                ['spec' => self::cargo('box', 0.6, 0.6, 0.8), 'count' => 12],
                ['spec' => self::cargo('small', 0.4, 0.3, 0.4), 'count' => 10],
            ],
            payloadKg: 5000.0,
            bayM3: 14.4,
        );

        ['placed' => $placed] = (new PackLayout())->forPlan($plan);

        foreach ($placed as $i => $a) {
            foreach (array_slice($placed, $i + 1) as $b) {
                $overlap = true;
                for ($axis = 0; $axis < 3; ++$axis) {
                    if ($a['box']['max'][$axis] <= $b['box']['min'][$axis] + 1e-9 || $b['box']['max'][$axis] <= $a['box']['min'][$axis] + 1e-9) {
                        $overlap = false;
                    }
                }
                self::assertFalse($overlap, sprintf('%s and %s fill the same space', $a['id'], $b['id']));
            }
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
            self::assertGreaterThanOrEqual(-0.6 - 1e-6, $entry['box']['min'][0], 'a floor unit is outside the arches');
            self::assertLessThanOrEqual(0.6 + 1e-6, $entry['box']['max'][0], 'a floor unit is outside the arches');
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
            self::assertGreaterThanOrEqual($near - 1e-6, $entry['box']['min'][1]);
            self::assertLessThanOrEqual($near + 4.0 + 1e-6, $entry['box']['max'][1]);
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
            self::assertEqualsWithDelta(0.0, $entry['box']['min'][0] + $entry['box']['max'][0], 1e-9, 'and it runs down the centre line');
        }
    }

    /** **An open bed lays a unit as low as it goes**, since nothing stacks there. The crate goes onto its side. */
    public function testAnOpenBedLaysAUnitDown(): void
    {
        $plan = new LoadPlan(self::trailer(), [['spec' => self::cargo('crate', 0.8, 1.2, 1.0), 'count' => 1]], 550.0, null);

        ['placed' => [$crate]] = (new PackLayout())->forPlan($plan);

        self::assertEqualsWithDelta(0.8, $crate['box']['max'][2], 1e-9);
    }

    /** **An open bed checks its width**, so a 2 m truss lies along a 1.5 m trailer and a unit too big every way overflows. */
    public function testAnOpenBedTurnsWhatIsTooWideAndRefusesWhatCannotTurn(): void
    {
        $plan = new LoadPlan(self::trailer(), [
            ['spec' => self::cargo('truss', 2.0, 0.26, 0.29), 'count' => 1],
            ['spec' => self::cargo('block', 1.6, 1.6, 1.6), 'count' => 1],
        ], 550.0, null);

        ['placed' => $placed, 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        self::assertCount(1, $placed);
        self::assertLessThanOrEqual(1.5 + 1e-9, $placed[0]['box']['max'][0] - $placed[0]['box']['min'][0]);
        self::assertSame(['block'], array_map(static fn (DeviceSpec $s): string => $s->id, $overflow));
    }

    /**
     * **A 2 m truss across a 1.38 m floor lies along the bay** (LOAD-6). Before turns it was reported as overflow, or
     * placed reaching through the wheel arches.
     */
    public function testATrussLongerThanTheFloorIsWideLiesAlongTheBay(): void
    {
        $plan = new LoadPlan(self::van(bay: [1.765, 2.0, 4.0], arches: 1.38), [['spec' => self::cargo('truss', 2.0, 0.26, 0.29), 'count' => 1]], 5000.0, 14.1);

        ['placed' => [$truss], 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        self::assertSame([], $overflow);
        self::assertSame(90.0, $truss['turn']->yawDeg);
        self::assertEqualsWithDelta(2.0, $truss['box']['max'][1] - $truss['box']['min'][1], 1e-9);
    }

    /** **A unit that fits as it stands is not turned**, so a cabinet stays on its feet. */
    public function testAUnitThatFitsIsNotTurned(): void
    {
        $plan = new LoadPlan(self::van(bay: [1.8, 2.0, 4.0], arches: null), [['spec' => self::cargo('box', 0.6, 0.9, 0.8), 'count' => 4]], 5000.0, 14.4);

        foreach ((new PackLayout())->forPlan($plan)['placed'] as $entry) {
            self::assertSame([0.0, 0.0, 0.0], [$entry['turn']->pitchDeg, $entry['turn']->rollDeg, $entry['turn']->yawDeg]);
        }
    }

    /** **An upright unit is only ever turned about the vertical**, so one taller than the roof overflows. */
    public function testAnUprightUnitIsNeverLaidDown(): void
    {
        $van = self::van(bay: [1.8, 1.0, 4.0], arches: null);
        $layout = new PackLayout();

        $lying = $layout->forPlan(new LoadPlan($van, [['spec' => self::cargo('rack', 0.6, 1.2, 0.7), 'count' => 1]], 5000.0, 7.2));
        $upright = $layout->forPlan(new LoadPlan($van, [['spec' => self::cargo('rack', 0.6, 1.2, 0.7, upright: true), 'count' => 1]], 5000.0, 7.2));

        self::assertCount(1, $lying['placed'], 'without the flag it goes in on its side');
        self::assertSame([], $upright['placed']);
        self::assertCount(1, $upright['overflow']);
    }

    /**
     * **A unit that overflows leaves its row open for the next one.** The first version moved the row cursor before
     * it knew whether the unit fitted, so the small box after the big one started a row of its own or overflowed.
     */
    public function testAUnitThatOverflowsLeavesItsRowOpen(): void
    {
        $plan = new LoadPlan(self::van(bay: [1.3, 0.5, 1.0], arches: null), [
            ['spec' => self::cargo('first', 0.6, 0.4, 0.6), 'count' => 1],
            ['spec' => self::cargo('huge', 1.2, 1.2, 1.2), 'count' => 1],
            ['spec' => self::cargo('next', 0.6, 0.4, 0.6), 'count' => 1],
        ], 5000.0, 0.65);

        ['placed' => $placed, 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        self::assertSame(['huge'], array_map(static fn (DeviceSpec $s): string => $s->id, $overflow));
        self::assertCount(2, $placed);
        self::assertEqualsWithDelta($placed[0]['box']['min'][1], $placed[1]['box']['min'][1], 1e-9, 'the next unit shares the row');
    }

    /** **A unit too wide for the floor in every turn overflows** rather than reaching through the wheel arches. */
    public function testAUnitTooWideInEveryTurnOverflows(): void
    {
        $plan = new LoadPlan(self::van(bay: [1.8, 2.0, 4.0], arches: 1.0), [['spec' => self::cargo('block', 1.2, 1.2, 1.2), 'count' => 1]], 5000.0, 14.4);

        ['placed' => $placed, 'overflow' => $overflow] = (new PackLayout())->forPlan($plan);

        self::assertSame([], $placed);
        self::assertCount(1, $overflow);
    }

    /**
     * **A stacked unit may overhang its column by half the gap on each side**, so an 0.600 m Achenbach stands on an
     * 0.591 m Flexy. Anything wider still needs a wider column.
     */
    public function testAStackedUnitMayOverhangItsColumnByHalfTheGap(): void
    {
        $van = self::van(bay: [0.65, 2.0, 1.0], arches: null);
        $layout = new PackLayout();

        $fits = $layout->forPlan(new LoadPlan($van, [
            ['spec' => self::cargo('flexy', 0.591, 0.763, 0.964), 'count' => 1],
            ['spec' => self::cargo('sub', 0.6, 0.6, 0.6, upright: true), 'count' => 1],
        ], 5000.0, 1.3));
        $tooWide = $layout->forPlan(new LoadPlan($van, [
            ['spec' => self::cargo('flexy', 0.591, 0.763, 0.964), 'count' => 1],
            ['spec' => self::cargo('wide', 0.62, 0.6, 0.62, upright: true), 'count' => 1],
        ], 5000.0, 1.3));

        self::assertSame('test-van-flexy-1', $fits['placed'][1]['on']);
        self::assertCount(1, $tooWide['overflow']);
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

    private static function cargo(string $id, float $w, float $h, float $d, bool $upright = false): DeviceSpec
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
        ] + ($upright ? ['transport' => ['upright' => true]] : [])), '/tmp/'.$id.'.yaml');
    }
}
