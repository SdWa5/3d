<?php

declare(strict_types=1);

namespace App\Build;

use App\Process\BinaryChecker;
use App\Process\ProcessRunner;
use RuntimeException;

/**
 * Invokes Blender in background mode on one of the bpy scripts.
 *
 * `--factory-startup` is deliberate: without it a build would inherit whatever add-ons and unit
 * settings the person running it happens to have configured, and models would stop being
 * reproducible. The script itself enables the glTF exporter it needs.
 */
final class BlenderRunner
{
    private ?string $resolvedBinary = null;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly string $binary = 'blender',
    ) {
    }

    /**
     * Resolves the Blender binary once and remembers it, so a multi-model build does not run
     * `which` for every single spec.
     *
     * @throws RuntimeException when Blender is not installed
     */
    public function binary(): string
    {
        if ($this->resolvedBinary === null) {
            $this->resolvedBinary = (new BinaryChecker($this->runner))($this->binary);
        }

        return $this->resolvedBinary;
    }

    /**
     * Everything after `--` goes to the script rather than to Blender itself.
     */
    public function buildCommand(string $binary, string $script, string $planFile): string
    {
        return implode(' ', [
            escapeshellarg($binary),
            '--background',
            '--factory-startup',
            '--python',
            escapeshellarg($script),
            '--',
            '--plan',
            escapeshellarg($planFile),
        ]);
    }

    /**
     * Runs a bpy script against a plan file. Blender exits 0 even for some script failures, so
     * callers additionally check that the expected output files appeared.
     *
     * @param callable(string):void|null $onOutput raw output chunks, for verbose mode
     * @throws RuntimeException when Blender exits non-zero
     */
    public function run(string $script, string $planFile, ?callable $onOutput = null): string
    {
        $cmd = $this->buildCommand($this->binary(), $script, $planFile);
        $collected = '';
        $collect = static function (string $chunk) use (&$collected, $onOutput): void {
            $collected .= $chunk;
            if ($onOutput !== null) {
                $onOutput($chunk);
            }
        };

        [$exit] = $this->runner->run($cmd, $collect, $collect);
        if ($exit !== 0) {
            throw new RuntimeException(sprintf(
                "Blender failed (exit %d) running %s:\n%s",
                $exit,
                basename($script),
                self::tail($collected),
            ));
        }

        return $collected;
    }

    /**
     * Blender is extremely chatty; only the end of its output is useful in an error message.
     */
    private static function tail(string $output, int $lines = 20): string
    {
        $all = preg_split('/\R/', trim($output)) ?: [];

        return implode("\n", array_slice($all, -$lines));
    }
}
