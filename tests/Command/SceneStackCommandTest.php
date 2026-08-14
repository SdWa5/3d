<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
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
        // ONE SHAPE NAMED, because this test is about the three alignments and `--shape` now multiplies them the same
        // way — both shapes are written by default, so leaving it open would make this assert 4 and stop saying
        // anything about alignment.
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--shape' => ['pyramid'], '--dry-run' => true,
        ]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(2, preg_match_all('/^id: /m', $display), 'center and stereo differ; block does not');
        self::assertStringContainsString('the same rig as stacked-center', $display);
        self::assertStringContainsString('id: stacked-stereo', $display);

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

        // More than one owner, and more than one stack count, or it is not a sweep.
        self::assertMatchesRegularExpression('/^id: \S*-sdwa5-\d/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-gmss-\d/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-\w+-1/m', $display);
        self::assertMatchesRegularExpression('/^id: \S*-\w+-2/m', $display);

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
            '--from' => self::STACKABLE, '--stacks' => '1',
            '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertSame(1, preg_match_all('/^id: /m', $display));
        self::assertStringContainsString('id: stacked-center', $display, 'no owner or stack-count suffix');
    }

    /** The fast path: one alignment and one shape named outright, exactly one scene. */
    public function testASingleAlignmentProducesExactlyOneScene(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--align' => ['block'], '--shape' => ['pyramid'], '--dry-run' => true,
        ]);

        self::assertSame(1, preg_match_all('/^id: /m', $tester->getDisplay()));
    }

    /**
     * Both shapes are written, and the pyramid is the one that keeps the plain id.
     *
     * The two answer opposite questions — the pyramid orders the fill for row *width* so the wall tapers and comes
     * out shorter, `free` keeps the deepest and heaviest cabinets on the floor and accepts a wall that widens as it
     * rises. On the GMSS cabinets that is 2.070 m against 3.240 for the same twelve boxes, which is worth being able
     * to look at both ways round.
     */
    public function testBothShapesAreWrittenAndThePyramidKeepsThePlainId(): void
    {
        $display = $this->invoke([
            '--from' => ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub', 'tecnare-m2122'],
            // 3.5 m, not the 3.0 m default: `free` comes out at 3.240 m here and the band would refuse it, and the
            // subject of this test is the two fill orders rather than which of them meets a ceiling.
            '--max-width' => '3.80', '--interface-height' => '0', '--max-sub-height' => '3.5',
            '--align' => ['center'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-center', $display);
        self::assertStringContainsString('id: stacked-free-center', $display);

        // The pyramid puts the six IQ subs on the floor for a 3.28 m base; `free` puts the two wall basses there,
        // which is 1.34 m and two rows more of stack.
        self::assertStringContainsString('6× gmss-iq-sub', $display);
        self::assertStringContainsString('2× gmss-wall-bass', $display);
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
            '--interface-height' => '0', '--max-sub-height' => '99',
            '--shape' => ['pyramid'], '--mirror-style' => ['alternate'], '--dry-run' => true];

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
        $tester = $this->invoke([
            '--max-width' => '5.0', '--interface-height' => '2.0', '--from' => self::OWN_GEAR,
            '--align' => ['center'], '--dry-run' => true,
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
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
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

    /** A single stack has no pair to mirror against, so it never claims one. */
    public function testASingleStackIsNeverMirrored(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertStringNotContainsString('mirror:', $tester->getDisplay());
    }

    public function testAStackCountBelowOneIsRejected(): void
    {
        $tester = $this->invoke(['--stacks' => '0', '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--stacks must be at least 1', $tester->getDisplay());
    }

    /**
     * The one that matters. A generator that emits a scene the compiler rejects is worse than no generator,
     * because the failure then surfaces later and further from its cause — so every candidate is compiled
     * before it is written, and this pins that the check is real by reading the cabinets back.
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
            // 12 Flexy + 6 Achenbach + 3 Tecnare + 2 2-ways. A generator that quietly dropped cabinets
            // would pass every other check in this file.
            self::assertCount(23, $result['placed'], basename($file).' lost cabinets');
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
        $shared = [
            '--from' => ['flexy-folded-horn-hybrid', 'tecnare-m2122'], '--stacks' => '2',
            '--max-width' => '3.70', '--align' => ['center'], '--dry-run' => true,
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
            // 3.5 m because this gear comes out at 3.040 m: a ceiling the rig misses is refused rather than written,
            // so a test about the ceiling *reaching the file* has to state one the rig meets.
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.5',
            '--interface-height' => '0', '--align' => ['center'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('max_sub_height_m: 3.5', $display);
    }

    /**
     * **A row is slid along its support rather than the rig being refused**, where nothing stands beside it.
     *
     * The GMSS cabinets are the case the stage-width ladder cannot help: four sub types and at most six of any one of
     * them means the row count is set by the types, so the wall is 3.34 m at every width from 3.70 to 6.00 m. Their one
     * arrangement inside the band packs the nukes and mid-bass into a single row — 2.42 m on a 1.89 m support, where
     * centred the outboard nuke lands on a fraction of itself and the whole rig was thrown away. Slid, it is carried.
     *
     * Pinned on the sub height rather than on the offset, because the height is what the rig is for and the offset is
     * how it got there.
     */
    public function testASoloStackSlidesARowRatherThanLosingTheRig(): void
    {
        $display = $this->invoke([
            '--from' => ['gmss-wall-bass', 'gmss-mid-bass', 'gmss-iq-sub', 'gmss-nuke', 'gmss-turbo-top'],
            '--max-width' => '3.70', '--stacks' => '1', '--align' => ['center'],
            '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-center', $display);
        self::assertStringContainsString('2× gmss-nuke + 1× gmss-mid-bass', $display, 'the packed row is the point');
        self::assertSame([2.84], $this->heights($display));
    }

    /**
     * **A rig whose sub wall misses the band is not written**, and the refusal says by how much.
     *
     * The two bounds existed long before this and were preferences: the solver reported a miss as a warning and built
     * the rig anyway, which is right for a scene somebody wrote and wrong for one this command generates. Measured
     * across the 54 scenes that shipped before it bound, only 16 had every stack between 2 and 3 m — the rest included
     * a 5.73 m wall and a 0.60 m one, tops firing at knee height.
     *
     * Asserted on the numbers rather than on the refusal alone, because "no workable arrangement" for a rig that is
     * 40 mm too tall is what sends somebody hunting for a geometry fault.
     */
    public function testARigThatMissesTheSubHeightBandIsRefusedWithTheMeasurement(): void
    {
        $display = $this->invoke([
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.0',
            '--interface-height' => '0', '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('3.040 m against the 3.000 m ceiling', $display);
        self::assertStringContainsString('40 mm too high', $display);
        self::assertStringNotContainsString('id: stacked-center', $display);
    }

    /**
     * The floor is the other half, and it refuses the opposite mistake: a wall too short to get the tops over a
     * standing crowd. Two Achenbachs cannot make a 2 m wall however they are stacked, so the rig is not written.
     */
    public function testAWallTooShortToClearTheInterfaceIsRefused(): void
    {
        $display = $this->invoke([
            '--from' => ['achenbach-18', 'eighteensound-2way-15'], '--max-width' => '3.70',
            '--interface-height' => '2.0', '--align' => ['center'], '--shape' => ['pyramid'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('would fire below head height', $display);
        self::assertStringNotContainsString('id: stacked-center', $display);
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

        self::assertStringContainsString('id: stacked-center', $display);
        self::assertStringNotContainsString('too high', $display);
    }

    /**
     * **A rig that misses the band on the default stage is moved onto one that fits it**, rather than skipped.
     *
     * This is the half of the band that adds scenes instead of removing them. A sub wall gets shorter as the stage
     * gets wider, so a rig over the ceiling at 3.70 m is often not an impossible rig but a rig on the wrong stage:
     * both systems' gear across two stacks is 3.16 m of subs at 3.70 m and 2.83 m at 4.40 m, and only the second is
     * one you would build. The width the sweep settled on is written into the recorded command, because a replay that
     * inherited the default would rebuild the rig that missed.
     *
     * Read off the file the sweep wrote rather than by running it again: the sweep is the slowest thing in this suite
     * and the recorded line is the durable evidence — it is what a replay uses, so a width that failed to reach it
     * would be the actual defect.
     */
    public function testTheSweepMovesARigOntoAWiderStageRatherThanSkippingIt(): void
    {
        $yaml = (string)file_get_contents(dirname(__DIR__, 2).'/scenes/generated/stacked-all-2-center.yaml');

        self::assertMatchesRegularExpression('/--max-width=4\.4\b/', $yaml, 'the wider stage has to be recorded');
        self::assertStringContainsString('max_width_m: 4.4', $yaml, 'and reach the stack the compiler re-solves');
    }

    /**
     * **Every generated scene stands inside the band its own file asks for**, which is the invariant the band exists
     * to hold and the one a stale file would break silently.
     *
     * Read from the files rather than from a sweep, so it also covers the turned siblings `build:all` writes and any
     * scene left behind by a solver change. Each file states its own bounds, so nothing here assumes 2–3 m: a scene
     * that asks for something else is held to what it asks for.
     */
    public function testEveryGeneratedSceneStandsInsideItsOwnBand(): void
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
                self::assertGreaterThanOrEqual((float)$walls[2][$index], (float)$height + 1e-9, $name);
                if ($ceiling !== []) {
                    self::assertLessThanOrEqual((float)$ceiling[1] + 1e-9, (float)$height, $name);
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
     * The mirror-style axis is only swept when something is actually rolled.
     *
     * {@see \App\Scene\Tier::mirrored} acts only on segments lying on a quarter turn, so with no `--roll-mirror` it is a
     * no-op and `upright` comes out byte-identical to `alternate`. Sweeping it anyway doubled every default run's
     * candidates for no possible output: 66 `upright` candidates, 0 written, 18 of them recognised as duplicates and the
     * other 48 refused on the same height and support grounds as their twin.
     *
     * Asserted on the *ids offered*, not on the files written, because the point is the candidate that is never built
     * rather than the scene that was never any different.
     */
    public function testTheMirrorStyleAxisIsSweptOnlyWhenSomethingIsRolled(): void
    {
        $plain = $this->invoke(['--dry-run' => true])->getDisplay();
        self::assertStringNotContainsString('-centred-', $plain);

        $rolled = $this->invoke([
            '--dry-run' => true,
            '--roll-mirror' => ['flexy-folded-horn-hybrid'],
            '--from' => ['flexy-folded-horn-hybrid', 'tecnare-m2122'],
        ])->getDisplay();
        self::assertStringContainsString('-centred-', $rolled);
    }

    /**
     * An explicit `--mirror-style=centred` is honoured even with nothing rolled, because the caller asked for it by
     * name. Only the *default* narrows — a stated option is never second-guessed.
     */
    public function testAnExplicitCentredStyleIsHonouredWithNothingRolled(): void
    {
        $display = $this->invoke(['--dry-run' => true, '--mirror-style' => ['centred']])->getDisplay();

        self::assertStringContainsString('-centred-', $display);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invoke(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new SceneStackCommand());

        $tester = new CommandTester($application->find('scene:stack'));
        $tester->execute($options);

        return $tester;
    }
}
