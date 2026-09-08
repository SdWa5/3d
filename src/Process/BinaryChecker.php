<?php

declare(strict_types=1);

namespace App\Process;

final class BinaryChecker
{
    public function __construct(private readonly ProcessRunner $runner)
    {
    }

    /**
     * Preflight-checks that $bin is available. Without $versionArg, resolves it via `which`
     * and returns the resolved path. With $versionArg, runs `$bin $versionArg` instead (for
     * binaries better probed by actually invoking them) and returns $bin unchanged.
     */
    public function __invoke(string $bin, ?string $versionArg = null): string
    {
        if (null !== $versionArg) {
            $cmd = escapeshellcmd($bin).' '.$versionArg;
            [$exit] = $this->runner->run($cmd.' 2>&1');
            if (0 !== $exit) {
                throw new \RuntimeException("Missing dependency: $bin. Please install it and ensure it's in PATH.");
            }

            return $bin;
        }

        [$exit, $out] = $this->runner->run('which '.escapeshellarg($bin).' 2>/dev/null');
        if (0 !== $exit || '' === trim($out)) {
            throw new \RuntimeException("Required binary not found: {$bin}");
        }

        return trim($out);
    }
}
