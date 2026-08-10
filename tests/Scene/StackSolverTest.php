<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\StackSolver;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Dealing the cabinets into rows, bottom up.
 *
 * Run against the **real gear list** rather than round fixtures, because the thing most likely to go wrong
 * here is an assumption that the cabinets share a module, and they emphatically do not: five widths
 * (0.4656 / 0.500 / 0.591 / 0.600 / 0.610) and five heights (0.600 / 0.763 / 0.836 / 0.914 / 0.960), no two
 * of them multiples of anything. A fill that quietly assumes a grid looks perfect on the Flexys alone.
 */
final class StackSolverTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    /**
     * The answer `full-rig` reached by hand: twelve Flexys in a 3.70 m stage become two rows of six at
     * 3.646 m, because `(3.70 + 0.02) / (0.591 + 0.02)` is 6.09 and six is what fits.
     */
    public function testTwelveFlexysInAThreeSevenMetreStageBecomeTwoRowsOfSix(): void
    {
        $tiers = $this->solve(['flexy-folded-horn-hybrid'], maxWidthM: 3.70, interfaceHeightM: 0.0);

        self::assertSame([6, 6], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        self::assertEqualsWithDelta(3.646, $tiers[0]->widthM(0.02), 1e-9);
    }

    /**
     * The constraint reproducing a stack we already trust. Two Flexy tiers reach 1.526 m and the tops would
     * fire into the crowd; adding the Achenbach row reaches 2.126 m and clears — which is exactly what
     * `full-rig-three-tier` arrived at by hand.
     */
    public function testTwoFlexyTiersMissTheInterfaceHeightAndAnAchenbachRowClearsIt(): void
    {
        $missed = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertNotSame([], $missed['problems']);
        self::assertStringContainsString('the subs stack 1.526 m high', $missed['problems'][0]);

        $cleared = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertEqualsWithDelta(2.126, $this->subHeight($cleared), 1e-9);
    }

    /**
     * The first of the two inventory sweeps: everything but the SKRAM. Two sub widths and two top widths,
     * none of them a multiple of another.
     */
    public function testTheWholeInventoryExceptTheSkramStacks(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(21, $this->cabinets($tiers), '12 Flexy, 4 Achenbach, 3 Tecnare, 2 18sound');
        self::assertSame([6, 6, 4, 3, 2], array_map(static fn (Tier $t): int => $t->count(), $tiers));
    }

    /**
     * The second sweep, and the one that breaks a grid assumption: the SKRAM is the widest cabinet we own
     * at 0.610 m and the second tallest at 0.914 m, so it is what a tier boundary has to bend around.
     *
     * Only two exist, so a row of nothing but SKRAMs is 1.240 m — narrower than the 2.460 m Achenbach row
     * that would stand on it, which is the whole reason mixed rows exist. Mixed into the bottom row instead,
     * the rig is a pyramid and every tier is carried.
     */
    public function testTheWholeInventoryIncludingTheSkramStacks(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(23, $this->cabinets($tiers), 'the whole inventory');
        self::assertSame([6, 4, 4, 4, 3, 2], array_map(static fn (Tier $t): int => $t->count(), $tiers));

        // The bottom row is the mixed one: two SKRAMs in the middle, a pair of Flexys either side.
        self::assertSame('2× flexy-folded-horn-hybrid + 2× skram + 2× flexy-folded-horn-hybrid', $tiers[0]->label());
        self::assertEqualsWithDelta(3.684, $tiers[0]->widthM(0.02), 1e-9);

        // Nothing here is a multiple of anything. `n` cabinets carry `n − 1` gaps, so a four-wide Flexy row
        // is 4 × 0.591 + 3 × 0.02 = 2.424 m — not 2.444, which is the mistake of counting a gap per cabinet.
        self::assertEqualsWithDelta([3.684, 2.424, 2.424, 2.460, 1.540, 0.9512], array_map(
            static fn (Tier $t): float => $t->widthM(0.02),
            $tiers,
        ), 1e-9);
    }

    /**
     * With no width bound the row count is whatever still gets the tops up: narrower rows mean more of
     * them, so the sub stack grows as the count falls, and the answer is the widest count that still
     * clears the interface.
     */
    public function testWithNoWidthBoundTheRowIsTheWidestThatStillReachesTheInterface(): void
    {
        $tiers = $this->solve(['flexy-folded-horn-hybrid', 'tecnare-m2122'], maxWidthM: null, interfaceHeightM: 2.0);

        // Twelve Flexys: rows of six reach only 1.526 m and miss, so it drops to three rows — dealt out
        // evenly as 4 + 4 + 4 rather than greedily as 5 + 5 + 2.
        self::assertSame([4, 4, 4, 3], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        self::assertEqualsWithDelta(2.289, $this->subHeight($tiers), 1e-9);
    }

    /**
     * Balanced, not greedy — and this is a support rule wearing arithmetic's clothes. Eight leftover Flexys
     * at six-per-row used to come out 6 + 2, and the 1.222 m row could not carry the 2.460 m Achenbach row
     * above it. As 4 + 4 at 2.444 m it can.
     */
    public function testLeftoverCabinetsAreBalancedAcrossRowsRatherThanFilledGreedily(): void
    {
        $tiers = $this->solve(['flexy-folded-horn-hybrid'], maxWidthM: 2.50, interfaceHeightM: 0.0);

        // Four fit in 2.50 m, so twelve is three even rows — never a short one left at the top.
        self::assertSame([4, 4, 4], array_map(static fn (Tier $t): int => $t->count(), $tiers));
    }

    /**
     * The gate on mixing, and the reason it is not "mix whenever the widest sub cannot fill a row alone".
     *
     * `full-rig-stacked`'s widest sub is the **Achenbach** — 0.600 m against the Flexy's 0.591 — with four
     * owned against a six-per-row fit. The looser rule would have dragged them into the bottom row and stood
     * them *under* the Flexys, silently restructuring a shipped scene. Its row is 2.460 m and the Tecnare row
     * above it is 1.514 m, so there is no inversion to remove and nothing is mixed.
     */
    public function testTheWidestSubIsLeftAloneWhenItsOwnRowAlreadyCarriesWhatIsAboveIt(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame([6, 6, 4, 3], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        foreach ($tiers as $tier) {
            self::assertFalse($tier->isMixed(), 'nothing here needed mixing');
        }
    }

    /**
     * The 610 mm of Achenbach that used to hang in mid-air. Nothing else in the pipeline notices: `on:` only
     * reads a top face, and the shipped-scene check only catches cabinets *inside* each other.
     */
    public function testATierOverhangingTheOneBelowItIsWarnedAboutWithTheOverhang(): void
    {
        // SKRAMs with only tops above them: the inversion is real but there is no second *sub* to flank
        // them with, so mixing cannot rescue it and the overhang has to be reported instead.
        $result = StackSolver::solve(
            $this->inventory(['skram', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 0.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems'], 'it is buildable, just not sensible');
        // A 1.540 m Tecnare row on a 1.240 m SKRAM row.
        self::assertStringContainsString('overhangs 150 mm each side', implode("\n", $result['warnings']));
    }

    /**
     * A mixed row of unequal cabinets is stepped, and the tier above rests on the tall ones and bridges the
     * short ones. Buildable — a crew shims it — but never silent, because a render makes it look deliberate.
     */
    public function testAMixedRowOfUnequalCabinetsWarnsAboutItsStep(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        // SKRAM 0.914 m against Flexy 0.763 m.
        self::assertStringContainsString('stepped by 151 mm', implode("\n", $result['warnings']));
    }

    /**
     * A tier that is no wider than the one below it has nothing to report, which is what keeps the warning
     * meaningful — a check that fired on every rig would be switched off within a week.
     */
    public function testATierNoWiderThanTheOneBelowItSaysNothing(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 0.0, gapM: 0.02),
        );

        self::assertSame([], $result['warnings'], 'two equal Flexy rows with a narrower top row over them');
    }

    /**
     * And the honest converse: the whole inventory *does* leave 18 mm of Achenbach proud of the Flexy row
     * below it, and that gets said. Small, absorbed by the working gaps in practice, and still not silent —
     * a render makes an overhanging tier look deliberate.
     */
    public function testTheWholeInventoryStillReportsItsEighteenMillimetreOverhang(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        // 2.460 m of Achenbach on a 2.424 m Flexy row.
        self::assertStringContainsString('overhangs 18 mm each side', implode("\n", $result['warnings']));
    }

    /**
     * A mixed bottom row is paid for out of the flanking device's stock, and those are the very cabinets the
     * sub tiers above are made of — so an unbounded row can eat the rig's own height.
     *
     * Grown greedily on a 10 m stage it swallowed all twelve Flexys into one 8.572 m row, left two sub tiers
     * at 1.514 m, and made a 2 m interface unreachable however the tops were arranged. Giving a pair back
     * until the interface clears keeps a third sub tier and reaches 2.277 m.
     */
    public function testAWideStageGivesBackAFlankingPairRatherThanLoseTheInterface(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: 10.0,
            interfaceHeightM: 2.0,
        );

        // Five pairs, not six: two Flexys stay behind to make a third sub tier.
        self::assertSame('5× flexy-folded-horn-hybrid + 2× skram + 5× flexy-folded-horn-hybrid', $tiers[0]->label());
        self::assertEqualsWithDelta(2.277, $this->subHeight($tiers), 1e-9);
    }

    /** Same bug, same fix, with no width bound at all — the case that has nothing to stop the growth. */
    public function testAnUnboundedStageAlsoReachesTheInterface(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: null,
            interfaceHeightM: 2.0,
        );

        self::assertGreaterThanOrEqual(2.0, $this->subHeight($tiers));
    }

    public function testAStackThatCannotReachTheInterfaceHeightSaysHowFarItGot(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 4.0, gapM: 0.02),
        );

        self::assertStringContainsString('stack.interface_height_m (4.000)', $result['problems'][0]);
        self::assertStringContainsString('2.126 m', $result['problems'][0]);
    }

    public function testAStackTallerThanItsCeilingSaysSo(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, maxHeightM: 2.5, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertStringContainsString('stack.max_height_m (2.500)', $result['problems'][0]);
        self::assertStringContainsString('3.086 m tall', $result['problems'][0]);
    }

    public function testATierNarrowerThanTheStatedMinimumSaysSo(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, minWidthM: 5.0, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertStringContainsString('stack.min_width_m (5.000)', $result['problems'][0]);
        self::assertStringContainsString('3.646 m', $result['problems'][0]);
    }

    /**
     * The fill is bottom-up, so a top listed before a sub would put a Tecnare under a Flexy and still pass
     * every height check. Refused outright rather than silently sorted, because the list's order is also
     * the frequency order the author stated.
     */
    public function testATopListedBeforeASubIsRefused(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['tecnare-m2122', 'flexy-folded-horn-hybrid']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertStringContainsString('subs come before tops', $result['problems'][0]);
    }

    /**
     * A stage narrower than one cabinet gets a width failure naming the cabinet, not an empty rig — the
     * row count floors to one rather than to zero for exactly this reason.
     */
    public function testAStageNarrowerThanOneCabinetIsAWidthFailureAndNotAnEmptyRig(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid']),
            new Stack(from: [], maxWidthM: 0.4, interfaceHeightM: 0.0, gapM: 0.02),
        );

        self::assertStringContainsString('stack.max_width_m (0.400)', $result['problems'][0]);
        self::assertStringContainsString('flexy-folded-horn-hybrid', $result['problems'][0]);
    }

    /** A wall of subs alone has no sub/top interface, and demanding one would refuse a good rig. */
    public function testASubWallWithNoTopsNeedsNoInterface(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
    }

    /**
     * @param list<string> $from
     * @return list<Tier>
     */
    private function solve(array $from, ?float $maxWidthM, float $interfaceHeightM): array
    {
        $result = StackSolver::solve(
            $this->inventory($from),
            new Stack(from: $from, maxWidthM: $maxWidthM, interfaceHeightM: $interfaceHeightM, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);

        return $result['tiers'];
    }

    /**
     * @param list<string> $ids
     * @return list<array{DeviceSpec, int}>
     */
    private function inventory(array $ids): array
    {
        return array_map(
            fn (string $id): array => [$this->devices[$id], $this->devices[$id]->quantity],
            $ids,
        );
    }

    /** @param list<Tier> $tiers */
    private function cabinets(array $tiers): int
    {
        return array_sum(array_map(static fn (Tier $t): int => $t->count(), $tiers));
    }

    /** @param list<Tier> $tiers */
    private function subHeight(array $tiers): float
    {
        $height = 0.0;
        foreach ($tiers as $tier) {
            if ($tier->isSub()) {
                $height += $tier->heightM();
            }
        }

        return $height;
    }
}
