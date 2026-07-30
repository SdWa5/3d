<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One thing wrong with one spec. Carrying the file path rather than just the id means a
 * violation is still reportable when the id itself is the problem.
 */
final class Violation
{
    public function __construct(
        public readonly string $file,
        public readonly string $message,
    ) {
    }

    /**
     * Path relative to the project root, which is what a spec author recognises.
     */
    public function shortFile(string $projectDir): string
    {
        $prefix = rtrim($projectDir, '/').'/';

        return str_starts_with($this->file, $prefix)
            ? substr($this->file, strlen($prefix))
            : $this->file;
    }
}
