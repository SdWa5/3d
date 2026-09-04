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
     * **`low` costs nothing, and that is the point rather than an omission.** The fill already deals the
     * lowest-reaching type first and it already lands on the floor, so pricing that would re-rank every rig in the
     * repository to express a preference they already satisfy — eleven solver tests said so when it did.
     */
    public function testLowIsFreeAndCentralPaysForDistanceFromTheCentreLine(): void
    {
        self::assertSame(0.0, LowEndBias::Low->cost(1.0, 1.0));
        self::assertSame(0.0, LowEndBias::Low->cost(9.0, 9.0));

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
