<?php

declare(strict_types=1);

namespace App\Tests\Load;

use App\Load\LoadPlan;
use App\Load\LoadPlanner;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The pack, and the two things it is not allowed to do.
 *
 * **It may never produce an illegal plan and it may never hide a remainder.** Those are the two failure modes with
 * consequences outside this repository: the first is a fine and a refused insurance claim, and the second is a
 * plan somebody loads a van from at six in the morning and discovers is short at the venue. Everything else here —
 * which van a cabinet lands in, how the bays come out — is a heuristic's opinion and is tested as behaviour rather
 * than as a promise.
 */
final class LoadPlannerTest extends TestCase
{
    /**
     * **No vehicle is ever over its payload, whatever the load.** The hard rule, asserted on a load deliberately
     * far too heavy for the fleet: the planner has to leave gear behind rather than fill a van past its limit.
     */
    public function testAVehicleIsNeverPlannedOverItsPayload(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::van('small-van', permittedGross: 3500.0, inService: 3000.0, bay: [2.0, 2.0, 2.0]),
            self::cargo('anvils', weight: 100.0, quantity: 20, size: [0.3, 0.3, 0.3]),
        ]);

        self::assertCount(1, $plans);
        self::assertFalse($plans[0]->isOverloaded());
        self::assertLessThanOrEqual(500.0, $plans[0]->weightKg());
        self::assertNotSame([], $leftovers, 'the excess has to be reported, never quietly dropped');
    }

    /**
     * Every unit is accounted for exactly once — in a vehicle or in the leftovers, never both and never neither.
     *
     * This is the arithmetic that makes the report trustworthy at all. A planner that loses a cabinet produces a
     * plan that passes every weight check and is wrong.
     */
    public function testEveryUnitIsEitherCarriedOrNamedAsLeftBehind(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::van('van-a', permittedGross: 3500.0, inService: 2500.0, bay: [2.0, 2.0, 4.0]),
            self::van('van-b', permittedGross: 3500.0, inService: 2800.0, bay: [1.8, 1.9, 3.5]),
            self::cargo('tops', weight: 20.0, quantity: 8, size: [0.5, 0.6, 0.4]),
            self::cargo('subs', weight: 90.0, quantity: 6, size: [0.6, 0.7, 0.8]),
            self::cargo('racks', weight: 69.0, quantity: 3, size: [0.6, 1.2, 0.8]),
        ]);

        $carried = array_sum(array_map(static fn (LoadPlan $plan): int => $plan->units(), $plans));
        $left = array_sum(array_map(static fn (array $item): int => $item['count'], $leftovers));

        self::assertSame(8 + 6 + 3, $carried + $left);
    }

    /**
     * **The two-dimensional score, on the case that separates it from scoring weight alone.**
     *
     * Two vans: a roomy one with 1000 kg and 20 m³, and a small one with 600 kg and 4 m³. Two loads: 500 kg in one
     * cubic metre, and 400 kg in fifteen. Both loads fit either van by weight, so weight alone has to guess.
     *
     * Weight alone guesses wrong, and it does so by following its own rule correctly. It places the dense load in
     * the roomy van, because that van has the most room left. It then places the bulky load in the *small* van —
     * because after the first placement the small van has 600 kg of room against the roomy van's 500, so "most room
     * left" now points at the van with a four cubic metre bay. Fifteen cubic metres go into it and it bursts.
     *
     * Scoring the worse of the two fills puts the bulky load in the roomy van instead: 0.9 against 3.75. Both loads
     * travel, neither bay bursts, and the small van goes out empty — which is the right answer and the one weight
     * alone cannot reach.
     *
     * The first version of this test used two vans of equal payload and passed under both scorings, so it proved
     * nothing while looking like it proved the point.
     */
    public function testABulkyLoadGoesToTheBigBayWhereWeightAloneWouldBurstTheSmallOne(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::van('roomy-van', permittedGross: 4000.0, inService: 3000.0, bay: [2.0, 2.0, 5.0]),
            self::van('small-van', permittedGross: 3600.0, inService: 3000.0, bay: [1.0, 2.0, 2.0]),
            self::cargo('dense', weight: 500.0, quantity: 1, size: [1.0, 1.0, 1.0]),
            self::cargo('bulky', weight: 400.0, quantity: 1, size: [1.5, 2.0, 5.0]),
        ]);

        self::assertSame([], $leftovers);
        foreach ($plans as $plan) {
            self::assertFalse(
                $plan->exceedsTheBay(),
                $plan->vehicle->id.' burst its bay when the other van had room for the bulky load',
            );
        }

        $roomy = array_values(array_filter($plans, static fn (LoadPlan $p): bool => $p->vehicle->id === 'roomy-van'));
        self::assertSame(2, $roomy[0]->units(), 'both loads belong in the van that can hold them');
    }

    /**
     * **A pinned device rides on the bin it names, even when that bin is the worst choice by every other rule.**
     *
     * This is the one case where scoring bins by strain gets the answer exactly backwards. Sepp's 465 kg generator
     * against a 550 kg trailer is the most strained bin of the three, so left to the score it went to a *van* and
     * the trailer filled up with speaker cabinets — legal on every weight check and impossible to load, since two
     * people cannot lift it and no van has a ramp.
     */
    public function testAPinnedDeviceRidesOnTheBinItNamesEvenWhenThatBinIsTheWorstChoice(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::van('roomy-van', permittedGross: 4000.0, inService: 3000.0, bay: [2.0, 2.0, 5.0]),
            self::van('tight-trailer', permittedGross: 750.0, inService: 200.0, bay: null),
            self::cargo('generator', weight: 465.0, quantity: 1, size: [0.85, 1.2, 1.7], carriedOn: 'tight-trailer'),
            self::cargo('cabinets', weight: 85.0, quantity: 6, size: [0.6, 0.6, 0.8]),
        ]);

        self::assertSame([], $leftovers);
        $byId = [];
        foreach ($plans as $plan) {
            foreach ($plan->items as ['spec' => $spec]) {
                $byId[$spec->id] = $plan->vehicle->id;
            }
        }

        self::assertSame('tight-trailer', $byId['generator'], 'the pin has to beat the bin score');
        self::assertSame('roomy-van', $byId['cabinets'], 'and everything else still balances');
    }

    /**
     * **A pin that cannot be honoured leaves the device behind rather than quietly unpinning it.** Sending it to a
     * van instead would produce a plan nobody can load while reporting success, which is worse than a remainder.
     */
    public function testAPinnedDeviceThatWillNotFitItsBinIsLeftBehind(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::van('roomy-van', permittedGross: 4000.0, inService: 3000.0, bay: [2.0, 2.0, 5.0]),
            self::van('tiny-trailer', permittedGross: 400.0, inService: 200.0, bay: null),
            self::cargo('generator', weight: 465.0, quantity: 1, size: [0.85, 1.2, 1.7], carriedOn: 'tiny-trailer'),
        ]);

        self::assertCount(1, $leftovers);
        self::assertSame('generator', $leftovers[0]['spec']->id);
        foreach ($plans as $plan) {
            self::assertSame([], $plan->items, 'nothing should have been placed in the van instead');
        }
    }

    /**
     * A device stays together when one vehicle can take all of it, because a matched pair of tops split across two
     * vans is a valid plan and an annoying one.
     */
    public function testADeviceIsSplitOnlyWhenNoVehicleCanTakeTheLot(): void
    {
        ['plans' => $plans] = (new LoadPlanner())->plan([
            self::van('van-a', permittedGross: 3500.0, inService: 2500.0, bay: [2.0, 2.0, 4.0]),
            self::van('van-b', permittedGross: 3500.0, inService: 2500.0, bay: [2.0, 2.0, 4.0]),
            self::cargo('pair-of-tops', weight: 20.0, quantity: 4, size: [0.4, 0.5, 0.4]),
        ]);

        $holding = array_values(array_filter(
            $plans,
            static fn (LoadPlan $plan): bool => $plan->items !== [],
        ));

        self::assertCount(1, $holding, 'four tops that fit in one van were dealt across two');
        self::assertSame(4, $holding[0]->items[0]['count']);
    }

    /**
     * With no vehicle there is no plan, and **every device is a leftover rather than an empty success**.
     */
    public function testWithNoVehicleEverythingIsLeftBehind(): void
    {
        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan([
            self::cargo('tops', weight: 20.0, quantity: 4, size: [0.4, 0.5, 0.4]),
        ]);

        self::assertSame([], $plans);
        self::assertSame(4, $leftovers[0]['count']);
    }

    /**
     * An unmeasured bay gives no space answer, and **the absence of an answer is not a pass**. Reporting it as
     * "does not exceed the bay" would read as checked-and-fine to anybody skimming.
     */
    public function testAnUnmeasuredBayAnswersNeitherWayAboutSpace(): void
    {
        ['plans' => $plans] = (new LoadPlanner())->plan([
            self::van('no-tape-measure', permittedGross: 3500.0, inService: 2500.0, bay: null),
            self::cargo('tops', weight: 20.0, quantity: 4, size: [0.4, 0.5, 0.4]),
        ]);

        self::assertNull($plans[0]->exceedsTheBay());
        self::assertNull($plans[0]->bayFill());
        self::assertGreaterThan(0.0, $plans[0]->weightKg(), 'weight is still answerable without a bay');
    }

    /**
     * **The real fleet does not carry the real load, and this test has now said both things in one day.**
     *
     * Three sources, three answers, and only one of them had been near the vehicle:
     *
     * | source for Sepp's payload | figure | fleet against the load |
     * | --- | --- | --- |
     * | estimated, deliberately cautious | 1200 kg | 14.5 kg short |
     * | his Zulassungsschein, field A10 | 1365 kg | 150.5 kg spare |
     * | **a weighbridge, full tank and driver** | **1000 kg** | **214.5 kg short** |
     *
     * The 750 kg trailer then added 550 kg of capacity and the 465 kg generator with it, a net 85 kg, so the
     * remainder is smaller and still a remainder.
     *
     * The estimate was pessimistic and the document was optimistic, which is not the order anybody expects. A
     * registration document is authoritative about what a vehicle **may** weigh and merely historical about what
     * it does: this van has been fitted out with shelving, a bulkhead and a ply floor since it was approved, and
     * no registration field has ever seen them.
     *
     * Read out of `specs/` rather than from a fixture, because it is a statement about our two vans and our gear,
     * and the day one of those numbers changes this test should be the thing that notices. It has been twice.
     */
    public function testOurOwnFleetCannotCarryOurOwnGearAndSaysSo(): void
    {
        $specs = (new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'];
        // GMSS does not travel in these two vans. Stated by the owner, and it is the whole of why this is close.
        $travelling = array_values(array_filter(
            $specs,
            static fn (DeviceSpec $spec): bool => $spec->owner !== 'gmss',
        ));

        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan($travelling);

        // Three bins now: two vans and the 750 kg trailer Sepp is buying, which the generator is pinned to.
        self::assertCount(3, $plans, 'every transporter should be a bin');
        foreach ($plans as $plan) {
            self::assertFalse($plan->isOverloaded(), $plan->vehicle->id.' was planned over its legal payload');
            // **And neither bay bursts any more.** With the fleet 14.5 kg short, weight forced all twelve Flexys
            // into one van and buried the other; with 150.5 kg of headroom the two-dimensional score finally has
            // room to balance both, and Sepp's van went from 112 % of its bay to 54 %.
            self::assertNotTrue($plan->exceedsTheBay(), $plan->vehicle->id.' is over its bay by bounding box alone');
        }
        self::assertNotSame([], $leftovers, 'the fleet is 214.5 kg short and the plan has to admit it');
    }

    /**
     * @param ?list<float> $bay width, height, depth
     */
    private static function van(string $id, float $permittedGross, float $inService, ?array $bay): DeviceSpec
    {
        $block = ['permitted_gross_kg' => $permittedGross];
        if ($bay !== null) {
            $block['load_bay_m'] = ['width' => $bay[0], 'height' => $bay[1], 'depth' => $bay[2]];
        }

        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'category' => 'vehicle',
            'subtype' => 'van',
            'quantity' => 1,
            'build' => 'original',
            'clone_of' => null,
            'provenance' => 'estimated',
            'audio' => null,
            'geometry' => [
                'shape' => 'box',
                // Comfortably bigger than any bay above, since the validator refuses an inside larger than the
                // outside and this fixture is not what that rule is being tested on.
                'dimensions_m' => ['width' => 2.2, 'height' => 2.9, 'depth' => 6.9],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => $inService, 'handles' => []],
            'vehicle' => $block,
        ]), '/tmp/'.$id.'.yaml');
    }

    /**
     * @param list<float> $size width, height, depth
     */
    private static function cargo(
        string $id,
        float $weight,
        int $quantity,
        array $size,
        ?string $carriedOn = null,
    ): DeviceSpec {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'quantity' => $quantity,
            'carried_on' => $carriedOn,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => $size[0], 'height' => $size[1], 'depth' => $size[2]],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => $weight, 'handles' => []],
        ]), '/tmp/'.$id.'.yaml');
    }
}
