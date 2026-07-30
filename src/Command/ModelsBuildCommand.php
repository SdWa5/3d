<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use App\Spec\DeviceSpec;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Generates the .glb and .blend for each spec.
 *
 * Validation runs first and a failure aborts the build: generating models from specs that break
 * the conventions would produce a library whose pieces no longer fit together, which is the one
 * thing this project exists to prevent.
 */
final class ModelsBuildCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('models:build')
            ->setDescription('Build .glb + .blend models from the specs using Blender')
            ->addOption(
                'id',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only build these spec ids (repeatable)',
            )
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rebuild even when the output is up to date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ($errors !== []) {
            $this->io->error('Some specs could not be read — fix them first (see `specs:validate`)');

            return self::FAILURE;
        }

        $violations = $this->validator()->validate($specs);
        if ($violations !== []) {
            $this->reportViolations($violations);
            $this->io->error('Specs are invalid — refusing to build. Run `bin/console specs:validate`');

            return self::FAILURE;
        }

        /** @var list<string> $onlyIds */
        $onlyIds = $input->getOption('id');
        $selected = $this->select($specs, $onlyIds);
        if ($selected === null) {
            return self::FAILURE;
        }
        if ($selected === []) {
            $this->io->warning('Nothing to build');

            return self::SUCCESS;
        }

        $builder = new ModelBuilder($this->projectDir(), new BlenderRunner($this->runner));
        $force = (bool)$input->getOption('force');
        $sink = $this->blenderOutputSink($output);

        $built = 0;
        $skipped = 0;
        foreach ($selected as $spec) {
            if (!$force && !$builder->isStale($spec)) {
                ++$skipped;
                $this->io->text("  <info>·</info> {$spec->id} up to date");
                continue;
            }

            $this->io->text("  <info>→</info> {$spec->id}");
            try {
                $builder->build($spec, $sink);
            } catch (RuntimeException $e) {
                $this->io->error($e->getMessage());

                return self::FAILURE;
            } catch (Throwable $e) {
                $this->io->error("Unexpected failure building {$spec->id}: ".$e->getMessage());

                return self::FAILURE;
            }
            ++$built;
        }

        $this->io->newLine();
        $this->io->success(sprintf(
            '%d model%s built, %d up to date → %s',
            $built,
            $built === 1 ? '' : 's',
            $skipped,
            $this->relative($builder->buildDir()),
        ));

        return self::SUCCESS;
    }

    /**
     * Narrows the build to the requested ids, reporting any that do not exist rather than
     * silently building nothing.
     *
     * @param list<DeviceSpec> $specs
     * @param list<string> $onlyIds
     * @return list<DeviceSpec>|null null when an id was not found
     */
    private function select(array $specs, array $onlyIds): ?array
    {
        if ($onlyIds === []) {
            return $specs;
        }

        $known = [];
        foreach ($specs as $spec) {
            $known[$spec->id] = $spec;
        }

        $missing = array_values(array_diff($onlyIds, array_keys($known)));
        if ($missing !== []) {
            $this->io->error('Unknown spec id(s): '.implode(', ', $missing));

            return null;
        }

        return array_values(array_map(static fn (string $id): DeviceSpec => $known[$id], $onlyIds));
    }
}
