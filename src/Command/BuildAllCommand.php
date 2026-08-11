<?php

declare(strict_types=1);

namespace App\Command;

use App\Render\LightingPreset;
use App\Render\RenderPlan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $rows = [];
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
