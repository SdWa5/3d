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
    protected function configure(): void
    {
        $this
            ->setName('build:all')
            ->setDescription('Validate, build models, library, scenes and renders — the whole pipeline')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rebuild models even if they look up to date')
            ->addOption('skip-render', null, InputOption::VALUE_NONE, 'Stop after the scenes are assembled')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the stages and variants without running any')
            ->addOption(
                'aim-line-variants',
                null,
                InputOption::VALUE_NONE,
                'Render every scene twice, with and without aim lines, into separate folders',
            )
            ->addOption(
                'lighting-variants',
                null,
                InputOption::VALUE_NONE,
                'Render every scene under every lighting preset, one folder each',
            )
            ->addOption('camera', 'c', InputOption::VALUE_REQUIRED, 'Camera preset to render with')
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, 'Lighting preset, unless --lighting-variants')
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Cycles samples')
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, 'WIDTHxHEIGHT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        $stages = [
            ['specs:validate', []],
            ['models:build', $input->getOption('force') ? ['--force' => true] : []],
            ['library:build', []],
            ['scene:build', []],
        ];

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

        $lightings = $input->getOption('lighting-variants')
            ? array_map(static fn (LightingPreset $p): string => $p->value, LightingPreset::cases())
            : [$input->getOption('lighting')];

        $aimModes = $input->getOption('aim-line-variants')
            ? [RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS]
            : [null];

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
