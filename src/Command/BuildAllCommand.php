<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\CompiledScene;
use App\Process\Parallel;
use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\SceneLoader;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Everything, from specs to pictures, in the right order.
 *
 * Each stage already refuses to run on stale input — `library:build` will not stitch a library from models
 * older than their specs, and `scene:build` will not place a device whose model is out of date — but nothing
 * knew the *order*, so getting from an edited spec to a new render meant remembering five commands and which
 * of them the change had invalidated. This is that knowledge, written down once.
 *
 * It delegates rather than reimplements: each stage runs through the application, so its own staleness rules,
 * its own reporting and its own refusals are the ones that apply. A stage that fails stops the run, because
 * every stage after it would be building on what just went wrong.
 *
 * The variant options are what makes it more than a shell alias. Comparing a rig with and without aim lines,
 * or under every lighting preset, used to mean running `scene:render` by hand once per combination with
 * `--out` set to a path chosen by hand — and getting it wrong silently, because until now neither the
 * picture's filename nor its plan's mentioned the lighting or the aim mode, so two variants overwrote each
 * other.
 */
final class BuildAllCommand extends BaseCommand
{
    /**
     * Every aim mode `--aim-lines` accepts, in the order `scene:render` lists them.
     *
     * Only the first two are swept by default. `all` draws a line off every cabinet rather than off the tops
     * alone, which is a diagnostic for one rig rather than something worth a folder in every sweep — so it is
     * nameable but never automatic.
     */
    private const AIM_MODES = [RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS, RenderPlan::AIM_ALL];

    protected function configure(): void
    {
        $this
            ->setName('build:all')
            ->setDescription('Validate, build models, library, scenes and renders — the whole pipeline')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rebuild everything, even what looks up to date')
            ->addOption('skip-render', null, InputOption::VALUE_NONE, 'Stop after the scenes are assembled')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the stages and variants without running any')
            ->addOption('keep-stale', null, InputOption::VALUE_NONE, 'Leave generated scene files the sweep no longer writes. Default: delete them')
            ->addOption('camera', 'c', InputOption::VALUE_REQUIRED, 'Camera preset to render with')
            // Naming one picks it out; `--every-variant` asks for all eight. See {@see renderVariants} for why
            // the sweep stopped being the default.
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, 'Render this lighting instead of the default preset')
            ->addOption('aim-lines', 'a', InputOption::VALUE_REQUIRED, 'Render this aim mode (none, tops) instead of none')
            ->addOption('every-variant', null, InputOption::VALUE_NONE, 'Render all four lighting presets in both aim modes — eight pictures per scene')
            ->addOption('jobs', 'j', InputOption::VALUE_REQUIRED, 'Processes to regenerate the scenes in — 1 is serial, 0 is one per core', '0')
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Cycles samples')
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, 'WIDTHxHEIGHT')
            ->addOption('quick-preview', null, InputOption::VALUE_NONE, 'Render every variant at preview quality')
            ->addOption('high-quality', null, InputOption::VALUE_NONE, 'Render every variant at full quality');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // Every stage skips what is already current, so a rebuild after touching one scene costs one scene
        // and its renders rather than the whole library. `--force` overrides all of them at once — it used to
        // reach only `models:build`, which meant a forced run still reused stale scenes and renders.
        $force = $input->getOption('force') ? ['--force' => true] : [];

        $stages = [
            ['specs:validate', []],
            ['models:build', $force],
            ['library:build', []],
            ['scene:build', $force],
        ];

        // The generated scenes are rewritten before anything is built from them, which closes the last gap in
        // "from specs to pictures". Every other stage already follows the specs; these files did not, so re-measuring
        // the GMSS cabinets left eleven of them describing rows that no longer existed and nothing noticed.
        //
        // **This stage writes to `scenes/generated/`, which is tracked** — the only stage that touches anything
        // outside `build/`. That is the deliberate trade: a generated scene is build output that happens to be worth
        // reviewing in a diff, so it has to be regenerated like build output and reviewed like source.
        if (!$dryRun && self::SUCCESS !== $this->regenerate($input, $output, !$input->getOption('keep-stale'))) {
            return self::FAILURE;
        }

        // Checked here rather than left to `scene:render`, because a sweep is the one place a typo is expensive:
        // `--dry-run` runs no stage at all, so an unvalidated value would list a plausible pass and only be
        // caught when the real sweep reached the render an hour later.
        $aimLines = $input->getOption('aim-lines');
        if (null !== $aimLines && !in_array($aimLines, self::AIM_MODES, true)) {
            $this->io->error(sprintf(
                "Unknown --aim-lines value '%s'. Available: %s",
                (string) $aimLines,
                implode(', ', self::AIM_MODES),
            ));

            return self::FAILURE;
        }

        $renders = $input->getOption('skip-render') ? [] : $this->renderVariants($input);

        if ($dryRun) {
            $this->describe($stages, $renders);

            return self::SUCCESS;
        }

        foreach ($stages as [$name, $arguments]) {
            $exit = $this->delegate($name, $arguments, $output);
            if (self::SUCCESS !== $exit) {
                $this->io->error("Stopped at `{$name}` — nothing after it would have been built on anything good");

                return $exit;
            }
        }

        foreach ($renders as $label => $arguments) {
            $this->io->section((string) $label);
            $exit = $this->delegate('scene:render', $arguments, $output);
            if (self::SUCCESS !== $exit) {
                $this->io->error("Stopped at `scene:render` ({$label})");

                return $exit;
            }
        }

        $this->io->success(sprintf(
            '%d stage%s and %d render pass%s done',
            count($stages),
            1 === count($stages) ? '' : 's',
            count($renders),
            1 === count($renders) ? '' : 'es',
        ));

        return self::SUCCESS;
    }

    /**
     * Every render pass this run should make, keyed by a label that says what it is.
     *
     * With no variant asked for there is exactly one, writing where `scene:render` always wrote — so the
     * plain case leaves the output tree exactly as it was. Ask for variants and each pass gets a folder
     * named after what makes it different, because a picture whose filename does not say which lighting it
     * used is a picture you have to render again to identify.
     *
     * @return array<string, array<string, mixed>>
     */
    private function renderVariants(InputInterface $input): array
    {
        $shared = array_filter([
            '--camera' => $input->getOption('camera'),
            '--samples' => $input->getOption('samples'),
            '--resolution' => $input->getOption('resolution'),
        ], static fn (mixed $value): bool => null !== $value);

        foreach (['quick-preview', 'high-quality'] as $level) {
            if ($input->getOption($level)) {
                $shared['--'.$level] = true;
            }
        }

        if ($input->getOption('force')) {
            $shared['--force'] = true;
        }

        // **ONE PICTURE PER SCENE UNLESS EVERY VARIANT IS ASKED FOR, AND THAT IS A REVERSAL.** Sweeping all four
        // lighting presets in both aim modes was the default from 0.70.0, on the argument that the useful output
        // was the one nobody got by default. The argument was right about usefulness and wrong about arithmetic:
        // eight renders times 483 generated scenes is 3864 pictures for one `build:all`, and Blender is the
        // slowest thing in this repository by a wide margin. Stated by the owner, who asked for the lighting sweep
        // to go if it was what held the pipeline up. It is. `--every-variant` asks for the eight back, and naming
        // a `--lighting` or an `--aim-lines` still picks one out.
        $everyVariant = (bool) $input->getOption('every-variant');

        $lightings = match (true) {
            null !== $input->getOption('lighting') => [$input->getOption('lighting')],
            $everyVariant => array_map(static fn (LightingPreset $p): string => $p->value, LightingPreset::cases()),
            // Null rather than a named preset, so the plain case keeps writing where `scene:render` always wrote
            // and defers the choice of preset to it.
            default => [null],
        };

        $aimModes = match (true) {
            null !== $input->getOption('aim-lines') => [$input->getOption('aim-lines')],
            $everyVariant => [RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS],
            default => [RenderPlan::AIM_NONE],
        };

        $plain = 1 === count($lightings) && 1 === count($aimModes);

        $variants = [];
        foreach ($lightings as $lighting) {
            foreach ($aimModes as $aim) {
                $arguments = $shared;
                if (null !== $lighting) {
                    $arguments['--lighting'] = $lighting;
                }
                $arguments['--aim-lines'] = $aim;

                $folder = trim(($lighting ?? 'default').(RenderPlan::AIM_NONE === $aim ? '' : '-aim'), '-');
                if (!$plain) {
                    $arguments['--out-dir'] = $this->projectDir().'/build/renders/'.$folder;
                }

                $variants[$plain ? 'renders' : $folder] = $arguments;
            }
        }

        return $variants;
    }

    /**
     * @param list<array{string, array<string, mixed>}> $stages
     * @param array<string, array<string, mixed>> $renders
     */
    private function describe(array $stages, array $renders): void
    {
        // Listed first because it runs first, and named with the count so a dry run says how many files a real run
        // would rewrite — which is the one thing about this stage worth knowing before starting it.
        $generated = self::generatedScenes($this->scenesDir().'/'.SceneLoader::GENERATED);
        $rows = [['scene:stack', sprintf('replaying %d generated scenes\' own commands', count($generated))]];

        foreach ($stages as [$name, $arguments]) {
            $rows[] = [$name, $this->flags($arguments) ?: '—'];
        }
        foreach ($renders as $label => $arguments) {
            $rows[] = ['scene:render', $this->flags($arguments) ?: '—'];
        }

        $this->io->table(['Stage', 'With'], $rows);
        $this->io->text(sprintf(
            '%d stages then %d render pass%s. Nothing was run.',
            count($stages),
            count($renders),
            1 === count($renders) ? '' : 'es',
        ));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function flags(array $arguments): string
    {
        $parts = [];
        foreach ($arguments as $flag => $value) {
            $parts[] = true === $value ? $flag : $flag.'='.$this->relative((string) $value);
        }

        return implode(' ', $parts);
    }

    /**
     * Every generated scene rewritten by **replaying the command written in its own header**.
     *
     * There is no list of commands anywhere, and there deliberately is not one: a second copy would go out of step
     * with the files, which is the failure this whole stage exists to prevent. `scene:stack` writes the line that
     * produced each file, so the file is its own recipe and a scene that stops being generated simply stops being
     * regenerated.
     *
     * A file with no such line is **skipped and named**, not guessed at. Anything under `generated/` that a person
     * wrote by hand is a mistake worth seeing rather than one to overwrite silently.
     *
     * **This stage used to carry a second, hard-coded orientation axis and no longer does.** A `regenerateTurned()`
     * pass re-ran every recorded command with `--roll-mirror=flexy-folded-horn-hybrid --roll-mirror=skram` and an
     * `-turned` id, which is where the ten `-turned-` scenes deleted in 0.76.0 came from. It was exactly the second copy
     * this docblock argues against, and the orientation axis in `scene:stack` supersedes it on every count: every sub
     * rather than two named cabinets, three modes and seven pairs rather than one, and each choice recorded in the
     * file's own line instead of applied on the way past.
     *
     * It also broke outright once the axis landed, which is what made the removal urgent rather than tidy. The pass
     * detected an already-turned rig by looking for `--roll-mirror=` in the recorded command, and a turned rig now
     * records `--orientation=turned` — so it turned the turned scenes again and wrote 141 extra files with ids like
     * `stacked-sdwa5-sepp-2-turned-turned-column-center`.
     */
    private function regenerate(InputInterface $input, OutputInterface $output, bool $deleteStale = true): int
    {
        [$exit, $written] = $this->replayRecorded($input, $output);
        if (self::SUCCESS !== $exit) {
            return $exit;
        }

        $directory = $this->scenesDir().'/'.SceneLoader::GENERATED;
        if ($deleteStale && self::SUCCESS !== $this->deleteStaleScenes($directory, $written)) {
            return self::FAILURE;
        }

        return $this->prune();
    }

    /**
     * The replay itself, split from the two deletions that follow it.
     *
     * **Split so a test can run it**, which is the whole of TOOL-6. `regenerate()` writes into the repository and then
     * deletes from it, and a test that failed midway through the deletions could take real renders with it — so the
     * stage that had never been run by anything but `--dry-run` was the one stage nobody dared run. That is how a
     * defect writing 141 stray scenes reached `git status` before it reached the suite. The replay on its own is
     * idempotent by contract: every recorded command rewrites its own file, so a test can call this, assert nothing
     * moved, and leave the tree exactly as it found it.
     *
     * `$directory` is the generated scene set and defaults to the real one. **It is a parameter so a test can hand it
     * two files instead of 483**, which took a check about one synthetic scene from 9m32s to seconds — and, more to
     * the point, stopped it depending on which of the real rigs happen to solve this week. `scene:stack` still resolves
     * its own output path, so a replay pointed elsewhere writes into the real tree and a test has to clean up after it.
     *
     * @return array{int, list<string>} the exit code, and every path `scene:stack` reported writing
     */
    private function replayRecorded(InputInterface $input, OutputInterface $output, ?string $directory = null): array
    {
        $directory ??= $this->scenesDir().'/'.SceneLoader::GENERATED;
        $files = self::generatedScenes($directory);
        if ([] === $files) {
            return [self::SUCCESS, []];
        }

        $application = $this->getApplication();
        if (null === $application) {
            $this->io->error('build:all has to run through the application, so it can find the other commands');

            return [self::FAILURE, []];
        }

        $this->io->section('scene:stack — regenerating '.count($files).' generated scenes');

        sort($files);

        // **Each replay is its own process, and each one writes its own file.** Five hundred `scene:stack` runs that
        // share nothing took a quarter of an hour of one core while the other twenty-seven sat idle. The replays
        // cannot collide: a recorded command rewrites exactly the file it was read from, which is the same contract
        // that makes this stage idempotent in the first place. **The child's console output is captured rather than
        // printed**, because twenty-eight processes writing to one terminal interleave mid-line; the parent prints
        // the buffers back in file order, so the log reads exactly as a serial run's did.
        //
        // A verbosity note that is easy to get wrong: the buffer is given the real output's verbosity, or a `-v`
        // run would come out quiet.
        $replays = Parallel::map(
            // Keyed by path, because `glob()` returns a list and the loop below names the failing file from the key.
            array_combine($files, $files),
            function (string $file) use ($application, $output): array {
                $command = self::recordedCommand((string) file_get_contents($file));
                if (null === $command) {
                    return ['exit' => self::SUCCESS, 'output' => '', 'written' => [], 'note' => sprintf(
                        '  <comment>skipped</comment> %s — no `Regenerate it with:` line, so it is not regenerable',
                        $this->relative($file),
                    )];
                }

                $buffer = new BufferedOutput($output->getVerbosity(), $output->isDecorated());
                // `--force` because the file being replaced is precisely the one this command wrote; without it every
                // run after the first would refuse itself.
                $stack = $application->find('scene:stack');
                // `--jobs=1` on the child: a recorded command rebuilds one rig, and a nested pool would fight the
                // one already running. Where the parent is serial this keeps the whole stage in one process.
                $exit = $stack->run(new StringInput($command.' --force --jobs=1'), $buffer);

                return [
                    'exit' => $exit,
                    'output' => $buffer->fetch(),
                    // The union across every replay, and the reason it is collected here rather than read once at the
                    // end: {@see SceneStackCommand::$written} describes one run, and this stage is hundreds of them.
                    'written' => $stack instanceof SceneStackCommand ? $stack->written : [],
                    // **A rig that no longer solves is stale, not broken, and this is what makes the stage
                    // idempotent.** Every refusal was printed with its reason a moment ago, so there is nothing to add
                    // beyond leaving the file out of `written` — {@see deleteStaleScenes} then removes it, and the next
                    // run has nothing to replay. Treated as a hard error this aborted the whole stage on the first such
                    // file, which meant `build:all` could not be run twice:
                    // `stacked-all--------1-pyramid-mixed---alternate-stereo` stopped being offered and took the other
                    // 449 replays down with it. See {@see SceneStackCommand::NOTHING_TO_WRITE}.
                    'note' => SceneStackCommand::NOTHING_TO_WRITE === $exit ? sprintf(
                        '  <comment>stale</comment>   %s — the sweep no longer offers this rig, so it is deleted rather than rebuilt',
                        $this->relative($file),
                    ) : null,
                ];
            },
            (int) $input->getOption('jobs'),
        );

        $written = [];
        foreach ($replays as $file => $replay) {
            if ('' !== $replay['output']) {
                $output->write($replay['output']);
            }
            if (null !== $replay['note']) {
                $this->io->text($replay['note']);
            }
            // **Reported in file order rather than at the moment it happened**, which is the one behaviour the fork
            // changes. A serial replay stopped at the first broken rig and never learned whether the rest were fine;
            // this runs them all and then names the first failure the old order would have named.
            if (self::SUCCESS !== $replay['exit'] && SceneStackCommand::NOTHING_TO_WRITE !== $replay['exit']) {
                $this->io->error('Regenerating '.$this->relative((string) $file).' failed');

                return [self::FAILURE, $written];
            }
            if (self::SUCCESS === $replay['exit']) {
                $written = [...$written, ...$replay['written']];
            }
        }

        return [self::SUCCESS, $written];
    }

    /**
     * Generated scene files this run did not write, deleted — the other half of {@see prune}.
     *
     * **A replay renames rather than replaces.** Every scene's own recorded command rebuilds it under whatever name
     * the *current* naming produces, so an axis that gains a value, a name that gains a field or a rig that stops
     * solving leaves the old file sitting there, correct-looking and describing a rig the sweep no longer offers. One
     * release renamed all 150 at once and the tree had 546 files in it until somebody noticed.
     *
     * **Decided by what the run wrote, never by a clock**, which is the whole safety of it. {@see prune}'s docblock
     * records why: two attempts at deciding staleness by timestamp both destroyed the scene set, because
     * `filemtime()` is whole seconds where `microtime(true)` is fractional and a file written in the same second as
     * the run started reads as older than the run. `scene:stack` now reports the paths it wrote and this compares
     * against that report, so a file is stale when the sweep did not produce it rather than when it looks old.
     *
     * Three guards, each of which has to hold or nothing is deleted:
     *
     * * **A run that wrote nothing deletes nothing.** An empty report is far more likely to be a broken stage than an
     *   instruction to empty the tree — the same reasoning {@see prune} applies to an empty scene set.
     * * **Only files that carry a `Regenerate it with:` line.** A file without one is not something this pipeline
     *   wrote, `regenerate()` above says so and skips it, and deleting what it declined to rebuild would be the
     *   pipeline removing somebody else's work.
     * * **Only `scenes/generated/`**, which is the one tracked directory this pipeline owns.
     *
     * **This catches a rename and a rig that has stopped solving, and it used to catch only the first.** Worth stating
     * plainly, because the rule is subtler than the name. {@see replayRecorded} replays *every* file that carries a
     * recorded line, so a file lands in `$written` by construction whenever its replay succeeds, and the only way it
     * can go missing is by coming out under a different name — a rename. A scene the sweep no longer offers replays
     * perfectly well from its own line and used to survive here for ever, which is what **TOOL-7** was.
     *
     * What closed it is that "every candidate was refused" is now its own exit code rather than a failure. The replay
     * leaves such a file out of `$written` and says so, and this deletes it, so the stage converges: run `build:all`
     * twice and the second run has nothing to delete. Before that it did worse than miss them — one rig that stopped
     * solving aborted the whole stage, so the pipeline could not be run at all until the file was removed by hand.
     *
     * Measured rather than reasoned, twice over: 18 files carrying `--max-width=3.7` outlived the release that deleted
     * the width ladder, and 10 more outlived 0.84.0, `stacked-all--------1-pyramid-mixed---alternate-stereo` among
     * them. Of those 10 this stage now deletes 4 — the ones whose rig stopped solving.
     *
     * **The other 6 are duplicates and are the part that is still open**, filed as **TOOL-15**. Dedup is a decision
     * across a whole sweep, "the same rig as X", and a replay is one file with nothing to compare itself against, so a
     * collapsed variant rebuilds itself happily and survives. Narrow: all six were confirmed duplicates of a sibling
     * that is also on disk, so nothing is lost by them and nothing is lost by removing them either. The honest fix is
     * to run the sweep as this stage rather than replaying files.
     *
     * `--keep-stale` switches it off. It defaults to deleting because the stale files are the confusing half: they
     * compile, they render, and nothing about looking at one says it belongs to a rig that no longer exists.
     *
     * @param list<string> $written absolute paths `scene:stack` reported writing
     */
    private function deleteStaleScenes(string $directory, array $written): int
    {
        if ([] === $written) {
            return self::SUCCESS;
        }

        $removed = [];
        foreach (self::generatedScenes($directory) as $file) {
            if (!self::isStaleScene($file, $written, (string) file_get_contents($file))) {
                continue;
            }
            if (!@unlink($file)) {
                $this->io->error('Could not delete the stale '.$this->relative($file));

                return self::FAILURE;
            }
            $removed[] = $this->relative($file);
        }

        if ([] !== $removed) {
            $this->io->section(sprintf(
                'deleted %d stale generated scene%s',
                count($removed),
                1 === count($removed) ? '' : 's',
            ));
            foreach (array_slice($removed, 0, 12) as $file) {
                $this->io->text('  <comment>removed</comment> '.$file);
            }
            if (count($removed) > 12) {
                $this->io->text(sprintf('  … and %d more', count($removed) - 12));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Every generated scene file under `$directory`, **recursively and sorted**.
     *
     * One helper rather than four `glob()` calls, because the generated set gained a directory level per inventory
     * and a flat `glob($directory.'/*.{yaml,yml}')` silently stopped seeing any of it. Silently is the word that
     * matters here: the replay stage would have reported nothing to replay, the stale check would have found nothing
     * stale, and {@see prune} would then have deleted every derived artifact on the grounds that its scene no longer
     * existed. All four read the same set, so all four ask the same question in the same place.
     *
     * The same walk {@see SceneLoader::files} does, for the same reason.
     *
     * @return list<string>
     */
    private static function generatedScenes(string $directory): array
    {
        return self::filesUnder($directory, ['yaml', 'yml']);
    }

    /**
     * Every file under `$directory` with one of these extensions, recursively and sorted.
     *
     * The same walk again, for the derived side. {@see prune}'s four `glob()` calls were one level deep and the
     * derived tree gained the same directory levels the scene tree did, so they saw an empty set — which reads to
     * the prune as "nothing derived exists" rather than as "look deeper".
     *
     * @param list<string> $extensions lowercase, without the dot
     *
     * @return list<string>
     */
    private static function filesUnder(string $directory, array $extensions): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Whether one generated scene file is stale: this run did not write it, and it is one this pipeline owns.
     *
     * Split out from the loop so the rule can be tested on strings rather than on a directory somebody has to build
     * first — the same bargain {@see sceneIdOf} strikes, and for the same reason: the decision is the part worth
     * pinning and the filesystem is not.
     *
     * @param list<string> $written absolute paths `scene:stack` reported writing
     */
    private static function isStaleScene(string $file, array $written, string $yaml): bool
    {
        return !in_array($file, $written, true) && null !== self::recordedCommand($yaml);
    }

    /**
     * Every **derived** artifact under a `generated/` directory whose scene no longer exists.
     *
     * The scene set changes shape whenever the sweep does, and this release renamed every id — `stacked-center` became
     * `stacked-sdwa5-2-center` — which left 38 orphaned `.blend` files and 11 orphaned renders behind. Nothing pruned
     * them, because every stage only ever added, so the tree accumulated a layer per release and a reader could not
     * tell which pictures belonged to the current rigs.
     *
     * **Derived files only, and the reason is that this is a pure set comparison.** A `.blend`, a plan or a render
     * carries its scene's id in its name, so "no scene of that id exists" is a fact with no timing in it — and
     * everything under `build/` is disposable and regenerable, which is why it is gitignored.
     *
     * **Generated scene files are deliberately NOT pruned automatically.** Deciding a scene file is stale means
     * knowing which files this run wrote, and two attempts at that by timestamp both destroyed the scene set:
     * `filemtime()` is whole seconds while `microtime(true)` is fractional, so a file written in the same second as
     * the run started reads as older than the run and was deleted. A stale scene file is visible in `git status`,
     * costs nothing, and is overwritten by the next `--force`; a deleted one is 25 files of work. If this is worth
     * automating later it needs `scene:stack` to report the paths it wrote, not a cleverer clock.
     */
    private function prune(): int
    {
        $loader = new SceneLoader($this->scenesDir());
        $keys = [];
        foreach (self::generatedScenes($this->scenesDir().'/'.SceneLoader::GENERATED) as $scene) {
            $keys[$loader->keyOf($scene)] = true;
        }
        if ([] === $keys) {
            // No generated scenes at all is far more likely to be a bad run than an instruction to empty the tree.
            return self::SUCCESS;
        }

        // **KEYED BY PATH, NOT BY BASENAME, AND WALKED RECURSIVELY.** Both halves of that broke together in 0.98.0:
        // the derived tree mirrors the scene tree, so a one-level `glob()` found none of it, and a basename is
        // shared by up to eleven inventories, so any file it did find was matched against the wrong scene. Each
        // root below is the directory the mirrored path is relative to.
        $build = $this->projectDir().'/build';
        $removed = [];
        $roots = [
            $build.'/scenes' => self::filesUnder($build.'/scenes/'.SceneLoader::GENERATED, ['blend', 'blend1']),
            $build.'/plans' => self::filesUnder($build.'/plans/'.SceneLoader::GENERATED, ['json']),
            $build.'/renders' => self::filesUnder($build.'/renders/'.SceneLoader::GENERATED, ['png', 'mp4']),
        ];
        foreach (glob($build.'/renders/*', GLOB_ONLYDIR) ?: [] as $variant) {
            if (SceneLoader::GENERATED !== basename($variant)) {
                $roots[$variant] = self::filesUnder($variant.'/'.SceneLoader::GENERATED, ['png', 'mp4']);
            }
        }

        foreach ($roots as $root => $derived) {
            foreach ($derived as $file) {
                $key = self::sceneKeyOf($file, (string) $root);
                if (!is_file($file) || null === $key || isset($keys[$key])) {
                    continue;
                }
                if (@unlink($file)) {
                    $removed[] = $this->relative($file);
                }
            }
        }

        if ([] !== $removed) {
            $this->io->section(sprintf(
                'pruned %d stale derived file%s',
                count($removed),
                1 === count($removed) ? '' : 's',
            ));
            foreach (array_slice($removed, 0, 12) as $file) {
                $this->io->text('  <comment>removed</comment> '.$file);
            }
            if (count($removed) > 12) {
                $this->io->text(sprintf('  … and %d more', count($removed) - 12));
            }
        }

        return self::SUCCESS;
    }

    /**
     * The scene **key** a derived file belongs to, or null when its name says nothing.
     *
     * Fly-through plans and MP4s carry a recognised fly-through suffix. The other shapes are `<name>.blend`, `_scene-<name>.json`, `_compiled-<name>.json` and `<name>-<camera>.png`. A camera
     * suffix is stripped from a known list rather than by taking everything before the last dash, because scene
     * names contain dashes themselves — `stacked-sdwa5-2-center-three-quarter.png` would otherwise resolve to a
     * scene called `stacked-sdwa5-2-center-three`.
     *
     * **The directory travels with the name**, which is the whole of the 0.98.0 fix: `$root` is the directory the
     * derived tree mirrors `scenes/` from, so `build/scenes/generated/gmss/x.blend` under root `build/scenes`
     * answers `generated/gmss/x` — the same key the scene itself has. Without it, eleven inventories' artifacts all
     * answered `x` and the prune matched them against whichever scene it met first.
     */
    private static function sceneKeyOf(string $file, string $root): ?string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $prefix = rtrim(str_replace('\\', '/', $root), '/').'/';
        $directory = str_replace('\\', '/', pathinfo($file, PATHINFO_DIRNAME)).'/';
        $relative = str_starts_with($directory, $prefix) ? substr($directory, strlen($prefix)) : '';
        if (str_ends_with($file, '.mp4') || str_ends_with($file, '.json')) {
            foreach (['-fly-through-perpendicular', '-fly-through'] as $suffix) {
                if (str_ends_with($name, $suffix)) {
                    return $relative.substr($name, 0, -strlen($suffix));
                }
            }
        }
        foreach (['_scene-', CompiledScene::PREFIX] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return $relative.substr($name, strlen($prefix));
            }
        }
        if (str_ends_with($file, '.png')) {
            foreach (['three-quarter', 'front', 'side', 'top', 'iso'] as $camera) {
                if (str_ends_with($name, '-'.$camera)) {
                    return $relative.substr($name, 0, -strlen('-'.$camera));
                }
            }

            return null;
        }

        return $relative.$name;
    }

    /**
     * The `scene:stack` arguments recorded in a generated scene's header, or null when it records none.
     *
     * The line is wrapped across comment lines to keep the header readable, so the continuations — indented further
     * than the first line — are joined back together here. Matching on the indent rather than on a marker keeps the
     * writer free to reflow the line at a different width without this having to agree about where.
     */
    private static function recordedCommand(string $yaml): ?string
    {
        $lines = explode("\n", $yaml);
        $arguments = null;

        foreach ($lines as $line) {
            if (null === $arguments) {
                if (1 === preg_match('/^#\s{3}bin\/console scene:stack (.+)$/', $line, $matches)) {
                    $arguments = trim($matches[1]);
                }
                continue;
            }
            if (1 === preg_match('/^#\s{5,}(\S.*)$/', $line, $matches)) {
                $arguments .= ' '.trim($matches[1]);
                continue;
            }
            break;
        }

        return $arguments;
    }

    private function delegate(string $name, array $arguments, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if (null === $application) {
            $this->io->error('build:all has to run through the application, so it can find the other commands');

            return self::FAILURE;
        }

        return $application->find($name)->run(new ArrayInput($arguments), $output);
    }
}
