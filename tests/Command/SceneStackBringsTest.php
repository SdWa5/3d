<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;

/**
 * `scene:stack` — what each system brings to an event, which overrides the specs' counts for that event.
 *
 * A spec says how many of a cabinet exist, and `systems.<owner>.brings` says how many are coming to *this* gig. The
 * override has to travel into the recorded regenerate line, or a replay would rebuild the rig from the library's
 * counts and quietly produce a different scene under the same name, which is what most of these tests pin.
 *
 * A count that merely restates the spec is not recorded, because a line that says nothing is a line that has to be
 * maintained anyway.
 */
final class SceneStackBringsTest extends SceneStackTestCase
{
    /**
     * **The event builds the rig it states, not the rig the specs describe.**.
     *
     * `sub-60x60` is left at home at zero, so a cabinet Innschleife own must not appear anywhere in the output, not in
     * a stack, not in a refusal, not in the recorded line's `--from` list, while the big top they bring does.
     */
    public function testAnEventBuildsWithTheCountsItStatesRatherThanTheSpecs(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--from=tms4', $tester->getDisplay());
        self::assertStringNotContainsString('device: sub-60x60', $tester->getDisplay());
        self::assertStringNotContainsString('--from=sub-60x60', $tester->getDisplay());
    }

    /**
     * **Only the swept systems bring anything.** An Innschleife run at the next event carries none of PSL's counts and
     * hangs none of PSL's panel, which is what kept the roster files composable before they were folded in.
     */
    public function testOnlyTheSweptSystemsBringTheirCounts(): void
    {
        $display = $this->invoke([
            '--owner' => ['innschleife'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('--quantity=tms4:1', $display);
        self::assertStringNotContainsString('concert-audio-esx', $display);
        self::assertStringNotContainsString('deco-panel', $display);
    }

    /**
     * **Brought cabinets a `--from` list leaves out are refused**, because their counts would change nothing and the
     * scenes would still be filed as that system's rig at the event.
     */
    public function testBroughtCabinetsTheSweepDoesNotHoldAreRefused(): void
    {
        $tester = $this->invoke([
            '--from' => ['wsx-18', 'sbh-18'], '--event' => 'next-event-light', '--into' => self::THROWAWAY_ID,
            '--low-end' => ['low'], '--dry-run' => true,
        ]);

        // The error box wraps its text, so the words are compared with the line breaks folded back into spaces.
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('the event has innschleife bring kicker-15', $display);
        self::assertStringContainsString('Say --owner=innschleife', $display);
    }

    /** An explicit `--quantity` is the newer statement, so it replaces the event's count for that device. */
    public function testAnExplicitQuantityReplacesTheEventsCount(): void
    {
        $display = $this->invoke([
            '--owner' => ['innschleife'], '--event' => 'next-event-light', '--quantity' => ['wsx-18:2'],
            '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('--quantity=wsx-18:2', $display);
        self::assertStringNotContainsString('--quantity=wsx-18:4', $display);
    }

    /**
     * **The recorded line carries the counts and never the event that stated them.**.
     *
     * An event is a file that can be edited, and a replay has to rebuild *this* scene, the same argument the `--from`
     * list is written out on. Recording `--event=` instead would make every replay depend on what the file says on
     * the day it runs, so a corrected count would silently rewrite last week's rigs under their old names.
     */
    public function testTheRecordedLineCarriesTheCountsRatherThanTheEvent(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertStringContainsString('--quantity=tms4:1', $tester->getDisplay());
        self::assertStringNotContainsString('--event=', $tester->getDisplay());
    }

    /**
     * A count that already matches the spec changes no rig, so it is not recorded. A `--quantity` in a replay line
     * that does nothing is noise, and it would also trip the refusal below for a run that overrode nothing.
     *
     * The upright rigs alone, 29 of them, because the rolled ones carry nothing this assertion reads and took the
     * case from 2 s to 24 s.
     */
    public function testACountThatMatchesTheSpecIsNotRecorded(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--orientation' => ['upright'],
            '--quantity' => ['kicker-15:4'],
            '--into' => 'zz-test-roster',
            '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('--quantity=', $tester->getDisplay());
    }

    /**
     * **A changed rig under an unchanged name is the one failure this command must not have.** Every other axis is
     * in the file name or in the folder, so two rigs cannot collide. A count override is in neither, and a sweep
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

    /** Several systems at one event have no single folder name, so the counts need a stated `--into`. */
    public function testSeveralSystemsAtAnEventNeedAFolder(): void
    {
        $tester = $this->invoke([
            '--owner' => ['psl', 'innschleife'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('say --into=NAME', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * One system at an event is filed as `<owner>-<event>`, which is what stops its rigs overwriting the ones built
     * from everything that system owns. It is the name the roster file used to carry, so no folder moved.
     */
    public function testOneSystemAtAnEventIsFiledUnderOwnerAndEvent(): void
    {
        $tester = $this->invoke([
            '--owner' => ['innschleife'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertStringContainsString('--into=innschleife-next-event-light', $tester->getDisplay());
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
     * **A scene built from an event's counts states them in the file it writes, and the file is worthless without
     * them.**.
     *
     * A `stack:` block is re-solved on every build, so a count left out comes back as whatever the spec says today.
     * That is not cosmetic: twelve ESX laid out under five EF 6, rebuilt from a spec that says six, produced a rig
     * three rows shorter with a top row still spread for the taller one, a floating cabinet in a file the writer had
     * already named `-possible`. `ShippedScenesTest` caught two of them.
     */
    public function testAnEventBuiltSceneStatesItsCountsInTheFileItWrites(): void
    {
        $tester = $this->invoke([
            '--owner' => ['psl'], '--event' => 'next-event-light', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString("- device: concert-audio-esx\n          count: 9", $tester->getDisplay());
    }
}
