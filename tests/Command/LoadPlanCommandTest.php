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
     * **The real fleet is 214.5 kg short of one trip, and the exit code says so without calling it a crash.**
     *
     * The figure behind it has moved twice in a day — estimated 1200 kg, documented 1365, weighed 1000 — so what
     * this pins is the shape of the answer rather than the number: gear is left behind, it is named, and the
     * command exits `OVERLOADED`.
     */
    public function testTheRealFleetIsShortOfOneTripAndSaysSo(): void
    {
        $tester = $this->invoke(['--exclude-owner' => ['gmss']]);

        self::assertSame(LoadPlanCommand::OVERLOADED, $tester->getStatusCode());
        self::assertStringContainsString('NOT CARRIED', $tester->getDisplay());
        self::assertStringContainsString('short by', $tester->getDisplay());
    }

    /**
     * **The exit code says whether the load may legally travel, and it is not the same code as a broken command.**
     *
     * A payload overrun is a fine, a liability question after an accident and a refused insurance claim, so it can
     * never be a warning somebody scrolls past — but it is also not a crash, and a script wants to tell "the fleet
     * is too small" from "the command is broken". Hence a third code, the same argument `scene:stack` makes for
     * {@see \App\Command\SceneStackCommand::NOTHING_TO_WRITE}. Asserted on one van rather than two, because the
     * fleet no longer overloads and the code still has to work.
     */
    public function testAFleetThatCannotCarryTheLoadExitsOverloadedRatherThanFailed(): void
    {
        $tester = $this->invoke(['--exclude-owner' => ['gmss'], '--vehicle' => ['opel-movano-l4h3']]);

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
     * **The provenance of the verdict, printed with the verdict.** Half the fleet payload is Sepp's assumed 1200 kg
     * and not one weight in the library has been on a scale, so a reader who does not know that will treat a two
     * kilogramme margin as a decision. The report says which figures it summed and where they came from, and calls
     * a margin inside its own error bar undecided rather than passing it.
     */
    public function testTheReportNamesWhatItsVerdictIsMadeOf(): void
    {
        $display = $this->invoke(['--exclude-owner' => ['gmss']])->getDisplay();

        self::assertMatchesRegularExpression('/Weights: \d+ of \d+ devices weighed on a scale/', $display);

        // **The two payloads no longer come from the same kind of source, and the report says which is which.**
        // Sepp's van has been on a weighbridge; the Movano's mass is still field G of a registration document —
        // the same class of figure that turned out 365 kg light on the van that got weighed.
        self::assertStringContainsString('from measured masses', $display);
        self::assertStringContainsString('from datasheet masses', $display);
    }

    /**
     * **A margin inside the error of its own inputs is still reported as undecided**, which is the rule the real
     * fleet stopped exercising the day it got its second documented payload. Shown on a load tuned to sit within
     * 2 % of the Movano's limit, because the rule is what matters rather than which numbers happen to trigger it.
     */
    public function testAMarginInsideItsOwnErrorBarIsStillUndecided(): void
    {
        // One Movano offered the whole travelling load fills to within 10 kg of its 1024 kg limit, which is 1 %.
        $display = $this->invoke([
            '--exclude-owner' => ['gmss'], '--vehicle' => ['opel-movano-l4h3'],
        ])->getDisplay();

        self::assertStringContainsString('UNDECIDED for opel-movano-l4h3', $display);
        self::assertStringContainsString('neither a pass nor a refusal', $display);
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
     * **A device that travels some other way is named and left out**, which is not the question `--exclude-owner`
     * answers.
     *
     * Sepp's 465 kg generator rides on a trailer rather than in a van — stated by the owner — so it is neither a
     * whole owner's gear nor part of a van's load. Without a way to say so the planner puts 465 kg of it in a van
     * and reports a shortfall of 679.5 kg that nobody actually has. Until the trailer is a spec and becomes a third
     * bin, naming the device is the only way to tell the truth about what the vans carry.
     */
    public function testADeviceTravellingSomeOtherWayIsLeftOutByName(): void
    {
        $withIt = $this->invoke(['--exclude-owner' => ['gmss']])->getDisplay();
        $withoutIt = $this->invoke([
            '--exclude-owner' => ['gmss'], '--exclude' => ['generator-25kva'],
        ])->getDisplay();

        self::assertStringContainsString('generator-25kva', $withIt, 'it is cargo unless excluded');
        self::assertStringNotContainsString('generator-25kva', $withoutIt);
    }

    /**
     * An id that is not a device is a typo, and a typo that silently excluded nothing would give a load plan for a
     * heavier load than the one being planned.
     */
    public function testAnUnknownDeviceToExcludeIsRefused(): void
    {
        $tester = $this->invoke(['--exclude' => ['sepp-generator-30kva']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("unknown device 'sepp-generator-30kva'", $tester->getDisplay());
    }

    /**
     * **A vehicle id cannot be excluded as cargo**, because a van is not cargo. Left unguarded this would silently
     * accept `--exclude=opel-movano-l4h3` and change nothing, which reads as having worked.
     */
    public function testAVehicleCannotBeExcludedAsCargo(): void
    {
        $tester = $this->invoke(['--exclude' => ['opel-movano-l4h3']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('unknown device', $tester->getDisplay());
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
