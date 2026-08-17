<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\Gravity;
use App\Scene\StackChecks;
use App\Scene\StackEntry;
use App\Scene\StackShape;
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
    /** Every speaker in the library, in the order the command deals them. */
    private const EVERY_SPEAKER = [
        'gmss-wall-bass', 'gmss-mid-bass', 'skram', 'flexy-folded-horn-hybrid', 'gmss-nuke', 'achenbach-18',
        'gmss-iq-sub', 'tecnare-m2122', 'gmss-turbo-top', 'eighteensound-2way-15',
    ];

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

        self::assertSame(23, $this->cabinets($tiers), '12 Flexy, 6 Achenbach, 3 Tecnare, 2 18sound');
        self::assertSame([6, 6, 6, 5], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        self::assertSame(
            '1× eighteensound-2way-15 + 3× tecnare-m2122 + 1× eighteensound-2way-15',
            $tiers[3]->label(),
        );
    }

    /**
     * The whole inventory **is** one stack, and the two SKRAMs go in the middle of the bottom row.
     *
     * This is the arrangement the feature was built for. It was briefly impossible, because mixing cabinets of
     * different heights was banned after a mixed row left six Flexys floating — but the floating was caused by
     * resting the whole row above at the taller cabinet's height, not by the mixing. Gravity fixed it: each
     * cabinet lands on whatever is under *it* ({@see \App\Scene\Stack::runsFor}), so the row above a mixed row
     * simply has an uneven top.
     *
     * Achenbach is pinned to four here rather than read off the spec: six of them are already 3.70 m wide —
     * the whole stage — so there is no width left for the SKRAMs anywhere in a bounded stack, and the real
     * inventory genuinely does not fit this way any more ({@see \App\Tests\Command\SceneStackCommandTest}
     * pins that `scene:stack` now leaves the SKRAMs out instead). What this test is about — a mixed bottom
     * row surviving under an uneven top — still needs a case where the rest of the rig fits above it.
     */
    public function testTheWholeInventoryIsOneStackWithTheSkramsInTheBottomRow(): void
    {
        $result = StackSolver::solve(
            [
                [$this->devices['flexy-folded-horn-hybrid'], 12],
                [$this->devices['skram'], 2],
                [$this->devices['achenbach-18'], 4],
                [$this->devices['tecnare-m2122'], 3],
                [$this->devices['eighteensound-2way-15'], 2],
            ],
            new Stack(
                from: array_map(
                    static fn (string $id): StackEntry => new StackEntry($id),
                    ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
                ),
                maxWidthM: 3.70,
                interfaceHeightM: 2.0,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);
        $tiers = $result['tiers'];
        self::assertSame(23, $this->cabinets($tiers), 'every cabinet in this pinned inventory');
        self::assertSame(
            '2× flexy-folded-horn-hybrid + 2× skram + 2× flexy-folded-horn-hybrid',
            $tiers[0]->label(),
        );
        self::assertEqualsWithDelta(3.684, $tiers[0]->widthM(0.02), 1e-9);
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
     * A row is sized against the tier carrying it, not just against the stage — so the fill stops handing
     * {@see \App\Scene\StackChecks} rows it is about to reject.
     *
     * The case that found it: six Achenbachs are 3.700 m and fit any stage this repository states, while the four
     * Flexys under them are 2.424 m. Sized on the stage alone, an Achenbach on each end of that row has nothing
     * beneath it at all, and the whole arrangement was refused — for rows the fill had generated itself. Sized on
     * the support it becomes rows the Flexys can carry.
     *
     * The bound is {@see \App\Scene\Gravity::MIN_BEARING} rearranged, not a stricter rule of its own: a row may
     * reach two thirds of a cabinet past its support on each side, which is exactly the overhang the checker
     * permits. A test that pinned "never wider than below" would be pinning the wrong rule and would refuse rigs
     * this repository ships — `full-rig-arc`'s Achenbach row stands 27 mm proud of its sub wall on purpose.
     */
    public function testARowIsSizedAgainstTheTierCarryingItRatherThanTheStage(): void
    {
        // A wide stage, so nothing here is a stage-width failure: only the support can decide the row.
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18'],
            maxWidthM: 6.00,
            interfaceHeightM: 0.0,
        );

        self::assertNotSame([], $tiers, 'the arrangement the old fill refused');

        // Every tier keeps within a cabinet's-worth of overhang of the one below it, all the way up.
        $widths = array_map(static fn (Tier $t): float => $t->widthM(0.02), $tiers);
        foreach (array_slice($widths, 1) as $index => $width) {
            self::assertLessThan(
                $widths[$index] * 2,
                $width,
                'a tier must be carried by the one below, not merely narrower than the stage',
            );
        }
    }

    /**
     * A stated mix takes only as many flanking cabinets as fit, and consumes exactly those.
     *
     * Two bugs met in this one row, and both shipped rigs that could not be built:
     *
     * **The flanking stock was placed twice.** `fillWith()` iterated `foreach ($remaining as [$device, $count])`,
     * which destructures a snapshot taken before the first iteration — so a mix that consumed the flanking device
     * zeroed it in `$remaining` and the loop, still holding the stale count, dealt the same cabinets again into rows
     * of their own. Eight GMSS turbo subs became sixteen out of a stock of eight, and only `scene:build`'s over-use
     * warning noticed.
     *
     * **And it took the whole stock rather than what fits.** All eight either side of three middle subs is a 6.065 m
     * row on a 5 m stage, so the mix meant to widen a narrow tier had the bounds check refuse the arrangement
     * instead.
     */
    public function testAStatedMixTakesOnlyTheFlankersThatFitAndConsumesExactlyThose(): void
    {
        $result = StackSolver::solve(
            [[$this->devices['flexy-folded-horn-hybrid'], 2], [$this->devices['achenbach-18'], 6]],
            new Stack(
                from: [
                    new StackEntry('flexy-folded-horn-hybrid', mixWith: ['achenbach-18']),
                    new StackEntry('achenbach-18'),
                ],
                maxWidthM: 2.50,
                interfaceHeightM: 0.0,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems'], 'the row has to fit the stage it is given');

        // Every cabinet placed exactly once: 2 Flexys and 6 Achenbachs, never more.
        $placed = [];
        foreach ($result['tiers'] as $tier) {
            foreach ($tier->seats(0.02) as [$device, $count]) {
                $placed[$device->id] = ($placed[$device->id] ?? 0) + $count;
            }
        }
        self::assertSame(2, $placed['flexy-folded-horn-hybrid'] ?? 0);
        self::assertSame(6, $placed['achenbach-18'] ?? 0, 'the flanking stock is placed once, not twice');

        // The mixed row fits, so the Achenbachs it could not take are still dealt their own rows.
        self::assertGreaterThan(1, count($result['tiers']), 'the leftovers get rows of their own');
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
     * With the SKRAMs left out, the widest sub is the **Achenbach** — 0.600 m against the Flexy's 0.591 — with all
     * six owned filling its row exactly. The looser rule would have dragged them into the bottom row and stood
     * them *under* the Flexys, silently restructuring a shipped scene. Its own row is 3.700 m, a 27 mm overhang
     * on the 3.646 m Flexy rows below it, and the Tecnare row above it is 1.514 m — so there is no inversion to
     * remove and nothing is mixed.
     */
    public function testTheWidestSubIsLeftAloneWhenItsOwnRowAlreadyCarriesWhatIsAboveIt(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame([6, 6, 6, 3], array_map(static fn (Tier $t): int => $t->count(), $tiers));
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
     * Cabinets of different heights **are** mixed, and the row is simply stepped.
     *
     * A SKRAM is 0.914 m against a Flexy's 0.763. Forbidding that combination was the wrong fix for floating
     * subs — it also threw out the one arrangement the mixed row exists for. Nothing warns about the step any
     * more either, because under gravity nothing bridges it.
     */
    public function testCabinetsOfDifferentHeightsAreMixedAndTheRowIsSimplyStepped(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        self::assertTrue($result['tiers'][0]->isMixed(), 'the SKRAMs share the bottom row');
        self::assertEqualsWithDelta(0.151, $result['tiers'][0]->heightStepM(), 1e-9);
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
     * And the honest small case: the Achenbach row is 3.700 m on a 3.646 m Flexy row, so it stands 27 mm proud
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
        self::assertStringContainsString('overhangs 27 mm each side', implode("\n", $result['warnings']));
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
     * A stated `count` over-books deliberately, which a stack could not do at all before.
     *
     * `full-rig-all-tops` asks for eight Achenbachs against the six we own — to see whether the rig would work
     * if two more were borrowed — and a stack had no way to say the same thing. Nothing new reports it:
     * `SceneReport::summarise()` already returns `over_inventory` and `scene:build` already warns on it.
     */
    public function testAStatedCountOverridesWhatTheInventoryHolds(): void
    {
        $achenbach = $this->devices['achenbach-18'];
        self::assertSame(6, $achenbach->quantity, 'six owned, which is what makes eight a claim');

        $result = StackSolver::solve(
            [[$this->devices['flexy-folded-horn-hybrid'], 12], [$achenbach, 8], [$this->devices['tecnare-m2122'], 3]],
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        // Eight no longer fits one row at 3.70 m, so the Achenbachs split into two rows of four.
        self::assertSame([6, 6, 4, 4, 3], array_map(static fn (Tier $t): int => $t->count(), $result['tiers']));
    }

    /**
     * `mix_with` lifts mixing off the bottom row: it fires wherever the device that asked for it sits.
     *
     * On fixtures, because nothing we own can be mixed — five cabinets, five heights. Two same-height subs
     * placed *above* a third are the case the old code could not express: `mixedBottomRow` only ever looked at
     * the bottom.
     */
    public function testATierHigherUpTheStackCanBeMixedToo(): void
    {
        $base = SpecFactory::spec([
            'id' => 'base-sub',
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 0.5, 'depth' => 0.8]],
            'physical' => ['weight_kg' => 90.0],
        ]);
        $wide = SpecFactory::spec([
            'id' => 'wide-sub',
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => 0.8, 'height' => 0.6, 'depth' => 0.8]],
            'physical' => ['weight_kg' => 90.0],
        ]);
        $narrow = SpecFactory::spec([
            'id' => 'narrow-sub',
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 0.6, 'depth' => 0.8],
            ],
            'physical' => ['weight_kg' => 60.0],
        ]);

        $result = StackSolver::solve(
            [[$base, 6], [$wide, 2], [$narrow, 2], [$this->devices['tecnare-m2122'], 1]],
            new Stack(
                from: [
                    new StackEntry('base-sub'),
                    new StackEntry('wide-sub', mixWith: ['narrow-sub']),
                    new StackEntry('narrow-sub'),
                    new StackEntry('tecnare-m2122'),
                ],
                maxWidthM: 3.70,
                interfaceHeightM: 0.0,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);
        // The second tier is the mixed one — not the bottom, which is what this is about.
        self::assertFalse($result['tiers'][0]->isMixed(), 'the bottom row is plain');
        self::assertSame('1× narrow-sub + 2× wide-sub + 1× narrow-sub', $result['tiers'][1]->label());
    }

    /**
     * A `mix_with` naming a device of a different height is honoured, not refused.
     *
     * The row comes out stepped and gravity deals with it. What is still refused is naming a device that is not
     * in the stack at all, which is a typo rather than a geometry question.
     */
    public function testMixingAcrossDifferentHeightsIsHonoured(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122']),
            new Stack(
                from: [
                    new StackEntry('flexy-folded-horn-hybrid', mixWith: ['skram']),
                    new StackEntry('skram'),
                    new StackEntry('achenbach-18'),
                    new StackEntry('tecnare-m2122'),
                ],
                maxWidthM: 3.70,
                interfaceHeightM: 2.0,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);
        self::assertTrue($result['tiers'][0]->isMixed());
    }

    /** Naming a device that is not in this stack at all is refused rather than ignored. */
    public function testMixingWithADeviceOutsideTheStackIsRefused(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'tecnare-m2122']),
            new Stack(
                from: [new StackEntry('flexy-folded-horn-hybrid', mixWith: ['skram']), new StackEntry('tecnare-m2122')],
                maxWidthM: 3.70,
                interfaceHeightM: 2.0,
                gapM: 0.02,
            ),
        );

        self::assertStringContainsString(
            "mix_with names 'skram', which is not in this stack",
            implode("\n", $result['problems']),
        );
    }

    /**
     * The **top** row may be as uneven as it likes, because nothing stands on it to bridge the step.
     *
     * Warning about it was a false positive on every rig we own: the tops row always mixes an 0.960 m M2122
     * with an 0.836 m 2-way, and there is nothing above it.
     */
    public function testAStepInTheTopRowIsNotWarnedAbout(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertStringNotContainsString('stepped by', implode("\n", $result['warnings']));
    }

    /**
     * A device asked to lie on its side is **fitted and stacked by its rolled dimensions**.
     *
     * A Flexy is 591 × 763 upright and 763 × 591 on its side, so four fill a 3.70 m stage where six stand up,
     * and a tier of them raises what is above by 591 mm rather than 763. Every one of those numbers has to come
     * from the rolled box: fitting rolled cabinets by their nominal 591 mm put 3.895 m of cabinet on a 3.70 m
     * stage and reported it as a width failure afterwards.
     */
    public function testARolledDeviceIsFittedByItsRolledWidth(): void
    {
        // Achenbach pinned to the four owned when this scenario's numbers were computed — see
        // testAStatedCountOverridesWhatTheInventoryHolds. Six real Achenbachs are their own row's full
        // 3.70 m width and no longer illustrate anything about rolled Flexys.
        $result = StackSolver::solve(
            [
                [$this->devices['flexy-folded-horn-hybrid'], 12],
                [$this->devices['achenbach-18'], 4],
                [$this->devices['tecnare-m2122'], 3],
                [$this->devices['eighteensound-2way-15'], 2],
            ],
            new Stack(
                from: [
                    new StackEntry('flexy-folded-horn-hybrid', rollMirror: 90.0),
                    new StackEntry('achenbach-18'),
                    new StackEntry('tecnare-m2122'),
                    new StackEntry('eighteensound-2way-15'),
                ],
                maxWidthM: 3.70,
                interfaceHeightM: 2.0,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);

        $tiers = $result['tiers'];
        self::assertSame([4, 4, 4, 4, 5], array_map(static fn (Tier $t): int => $t->count(), $tiers));
        // Four rolled Flexys: 4 × 0.763 + 3 × 0.02 = 3.112, against 3.646 for six standing up.
        self::assertEqualsWithDelta(3.112, $tiers[0]->widthM(0.02), 1e-9);
        // Three rolled tiers reach 3 × 0.591, where three upright ones would be 2.289.
        self::assertEqualsWithDelta(1.773, $this->subHeight(array_slice($tiers, 0, 3)), 1e-9);
    }

    /**
     * And the row is mirrored about its own centre, not about each segment.
     *
     * The half past the middle takes the stated quarter turn and the half before it the mirror image, so the
     * rig is symmetric about its centre line. A mixed row is the case that makes the distinction real: mirroring
     * each segment about itself would turn the two middle cabinets one way each and break the symmetry.
     */
    public function testARolledTierIsMirroredAboutTheRowsOwnCentre(): void
    {
        // Achenbach pinned to four — see testARolledDeviceIsFittedByItsRolledWidth just above.
        $result = StackSolver::solve(
            [
                [$this->devices['flexy-folded-horn-hybrid'], 12],
                [$this->devices['achenbach-18'], 4],
                [$this->devices['tecnare-m2122'], 3],
                [$this->devices['eighteensound-2way-15'], 2],
            ],
            new Stack(
                from: [
                    new StackEntry('flexy-folded-horn-hybrid', rollMirror: 90.0),
                    new StackEntry('achenbach-18'),
                    new StackEntry('tecnare-m2122'),
                    new StackEntry('eighteensound-2way-15'),
                ],
                maxWidthM: 3.70,
                interfaceHeightM: 2.0,
                gapM: 0.02,
            ),
        );

        self::assertSame(
            '2× flexy-folded-horn-hybrid rolled 270° + 2× flexy-folded-horn-hybrid rolled 90°',
            $result['tiers'][0]->label(),
        );
        // The Achenbachs above were not asked to turn, and did not.
        self::assertSame('4× achenbach-18', $result['tiers'][3]->label());
    }

    /**
     * A sub tier narrower than the one below it is **flanked from below** to close the step.
     *
     * The rule that builds the mixed bottom row asks whether a row would be narrower than the row coming to
     * stand *on* it — a support question, which is why it can only ever fire at the bottom. Asking whether a row
     * is narrower than the row it stands *on* is the same mechanism pointed the other way. Four Achenbachs on
     * six Flexys is 2.460 m on 3.646 m: perfectly carried, and a 593 mm shoulder each side. One Flexy either
     * side of them makes it 3.682 m and the wall flat.
     *
     * Achenbach is pinned to four (see {@see self::solvePinned()}): six real ones are the full 3.70 m stage
     * width on their own, so they can no longer be the narrower row a step needs to close.
     */
    public function testASubTierIsFlankedFromBelowToCloseTheStep(): void
    {
        $tiers = $this->solvePinned(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(23, $this->cabinets($tiers), 'the pinned inventory, none spent on the flanks');
        self::assertSame(
            '1× flexy-folded-horn-hybrid + 4× achenbach-18 + 1× flexy-folded-horn-hybrid',
            $tiers[2]->label(),
        );

        $widths = array_map(static fn (Tier $t): float => $t->widthM(0.02), $tiers);
        self::assertEqualsWithDelta([3.684, 3.646, 3.682, 2.5112], $widths, 1e-9);
        // 38 mm between the widest and narrowest sub tier, where it used to be 1.260 m across five tiers.
        self::assertEqualsWithDelta(0.038, max(array_slice($widths, 0, 3)) - min(array_slice($widths, 0, 3)), 1e-9);
    }

    /**
     * And only one pair, because every cabinet the flanks take is one fewer in the row below.
     *
     * The same converging-widths criterion the bottom row's flanks use. A second pair would make the Achenbach
     * row 4.904 m while leaving four Flexys — 2.424 m — underneath it, which is not a flatter wall, it is the
     * same step moved down a tier with the rig now top-heavy.
     */
    public function testTheFlankedTierIsNeverWiderThanWhatCarriesIt(): void
    {
        $tiers = $this->solvePinned(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        // 18 mm each side, which {@see \App\Scene\StackChecks::supportChecks} warns about and does not refuse.
        self::assertGreaterThan($tiers[1]->widthM(0.02), $tiers[2]->widthM(0.02));
        self::assertLessThan(0.02, ($tiers[2]->widthM(0.02) - $tiers[1]->widthM(0.02)) / 2);
    }

    /**
     * A cabinet with **nothing** under it is an error, not a silent drop to the floor.
     *
     * Falling puts a cabinet on the floor when it finds nothing beneath it, which for a tier above the bottom
     * means inside the tier below. It was unreachable — but only because the tier-width rule happens to refuse
     * the shapes that cause it, so the invariant was held by a coincidence of two rules agreeing rather than by
     * itself. Here the row above is wide enough that its outer cabinets miss the base entirely.
     */
    public function testACabinetWithNothingUnderItIsAnError(): void
    {
        $tiers = [
            Tier::of($this->devices['achenbach-18'], 1),
            Tier::of($this->devices['achenbach-18'], 5),
        ];

        $check = new \ReflectionMethod(\App\Scene\StackChecks::class, 'supportChecks');
        $problems = $check->invoke(null, $tiers, new Stack(from: [], maxWidthM: null, interfaceHeightM: 0.0, gapM: 0.02))['problems'];

        self::assertStringContainsString('nothing under it at all', implode("\n", $problems));
        self::assertStringContainsString('would fall to the floor', implode("\n", $problems));
    }

    /**
     * A sub tier narrowed to a single column is refused — a pillar rather than a rig.
     *
     * The interface chase had no floor: narrower rows mean more of them and so a taller stack, so on a pile it
     * cannot otherwise lift, the search kept narrowing until `--per-owner` gave `sdwa5` a rig 1.8 m across and
     * 4.9 m tall. Every other rule passed it — each tier exactly as wide as the one below, nothing overhanging,
     * every cabinet carried — because a column is never more than half a cabinet wider than the column beneath
     * it. Missing the interface is a warning; a tower is not an answer.
     */
    public function testASubTierNarrowedToASingleColumnIsRefused(): void
    {
        // The refusal works by steering the search, so the usual outcome is a wider rig and a warning rather
        // than a message: an unreachable 12 m interface no longer buys a tower of one-wide tiers.
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 12.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        foreach ($result['tiers'] as $tier) {
            if ($tier->isSub()) {
                self::assertGreaterThan(1, $tier->count(), 'no sub tier is a single column');
            }
        }
        self::assertStringContainsString('interface', implode("\n", $result['warnings']));

        // The message itself surfaces only when a column is the *only* thing that fits — here a stage narrower
        // than two Flexys, where every arrangement is a pillar and there is nothing better to fall back to.
        $narrow = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 0.70, interfaceHeightM: 2.0, gapM: 0.02),
        );
        self::assertStringContainsString('is a single column', implode("\n", $narrow['problems']));

        // And with a reachable interface the same inventory is untouched.
        $sane = $this->solve(['flexy-folded-horn-hybrid', 'tecnare-m2122'], maxWidthM: 3.70, interfaceHeightM: 2.0);
        self::assertSame([4, 4, 4, 3], array_map(static fn (Tier $t): int => $t->count(), $sane));
    }

    /**
     * A cabinet only **touching** what carries it is an error — a third of it has to be on there.
     *
     * The case is a 2-way perched on a 163 mm shoulder, catching it by one corner: **1.2 %**. What makes the
     * boundary a third rather than a half is the arrangement it must *not* refuse — a Flexy resting on a SKRAM
     * with the rest cantilevered outward bears **49.9 %**, which crews stack and strap, and a half fell exactly
     * between the two. Forty times apart, and the old rule refused both.
     *
     * Note what this deliberately does **not** ask: whether a lower surface sits under the overhang. A surface
     * below can only catch a cabinet that tilts — it cannot make one less stable than the same cabinet over thin
     * air. An earlier version treated it as a hazard and refused a Flexy row on a mixed Flexy-and-SKRAM bottom
     * row while allowing the identical row on a lone SKRAM, which is backwards.
     */
    public function testACabinetOnlyTouchingItsSupportIsAnError(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $stack = new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 0.0, gapM: 0.02);

        // The boundary itself: above the perch, below the cantilever. Both figures are measured in `GravityTest`
        // — 1.2 % for a 2-way catching a 163 mm shoulder by one corner, 49.9 % for a Flexy on a SKRAM with the
        // rest of it hanging outward.
        self::assertGreaterThan(0.012, Gravity::MIN_BEARING, 'the perch is refused');
        self::assertLessThan(0.499, Gravity::MIN_BEARING, 'the cantilever is not');

        // And the arrangement that matters: Flexys and a SKRAM side by side, carrying a Flexy row.
        $mixed = [
            new Tier([[$flexy, 1], [$this->devices['skram'], 1], [$flexy, 1]]),
            Tier::of($flexy, 2),
        ];
        self::assertSame([], StackChecks::supportChecks($mixed, $stack)['problems']);
    }

    /**
     * And a row whose end cabinets reach past their support **stands**, because a row is one body.
     *
     * Four Flexys on a 1.832 m row overhang 296 mm each side against a half-cabinet of 295.5 — refused by half a
     * millimetre by the old proxy, which turns out to *be* the per-cabinet centre-of-mass rule. The row's own mass
     * is dead centre over its support, and its outer cabinets are held by the neighbours they are strapped to.
     * Nothing under the overhang, so there is no tilt to measure either.
     */
    public function testARowWhoseEndCabinetsReachPastTheirSupportStands(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $tiers = [
            new Tier([[$flexy, 1], [$this->devices['skram'], 1], [$flexy, 1]]),
            Tier::of($flexy, 4),
        ];

        $stack = new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 0.0, gapM: 0.02);
        $checked = StackChecks::supportChecks($tiers, $stack);

        // Still worth saying out loud, so the 296 mm is reported rather than silently accepted.
        self::assertStringContainsString('overhangs 296 mm', implode("\n", $checked['warnings']));
    }

    /**
     * The mixed bottom row grows only until the step is gone — never wider.
     *
     * Mixing exists to remove an inverted step, so every cabinet the flanks take past that point is one stolen
     * from the rows above. With no `max_width_m` to stop it the flanks ate eight of the twelve Flexys and left a
     * **6.128 m bottom row carrying a 2.424 m one** — a pancake with a tower on it. The two widths converge from
     * both ends as the flanks grow, and the crossing point is where the taper stops.
     */
    public function testTheMixedBottomRowGrowsOnlyUntilTheStepIsGone(): void
    {
        $tiers = $this->solvePinned(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: null,
            interfaceHeightM: 2.0,
        );

        self::assertSame(
            '2× flexy-folded-horn-hybrid + 2× skram + 2× flexy-folded-horn-hybrid',
            $tiers[0]->label(),
            'two pairs, not four: past that the flanks eat the rows above',
        );

        // Two pairs rather than three, because the Achenbach row above takes a pair as well and six Flexys
        // in the row between come to 3.646 m — see testASubTierIsFlankedFromBelowToCloseTheStep. Before the
        // two decisions knew about each other this was 3 pairs and 4.906 m, on the assumption that all eight
        // remaining Flexys would stand in one 4.868 m row and overhang anything narrower.
        $widths = array_map(static fn (Tier $t): float => $t->widthM(0.02), $tiers);
        self::assertEqualsWithDelta([3.684, 3.646, 3.682, 2.5112], $widths, 1e-9);
    }

    /**
     * And a stated `max_width_m` usually bites first, so bounding the stage changes nothing about the mix.
     */
    public function testAWidthBoundStillDecidesTheMixWhenItIsTheTighterLimit(): void
    {
        $tiers = $this->solvePinned(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(
            '2× flexy-folded-horn-hybrid + 2× skram + 2× flexy-folded-horn-hybrid',
            $tiers[0]->label(),
        );
        self::assertEqualsWithDelta(3.684, $tiers[0]->widthM(0.02), 1e-9);
    }

    /**
     * The interface height is an **optimum, not a requirement**: a height nothing we own can reach is a
     * warning naming the ceiling, not a refusal.
     *
     * Refusing was wrong, and it made small rigs unbuildable for no good reason — six Achenbachs one-wide
     * reach 3.600 m and two-wide only 1.800 m, and neither is absurd. Tops sitting lower than ideal is a
     * judgement about coverage; a cabinet hanging off its support is not, and that one stays an error.
     *
     * 12 m rather than a rounder number on purpose: every Flexy in a one-wide column is 9.156 m and the
     * Achenbachs add 3.6, so 12.756 m is the ceiling of this inventory however the rows are cut. Anything
     * under that the solver reaches by narrowing, which is what it should do.
     */
    public function testAnUnreachableInterfaceHeightWarnsAndNamesTheCeiling(): void
    {
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']),
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 12.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems'], 'a low rig is buildable, just not ideal');

        $warnings = implode("\n", $result['warnings']);
        // 6.378 m, not the 12.756 m of one-wide columns: narrowing that far would leave the three-wide
        // Tecnare row 160 mm off each edge of a two-wide Achenbach row, and support outranks the interface.
        self::assertStringContainsString('6.378 m against the 12.000 m interface', $warnings);
        self::assertStringContainsString('while every tier is still carried', $warnings);
        self::assertStringContainsString('overhangs 160 mm each side', $warnings);
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
        self::assertStringContainsString('3.700 m', $result['problems'][0]);
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
     * A ceiling packs several device types into one row, and that is the only thing that shortens a stack of
     * many types.
     *
     * Two SKRAMs, twelve Flexys, six Achenbachs and three Tecnares on the 3.70 m stage are six rows and 3.640 m
     * dealt one type per row. Under a ceiling the same cabinets come out five rows and 3.040 m: the Achenbachs
     * share a row with the two Flexys left over, so those two cost no row of their own. 600 mm out of nothing but
     * a different arrangement.
     */
    public function testACeilingPacksSeveralDeviceTypesIntoOneRow(): void
    {
        $ids = ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'];

        $dealt = $this->solveTo($ids, maxWidthM: 3.70, maxSubHeightM: null);
        self::assertCount(6, $dealt['tiers']);
        self::assertEqualsWithDelta(3.640, $this->subHeight($dealt['tiers']), 1e-9);

        $packed = $this->solveTo($ids, maxWidthM: 3.70, maxSubHeightM: 3.0);
        self::assertSame([], $packed['problems']);
        self::assertCount(5, $packed['tiers']);
        self::assertEqualsWithDelta(3.040, $this->subHeight($packed['tiers']), 1e-9);
        self::assertSame(
            '2× achenbach-18 + 2× flexy-folded-horn-hybrid + 2× achenbach-18',
            $packed['tiers'][2]->label(),
        );
        self::assertSame($this->cabinets($dealt['tiers']), $this->cabinets($packed['tiers']));
    }

    /**
     * A packed row never puts a shallower cabinet below a deeper one, however the cuts fall.
     *
     * The property packing could most easily break, and the one thing that makes it safe: a row may only take
     * types that are **adjacent** in the fill order, so the rows are contiguous runs of that order. Asserted as
     * "every device's rows are consecutive" rather than by naming the rows, because that is the invariant — the
     * particular cuts are allowed to change when a cabinet is finally measured.
     */
    public function testAPackedStackKeepsTheDeeperCabinetsInTheLowerRows(): void
    {
        $ids = ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'];
        $tiers = $this->solveTo($ids, maxWidthM: 3.70, maxSubHeightM: 3.0)['tiers'];

        $rows = [];
        foreach ($tiers as $index => $tier) {
            foreach ($tier->segments as [$device]) {
                $rows[$device->id][] = $index;
            }
        }

        $previous = -1;
        foreach ($ids as $id) {
            $own = array_values(array_unique($rows[$id] ?? []));
            self::assertNotSame([], $own, $id.' is not in the stack at all');
            self::assertSame(range(min($own), max($own)), $own, $id.' appears in rows that are not consecutive');
            self::assertGreaterThanOrEqual($previous, min($own), $id.' starts below the type that should be under it');
            $previous = min($own);
        }
    }

    /**
     * A device with one cabinet in the stack stands under a ceiling, where the deal refuses it.
     *
     * One SKRAM and three Achenbachs dealt one type per row leave a lone Achenbach on top, which
     * {@see StackChecks::pillarProblems} refuses as a pillar. Packed, the SKRAM shares the bottom row with an
     * Achenbach and the other two stand on it — same four cabinets, same 1.514 m, and a rig rather than a column.
     */
    public function testASingleCabinetStandsUnderACeilingWhereTheDealRefusesIt(): void
    {
        $dealt = $this->solveTo(
            ['skram', 'achenbach-18'],
            maxWidthM: 3.70,
            maxSubHeightM: null,
            skramCount: 1,
            achenbachCount: 3,
        );
        self::assertStringContainsString('is a single column', $dealt['problems'][0] ?? '');

        $packed = $this->solveTo(
            ['skram', 'achenbach-18'],
            maxWidthM: 3.70,
            maxSubHeightM: 3.0,
            skramCount: 1,
            achenbachCount: 3,
        );
        self::assertSame([], $packed['problems']);
        self::assertSame(['1× skram + 1× achenbach-18', '2× achenbach-18'], array_map(
            static fn (Tier $t): string => $t->label(),
            $packed['tiers'],
        ));
        self::assertEqualsWithDelta(1.514, $this->subHeight($packed['tiers']), 1e-9);
    }

    /**
     * The Achenbachs stand **on** the Flexys, which is what the fill order asks for and the bearing rule permits.
     *
     * Worth pinning because the low-rig scene was written the other way round on a "widest row first" rule that
     * does not exist: six Achenbachs are 3.700 m on six Flexys' 3.646 m and stand 27 mm proud per side, against
     * the two thirds of a cabinet {@see Gravity::MIN_BEARING} allows. Five Flexys and three Achenbachs is the
     * same shape with the numbers further apart.
     *
     * **The order is the invariant; how many rows the Flexys take is not.** Under a ceiling the five Flexys now come
     * out as 3 + 2 rather than one row of five, because `target_sub_height_m` prefers the taller arrangement — same
     * cabinets, same order, 1.526 m instead of 0.763 m and that much nearer the aim. Asserting the exact row list in
     * both cases would pin the solver's arithmetic in a test about which cabinet stands on which.
     *
     * **And that is why it no longer names the top row's cabinet count either.** GEO-12's width budget finds
     * `3× flexy | 2× flexy + 1× achenbach | 2× achenbach` under a ceiling: all eight cabinets, nothing refused, one
     * Achenbach sharing a row with Flexys rather than standing on them. A mixed row of two types **adjacent in the
     * fill order is explicitly allowed** — see `docs/scenes.md` — and it lands nearer the aim, so `2×` on top is a
     * better answer rather than a broken one. The order rule is what survives: **no Flexy anywhere above an
     * Achenbach**, which is the inversion this test exists to catch, asserted directly instead of through a row count
     * that stands in for it.
     */
    public function testTheAchenbachsStandOnTheFlexysRatherThanUnderThem(): void
    {
        foreach ([null, 3.0] as $ceiling) {
            $result = $this->solveTo(
                ['flexy-folded-horn-hybrid', 'achenbach-18'],
                maxWidthM: 3.70,
                maxSubHeightM: $ceiling,
                flexyCount: 5,
                achenbachCount: 3,
            );
            $labels = array_map(static fn (Tier $t): string => $t->label(), $result['tiers']);

            self::assertSame([], $result['problems']);
            self::assertStringContainsString('achenbach-18', $labels[count($labels) - 1], 'the Achenbachs go on top');
            self::assertStringNotContainsString(
                'flexy-folded-horn-hybrid',
                $labels[count($labels) - 1],
                'and nothing else is up there with them',
            );

            // The inversion this test exists to catch, stated as itself: once a row holds an Achenbach, no row above
            // it may hold a Flexy. A row holding both is fine — they are adjacent in the fill order.
            $seenAchenbach = false;
            foreach ($labels as $row) {
                self::assertFalse(
                    $seenAchenbach && str_contains($row, 'flexy-folded-horn-hybrid'),
                    'no Flexy stands above an Achenbach',
                );
                $seenAchenbach = $seenAchenbach || str_contains($row, 'achenbach-18');
            }

            self::assertSame(8, $this->cabinets($result['tiers']), 'all five Flexys and all three Achenbachs');
        }

        // Without a ceiling there is nothing to rank, so the first hit stands and it is the single row of five.
        $free = $this->solveTo(
            ['flexy-folded-horn-hybrid', 'achenbach-18'],
            maxWidthM: 3.70,
            maxSubHeightM: null,
            flexyCount: 5,
            achenbachCount: 3,
        );
        self::assertSame(['5× flexy-folded-horn-hybrid', '3× achenbach-18'], array_map(
            static fn (Tier $t): string => $t->label(),
            $free['tiers'],
        ));
    }

    /**
     * **The target decides which of the legal arrangements comes back**, and moving it moves the rig.
     *
     * The whole of the change: under a ceiling the solver walks every arrangement that stands up and used to keep the
     * *shortest*, which parked the transition as low as the interface allowed. A target says which one is wanted, and
     * the same twelve cabinets answer differently at 2.0 m, 2.5 m and 3.0 m without a single other input changing.
     *
     * Asserted as an ordering rather than three numbers, because the exact heights are the inventory's arithmetic and
     * would have to be re-pinned every time a cabinet is measured. What must hold is that a higher aim never returns a
     * lower rig.
     */
    public function testTheTargetDecidesWhichLegalArrangementComesBack(): void
    {
        // Five Flexys and three Achenbachs, because that inventory has three arrangements that stand up and they are
        // far apart: one row of five, 3 + 2, and five rows of one. An aim cannot move a rig with nowhere to go, and
        // most of this gear has exactly one arrangement under a ceiling.
        $ids = ['flexy-folded-horn-hybrid', 'achenbach-18'];

        $heights = [];
        foreach ([1.0, 2.0, 3.0] as $target) {
            $result = $this->solveTo(
                $ids,
                maxWidthM: 3.70,
                maxSubHeightM: 3.0,
                flexyCount: 5,
                achenbachCount: 3,
                targetSubHeightM: $target,
            );
            self::assertSame([], $result['problems'], 'aiming at '.$target.' m must still produce a rig');
            $heights[] = $this->subHeight($result['tiers']);
        }

        self::assertLessThanOrEqual($heights[1], $heights[0], 'aiming lower must not return a taller rig');
        self::assertLessThanOrEqual($heights[2], $heights[1], 'aiming higher must not return a shorter rig');
        self::assertGreaterThan($heights[0], $heights[2], 'and the two ends must differ, or the aim does nothing');

        // Every one of them legal, which is the bound the target is a preference inside of.
        foreach ($heights as $height) {
            self::assertLessThanOrEqual(3.0 + 1e-9, $height);
        }
    }

    /**
     * A ceiling the inventory cannot come under is a warning naming the miss, not a refusal.
     *
     * The mirror of the interface warning and a warning for the mirror reason: the solver already keeps the best
     * arrangement that stands up, so the number it reached *is* what the inventory can do. Refusing would make the key
     * unusable on the rigs it exists for.
     *
     * The message says "the nearest the target" rather than "the shortest", which is not a wording change — the solver
     * ranks by `target_sub_height_m` now, and a message promising the shortest arrangement would describe a rule that
     * no longer exists, on exactly the rigs where the difference shows.
     */
    public function testACeilingTheStackCannotMeetWarnsAndNamesTheMiss(): void
    {
        $result = $this->solveTo(
            ['flexy-folded-horn-hybrid', 'achenbach-18'],
            maxWidthM: 3.70,
            maxSubHeightM: 1.0,
            flexyCount: 5,
            achenbachCount: 3,
        );

        self::assertSame([], $result['problems']);
        self::assertContains(
            'the subs reach 1.363 m against the 1.000 m ceiling asked for, so they stand 363 mm too high — '
            .'1.363 m is the nearest the 2.500 m target that every tier is still carried at',
            $result['warnings'],
        );

        // **And 1.363 m rather than 2.126 m is the point of the assertion above.** No arrangement of these cabinets
        // comes under a 1.0 m ceiling, and 2.126 m is the one nearest the 2.5 m target — so ranking on the target alone
        // answers a rig that misses by 1126 mm where one missing by 363 mm exists. With nothing legal to prefer between,
        // the aim falls back to the ceiling.
        self::assertLessThan(
            2.0,
            $this->subHeight($result['tiers']),
            'with nothing under the ceiling, the least miss wins rather than the nearest the target',
            $result['warnings'],
        );
    }

    /**
     * An interface height above the stack's own sub ceiling is refused rather than resolved by precedence.
     *
     * The two keys say opposite things about one number, and obeying either would silently ignore the other.
     */
    public function testAnInterfaceHeightAboveItsOwnSubCeilingIsRefused(): void
    {
        $problems = (new Stack(
            from: [new StackEntry('flexy-folded-horn-hybrid')],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
            maxSubHeightM: 1.5,
        ))->problems();

        self::assertCount(1, $problems);
        self::assertStringContainsString('interface_height_m (2) is above stack.max_sub_height_m (1.5)', $problems[0]);
    }

    /**
     * The invariant `pyramid` exists for: **no row holds more cabinets than the row below it.**
     *
     * A count rather than a width, because that is the rule — six Achenbachs at 3.700 m on six Flexys' 3.646 is a
     * 27 mm shoulder per side and flush, and capping the width refused it, split them into two rows of three and left
     * the tops with a 1.84 m row that could not carry them.
     */
    public function testAPyramidNeverWidensAsItRises(): void
    {
        foreach ([['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'], self::EVERY_SPEAKER] as $ids) {
            $tiers = $this->solveTo($ids, maxWidthM: 3.80, maxSubHeightM: 3.0, shape: StackShape::Pyramid)['tiers'];

            $below = PHP_INT_MAX;
            foreach ($tiers as $tier) {
                if (!$tier->isSub()) {
                    // Nothing stands on the tops, so their row is bounded by `max_width_m` and reported, not tapered.
                    continue;
                }
                self::assertLessThanOrEqual(
                    $below,
                    $tier->count(),
                    sprintf('%s holds more cabinets than the row below it', $tier->label()),
                );
                $below = $tier->count();
            }
        }
    }

    /**
     * The pyramid puts the type that makes the widest row on the floor, and that is what shortens the stack.
     *
     * Two wall basses are the heaviest cabinets in either system and 1.34 m of row between them; six IQ subs are
     * 3.28 m. Weight order puts the wall basses down and needs five rows; width order puts the IQ subs down and needs
     * three. The same twelve cabinets either way.
     */
    public function testAPyramidPutsTheWidestRowMakingTypeOnTheFloor(): void
    {
        $ids = ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub', 'tecnare-m2122'];

        $free = $this->solveTo($ids, maxWidthM: 3.80, maxSubHeightM: 3.0, shape: StackShape::Free);
        $pyramid = $this->solveTo($ids, maxWidthM: 3.80, maxSubHeightM: 3.0, shape: StackShape::Pyramid);

        self::assertSame([], $pyramid['problems']);
        self::assertStringContainsString('gmss-wall-bass', $free['tiers'][0]->label());
        self::assertStringContainsString('gmss-iq-sub', $pyramid['tiers'][0]->label());

        // 2.070 m, and it went 2.070 → 2.570 → 2.070 across two releases for two different reasons that are worth
        // keeping apart. `target_sub_height_m` took it to 2.570, because the pyramid had several arrangements that
        // stand up and the solver stopped taking the shortest. The **width-based** pyramid rule took it back, because
        // the 2.570 arrangement stepped a row 265 mm out over its support — legal under the old count rule, which
        // compared cabinets, and a V under the rule that compares metres.
        self::assertEqualsWithDelta(3.240, $this->subHeight($free['tiers']), 1e-9);
        self::assertEqualsWithDelta(2.070, $this->subHeight($pyramid['tiers']), 1e-9);
        self::assertLessThan(count($free['tiers']), count($pyramid['tiers']));

        // Same cabinets both ways — the shape is not bought by leaving one out.
        self::assertSame($this->cabinets($free['tiers']), $this->cabinets($pyramid['tiers']));
    }

    /**
     * A crater is still refused, and a shoulder is not — both directions, or the narrowing is untested.
     *
     * The crater: two 1.400 m wall basses either side of the 0.500 m mid bass leave the row above over a 900 mm void,
     * and an IQ sub landing in it came out 130 mm inside a wall bass. The shoulder: a Flexy flanking Achenbachs is a
     * 163 mm step and the row above rises clear of it, which is what the flanking rule exists to build.
     */
    public function testACraterIsRefusedAndAShoulderIsNot(): void
    {
        $crater = $this->solveTo(
            ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub'],
            maxWidthM: 3.80,
            maxSubHeightM: null,
        );
        foreach ($crater['tiers'] as $tier) {
            // The mid bass never ends up flanked on both sides by a wall bass.
            self::assertDoesNotMatchRegularExpression(
                '/gmss-wall-bass.*gmss-mid-bass.*gmss-wall-bass/',
                $tier->label(),
            );
        }

        $shoulder = $this->solveTo(
            ['flexy-folded-horn-hybrid', 'achenbach-18'],
            maxWidthM: 3.70,
            maxSubHeightM: null,
            flexyCount: 6,
            achenbachCount: 4,
        );
        self::assertSame([], $shoulder['problems']);
    }

    /**
     * Like {@see self::solve()} but hands back the whole result, so a test can read the warnings or assert on a
     * refusal, and takes the counts the scenario needs rather than the whole inventory.
     *
     * @param list<string> $ids
     * @return array{tiers: list<Tier>, problems: list<string>, warnings: list<string>}
     */
    private function solveTo(
        array $ids,
        ?float $maxWidthM,
        ?float $maxSubHeightM,
        ?int $skramCount = null,
        ?int $flexyCount = null,
        ?int $achenbachCount = null,
        StackShape $shape = StackShape::Free,
        float $targetSubHeightM = Stack::DEFAULT_TARGET_SUB_HEIGHT_M,
    ): array {
        $counts = array_filter([
            'skram' => $skramCount,
            'flexy-folded-horn-hybrid' => $flexyCount,
            'achenbach-18' => $achenbachCount,
        ], static fn (?int $count): bool => $count !== null);

        return StackSolver::solve(
            array_map(
                fn (string $id): array => [$this->devices[$id], $counts[$id] ?? $this->devices[$id]->quantity],
                $ids,
            ),
            new Stack(
                from: array_map(static fn (string $id): StackEntry => new StackEntry($id), $ids),
                maxWidthM: $maxWidthM,
                interfaceHeightM: 0.0,
                gapM: 0.02,
                maxSubHeightM: $maxSubHeightM,
                shape: $shape,
                targetSubHeightM: $targetSubHeightM,
            ),
        );
    }

    /**
     * @param list<string> $from
     * @return list<Tier>
     */
    private function solve(array $from, ?float $maxWidthM, float $interfaceHeightM): array
    {
        $result = StackSolver::solve(
            $this->inventory($from),
            new Stack(
                from: array_map(static fn (string $id): StackEntry => new StackEntry($id), $from),
                maxWidthM: $maxWidthM,
                interfaceHeightM: $interfaceHeightM,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);

        return $result['tiers'];
    }

    /**
     * Like {@see self::solve()}, but pins Achenbach to the four this scenario's numbers were computed
     * against — see testAStatedCountOverridesWhatTheInventoryHolds. Six real Achenbachs are their own
     * row's full 3.70 m stage width and can never again be narrower than what stands under them, which
     * is exactly the geometry these particular scenarios exist to demonstrate.
     *
     * @param list<string> $ids
     * @return list<Tier>
     */
    private function solvePinned(array $ids, ?float $maxWidthM, float $interfaceHeightM): array
    {
        $inventory = array_map(
            fn (string $id): array => [$this->devices[$id], 'achenbach-18' === $id ? 4 : $this->devices[$id]->quantity],
            $ids,
        );

        $result = StackSolver::solve(
            $inventory,
            new Stack(
                from: array_map(static fn (string $id): StackEntry => new StackEntry($id), $ids),
                maxWidthM: $maxWidthM,
                interfaceHeightM: $interfaceHeightM,
                gapM: 0.02,
            ),
        );

        self::assertSame([], $result['problems']);

        return $result['tiers'];
    }

    /**
     * @param list<string> $ids
     * @return list<array{DeviceSpec, int}>
     */
    /**
     * The seating check GEO-11 added, pinned on the solver alone.
     *
     * **Asserted here rather than through the compiler on purpose.** What this has to hold is the *contract* — a
     * candidate the caller refuses is not returned, the search goes on looking, and refusing everything still yields
     * an arrangement to report rather than an exception. Whether the compiler's own answer is right is
     * `ShippedScenesTest`'s job, and pinning both in one test would mean neither says which half broke.
     *
     * The third case is the one worth having: with every candidate refused there is nothing legal to rank, and
     * {@see StackSolver::fill} falls back to the widest attempt so the caller can say *why* rather than being handed
     * an empty rig. That fallback is deliberately outside the check — it is a diagnosis, not an offer.
     */
    public function testTheCallerCanRefuseAnArrangementAndTheSearchLooksFurther(): void
    {
        $inventory = $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122']);
        $stack = new Stack(
            from: array_map(
                static fn (string $id): StackEntry => new StackEntry($id),
                ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            ),
            maxWidthM: 3.70,
            interfaceHeightM: 0.0,
            gapM: 0.02,
            maxSubHeightM: 3.0,
        );

        $unchecked = StackSolver::solve($inventory, $stack);
        self::assertSame([], $unchecked['problems']);
        $first = array_map(static fn (Tier $t): string => $t->label(), $unchecked['tiers']);

        // Refuse exactly the arrangement it would otherwise return, and it has to find another.
        $refusals = 0;
        $second = StackSolver::solve($inventory, $stack, null, static function (array $tiers) use ($first, &$refusals): bool {
            $labels = array_map(static fn (Tier $t): string => $t->label(), $tiers);
            if ($labels === $first) {
                ++$refusals;

                return false;
            }

            return true;
        });

        self::assertGreaterThan(0, $refusals, 'the check has to be asked at all');
        self::assertNotSame(
            $first,
            array_map(static fn (Tier $t): string => $t->label(), $second['tiers']),
            'a refused arrangement is not the one handed back',
        );

        // And refusing everything leaves something to report rather than nothing to look at.
        $none = StackSolver::solve($inventory, $stack, null, static fn (array $tiers): bool => false);
        self::assertNotSame([], $none['tiers'], 'the widest attempt is still returned, so the refusal can name a rig');
    }

    /**
     * @param list<string> $ids
     * @return list<array{DeviceSpec, int}>
     */
    /**
     * **A sub wing that falls short of its interface says so, rather than saying nothing.**
     *
     * The rule used to be "nothing to fire over anybody's head means nothing to say", which is right about the tops
     * and wrong about the header: a scene file prints `Subs reach 1.800 m against a 2.000 m interface` for every
     * stack whether or not anything stands on it, so an unexplained miss reads as a solver bug to the next reader.
     * The suite caught a shipped scene doing exactly that — the shared tops all went to the two wide walls and left
     * six Achenbachs as a wing — and the miss is not specific to that axis value, since `--split=by-type` can hand a
     * stack whole types and give one of them no tops either.
     *
     * **And the reason has to be the right one.** "The tops sit lower than ideal" is false of a stack with no tops,
     * which is why this is a second message rather than the same one with the guard dropped.
     */
    public function testASubWingShortOfItsInterfaceSaysNothingStandsOnIt(): void
    {
        $result = StackSolver::solve(
            // Six Achenbachs and no top of any kind, two wide: 1.8 m against a 2.0 m interface.
            $this->inventory(['achenbach-18']),
            new Stack(from: [], maxWidthM: 1.30, interfaceHeightM: 2.0, gapM: 0.02),
        );

        $warnings = implode("\n", $result['warnings']);

        self::assertSame([], $result['problems']);
        self::assertStringContainsString('m interface asked for', $warnings, 'a short wing must not be silent');
        self::assertStringContainsString('nothing stands on them', $warnings);
        self::assertStringNotContainsString(
            'the tops sit',
            $warnings,
            'a stack with no tops cannot have tops sitting low',
        );
    }

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
