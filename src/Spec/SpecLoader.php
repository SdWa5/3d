<?php

declare(strict_types=1);

namespace App\Spec;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads spec files from disk. Files are discovered recursively under the specs directory and
 * sorted by path, so command output and generated docs have a stable order.
 *
 * A file that cannot be parsed at all is reported as a load error rather than thrown out of the
 * loader: one broken spec must not hide the state of every other spec in the library.
 */
final class SpecLoader
{
    public function __construct(private readonly string $specsDir)
    {
    }

    /**
     * @return list<string> absolute paths of all spec files, sorted
     */
    public function files(): array
    {
        if (!is_dir($this->specsDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->specsDir, \FilesystemIterator::SKIP_DOTS),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Loads a single spec file.
     *
     * @throws InvalidSpecException when the file is unreadable, is not a YAML mapping, or a
     *                             required field is missing or of the wrong type
     */
    public function load(string $file): DeviceSpec
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new InvalidSpecException('cannot read file');
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new InvalidSpecException('invalid YAML: '.$e->getMessage(), 0, $e);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidSpecException('expected a YAML mapping at the top level');
        }

        /** @var array<string, mixed> $data */
        return DeviceSpec::fromArray($data, $file);
    }

    /**
     * Loads every spec file, separating the ones that parsed from the ones that did not.
     *
     * @return array{specs: list<DeviceSpec>, errors: array<string, string>} errors keyed by file path
     */
    public function loadAll(): array
    {
        $specs = [];
        $errors = [];
        foreach ($this->files() as $file) {
            try {
                $specs[] = $this->load($file);
            } catch (InvalidSpecException $e) {
                $errors[$file] = $e->getMessage();
            }
        }

        return ['specs' => $specs, 'errors' => $errors];
    }
}
