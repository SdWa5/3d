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
 * writing the settings beside the output and comparing them, which is a change to how freshness is *keyed*
 * rather than to what it watches.
 */
final class Staleness
{
    /**
     * Where an output's settings are recorded — hidden, and beside the file it describes.
     *
     * Beside it rather than in a manifest so that nothing has to be read-modify-written, and so that moving or
     * deleting an output cannot leave a lie behind: a stamp with no output is never consulted, because a missing
     * output is already stale.
     */
    public static function stampFor(string $output): string
    {
        return dirname($output).'/.'.basename($output).'.built-with.json';
    }

    /**
     * Whether an output exists but was built with settings other than these.
     *
     * An output with **no stamp** counts as changed, which is what makes this self-healing: everything built
     * before stamps existed re-renders once, at whatever it is now being asked for, and carries a stamp
     * afterwards. A missing output is not this rule's business — {@see outOfDate} already says so.
     *
     * @param array<string, mixed> $settings must be JSON-encodable, and is compared after a round trip so that
     *                                       an int and a float that read the same in JSON are the same settings
     */
    public static function settingsChanged(string $output, array $settings): bool
    {
        if (!is_file($output)) {
            return false;
        }

        $stamp = self::stampFor($output);
        if (!is_file($stamp)) {
            return true;
        }

        try {
            $recorded = json_decode((string)file_get_contents($stamp), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // A stamp nobody can read is a stamp that tells us nothing, so rebuild rather than trust it.
            return true;
        }

        return $recorded !== self::normalised($settings);
    }

    /**
     * Record what an output was built with, so the next run can tell instructions apart from inputs.
     *
     * Called after the build succeeds, never before: a stamp written ahead of a render that then failed would
     * claim the old picture was made with the new settings, which is the one way this could rebuild too little.
     *
     * @param array<string, mixed> $settings
     */
    public static function recordSettings(string $output, array $settings): bool
    {
        $json = json_encode(
            self::normalised($settings),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        return @file_put_contents(self::stampFor($output), $json."\n") !== false;
    }

    /**
     * The settings as JSON sees them, with keys in a fixed order.
     *
     * Both sides of the comparison go through this, so neither the order the caller happened to build the array
     * in nor `128` against `128.0` reads as a change worth re-rendering for.
     *
     * @param array<string, mixed> $settings
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
            $time = (int)filemtime($output);
            $oldestOutput = $oldestOutput === null ? $time : min($oldestOutput, $time);
        }
        if ($oldestOutput === null) {
            // Nothing claimed to be built, so there is nothing to be up to date.
            return true;
        }

        foreach ($inputs as $input) {
            // A missing input cannot have changed. It is also not this check's business to complain about —
            // the stage that needs it will fail with a better message than "stale" would be.
            if (is_file($input) && (int)filemtime($input) > $oldestOutput) {
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
