<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

final class ReplayBudgetTest extends TestCase
{
    public function testACommandInsideTheBudgetReportsItsOwnExitCode(): void
    {
        $run = ReplayBudget::run('sh -c "echo broken >&2; exit 3"', sys_get_temp_dir(), 5);

        self::assertSame(3, $run['exit']);
        self::assertSame('broken', $run['error']);
    }

    /**
     * **The kill happens at the budget, not when the command would have finished.** A ten-second sleep under a
     * one-second budget has to come back in about one second, or the budget bounds nothing.
     */
    public function testACommandPastTheBudgetIsKilledAtTheBudget(): void
    {
        $started = microtime(true);
        $run = ReplayBudget::run('sleep 10', sys_get_temp_dir(), 1);
        $took = microtime(true) - $started;

        self::assertNull($run['exit']);
        self::assertLessThan(5.0, $took, 'the command ran to its own end rather than to the budget');
    }

    public function testNoBudgetRunsTheCommandToItsEnd(): void
    {
        self::assertSame(0, ReplayBudget::run('sleep 1', sys_get_temp_dir(), null)['exit']);
    }

    /**
     * **A full replay promises every scene solved to the end**, so it has no budget at all, and an explicit budget
     * replaces the default.
     */
    public function testTheBudgetFollowsTheEnvironment(): void
    {
        $full = getenv('SDWA5_FULL_REPLAY');
        $stated = getenv('SDWA5_REPLAY_BUDGET');

        try {
            putenv('SDWA5_FULL_REPLAY');
            putenv('SDWA5_REPLAY_BUDGET');
            self::assertSame(ReplayBudget::SECONDS, ReplayBudget::seconds());

            putenv('SDWA5_REPLAY_BUDGET=7');
            self::assertSame(7, ReplayBudget::seconds());

            putenv('SDWA5_FULL_REPLAY=1');
            self::assertNull(ReplayBudget::seconds());
        } finally {
            putenv(false === $full ? 'SDWA5_FULL_REPLAY' : 'SDWA5_FULL_REPLAY='.$full);
            putenv(false === $stated ? 'SDWA5_REPLAY_BUDGET' : 'SDWA5_REPLAY_BUDGET='.$stated);
        }
    }

    public function testTheReplayLineForcesTheFileAndStaysInOneProcess(): void
    {
        $line = ReplayBudget::replayLine('--from=skram --stacks=1');

        self::assertStringEndsWith(' bin/console scene:stack --from=skram --stacks=1 --force --jobs=1', $line);
    }

    /** A recorded line a shell would read differently is refused rather than quoted into a different command. */
    public function testARecordedLineWithAShellMetacharacterIsRefused(): void
    {
        $this->expectException(\LogicException::class);

        ReplayBudget::replayLine('--from=skram; rm -rf scenes');
    }
}
