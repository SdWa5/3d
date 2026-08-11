<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\StackSolver;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
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
     * The row count is narrowed until the tops are up, because narrower rows mean more of them.
     *
     * Twelve Flexys six-wide are two tiers at 1.526 m and miss a 2 m interface; four-wide they are three
     * tiers at 2.289 m and clear it. Nothing else changes — same cabinets, same stage, more height bought
     * with width.
     */
    public function testTheRowIsNarrowedUntilTheTopsAreUp(): void
    {
        $tiers = $this->solve(['flexy-folded-horn-hybrid', 'tecnare-m2122'], maxWidthM: 3.70, interfaceHeightM: 2.0);

        self::assertSame([4, 4, 4, 3], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        self::assertEqualsWithDelta(2.289, $this->subHeight($tiers), 1e-9);

        // Add the Achenbachs and six-wide Flexy rows clear it instead, at 2.126 m — the hand-built answer.
        $withAchenbach = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );
        self::assertEqualsWithDelta(2.126, $this->subHeight($withAchenbach), 1e-9);
    }

    /**
     * Everything but the SKRAM: three sub tiers of matching cabinets, then **one** row of tops.
     *
     * Tops share a row rather than stacking, and the widest goes in the middle — the M2122 cluster with a
     * 2-way outboard of it each side, which is how the hand-built rigs arrange them too.
     */
    public function testTheWholeInventoryExceptTheSkramStacks(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(21, $this->cabinets($tiers), '12 Flexy, 4 Achenbach, 3 Tecnare, 2 18sound');
        self::assertSame([6, 6, 4, 5], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        self::assertSame(
            '1× eighteensound-2way-15 + 3× tecnare-m2122 + 1× eighteensound-2way-15',
            $tiers[3]->label(),
        );
    }

    /**
     * The whole inventory **cannot** be one stack, and the solver has to say so rather than build a rig that
     * falls over.
     *
     * Only two SKRAMs exist, so a row of them is 1.240 m. They cannot be mixed into a wider row either,
     * because a SKRAM is 0.914 m tall against a Flexy's 0.763 and a row with a step through it has two top
     * faces — the tier above would rest on the tall pair and hang in the air over the rest. Left as their own
     * row, the 2.511 m row of tops stands on 1.240 m and more than half of each outboard cabinet is off the
     * edge. Refused, with the fix named: they belong beside the rig, not in it.
     */
    public function testTheWholeInventoryCannotBeOneStackBecauseOfTheSkrams(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        $problems = implode("\n", $result['problems']);
        self::assertStringContainsString('overhangs 610 mm each side', $problems);
        self::assertStringContainsString('stands on nothing', $problems);
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
     * With the SKRAMs left out, the widest sub is the **Achenbach** — 0.600 m against the Flexy's 0.591 — with four
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
     * Cabinets of different heights are never mixed into one row, however badly a mix would help the widths.
     *
     * This is the fix for the worst bug the feature had: SKRAMs mixed among Flexys left the row above resting
     * on the tall pair and floating 151 mm over the short ones. A row has to have one top face.
     *
     * Our five cabinets have five different heights, so nothing in the current inventory can be mixed at all —
     * which is why the SKRAMs end up beside the rig rather than in it.
     */
    public function testCabinetsOfDifferentHeightsAreNeverMixedIntoOneRow(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        foreach ($result['tiers'] as $tier) {
            self::assertFalse($tier->isMixed(), 'no two of our cabinets share a height');
        }
        self::assertStringNotContainsString('stepped by', implode("\n", $result['warnings']));
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
     * And the honest small case: the tops row is 2.511 m on a 2.460 m Achenbach row, so it stands 26 mm proud
     * at each end. Absorbed by the working gaps in practice, well under half a cabinet, and still not silent —
     * a render makes an overhanging tier look deliberate.
     */
    public function testASmallOverhangIsWarnedAboutRatherThanRefused(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        self::assertStringContainsString('overhangs 26 mm each side', implode("\n", $result['warnings']));
    }

    /**
     * A wide stage must not cost the rig its height.
     *
     * With no width bound to stop it, the fill used to put every Flexy into one row and leave two sub tiers
     * at 1.514 m, so a 2 m interface was unreachable however the tops were arranged. The row count is now
     * chosen as the widest that still clears the interface.
     */
    public function testAWideStageStillReachesTheInterface(): void
    {
        foreach ([10.0, null] as $maxWidthM) {
            $tiers = $this->solve(
                ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
                maxWidthM: $maxWidthM,
                interfaceHeightM: 2.0,
            );

            self::assertGreaterThanOrEqual(2.0, $this->subHeight($tiers));
        }
    }

    /**
     * A height nothing we own can reach is refused, naming how far it got.
     *
     * 12 m rather than a rounder number on purpose: every Flexy in a one-wide column is 9.156 m and the
     * Achenbachs add 2.4, so 11.556 m is the ceiling of this inventory however the rows are cut. Anything
     * under that the solver can reach by narrowing, which is what it should do.
     */
    public function testAStackThatCannotReachTheInterfaceHeightSaysHowFarItGot(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 12.0, gapM: 0.02),
        );

        $problems = implode("\n", $result['problems']);
        self::assertStringContainsString('stack.interface_height_m (12.000)', $problems);
        self::assertStringContainsString('11.556 m high', $problems);
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
