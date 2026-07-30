<?php

declare(strict_types=1);

namespace App\Command;

use App\Spec\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks every spec file without touching Blender, which is why CI can run it: it is the gate
 * that keeps the library's shared conventions — one scale, one orientation, one origin, and a
 * known provenance for every number — from quietly rotting.
 */
final class SpecsValidateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('specs:validate')
            ->setDescription('Validate all equipment specs (no Blender required)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        $violations = $this->validator()->validate($specs);
        $failures = Violation::errorsIn($violations);

        if ($violations !== []) {
            $this->io->newLine();
            $this->reportViolations($violations);
        }

        $problems = count($errors) + count($failures);
        if ($problems > 0) {
            $this->io->newLine();
            $this->io->error(sprintf(
                '%d problem%s in %d spec%s',
                $problems,
                $problems === 1 ? '' : 's',
                count($specs) + count($errors),
                count($specs) + count($errors) === 1 ? '' : 's',
            ));

            return self::FAILURE;
        }

        if ($specs === []) {
            $this->io->warning('No specs found in '.$this->relative($this->specsDir()));

            return self::SUCCESS;
        }

        $warnings = Violation::warningsIn($violations);
        $this->io->success(sprintf(
            '%d spec%s valid%s',
            count($specs),
            count($specs) === 1 ? '' : 's',
            $warnings === [] ? '' : sprintf(' (%d warning%s)', count($warnings), count($warnings) === 1 ? '' : 's'),
        ));

        return self::SUCCESS;
    }
}
