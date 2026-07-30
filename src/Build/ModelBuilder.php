<?php

declare(strict_types=1);

namespace App\Build;

use App\Spec\DeviceSpec;
use JsonException;
use RuntimeException;

/**
 * Turns specs into model files. One Blender process per model: slower than batching them all
 * into a single run, but a broken spec then fails on its own instead of taking the whole build
 * down with it, and the up-to-date check can skip individual models.
 */
final class ModelBuilder
{
    public const MODEL_SCRIPT = 'blender/build_model.py';

    public const LIBRARY_SCRIPT = 'blender/build_library.py';

    public function __construct(
        private readonly string $projectDir,
        private readonly BlenderRunner $blender,
    ) {
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
     */
    public function isStale(DeviceSpec $spec): bool
    {
        $glb = $this->glbPath($spec);
        $blend = $this->blendPath($spec);
        if (!is_file($glb) || !is_file($blend)) {
            return true;
        }

        $outputTime = min((int)filemtime($glb), (int)filemtime($blend));
        $inputs = [$spec->sourcePath, ...$this->modelInputScripts()];
        if ($spec->meshOverride !== null) {
            $inputs[] = $this->resolve($spec->meshOverride);
        }

        foreach ($inputs as $input) {
            if (is_file($input) && (int)filemtime($input) > $outputTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds one model and verifies the outputs actually appeared — Blender can exit 0 after a
     * script error, so a silent no-op would otherwise look like success.
     *
     * @param callable(string):void|null $onOutput
     * @throws RuntimeException when the build fails or produces nothing
     */
    public function build(DeviceSpec $spec, ?callable $onOutput = null): void
    {
        $glb = $this->glbPath($spec);
        $blend = $this->blendPath($spec);
        $this->ensureDir(dirname($glb));
        $this->ensureDir(dirname($blend));

        $planFile = $this->writePlan($spec, $glb, $blend);
        $this->blender->run($this->resolve(self::MODEL_SCRIPT), $planFile, $onOutput);

        foreach ([$glb, $blend] as $expected) {
            if (!is_file($expected)) {
                throw new RuntimeException(sprintf(
                    'Blender reported success but did not write %s — see the build log above',
                    $expected,
                ));
            }
        }
    }

    /**
     * Assembles the asset library from the per-model .blend files. Expects those to exist, so
     * callers run a model build first.
     *
     * @param list<DeviceSpec> $specs
     * @param callable(string):void|null $onOutput
     * @throws RuntimeException when the library cannot be written
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
                'provenance' => $spec->provenance->value,
                // Used to lay the devices out side by side in the library file.
                'width_m' => $spec->dimensions->width,
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
            throw new RuntimeException("Blender did not write the asset library at {$library}");
        }
    }

    public function buildDir(): string
    {
        return $this->projectDir.'/build';
    }

    private function writePlan(DeviceSpec $spec, string $glb, string $blend): string
    {
        $planFile = $this->buildDir().'/plans/'.$spec->id.'.json';
        $this->writeJson($planFile, BuildPlan::forSpec($spec, $glb, $blend));

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
        } catch (JsonException $e) {
            throw new RuntimeException("Cannot encode build plan for {$file}: ".$e->getMessage(), 0, $e);
        }
        if (@file_put_contents($file, $json."\n") === false) {
            throw new RuntimeException("Cannot write build plan to {$file}");
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
            throw new RuntimeException("Cannot create directory {$dir}");
        }
    }

    private function resolve(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->projectDir.'/'.$path;
    }
}
