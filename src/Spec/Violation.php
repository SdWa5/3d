<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One thing wrong with one spec. Carrying the file path rather than just the id means a
 * violation is still reportable when the id itself is the problem.
 */
final class Violation
{
    public const ERROR = 'error';

    /**
     * Something worth saying but not worth refusing over. The case that forced this to exist: an
     * override mesh is third-party CAD that cannot be committed, so a spec may legitimately name a
     * file this checkout does not have. That must not make the repository invalid for everyone else.
     */
    public const WARNING = 'warning';

    public function __construct(
        public readonly string $file,
        public readonly string $message,
        public readonly string $severity = self::ERROR,
    ) {
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    /**
     * @param list<self> $violations
     * @return list<self>
     */
    public static function errorsIn(array $violations): array
    {
        return array_values(array_filter($violations, static fn (self $v): bool => $v->isError()));
    }

    /**
     * @param list<self> $violations
     * @return list<self>
     */
    public static function warningsIn(array $violations): array
    {
        return array_values(array_filter($violations, static fn (self $v): bool => !$v->isError()));
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
