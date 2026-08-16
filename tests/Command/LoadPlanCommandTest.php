<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\LoadPlanCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

/**
 * `load:plan` against the real fleet and the real library.
 *
 * **Run on `specs/` rather than on fixtures, deliberately.** The planner's own behaviour is covered unit by unit in
 * {@see \App\Tests\Load\LoadPlannerTest}; what this file is for is the answer about our two vans and our gear, and
 * a fixture would only prove the console wiring works. The day somebody measures Sepp's van or weighs a Flexy, this
 * is the test that should notice.
 */
final class LoadPlanCommandTest extends TestCase
{
    /**
     * **The exit code says whether the load may legally travel, and it is not the same code as a broken command.**
     *
     * A payload overrun is a fine, a liability question after an accident and a refused insurance claim, so it can
     * never be a warning somebody scrolls past — but it is also not a crash, and a script that runs this wants to
     * tell "the fleet is too small" from "the command is broken". Hence a third code, the same argument
     * `scene:stack` makes for {@see \App\Command\SceneStackCommand::NOTHING_TO_WRITE}.
     */
    public function testAFleetThatCannotCarryTheLoadExitsOverloadedRatherThanFailed(): void
    {
        $tester = $this->invoke(['--exclude-owner' => ['gmss']]);

        self::assertSame(LoadPlanCommand::OVERLOADED, $tester->getStatusCode());
        self::assertStringContainsString('NOT CARRIED', $tester->getDisplay());
        self::assertStringContainsString('short by', $tester->getDisplay());
    }

    /**
     * **Both verdicts, separately, with the numbers behind each.** This is the whole of LOAD-4: a plan can fit the
     * bay comfortably and be over the axle, so one line and one pass/fail would hide the case that matters.
     */
    public function testWeightAndSpaceAreReportedAsTwoAnswers(): void
    {
        $display = $this->invoke(['--exclude-owner' => ['gmss']])->getDisplay();

        self::assertMatchesRegularExpression('/weight\s+[\d.]+ kg of [\d.]+ kg payload/', $display);
        self::assertMatchesRegularExpression('/space\s+[\d.]+ m³ of [\d.]+ m³ bay/', $display);
    }

    /**
     * **A bounding-box sum over the bay is stated as evidence, and it is the only space answer that is safe.**
     * Sepp's van comes out over its bay on our real gear, and the report has to say so in a sentence somebody
     * reading quickly cannot mistake for a rounding note.
     */
    public function testABayAlreadyExceededByBoundingBoxesIsSaidOutLoud(): void
    {
        self::assertStringContainsString(
            'WHICH ALREADY EXCEEDS IT',
            $this->invoke(['--exclude-owner' => ['gmss']])->getDisplay(),
        );
    }

    /**
     * **The provenance of the verdict, printed with the verdict.** Half the fleet payload is Sepp's assumed 1200 kg
     * and not one weight in the library has been on a scale, so a reader who does not know that will treat a two
     * kilogramme margin as a decision. The report says which figures it summed and where they came from, and calls
     * a margin inside its own error bar undecided rather than passing it.
     */
    public function testTheReportNamesWhatItsVerdictIsMadeOf(): void
    {
        $display = $this->invoke(['--exclude-owner' => ['gmss']])->getDisplay();

        self::assertMatchesRegularExpression('/Weights: \d+ of \d+ devices weighed on a scale/', $display);
        self::assertStringContainsString('from estimated masses', $display);
        self::assertStringContainsString('UNDECIDED', $display);
    }

    /**
     * A smaller load does fit, and then the command says so and exits clean — otherwise the overloaded path above
     * would be indistinguishable from the command always failing.
     */
    public function testALoadTheFleetCanCarryExitsClean(): void
    {
        $tester = $this->invoke(['--owner' => ['sepp']]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no vehicle is over its payload', $tester->getDisplay());
        self::assertStringNotContainsString('NOT CARRIED', $tester->getDisplay());
    }

    /**
     * A named owner that does not exist is a typo, and a typo that silently carries nothing would print a load plan
     * for an empty van and exit clean.
     */
    public function testAnUnknownOwnerIsRefusedAndNamesTheOnesThereAre(): void
    {
        $tester = $this->invoke(['--exclude-owner' => ['gmbh']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("unknown owner 'gmbh'", $tester->getDisplay());
        self::assertStringContainsString('sdwa5', $tester->getDisplay());
    }

    /**
     * Naming one vehicle plans for that vehicle alone, which is the "Sepp is not coming" case and the one most
     * likely to be asked on the day.
     */
    public function testNamingOneVehiclePlansForThatVehicleAlone(): void
    {
        $display = $this->invoke([
            '--exclude-owner' => ['gmss'], '--vehicle' => ['opel-movano-l4h3'],
        ])->getDisplay();

        self::assertStringContainsString('opel-movano-l4h3', $display);
        self::assertStringNotContainsString('sepp-transporter-l3h2 (sepp) —', $display);
        // One van cannot take what two could not, so the remainder grows rather than the plan getting cleverer.
        self::assertStringContainsString('NOT CARRIED', $display);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invoke(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new LoadPlanCommand());

        $tester = new CommandTester($application->find('load:plan'));
        $tester->execute($options);

        return $tester;
    }
}
