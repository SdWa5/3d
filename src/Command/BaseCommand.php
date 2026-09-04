<?php

declare(strict_types=1);

namespace App\Command;

use App\Process\ProcessRunner;
use App\Process\ProcOpenProcessRunner;
use App\Scene\SceneLoader;
use App\Scene\SceneSpec;
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

    /**
     * Where the event rosters live — beside `specs/` rather than inside it, since {@see SpecLoader} would read one
     * as a broken device. {@see \App\Spec\RosterLoader} says why at length.
     */
    protected function rostersDir(): string
    {
        return $this->projectDir().'/rosters';
    }

    protected function scenesDir(): string
    {
        return $this->projectDir().'/scenes';
    }

    /**
     * `$directory` with the scene's **own directory under `scenes/`** composed onto it.
     *
     * One rule applied to every directory a scene produces something in — the assembled `.blend`, the build plan,
     * the renders — so that everything derived from `scenes/generated/gmss/x.yaml` lands in
     * `<directory>/generated/gmss/` and nothing generated is ever mixed in with work somebody wrote by hand.
     * Composed onto whatever directory the caller already decided on, `--out-dir` included, rather than replacing it.
     *
     * **IT USED TO APPEND `generated` AND NOTHING ELSE, AND THAT WAS A DATA-LOSS BUG FROM 0.98.0 ONWARDS.** The
     * derived file was then named by the scene's `id`, which is its basename — and basenames stopped being unique
     * the moment the inventory became a folder. Eleven rigs wrote one `.blend`: the first inventory in sort order
     * won, and every other scene of that name was found to have an artifact newer than its own source and skipped
     * as up to date. Mirroring the whole relative directory is what makes a derived path as unique as its scene.
     * {@see \App\Scene\SceneLoader::keyOf} carries the full argument.
     */
    protected function derivedDir(string $directory, SceneSpec $scene): string
    {
        $relative = (new SceneLoader($this->scenesDir()))->relativeDirOf($scene->sourcePath);

        return rtrim($directory, '/').($relative === '' ? '' : '/'.$relative);
    }

    /**
     * What a scene is filed under: its path below `scenes/`, without the extension.
     *
     * The one answer to "is this the same scene?" — used by the prune to compare the set that exists against the
     * set a run wrote, and by anything else that needs a name that cannot collide. `$scene->id` is the label and
     * is not that. See {@see \App\Scene\SceneLoader::keyOf}.
     */
    protected function sceneKey(SceneSpec $scene): string
    {
        return (new SceneLoader($this->scenesDir()))->keyOf($scene->sourcePath);
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
