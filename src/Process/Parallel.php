<?php

declare(strict_types=1);

namespace App\Process;

use RuntimeException;

/**
 * Runs a list of independent jobs across several forked processes and gives the answers back in order.
 *
 * **This exists because two stages of the pipeline are embarrassingly parallel and were each costing a quarter of
 * an hour on one core.** The `scene:stack` sweep solves a thousand candidates that share nothing but the inventory,
 * and `build:all` replays five hundred recorded commands that each write their own file. Neither reads what the
 * other jobs write, so the only thing either loop ever shared was the CPU.
 *
 * Three properties are worth stating, because each of them was a bug before it was a rule.
 *
 * **The order out is the order in.** Results are re-keyed from the caller's own list rather than from whichever
 * child finished first, so a parallel run and a serial run print the same lines in the same sequence. Output that is
 * correct but unstable is worse than output that is slow, because it makes every diff meaningless.
 *
 * **Work is taken, not dealt.** Jobs are wildly uneven — an `all` rig solves in a minute and a single-owner rig in a
 * second — so a static deal by index left most workers idle while one finished the expensive half alone. A shared
 * cursor under `flock` costs a syscall per job and balances itself.
 *
 * **A child is killed, never returned from.** Falling off the end of a forked child runs every shutdown handler its
 * parent registered, which under PHPUnit means a second copy of the test report on stdout and a second exit code.
 * The child has written its file and holds no lock, so there is nothing worth unwinding.
 *
 * What a job returns has to survive `serialize()`, which in practice means arrays, scalars and readonly value
 * objects. What it must not do is mutate anything the parent will read afterwards: a fork gives it a copy, and the
 * copy dies with it.
 */
final class Parallel
{
    /** How many runs this process has started, so two of them cannot share a working directory. */
    private static int $runs = 0;

    /**
     * @template TIn
     * @template TOut
     * @param array<array-key, TIn> $jobs
     * @param callable(TIn, array-key): TOut $run
     * @param int $workers processes to use; 1 runs serially in this process, 0 asks the machine how many cores it has
     * @return array<array-key, TOut> the same keys in the same order
     */
    public static function map(array $jobs, callable $run, int $workers = 0): array
    {
        $keys = array_keys($jobs);
        $count = self::workers($workers, count($jobs));

        if ($count < 2) {
            $serial = [];
            foreach ($jobs as $key => $job) {
                $serial[$key] = $run($job, $key);
            }

            return $serial;
        }

        // The pid alone is not enough: one process runs this several times, and a leftover file from an earlier
        // run would be read as this one's answer.
        $directory = sys_get_temp_dir().'/parallel-'.getmypid().'-'.(++self::$runs);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException('cannot create a working directory for the run at '.$directory);
        }
        file_put_contents($directory.'/cursor', '0');

        $children = [];
        for ($worker = 0; $worker < $count; ++$worker) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                // Whatever forked already still does the whole list, because the cursor decides who takes what.
                break;
            }
            if ($pid === 0) {
                self::work($directory, $worker, $jobs, $keys, $run);
            }
            $children[$worker] = $pid;
        }

        if ($children === []) {
            self::clean($directory, []);

            return self::map($jobs, $run, 1);
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $done = [];
        foreach (array_keys($children) as $worker) {
            $file = $directory.'/'.$worker;
            $raw = is_file($file) ? (string)file_get_contents($file) : '';
            if ($raw === '') {
                self::clean($directory, array_keys($children));

                throw new RuntimeException(sprintf(
                    'worker %d produced nothing, so the run is incomplete rather than short',
                    $worker,
                ));
            }
            /** @var array<int, TOut> $part */
            $part = unserialize($raw);
            $done += $part;
        }
        self::clean($directory, array_keys($children));

        $results = [];
        foreach ($keys as $index => $key) {
            if (!array_key_exists($index, $done)) {
                throw new RuntimeException(sprintf('the run lost job %s to a worker', (string)$key));
            }
            $results[$key] = $done[$index];
        }

        return $results;
    }

    /**
     * Whether this platform can fork at all. Everything else degrades to a serial run rather than failing.
     */
    public static function isSupported(): bool
    {
        return function_exists('pcntl_fork') && function_exists('posix_kill') && function_exists('flock');
    }

    /**
     * One worker: take jobs off the shared cursor until there are none left, write the answers, die.
     *
     * Results are keyed by *position* rather than by the caller's key, because a caller's keys may be anything and
     * the parent reassembles by position anyway.
     *
     * @param array<array-key, mixed> $jobs
     * @param list<array-key> $keys
     */
    private static function work(string $directory, int $worker, array $jobs, array $keys, callable $run): never
    {
        // Opened here rather than inherited: a forked handle shares its parent's open file description, and `flock`
        // on a shared description locks out nobody.
        $cursor = fopen($directory.'/cursor', 'r+');
        $mine = [];
        if ($cursor !== false) {
            while (($index = self::next($cursor, count($keys))) !== null) {
                $mine[$index] = $run($jobs[$keys[$index]], $keys[$index]);
            }
            fclose($cursor);
        }
        file_put_contents($directory.'/'.$worker, serialize($mine));
        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }

    /**
     * The position of the next job to run, or null when the list is exhausted.
     *
     * @param resource $cursor
     */
    private static function next($cursor, int $total): ?int
    {
        if (!flock($cursor, LOCK_EX)) {
            return null;
        }

        rewind($cursor);
        $next = (int)stream_get_contents($cursor);
        if ($next < $total) {
            ftruncate($cursor, 0);
            rewind($cursor);
            fwrite($cursor, (string)($next + 1));
            fflush($cursor);
        }
        flock($cursor, LOCK_UN);

        return $next < $total ? $next : null;
    }

    /**
     * @param list<int> $workers
     */
    private static function clean(string $directory, array $workers): void
    {
        foreach ($workers as $worker) {
            @unlink($directory.'/'.$worker);
        }
        @unlink($directory.'/cursor');
        @rmdir($directory);
    }

    /**
     * How many processes to use. One whenever there is nothing to gain or no way to get it.
     */
    private static function workers(int $stated, int $jobs): int
    {
        if ($stated === 1 || $jobs < 2 || !self::isSupported()) {
            return 1;
        }
        if ($stated > 1) {
            return min($jobs, $stated);
        }

        return max(1, min($jobs, self::cores()));
    }

    /**
     * The core count, asked of the machine rather than guessed. A container sees the host's cores, which is the
     * right answer here because it is allowed to use them.
     */
    public static function cores(): int
    {
        $nproc = shell_exec('nproc 2>/dev/null');

        return max(1, (int)($nproc !== null ? trim($nproc) : 1));
    }
}
