<?php

declare(strict_types=1);

namespace App\Tests\Process;

use App\Process\Parallel;
use PHPUnit\Framework\TestCase;

/**
 * The fork helper behind the `scene:stack` sweep and the `build:all` replay.
 *
 * **What is worth testing here is not the speed.** A timing assertion on a shared machine is a flaky test, and the
 * measurement that justifies this class lives in the changelog. What has to hold on every run is that the answers
 * are the caller's answers, in the caller's order, whichever process produced them — because that is what lets a
 * parallel sweep be a drop-in for a serial one, and it is the property a race would break first.
 */
final class ParallelTest extends TestCase
{
    public function testAThrowingWorkerReportsItsErrorInTheParent(): void
    {
        if (!Parallel::isSupported()) {
            self::markTestSkipped('this platform cannot fork');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('probe failure');
        Parallel::map([1, 2], static function (int $value): int {
            if (1 === $value) {
                throw new \LogicException('probe failure');
            }

            return $value;
        }, 2);
    }

    /**
     * **The order out is the order in, even when the work finishes in the opposite order.** The first job is made
     * the slowest on purpose: with results keyed by completion this comes back reversed, and with results keyed by
     * position it cannot.
     */
    public function testResultsComeBackInTheOrderTheyWereGivenNotTheOrderTheyFinished(): void
    {
        $jobs = [];
        foreach (range(1, 8) as $n) {
            $jobs['job-'.$n] = $n;
        }

        $results = Parallel::map($jobs, static function (int $n): array {
            // Descending, so the first job is the last to finish.
            usleep((9 - $n) * 20_000);

            return ['n' => $n, 'pid' => getmypid()];
        }, 4);

        self::assertSame(array_keys($jobs), array_keys($results));
        self::assertSame(range(1, 8), array_column($results, 'n'));
    }

    /**
     * The parallel answer is the serial answer. Asserted on a computation with enough structure that a lost or
     * duplicated job shows up, rather than on eight identical values.
     */
    public function testParallelAndSerialAgree(): void
    {
        $jobs = array_combine(
            array_map(static fn (int $n): string => 'k'.$n, range(1, 20)),
            range(1, 20),
        );
        $work = static fn (int $n, string $key): string => $key.':'.($n * $n);

        self::assertSame(Parallel::map($jobs, $work, 1), Parallel::map($jobs, $work, 6));
    }

    /**
     * **The work is taken rather than dealt, so one slow job cannot idle the pool.** Two workers and four jobs, of
     * which the first is worth all the others put together: dealt by index, worker 0 takes jobs 0 and 2 and runs for
     * the whole of the slow one plus a short one. Taken from a cursor, worker 1 picks up everything else while
     * worker 0 is still busy, and every job is claimed exactly once — which is the part asserted here, because a
     * cursor that is not locked hands the same index to two workers.
     */
    public function testEveryJobIsClaimedExactlyOnceWhateverTheDurations(): void
    {
        $jobs = ['slow' => 200_000, 'a' => 1000, 'b' => 1000, 'c' => 1000, 'd' => 1000, 'e' => 1000];

        $results = Parallel::map($jobs, static function (int $sleep, string $key): array {
            usleep($sleep);

            return [$key, getmypid()];
        }, 2);

        self::assertSame(array_keys($jobs), array_keys($results));
        self::assertSame(array_keys($jobs), array_column($results, 0));
    }

    /**
     * A single job never forks, because a fork costs more than the job saves and the serial path is the one a
     * debugger can step through.
     */
    public function testASingleJobRunsInThisProcess(): void
    {
        $results = Parallel::map(['only' => 1], static fn (int $n): int => getmypid(), 8);

        self::assertSame(['only' => getmypid()], $results);
    }

    /**
     * `workers = 1` is the stated serial path, and it stays in this process however many jobs there are — which is
     * what `--jobs=1` on both commands is for.
     */
    public function testOneWorkerStaysInThisProcess(): void
    {
        $results = Parallel::map(array_fill(0, 5, 1), static fn (): int => getmypid(), 1);

        self::assertSame(array_fill(0, 5, getmypid()), $results);
    }

    /**
     * **A child really is a different process**, which is the whole point and is worth pinning: if a future change
     * quietly fell back to running everything in the parent, every test above would still pass and the release
     * would be a rename.
     */
    public function testTheWorkActuallyHappensInOtherProcesses(): void
    {
        if (!Parallel::isSupported()) {
            self::markTestSkipped('this platform cannot fork');
        }

        $pids = Parallel::map(array_fill(0, 12, 1), static function (): int {
            usleep(20_000);

            return getmypid();
        }, 4);

        self::assertNotContains(getmypid(), $pids, 'the jobs ran in the parent');
        self::assertGreaterThan(1, count(array_unique($pids)), 'every job ran in the same child');
    }

    /**
     * **What a child writes to disk survives it and what it writes to memory does not**, and that asymmetry is the
     * one thing a caller has to know. `scene:stack` relies on the first half — a worker writes its scene file and
     * the parent never sees the object that wrote it — and would be broken by assuming the second.
     */
    public function testAChildsFilesSurviveAndItsMemoryDoesNot(): void
    {
        if (!Parallel::isSupported()) {
            self::markTestSkipped('this platform cannot fork');
        }

        $directory = sys_get_temp_dir().'/parallel-test-'.getmypid();
        mkdir($directory);
        $shared = new \ArrayObject(['touched' => false]);

        try {
            Parallel::map(['a' => 'a', 'b' => 'b'], static function (string $name) use ($directory, $shared): bool {
                $shared['touched'] = true;
                file_put_contents($directory.'/'.$name, $name);

                return true;
            }, 2);

            self::assertFileExists($directory.'/a');
            self::assertFileExists($directory.'/b');
            self::assertFalse($shared['touched'], 'a child mutated the parent, which a fork cannot do');
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * An empty list is not an error and forks nothing.
     */
    public function testAnEmptyListIsAnEmptyResult(): void
    {
        self::assertSame([], Parallel::map([], static fn (): int => 1, 4));
    }

    /**
     * **A job that dies takes the run down rather than shortening it.** A worker killed mid-job writes no file, and
     * the alternative to throwing is a sweep that silently returns fewer scenes than it was asked for — which reads
     * as "that is all there is" and is exactly the failure this repository refuses everywhere else.
     */
    public function testAWorkerThatDiesIsAnErrorRatherThanAShortResult(): void
    {
        if (!Parallel::isSupported()) {
            self::markTestSkipped('this platform cannot fork');
        }

        $this->expectException(\RuntimeException::class);

        Parallel::map(array_fill(0, 4, 1), static function (): int {
            posix_kill(posix_getpid(), SIGKILL);

            return 1;
        }, 2);
    }
}
