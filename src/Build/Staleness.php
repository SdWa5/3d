<?php

declare(strict_types=1);

namespace App\Build;

/**
 * Whether something built is older than what it was built from.
 *
 * One rule, used by all three build stages, because a pipeline where each stage decides freshness its own way
 * is a pipeline that rebuilds too much or — far worse — too little. It was already {@see ModelBuilder::isStale}'s
 * rule; it is here so the scene assembly and the renders can share it rather than grow near-copies.
 *
 * **Mtimes, not hashes**, deliberately. A hash would avoid rebuilding after a no-op edit, and would cost a read
 * of every input on every check for a saving nobody has asked for. Mtimes also make the *chain* work with no
 * extra bookkeeping: a spec is newer than its model, so the model rebuilds; the model is then newer than the
 * scene, so the scene reassembles; the scene is then newer than the render, so the render redraws. Each stage
 * only compares its own neighbours and the whole cascade falls out.
 *
 * The comparison takes the **oldest** output and the **newest** input, so a stage with several outputs — a
 * model is a `.glb` and a `.blend` — is fresh only when every one of them is.
 *
 * **Mtimes cannot answer one question**, though, and that is the second rule here: whether an output was built
 * with the *settings being asked for now*. Nothing on disk moves when the default resolution is raised or
 * `--lighting=stage` is passed, so a 1600×900 studio render stayed "current" against a request for a Full HD
 * one — the inputs really had not changed, only the instructions had. {@see settingsChanged} closes that by
 * recording the settings in one `built-with.json` per output tree and comparing them, which is a change to how
 * freshness is *keyed* rather than to what it watches.
 */
final class Staleness
{
    /**
     * The one file a tree of outputs records its settings in — `built-with.json` at the root of that tree.
     *
     * One manifest rather than a stamp beside every output, because the per-output form meant 381 hidden files
     * interleaved with 381 pictures. A single readable record of how everything in the tree was made is worth
     * more than the read-modify-write it costs, and the stages are sequential so there is no writer to race.
     */
    public static function manifestIn(string $directory): string
    {
        return rtrim($directory, '/').'/built-with.json';
    }

    /**
     * Whether an output exists but was built with settings other than these.
     *
     * An output **absent from the manifest** counts as changed, which is what makes this self-healing:
     * everything built before the manifest existed rebuilds once, at whatever it is now being asked for, and is
     * recorded afterwards. A missing output is not this rule's business — {@see outOfDate} already says so.
     *
     * @param array<string, mixed> $settings must be JSON-encodable, and is compared after a round trip so that
     *                                       an int and a float that read the same in JSON are the same settings
     */
    public static function settingsChanged(string $manifest, string $output, array $settings): bool
    {
        if (!is_file($output)) {
            return false;
        }

        $recorded = self::manifest($manifest)[self::keyFor($manifest, $output)] ?? null;

        return $recorded !== self::normalised($settings);
    }

    /**
     * Record what one output was built with, leaving every other entry in the manifest alone.
     *
     * Called after the build succeeds, never before: an entry written ahead of a render that then failed would
     * claim the old picture was made with the new settings, which is the one way this could rebuild too little.
     *
     * @param array<string, mixed> $settings
     */
    public static function recordSettings(string $manifest, string $output, array $settings): bool
    {
        $entries = self::manifest($manifest);
        $entries[self::keyFor($manifest, $output)] = self::normalised($settings);
        ksort($entries);

        $json = json_encode($entries, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $dir = dirname($manifest);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            return false;
        }

        return false !== @file_put_contents($manifest, $json."\n");
    }

    /**
     * The manifest as it stands, or empty when there is none — or when it cannot be read.
     *
     * A manifest nobody can parse tells us nothing, so everything in its tree reads as changed and rebuilds,
     * rather than being trusted. That is the safe direction: too eager costs a re-render, too lazy costs a
     * picture that does not match what was asked for.
     *
     * @return array<string, mixed>
     */
    private static function manifest(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $entries = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($entries) ? $entries : [];
    }

    /**
     * How one output is named inside the manifest: its path relative to the manifest's own directory.
     *
     * Relative so the entries stay readable and stay true if the build tree moves — `studio/full-rig-side.png`
     * rather than an absolute path from whichever machine last rendered. An output somewhere else entirely, which
     * `--out` allows, keeps its full path, since there is nothing to be relative to.
     */
    private static function keyFor(string $manifest, string $output): string
    {
        $root = realpath(dirname($manifest)) ?: rtrim(dirname($manifest), '/');
        // `realpath` on the output itself is no use: it has to work for a file about to be written. Its
        // directory does exist, which is enough to compare the two.
        $directory = realpath(dirname($output)) ?: rtrim(dirname($output), '/');
        $full = $directory.'/'.basename($output);

        return str_starts_with($full, $root.'/') ? substr($full, strlen($root) + 1) : $full;
    }

    /**
     * The settings as JSON sees them, with keys in a fixed order.
     *
     * Both sides of the comparison go through this, so neither the order the caller happened to build the array
     * in nor `128` against `128.0` reads as a change worth re-rendering for.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function normalised(array $settings): array
    {
        ksort($settings);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(json_encode($settings, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<string> $outputs
     * @param list<string> $inputs
     */
    public static function outOfDate(array $outputs, array $inputs): bool
    {
        $oldestOutput = null;
        foreach ($outputs as $output) {
            if (!is_file($output)) {
                return true;
            }
            $time = (int) filemtime($output);
            $oldestOutput = null === $oldestOutput ? $time : min($oldestOutput, $time);
        }
        if (null === $oldestOutput) {
            // Nothing claimed to be built, so there is nothing to be up to date.
            return true;
        }

        foreach ($inputs as $input) {
            // A missing input cannot have changed. It is also not this check's business to complain about —
            // the stage that needs it will fail with a better message than "stale" would be.
            if (is_file($input) && (int) filemtime($input) > $oldestOutput) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every Python file a Blender stage runs, so editing the geometry or the lighting rebuilds what it
     * affects. The whole of `blender/lib` counts for any script, because they all import from it.
     *
     * @return list<string>
     */
    public static function blenderInputs(string $projectDir, string $script): array
    {
        $inputs = [$projectDir.'/'.$script];
        foreach (glob($projectDir.'/blender/lib/*.py') ?: [] as $lib) {
            $inputs[] = $lib;
        }

        return $inputs;
    }
}
