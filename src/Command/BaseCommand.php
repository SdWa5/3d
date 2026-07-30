<?php

declare(strict_types=1);

namespace App\Command;

use App\Process\ProcessRunner;
use App\Process\ProcOpenProcessRunner;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\SpecValidator;
use App\Spec\Violation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared plumbing for the console commands: paths, spec loading and violation reporting.
 *
 * The ProcessRunner is injected with a real default so tests can swap in a fake without the
 * commands needing a container.
 */
abstract class BaseCommand extends Command
{
    protected SymfonyStyle $io;

    protected readonly ProcessRunner $runner;

    public function __construct(?ProcessRunner $runner = null, ?string $name = null)
    {
        parent::__construct($name);
        $this->runner = $runner ?? new ProcOpenProcessRunner();
    }

    /**
     * Repository root — src/Command is two levels below it.
     */
    protected function projectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function specsDir(): string
    {
        return $this->projectDir().'/specs';
    }

    protected function loader(): SpecLoader
    {
        return new SpecLoader($this->specsDir());
    }

    protected function validator(): SpecValidator
    {
        return new SpecValidator($this->projectDir());
    }

    /**
     * Loads every spec, printing whatever failed to parse. Returns only the usable specs, so a
     * caller can decide whether to continue with a partial library or stop.
     *
     * @return array{specs: list<DeviceSpec>, errors: array<string, string>}
     */
    protected function loadSpecs(): array
    {
        $result = $this->loader()->loadAll();
        foreach ($result['errors'] as $file => $message) {
            $this->io->text(sprintf('<error>%s</error>: %s', $this->relative($file), $message));
        }

        return $result;
    }

    /**
     * @param list<Violation> $violations
     */
    protected function reportViolations(array $violations): void
    {
        $byFile = [];
        foreach ($violations as $violation) {
            $byFile[$violation->shortFile($this->projectDir())][] = $violation;
        }
        ksort($byFile);

        foreach ($byFile as $file => $entries) {
            $this->io->text("<comment>{$file}</comment>");
            foreach ($entries as $entry) {
                $marker = $entry->isError() ? '<error>✗</error>' : '<comment>!</comment>';
                $this->io->text("  {$marker} {$entry->message}");
            }
        }
    }

    /**
     * Validates and separates the two, so a caller can refuse on errors while still surfacing
     * warnings.
     *
     * @param list<DeviceSpec> $specs
     * @return array{errors: list<Violation>, warnings: list<Violation>}
     */
    protected function checkSpecs(array $specs): array
    {
        $violations = $this->validator()->validate($specs);
        $warnings = Violation::warningsIn($violations);
        if ($warnings !== []) {
            $this->reportViolations($warnings);
        }

        return ['errors' => Violation::errorsIn($violations), 'warnings' => $warnings];
    }

    protected function relative(string $path): string
    {
        $prefix = $this->projectDir().'/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    /**
     * Streams Blender's output only when the user asked for it — a build otherwise prints
     * thousands of lines nobody reads.
     *
     * @return callable(string):void|null
     */
    protected function blenderOutputSink(OutputInterface $output): ?callable
    {
        if (!$output->isVerbose()) {
            return null;
        }

        return static function (string $chunk) use ($output): void {
            $output->write($chunk);
        };
    }
}
