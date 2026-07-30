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
    public function __construct(private readonly string $scenesDir)
    {
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob(rtrim($this->scenesDir, '/').'/*.{yaml,yml}', GLOB_BRACE) ?: [];
        sort($files);

        return array_values($files);
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
