<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\InvalidSpecException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads scene files from `scenes/`. Same shape as SpecLoader on purpose — one way to read a YAML
 * file in this project, not two.
 */
final class SceneLoader
{
    /**
     * The subdirectory generated output lives in, under `scenes/` and under each build directory alike.
     *
     * One name in one place, because the whole point of it is that a generated scene and everything derived from
     * it share the same marker: `scenes/generated/x.yaml` builds to `build/scenes/generated/x.blend` and renders
     * to `build/renders/generated/x-*.png`. Generated scenes used to sit among the hand-written ones with nothing
     * telling them apart, so a regeneration silently rewrote a file somebody had read.
     */
    public const GENERATED = 'generated';

    public function __construct(private readonly string $scenesDir)
    {
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        if (!is_dir($this->scenesDir)) {
            return [];
        }

        // Recursive, the same way {@see \App\Spec\SpecLoader} reads `specs/` — which is what "same shape as
        // SpecLoader" was always meant to include. It is what lets generated scenes live in `scenes/generated/`
        // without a single command having to know that directory exists: `scene:build stacked-center` still
        // resolves by bare id, because an id has always been the file's basename rather than its path.
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scenesDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && in_array(strtolower($file->getExtension()), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return array_values($files);
    }

    /**
     * Whether this scene was written by `scene:stack` rather than by a person — which is to say, whether it lives
     * under `generated/`.
     *
     * Read off the path rather than recorded in the file, because the path is the thing that cannot get out of
     * date: a generated scene copied into `scenes/` by hand *becomes* hand-written, comments and all, and a flag
     * inside it would still claim otherwise. Everything derived from a generated scene goes to a matching
     * `generated/` subdirectory, so this is the one question the build and render commands ask.
     */
    public static function isGenerated(SceneSpec $scene): bool
    {
        return str_contains(str_replace('\\', '/', $scene->sourcePath), '/'.self::GENERATED.'/');
    }

    /**
     * @throws InvalidSpecException
     */
    public function load(string $file): SceneSpec
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
        return SceneSpec::fromArray($data, $file);
    }

    /**
     * Finds a scene by id or by path, so `scene:build staudham` and
     * `scene:build scenes/staudham.yaml` both work.
     *
     * @return array{scene: SceneSpec|null, known: list<string>}
     */
    public function find(string $nameOrPath): array
    {
        $known = [];
        foreach ($this->files() as $file) {
            if ($file === $nameOrPath || realpath($file) === realpath($nameOrPath)) {
                return ['scene' => $this->load($file), 'known' => $known];
            }

            $id = pathinfo($file, PATHINFO_FILENAME);
            $known[] = $id;
            if ($id === $nameOrPath) {
                return ['scene' => $this->load($file), 'known' => $known];
            }
        }

        return ['scene' => null, 'known' => $known];
    }
}
