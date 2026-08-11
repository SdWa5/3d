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

        $candidates = [];
        $skipped = [];
        foreach ($modes as $mode) {
            $name = sprintf('%s-%s', (string)$input->getOption('id'), $mode->value);
            $built = $this->build($devices, $from, $at, $mode, $input);
            if (is_string($built)) {
                $skipped[$name] = $built;
                continue;
            }
            $candidates[$name] = $built;
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
     * @return array{yaml: string, cabinets: int, fingerprint: string}|string
     */
    private function build(
        array $devices,
        array $from,
        array $at,
        LayoutMode $mode,
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

        $inventory = array_map(
            static fn (string $id): array => [$devices[$id], $devices[$id]->quantity],
            $from,
        );

        $solved = StackSolver::solve($inventory, $stack);
        if ($solved['problems'] !== []) {
            return $solved['problems'][0];
        }

        $yaml = StackSceneWriter::yaml(
            id: 'placeholder',
            name: $this->describe($mode),
            stack: $stack,
            tiers: $solved['tiers'],
            warnings: $solved['warnings'],
            at: $at,
            from: $from,
            align: $mode === LayoutMode::Center ? null : $mode,
        );

        // Compiled before it is written. Anything the compiler calls an error means this arrangement is not
        // one of the possibilities, whatever the solver thought of the tiers.
        $compiled = $this->compileYaml($yaml, $devices);
        if (is_string($compiled)) {
            return $compiled;
        }

        return ['yaml' => $yaml] + $compiled;
    }

    /**
     * What the scene actually resolves to — how many cabinets, and a fingerprint of where they all end up —
     * or the first error it produces.
     *
     * The fingerprint is the **solved geometry**, not the file, and that distinction is the whole point of
     * it: two arrangements can differ in what they *say* and still be the same rig. Once every top shares one
     * row, that row is mixed, `align` has nothing left to distribute, and `center`/`block`/`stereo` all come
     * out identical — three files implying a choice that does not exist.
     *
     * @param array<string, DeviceSpec> $devices
     * @return array{cabinets: int, fingerprint: string}|string
     */
    private function compileYaml(string $yaml, array $devices): array|string
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

        $marks = [];
        foreach ($result['placed'] as $entry) {
            $position = $entry->liftedPosition();
            $marks[] = sprintf(
                '%s@%.6F,%.6F,%.6F/%.4F',
                $entry->device->id,
                $position[0],
                $position[1],
                $position[2],
                $entry->yawDeg(),
            );
        }
        sort($marks);

        return ['cabinets' => count($result['placed']), 'fingerprint' => implode('|', $marks)];
    }

    /**
     * Drops arrangements that place their cabinets in exactly the same spots as an earlier one.
     *
     * `stereo` on an odd tier of three resolves identically to `block` — the leftover cabinet centres on
     * `at` and the outer two land on the envelope edges — and writing that rig twice under two names would
     * suggest a choice that does not exist.
     *
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
     * @param array<string, string> $skipped
     * @return array<string, array{yaml: string, cabinets: int, fingerprint: string}>
     */
    private function deduplicate(array $candidates, array &$skipped): array
    {
        $kept = [];
        $seen = [];

        foreach ($candidates as $name => $candidate) {
            $fingerprint = $candidate['fingerprint'];
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
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
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
            $spec->subtype === 'sub' ? $subs[] = $spec : $tops[] = $spec;
        }

        usort($subs, self::byFrequency());
        usort($tops, self::byFrequency());

        return array_map(static fn (DeviceSpec $s): string => $s->id, [...$subs, ...$tops]);
    }


    /**
     * Lowest first, so the deepest cabinets end up on the floor carrying everything.
     *
     * On the **driven** corner where a spec states one, not the cabinet's own: our Achenbachs reach 35 Hz but
     * are high-passed at 38 like the Flexys, deliberately, so that they sit above them rather than under.
     * See {@see \App\Spec\Passband}.
     *
     * The high corner breaks a tie, and that tie is exactly the Flexy-versus-Achenbach case: both are driven
     * from 38 Hz, and the one that stops sooner — the Flexy at 200 Hz against the Achenbach's 1500 — is the
     * more sub-like of the two and belongs lower. A spec with no passband sorts last within its band and falls
     * back to how much row it can make, which puts the most numerous cabinet on the floor.
     *
     * @return callable(DeviceSpec, DeviceSpec): int
     */
    private static function byFrequency(): callable
    {
        return static function (DeviceSpec $a, DeviceSpec $b): int {
            $low = ($a->passband?->orderingLowHz() ?? INF) <=> ($b->passband?->orderingLowHz() ?? INF);
            if ($low !== 0) {
                return $low;
            }

            $high = ($a->passband?->highHz ?? INF) <=> ($b->passband?->highHz ?? INF);
            if ($high !== 0) {
                return $high;
            }

            return $b->quantity * $b->dimensions->width <=> $a->quantity * $a->dimensions->width;
        };
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


    private function describe(LayoutMode $mode): string
    {
        return 'Solved rig — tiers '.match ($mode) {
            LayoutMode::Center => 'centred',
            LayoutMode::Block => 'justified',
            LayoutMode::Stereo => 'split left and right',
        };
    }
}
