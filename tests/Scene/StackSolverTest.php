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

        self::assertSame([6, 6], array_map(static fn (Tier $t): int => $t->count, $tiers));
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
        self::assertSame([6, 6, 4, 3, 2], array_map(static fn (Tier $t): int => $t->count, $tiers));
    }

    /**
     * The second sweep, and the one that breaks a grid assumption: the SKRAM is the widest cabinet we own
     * at 0.610 m and the second tallest at 0.914 m, so it is what a tier boundary has to bend around. Five
     * cabinets, five widths, five heights — every row count here is a different number.
     */
    public function testTheWholeInventoryIncludingTheSkramStacks(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(23, $this->cabinets($tiers), 'the whole inventory');
        // Six Flexys fit, but only five SKRAMs would — and only two exist, so that row is two wide.
        self::assertSame([6, 6, 2, 4, 3, 2], array_map(static fn (Tier $t): int => $t->count, $tiers));
        // Nothing here is a multiple of anything: 3.646, 1.240 and 2.460 m of sub row.
        self::assertEqualsWithDelta(3.646, $tiers[0]->widthM(0.02), 1e-9);
        self::assertEqualsWithDelta(1.240, $tiers[2]->widthM(0.02), 1e-9);
        self::assertEqualsWithDelta(2.460, $tiers[3]->widthM(0.02), 1e-9);
    }

    /**
     * With no width bound the row count is whatever still gets the tops up: narrower rows mean more of
     * them, so the sub stack grows as the count falls, and the answer is the widest count that still
     * clears the interface.
     */
    public function testWithNoWidthBoundTheRowIsTheWidestThatStillReachesTheInterface(): void
    {
        $tiers = $this->solve(['flexy-folded-horn-hybrid', 'tecnare-m2122'], maxWidthM: null, interfaceHeightM: 2.0);

        // Twelve Flexys: rows of 4 give three tiers at 2.289 m and clear; rows of 5 give three tiers too
        // (ceil(12/5) = 3), and rows of 6 give only two at 1.526 m and miss.
        self::assertSame([5, 5, 2, 3], array_map(static fn (Tier $t): int => $t->count, $tiers));
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
        return array_sum(array_map(static fn (Tier $t): int => $t->count, $tiers));
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
