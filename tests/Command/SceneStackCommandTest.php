<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\SweepAxes;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;

/**
 * `scene:stack` — the sweep itself, the shapes and alignments, and every refusal.
 *
 * What is left here after the axes moved out: which rigs the bare command decides to write at all, that
 * naming a value narrows one axis rather than collapsing the sweep, the shapes, the fill order, where a swept
 * scene is filed, and the refusals for an option nobody can act on. Plus the one test that is about none of
 * that and matters more than any of it, namely that twenty-eight processes say the same thing as one.
 *
 * The axes have suites of their own, and each of them is named after what it varies:
 * {@see SceneStackMirrorTest}, {@see SceneStackSystemsTest}, {@see SceneStackFeasibilityTest} and
 * {@see SceneStackBringsTest}. The shared fixture and the cleanup live in {@see SceneStackTestCase}, because
 * these tests write into the real `scenes/` directory and a second copy of that rule would be a second
 * chance to get it wrong.
 */
final class SceneStackCommandTest extends SceneStackTestCase
{
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
            '--max-width' => '3.70', '--systems' => ['pooled'], '--low-end' => ['low'], '--stacks' => '1', '--from' => self::STACKABLE,
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--dry-run' => true,
        ]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(2, preg_match_all('/^id: /m', $display), 'center and stereo differ; block does not');
        self::assertStringContainsString('the same rig as stacked-1-pooled--------pyramid-upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-stereo', $display);

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
        $tester = $this->invoke(['--low-end' => ['low'], '--dry-run' => true]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertGreaterThan(8, preg_match_all('/^id: /m', $display), 'the bare command must produce a set');

        // More than one stack count, or it is not a sweep. **The owner is no longer in the id** — it is the folder
        // the scene is written into, and the recorded `--into` is where a dry run says so. This used to assert one
        // `-sdwa5-` id and one `-gmss-` id, which was the powerset default rather than a property of a sweep.
        self::assertMatchesRegularExpression('/^id: stacked-1-/m', $display);
        self::assertMatchesRegularExpression('/^id: stacked-2-/m', $display);
        self::assertStringContainsString('--into=sdwa5-sepp', $display);
        self::assertDoesNotMatchRegularExpression('/^id: \S*sdwa5/m', $display, 'the inventory is the folder now');

        // Every refusal carries a reason — a sweep that drops candidates silently reads as "that is all there is".
        foreach (explode("\n", $display) as $line) {
            if (str_contains($line, 'skipped')) {
                self::assertStringContainsString(' — ', $line, 'a skipped candidate must say why');
            }
        }
    }

    /**
     * **Naming a value on an axis switches the other values of that axis off. It does not switch the sweep off.**.
     *
     * That is SWP-3's first ask and it used to be true of five axes and false of three: `--from`, `--stacks` and
     * `--per-owner` were read as "the caller has one specific rig in mind" and collapsed the whole cross product to
     * a single candidate, so **"sweep everything, but only two stacks" could not be asked for.** Now every option
     * narrows one axis, and a caller who wants exactly one scene names one value on every axis — which is what a
     * replay does, and why `build:all` still rewrites one file per recorded line.
     */
    public function testNamingAValueNarrowsThatAxisAndLeavesTheRestSweeping(): void
    {
        // Every axis named: one rig, and its name carries the value chosen on each of them.
        $one = $this->invoke([
            '--systems' => ['pooled'], '--low-end' => ['low'], '--from' => self::STACKABLE, '--stacks' => '1', '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--mirror-style' => ['alternate'],
            '--dry-run' => true,
        ])->getDisplay();

        self::assertSame(1, preg_match_all('/^id: /m', $one));
        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-center', $one);

        // The stack count alone: every other axis keeps walking, which is the thing that could not be asked for.
        $narrowed = $this->dryRun(['--owner' => ['gmss'], '--stacks' => '2', '--low-end' => ['low']])->getDisplay();
        $swept = $this->dryRun(['--owner' => ['gmss'], '--low-end' => ['low']])->getDisplay();

        $count = preg_match_all('/^id: /m', $narrowed);
        self::assertGreaterThan(1, $count, 'naming the stack count still collapsed the sweep');
        self::assertLessThan(preg_match_all('/^id: /m', $swept), $count, 'and it narrowed nothing');
        self::assertSame(0, preg_match_all('/^id: stacked-[13]-/m', $narrowed), 'only the stated stack count');
    }

    /** The fast path: one alignment, one shape and one orientation named outright, exactly one scene. */
    public function testASingleAlignmentProducesExactlyOneScene(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--systems' => ['pooled'], '--low-end' => ['low'], '--stacks' => '1', '--from' => self::STACKABLE, '--align' => ['block'],
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
            '--systems' => ['pooled'], '--stacks' => '1', '--from' => ['wall-bass', 'mid-bass', 'iq-sub', 'tecnare-m2122'],
            // 3.5 m, not the 3.0 m default: `free` comes out at 3.240 m here and the band would refuse it, and the
            // subject of this test is the two fill orders rather than which of them meets a ceiling.
            '--max-width' => '3.80', '--interface-height' => '0', '--max-sub-height' => '3.5',
            // One orientation, so the five scenes below are the five shapes rather than shapes times orientations.
            '--align' => ['center'], '--orientation' => ['upright'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------free----upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------v-------upright-alternate-center', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------tower---upright-alternate-center', $display);
        self::assertStringContainsString('skipped stacked-1-pooled--------mixed---upright-alternate-center', $display);
        self::assertCount(5, SweepAxes::shapes([]));

        // The pyramid puts the IQ subs on the floor, which is the widest row they can make; `free` puts the two wall
        // basses there, which is 1.34 m and two rows more of stack; `v` puts the single mid-bass there at 1.20 m,
        // which is the narrowest floor of the three because everything above it has to be wider.
        // The order is {@see StackShape::cases()}, so 0 is the pyramid, 1 is free and 2 is v.
        preg_match_all('/^#\s+1\s+(\S.*?)\s{2,}[\d.]+ m wide$/m', $display, $bottomRows);
        self::assertCount(4, $bottomRows[1], 'mixed duplicates the pyramid on this inventory');
        self::assertStringContainsString('iq-sub', $bottomRows[1][0], 'the pyramid stands on the IQ subs');
        self::assertStringContainsString('wall-bass', $bottomRows[1][1], 'and free on the wall basses');
        self::assertStringContainsString('mid-bass', $bottomRows[1][2], 'and v on the single mid-bass');
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
        // **The three owners are named, because silence stopped meaning "every owner".** A bare `--per-owner` builds
        // the default inventory now, which is ours and Sepp's — two stacks, and this test is about which of three
        // unequal ones takes the middle.
        // **One stack per system and one scene**, which under narrowing means naming the stack count too: three
        // systems side by side is `--stacks=1`, and leaving it open sweeps 1, 2 and 3 of them.
        $shared = ['--per-owner' => true, '--owner' => ['gmss', 'innschleife', 'sdwa5', 'sepp'], '--stacks' => '1',
            '--max-width' => '3.70', '--gap' => '0.05', '--interface-height' => '0', '--max-sub-height' => '99',
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--mirror-style' => ['alternate'],
            '--low-end' => ['low'], '--dry-run' => true];

        $mono = $this->heights($this->invoke($shared + ['--align' => ['center']])->getDisplay());
        self::assertCount(3, $mono);
        self::assertSame(max($mono), $mono[1], 'mono: the tallest stack takes the middle');

        // **The stereo half is deliberately not asserted here, because no multi-stack stereo rig survives the checks
        // yet** — the tops-spread envelope refuses the narrow supports, and every generated scene is mono. Asserting
        // it would mean asserting on a refusal. `byHeight()` mirrors the mono order for stereo and the ordering is
        // pinned by the mono case; what is missing is a rig to see it on, which is a TODO rather than a test.
    }

    /**
     * A stated `--order` puts the systems where it says, whatever their heights.
     *
     * The height rule is a good default precisely because nobody had said where the stacks go. Somebody saying so
     * is a different kind of fact, and it has to win — a stage plan that reshuffles itself when one wall grows
     * 380 mm is not a stage plan. The `--low-end` axis is what exposed this in the shipped tree: two variants of
     * one `next-event` rig differing in nothing but that flag stood `innschleife | psl | ours` and
     * `ours | psl | innschleife`, because moving the SKRAMs changed which wall was tallest.
     */
    public function testAStatedOrderPutsTheSystemsWhereItSays(): void
    {
        $shared = ['--per-owner' => true, '--owner' => ['gmss', 'innschleife', 'sdwa5', 'sepp'], '--stacks' => '1',
            '--max-width' => '3.70', '--gap' => '0.05', '--interface-height' => '0', '--max-sub-height' => '99',
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--mirror-style' => ['alternate'],
            '--low-end' => ['low'], '--align' => ['center'], '--dry-run' => true];

        self::assertSame(
            ['ours', 'gmss', 'innschleife'],
            $this->labels($this->invoke($shared + ['--order' => ['ours,gmss,innschleife']])->getDisplay()),
        );

        // The reverse, so the assertion is about the order rather than about one arrangement the heights happen
        // to produce anyway.
        self::assertSame(
            ['innschleife', 'gmss', 'ours'],
            $this->labels($this->invoke($shared + ['--order' => ['innschleife', 'gmss', 'ours']])->getDisplay()),
        );
    }

    /**
     * **A system's stacks stay together and the systems run left to right**, which is the multi-stack case.
     *
     * `--stacks=2` labels the blocks `ours-1`, `ours-2`, `psl-1` and so on, so an order naming systems matched
     * nothing and 533 scenes kept the height rule — which mirrors each pair about the centre and puts `ours` in
     * the middle, the opposite of an order beginning with it. Stated by the owner: strictly left to right,
     * grouped. Equal ranks hold their relative order under PHP's stable sort, which is what keeps a pair adjacent.
     */
    public function testEachSystemsStacksStayTogetherInTheStatedOrder(): void
    {
        $display = $this->invoke([
            '--owner' => ['innschleife', 'psl', 'sdwa5', 'sepp'], '--systems' => ['systems-apart'],
            '--stacks' => '2', '--max-width' => '3.70', '--gap' => '0.05', '--interface-height' => '0',
            '--max-sub-height' => '99', '--shape' => ['free'], '--orientation' => ['upright'],
            '--mirror-style' => ['alternate'], '--low-end' => ['low'], '--align' => ['center'],
            '--order' => ['ours,psl,innschleife'], '--dry-run' => true,
        ])->getDisplay();

        $labels = $this->labels($display);
        self::assertNotEmpty($labels, 'the rig has to stand up for this to say anything');

        // Collapsed to one entry per run: grouped means each system appears as a single run.
        $runs = [];
        foreach ($labels as $label) {
            if ([] === $runs || end($runs) !== $label) {
                $runs[] = $label;
            }
        }

        self::assertSame(['ours', 'psl', 'innschleife'], $runs);
    }

    /**
     * **An order naming none of the stacks leaves the height rule alone**, which is the `pooled` case.
     *
     * `--order` states where *systems* go, and a pooled rig has no systems — its stacks are labelled `1`, `2`, `3`.
     * Returning early on any non-empty order put every pooled rig in solve order and skipped the height rule, a
     * silent geometry change in the one mode the option cannot be about. Sweeping `next-event` with
     * `--order=ours,psl,innschleife` moved 8 pooled ids that way, five of them across the possible/impossible line.
     */
    public function testAnOrderThatNamesNoneOfTheStacksLeavesTheHeightRuleInCharge(): void
    {
        $shared = ['--owner' => ['gmss', 'innschleife', 'sdwa5', 'sepp'], '--systems' => ['pooled'],
            '--stacks' => '3', '--max-width' => '3.70', '--gap' => '0.05', '--interface-height' => '0',
            '--max-sub-height' => '99', '--shape' => ['pyramid'], '--orientation' => ['upright'],
            '--mirror-style' => ['alternate'], '--low-end' => ['low'], '--align' => ['center'],
            '--dry-run' => true];

        $without = $this->heights($this->invoke($shared)->getDisplay());
        $with = $this->heights($this->invoke($shared + ['--order' => ['ours,psl,innschleife']])->getDisplay());

        self::assertCount(3, $without);
        self::assertSame($without, $with, 'a system order says nothing about a pooled rig and must not move it');
        self::assertSame(max($without), $without[1], 'and the height rule still has the tallest in the middle');
    }

    /**
     * The system label of each stack, left to right, off the placement ids the writer prints.
     *
     * @return list<string>
     */
    private function labels(string $display): array
    {
        preg_match_all('/^  - id: main-([a-z0-9-]+?)(?:-\d+)?$/m', $display, $matches);

        return $matches[1];
    }

    /** An unknown `--shape` names the values there are rather than falling back to one. */
    public function testAnUnknownShapeIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::STACKABLE, '--max-width' => '3.70', '--shape' => ['wedge'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--shape: unknown value 'wedge'", $tester->getDisplay());
        self::assertStringContainsString('allowed: pyramid, free, v, tower, mixed', preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--stacks' => '1', '--systems' => ['pooled'],
            '--id' => self::THROWAWAY_ID, '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame([], self::throwaway());
    }

    /**
     * Generated files live next to hand-written ones, so clobbering one has to be asked for. This is the
     * test that stops the command eating a scene somebody spent an afternoon commenting.
     *
     * One stack, pooled, because the refusal is about a file being there and any written rig proves it. The open
     * stack count made this three sweeps of 89 rigs and 2 min 10 s, measured in TOOL-22.
     */
    public function testAnExistingSceneIsRefusedWithoutForce(): void
    {
        $args = [
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--stacks' => '1', '--systems' => ['pooled'],
            '--align' => ['block'], '--id' => self::THROWAWAY_ID,
        ];
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
     *
     * The limit is checked only after every candidate is solved, so the sweep is kept to one pooled stack. Any count
     * above zero proves the refusal.
     */
    public function testExceedingMaxScenesIsRefusedRatherThanTruncated(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--stacks' => '1', '--systems' => ['pooled'],
            '--max-scenes' => '0', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--max-scenes=0', $tester->getDisplay());
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
            '--max-width' => '3.70', '--systems' => ['pooled'], '--stacks' => '1', '--from' => self::STACKABLE, '--id' => self::THROWAWAY_ID,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        // Under `generated/`, which is the one thing the layout rule turns on: everything derived from a scene
        // takes its subdirectory from where the scene itself sits, so a generated rig can never overwrite the
        // build output of a hand-written one that shares its id.
        $written = self::throwaway();
        self::assertNotSame([], $written);
        self::assertSame([], glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: [], 'nothing in scenes/ itself');

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
            preg_match_all('/^#\s+\*\s+([a-z0-9-]+): LEFT OUT/m', (string) file_get_contents($file), $omitted);
            $missing = array_sum(array_map(
                static fn (string $id): int => $devices[$id]->quantity,
                array_unique($omitted[1]),
            ));

            self::assertCount(23 - $missing, $result['placed'], basename($file).' lost cabinets it did not name');
        }
    }

    public function testAnUnknownAlignmentIsRejected(): void
    {
        $tester = $this->invoke(['--align' => ['blok'], '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("unknown value 'blok'", $tester->getDisplay());
    }

    public function testAnUnknownDeviceIsRejected(): void
    {
        $tester = $this->invoke(['--from' => ['nope'], '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("Unknown device 'nope'", $tester->getDisplay());
    }

    /**
     * A bare sweep builds **one** inventory, and it is our gear and Sepp's.
     *
     * **This test used to assert the opposite and the replacement is the point.** The inventory axis was every
     * non-empty combination of owners — seven of them for three owners, which read as generosity and was where the
     * finding came from that `sdwa5-sepp` writes more scenes than any single owner. Five owners make that powerset 31
     * inventories, 26 of them rigs nobody will ever build, and the sweep goes past its own fuse before it writes
     * anything. Stated by the owner: the sweep is always run against a subset, and the default subset is the pair.
     *
     * So the finding is kept as the default rather than as an enumeration, and the other inventories are one
     * `--owner` away. Asserted on the recorded `--into`, which is where the inventory lives now — a directory under
     * `scenes/generated/` rather than a dash-padded field in every file name inside it.
     *
     * Narrowed hard on the other axes, because this test is about which *inventory* is offered and a bare sweep would
     * be the same assertion at eight times the runtime.
     */
    public function testABareSweepBuildsTheDefaultInventoryAlone(): void
    {
        $display = $this->invoke([
            '--low-end' => ['low'], '--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid'], '--orientation' => ['upright'],
        ])->getDisplay();

        preg_match_all('/--into=([a-z0-9-]+)/', $display, $matches);
        $inventories = array_values(array_unique($matches[1]));

        self::assertSame(['sdwa5-sepp'], $inventories);
        self::assertSame(SweepAxes::DEFAULT_OWNERS, ['sdwa5', 'sepp'], 'the default moved and this test did not');
    }

    /**
     * A swept scene is written **into its inventory's folder**, and its name does not repeat the inventory.
     *
     * The two halves are one rule: SWP-3 says an axis value appears in the path or in the name and never in both,
     * and the system name inside a folder named after the system said it 271 times over. Asserted on a real write
     * rather than on a dry run, because the directory is the thing under test — a dry run prints the YAML and never
     * touches the tree.
     *
     * Narrowed to one point on every other axis so this writes one file, and cleaned up in `finally` so a failure
     * cannot leave a stray behind for the shipped-scene sweep to trip over.
     */
    public function testASweptSceneGoesIntoItsInventorysFolderAndDropsItFromTheName(): void
    {
        $generated = dirname(__DIR__, 2).'/scenes/generated/sepp';
        $before = glob($generated.'/zz-test-*.yaml') ?: [];
        self::assertSame([], $before, 'a previous run left a stray behind');

        try {
            $tester = $this->invoke([
                '--owner' => ['sepp'], '--align' => ['center'], '--shape' => ['pyramid'],
                '--orientation' => ['turned'], '--mirror-style' => ['alternate'], '--systems' => ['pooled'],
                '--id' => 'zz-test-into', '--force' => true,
            ]);

            self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

            $written = glob($generated.'/zz-test-into-*.yaml') ?: [];
            self::assertNotSame([], $written, 'nothing landed in scenes/generated/sepp/');
            foreach ($written as $file) {
                self::assertStringNotContainsString('sepp', basename($file), 'the folder already says whose gear it is');
                // The recorded line has to carry the folder, or a replay lands in scenes/generated/ itself.
                self::assertStringContainsString('--into=sepp', (string) file_get_contents($file));
            }
        } finally {
            foreach (glob($generated.'/zz-test-*.yaml') ?: [] as $file) {
                unlink($file);
            }
        }
    }

    /** `--into` refuses anything that is not a directory name, rather than creating one. */
    public function testAnIntoThatIsNotADirectoryNameIsRefused(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--into' => '../escape', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('is not a directory name', $tester->getDisplay());
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
            '--low-end' => ['low'], '--dry-run' => true, '--owner' => ['sepp', 'gmss'], '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'],
        ])->getDisplay();

        preg_match_all('/^\s*(?:skipped|id:)\s*stacked-(\d)/m', $display, $matches);

        // **The inventory is read off the folder rather than off the id**, because that is where it went in 0.98.0.
        // The label is still the owners in the specs' own order whichever order they were typed in, so the rig has
        // one name — that half of the promise is unchanged and is what this line pins.
        preg_match_all('/--into=(\S+)/', $display, $folders);
        self::assertSame(['gmss-sepp'], array_values(array_unique($folders[1])));
        self::assertSame(['1', '2', '3'], array_values(array_unique($matches[1])), 'the stack counts still sweep');
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
            '--orientation' => ['upright'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        // Read off the resolved `--from` in the recorded line, which is where the narrowing has to land: an unsplit rig
        // writes its stack as the shorthand list of ids, so there is no `device:` key to assert on.
        self::assertStringContainsString('--from=wall-bass', $display);
        self::assertStringNotContainsString('flexy-folded-horn-hybrid', $display);
        self::assertStringNotContainsString('achenbach-18', $display);
    }

    /** Naming both `--owner` and `--from` is refused rather than one of them being quietly dropped. */
    public function testOwnerAndFromTogetherAreRefused(): void
    {
        $tester = $this->invoke(['--owner' => ['gmss'], '--from' => self::OWN_GEAR, '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());

        // Whitespace collapsed, because the console wraps the block mid-sentence.
        self::assertStringContainsString(
            'name one or the other',
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    /** An unknown `--owner` names the owners there are rather than building everything instead. */
    public function testAnUnknownOwnerIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke(['--owner' => ['nobody'], '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        $wrapped = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString("--owner: unknown value 'nobody'", $wrapped);
        self::assertStringContainsString('allowed: gmss, innschleife, psl, sdwa5, sepp', $wrapped);
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
        $order = \App\Spec\FillOrder::byFillOrder();

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
     * **The sweep across processes says exactly what the sweep in one process says.**.
     *
     * This is the property the whole of {@see \App\Process\Parallel} exists to preserve, and it is the one a fork
     * breaks first: candidates come back in completion order, a duplicate id wins a race, a worker that silently
     * dies shortens the list. Any of those shows up here as a differing display, because the display carries every
     * id, every refusal and every reason in order.
     *
     * Asserted on a narrow rig rather than the bare sweep, because the bare sweep is two minutes of work to prove a
     * statement about ordering that a rig of one owner makes just as well.
     *
     * **And on one pooled upright stack of that owner rather than all of its rigs**, because the serial half is one
     * core doing the whole sweep. Over every `gmss` rig that was 146 ids and 2 min 50 s, the slowest case in the
     * suite when TOOL-22 measured it. The narrow sweep still writes 9 rigs in four shapes, collapses 6 duplicates and
     * notes 3 misses, so the order, the deduplication and the reasons all have something to get wrong.
     */
    public function testTheSweepSaysTheSameThingInOneProcessAsInTwentyEight(): void
    {
        $shared = [
            '--owner' => ['gmss'], '--stacks' => '1', '--systems' => ['pooled'], '--orientation' => ['upright'],
            '--low-end' => ['low'], '--dry-run' => true,
        ];

        $serial = $this->invoke($shared + ['--jobs' => '1']);
        $parallel = $this->invoke($shared);

        self::assertSame(0, $serial->getStatusCode());
        self::assertSame(0, $parallel->getStatusCode());
        self::assertSame($serial->getDisplay(), $parallel->getDisplay());
        // And it is a sweep rather than a single rig, or the two paths would agree by never diverging.
        self::assertGreaterThan(1, preg_match_all('/^id: /m', $parallel->getDisplay()));
    }
}
