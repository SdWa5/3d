<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Remembers which items passed a check, keyed on everything the check's answer depends on.
 *
 * **TOOL-22's lever for the library sweep.** `ShippedScenesTest` compiles every shipped scene on every run, 4 min 21 s
 * of an 11-minute suite, whether or not anything a scene depends on changed. A commit that touches only docs, `tools/`
 * or other tests changes none of it, and one that touches a few scenes changes only those.
 *
 * **The key is wide on purpose.** A stale entry that reports a broken rig as standing is worse than a slow suite, so
 * the fingerprint covers every file under the inputs rather than a guessed subset, plus the PHP version. Any change to
 * `src/` therefore misses every entry, which is the price of never being wrong about it.
 *
 * **Only passes are remembered.** A failing item is checked again on every run, so a failure report is always fresh
 * and a cache can only ever skip work whose answer was "fine".
 *
 * **CI never reads it**, and neither does a run with `SDWA5_TEST_CACHE=0`. Entries are empty files under
 * `build/test-cache/<name>/<fingerprint>/`, and opening the cache deletes the folders of every other fingerprint, so
 * it holds one tree's answers and does not grow with every commit.
 */
final class ResultCache
{
    private function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * The cache for this check and this tree, or null when caching is off.
     *
     * Call it before forking, so the fingerprint is hashed once and the children inherit it.
     *
     * @param list<string> $inputs files and folders relative to the project whose content the check depends on
     */
    public static function open(string $project, string $name, array $inputs): ?self
    {
        if ('0' === getenv('SDWA5_TEST_CACHE') || false !== getenv('CI')) {
            return null;
        }

        $root = $project.'/build/test-cache/'.$name;
        $fingerprint = self::fingerprint($project, $inputs);

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $stale) {
            if (basename($stale) !== $fingerprint) {
                self::remove($stale);
            }
        }

        return new self($root.'/'.$fingerprint);
    }

    /**
     * A hash over every file under the inputs, each as its relative path and its content, and the PHP version.
     *
     * The path is in it as well as the content, because a file moved from one folder to another can change what the
     * code does with it.
     *
     * @param list<string> $inputs
     */
    public static function fingerprint(string $project, array $inputs): string
    {
        $files = [];
        foreach ($inputs as $input) {
            $path = $project.'/'.$input;
            if (is_file($path)) {
                $files[$input] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $files[substr($file->getPathname(), strlen($project) + 1)] = $file->getPathname();
                }
            }
        }
        ksort($files);

        $context = hash_init('sha256');
        hash_update($context, PHP_VERSION."\n");
        foreach ($files as $relative => $file) {
            hash_update($context, $relative."\0".hash_file('sha256', $file)."\n");
        }

        return hash_final($context);
    }

    /** Whether this item was recorded as passing under the same fingerprint. */
    public function passed(string $item): bool
    {
        return is_file($this->path($item));
    }

    /**
     * Records that this item passed. Safe from forked children at once, since every item has its own file and an empty
     * file cannot be half written.
     */
    public function recordPass(string $item): void
    {
        $path = $this->path($item);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return;
        }
        @touch($path);
    }

    private function path(string $item): string
    {
        $key = hash('sha256', $item);

        return $this->directory.'/'.substr($key, 0, 2).'/'.$key;
    }

    private static function remove(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
        }
        @rmdir($directory);
    }
}
