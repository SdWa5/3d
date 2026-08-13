<?php

declare(strict_types=1);

namespace App\Command;

use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\SceneLoader;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
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
            ->addOption('camera', 'c', InputOption::VALUE_REQUIRED, 'Camera preset to render with')
            // Naming one narrows the sweep to it. That is the whole of the opt-out: there is no
            // `--no-lighting-variants`, because "just this lighting" is what stating a lighting already means.
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, 'Render this lighting only, instead of every preset')
            ->addOption('aim-lines', 'a', InputOption::VALUE_REQUIRED, 'Render this aim mode only (none, tops), instead of both')
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Cycles samples')
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, 'WIDTHxHEIGHT')
            ->addOption('quick-preview', null, InputOption::VALUE_NONE, 'Render every variant at preview quality')
            ->addOption('high-quality', null, InputOption::VALUE_NONE, 'Render every variant at full quality');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

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
        if (!$dryRun && $this->regenerate($output) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // Checked here rather than left to `scene:render`, because a sweep is the one place a typo is expensive:
        // `--dry-run` runs no stage at all, so an unvalidated value would list a plausible pass and only be
        // caught when the real sweep reached the render an hour later.
        $aimLines = $input->getOption('aim-lines');
        if ($aimLines !== null && !in_array($aimLines, self::AIM_MODES, true)) {
            $this->io->error(sprintf(
                "Unknown --aim-lines value '%s'. Available: %s",
                (string)$aimLines,
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
            if ($exit !== self::SUCCESS) {
                $this->io->error("Stopped at `{$name}` — nothing after it would have been built on anything good");

                return $exit;
            }
        }

        foreach ($renders as $label => $arguments) {
            $this->io->section((string)$label);
            $exit = $this->delegate('scene:render', $arguments, $output);
            if ($exit !== self::SUCCESS) {
                $this->io->error("Stopped at `scene:render` ({$label})");

                return $exit;
            }
        }

        $this->io->success(sprintf(
            '%d stage%s and %d render pass%s done',
            count($stages),
            count($stages) === 1 ? '' : 's',
            count($renders),
            count($renders) === 1 ? '' : 'es',
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
        ], static fn (mixed $value): bool => $value !== null);

        foreach (['quick-preview', 'high-quality'] as $level) {
            if ($input->getOption($level)) {
                $shared['--'.$level] = true;
            }
        }

        if ($input->getOption('force')) {
            $shared['--force'] = true;
        }

        // **Every variant, unless one is named.** These used to be opt-in flags that every invocation passed, so
        // the useful default was the one nobody got by default. Four lighting presets times two aim modes is eight
        // renders per scene — which is why the quality level matters as much as it does.
        $lightings = $input->getOption('lighting') !== null
            ? [$input->getOption('lighting')]
            : array_map(static fn (LightingPreset $p): string => $p->value, LightingPreset::cases());

        $aimModes = $input->getOption('aim-lines') !== null
            ? [$input->getOption('aim-lines')]
            : [RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS];

        $plain = count($lightings) === 1 && count($aimModes) === 1;

        $variants = [];
        foreach ($lightings as $lighting) {
            foreach ($aimModes as $aim) {
                $arguments = $shared;
                if ($lighting !== null) {
                    $arguments['--lighting'] = $lighting;
                }
                if ($aim !== null) {
                    $arguments['--aim-lines'] = $aim;
                }

                $folder = trim(($lighting ?? 'default').($aim === RenderPlan::AIM_NONE ? '' : '-aim'), '-');
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
        $generated = glob($this->scenesDir().'/'.SceneLoader::GENERATED.'/*.{yaml,yml}', GLOB_BRACE) ?: [];
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
            count($renders) === 1 ? '' : 'es',
        ));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function flags(array $arguments): string
    {
        $parts = [];
        foreach ($arguments as $flag => $value) {
            $parts[] = $value === true ? $flag : $flag.'='.$this->relative((string)$value);
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $arguments
     */
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
     */
    /**
     * The cabinets a turned rig lays on their sides, stated here because **no spec field says which are horn-loaded**.
     *
     * That omission is deliberate and predates this: "adding one to drive a rotation would be inventing a property to
     * serve a layout". So the list lives with the thing that uses it. These two are the ones the hand-made turned
     * scenes already passed to `--roll-mirror`: a Flexy on its side is 763 × 591 rather than 591 × 763, which is a
     * wider and lower wall out of the same cabinets, and a SKRAM likewise.
     *
     * Turning the *whole* inventory does not work and is not expected to — see TODO 4: a rolled SKRAM is 610 mm tall
     * against a rolled Flexy's 591, so a bottom row mixing them has a 19 mm step and the row above lands on 17 % of
     * itself. The turned pass reports which rigs refuse rather than pretending they all work.
     */
    private const TURNABLE = ['flexy-folded-horn-hybrid', 'skram'];

    private function regenerate(OutputInterface $output): int
    {
        $directory = $this->scenesDir().'/'.SceneLoader::GENERATED;
        $files = glob($directory.'/*.{yaml,yml}', GLOB_BRACE) ?: [];
        if ($files === []) {
            return self::SUCCESS;
        }

        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('build:all has to run through the application, so it can find the other commands');

            return self::FAILURE;
        }

        $this->io->section('scene:stack — regenerating '.count($files).' generated scenes');

        sort($files);
        $turned = [];

        foreach ($files as $file) {
            $command = self::recordedCommand((string)file_get_contents($file));
            if ($command === null) {
                $this->io->text(sprintf(
                    '  <comment>skipped</comment> %s — no `Regenerate it with:` line, so it is not regenerable',
                    $this->relative($file),
                ));
                continue;
            }

            // `--force` because the file being replaced is precisely the one this command wrote; without it every
            // run after the first would refuse itself.
            $exit = $application->find('scene:stack')->run(new StringInput($command.' --force'), $output);
            if ($exit !== self::SUCCESS) {
                $this->io->error('Regenerating '.$this->relative($file).' failed');

                return self::FAILURE;
            }

            $turned[] = $command;
        }

        $exit = $this->regenerateTurned($turned, $application, $output);

        return $exit === self::SUCCESS ? $this->prune() : $exit;
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
        $ids = [];
        foreach (glob($this->scenesDir().'/'.SceneLoader::GENERATED.'/*.{yaml,yml}', GLOB_BRACE) ?: [] as $scene) {
            $ids[pathinfo($scene, PATHINFO_FILENAME)] = true;
        }
        if ($ids === []) {
            // No generated scenes at all is far more likely to be a bad run than an instruction to empty the tree.
            return self::SUCCESS;
        }

        $build = $this->projectDir().'/build';
        $removed = [];
        $derived = [
            ...(glob($build.'/scenes/'.SceneLoader::GENERATED.'/*') ?: []),
            ...(glob($build.'/plans/'.SceneLoader::GENERATED.'/*') ?: []),
            ...(glob($build.'/renders/'.SceneLoader::GENERATED.'/*.png') ?: []),
            ...(glob($build.'/renders/*/'.SceneLoader::GENERATED.'/*.png') ?: []),
        ];

        foreach ($derived as $file) {
            $id = self::sceneIdOf($file);
            if (!is_file($file) || $id === null || isset($ids[$id])) {
                continue;
            }
            if (@unlink($file)) {
                $removed[] = $this->relative($file);
            }
        }

        if ($removed !== []) {
            $this->io->section(sprintf(
                'pruned %d stale derived file%s',
                count($removed),
                count($removed) === 1 ? '' : 's',
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
     * The scene id a derived file belongs to, or null when its name says nothing.
     *
     * The three shapes it has to read are `<id>.blend`, `_scene-<id>.json` and `<id>-<camera>.png`. A camera suffix is
     * stripped from a known list rather than by taking everything before the last dash, because scene ids contain
     * dashes themselves — `stacked-sdwa5-2-center-three-quarter.png` would otherwise resolve to a scene called
     * `stacked-sdwa5-2-center-three`.
     */
    private static function sceneIdOf(string $file): ?string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (str_starts_with($name, '_scene-')) {
            return substr($name, strlen('_scene-'));
        }
        if (str_ends_with($file, '.png')) {
            foreach (['three-quarter', 'front', 'side', 'top', 'iso'] as $camera) {
                if (str_ends_with($name, '-'.$camera)) {
                    return substr($name, 0, -strlen('-'.$camera));
                }
            }

            return null;
        }

        return $name;
    }

    /**
     * The same rigs again with the horn-loaded cabinets on their sides.
     *
     * **A refusal here is not a build failure**, and that is the whole design of this pass. Turning cabinets changes
     * the geometry enough that some rigs genuinely cannot be built that way — a bottom row mixing a rolled SKRAM with
     * a rolled Flexy has a 19 mm step and the row above lands on 17 % of itself — so a turned variant that refuses is
     * reported with its reason and the build carries on. `scene:stack` already treats an unbuildable alignment the
     * same way; this only has to not turn that into an error.
     *
     * Skipped for a rig that is already turned, since a scene generated with `--roll-mirror` has nothing left to roll
     * and would just rewrite itself under a longer name.
     *
     * @param list<string> $commands the `scene:stack` arguments of each generated scene
     */
    private function regenerateTurned(array $commands, Application $application, OutputInterface $output): int
    {
        $rolls = implode(' ', array_map(static fn (string $id): string => '--roll-mirror='.$id, self::TURNABLE));

        $turned = array_values(array_filter(
            $commands,
            static fn (string $command): bool => !str_contains($command, '--roll-mirror='),
        ));
        if ($turned === []) {
            return self::SUCCESS;
        }

        $this->io->section(sprintf('scene:stack — %d turned variants (%s)', count($turned), implode(', ', self::TURNABLE)));

        foreach ($turned as $command) {
            // The id has to change or the turned rig overwrites the upright one. `--id` is always recorded, except
            // when it was the default, so append to whatever is there rather than assuming a value.
            $id = preg_match('/--id=(\S+)/', $command, $matches) === 1 ? $matches[1] : 'stacked';
            $arguments = preg_replace('/--id=\S+/', '', $command).' --id='.$id.'-turned '.$rolls.' --force';

            if ($application->find('scene:stack')->run(new StringInput((string)$arguments), $output) !== self::SUCCESS) {
                $this->io->text(sprintf(
                    '  <comment>no turned rig</comment> %s-turned — see the reason above; the upright one is unaffected',
                    $id,
                ));
            }
        }

        return self::SUCCESS;
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
            if ($arguments === null) {
                if (preg_match('/^#\s{3}bin\/console scene:stack (.+)$/', $line, $matches) === 1) {
                    $arguments = trim($matches[1]);
                }
                continue;
            }
            if (preg_match('/^#\s{5,}(\S.*)$/', $line, $matches) === 1) {
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
        if ($application === null) {
            $this->io->error('build:all has to run through the application, so it can find the other commands');

            return self::FAILURE;
        }

        return $application->find($name)->run(new ArrayInput($arguments), $output);
    }
}
