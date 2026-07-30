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
        $exit = $tester->execute(['scene' => 'full-rig', '--dry-run' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exit, $display);
        self::assertStringContainsString('Cabinets:      17', $display);
        self::assertStringContainsString('Total weight:', $display);
        self::assertStringContainsString('Tallest stack:', $display);
        self::assertStringContainsString('flexy-folded-horn-hybrid   14', $display);
    }

    public function testUnknownSceneListsWhatExists(): void
    {
        $tester = new CommandTester(new SceneBuildCommand());
        $exit = $tester->execute(['scene' => 'nope', '--dry-run' => true]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Available: full-rig', $tester->getDisplay());
    }

    public function testBuildingEveryScenePicksUpTheShippedOne(): void
    {
        $tester = new CommandTester(new SceneBuildCommand());
        $exit = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        self::assertStringContainsString('full-rig', $tester->getDisplay());
    }
}
