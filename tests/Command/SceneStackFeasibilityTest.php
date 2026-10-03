<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;

/**
 * `scene:stack` — heights, bands, widths, and the rig that does not stand up.
 *
 * CVR-7 is the rule the whole group turns on, and it is stated by the owner: the sub/top interface height is
 * an optimisation problem rather than a hard constraint, so a wall that puts the tops below or above head
 * height is **not** a reason to refuse a rig. A missed band is therefore written onto the scene as a
 * measurement, and 551 of the sweep's refusals disappeared when that changed.
 *
 * The same holds for the stage width, which bounds nothing unless it is stated. What is still refused is
 * geometry: a rig with no arrangement at all, and a request nobody can act on — and those two exit
 * differently on purpose, because "none of these rigs stands up" is a different answer to a different
 * question than "that is not a rig I can read".
 */
final class SceneStackFeasibilityTest extends SceneStackTestCase
{
    /**
     * **Every written scene declares which side of the feasibility axis it is on**, and no name is allowed to be
     * silent about it.
     *
     * This is the sixth axis of SWP-1 and the odd one out among them: the other five are things a caller asks for,
     * this one is the solver's answer. Writing it anyway is the same argument that took `pyramid`, `upright` and
     * `alternate` out of hiding in 0.79.0 — a name with a gap in it says a value was left out, never which one.
     * Stated by the owner: treat it like the other axis.
     */
    public function testEveryWrittenSceneSaysWhetherItStandsUp(): void
    {
        $display = $this->dryRun(['--owner' => ['gmss'], '--low-end' => ['low']])->getDisplay();

        preg_match_all('/^id: (\S+)$/m', $display, $matches);
        self::assertNotSame([], $matches[1]);

        foreach ($matches[1] as $id) {
            self::assertMatchesRegularExpression(
                '/-(im)?possible$/',
                $id,
                $id.' does not say which side of the feasibility axis it is on',
            );
        }
    }

    /**
     * **A rig that does not stand up is written rather than refused, and it says so in its own header.**.
     *
     * The whole of CVR-5. "A `turbo-top` would stand at 0.660 m with nothing under it across x" took a debug
     * dump, two probes and a corrected coordinate mapping to understand; the same rig as a picture, with that
     * cabinet caged in red, says it at a glance. The two checks that name a cabinet therefore stopped refusing and
     * started reporting.
     */
    public function testARigThatDoesNotStandUpIsWrittenWithTheReasonInItsHeader(): void
    {
        // GMSS under Sepp's tops in two mixed stacks, pinned on every axis. GEO-13 made every gmss rig stand up, and the
        // group spread of 0.132.0 did the same for our own stereo rigs, so neither has one left to show.
        $display = $this->invoke([
            '--orientation' => ['mixed'], '--systems' => ['pooled'],
            '--from' => ['wall-bass', 'mid-bass', 'nuke', 'achenbach-18', 'iq-sub', 'eighteensound-2way-15', 'turbo-top'],
            '--stacks' => '2', '--align' => ['center'], '--low-end' => ['low'], '--shape' => ['pyramid'],
            '--mirror-style' => ['alternate'], '--dry-run' => true,
        ])->getDisplay();

        self::assertMatchesRegularExpression('/^id: \S+-impossible$/m', $display, 'no impossible rig was written');
        self::assertStringContainsString('THIS RIG DOES NOT STAND UP', $display);
        self::assertStringContainsString('with nothing under it', $display);
    }

    /**
     * **What is still a refusal after CVR-5, and why the line is where it is.** A rig with no arrangement at all has
     * no geometry to look at, so painting it red is not an option — there is nothing to paint. Only the checks that
     * name a *cabinet* moved.
     */
    public function testARigWithNoArrangementAtAllIsStillRefusedRatherThanDrawn(): void
    {
        $tester = $this->invoke([
            '--from' => ['flexy-folded-horn-hybrid'], '--max-width' => '0.2', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(SceneStackCommand::NOTHING_TO_WRITE, $tester->getStatusCode());
        self::assertStringNotContainsString('-impossible', $tester->getDisplay());
    }

    /**
     * **An unbuildable rig and an unusable request are both "nothing written" and must not share an exit code.**.
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
            '--from' => ['flexy-folded-horn-hybrid'], '--max-width' => '0.2', '--low-end' => ['low'], '--dry-run' => true,
        ]);
        self::assertSame(SceneStackCommand::NOTHING_TO_WRITE, $unbuildable->getStatusCode());
        self::assertStringContainsString('No workable arrangement', $unbuildable->getDisplay());

        // Unusable request: the same empty candidate list, reached by asking for something incoherent.
        $unusable = $this->invoke([
            '--from' => ['flexy-folded-horn-hybrid'], '--split' => 'sideways', '--low-end' => ['low'], '--dry-run' => true,
        ]);
        self::assertSame(SceneStackCommand::FAILURE, $unusable->getStatusCode());
    }

    /** `--max-sub-height` reaches the written stack, so the scene keeps asking for it on every rebuild. */
    public function testTheSubHeightCeilingIsWrittenIntoTheScene(): void
    {
        $display = $this->invoke([
            // 3.5 m because this gear comes out at 3.040 m, so the ceiling is met rather than missed. A missed one
            // would be written too — see the band tests — and this is about the key reaching the file.
            '--systems' => ['pooled'], '--stacks' => '1', '--shape' => ['pyramid'],
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.5',
            '--interface-height' => '0', '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('max_sub_height_m: 3.5', $display);
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
            '--systems' => ['pooled'], '--stacks' => '1', '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '3.0',
            '--interface-height' => '0', '--align' => ['center'], '--shape' => ['pyramid'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('3.040 m against the 3.000 m ceiling', $display);
        self::assertStringContainsString('40 mm too high', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-center', $display, 'the miss no longer costs the rig');
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
            '--systems' => ['pooled'], '--stacks' => '1', '--from' => ['achenbach-18', 'eighteensound-2way-15'], '--max-width' => '3.70',
            '--interface-height' => '2.0', '--align' => ['center'], '--shape' => ['pyramid'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('fire below head height', $display);
        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-center', $display, 'a short wall is a rig, not a refusal');
    }

    /**
     * **The two bounds are the control, so there is no third option.** Stating a band wide enough for anything is how
     * a caller declines the check — which is what the low hand-written rigs say for themselves — and it has to keep
     * working, or the only way to build an unusual rig would be to edit the command.
     */
    public function testStatingABandWideEnoughForAnythingAcceptsTheSameRig(): void
    {
        $display = $this->invoke([
            '--systems' => ['pooled'], '--stacks' => '1', '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--max-sub-height' => '99',
            '--interface-height' => '0', '--align' => ['center'], '--shape' => ['pyramid'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-1-pooled--------pyramid-upright-alternate-center', $display);
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
        $shared = ['--systems' => ['pooled'], '--stacks' => '1', '--from' => self::OWN_GEAR, '--align' => ['center'], '--shape' => ['free'], '--low-end' => ['low'], '--dry-run' => true];

        $unbounded = $this->invoke($shared)->getDisplay();
        $bounded = $this->invoke($shared + ['--max-width' => '2.40'])->getDisplay();

        self::assertStringContainsString('id: stacked-1-pooled--------free----upright-alternate-center', $unbounded);
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
        $files = self::generatedScenes();
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $yaml = (string) file_get_contents($file);
            $name = basename($file);

            preg_match('/^\s*max_sub_height_m:\s*(\S+)/m', $yaml, $ceiling);

            // **Per stack, not per file, and that distinction was a real hole rather than a refinement.** This used to
            // measure each wall and then look for the explanation anywhere in the file, so a scene whose *other* stack
            // warned about something covered for a silent one — and **70 shipped scenes had a silent short sub wing
            // inside them** for exactly that reason, found in 0.96.0 when a scene with no other warning finally
            // failed. Each wall is now held to the lines that follow its own `Subs reach` header.
            $blocks = preg_split('/(?=^# Subs reach )/m', $yaml, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $walls = 0;
            foreach ($blocks as $block) {
                if (1 !== preg_match('/^# Subs reach ([\d.]+) m against a ([\d.]+) m interface/', $block, $wall)) {
                    continue;
                }
                ++$walls;
                // Only the block's own commentary, which ends where the next stack's does or where the YAML starts.
                $said = preg_split('/^(# main-|id: )/m', $block)[0] ?? '';

                if ((float) $wall[1] + 1e-9 < (float) $wall[2]) {
                    self::assertStringContainsString(
                        'm interface asked for',
                        $said,
                        $name.' has a stack short of its interface and says nothing about it',
                    );
                }
                if ([] !== $ceiling && (float) $wall[1] > (float) $ceiling[1] + 1e-9) {
                    self::assertStringContainsString(
                        'm ceiling asked for',
                        $said,
                        $name.' has a stack over its ceiling and says nothing about it',
                    );
                }
            }

            self::assertGreaterThan(0, $walls, $name.' records no sub wall height');
        }
    }

    /**
     * A wide stage must not cost the rig its height. `max_width_m` is a maximum, not a target: on a 10 m
     * stage every device fits in one row, which leaves two sub tiers and puts a 2 m interface out of reach
     * forever unless the rows are allowed to narrow.
     *
     * One pooled stack, because that is the rig most at risk: all the cabinets in a single stack have the most row
     * width to spend and the fewest tiers to lose.
     */
    public function testAWideStageStillReachesTheInterface(): void
    {
        foreach ([['--max-width' => '10.0'], []] as $widthOption) {
            $tester = $this->invoke($widthOption + [
                '--from' => self::STACKABLE, '--interface-height' => '2.0', '--stacks' => '1', '--systems' => ['pooled'],
                '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
            ]);

            self::assertSame(0, $tester->getStatusCode(), 'a wide or unbounded stage still solves');
            self::assertStringContainsString('against a 2.0 m interface', $tester->getDisplay());
        }
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
     * That is the whole value of the line in {@see SceneStackCommand} that hands a solo stack `INF`, and
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
     * `mid-bass` is left out here and always was — 1.200 m of cabinet that the 3.70 m stage cannot carry beside
     * the rest — so that is the rig rather than anything this change did.
     */
    public function testASoloStackSlidesARowRatherThanLosingTheRig(): void
    {
        $display = $this->invoke([
            '--systems' => ['pooled'], '--from' => ['wall-bass', 'mid-bass', 'iq-sub', 'nuke', 'turbo-top'],
            '--max-width' => '3.70', '--stacks' => '1', '--align' => ['center'], '--shape' => ['free'],
            '--orientation' => ['turned'], '--mirror-style' => ['centred'], '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('id: stacked-1-pooled--------free----turned--centred---center', $display);

        // Carried rather than refused, which is what the name is about. The rig comes out, and the only cabinet it
        // gives up is the one the stage cannot take.
        self::assertStringContainsString('mid-bass: LEFT OUT', $display);
        self::assertStringNotContainsString('iq-sub: LEFT OUT', $display, 'the subs are all carried');

        // **2.84 m since 0.142.0, in six symmetric rows of pairs.** The 2.70 m rig put a spare IQ sub beside each pair of
        // wall basses and nukes, and a lopsided row no longer wins while a symmetric one stands.
        self::assertSame([2.84], $this->heights($display));
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
     * Asserted on the sub-wall height rather than on the row list because that is the one number both sides state,
     * and it is what separated the two answers by a factor of three.
     *
     * **Read off `# Subs reach`, which the writer always emits, rather than off a warning.** It used to compare the
     * header's `the subs reach … m against` against the rebuild's violation of the same words, and that is a
     * sound comparison only while this rig misses its band. In 0.112.0 it stopped missing it — the sub wall now
     * lands at 2.451 m inside the 2 m interface and the 3 m ceiling — so the warning went away and with it the
     * whole assertion, which the test caught and said out loud. The header line is unconditional, so the check
     * cannot go quiet again.
     */
    public function testAWrittenSceneRebuildsToTheSubWallItsOwnHeaderReports(): void
    {
        $tester = $this->invoke([
            '--systems' => ['pooled'], '--from' => [
                'wall-bass', 'mid-bass', 'skram', 'flexy-folded-horn-hybrid', 'nuke',
                'achenbach-18', 'iq-sub', 'tecnare-m2122', 'eighteensound-2way-15', 'turbo-top',
            ],
            '--orientation' => ['turned'], '--stacks' => '1', '--align' => ['center'], '--shape' => ['free'],
            '--mirror-style' => ['alternate'], '--id' => self::THROWAWAY_ID,
        ]);
        self::assertSame(0, $tester->getStatusCode());

        $written = self::throwaway();
        self::assertCount(1, $written);
        $contents = (string) file_get_contents($written[0]);

        // A solo stack slides without bound, so the file has to say so. Written as `.inf` because `sprintf('%.4F')`
        // gives `INF`, which YAML reads as a word.
        self::assertStringContainsString('slide_slack_m: .inf', $contents);

        self::assertSame(
            1,
            preg_match('/# Subs reach ([\d.]+) m against/', $contents, $header),
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

        // The top of the rebuilt sub wall, which is the quantity the header's own figure is printed from. Measured
        // on the placed shells rather than on a row list, so a rebuild that re-solved the wall into different rows
        // still has to arrive at the same height.
        $wall = -INF;
        foreach ($result['placed'] as $entry) {
            if ('sub' === $entry->device->subtype) {
                $wall = max($wall, $entry->worldBox()['max'][2]);
            }
        }

        self::assertEqualsWithDelta(
            (float) $header[1],
            $wall,
            5e-4,
            'the rebuilt rig is not the one the file describes',
        );
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
}
