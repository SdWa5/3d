<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;

/**
 * `scene:stack` — rosters, which override the specs' counts for one event.
 *
 * A spec says how many of a cabinet exist; a roster says how many are coming to *this* gig. The override has
 * to travel into the recorded regenerate line, or a replay would rebuild the rig from the library's counts
 * and quietly produce a different scene under the same name — which is what most of these tests pin.
 *
 * A count that merely restates the spec is not recorded, because a line that says nothing is a line that has
 * to be maintained anyway.
 */
final class SceneStackRosterTest extends SceneStackTestCase
{
    /**
     * **A roster builds the rig it states, not the rig the specs describe.**.
     *
     * `tms4` is left at home at zero, so a cabinet Innschleife own must not appear anywhere in the output — not in
     * a stack, not in a refusal, not in the recorded line's `--from` list — while the middle top the roster brings
     * does.
     */
    public function testARosterBuildsWithTheCountsItStatesRatherThanTheSpecs(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--roster' => ['innschleife-next-event'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--from=top-70x93', $tester->getDisplay());
        self::assertStringNotContainsString('device: tms4', $tester->getDisplay());
        self::assertStringNotContainsString('--from=tms4', $tester->getDisplay());
    }

    /**
     * **A roster whose cabinets the sweep does not hold is refused**, because its counts would change nothing and
     * the scenes would still be filed under its name. Swept at 0.105.0 without `--owner`, Innschleife's roster
     * produced 146 scenes of sdwa5 and sepp cabinets.
     */
    public function testARosterWhoseCabinetsAreNotSweptIsRefused(): void
    {
        $tester = $this->invoke([
            '--owner' => ['sdwa5'], '--roster' => ['innschleife-next-event'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        // The error box wraps its text, so the words are compared with the line breaks folded back into spaces.
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--roster=innschleife-next-event brings wsx-18', $display);
        self::assertStringContainsString('Say --owner=innschleife', $display);
    }

    /** Explicit zero quantities leave the roster's cabinets at home before the inventory check. */
    public function testZeroQuantitiesOverrideTheRosterBeforeCheckingTheInventory(): void
    {
        $tester = $this->invoke([
            '--owner' => ['sdwa5'], '--roster' => ['innschleife-next-event'],
            '--quantity' => ['wsx-18:0', 'sbh-18:0', 'kicker-15:0', 'tms2:0', 'top-70x93:0'],
            '--orientation' => ['mixed'], '--stacks' => '1', '--align' => ['center'],
            '--shape' => ['pyramid'], '--mirror-style' => ['alternate'], '--systems' => ['pooled'],
            '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('which the swept inventory does not hold', $tester->getDisplay());
    }

    /**
     * **The recorded line carries the counts and never the roster that stated them.**.
     *
     * A roster is a file that can be edited, and a replay has to rebuild *this* scene — the same argument the
     * `--from` list is written out on. Recording `--roster=` instead would make every replay depend on what the
     * file says on the day it runs, so a corrected roster would silently rewrite last week's rigs under their old
     * names.
     */
    public function testTheRecordedLineCarriesTheCountsRatherThanTheRoster(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--roster' => ['innschleife-next-event'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertStringContainsString('--quantity=tms4:0', $tester->getDisplay());
        self::assertStringNotContainsString('--roster=', $tester->getDisplay());
    }

    /**
     * A count that already matches the spec changes no rig, so it is not recorded — a `--quantity` in a replay
     * line that does nothing is noise, and it would also trip the refusal below for a run that overrode nothing.
     */
    public function testACountThatMatchesTheSpecIsNotRecorded(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'],
            '--quantity' => ['kicker-15:4'],
            '--into' => 'zz-test-roster',
            '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('--quantity=', $tester->getDisplay());
    }

    /**
     * **A changed rig under an unchanged name is the one failure this command must not have.** Every other axis is
     * in the file name or in the folder, so two rigs cannot collide; a count override is in neither, and a sweep
     * would write its files over the ones a bare sweep just wrote.
     */
    public function testACountOverrideWithNoFolderToWriteIntoIsRefused(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--quantity' => ['tms4:0'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('changes the rig without changing its name', $tester->getDisplay());
    }

    /**
     * One roster names the folder itself, which is what stops the two variants of one event overwriting each
     * other while both are `--owner=innschleife`.
     */
    public function testASingleRosterNamesTheFolderTheScenesAreFiledUnder(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--roster' => ['innschleife-next-event'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertStringContainsString('--into=innschleife-next-event', $tester->getDisplay());
    }

    public function testAnUnknownRosterIsRefusedWithTheOnesThereAre(): void
    {
        $tester = $this->invoke(['--roster' => ['no-such-event'], '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('no roster named no-such-event', $tester->getDisplay());
        self::assertStringContainsString('innschleife-next-event', $tester->getDisplay());
    }

    public function testACountForADeviceThatDoesNotExistIsRefused(): void
    {
        $tester = $this->invoke([
            '--quantity' => ['no-such-cabinet:2'], '--into' => 'zz-test-roster', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString("no device is called 'no-such-cabinet'", $tester->getDisplay());
    }

    public function testACountThatIsNotAWholeNumberOfUnitsIsRefused(): void
    {
        $tester = $this->invoke([
            '--quantity' => ['tecnare-m2122:two'], '--into' => 'zz-test-roster', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('expects DEVICE:COUNT', $tester->getDisplay());
    }

    /**
     * **A scene built from a roster states its counts, and the file is worthless without them.**.
     *
     * A `stack:` block is re-solved on every build, so a count left out comes back as whatever the spec says
     * today. That is not a cosmetic difference: twelve ESX laid out under five EF 6, rebuilt from a spec that
     * says six, produced a rig three rows shorter with a top row still spread for the taller one — a floating
     * cabinet, in a file the writer had already named `-possible` because it checked the rig it meant rather
     * than the rig it wrote. `ShippedScenesTest` caught two of them.
     */
    public function testARosterBuiltSceneStatesItsCountsInTheFileItWrites(): void
    {
        // The deco panel stays at home, because without an event nothing names a truss for it. See SceneStackEventTest.
        $tester = $this->invoke([
            '--owner' => ['psl'], '--roster' => ['psl-next-event'], '--quantity' => ['deco-panel-10x2-5:0'],
            '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString("- device: concert-audio-esx\n          count: 12", $tester->getDisplay());
        self::assertStringContainsString("- device: concert-audio-ef6\n          count: 5", $tester->getDisplay());
    }
}
