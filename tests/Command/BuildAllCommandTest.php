<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BuildAllCommand;
use App\Command\CatalogCommand;
use App\Command\LibraryBuildCommand;
use App\Command\ModelsBuildCommand;
use App\Command\SceneBuildCommand;
use App\Command\SceneRenderCommand;
use App\Command\SpecsValidateCommand;
use App\Process\Parallel;
use App\Tests\Support\ReplaySample;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

/**
 * The order of the pipeline and the shape of a variant sweep, checked with `--dry-run` — which is the only
 * way to check them at all here, since every stage but the first needs Blender and CI has none.
 */
final class BuildAllCommandTest extends TestCase
{
    public function testTheStagesRunInDependencyOrder(): void
    {
        $display = $this->dryRun(['--dry-run' => true]);

        // `scene:stack` first: the generated scenes are input to everything after them, and they were the one
        // thing in the pipeline that did not follow the specs — re-measuring the GMSS cabinets left eleven of them
        // describing rows that no longer existed and nothing in a full build noticed.
        $order = ['scene:stack', 'specs:validate', 'models:build', 'library:build', 'scene:build', 'scene:render'];
        $positions = array_map(static fn (string $stage): int|false => strpos($display, $stage), $order);

        foreach ($positions as $index => $position) {
            self::assertNotFalse($position, "{$order[$index]} is missing");
            if ($index > 0) {
                self::assertGreaterThan(
                    $positions[$index - 1],
                    $position,
                    "{$order[$index]} should come after {$order[$index - 1]}",
                );
            }
        }
    }

    public function testADryRunRunsNothing(): void
    {
        self::assertStringContainsString('Nothing was run', $this->dryRun(['--dry-run' => true]));
    }

    /**
     * The regeneration stage says how many files a real run would rewrite.
     *
     * Worth naming in the dry run specifically because it is the only stage that writes outside `build/`: everything
     * else a full build produces is disposable, and these are tracked files somebody may be part-way through
     * reviewing. A dry run is where you find that out.
     */
    public function testTheDryRunSaysHowManyGeneratedScenesWouldBeRewritten(): void
    {
        $count = count(self::generatedScenes(dirname(__DIR__, 2).'/scenes/generated'));
        self::assertGreaterThan(0, $count, 'there should be generated scenes to regenerate');

        self::assertStringContainsString(
            sprintf("replaying %d generated scenes' own commands", $count),
            $this->dryRun(['--dry-run' => true]),
        );
    }

    /**
     * A derived file whose scene no longer exists is pruned; everything else is left alone.
     *
     * The scene ids changed shape wholesale in 0.68.0 — `stacked-center` became `stacked-sdwa5-2-center` — and nothing
     * pruned, because every stage only added. That left 38 orphaned `.blend` files and 11 orphaned renders, and a
     * reader could not tell which pictures belonged to the current rigs.
     *
     * Written as a unit test on the naming rather than by running the whole pipeline, because the pipeline needs
     * Blender. What matters is that a camera suffix is stripped from a known list and not by cutting at the last dash:
     * scene ids contain dashes, so `stacked-sdwa5-2-center-three-quarter.png` would otherwise be read as belonging to
     * a scene called `stacked-sdwa5-2-center-three` and pruned as an orphan of a rig that does exist.
     *
     * The cases below carry the padded ids the sweep writes today, where every axis is a fixed-width field and the
     * padding is itself a run of dashes. That is the harshest case the cutting rule has to survive.
     *
     * **The inventory left these names when it became a directory** — `stacked-sdwa5-----2-…` is now
     * `sdwa5/stacked-2-…`. Two of the old-form cases are kept on purpose: the run of padding dashes they carry is
     * exactly the case a test about not cutting at the last dash wants.
     *
     * **AND THE DIRECTORY IS PART OF THE ANSWER NOW, WHICH IS THE 0.98.0 DEFECT THIS TEST DID NOT CATCH.** A
     * derived file used to be matched by basename alone, and 421 of the 589 generated basenames belong to two or
     * more inventories — so eleven rigs' artifacts all answered the same id, and the prune compared each of them
     * against whichever scene it met first. The key is the path relative to the mirrored root, which is why the
     * root is a second argument rather than something the method guesses.
     */
    public function testADerivedFileIsMatchedToItsSceneByItsKey(): void
    {
        $method = new \ReflectionMethod(BuildAllCommand::class, 'sceneKeyOf');

        foreach ([
            ['build/scenes/generated/stacked-sdwa5-----2-free----turned--centred---center.blend', 'build/scenes',
                'generated/stacked-sdwa5-----2-free----turned--centred---center'],
            ['build/plans/generated/_scene-stacked-sdwa5-----2-free----turned--centred---center.json', 'build/plans',
                'generated/stacked-sdwa5-----2-free----turned--centred---center'],
            ['build/renders/generated/stacked-sdwa5-----2-free----turned--centred---center-three-quarter.png',
                'build/renders', 'generated/stacked-sdwa5-----2-free----turned--centred---center'],
            ['build/renders/studio/generated/stacked-gmss------1-free----upright-alternate-center-front.png',
                'build/renders/studio', 'generated/stacked-gmss------1-free----upright-alternate-center'],
            // The shape the sweep writes now: the inventory is a directory, and two inventories share the name.
            ['build/scenes/generated/gmss/stacked-2-pooled--------free----turned--centred---center.blend',
                'build/scenes', 'generated/gmss/stacked-2-pooled--------free----turned--centred---center'],
            ['build/scenes/generated/sdwa5-sepp/stacked-2-pooled--------free----turned--centred---center.blend',
                'build/scenes', 'generated/sdwa5-sepp/stacked-2-pooled--------free----turned--centred---center'],
            ['build/renders/generated/next-event/stacked-1-systems-apart-free----upright-alternate-center-possible-three-quarter.png',
                'build/renders', 'generated/next-event/stacked-1-systems-apart-free----upright-alternate-center-possible'],
        ] as [$file, $root, $expected]) {
            self::assertSame($expected, $method->invoke(null, $file, $root), $file);
        }

        // **The two same-named blends above are the point of the whole change**: one basename, two inventories, two
        // keys. Matched by basename they were one file and ten rigs went unbuilt.
        self::assertNotSame(
            $method->invoke(null, 'build/scenes/generated/gmss/x.blend', 'build/scenes'),
            $method->invoke(null, 'build/scenes/generated/sdwa5-sepp/x.blend', 'build/scenes'),
        );

        // A picture whose name carries no camera says nothing about which scene it belongs to, and guessing would risk
        // pruning a file that is not an orphan.
        self::assertNull($method->invoke(null, 'build/renders/generated/mystery.png', 'build/renders'));
    }

    /**
     * Every generated scene under `$directory`, keyed by its path **relative to that directory**.
     *
     * Relative rather than by basename, because the generated set gained a directory level per inventory and two
     * inventories can hold the same file name: `sdwa5/stacked-1-pooled-…` and `gmss/stacked-1-pooled-…` are
     * different rigs with identical basenames. Keyed by basename this test would have compared one to the other and
     * called the difference a rebuild.
     *
     * @return array<string, string>
     */
    private static function generatedScenes(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && strtolower($file->getExtension()) === 'yaml') {
                $found[substr($file->getPathname(), strlen($directory) + 1)] = (string)file_get_contents($file->getPathname());
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * **A generated scene is stale when the run did not write it, never when it merely looks old.**
     *
     * The rule this pins is the one that makes deleting scene files safe at all. Two earlier attempts decided it by
     * timestamp and both destroyed the scene set, because `filemtime()` is whole seconds where `microtime(true)` is
     * fractional, so a file written in the same second the run started reads as older than the run. `scene:stack` now
     * reports the paths it wrote and staleness is a set difference against that report.
     *
     * The second half is the guard that keeps the pipeline out of somebody else's files: a scene with no
     * `Regenerate it with:` line is not one this pipeline wrote, the regenerate stage already declines to rebuild it,
     * and deleting what it declined to rebuild would be strictly worse than leaving it.
     *
     * Tested on strings rather than on a directory, for the same reason {@see testADerivedFileIsMatchedToItsSceneById}
     * is: the decision is the part worth pinning and the filesystem is not.
     */
    public function testASceneIsStaleOnlyWhenTheRunDidNotWriteItAndThePipelineOwnsIt(): void
    {
        $method = new \ReflectionMethod(BuildAllCommand::class, 'isStaleScene');
        $generated = "# Generated by `bin/console scene:stack`\n#\n# Regenerate it with:\n#\n"
            ."#   bin/console scene:stack --id=stacked\n#\nid: x\n";
        $handWritten = "# A scene somebody wrote.\nid: x\n";
        $written = ['/scenes/generated/kept.yaml'];

        self::assertFalse(
            $method->invoke(null, '/scenes/generated/kept.yaml', $written, $generated),
            'a file this run wrote is never stale',
        );
        self::assertTrue(
            $method->invoke(null, '/scenes/generated/gone.yaml', $written, $generated),
            'a generated file the run did not write is stale',
        );
        self::assertFalse(
            $method->invoke(null, '/scenes/generated/gone.yaml', $written, $handWritten),
            'a file with no recorded command is not this pipeline\'s to delete',
        );
    }

    /**
     * Every generated scene carries a runnable `scene:stack` line, because that line is what the stage replays.
     *
     * There is deliberately no list of commands in the code — a second copy would drift from the files — so this is
     * the invariant that keeps the stage able to do its job: a generated scene with no recorded command is skipped,
     * and a skipped scene silently stops following the specs.
     */
    public function testEveryGeneratedSceneRecordsTheCommandThatMadeIt(): void
    {
        $directory = dirname(__DIR__, 2).'/scenes/generated';
        $files = array_keys(self::generatedScenes($directory));
        self::assertNotSame([], $files);

        foreach ($files as $relative) {
            $file = $directory.'/'.$relative;
            $yaml = (string)file_get_contents($file);
            self::assertMatchesRegularExpression(
                '/^#\s{3}bin\/console scene:stack .+$/m',
                $yaml,
                basename($file).' records no command, so `build:all` cannot regenerate it',
            );
            // The id has to be in there too, or a replay writes `stacked-<mode>` over the top of another scene.
            self::assertMatchesRegularExpression(
                '/(--id=|bin\/console scene:stack(?!.*--id=))/',
                $yaml,
                basename($file).' records no --id',
            );
        }
    }

    /**
     * **The scene set is a fixed point of its own replay**: replaying every recorded command writes back exactly the
     * files that are there, and not one file more.
     *
     * This is the invariant `build:all`'s first stage rests on, and it went unchecked until it broke twice in one day.
     * The stage carried a second, hard-coded orientation axis — every recorded command re-run with
     * `--roll-mirror=flexy-folded-horn-hybrid --roll-mirror=skram` under an `-turned` id — which the real orientation
     * axis superseded. It detected an already-turned rig by looking for `--roll-mirror=` in the recorded line, and a
     * turned rig records `--orientation=turned`, so it turned the turned rigs again: **149 scenes in, 290 out**, with
     * ids like `stacked-sdwa5-sepp-2-turned-turned-column-center` and two committed scenes silently rewritten.
     *
     * Both halves are asserted because they fail differently. Contents catch a replay that rebuilds a *different* rig,
     * which is what a dropped option does; the file list catches a replay that writes an *extra* file, which is what
     * an id built from the wrong pieces does. `--dry-run` cannot see either, which is why the rest of this class did
     * not catch it.
     *
     * **What this does not cover, stated plainly: it replays the commands itself rather than running the stage.** The
     * bug above lived in the stage's *extra* pass, so this test would not have caught that one — it pins the contract
     * the stage depends on, not the stage. The stage itself is covered by
     * {@see testTheRegenerateStageRewritesTheSceneSetAndReportsWhatItWrote}, which became possible once the replay was
     * split from the two deletions that follow it — a test that can delete somebody's renders when it fails is worse
     * than the gap, so the seam had to come first.
     */
    /**
     * **THE STAGE ITSELF, RUN RATHER THAN DESCRIBED** — TOOL-6, and the gap that let a real defect through.
     *
     * `build:all`'s regenerate stage is the only one that writes into tracked files, and until now the only one no
     * test had ever run: everything above checks it with `--dry-run`, which lists the stages and executes none. So a
     * removed pass that re-ran every recorded command with an extra `-turned` id wrote **141 stray scenes** and two
     * rewritten committed files, and `git status` found it rather than the suite.
     *
     * The sibling test above replays the recorded commands by hand, which pins the *contract* the stage rests on. This
     * one calls the stage, so a second pass, a mangled command line or a lost `--force` shows up here. It is the
     * difference between "every recorded command is idempotent" and "the code that runs them does nothing else".
     *
     * **Only the replay, never the deletions.** `regenerate()` goes on to delete stale scenes and prune derived files,
     * and a test that failed midway through those could take real renders with it. {@see BuildAllCommand} splits the
     * replay out for exactly this reason, and the replay is idempotent by contract, so this can run it against the
     * real tree and leave it as it found it.
     */
    public function testTheRegenerateStageRewritesTheSceneSetAndReportsWhatItWrote(): void
    {
        // **THE ONE TEST HERE THAT CANNOT BE SAMPLED, SO IT IS ASKED FOR RATHER THAN RUN.** It drives the
        // production stage, and the stage replays every scene by definition — that is the whole of what it
        // promises. At 2688 scenes that is most of an hour, and an hour is a suite nobody runs before committing.
        //
        // What still runs every time is the property, on a sample:
        // {@see testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet} replays the same recorded lines
        // through the same command and compares the same before-and-after set. What only this one covers is the
        // *stage* — that it reports the paths it wrote, which is the set the stale deletion is a difference
        // against. Run it on a release, or whenever `BuildAllCommand` itself is touched.
        if (getenv('SDWA5_FULL_REPLAY') === false) {
            self::markTestSkipped('SDWA5_FULL_REPLAY=1 runs the whole regenerate stage — ca. 25 Minuten');
        }

        $directory = dirname(__DIR__, 2).'/scenes/generated';
        $before = self::generatedScenes($directory);
        self::assertNotSame([], $before);

        $command = new BuildAllCommand();
        $application = new Application();
        $application->add($command);
        $application->add(new \App\Command\SceneStackCommand());

        // `io` is set in execute(), which this deliberately does not call.
        $io = new \Symfony\Component\Console\Style\SymfonyStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );
        (new \ReflectionProperty(\App\Command\BaseCommand::class, 'io'))->setValue($command, $io);

        try {
            /** @var array{int, list<string>} $result */
            $result = (new \ReflectionMethod(BuildAllCommand::class, 'replayRecorded'))
                ->invoke($command, new ArrayInput([], $command->getDefinition()), new \Symfony\Component\Console\Output\NullOutput());
            [$exit, $written] = $result;

            self::assertSame(0, $exit, 'the stage itself failed');

            $after = self::generatedScenes($directory);

            // The two halves the stray-scene defect broke: which files exist, and what is in them.
            self::assertSame(array_keys($before), array_keys($after), 'the stage changed which scenes exist');
            self::assertSame($before, $after, 'the stage rebuilt a scene differently from the way it was written');

            // And it reports what it wrote, which is what the stale deletion is a set difference against. A stage that
            // reported nothing would silently make that deletion a no-op rather than an error.
            self::assertSame(
                array_keys($before),
                array_values(array_unique(array_map(
                    static fn (string $path): string => substr($path, strlen($directory) + 1),
                    $written,
                ))),
                'every regenerable scene is reported as written',
            );
        } finally {
            // Whatever happened, put the tree back.
            foreach (array_keys(self::generatedScenes($directory)) as $relative) {
                if (!isset($before[$relative])) {
                    unlink($directory.'/'.$relative);
                    continue;
                }
                file_put_contents($directory.'/'.$relative, $before[$relative]);
            }
        }
    }

    /**
     * **A recorded rig that no longer solves is stale, and the stage carries on** — TOOL-7, reported by the owner as
     * `build:all` refusing to run at all.
     *
     * `scene:stack` used to return `FAILURE` both when it broke and when every candidate was refused for a stated
     * reason, and the replay could only read the first meaning. So one scene whose rig the sweep had stopped
     * offering aborted the whole stage and took the other 482 replays with it, and the only way forward was to find
     * and delete the file by hand — from a stage whose entire job is deleting exactly that file.
     *
     * The two halves are asserted separately because they fail differently: the stage has to **succeed**, and the
     * dead scene has to be **absent from the written set**, which is what {@see BuildAllCommand::deleteStaleScenes}
     * takes its set difference against. A stage that succeeded but still reported the file as written would delete
     * nothing and look fine.
     *
     * Built on two scenes of its own rather than on the real 483, because which real rigs solve is exactly the thing
     * that changes underneath a test like this — and because replaying the whole set to check one file took 9m32s.
     * `--max-width=0.2` against a 0.591 m cabinet refuses every candidate for a reason the command prints, and will go
     * on doing so however the solver changes. The live scene beside it is the half that pins "took the other 482 with
     * it": a stage that merely stopped erroring, and stopped replaying, would pass without it.
     */
    public function testARecordedRigThatNoLongerSolvesIsStaleRatherThanFatal(): void
    {
        $recorded = static fn (string $id, string $options): string => implode("\n", [
            '# Regenerate it with:',
            '#',
            '#   bin/console scene:stack '.$options.' --id='.$id,
            '#',
            'id: '.$id,
            'name: "a stub, read only for the line above"',
            '',
            'placements: []',
            '',
        ]);

        $directory = sys_get_temp_dir().'/sdwa5-replay-'.getmypid();
        mkdir($directory, 0o777, true);
        file_put_contents(
            $directory.'/zz-test-abandoned-rig.yaml',
            $recorded('zz-test-abandoned-rig', '--from=flexy-folded-horn-hybrid --max-width=0.2 --stacks=1 --align=center --shape=free'),
        );
        file_put_contents(
            $directory.'/zz-test-live-rig.yaml',
            $recorded('zz-test-live-rig', '--from=flexy-folded-horn-hybrid --max-width=3.7 --stacks=1 --align=center --shape=free --orientation=upright --mirror-style=alternate'),
        );

        $command = new BuildAllCommand();
        $application = new Application();
        $application->add($command);
        $application->add(new \App\Command\SceneStackCommand());

        $io = new \Symfony\Component\Console\Style\SymfonyStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );
        (new \ReflectionProperty(\App\Command\BaseCommand::class, 'io'))->setValue($command, $io);

        try {
            /** @var array{int, list<string>} $result */
            $result = (new \ReflectionMethod(BuildAllCommand::class, 'replayRecorded'))
                ->invoke($command, new ArrayInput([], $command->getDefinition()), new \Symfony\Component\Console\Output\NullOutput(), $directory);
            [$exit, $written] = $result;

            self::assertSame(0, $exit, 'one abandoned rig aborted the whole stage');

            $names = implode("\n", array_map('basename', $written));
            self::assertStringNotContainsString(
                'zz-test-abandoned-rig',
                $names,
                'the abandoned rig is reported as written, so the stale deletion would never remove it',
            );
            self::assertStringContainsString(
                'zz-test-live-rig',
                $names,
                'the stage stopped replaying after the abandoned rig instead of carrying on past it',
            );
        } finally {
            // `scene:stack` resolves its own output path, so the live rig landed in the real tree.
            foreach (glob(dirname(__DIR__, 2).'/scenes/generated/zz-test-*.yaml') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($directory);
        }
    }

    public function testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet(): void
    {
        $directory = dirname(__DIR__, 2).'/scenes/generated';
        $before = self::generatedScenes($directory);
        self::assertNotSame([], $before);

        $application = new Application();
        $application->add(new \App\Command\SceneStackCommand());

        try {
            // Across processes, the same way the stage itself replays — 483 commands that share nothing but the
            // inventory. **Nothing is asserted inside the closure**, because an assertion that fails in a forked
            // child dies with the child and comes back as "a worker produced nothing" rather than as the message it
            // was written to give. The exit codes come home and are judged here.
            $exits = Parallel::map(
                // **SAMPLED, AND THE COMPARISON BELOW IS NOT.** One solve per scene over 2688 scenes is most of
                // an hour, and a property that costs the output of the thing it tests will always end up there.
                // What is sampled is what gets *replayed*; `$before` against `$after` still walks the whole set,
                // because a stale check against a sample would call the rest of the repository stale.
                ReplaySample::of($before),
                static function (string $yaml) use ($application): ?int {
                    $command = self::recordedCommandIn($yaml);
                    if ($command === null) {
                        return null;
                    }

                    return $application->find('scene:stack')->run(
                        new \Symfony\Component\Console\Input\StringInput($command.' --force'),
                        new \Symfony\Component\Console\Output\NullOutput(),
                    );
                },
            );

            foreach ($exits as $name => $exit) {
                self::assertNotNull($exit, $name.' records no command, so it cannot be replayed');
                self::assertSame(0, $exit, 'replaying '.$name.' failed');
            }

            $after = self::generatedScenes($directory);

            self::assertSame(array_keys($before), array_keys($after), 'the replay changed which scenes exist');
            self::assertSame($before, $after, 'the replay rebuilt a scene differently from the way it was written');
        } finally {
            // Whatever happened, put the tree back: a failing assertion must not leave 141 stray files behind for the
            // next test — or the next person — to trip over.
            foreach (array_keys(self::generatedScenes($directory)) as $relative) {
                if (!isset($before[$relative])) {
                    unlink($directory.'/'.$relative);
                    continue;
                }
                file_put_contents($directory.'/'.$relative, $before[$relative]);
            }
        }
    }

    /** The `scene:stack` arguments a generated scene records, unwrapped from its comment block. */
    private static function recordedCommandIn(string $yaml): ?string
    {
        $command = null;
        foreach (explode("\n", $yaml) as $line) {
            if ($command === null) {
                if (preg_match('/^#\s{3}bin\/console scene:stack (.+)$/', $line, $matches) === 1) {
                    $command = trim($matches[1]);
                }
                continue;
            }
            // Continuation lines are indented further than the first, which is how the writer wraps a long line.
            if (preg_match('/^#\s{5,}(\S.*)$/', $line, $matches) !== 1) {
                break;
            }
            $command .= ' '.trim($matches[1]);
        }

        return $command;
    }

    /**
     * **One picture per scene with nothing asked for, and that is a reversal of 0.70.0's default.**
     *
     * The sweep of four lighting presets in both aim modes is eight renders of each of 483 generated scenes, which
     * is 3864 pictures out of the slowest tool in the pipeline. Stated by the owner, who asked for the lighting
     * variants to go if they were what held `build:all` up. The useful-by-default argument still stands for
     * everything cheap; a render is not cheap.
     */
    public function testOnePictureIsRenderedPerSceneWithNoFlagsAtAll(): void
    {
        $display = $this->dryRun(['--dry-run' => true]);

        self::assertStringContainsString('1 render pass', $display);
        // The plain folder, so the default does not quietly move anybody's renders into a subdirectory.
        self::assertStringNotContainsString('--out-dir', $display);
        self::assertStringNotContainsString('--lighting', $display);
    }

    /**
     * And the eight are still one flag away, because comparing lightings side by side is what they are for.
     */
    public function testEveryVariantIsRenderedWhenItIsAskedFor(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--every-variant' => true]);

        self::assertStringContainsString('8 render passes', $display);
        self::assertStringContainsString('build/renders/studio-aim', $display);
        self::assertStringContainsString('build/renders/flat', $display);
    }

    /**
     * Naming a lighting picks it out of the sweep and leaves the aim modes alone, which is what it always did.
     */
    public function testNamingALightingLeavesOnlyItsTwoAimModes(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--every-variant' => true, '--lighting' => 'studio']);

        self::assertStringContainsString('2 render passes', $display);
        self::assertStringContainsString('--aim-lines=none', $display);
        self::assertStringContainsString('--aim-lines=tops', $display);
        self::assertStringNotContainsString('build/renders/flat', $display);
    }

    public function testNamingAnAimModeLeavesOneFolderPerLightingPreset(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--every-variant' => true, '--aim-lines' => 'none']);

        self::assertStringContainsString('4 render passes', $display);
        foreach (['studio', 'stage', 'daylight', 'flat'] as $preset) {
            self::assertStringContainsString('build/renders/'.$preset, $display);
        }
    }

    /**
     * Narrowed to one pass, the output goes exactly where `scene:render` always put it — so the single-variant
     * case does not quietly move anybody's renders into a subfolder.
     */
    public function testNarrowingToASinglePassWritesToThePlainFolder(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--lighting' => 'studio', '--aim-lines' => 'none']);

        self::assertStringContainsString('1 render pass', $display);
        self::assertStringNotContainsString('--out-dir', $display);
    }

    /**
     * A dry run runs no stage, so nothing downstream would catch the typo — and by the time the real sweep
     * reached `scene:render` it would have spent every earlier stage first.
     */
    public function testAnUnknownAimModeIsRefusedBeforeAnythingRuns(): void
    {
        $display = $this->invoke(['--dry-run' => true, '--aim-lines' => 'top'], Command::FAILURE);

        self::assertStringContainsString("Unknown --aim-lines value 'top'", $display);
        self::assertStringNotContainsString('render pass', $display);
    }

    /**
     * A quality level reaches every pass in the sweep, since choosing preview quality is a statement about the
     * run rather than about one variant of it.
     */
    public function testAQualityLevelIsForwardedToEveryPass(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--every-variant' => true, '--quick-preview' => true]);

        self::assertSame(8, substr_count($display, '--quick-preview'));
    }

    public function testSkipRenderLeavesTheRenderStageOut(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--skip-render' => true]);

        self::assertStringContainsString('0 render passes', $display);
        self::assertStringNotContainsString('scene:render', $display);
    }

    public function testForceIsForwardedToTheModelBuild(): void
    {
        self::assertStringContainsString('--force', $this->dryRun(['--dry-run' => true, '--force' => true]));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function dryRun(array $arguments): string
    {
        return $this->invoke($arguments, Command::SUCCESS);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function invoke(array $arguments, int $expected): string
    {
        $application = new Application('sdwa5-3d', 'test');
        $application->addCommands([
            new SpecsValidateCommand(),
            new ModelsBuildCommand(),
            new LibraryBuildCommand(),
            new SceneBuildCommand(),
            new SceneRenderCommand(),
            new CatalogCommand(),
            new BuildAllCommand(),
        ]);

        $tester = new CommandTester($application->find('build:all'));
        $exit = $tester->execute($arguments);

        self::assertSame($expected, $exit, $tester->getDisplay());

        return $tester->getDisplay();
    }
}
