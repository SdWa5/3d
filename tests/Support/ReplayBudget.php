<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * A time limit on one sampled replay, so a single expensive rig cannot decide how long the suite takes.
 *
 * **TOOL-22 measured the draw deciding the wall clock.** {@see ReplaySample} replays 120 random generated scenes, and
 * one seed replayed all of them in 35 s while another drew a 22-type pooled V stack that was still solving after ten
 * minutes. Every other worker had finished long before, so the suite waited on one core for one rig.
 *
 * **A separate process, killed, rather than an alarm inside this one.** An alarm handler throws into whatever code is
 * running, and `CandidateCheck::compileYaml()` catches `\Throwable` around its parse. An alarm landing there would be
 * swallowed, reject a valid candidate and let the replay write a different scene. A killed process cannot catch
 * anything, and `SceneStackCommand::emit()` writes a temporary file and renames it, so the scene is either the old
 * file or a whole new one. The caller removes a temporary file a kill left behind.
 *
 * **A stopped replay is not a pass in disguise.** Its scene keeps the file it had, so the before-and-after comparison
 * still covers it, but it got no fresh solve. That is why the caller names every replay that ran over and fails once
 * more than {@see TOLERATED} of them do, which is what a solver that got slower across the board looks like.
 */
final class ReplayBudget
{
    /** Seconds one replay may take before it is stopped. Chosen from measured replay times, see the README. */
    public const SECONDS = 60;

    /** How many replays of one sample may run over before the test calls the solver slow rather than the draw unlucky. */
    public const TOLERATED = 5;

    /**
     * The budget this run uses: none under `SDWA5_FULL_REPLAY`, which promises every scene replayed to the end,
     * `SDWA5_REPLAY_BUDGET` when it is set, the default otherwise.
     */
    public static function seconds(): ?int
    {
        if (false !== getenv('SDWA5_FULL_REPLAY')) {
            return null;
        }

        $stated = getenv('SDWA5_REPLAY_BUDGET');

        return false !== $stated && (int) $stated > 0 ? (int) $stated : self::SECONDS;
    }

    /**
     * Runs a shell command line in `$directory` and returns its exit code with its error output, or null for the
     * exit code when it ran past the budget and was killed. A null budget runs it to the end.
     *
     * The line is run through `exec`, so the process the budget kills is the command itself and not a shell waiting
     * on it.
     *
     * @return array{exit: ?int, seconds: float, error: string}
     */
    public static function run(string $commandLine, string $directory, ?int $seconds): array
    {
        $process = Process::fromShellCommandline('exec '.$commandLine, $directory, null, null, $seconds);
        $started = microtime(true);

        try {
            $process->run();
            $exit = $process->getExitCode();
        } catch (ProcessTimedOutException) {
            $exit = null;
        }

        return ['exit' => $exit, 'seconds' => microtime(true) - $started, 'error' => trim($process->getErrorOutput())];
    }

    /**
     * The command line that replays one recorded `scene:stack` invocation over its own file.
     *
     * `--jobs=1` because a replay names every axis and solves one rig, and a forked child would outlive the kill.
     */
    public static function replayLine(string $recorded): string
    {
        // The writer records plain `--option=value` words. Anything a shell would read differently is refused here
        // rather than quoted, because quoting it would replay a command the file never recorded.
        if (1 === preg_match('/[|&;<>()$`\\\\"\'*?\n]/', $recorded)) {
            throw new \LogicException('the recorded command holds a shell metacharacter: '.$recorded);
        }

        return escapeshellarg(PHP_BINARY).' bin/console scene:stack '.$recorded.' --force --jobs=1';
    }
}
