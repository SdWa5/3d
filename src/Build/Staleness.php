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
 */
final class Staleness
{
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
