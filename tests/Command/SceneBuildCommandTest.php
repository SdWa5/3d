<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneBuildCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs against the repository's real scenes and specs. `--dry-run` needs no Blender, so this checks
 * the whole compile-and-report path in CI.
 */
final class SceneBuildCommandTest extends TestCase
{
    public function testTheShippedSceneCompilesAndReports(): void
    {
        $tester = new CommandTester(new SceneBuildCommand());
        $exit = $tester->execute(['scene' => 'full-rig-all-tops', '--dry-run' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exit, $display);
        self::assertStringContainsString('Cabinets:      23', $display);
        self::assertStringContainsString('Total weight:', $display);
        self::assertStringContainsString('Tallest stack:', $display);
        self::assertStringContainsString('flexy-folded-horn-hybrid   12', $display);
    }

    public function testUnknownSceneListsWhatExists(): void
    {
        $tester = new CommandTester(new SceneBuildCommand());
        $exit = $tester->execute(['scene' => 'nope', '--dry-run' => true]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Available:', $tester->getDisplay());
        self::assertStringContainsString('full-rig-all-tops', $tester->getDisplay());
    }

    /**
     * **Gated, because its cost is a compile of every scene in the repository and that work is already done.**
     * `scene:build` with no argument compiles all 2688 of them — 13 minutes — and
     * {@see \App\Tests\Scene\ShippedScenesTest} compiles the same set every run to a stricter standard, so what
     * is lost by asking for this one is the command's own no-argument path rather than any coverage of the scenes.
     * `SDWA5_FULL_REPLAY=1` runs it, and a release should.
     */
    public function testBuildingEveryScenePicksUpTheShippedOne(): void
    {
        if (getenv('SDWA5_FULL_REPLAY') === false) {
            self::markTestSkipped('SDWA5_FULL_REPLAY=1 compiles every scene through the command — ca. 15 Minuten');
        }

        $tester = new CommandTester(new SceneBuildCommand());
        $exit = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        self::assertStringContainsString('full-rig-all-tops', $tester->getDisplay());
    }
}
