<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SpecsValidateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs against the repository's real specs — the same check CI performs. If someone commits a
 * spec that breaks the shared conventions, this test is what catches it.
 */
final class SpecsValidateCommandTest extends TestCase
{
    public function testTheRepositorysOwnSpecsAreValid(): void
    {
        $tester = new CommandTester(new SpecsValidateCommand());
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        self::assertMatchesRegularExpression('/\d+ specs? valid/', $tester->getDisplay());
    }
}
