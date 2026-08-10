<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\LayoutMode;
use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Scene\Stack;
use App\Scene\StackSceneWriter;
use App\Scene\StackSolver;
use App\Spec\DeviceSpec;
use App\Spec\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes scene files from constraints instead of from a tier list somebody typed out.
 *
 * `scene:build` already solves a `stack:` block, but somebody has to write the scene first. This is the step
 * before that: give it the gear and the bounds, get one scene per arrangement that actually works — and a
 * reason for every arrangement it left out, so a constraint that rules one out is visible rather than
 * silently absent.
 *
 * Every candidate is **compiled before it is written**. A generator that emits a scene the compiler rejects
 * is worse than no generator, because the failure surfaces later and further from its cause.
 */
final class SceneStackCommand extends BaseCommand
{
    /** Enough to compare a handful of rigs; past this something is being enumerated by accident. */
    private const DEFAULT_MAX_SCENES = 24;

    protected function configure(): void
    {
        $this
            ->setName('scene:stack')
            ->setDescription('Solve a rig from constraints and write it out as scene files')
            ->addOption('from', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Device ids, low frequency first. Default: every speaker, subs before tops')
            ->addOption('max-width', null, InputOption::VALUE_REQUIRED, 'How wide the stage or truss allows, in metres')
            ->addOption('min-width', null, InputOption::VALUE_REQUIRED, 'Floor on the widest tier, in metres')
            ->addOption('max-height', null, InputOption::VALUE_REQUIRED, 'Ceiling or rigging limit, in metres')
            ->addOption('interface-height', null, InputOption::VALUE_REQUIRED, 'Height the tops must clear, in metres', (string)Stack::DEFAULT_INTERFACE_HEIGHT_M)
            ->addOption('gap', null, InputOption::VALUE_REQUIRED, 'Working gap between neighbours, in metres', '0.02')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Where the rig is centred, as X,Y', '-0.302,0')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Base scene id', 'stacked')
            ->addOption('align', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'center, block or stereo. Default: all three')
            ->addOption('subs', null, InputOption::VALUE_REQUIRED, 'What to do with the widest sub: mixed, beside or both', 'mixed')
            ->addOption('max-scenes', null, InputOption::VALUE_REQUIRED, 'Refuse to write more than this many', (string)self::DEFAULT_MAX_SCENES)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the scenes instead of writing them')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing scene file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        $devices = [];
        foreach ($specs as $spec) {
            $devices[$spec->id] = $spec;
        }

        $from = $input->getOption('from') ?: $this->everySpeaker($specs);
        $missing = array_values(array_diff($from, array_keys($devices)));
        if ($missing !== []) {
            $this->io->error(sprintf("Unknown device '%s'", $missing[0]));

            return self::FAILURE;
        }

        $at = $this->readAt((string)$input->getOption('at'));
        if ($at === null) {
            $this->io->error('--at expects X,Y in metres');

            return self::FAILURE;
        }

        $modes = $this->readModes($input->getOption('align'));
        if ($modes === null) {
            return self::FAILURE;
        }

        $placements = match ((string)$input->getOption('subs')) {
            'mixed' => [false],
            'beside' => [true],
            'both' => [false, true],
            default => null,
        };
        if ($placements === null) {
            $this->io->error('--subs expects mixed, beside or both');

            return self::FAILURE;
        }

        $candidates = [];
        $skipped = [];
        foreach ($placements as $beside) {
            foreach ($modes as $mode) {
                $built = $this->build($devices, $from, $at, $mode, $beside, $input);
                if (is_string($built)) {
                    $skipped[$this->nameFor((string)$input->getOption('id'), $mode, $beside)] = $built;
                    continue;
                }
                $candidates[$this->nameFor((string)$input->getOption('id'), $mode, $beside)] = $built;
            }
        }

        $candidates = $this->deduplicate($candidates, $skipped);

        foreach ($skipped as $name => $reason) {
            $this->io->text(sprintf('  <comment>skipped</comment> %s — %s', $name, $reason));
        }

        if ($candidates === []) {
            $this->io->warning('No workable arrangement — nothing written');

            return self::FAILURE;
        }

        $limit = (int)$input->getOption('max-scenes');
        if (count($candidates) > $limit) {
            // Refused rather than truncated: a silent cap reads as "that is every possibility" when it is not.
            $this->io->error(sprintf(
                '%d arrangements is more than --max-scenes=%d — narrow it with --align/--subs, or raise the limit',
                count($candidates),
                $limit,
            ));

            return self::FAILURE;
        }

        return $this->emit($candidates, (bool)$input->getOption('dry-run'), (bool)$input->getOption('force'));
    }

    /**
     * One candidate scene, or the reason there is none.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     * @param array{float, float} $at
     * @return array{yaml: string, cabinets: int}|string
     */
    private function build(
        array $devices,
        array $from,
        array $at,
        LayoutMode $mode,
        bool $beside,
        InputInterface $input,
    ): array|string {
        $stack = new Stack(
            from: $from,
            maxWidthM: $this->readFloat($input, 'max-width'),
            minWidthM: $this->readFloat($input, 'min-width'),
            maxHeightM: $this->readFloat($input, 'max-height'),
            interfaceHeightM: (float)$input->getOption('interface-height'),
            gapM: (float)$input->getOption('gap'),
        );

        $problems = $stack->problems();
        if ($problems !== []) {
            return $problems[0];
        }

        $stood = null;
        $inStack = $from;
        if ($beside) {
            $widest = $this->widestSub($devices, $from);
            if ($widest === null) {
                return 'no sub to stand beside the rig';
            }
            $stood = [$devices[$widest], $devices[$widest]->quantity];
            $inStack = array_values(array_filter($from, static fn (string $id): bool => $id !== $widest));
            if ($inStack === []) {
                return 'standing the only sub beside the rig would leave nothing in it';
            }
            $stack = new Stack(
                from: $inStack,
                maxWidthM: $stack->maxWidthM,
                minWidthM: $stack->minWidthM,
                maxHeightM: $stack->maxHeightM,
                interfaceHeightM: $stack->interfaceHeightM,
                gapM: $stack->gapM,
            );
        }

        $inventory = array_map(
            static fn (string $id): array => [$devices[$id], $devices[$id]->quantity],
            $inStack,
        );

        $solved = StackSolver::solve($inventory, $stack);
        if ($solved['problems'] !== []) {
            return $solved['problems'][0];
        }

        $yaml = StackSceneWriter::yaml(
            id: 'placeholder',
            name: $this->describe($mode, $beside),
            stack: $stack,
            tiers: $solved['tiers'],
            warnings: $solved['warnings'],
            at: $at,
            from: $inStack,
            align: $mode === LayoutMode::Center ? null : $mode,
            beside: $stood,
        );

        // Compiled before it is written. Anything the compiler calls an error means this arrangement is not
        // one of the possibilities, whatever the solver thought of the tiers.
        $compiled = $this->compileYaml($yaml, $devices);
        if (is_string($compiled)) {
            return $compiled;
        }

        return ['yaml' => $yaml, 'cabinets' => $compiled];
    }

    /**
     * How many cabinets the scene places, or the first error it produces.
     *
     * @param array<string, DeviceSpec> $devices
     * @return int|string
     */
    private function compileYaml(string $yaml, array $devices): int|string
    {
        try {
            /** @var array<string, mixed> $data */
            $data = Yaml::parse($yaml);
            $scene = SceneSpec::fromArray($data, 'generated');
        } catch (\Throwable $e) {
            return 'the generated scene does not parse: '.$e->getMessage();
        }

        $result = (new SceneCompiler($devices))->compile($scene);
        $errors = Violation::errorsIn($result['violations']);
        if ($errors !== []) {
            return $errors[0]->message;
        }

        return count($result['placed']);
    }

    /**
     * Drops arrangements that place their cabinets in exactly the same spots as an earlier one.
     *
     * `stereo` on an odd tier of three resolves identically to `block` — the leftover cabinet centres on
     * `at` and the outer two land on the envelope edges — and writing that rig twice under two names would
     * suggest a choice that does not exist.
     *
     * @param array<string, array{yaml: string, cabinets: int}> $candidates
     * @param array<string, string> $skipped
     * @return array<string, array{yaml: string, cabinets: int}>
     */
    private function deduplicate(array $candidates, array &$skipped): array
    {
        $kept = [];
        $seen = [];

        foreach ($candidates as $name => $candidate) {
            // The tier table in the header is the solved geometry in text form, which is all that has to
            // match for two arrangements to be the same rig.
            $fingerprint = preg_replace('/^(id|name):.*$/m', '', $candidate['yaml']);
            $existing = array_search($fingerprint, $seen, true);
            if ($existing !== false) {
                $skipped[$name] = sprintf('the same rig as %s', $existing);
                continue;
            }

            $seen[$name] = $fingerprint;
            $kept[$name] = $candidate;
        }

        return $kept;
    }

    /**
     * @param array<string, array{yaml: string, cabinets: int}> $candidates
     */
    private function emit(array $candidates, bool $dryRun, bool $force): int
    {
        $exit = self::SUCCESS;

        foreach ($candidates as $name => $candidate) {
            $path = $this->scenesDir().'/'.$name.'.yaml';
            $yaml = str_replace('id: placeholder', 'id: '.$name, $candidate['yaml']);

            if ($dryRun) {
                $this->io->section($name.'.yaml');
                $this->io->writeln($yaml);
                continue;
            }
            if (file_exists($path) && !$force) {
                $this->io->text(sprintf('  <comment>exists</comment>  %s — pass --force to overwrite', $this->relative($path)));
                $exit = self::FAILURE;
                continue;
            }
            if (file_put_contents($path, $yaml) === false) {
                $this->io->error('Could not write '.$this->relative($path));

                return self::FAILURE;
            }

            $this->io->text(sprintf('  <info>wrote</info>   %-44s %d cabinets', $this->relative($path), $candidate['cabinets']));
        }

        if (!$dryRun && $exit === self::SUCCESS) {
            $this->io->newLine();
            $this->io->text('Now: <comment>bin/console scene:build</comment>');
        }

        return $exit;
    }

    /**
     * Every speaker in the library, subs before tops — the order the fill needs and the one nobody should
     * have to type out.
     *
     * @param list<DeviceSpec> $specs
     * @return list<string>
     */
    private function everySpeaker(array $specs): array
    {
        $subs = [];
        $tops = [];
        foreach ($specs as $spec) {
            if ($spec->category->value !== 'speaker' || $spec->quantity < 1) {
                continue;
            }
            // Widest first within each band, which is the order that stacks without inverting.
            $spec->subtype === 'sub' ? $subs[] = $spec : $tops[] = $spec;
        }

        $byWidth = static fn (DeviceSpec $a, DeviceSpec $b): int => $b->dimensions->width <=> $a->dimensions->width;
        usort($subs, $byWidth);
        usort($tops, $byWidth);

        return array_map(static fn (DeviceSpec $s): string => $s->id, [...$subs, ...$tops]);
    }

    /**
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     */
    private function widestSub(array $devices, array $from): ?string
    {
        $widest = null;
        foreach ($from as $id) {
            if ($devices[$id]->subtype !== 'sub') {
                continue;
            }
            if ($widest === null || $devices[$id]->dimensions->width > $devices[$widest]->dimensions->width) {
                $widest = $id;
            }
        }

        return $widest;
    }

    /**
     * @param list<string> $raw
     * @return list<LayoutMode>|null
     */
    private function readModes(array $raw): ?array
    {
        if ($raw === []) {
            return LayoutMode::cases();
        }

        $modes = [];
        foreach ($raw as $value) {
            $mode = LayoutMode::tryFrom($value);
            if ($mode === null) {
                $this->io->error(sprintf(
                    "--align: unknown value '%s' (allowed: %s)",
                    $value,
                    implode(', ', array_column(LayoutMode::cases(), 'value')),
                ));

                return null;
            }
            $modes[] = $mode;
        }

        return $modes;
    }

    /**
     * @return array{float, float}|null
     */
    private function readAt(string $raw): ?array
    {
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return [(float)$parts[0], (float)$parts[1]];
    }

    private function readFloat(InputInterface $input, string $option): ?float
    {
        $value = $input->getOption($option);

        return $value === null ? null : (float)$value;
    }

    private function nameFor(string $base, LayoutMode $mode, bool $beside): string
    {
        return $base.'-'.$mode->value.($beside ? '-beside' : '');
    }

    private function describe(LayoutMode $mode, bool $beside): string
    {
        return sprintf(
            'Solved rig — tiers %s%s',
            match ($mode) {
                LayoutMode::Center => 'centred',
                LayoutMode::Block => 'justified',
                LayoutMode::Stereo => 'split left and right',
            },
            $beside ? ', widest sub beside the rig' : '',
        );
    }
}
