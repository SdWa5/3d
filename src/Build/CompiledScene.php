<?php

declare(strict_types=1);

namespace App\Build;

use App\Render\RenderPlacement;
use App\Scene\PlacedDevice;
use App\Spec\Violation;

/**
 * A solved scene as `scene:build` leaves it for `scene:render`, `build/plans/<dir>/_compiled-<id>.json` (TOOL-11).
 *
 * **Build output, never an answer stored in a scene.** A scene file states constraints and is solved again from the
 * file on every `scene:build`. What is kept here is that build's result, valid exactly as long as nothing it was
 * solved from has moved, so the render passes that follow reuse it instead of solving the same file again. A record
 * that is missing, stale or unreadable is simply not used, and the render solves as it always did.
 *
 * **Stale is decided by {@see Staleness::outOfDate()}, against a deliberately wide set of inputs.** The scene file,
 * every spec, every event, all of `src/` and `composer.lock`. Narrower would be guessing which classes the solver
 * reaches, and a guess that misses one renders a picture framed on a rig the `.blend` no longer holds. Wider costs a
 * few hundred `stat` calls per command, which is nothing next to one solve.
 *
 * Only a solve **without errors** is ever written, so a scene the checks refuse is solved and refused in the render
 * every time, exactly as before.
 */
final class CompiledScene
{
    public const PLAN_VERSION = 1;

    public const PREFIX = '_compiled-';

    /**
     * Where a scene's record lives, given the plans directory already composed with the scene's own directory.
     */
    public static function fileIn(string $plansDir, string $sceneId): string
    {
        return rtrim($plansDir, '/').'/'.self::PREFIX.$sceneId.'.json';
    }

    /**
     * Every input a solve depends on apart from the scene file itself. Listed once per command, since none of it
     * changes while one runs.
     *
     * @return list<string>
     */
    public static function sharedInputs(string $projectDir): array
    {
        $inputs = [];
        foreach (['src', 'specs', 'events'] as $directory) {
            $inputs = [...$inputs, ...self::filesUnder($projectDir.'/'.$directory)];
        }
        $inputs[] = $projectDir.'/composer.lock';

        return $inputs;
    }

    /**
     * @param list<PlacedDevice> $placed
     * @param list<Violation> $warnings
     *
     * @throws \RuntimeException when the record cannot be written
     */
    public static function write(string $file, string $sceneId, array $placed, array $warnings): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }

        try {
            $json = json_encode(
                [
                    'plan_version' => self::PLAN_VERSION,
                    'scene_id' => $sceneId,
                    'placements' => array_map(
                        static fn (RenderPlacement $entry): array => $entry->toArray(),
                        RenderPlacement::listOf($placed),
                    ),
                    'warnings' => array_map(
                        static fn (Violation $warning): array => ['file' => $warning->file, 'message' => $warning->message],
                        $warnings,
                    ),
                ],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot encode the compiled scene: '.$e->getMessage(), 0, $e);
        }

        // Written beside the target and renamed over it, so a run killed halfway leaves the old record or the new
        // one and never half of one.
        $temporary = $file.'.tmp';
        if (false === @file_put_contents($temporary, $json."\n") || !@rename($temporary, $file)) {
            @unlink($temporary);

            throw new \RuntimeException("Cannot write the compiled scene to {$file}");
        }
    }

    /**
     * The stored solve, or null when there is none worth trusting.
     *
     * @param list<string> $inputs the scene file and {@see sharedInputs()}
     *
     * @return array{placements: list<RenderPlacement>, warnings: list<Violation>}|null
     */
    public static function read(string $file, string $sceneId, array $inputs): ?array
    {
        if (Staleness::outOfDate([$file], $inputs)) {
            return null;
        }
        $contents = @file_get_contents($file);
        if (false === $contents) {
            return null;
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data)
                || self::PLAN_VERSION !== ($data['plan_version'] ?? null)
                || $sceneId !== ($data['scene_id'] ?? null)
                || !is_array($data['placements'] ?? null)
                || !is_array($data['warnings'] ?? null)
            ) {
                return null;
            }

            $placements = [];
            foreach ($data['placements'] as $entry) {
                if (!is_array($entry)) {
                    return null;
                }
                $placements[] = RenderPlacement::fromArray($entry);
            }
            $warnings = [];
            foreach ($data['warnings'] as $entry) {
                if (!is_array($entry) || !is_string($entry['file'] ?? null) || !is_string($entry['message'] ?? null)) {
                    return null;
                }
                $warnings[] = new Violation($entry['file'], $entry['message'], Violation::WARNING);
            }
        } catch (\JsonException|\UnexpectedValueException) {
            return null;
        }

        return ['placements' => $placements, 'warnings' => $warnings];
    }

    /**
     * @return list<string>
     */
    private static function filesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
