<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CatalogCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

    public function testReportsMeasuringProgressSeparatelyForDimensionsAndWeights(): void
    {
        // Nothing in the library has been measured yet, so both counts must be visible and the
        // warning must fire — silently presenting design figures as data is the failure mode this
        // guards against.
        $tester = new CommandTester(new CatalogCommand());
        $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertStringContainsString('Dimensions measured:', $display);
        self::assertStringContainsString('Weights measured:', $display);
        self::assertStringContainsString('not fully measured', $display);
    }
}
