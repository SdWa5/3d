<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\LowEndBias;
use App\Scene\LowEndCost;
use App\Scene\Stack;
use App\Scene\StackEntry;
use App\Scene\StackSolver;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Where the lowest-reaching cabinets go — the eighth axis.
 *
 * **The rig this exists for is two SKRAMs one above the other on the centre line**, against both of them side by
 * side on the floor. Until the axis existed only the second was in the search at all: the dealer takes as many of
 * a type as the row budget allows, so the pair went in one row and there was nothing to prefer.
 */
final class LowEndBiasTest extends TestCase
{
    /**
     * **`low` pays a little for height and nothing for distance from the centre line.** A metre of low-end height
     * costs what 20 mm of target miss does, so height still decides between rigs that keep the low end in place.
     */
    public function testLowPaysLittleForHeightAndCentralPaysForDistanceFromTheCentreLine(): void
    {
        self::assertEqualsWithDelta(0.02, LowEndBias::Low->cost(1.0, 1.0), 1e-12);
        self::assertEqualsWithDelta(0.02, LowEndBias::Low->cost(1.0, 9.0), 1e-12, 'centrality is not priced');
        self::assertSame(0.0, LowEndBias::Low->cost(0.0, 9.0));

        // Four times the leader, so a quarter of a metre off the centre line is not something height can buy back.
        self::assertSame(4.0 * 0.5 + 1.0, LowEndBias::Central->cost(1.0, 0.5));
        self::assertGreaterThan(
            LowEndBias::Central->cost(1.0, 0.0),
            LowEndBias::Central->cost(0.0, 0.5),
            'half a metre off centre must cost more than a metre of height',
        );
    }

    /**
     * **`low` keeps today's rig and `central` stacks the SKRAMs.** The measurement the whole axis is for.
     */
    public function testCentralStacksTheLowestTypeAndLowLeavesItSideBySide(): void
    {
        $low = $this->rows(LowEndBias::Low);
        $central = $this->rows(LowEndBias::Central);

        // Both SKRAMs in one row, straddling the centre line.
        self::assertSame(1, self::rowsHolding($low, 'skram'));
        self::assertSame(2, self::countIn($low, 'skram'), 'both in that row');

        // One per row, each on the centre line with flankers either side.
        self::assertSame(2, self::rowsHolding($central, 'skram'), 'the pair was not spread across two rows');
        foreach ($central as $tier) {
            $segments = array_map(static fn (array $s): string => $s[0]->id, $tier->segments);
            if (!in_array('skram', $segments, true)) {
                continue;
            }
            // flank, centre, flank — the centre is the one on the line and the flanks keep the row symmetric.
            self::assertSame(['flexy-folded-horn-hybrid', 'skram', 'flexy-folded-horn-hybrid'], $segments);
        }
    }

    /**
     * **Per cabinet, not per segment.** `Tier::seats()` reports a run at its own centre, so two cabinets side by
     * side used to read as one lump sitting at their midpoint — which scored a pair shoved up a row and off to one
     * side as *more* central than the same pair straddling the middle on the floor.
     */
    public function testCentralityCountsEachCabinetsOwnDistanceFromTheCentre(): void
    {
        $devices = self::devices();
        $stack = self::stackFor(LowEndBias::Central);
        $pair = new \App\Scene\Tier([[$devices['skram'], 2, 0.0]]);

        // Two 0.61 m cabinets centred on the row: each sits 0.315 m out, so the measure is 0.315 and not 0.
        self::assertEqualsWithDelta(0.315, LowEndCost::centrality([$pair], $stack, 'skram'), 1e-9);
    }

    /**
     * **Every row below counts, and an even row is as tall as its cabinets.** The base used to advance by a row's
     * height step, which is zero for any row of one height, so a SKRAM row on two Flexy rows measured as low as one
     * on the floor.
     */
    public function testLownessCountsTheRowsUnderneath(): void
    {
        $devices = self::devices();
        $stack = self::stackFor(LowEndBias::Low);
        $skrams = new \App\Scene\Tier([[$devices['skram'], 6, 0.0]]);
        $flexys = new \App\Scene\Tier([[$devices['flexy-folded-horn-hybrid'], 6, 0.0]]);

        $floor = LowEndCost::lowness([$skrams, $flexys, $flexys], $stack, 'skram');
        $top = LowEndCost::lowness([$flexys, $flexys, $skrams], $stack, 'skram');

        self::assertEqualsWithDelta($skrams->heightM() / 2, $floor, 1e-9);
        self::assertEqualsWithDelta(2 * $flexys->heightM() + $skrams->heightM() / 2, $top, 1e-9);
    }

    /**
     * **A pyramid stands the lowest type on the floor when that row is the wider base.** Six SKRAMs and twelve
     * Flexys reach 2.440 m in either order. Sorted once on `quantity × width` the Flexys went first, and the tie kept
     * the first hit, so the SKRAMs stood on top although their row is 3.760 m against 3.646 m.
     */
    public function testAPyramidStandsTheWiderLowestRowOnTheFloor(): void
    {
        $devices = self::devices();
        $inventory = [
            [$devices['skram'], 6],
            [$devices['flexy-folded-horn-hybrid'], 12],
            [$devices['tecnare-m2122'], 3],
            [$devices['eighteensound-2way-15'], 2],
        ];
        $stack = new Stack(
            from: [
                new StackEntry('skram'),
                new StackEntry('flexy-folded-horn-hybrid'),
                new StackEntry('tecnare-m2122'),
                new StackEntry('eighteensound-2way-15'),
            ],
            interfaceHeightM: 2.4,
            gapM: 0.02,
            maxSubHeightM: 3.0,
            shape: \App\Scene\StackShape::Pyramid,
            slideSlackM: INF,
            targetSubHeightM: 2.44,
        );

        $tiers = StackSolver::solve($inventory, $stack)['tiers'];
        $rows = array_map(static fn (\App\Scene\Tier $tier): string => $tier->label(), array_slice($tiers, 0, 3));

        // The lower Flexy row is turned over so its mouths meet the row above, see MouthPairing.
        self::assertSame(['6× skram', '6× flexy-folded-horn-hybrid rolled 180°', '6× flexy-folded-horn-hybrid'], $rows);
    }

    /** @return list<\App\Scene\Tier> */
    private function rows(LowEndBias $bias): array
    {
        $devices = self::devices();
        $inventory = [
            [$devices['skram'], 2],
            [$devices['flexy-folded-horn-hybrid'], 12],
        ];

        return StackSolver::solve($inventory, self::stackFor($bias))['tiers'];
    }

    private static function stackFor(LowEndBias $bias): Stack
    {
        return new Stack(
            from: [new StackEntry('skram'), new StackEntry('flexy-folded-horn-hybrid')],
            interfaceHeightM: 0.0,
            gapM: 0.02,
            maxSubHeightM: 3.0,
            lowEnd: $bias,
        );
    }

    /** @return array<string, DeviceSpec> */
    private static function devices(): array
    {
        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }

        return $devices;
    }

    /** @param list<\App\Scene\Tier> $tiers */
    private static function rowsHolding(array $tiers, string $id): int
    {
        $rows = 0;
        foreach ($tiers as $tier) {
            foreach ($tier->segments as [$device]) {
                if ($device->id === $id) {
                    ++$rows;
                    continue 2;
                }
            }
        }

        return $rows;
    }

    /** @param list<\App\Scene\Tier> $tiers */
    private static function countIn(array $tiers, string $id): int
    {
        $count = 0;
        foreach ($tiers as $tier) {
            foreach ($tier->segments as [$device, $own]) {
                if ($device->id === $id) {
                    $count += $own;
                }
            }
        }

        return $count;
    }
}
