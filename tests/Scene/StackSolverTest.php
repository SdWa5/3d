<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\StackEntry;
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
     * The whole inventory **is** one stack, and the two SKRAMs go in the middle of the bottom row.
     *
     * This is the arrangement the feature was built for. It was briefly impossible, because mixing cabinets of
     * different heights was banned after a mixed row left six Flexys floating — but the floating was caused by
     * resting the whole row above at the taller cabinet's height, not by the mixing. Gravity fixed it: each
     * cabinet lands on whatever is under *it* ({@see \App\Scene\Stack::runsFor}), so the row above a mixed row
     * simply has an uneven top.
     */
    public function testTheWholeInventoryIsOneStackWithTheSkramsInTheBottomRow(): void
    {
        $tiers = $this->solve(
            ['flexy-folded-horn-hybrid', 'skram', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(23, $this->cabinets($tiers), 'every cabinet we own');
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
     * A stated `count` over-books deliberately, which a stack could not do at all before.
     *
     * `full-rig-all-tops` asks for six Achenbachs against the four we own — to see whether the rig would work
     * if two more were borrowed — and a stack had no way to say the same thing. Nothing new reports it:
     * `SceneReport::summarise()` already returns `over_inventory` and `scene:build` already warns on it.
     */
    public function testAStatedCountOverridesWhatTheInventoryHolds(): void
    {
        $achenbach = $this->devices['achenbach-18'];
        self::assertSame(4, $achenbach->quantity, 'four owned, which is what makes six a claim');

        $result = StackSolver::solve(
            [[$this->devices['flexy-folded-horn-hybrid'], 12], [$achenbach, 6], [$this->devices['tecnare-m2122'], 3]],
            new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 2.0, gapM: 0.02),
        );

        self::assertSame([], $result['problems']);
        self::assertSame([6, 6, 6, 3], array_map(static fn (Tier $t): int => $t->count(), $result['tiers']));
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
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
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
        $result = StackSolver::solve(
            $this->inventory(['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15']),
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
     */
    public function testASubTierIsFlankedFromBelowToCloseTheStep(): void
    {
        $tiers = $this->solve(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        self::assertSame(23, $this->cabinets($tiers), 'every cabinet we own, none spent on the flanks');
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
        $tiers = $this->solve(
            ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'],
            maxWidthM: 3.70,
            interfaceHeightM: 2.0,
        );

        // 18 mm each side, which {@see StackSolver::supportChecks} warns about and does not refuse.
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

        $check = new \ReflectionMethod(StackSolver::class, 'supportChecks');
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
     * A cabinet landing on less than half its own width is an error, however tidy the tier widths look.
     *
     * The gap the bearing check fills, in the geometry that found it. A single SKRAM between two Flexys makes a
     * 1.832 m row that is 151 mm taller in its middle, and a two-Flexy row on top of it is 1.202 m — comfortably
     * narrower, so the tier-width rule has nothing to say. Land the cabinets individually and the inner Flexy
     * rests on 295 mm of SKRAM and 296 mm of thin air: 49.9 %, over on the wrong side of the same
     * half-a-cabinet line the overhang rule already draws.
     *
     * This is what `scene:stack --stacks=2` runs into. Splitting the inventory in half puts one SKRAM in each
     * stack, and one SKRAM cannot be flanked into a bottom row that carries anything — which is why the
     * two-stack TODO says to keep the pair together.
     */
    public function testACabinetBearingOnLessThanHalfItsWidthIsAnError(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $tiers = [
            new Tier([[$flexy, 1], [$this->devices['skram'], 1], [$flexy, 1]]),
            Tier::of($flexy, 2),
        ];

        $stack = new Stack(from: [], maxWidthM: 3.70, interfaceHeightM: 0.0, gapM: 0.02);
        $check = new \ReflectionMethod(StackSolver::class, 'supportChecks');

        $problems = $check->invoke(null, $tiers, $stack)['problems'];

        self::assertStringContainsString('land on only 50% of its own width', implode("\n", $problems));
        self::assertSame([], $check->invoke(null, [$tiers[0]], $stack)['problems'], 'the row itself is fine');
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
        $tiers = $this->solve(
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
        $tiers = $this->solve(
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
     * Refusing was wrong, and it made small rigs unbuildable for no good reason — four Achenbachs one-wide
     * reach 2.400 m and two-wide only 1.200 m, and neither is absurd. Tops sitting lower than ideal is a
     * judgement about coverage; a cabinet hanging off its support is not, and that one stays an error.
     *
     * 12 m rather than a rounder number on purpose: every Flexy in a one-wide column is 9.156 m and the
     * Achenbachs add 2.4, so 11.556 m is the ceiling of this inventory however the rows are cut. Anything
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
        // 5.778 m, not the 11.556 m of one-wide columns: narrowing that far would leave the three-wide
        // Tecnare row 470 mm off each edge of a single Flexy, and support outranks the interface.
        self::assertStringContainsString('5.778 m against the 12.000 m interface', $warnings);
        self::assertStringContainsString('while every tier is still carried', $warnings);
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
