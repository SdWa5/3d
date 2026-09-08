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
     * A scene's **key**: its path under `scenes/`, without the extension.
     *
     * **THE BASENAME STOPPED BEING UNIQUE IN 0.98.0 AND NOTHING NOTICED FOR A RELEASE.** The inventory moved out of
     * the generated file name and into a folder, which was right, and from that moment 2072 generated scenes shared
     * 589 basenames — 421 of those names belonging to two or more inventories at once. Everything that keys a scene
     * by its `id` therefore keyed eleven different rigs the same way: `build/scenes/generated/<id>.blend` was written
     * by whichever inventory sorted first, and the other ten were then found to have an artifact newer than their own
     * source and were skipped as up to date. Silently, because a skipped rebuild looks exactly like a current one.
     *
     * **The key is the path because the path is the only thing that is unique for every scene in the repository.**
     * Not the axis tuple: `scenes/` also holds twenty hand-written scenes and a pack, which have no axes, and an
     * identity that exists for swept scenes alone is a second identity scheme rather than an identity.
     *
     * **The `id:` field inside the file keeps its job and loses the other one.** It is the label — what
     * `scene:build` prints, what reaches Blender's log — and it is no longer what anything is filed under. Today
     * every one of the 2092 scenes has an `id` equal to its basename, so the two agree wherever they are compared;
     * the point is that only one of them is guaranteed to.
     */
    public function keyOf(string $file): string
    {
        $root = rtrim(str_replace('\\', '/', $this->scenesDir), '/').'/';
        $path = str_replace('\\', '/', $file);
        $relative = str_starts_with($path, $root) ? substr($path, strlen($root)) : basename($path);

        return preg_replace('/\.ya?ml$/', '', $relative) ?? $relative;
    }

    /**
     * The directory a scene sits in, relative to `scenes/` — `generated/sdwa5-sepp`, `packs`, or the empty string
     * for a scene in the root.
     *
     * This is what every derived artifact's directory is composed from, so that a `.blend` and a `.png` are as
     * unique as the scene they come from. See {@see \App\Command\BaseCommand::derivedDir}.
     */
    public function relativeDirOf(string $file): string
    {
        $key = $this->keyOf($file);
        $slash = strrpos($key, '/');

        return false === $slash ? '' : substr($key, 0, $slash);
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
        if (false === $contents) {
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
     * **A BARE ID IS NO LONGER UNIQUE AND THIS USED TO TAKE THE FIRST ONE IT SAW.** The inventory moved out of the
     * generated file name and into a folder, which is right — a name inside a folder named after the system stated
     * it twice — and it made ten different rigs share the basename
     * `stacked-1-pooled--------free----turned--alternate-center-possible`. This method walked the file list and
     * returned on the first match, so `scene:build` on that name built whichever inventory sorted first, silently,
     * and a data provider keyed on those ids collapsed ten scenes into one. **A path always wins and is always
     * unambiguous**, so the fix is to keep looking rather than to return early, and to hand an ambiguity back as
     * one rather than resolving it by sort order.
     *
     * @return array{scene: SceneSpec|null, known: list<string>, ambiguous: list<string>} `ambiguous` holds the
     *                                                                                    matching paths when a bare id names more than one scene, and is empty otherwise
     */
    public function find(string $nameOrPath): array
    {
        $known = [];
        $matches = [];
        $wanted = realpath($nameOrPath);
        // **The key is tried before the basename**, because it is the only form that always resolves: every scene
        // has one and no two share one. `scene:build generated/gmss/stacked-1-…` is therefore always answerable,
        // where the basename it ends with names ten other rigs as well.
        $trimmed = preg_replace('/\.ya?ml$/', '', trim($nameOrPath, '/')) ?? $nameOrPath;
        foreach ($this->files() as $file) {
            if ($file === $nameOrPath || (false !== $wanted && realpath($file) === $wanted)) {
                return ['scene' => $this->load($file), 'known' => $known, 'ambiguous' => []];
            }
            if ($this->keyOf($file) === $trimmed) {
                return ['scene' => $this->load($file), 'known' => $known, 'ambiguous' => []];
            }

            $id = pathinfo($file, PATHINFO_FILENAME);
            $known[] = $this->keyOf($file);
            if ($id === $nameOrPath) {
                $matches[] = $file;
            }
        }

        if (1 === count($matches)) {
            return ['scene' => $this->load($matches[0]), 'known' => $known, 'ambiguous' => []];
        }

        return ['scene' => null, 'known' => $known, 'ambiguous' => $matches];
    }
}
