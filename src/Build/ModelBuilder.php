<?php

declare(strict_types=1);

namespace App\Build;

use App\Spec\DeviceSpec;

/**
 * Turns specs into model files. One Blender process per model: slower than batching them all
 * into a single run, but a broken spec then fails on its own instead of taking the whole build
 * down with it, and the up-to-date check can skip individual models.
 */
final class ModelBuilder
{
    public const MODEL_SCRIPT = 'blender/build_model.py';

    public const LIBRARY_SCRIPT = 'blender/build_library.py';

    /**
     * @param bool $frontImages whether a spec's `front_image` is applied at all. **Off by default**,
     *                          and deliberately a constructor argument rather than only a command
     *                          option, so the plain library stays what a caller gets without asking.
     *                          A run that flips it is caught by {@see Staleness::settingsChanged}
     *                          rather than by mtimes, because nothing on disk moves when a switch does
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly BlenderRunner $blender,
        private readonly bool $frontImages = false,
    ) {
    }

    /**
     * The settings stamp for this builder, recorded per output tree so a model built without
     * photographs is not mistaken for one built with them.
     *
     * @return array<string, mixed>
     */
    public function builtWith(): array
    {
        return ['front_images' => $this->frontImages];
    }

    public function glbPath(DeviceSpec $spec): string
    {
        return $this->buildDir().'/glb/'.$spec->id.'.glb';
    }

    public function blendPath(DeviceSpec $spec): string
    {
        return $this->buildDir().'/blend/'.$spec->id.'.blend';
    }

    public function libraryPath(): string
    {
        return $this->buildDir().'/library/sdwa5-3d.blend';
    }

    /**
     * A model is stale when its output is missing or older than any input that shapes it — the
     * spec file itself and the bpy scripts. Comparing against the scripts matters: a change to
     * the geometry builder has to rebuild everything, not just edited specs.
     *
     * **Every category produces a model, and for one afternoon one of them did not.** A vehicle was introduced as
     * the category that is never drawn, on the argument that a 6.8 m solid van would be the largest object in any
     * picture including it. That argument was right about the *solid* and wrong about the *model*: the owner asked
     * for wire-type vans so a pack can be planned, so a transporter is now drawn as its own outline with its load
     * bay caged inside it — {@see \App\Spec\Shape::LoadBay}.
     *
     * The half-day in between is worth a line, because it broke `build:all` outright and the loop it broke it in
     * was a good one: `models:build` skipped the vehicles on purpose, and `library:build` then refused to run
     * because two models were missing, advising *Run `bin/console models:build` first* — the command that had just
     * declined to build them.
     */
    public function isStale(DeviceSpec $spec): bool
    {
        $inputs = [$spec->sourcePath, ...$this->modelInputScripts()];
        if (null !== $spec->meshOverride) {
            $inputs[] = $this->resolve($spec->meshOverride->path);
        }
        // Counted whether or not front images are switched on for this run. A photograph that changed
        // while the feature was off still has to rebuild the model the moment it is switched back on,
        // and the setting itself is watched separately through Staleness::settingsChanged.
        if (null !== $spec->frontImage) {
            $inputs[] = $this->resolve($spec->frontImage->path);
        }

        // One rule for the whole pipeline; see {@see Staleness} for why it is mtimes rather than hashes.
        return Staleness::outOfDate([$this->glbPath($spec), $this->blendPath($spec)], $inputs);
    }

    /**
     * Builds one model and verifies the outputs actually appeared — Blender can exit 0 after a
     * script error, so a silent no-op would otherwise look like success.
     *
     * @param callable(string):void|null $onOutput
     *
     * @throws \RuntimeException when the build fails or produces nothing
     */
    public function build(DeviceSpec $spec, ?callable $onOutput = null): void
    {
        $glb = $this->glbPath($spec);
        $blend = $this->blendPath($spec);
        $this->ensureDir(dirname($glb));
        $this->ensureDir(dirname($blend));

        $planFile = $this->writePlan($spec, $glb, $blend);

        // Checking only that the outputs exist is not enough: a script error leaves the previous
        // build's files in place, and Blender can still exit 0, so a crash looks exactly like a
        // success. Requiring them to be rewritten is what actually catches it.
        $startedAt = time();
        $this->blender->run($this->resolve(self::MODEL_SCRIPT), $planFile, $onOutput);

        foreach ([$glb, $blend] as $expected) {
            if (!is_file($expected)) {
                throw new \RuntimeException(sprintf('Blender reported success but did not write %s — see the build log above', $expected));
            }
            if ((int) filemtime($expected) < $startedAt) {
                throw new \RuntimeException(sprintf("Blender reported success but left %s untouched — the script failed part way.\n".'Re-run with -v to see the traceback.', $expected));
            }
        }
    }

    /**
     * Assembles the asset library from the per-model .blend files. Expects those to exist, so
     * callers run a model build first.
     *
     * @param list<DeviceSpec> $specs
     * @param callable(string):void|null $onOutput
     *
     * @throws \RuntimeException when the library cannot be written
     */
    public function buildLibrary(array $specs, ?callable $onOutput = null): void
    {
        $library = $this->libraryPath();
        $this->ensureDir(dirname($library));

        $entries = [];
        foreach ($specs as $spec) {
            $entries[] = [
                'id' => $spec->id,
                'name' => $spec->name,
                'category' => $spec->category->value,
                'subtype' => $spec->subtype,
                'provenance' => $spec->provenance->label(),
                // Used to lay the devices out side by side in the library file. A wind-up stand's legs reach past its
                // column to the base spread, so the spread is what it needs beside its neighbours.
                'width_m' => $spec->mast->baseSpread ?? $spec->dimensions->width,
                'blend' => $this->blendPath($spec),
            ];
        }

        $planFile = $this->buildDir().'/plans/_library.json';
        $this->writeJson($planFile, [
            'plan_version' => 1,
            'output' => $library,
            'devices' => $entries,
        ]);

        $this->blender->run($this->resolve(self::LIBRARY_SCRIPT), $planFile, $onOutput);

        if (!is_file($library)) {
            throw new \RuntimeException("Blender did not write the asset library at {$library}");
        }
    }

    public function buildDir(): string
    {
        return $this->projectDir.'/build';
    }

    private function writePlan(DeviceSpec $spec, string $glb, string $blend): string
    {
        $planFile = $this->buildDir().'/plans/'.$spec->id.'.json';
        // A declared-but-absent override falls back to the generated block: the meshes are
        // third-party CAD this repository does not commit, so not having them is normal.
        $overridePath = null;
        if (null !== $spec->meshOverride) {
            $candidate = $this->resolve($spec->meshOverride->path);
            $overridePath = is_file($candidate) ? $candidate : null;
        }
        // Same fallback for a front photograph, and additionally off unless this run asked for it.
        $frontImagePath = null;
        if ($this->frontImages && null !== $spec->frontImage) {
            $candidate = $this->resolve($spec->frontImage->path);
            $frontImagePath = is_file($candidate) ? $candidate : null;
        }
        $this->writeJson($planFile, BuildPlan::forSpec($spec, $glb, $blend, $overridePath, $frontImagePath));

        return $planFile;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $file, array $data): void
    {
        $this->ensureDir(dirname($file));
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Cannot encode build plan for {$file}: ".$e->getMessage(), 0, $e);
        }
        if (false === @file_put_contents($file, $json."\n")) {
            throw new \RuntimeException("Cannot write build plan to {$file}");
        }
    }

    /**
     * Scripts whose contents shape a single model's geometry. The library assembler is
     * deliberately absent: it only stitches finished models together, so editing it must not
     * invalidate every model in the library.
     *
     * @return list<string>
     */
    private function modelInputScripts(): array
    {
        $scripts = [$this->resolve(self::MODEL_SCRIPT)];
        foreach (glob($this->resolve('blender/lib').'/*.py') ?: [] as $lib) {
            $scripts[] = $lib;
        }

        return $scripts;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }
    }

    private function resolve(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->projectDir.'/'.$path;
    }
}
