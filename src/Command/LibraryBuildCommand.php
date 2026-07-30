<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Assembles the Blender asset library from the per-model .blend files.
 *
 * This is what makes "quickly try a different setup" real: every device shows up in Blender's
 * Asset Browser and can be dragged into a scene at correct scale, already sitting on the floor.
 */
final class LibraryBuildCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('library:build')
            ->setDescription('Assemble the Blender asset library from the built models');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ($errors !== []) {
            $this->io->error('Some specs could not be read — fix them first (see `specs:validate`)');

            return self::FAILURE;
        }
        if ($specs === []) {
            $this->io->warning('No specs found — nothing to put in the library');

            return self::SUCCESS;
        }

        $builder = new ModelBuilder($this->projectDir(), new BlenderRunner($this->runner));

        // Refusing here beats producing a library that silently misses half the gear.
        $stale = [];
        foreach ($specs as $spec) {
            if ($builder->isStale($spec)) {
                $stale[] = $spec->id;
            }
        }
        if ($stale !== []) {
            $this->io->error(sprintf(
                "These models are missing or out of date: %s\nRun `bin/console models:build` first.",
                implode(', ', $stale),
            ));

            return self::FAILURE;
        }

        try {
            $builder->buildLibrary($specs, $this->blenderOutputSink($output));
        } catch (RuntimeException $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        }

        $this->io->success(sprintf(
            '%d device%s in %s',
            count($specs),
            count($specs) === 1 ? '' : 's',
            $this->relative($builder->libraryPath()),
        ));
        $this->io->text('Register the folder as an asset library in Blender: Preferences → File Paths → Asset Libraries.');

        return self::SUCCESS;
    }
}
