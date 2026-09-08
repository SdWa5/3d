<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * A deterministic sample of the generated scene set, for the two tests whose cost is one solve per scene.
 *
 * **The suite reached 1 h 17 min and one property is most of it.** Replaying every generated scene's own recorded
 * command is the check that has caught two real regressions in a week — a stray scene set, and a naming change
 * that silently rewrote files under new names — so the answer is not to delete it. It is that the set grew from
 * 1374 to 2688 as the sweep gained three axes, and a test whose cost scales with the output of the thing it tests
 * will always end up here.
 *
 * **A sample rather than a fixed subset, and the seed is printed.** A fixed subset is a fixed blind spot: the
 * scenes outside it would never be replayed again by anybody. A fresh sample every run covers the whole set over a
 * week of runs, and printing the seed is what keeps a failure reproducible — `SDWA5_REPLAY_SEED=…` replays exactly
 * the same draw. `SDWA5_FULL_REPLAY=1` takes the lot, which is what a nightly or a release run should do.
 *
 * **What must never be sampled is the set a stale check compares against.** Deleting "every generated scene this
 * run did not write" is only safe when the run wrote all of them; against a sample it would delete the rest of the
 * repository. Sampling belongs to what is *replayed*, never to what is *compared*.
 */
final class ReplaySample
{
    /** Enough to catch a systematic break in the first run and a rare one within a few. */
    public const KEEP = 120;

    /**
     * @template T
     *
     * @param array<string, T> $items keyed by whatever the caller reports a failure by
     *
     * @return array<string, T>
     */
    public static function of(array $items, int $keep = self::KEEP): array
    {
        if (false !== getenv('SDWA5_FULL_REPLAY') || count($items) <= $keep) {
            return $items;
        }

        $stated = getenv('SDWA5_REPLAY_SEED');
        $seed = false === $stated ? random_int(0, 2 ** 31 - 1) : (int) $stated;

        $keys = array_keys($items);
        mt_srand($seed);
        // `mt_rand` rather than `shuffle`, because only the former is seeded reproducibly across PHP versions.
        usort($keys, static fn (string $a, string $b): int => mt_rand() <=> mt_rand());
        $keys = array_slice($keys, 0, $keep);
        sort($keys);

        fwrite(STDERR, sprintf(
            "\n  [sample] %d of %d scenes, seed %d — SDWA5_REPLAY_SEED=%d repeats it, SDWA5_FULL_REPLAY=1 takes all\n",
            $keep,
            count($items),
            $seed,
            $seed,
        ));

        $sample = [];
        foreach ($keys as $key) {
            $sample[$key] = $items[$key];
        }

        return $sample;
    }
}
