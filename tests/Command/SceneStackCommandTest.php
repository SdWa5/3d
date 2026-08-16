<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `scene:stack` — solving a rig from constraints and writing it out.
 *
 * Everything here runs `--dry-run` except the tests that are *about* writing, and those write into the real
 * `scenes/` directory under a throwaway `--id` and are cleaned up in `tearDown()`. That is the same
 * bargain `SpecValidatorTest` strikes with `SpecFactory::tempDir()`: the command resolves its own paths from
 * the project root, so pointing it somewhere else would mean testing a different code path than the one that
 * ships.
 */
final class SceneStackCommandTest extends TestCase
{
    private const THROWAWAY_ID = 'zz-test-stack';

    /**
     * Every cabinet that can share a stack. The SKRAMs are left out on purpose: only two exist, nothing else
     * shares their height so they cannot be mixed into a row, and a row of their own carries nothing — the
     * solver refuses them, which {@see testAnArrangementThatCannotBeSolvedIsSkippedWithAReason} pins.
     */
    private const STACKABLE = ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'];

    /**
     * Everything the collective itself owns, SKRAMs included — the whole of what one rig could be built from.
     *
     * This used to be expressed as passing no `--from` at all, which meant "every spec in the repository". That
     * stopped being the same thing when the GMSS cabinets arrived: those describe a **different sound system**
     * that this repository only documents, they are all `provenance: estimated`, and a rig solved out of two
     * systems' gear at once is not something anybody would build. So the gear is named now rather than implied.
     *
     * **The order matters and is not alphabetical.** `--from` is taken as given — "low frequency first" — where
     * the default sorts subs before tops and each by its driven corner. This list reproduces that sort: SKRAM
     * (15 Hz) then Flexy (38, up to 200) then Achenbach (driven from 38, up to 1500, so the high corner breaks
     * the tie), then the tops. Shuffle it and the solver deals a different rig.
     */
    private const OWN_GEAR = [
        'skram',
        'flexy-folded-horn-hybrid',
        'achenbach-18',
        'eighteensound-2way-15',
        'tecnare-m2122',
    ];

    protected function tearDown(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/scenes/generated/'.self::THROWAWAY_ID.'*.yaml') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * The alignments are tried, and the ones that resolve to the same rig are written once.
     *
     * **`stereo` is now its own rig and `block` still is not**, which is the useful half of this test. A mixed tops
     * row is not distributed one segment at a time, so `block` has nothing to justify and comes out identical to
     * `center` — that limitation is unchanged. What changed is the *order* of the tops row: `stereo` puts the long
     * throws at the outer ends and the near-field fills inboard of them, where `center` centres the long throw. This
     * test used to assert that all three collapsed into one file and that "three files would imply a choice that
     * does not exist". Two of them now do.
     */
    public function testAlignmentsThatResolveToTheSameRigAreWrittenOnce(): void
    {
        // ONE SHAPE AND ONE ORIENTATION NAMED, because this test is about the three alignments and both of those axes
        // multiply them the same way — every value is written by default, so leaving them open would make this assert 4
        // and then 12, and stop saying anything about alignment.
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--dry-run' => true,
        ]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(2, preg_match_all('/^id: /m', $display), 'center and stereo differ; block does not');
        self::assertStringContainsString('the same rig as stacked-pyramid-upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-stereo', $display);

        // In stereo the long throw is at BOTH ends with the fills inboard, and the odd M2122 sits on the centre
        // line so the two clusters stay equal — a palindrome, where the centred row reads
        // `2way + 3× tecnare + 2way` with the long throw in the middle instead.
        self::assertStringContainsString(
            '1× tecnare-m2122 + 1× eighteensound-2way-15 + 1× tecnare-m2122 + 1× eighteensound-2way-15 '
            .'+ 1× tecnare-m2122',
            $display,
        );
    }

    /**
     * **THE HEADLINE: the bare command writes scenes.** Until 0.68.0 it wrote none at all.
     *
     * The project's goal is that as many sensible configurations as possible come out of one command in its default
     * settings, and measured against that the command scored zero: `--from` defaulted to every speaker in the
     * repository, which since the GMSS cabinets arrived means two sound systems in one stack whose eight tops alone
     * are 3.921 m and refuse on any stage we own. Every generated scene had to spell four to six flags out.
     *
     * Asserted as a floor rather than an exact count, because the number moves with the inventory and pinning it
     * would turn every new cabinet into a failing test. What must hold is that the sweep covers more than one owner
     * and more than one stack count, and that nothing is dropped in silence.
     *
     * **The floor came down from 25 scenes to 10 when the sub height band started binding**, and that is the trade
     * rather than a regression: of the 132 candidates, 54 cannot fill a 2 m sub wall out of the cabinets they are
     * given and 20 cannot get under 3 m even on a 6 m stage, so they are rigs whose tops would fire at knee height or
     * over the truss. Fewer scenes, each of them one somebody could build.
     */
    public function testTheBareCommandWritesScenesAcrossOwnersAndStackCounts(): void
    {
        $tester = $this->invoke(['--dry-run' => true]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertGreaterThan(8, preg_match_all('/^id: /m', $display), 'the bare command must produce a set');

        // More than one owner, and more than one stack count, or it is not a sweep. `-+` rather than `-` between the
        // owner label and the stack count, because the label is padded to a fixed width with dashes — see
        // {@see \App\Command\SceneStackCommand::padded}. Matching a single dash pinned the padding by accident, which
        // is not what any of these four lines is about.
        self::assertMatchesRegularExpression('/^id: \S*-sdwa5-+\d/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-gmss-+\d/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-\w+-+1/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-\w+-+2/m', $display);

        // Every refusal carries a reason — a sweep that drops candidates silently reads as "that is all there is".
        foreach (explode("\n", $display) as $line) {
            if (str_contains($line, 'skipped')) {
                self::assertStringContainsString(' — ', $line, 'a skipped candidate must say why');
            }
        }
    }

    /**
     * Naming any of the narrowing options collapses the sweep to that one point.
     *
     * The sweep is what *absence* means; nothing that worked before behaves differently.
     */
    public function testNamingTheGearOrStackCountCollapsesTheSweep(): void
    {
        $display = $this->invoke([
            '--from' => self::STACKABLE, '--stacks' => '1', '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--dry-run' => true,
        ])->getDisplay();

        self::assertSame(1, preg_match_all('/^id: /m', $display));
        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-center', $display, 'no owner or stack-count suffix');
    }

    /** The fast path: one alignment, one shape and one orientation named outright, exactly one scene. */
    public function testASingleAlignmentProducesExactlyOneScene(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['block'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--dry-run' => true,
        ]);

        self::assertSame(1, preg_match_all('/^id: /m', $tester->getDisplay()));
    }

    /**
     * Every shape is written, and the pyramid is the one that keeps the plain id.
     *
     * The three answer different questions. The pyramid orders the fill for row *width* so the wall tapers, `free`
     * keeps the deepest and heaviest cabinets on the floor and asks only the bearing rule, and `v` reverses the
     * pyramid's order so the wall widens as it rises. On these cabinets that is three different floors for the same
     * twelve boxes, which is worth being able to look at every way round.
     *
     * **Asserted on which type is on the floor, not on how many of it.** What the pyramid puts under the six IQ subs
     * moves whenever `target_sub_height_m` prefers a different arrangement, so pinning the count would make this test
     * fail on every future change to what the solver prefers — which is not what it is about.
     */
    public function testEveryShapeIsWrittenAndThePyramidKeepsThePlainId(): void
    {
        $display = $this->invoke([
            '--from' => ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub', 'tecnare-m2122'],
            // 3.5 m, not the 3.0 m default: `free` comes out at 3.240 m here and the band would refuse it, and the
            // subject of this test is the two fill orders rather than which of them meets a ceiling.
            '--max-width' => '3.80', '--interface-height' => '0', '--max-sub-height' => '3.5',
            // One orientation, so the three scenes below are the three shapes rather than shapes times orientations.
            '--align' => ['center'], '--orientation' => ['upright'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-free----upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-v-------upright-alternate-center', $display);

        // The pyramid puts the IQ subs on the floor, which is the widest row they can make; `free` puts the two wall
        // basses there, which is 1.34 m and two rows more of stack; `v` puts the single mid-bass there at 1.20 m,
        // which is the narrowest floor of the three because everything above it has to be wider.
        // The order is {@see StackShape::cases()}, so 0 is the pyramid, 1 is free and 2 is v.
        preg_match_all('/^#\s+1\s+(\S.*?)\s{2,}[\d.]+ m wide$/m', $display, $bottomRows);
        self::assertCount(3, $bottomRows[1], 'one bottom row per shape');
        self::assertStringContainsString('gmss-iq-sub', $bottomRows[1][0], 'the pyramid stands on the IQ subs');
        self::assertStringContainsString('gmss-wall-bass', $bottomRows[1][1], 'and free on the wall basses');
        self::assertStringContainsString('gmss-mid-bass', $bottomRows[1][2], 'and v on the single mid-bass');
    }

    /**
     * The tall stacks go where the alignment wants them: middle in mono, ends in stereo.
     *
     * The same rule as the tops row one level up — `topRow()` centres the long throw, `stereoTopRow()` pushes it
     * outboard — and until 0.68.0 the stacks came out in solve order with nothing looking at their heights, so
     * `--per-owner` read `3.34 | 3.20 | 1.80` with the tallest hard left in seven of thirty multi-stack scenes.
     *
     * `--per-owner` is the case that shows it, because three owners give three deliberately unequal stacks.
     */
    public function testTheTallestStackGoesWhereTheAlignmentWantsIt(): void
    {
        // The band is opted out of with the two bounds that are its control, because this is a test about *where*
        // the tall stacks go: three owners give three deliberately unequal stacks, and the tallest of them is over
        // the 3 m ceiling by construction. Holding it to the band would be asserting on a refusal.
        $shared = ['--per-owner' => true, '--max-width' => '3.70', '--gap' => '0.05',
            '--interface-height' => '0', '--max-sub-height' => '99', '--shape' => ['pyramid'],
            '--orientation' => ['upright'], '--mirror-style' => ['alternate'], '--dry-run' => true];

        $mono = $this->heights($this->invoke($shared + ['--align' => ['center']])->getDisplay());
        self::assertCount(3, $mono);
        self::assertSame(max($mono), $mono[1], 'mono: the tallest stack takes the middle');

        // **The stereo half is deliberately not asserted here, because no multi-stack stereo rig survives the checks
        // yet** — the tops-spread envelope refuses the narrow supports, and every generated scene is mono. Asserting
        // it would mean asserting on a refusal. `byHeight()` mirrors the mono order for stereo and the ordering is
        // pinned by the mono case; what is missing is a rig to see it on, which is a TODO rather than a test.
    }

    /**
     * The sub heights of each stack, left to right, off the header the writer prints.
     *
     * @return list<float>
     */
    private function heights(string $display): array
    {
        preg_match_all('/^# Subs reach ([\d.]+) m/m', $display, $matches);

        return array_map(floatval(...), $matches[1]);
    }

    /** An unknown `--shape` names the values there are rather than falling back to one. */
    public function testAnUnknownShapeIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::STACKABLE, '--max-width' => '3.70', '--shape' => ['wedge'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--shape: unknown value 'wedge'", $tester->getDisplay());
        self::assertStringContainsString('allowed: pyramid, free', $tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--id' => self::THROWAWAY_ID, '--dry-run' => true,
        ]);

        self::assertSame([], glob(dirname(__DIR__, 2).'/scenes/generated/'.self::THROWAWAY_ID.'*.yaml') ?: []);
    }

    /**
     * Generated files live next to hand-written ones, so clobbering one has to be asked for. This is the
     * test that stops the command eating a scene somebody spent an afternoon commenting.
     */
    public function testAnExistingSceneIsRefusedWithoutForce(): void
    {
        $args = ['--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['block'], '--id' => self::THROWAWAY_ID];
        $first = $this->invoke($args);
        self::assertSame(0, $first->getStatusCode());

        $again = $this->invoke($args);
        self::assertSame(1, $again->getStatusCode());
        self::assertStringContainsString('--force', $again->getDisplay());

        $forced = $this->invoke($args + ['--force' => true]);
        self::assertSame(0, $forced->getStatusCode());
    }

    /**
     * A silent cap would read as "that is every possibility" when it is not, so going over the limit is a
     * refusal with the count in it.
     */
    public function testExceedingMaxScenesIsRefusedRatherThanTruncated(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--max-scenes' => '0', '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--max-scenes=0', $tester->getDisplay());
    }

    /**
     * A wide stage must not cost the rig its height. `max_width_m` is a maximum, not a target: on a 10 m
     * stage every device fits in one row, which leaves two sub tiers and puts a 2 m interface out of reach
     * forever unless the rows are allowed to narrow.
     */
    public function testAWideStageStillReachesTheInterface(): void
    {
        foreach ([['--max-width' => '10.0'], []] as $widthOption) {
            $tester = $this->invoke($widthOption + [
                '--from' => self::STACKABLE, '--interface-height' => '2.0',
                '--align' => ['center'], '--dry-run' => true,
            ]);

            self::assertSame(0, $tester->getStatusCode(), 'a wide or unbounded stage still solves');
            self::assertStringContainsString('against a 2.0 m interface', $tester->getDisplay());
        }
    }

    /**
     * Asked for the whole inventory, the command places **all twenty-three** cabinets in one stack.
     *
     * The SKRAMs used to be left out, because a row of the two of them carries nothing and mixing them into a
     * Flexy row was banned after that mixed row left Flexys floating. Gravity removed the reason for the ban —
     * each cabinet lands on whatever is under it — so the arrangement the mixed row was built for works, and
     * the leaving-out machinery is left for cases that genuinely cannot stand up.
     *
     * 5.0 m rather than 3.70 m: six real Achenbachs are their own row's full 3.70 m stage width, so a SKRAM
     * row flanked by three Flexys either side (4.906 m) no longer fits under the 3.70 m bound and the command
     * falls back to leaving the SKRAMs out. Widen the stage and the flanked arrangement is reachable again.
     *
     * The gear is named via {@see OWN_GEAR} rather than left to the default, which is what "the whole
     * inventory" meant before a second sound system was documented in this repository.
     */
    public function testEverythingTheCollectiveOwnsGoesIntoOneStack(): void
    {
        // The shape and the orientation are named, because the band stopped refusing the variants: a `v` on rolled
        // cabinets does have to leave a Tecnare out, and it is a different rig rather than a counter-example to this
        // one. The subject is the SKRAMs sharing a bottom row in the rig this test has always been about.
        $tester = $this->invoke([
            '--max-width' => '5.0', '--interface-height' => '2.0', '--from' => self::OWN_GEAR,
            '--shape' => ['free'], '--orientation' => ['upright'], '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringNotContainsString('LEFT OUT', $output, 'nothing needs leaving out any more');
        self::assertStringContainsString('2× skram', $output, 'the SKRAMs share the bottom row');
    }

    /**
     * `--per-owner` groups by {@see \App\Spec\DeviceSpec::$owner} and adds no new concept: for this
     * collective, who owns a cabinet *is* the split between the rigs. Each group becomes its own stack, and
     * the stacks stand side by side rather than merging into one pile.
     *
     * Three owners now, not two — GMSS is the third, and it is exactly the case this option is for: a second
     * sound system's gear must not end up in the same pile as the collective's own.
     */
    public function testPerOwnerWritesOneStackPerOwnerSideBySide(): void
    {
        $tester = $this->invoke([
            '--per-owner' => true, '--max-width' => '3.70', '--gap' => '0.05',
            // Outside the band by construction — see `testTheTallestStackGoesWhereTheAlignmentWantsIt`. The subject
            // here is which stack each owner's gear lands in.
            '--interface-height' => '0', '--max-sub-height' => '99',
            // Pinned to the pyramid, and to the 0.05 m gap every per-owner scene in the library uses: at 0.02 the
            // aimed tops toe into each other, which is the whole reason the wider gap was chosen for these rigs.
            // Pinned to the pyramid because: the free-shape GMSS stack interpenetrates by 22.5 mm and the generator now
            // refuses it. That overlap is not new — it was simply never checked, because `--dry-run` writes no file
            // and only written scenes reach the shipped-scene sweep.
            '--shape' => ['pyramid'], '--mirror-style' => ['alternate'],
            '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-sdwa5', $output);
        self::assertStringContainsString('- id: main-sepp', $output);
        self::assertStringContainsString('- id: main-gmss', $output);
        self::assertStringContainsString('3 stacks side by side', $output);
    }

    /** `--stacks=2` is how a stereo pair is asked for: each group split evenly into two. */
    public function testStacksSplitsAGroupIntoThatManyStacks(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-1', $output);
        self::assertStringContainsString('- id: main-2', $output);
        // Six Flexys each rather than twelve in one, because the split shares every device out.
        self::assertStringContainsString('2 stacks side by side', $output);
    }

    /**
     * A split rig writes each stack's **share** into the file, because the file is re-solved on every build.
     *
     * This was a silent and complete failure of multi-stack scenes. A `stack:` block carries constraints and a
     * device list, and the compiler solves it afresh against each spec's own `quantity` — so a rig reported in
     * its header as two stacks of eleven was *built* with both stacks holding all twenty-three cabinets: two
     * 3.6 m walls 0.5 m apart, 561 mm inside each other. Nothing said so, because the header comment described
     * the intended split and no check ever compiled the written file.
     */
    public function testASplitRigWritesEachStacksShareSoItRebuildsTheSame(): void
    {
        // One shape and one orientation, so the count below is the two stacks of one scene rather than the same two
        // stacks across every variant of it.
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--stacks' => '2', '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        // Six of the twelve Flexys per stack, stated, so the re-solve cannot reach for all twelve.
        $output = $tester->getDisplay();
        self::assertStringContainsString("- device: flexy-folded-horn-hybrid\n          count: 6", $output);
        self::assertSame(2, substr_count($output, 'count: 6'), 'one share per stack');
    }

    /** And a rig that is *not* split keeps the shorthand, so an ordinary file stays a list of ids. */
    public function testAnUnsplitRigKeepsTheShorthandDeviceList(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--align' => ['center'], '--dry-run' => true,
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('- flexy-folded-horn-hybrid', $output);
        self::assertStringNotContainsString('count:', $output);
    }

    /**
     * One stack of a side-by-side pair is the **mirror image** of the other, not a second copy of it.
     *
     * An unmirrored pair is the same rig built twice: both SKRAM mouths facing the same way, both tops rows in the
     * same left-to-right order, and the two fills therefore on the same side of their stacks instead of both
     * facing the middle. It measures identically to a mirrored pair, which is why nothing caught it.
     */
    public function testOneStackOfEachPairIsTheMirrorImageOfTheOther(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
            // **The collective's own gear, named.** This used to pass no `--from` at all, which means every speaker in
            // the repository — two sound systems in one stack, and a rig the sub height band refuses. Naming the gear
            // that the two `--roll-mirror` cabinets actually belong to makes it a rig that ships: 2.392 m of subs on a
            // 2.0 m interface, mirrored, which is what this test is about.
            '--from' => self::OWN_GEAR,
            '--shape' => ['free'], '--mirror-style' => ['alternate'],
            '--roll-mirror' => ['skram', 'flexy-folded-horn-hybrid'],
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('mirror: true', $output);
        self::assertSame(1, substr_count($output, 'mirror: true'), 'only the earlier stack of the pair flips');

        // A ROLLED ROW READS REVERSED in the mirrored stack — 2 turned one way and 1 the other becomes 1 and 2.
        // This used to be pinned on the tops row instead, as "the 2-way ends up on the inner side of each stack",
        // and that was pinning the *fill order* rather than the mirroring: which device lands in the tops row moved
        // the moment the fill order changed to put the heavy GMSS subs on the floor, and the mirroring it was meant
        // to be testing had not changed at all. A rolled row is the better evidence because its handedness is the
        // thing `mirror` actually reverses.
        // Read on the mixed bottom row, which is the same row in both stacks and differs only in handedness: the
        // rolled SKRAM between two rolled Flexys faces one way in stack 1 and the other in its mirror image.
        self::assertStringContainsString(
            '1× flexy-folded-horn-hybrid rolled 270° + 1× skram rolled 270° + 1× flexy-folded-horn-hybrid rolled 90°',
            $output,
        );
        self::assertStringContainsString(
            '1× flexy-folded-horn-hybrid rolled 270° + 1× skram rolled 90° + 1× flexy-folded-horn-hybrid rolled 90°',
            $output,
        );

        // And the SKRAM is handed the other way in the mirrored stack.
        self::assertStringContainsString('1× skram rolled 270°', $output);
        self::assertStringContainsString('1× skram rolled 90°', $output);
    }

    /**
     * A single stack has no pair to mirror against, so it never claims one — at any orientation.
     *
     * Asserted on `mirror: true` rather than on `mirror:`, which is the key this test means and not merely a shorter
     * way of writing it. A turned rig states `roll_mirror: 90.0` on every rolled cabinet, and that ends in the same
     * seven characters — so the loose form passed only for as long as nothing was ever rolled, and would have failed on
     * a correct stack the moment one was.
     */
    public function testASingleStackIsNeverMirrored(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertStringNotContainsString('mirror: true', $tester->getDisplay());
    }

    public function testAStackCountBelowOneIsRejected(): void
    {
        $tester = $this->invoke(['--stacks' => '0', '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--stacks must be at least 1', $tester->getDisplay());
    }

    /**
     * **An unbuildable rig and an unusable request are both "nothing written" and must not share an exit code.**
     *
     * {@see SceneStackCommand::NOTHING_TO_WRITE} means the solver could not stand any of these rigs up, which is a
     * fact about the gear and is what makes `build:all`'s regenerate stage idempotent — it reads that code as "this
     * scene is stale, delete it". A `--stacks=0` is not that. It is a number nobody can act on, and reporting it the
     * same way would have a typo in a recorded command read as a scene to throw away. The two came out as one code
     * in 0.84.0 and three tests caught it.
     */
    public function testAnUnbuildableRigAndAnUnusableRequestExitDifferently(): void
    {
        // No workable arrangement: a 0.2 m stage carries nothing this repository owns.
        $unbuildable = $this->invoke([
            '--from' => ['flexy-folded-horn-hybrid'], '--max-width' => '0.2', '--dry-run' => true,
        ]);
        self::assertSame(SceneStackCommand::NOTHING_TO_WRITE, $unbuildable->getStatusCode());
        self::assertStringContainsString('No workable arrangement', $unbuildable->getDisplay());

        // Unusable request: the same empty candidate list, reached by asking for something incoherent.
        $unusable = $this->invoke([
            '--from' => ['flexy-folded-horn-hybrid'], '--split' => 'sideways', '--dry-run' => true,
        ]);
        self::assertSame(SceneStackCommand::FAILURE, $unusable->getStatusCode());
    }

    /**
     * The one that matters. A generator that emits a scene the compiler rejects is worse than no generator,
     * because the failure then surfaces later and further from its cause — so every candidate is compiled
     * before it is written, and this pins that the check is real by reading the cabinets back.
     *
     * **The invariant is that no cabinet goes missing *quietly*, which is not the same as every scene holding all 23.**
     * It used to be written the second way, and that was only true for as long as one gear list produced one rig. It now
     * produces seven, and they are genuinely different rigs: `mixed` rolls the Flexys, which makes each sub row 3.112 m
     * of four cabinets instead of 3.646 m of six, so the wall tapers faster and the two 2-ways have nothing left to
     * stand on. That rig carries 21 and **names the two it left out**, which is the generator working correctly.
     *
     * So the count asserted is 23 less whatever the file says it left out. A silent drop still fails, which is the defect
     * this test exists for; a refusal the file explains is allowed to be a refusal.
     */
    public function testEveryGeneratedSceneCompilesAndPlacesEveryCabinet(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--id' => self::THROWAWAY_ID,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        // Under `generated/`, which is the one thing the layout rule turns on: everything derived from a scene
        // takes its subdirectory from where the scene itself sits, so a generated rig can never overwrite the
        // build output of a hand-written one that shares its id.
        $written = glob(dirname(__DIR__, 2).'/scenes/generated/'.self::THROWAWAY_ID.'*.yaml') ?: [];
        self::assertNotSame([], $written);
        self::assertSame([], glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: []);

        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }
        $loader = new SceneLoader(dirname(__DIR__, 2).'/scenes');

        foreach ($written as $file) {
            $result = (new SceneCompiler($devices))->compile($loader->load($file));

            self::assertSame(
                [],
                array_map(static fn ($v): string => $v->message, Violation::errorsIn($result['violations'])),
                basename($file).' does not compile',
            );

            // 12 Flexy + 6 Achenbach + 3 Tecnare + 2 2-ways, less whatever this file states it could not carry. A
            // generator that quietly dropped cabinets would pass every other check in this file.
            preg_match_all('/^#\s+\*\s+([a-z0-9-]+): LEFT OUT/m', (string)file_get_contents($file), $omitted);
            $missing = array_sum(array_map(
                static fn (string $id): int => $devices[$id]->quantity,
                array_unique($omitted[1]),
            ));

            self::assertCount(23 - $missing, $result['placed'], basename($file).' lost cabinets it did not name');
        }
    }

    public function testAnUnknownAlignmentIsRejected(): void
    {
        $tester = $this->invoke(['--align' => ['blok'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("unknown value 'blok'", $tester->getDisplay());
    }

    public function testAnUnknownDeviceIsRejected(): void
    {
        $tester = $this->invoke(['--from' => ['nope'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("Unknown device 'nope'", $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $options
     */
    /**
     * `--split=by-type` gives each stack whole device types, and that is what makes it low.
     *
     * Split by count, every stack holds every type and is as many rows tall as there are types. Split by type,
     * each holds two or three and comes out in three rows. Asserted on the **rows**, not on which type landed
     * where: the balance is decided by `quantity × width`, so a cabinet finally being measured is allowed to move
     * a type from one stack to another without this test caring.
     */
    public function testSplitByTypeGivesEachStackWholeTypesAndFewerRows(): void
    {
        $shared = [
            // Two stacks rather than three: at three, an aimed tops row overlaps itself by 17.6 mm and the generator
            // refuses it now. The point of this test — by-type gives each stack whole types and fewer rows — is the
            // same either way.
            '--from' => self::OWN_GEAR, '--stacks' => '2', '--max-width' => '3.70',
            '--interface-height' => '0', '--align' => ['center'], '--dry-run' => true,
        ];

        $byCount = $this->invoke($shared);
        $byType = $this->invoke($shared + ['--split' => 'by-type']);

        self::assertSame(0, $byType->getStatusCode());
        // No stack taller than the tallest by-count one. Asserted on ROWS being no more rather than strictly fewer:
        // at two stacks the two splits need the same row count and by-type's gain shows up as height, while at three
        // an aimed tops row overlaps itself and the generator refuses the rig outright.
        self::assertLessThanOrEqual(
            max($this->rowsPerStack($byCount->getDisplay())),
            max($this->rowsPerStack($byType->getDisplay())),
            'by-type should leave no stack taller than the tallest by-count stack',
        );

        // Every stack holds every type by count, and a proper subset of them by type.
        foreach ($this->rowsPerStack($byType->getDisplay()) as $rows) {
            self::assertLessThan(count(self::OWN_GEAR), $rows);
        }
    }

    /**
     * The odd cabinet is placed by default and left out only when asked.
     *
     * Three Tecnares across two stacks: dealt, they are one and two and all three are in the rig; refused, they
     * are one each and the third is reported. The whole difference `--no-asymmetry` makes, on the smallest case
     * that shows it.
     */
    public function testTheOddCabinetIsPlacedByDefaultAndLeftOutOnlyWhenAsked(): void
    {
        // One shape named, because the band no longer refuses the others and two of them now write the same split
        // twice. The subject is what the split does with the odd cabinet, which no shape changes.
        $shared = [
            '--from' => ['flexy-folded-horn-hybrid', 'tecnare-m2122'], '--stacks' => '2', '--shape' => ['free'],
            '--orientation' => ['upright'], '--max-width' => '3.70', '--align' => ['center'], '--dry-run' => true,
        ];

        $dealt = $this->invoke($shared)->getDisplay();
        self::assertStringContainsString('SPLIT UNEVENLY, 1 of 3 over 2 stacks', $dealt);
        self::assertSame([7, 8], $this->cabinetsPerStack($dealt));

        $refused = $this->invoke($shared + ['--no-asymmetry' => true])->getDisplay();
        self::assertStringContainsString('LEFT OUT, 1 of 3', $refused);
        self::assertSame([7, 7], $this->cabinetsPerStack($refused));
    }

    /** `--max-sub-height` reaches the written stack, so the scene keeps asking for it on every rebuild. */
    public function testTheSubHeightCeilingIsWrittenIntoTheScene(): void
    {
        $display = $this->invoke([
            // 3.5 m because this gear comes out at 3.040 m, so the ceiling is met rather than missed. A missed one
            // would be written too — see the band tests — and this is about the key reaching the file.
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.5',
            '--interface-height' => '0', '--align' => ['center'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('max_sub_height_m: 3.5', $display);
    }

    /**
     * **A row is slid along its support rather than the rig being refused**, where nothing stands beside it.
     *
     * The GMSS cabinets turned on their sides are the case a wider stage cannot help: five rolled IQ subs make a
     * 3.29 m row and the widest support the rest of the inventory can put under it is 2.77 m, so the row hangs 260 mm
     * proud each side. Centred, an IQ sub lands on a fraction of itself and the arrangement is thrown away. Slid, the
     * row is carried and the rig comes out at 2.66 m.
     *
     * Measured by taking the slack away rather than by reading the solver: with `slideSlackM` forced to null the best
     * arrangement this invocation can find reaches 3.340 m against the 3.000 m ceiling, 680 mm further from the aim.
     * That is the whole value of the line in {@see \App\Command\SceneStackCommand} that hands a solo stack `INF`, and
     * it is what this test guards. It cost the rig outright while the ceiling was a gate; now it costs 680 mm.
     *
     * Pinned on the sub height rather than on the offset, because the height is what the rig is for and the offset is
     * how it got there.
     *
     * **THE 2.66 m ARRANGEMENT THIS TEST WAS WRITTEN AROUND OVERLAPS, AND GEO-11 IS WHAT FOUND OUT.** The five rolled
     * IQ subs slid along a 2.77 m support put two cabinets inside each other once placed, which no row width could
     * see and nothing checked, because this invocation states a `--max-width` and so was never one of the shipped
     * scenes the interpenetration sweep covers. The seating check refuses it — measured, it is the only candidate
     * refused for this rig — and the search falls to a clean 2.77 / 2.74 / 2.07 m at **2.70 m**. Four centimetres
     * further from the aim, and a rig that can actually be built.
     *
     * **So this no longer exercises the slide**, since every row now sits narrower than the one under it and nothing
     * hangs proud. That is filed as TOOL-8 rather than left looking covered. The feature itself is plainly still
     * live: 273 generated scenes carry an overhang warning, against 262 before the change.
     *
     * `gmss-mid-bass` is left out here and always was — 1.200 m of cabinet that the 3.70 m stage cannot carry beside
     * the rest — so that is the rig rather than anything this change did.
     */
    public function testASoloStackSlidesARowRatherThanLosingTheRig(): void
    {
        $display = $this->invoke([
            '--from' => ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub', 'gmss-nuke', 'gmss-turbo-top'],
            '--max-width' => '3.70', '--stacks' => '1', '--align' => ['center'], '--shape' => ['free'],
            '--orientation' => ['turned'], '--mirror-style' => ['centred'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-free----turned--centred---center', $display);

        // Carried rather than refused, which is what the name is about. The rig comes out, and the only cabinet it
        // gives up is the one the stage cannot take.
        self::assertStringContainsString('gmss-mid-bass: LEFT OUT', $display);
        self::assertStringNotContainsString('gmss-iq-sub: LEFT OUT', $display, 'the subs are all carried');

        self::assertSame([2.7], $this->heights($display));
    }

    /**
     * **A rig whose sub wall misses the band is written anyway, and says by how much.** Stated by the owner: the
     * sub/top interface height is an optimisation problem rather than a hard constraint.
     *
     * It used to be a refusal, and that refusal threw away 258 candidates in one family — every one of them a rig
     * that stands up and is merely shorter or taller than ideal. What replaces it is a number in two places: a
     * `noted` line for whoever ran the command, and the same sentence in the file's own header for whoever opens it
     * later and would otherwise read a knowingly short wall as a bug.
     *
     * Asserted on the numbers rather than on the note alone, because "40 mm too high" and "1470 mm too high" are
     * different rigs and only one of them is worth looking at.
     */
    public function testARigThatMissesTheSubHeightBandIsWrittenWithTheMeasurement(): void
    {
        $display = $this->invoke([
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.0',
            '--interface-height' => '0', '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('3.040 m against the 3.000 m ceiling', $display);
        self::assertStringContainsString('40 mm too high', $display);
        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-center', $display, 'the miss no longer costs the rig');
        self::assertMatchesRegularExpression(
            '/#\s+\*\s+the subs reach 3\.040 m against the 3\.000 m ceiling/',
            $display,
            'the miss has to be on the file, not only on the terminal',
        );
    }

    /**
     * The floor is the other half, and it is the same answer: a wall too short to get the tops over a standing crowd
     * is a rig with a note on it. Two Achenbachs cannot make a 2 m wall however they are stacked, and a pair of
     * Achenbachs with a 2-way on top is a rig somebody would genuinely carry into a small room.
     */
    public function testAWallTooShortToClearTheInterfaceIsWrittenWithTheMeasurement(): void
    {
        $display = $this->invoke([
            '--from' => ['achenbach-18', 'eighteensound-2way-15'], '--max-width' => '3.70',
            '--interface-height' => '2.0', '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('fire below head height', $display);
        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-center', $display, 'a short wall is a rig, not a refusal');
    }

    /**
     * **The two bounds are the control, so there is no third option.** Stating a band wide enough for anything is how
     * a caller declines the check — which is what the low hand-written rigs say for themselves — and it has to keep
     * working, or the only way to build an unusual rig would be to edit the command.
     */
    public function testStatingABandWideEnoughForAnythingAcceptsTheSameRig(): void
    {
        $display = $this->invoke([
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '99',
            '--interface-height' => '0', '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-pyramid-upright-alternate-center', $display);
        self::assertStringNotContainsString('too high', $display);
    }

    /**
     * **An unstated width limits nothing**, which is stated by the owner and is the whole of CVR-8: how wide a
     * generated scene comes out does not matter unless a parameter limiting the width is explicitly passed.
     *
     * It used to be limited whether or not anybody said so. `--max-width` defaulted to 3.70 m, so every generated
     * scene was solved against a stage nobody had asked for, and a rig too wide for it was refused for a reason that
     * came from the option's default rather than from the request.
     *
     * Asserted three ways, because a bound can leak back in at any of them: it must not reach the stack the compiler
     * re-solves, it must not reach the recorded command a replay runs, and — the one that proves the other two are
     * not merely cosmetic — the rig has to actually come out wider than the old default allowed.
     */
    public function testAnUnstatedWidthBoundsNothing(): void
    {
        $shared = ['--from' => self::OWN_GEAR, '--align' => ['center'], '--shape' => ['free'], '--dry-run' => true];

        $unbounded = $this->invoke($shared)->getDisplay();
        $bounded = $this->invoke($shared + ['--max-width' => '2.40'])->getDisplay();

        self::assertStringContainsString('id: stacked-free----upright-alternate-center', $unbounded);
        self::assertStringNotContainsString('max_width_m:', $unbounded, 'no bound reaches the re-solved stack');
        self::assertStringNotContainsString('--max-width=', $unbounded, 'and none reaches the recorded command');
        self::assertStringContainsString('max_width_m: 2.4', $bounded, 'a stated one still reaches it');

        // The one that proves the other three are not merely cosmetic: the same gear on a stated 2.40 m stage cannot
        // deal a row wider than that, and unstated it does.
        self::assertGreaterThan(
            2.40,
            max($this->rowWidths($unbounded)),
            'unbounded has to mean unbounded, not "bounded by something else"',
        );
        self::assertLessThanOrEqual(2.40, max($this->rowWidths($bounded)));
    }

    /**
     * Every row width the header reports, in metres.
     *
     * @return list<float>
     */
    private function rowWidths(string $display): array
    {
        preg_match_all('/^#\s+\d+\s+.*?([\d.]+) m wide$/m', $display, $rows);
        self::assertNotSame([], $rows[1], 'the header has to report a width per row');

        return array_map('floatval', $rows[1]);
    }

    /**
     * **Every generated scene that misses its own band says so in its own header**, which is the invariant that
     * replaced "every generated scene stands inside its band".
     *
     * The band stopped being a gate, so a file outside it is no longer a defect — a file outside it *in silence* is,
     * because the next reader would take a knowingly short wall for a solver bug. Read from the files rather than
     * from a sweep, so it also covers any scene left behind by a solver change, and each file is held to the bounds
     * it states rather than to 2–3 m.
     */
    public function testEveryGeneratedSceneOutsideItsBandSaysSo(): void
    {
        $files = glob(dirname(__DIR__, 2).'/scenes/generated/*.yaml') ?: [];
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $yaml = (string)file_get_contents($file);
            $name = basename($file);

            preg_match('/^\s*max_sub_height_m:\s*(\S+)/m', $yaml, $ceiling);
            preg_match_all('/^# Subs reach ([\d.]+) m against a ([\d.]+) m interface/m', $yaml, $walls);
            self::assertNotSame([], $walls[1], $name.' records no sub wall height');

            foreach ($walls[1] as $index => $height) {
                if ((float)$height + 1e-9 < (float)$walls[2][$index]) {
                    self::assertStringContainsString('m interface asked for', $yaml, $name.' is short and silent');
                }
                if ($ceiling !== [] && (float)$height > (float)$ceiling[1] + 1e-9) {
                    self::assertStringContainsString('m ceiling asked for', $yaml, $name.' is tall and silent');
                }
            }
        }
    }

    /** An unknown `--split` names the values there are, rather than falling back to one of them. */
    public function testAnUnknownSplitIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--split' => 'sideways', '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--split: unknown value 'sideways'", $tester->getDisplay());
        self::assertStringContainsString('allowed: by-count, by-type', $tester->getDisplay());
    }

    /**
     * `--split=by-type` needs at least one type per stack, and says so rather than writing an empty stack.
     */
    public function testSplitByTypeWithMoreStacksThanTypesSaysSo(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--stacks' => '9', '--split' => 'by-type',
            '--max-width' => '3.70', '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('5 device types cannot fill 9 stacks', $tester->getDisplay());
    }

    /**
     * How many rows each stack came out with, read off the header the writer prints.
     *
     * @return list<int>
     */
    private function rowsPerStack(string $display): array
    {
        preg_match_all('/^# main-\S+ — .*?, \d+ cabinets in (\d+) rows:$/m', $display, $matches);

        return array_map(intval(...), $matches[1]);
    }

    /** @return list<int> */
    private function cabinetsPerStack(string $display): array
    {
        preg_match_all('/^# main-\S+ — .*?, (\d+) cabinets in \d+ rows:$/m', $display, $matches);

        return array_map(intval(...), $matches[1]);
    }

    /**
     * The mirror-style axis is only swept where the orientation actually rolls something.
     *
     * {@see \App\Scene\Tier::mirrored} acts only on segments lying on a quarter turn, so with nothing rolled `centred`
     * comes out byte-identical to `alternate`. Sweeping the two axes independently made a third of every candidate a
     * duplicate by construction: 66 `centred` candidates, 0 written, 18 of them recognised as duplicates afterwards and
     * the other 48 refused on the same height and support grounds as their twin. Paired, the vacuous combinations cannot
     * be expressed at all.
     *
     * Asserted on the *ids offered*, not on the files written, because the point is the candidate that is never built
     * rather than the scene that was never any different. An id carries no orientation suffix only when it is `upright`,
     * so "every style suffix sits beside an orientation suffix" is the whole invariant.
     */
    public function testTheMirrorStyleAxisIsSweptOnlyWhereSomethingIsRolled(): void
    {
        // One alignment and one shape, which neither the orientation nor the mirror style depends on. The bare sweep
        // asserts the same thing eight times over, and `testTheBareCommandWritesScenesAcrossOwnersAndStackCounts` is
        // where that whole run is paid for once.
        $display = $this->invoke([
            '--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid'],
        ])->getDisplay();

        preg_match_all('/^\s*(?:skipped|id:)\s*(\S+)/m', $display, $matches);
        self::assertNotSame([], $matches[1]);

        // **Every axis in an id is a fixed-width field padded with dashes**, so `turned` reads as `turned--` and
        // `centred` as `centred---`. Collapsing runs of dashes first is what keeps this test about the pairing of two
        // axes rather than about how wide their columns happen to be — matching `-turned-centred-` literally pinned
        // the padding by accident and broke here the moment it arrived.
        $ids = array_map(static fn (string $id): string => (string)preg_replace('/-{2,}/', '-', $id), $matches[1]);
        $collapsed = (string)preg_replace('/-{2,}/', '-', $display);

        $vacuous = array_values(array_filter(
            $ids,
            static fn (string $id): bool => (str_contains($id, '-centred') || str_contains($id, '-column'))
                && !str_contains($id, '-turned-') && !str_contains($id, '-mixed-'),
        ));

        self::assertSame([], $vacuous, 'a mirror style was offered with nothing rolled to apply it to');

        // And the pairing is not merely absent — the rolled orientations do get all three styles.
        self::assertStringContainsString('-turned-centred-', $collapsed);
        self::assertStringContainsString('-turned-column-', $collapsed);
    }

    /**
     * An explicit `--mirror-style=centred` is honoured even with nothing rolled, because the caller asked for it by
     * name. Only the *default* narrows — a stated option is never second-guessed.
     */
    public function testAnExplicitCentredStyleIsHonouredWithNothingRolled(): void
    {
        $display = $this->invoke([
            '--dry-run' => true, '--orientation' => ['upright'], '--mirror-style' => ['centred'],
            '--align' => ['center'], '--shape' => ['pyramid'],
        ])->getDisplay();

        self::assertStringContainsString('-centred-', $display);
    }

    /**
     * **Laying the subs down is the largest single lever the sweep has**, and the tops stay standing whatever it does.
     *
     * Pinned as a property of the ids offered rather than as a scene count, which would be a second copy of whatever the
     * inventory currently happens to build. The two claims are the ones the axis exists for: a `-turned-` candidate is
     * offered at all, and no orientation ever puts a top on its side.
     */
    public function testTheSweepOffersTurnedRigsAndNeverRollsATop(): void
    {
        $display = $this->invoke(['--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid']])->getDisplay();

        self::assertStringContainsString('-turned-', $display);
        self::assertStringContainsString('-mixed-', $display);

        // Every rolled segment the writer names, against the tops there are. A top appears in these scenes constantly;
        // what may never appear is a top with a roll on it.
        // Unanchored, because a mixed row names several segments on one comment line and every one of them counts.
        preg_match_all('/(\S+) rolled \d+°/', $display, $rolled);
        foreach (array_unique($rolled[1]) as $id) {
            self::assertContains($id, [
                'flexy-folded-horn-hybrid', 'skram', 'gmss-iq-sub', 'gmss-nuke', 'gmss-wall-bass', 'gmss-mid-bass',
                'achenbach-18',
            ], $id.' is a top and was rolled');
        }
        self::assertNotSame([], $rolled[1], 'nothing was rolled at all, so the assertion above proves nothing');
    }

    /**
     * The inventory axis is every non-empty combination of owners, and the **pairs** are the ones that were missing.
     *
     * Borrowing gear between owners is something this repository supports on purpose, and until the combinations landed
     * the sweep jumped from one owner straight to all of them. The pair is not a curiosity either: `sdwa5-sepp` writes
     * more scenes than any single owner and more than `all`, because `sepp`'s six Achenbachs cannot stand alone and are
     * excellent under somebody else's tops.
     *
     * Narrowed hard on the other axes, because this test is about which *inventories* are offered and a bare sweep
     * would be the same assertion at eight times the runtime.
     */
    public function testTheSweepOffersEveryCombinationOfOwners(): void
    {
        $display = $this->invoke([
            '--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid'], '--orientation' => ['upright'],
        ])->getDisplay();

        // `rtrim` because the owner column is padded out to the widest label the gear list can make, so `gmss` reads
        // `gmss------` in a name. The padding is the point of the scheme and is not what this test is about.
        preg_match_all('/^\s*(?:skipped|id:)\s*stacked-([a-z0-9-]+?)-\d/m', $display, $matches);
        $inventories = array_values(array_unique(array_map(
            static fn (string $label): string => rtrim($label, '-'),
            $matches[1],
        )));
        sort($inventories);

        self::assertSame(['all', 'gmss', 'gmss-sdwa5', 'gmss-sepp', 'sdwa5', 'sdwa5-sepp', 'sepp'], $inventories);
    }

    /**
     * `--owner` narrows the inventory axis **without collapsing the sweep**, which is what separates it from `--from`.
     *
     * `--from` says "this rig at this width" and turns the width ladder and the stack-count sweep off with it;
     * `--owner=gmss --owner=sepp` says whose gear may be in the rig and leaves every other axis walking. Pinned on the
     * stack counts, which are the visible half of that.
     */
    public function testOwnerNarrowsTheInventoryWithoutCollapsingTheSweep(): void
    {
        $display = $this->invoke([
            '--dry-run' => true, '--owner' => ['sepp', 'gmss'], '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'],
        ])->getDisplay();

        preg_match_all('/^\s*(?:skipped|id:)\s*stacked-([a-z0-9-]+?)-(\d)/m', $display, $matches);

        // The owners in the specs' own order, whichever order they were typed in, so the file has one name.
        self::assertSame(['gmss-sepp'], array_values(array_unique(array_map(
            static fn (string $label): string => rtrim($label, '-'),
            $matches[1],
        ))));
        self::assertSame(['1', '2', '3'], array_values(array_unique($matches[2])), 'the stack counts still sweep');
    }

    /**
     * `--owner` still binds when another option has already collapsed the sweep.
     *
     * `--stacks=2` alone means "this rig, two stacks", and that path used to build from every speaker in the repository
     * whatever `--owner` said. Silently ignoring a stated option is the failure mode this whole command avoids
     * elsewhere, so the narrow path filters by owner too.
     *
     * **`free` rather than `pyramid`, and the shape was never the subject.** GMSS's four subs are 0.59 m to 1.200 m
     * wide, and once GEO-12 widened the search every pyramid arrangement in reach for them either overlaps or misses
     * the silhouette rule, so that one combination writes nothing at all. It is one of the rigs the CHANGELOG counts
     * as refused, and reading owner binding off a rig that has no good arrangement pinned an unrelated failure to this
     * test's name.
     */
    public function testOwnerStillBindsWhenAnotherOptionCollapsesTheSweep(): void
    {
        $display = $this->invoke([
            '--owner' => ['gmss'], '--stacks' => '1', '--align' => ['center'], '--shape' => ['free'],
            '--orientation' => ['upright'], '--dry-run' => true,
        ])->getDisplay();

        // Read off the resolved `--from` in the recorded line, which is where the narrowing has to land: an unsplit rig
        // writes its stack as the shorthand list of ids, so there is no `device:` key to assert on.
        self::assertStringContainsString('--from=gmss-wall-bass', $display);
        self::assertStringNotContainsString('flexy-folded-horn-hybrid', $display);
        self::assertStringNotContainsString('achenbach-18', $display);
    }

    /** Naming both `--owner` and `--from` is refused rather than one of them being quietly dropped. */
    public function testOwnerAndFromTogetherAreRefused(): void
    {
        $tester = $this->invoke(['--owner' => ['gmss'], '--from' => self::OWN_GEAR, '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());

        // Whitespace collapsed, because the console wraps the block mid-sentence.
        self::assertStringContainsString(
            'name one or the other',
            (string)preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    /** An unknown `--owner` names the owners there are rather than building everything instead. */
    public function testAnUnknownOwnerIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke(['--owner' => ['nobody'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        $wrapped = (string)preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString("--owner: unknown value 'nobody'", $wrapped);
        self::assertStringContainsString('allowed: gmss, sdwa5, sepp', $wrapped);
    }

    /** An unknown `--orientation` names the values there are rather than falling back to one of them. */
    public function testAnUnknownOrientationIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--orientation' => ['sideways'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--orientation: unknown value 'sideways'", $tester->getDisplay());

        // Whitespace collapsed, because the console wraps the block and puts the last value on the next line.
        $wrapped = (string)preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('allowed: upright, turned, mixed', $wrapped);
    }

    /**
     * A recorded command line names the **mode**, not the cabinets it resolved to.
     *
     * That is what keeps a replay correct across a spec change: `--orientation=turned` means "every sub", so a sub
     * measured tomorrow joins the rig the file describes, where a frozen list would rebuild yesterday's. The stated form
     * is only recorded where it is what the caller actually said.
     *
     * Read off the shipped files rather than from a run, the same way the stage-width test is: the recorded line is what
     * `build:all` replays, so a file that failed to carry the mode is the actual defect.
     *
     * **Asserted across the whole set rather than on two named files.** Naming this test's evidence by file name broke it
     * three times for reasons that had nothing to do with orientation, once per change to the id shape. The rule it is
     * really about holds for every generated scene, so every generated scene is where it is checked.
     */
    public function testARecordedLineNamesTheOrientationRatherThanTheCabinets(): void
    {
        $turned = 0;
        $upright = 0;
        foreach (glob(dirname(__DIR__, 2).'/scenes/generated/*.yaml') ?: [] as $file) {
            $yaml = (string)file_get_contents($file);
            $name = basename($file);

            if (str_contains($yaml, '--orientation=turned')) {
                ++$turned;
                // The mode is recorded, and the cabinets it resolved to live in the stack itself, which is where a
                // re-solve reads them from.
                self::assertStringNotContainsString('--roll-mirror=', $yaml, $name);
                self::assertStringContainsString('roll_mirror: 90.0', $yaml, $name);
            }

            if (str_contains($yaml, '--orientation=upright')) {
                ++$upright;
                self::assertStringNotContainsString('roll_mirror', $yaml, $name);
            }
        }

        // Both modes are actually represented, so a sweep that stopped writing one of them fails here rather than
        // passing an assertion loop that never ran.
        self::assertGreaterThan(0, $turned, 'no shipped scene records --orientation=turned');
        self::assertGreaterThan(0, $upright, 'no shipped scene records --orientation=upright');
    }

    /**
     * The fill order is decided by frequency, and only between two cabinets that **both** state one.
     *
     * Stated by the owner: the lowest and most powerful subs belong as low as the rig allows. The guard is the part
     * worth pinning, because it is the part an earlier frequency-first sort did not have — that version read a missing
     * passband as `INF`, fell back to `quantity × width` and put the 40 kg IQ subs under the 220 kg wall basses. Nine
     * of our ten speakers state no passband, so a rule that ranks on absence ranks almost everything on nothing.
     *
     * Both directions are asserted, since only the pair of them says the guard is a guard rather than an ordering that
     * happens to agree: two stated passbands beat the mass even when the mass disagrees, and one stated passband beats
     * nothing at all.
     */
    public function testTheFillOrderIsFrequencyFirstAndOnlyWhereBothCabinetsStateOne(): void
    {
        $order = (new \ReflectionMethod(SceneStackCommand::class, 'byFillOrder'))->invoke(null);

        $deep = SpecFactory::spec([
            'id' => 'light-and-deep',
            'physical' => ['weight_kg' => 10.0],
            'audio' => ['passband_hz' => ['low_hz' => 20, 'high_hz' => 100, 'provenance' => 'datasheet']],
        ]);
        $shallow = SpecFactory::spec([
            'id' => 'heavy-and-shallow',
            'physical' => ['weight_kg' => 200.0],
            'audio' => ['passband_hz' => ['low_hz' => 40, 'high_hz' => 100, 'provenance' => 'datasheet']],
        ]);
        $silent = SpecFactory::spec(['id' => 'heavy-and-silent', 'physical' => ['weight_kg' => 200.0]]);

        // Both state one, and they disagree with the mass by a factor of twenty. The frequency wins.
        self::assertLessThan(0, $order($deep, $shallow), 'a lighter, deeper cabinet belongs under a heavier one');

        // One of them says nothing, so there is no frequency to compare and the mass decides. Without the guard the
        // silent cabinet would sort above the deep one on a number it does not have.
        self::assertLessThan(0, $order($silent, $deep), 'a silent cabinet is placed by mass, not by its silence');
    }

    /**
     * **A written scene rebuilds the rig its own header describes**, which is the invariant the whole generator
     * rests on and the one that keeps breaking in a new field.
     *
     * The file states constraints rather than rows, so `scene:build` re-solves it from scratch. That only produces
     * the same rig if the file can express **every** input the command solved with, and `slide_slack_m` was the last
     * one it could not: `SceneStackCommand::stackFor()` gives a solo stack `INF`, meaning a badly-carried row may be
     * moved sideways to get it under something, and `Stack::fromReader()` had no key for it, so the rebuild got
     * `null` — "it may not move".
     *
     * **Measured on this exact rig before the key existed.** The command wrote a header describing four rows reaching
     * 1.860 m of subs, and `scene:build` on that file produced two rows reaching 0.660 m: a 23.5 m line of cabinet
     * with the tops 1340 mm below the interface, rendered and committed, whose own comment described a different rig.
     * GEO-11 is the same defect in the seating check, and {@see SceneStackCommand::probePlacement} states the rule
     * this test enforces — anything the solve reads has to be expressible on both sides.
     *
     * Asserted on the sub-wall height rather than on the row list because that is the one number both sides print in
     * the same words, and it is what separates the two answers by a factor of three.
     */
    public function testAWrittenSceneRebuildsToTheSubWallItsOwnHeaderReports(): void
    {
        $tester = $this->invoke([
            '--from' => [
                'gmss-wall-bass', 'gmss-mid-bass', 'skram', 'flexy-folded-horn-hybrid', 'gmss-nuke',
                'achenbach-18', 'gmss-iq-sub', 'tecnare-m2122', 'eighteensound-2way-15', 'gmss-turbo-top',
            ],
            '--orientation' => ['turned'], '--stacks' => '1', '--align' => ['center'], '--shape' => ['free'],
            '--mirror-style' => ['alternate'], '--id' => self::THROWAWAY_ID,
        ]);
        self::assertSame(0, $tester->getStatusCode());

        $written = glob(dirname(__DIR__, 2).'/scenes/generated/'.self::THROWAWAY_ID.'*.yaml') ?: [];
        self::assertCount(1, $written);
        $contents = (string)file_get_contents($written[0]);

        // A solo stack slides without bound, so the file has to say so. Written as `.inf` because `sprintf('%.4F')`
        // gives `INF`, which YAML reads as a word.
        self::assertStringContainsString('slide_slack_m: .inf', $contents);

        self::assertSame(
            1,
            preg_match('/the subs reach ([\d.]+) m against/', $contents, $header),
            'the header states no sub-wall height, so this test is checking nothing',
        );

        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }
        $result = (new SceneCompiler($devices))->compile(
            (new SceneLoader(dirname(__DIR__, 2).'/scenes'))->load($written[0]),
        );

        self::assertSame([], array_map(
            static fn ($v): string => $v->message,
            Violation::errorsIn($result['violations']),
        ));

        $rebuilt = array_values(array_filter(
            array_map(static fn ($v): string => $v->message, $result['violations']),
            static fn (string $message): bool => str_contains($message, 'the subs reach '),
        ));
        self::assertCount(1, $rebuilt, 'the rebuild reports no sub-wall height to compare against');
        self::assertStringContainsString(
            'the subs reach '.$header[1].' m against',
            $rebuilt[0],
            'the rebuilt rig is not the one the file describes',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    /**
     * **The sweep across processes says exactly what the sweep in one process says.**
     *
     * This is the property the whole of {@see \App\Process\Parallel} exists to preserve, and it is the one a fork
     * breaks first: candidates come back in completion order, a duplicate id wins a race, a worker that silently
     * dies shortens the list. Any of those shows up here as a differing display, because the display carries every
     * id, every refusal and every reason in order.
     *
     * Asserted on a narrow rig rather than the bare sweep, because the bare sweep is two minutes of work to prove a
     * statement about ordering that a rig of one owner makes just as well.
     */
    public function testTheSweepSaysTheSameThingInOneProcessAsInTwentyEight(): void
    {
        $shared = ['--owner' => ['gmss'], '--dry-run' => true];

        $serial = $this->invoke($shared + ['--jobs' => '1']);
        $parallel = $this->invoke($shared);

        self::assertSame(0, $serial->getStatusCode());
        self::assertSame(0, $parallel->getStatusCode());
        self::assertSame($serial->getDisplay(), $parallel->getDisplay());
        // And it is a sweep rather than a single rig, or the two paths would agree by never diverging.
        self::assertGreaterThan(1, preg_match_all('/^id: /m', $parallel->getDisplay()));
    }

    private function invoke(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new SceneStackCommand());

        $tester = new CommandTester($application->find('scene:stack'));
        $tester->execute($options);

        return $tester;
    }
}
