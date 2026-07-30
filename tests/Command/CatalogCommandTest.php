<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CatalogCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

final class CatalogCommandTest extends TestCase
{
    public function testListsTheRepositorysGearWithTotals(): void
    {
        $tester = new CommandTester(new CatalogCommand());
        $exit = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exit, $display);
        self::assertStringContainsString('Total weight:', $display);
        self::assertStringContainsString('Devices:', $display);
    }

    public function testWarnsAboutSpecsThatWereNeverMeasured(): void
    {
        // The shipped example specs are estimated off a photo, so the warning has to appear —
        // silently presenting guesses as data is the failure mode this guards against.
        $tester = new CommandTester(new CatalogCommand());
        $tester->execute([]);

        self::assertStringContainsString('not measured yet', $tester->getDisplay());
    }
}
