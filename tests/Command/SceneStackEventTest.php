<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;

final class SceneStackEventTest extends SceneStackTestCase
{
    private const OPTIONS = [
        '--owner' => ['innschleife'], '--roster' => ['innschleife-next-event'],
        '--event' => 'next-event', '--orientation' => ['mixed'], '--stacks' => '1',
        '--align' => ['center'], '--shape' => ['pyramid'], '--mirror-style' => ['alternate'],
        '--systems' => ['pooled'], '--low-end' => ['low'], '--dry-run' => true, '--jobs' => '1',
    ];

    private const PSL = [
        '--owner' => ['psl'], '--roster' => ['psl-next-event'],
        '--event' => 'next-event', '--orientation' => ['turned'], '--stacks' => '1',
        '--align' => ['center'], '--shape' => ['pyramid'], '--mirror-style' => ['alternate'],
        '--systems' => ['pooled'], '--low-end' => ['low'], '--dry-run' => true, '--jobs' => '1',
    ];

    public function testTheEventRecordsResolvedNumbersAndBuildsThePhotoRig(): void
    {
        $tester = $this->invoke(self::OPTIONS);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--room-width=13 --room-height=4', $tester->getDisplay());
        self::assertStringContainsString('--system-interface=innschleife:1.6', $tester->getDisplay());
        self::assertStringContainsString('--system-target=innschleife:1.75', $tester->getDisplay());
        self::assertStringNotContainsString('--event=', $tester->getDisplay());
        self::assertStringContainsString('4× kicker-15', $tester->getDisplay());
        self::assertStringContainsString('1× tms2 + 1× top-70x93 + 1× tms2', $tester->getDisplay());
    }

    /**
     * The event sets Innschleife up turned with its kickers standing, which is the photo, so `--orientation` has
     * nothing left to vary and the one candidate is named `stated`.
     */
    public function testTheEventsOrientationReplacesTheSweptOne(): void
    {
        $display = $this->invoke(self::OPTIONS)->getDisplay();

        self::assertStringContainsString('-stated-', $display);
        self::assertStringNotContainsString('-mixed-', $display);
        self::assertStringNotContainsString('--orientation=', $display);
        self::assertStringContainsString('--system-orientation=innschleife:turned', $display);
        self::assertStringContainsString('--system-orientation=psl:turned', $display);
        self::assertStringContainsString('--system-orientation=sdwa5:upright', $display);
        self::assertStringContainsString('--system-orientation=sepp:upright', $display);
        self::assertStringContainsString('--stand=kicker-15', $display);
        self::assertStringContainsString('wsx-18 rolled', $display);
        self::assertStringNotContainsString('kicker-15 rolled', $display);
    }

    /**
     * **The combined next-event rig carries Innschleife's photo layout**, which it could not at 0.5 m between stacks:
     * our 4.275 m, PSL's 3.58 m and the photo rig's 4.66 m are 13.515 m with two 0.5 m gaps, and 12.995 m with the
     * event's 0.24 m.
     */
    public function testTheCombinedRigFitsThePhotoLayoutIntoTheRoom(): void
    {
        $tester = $this->invoke([
            '--owner' => ['sdwa5', 'sepp', 'psl', 'innschleife'], '--roster' => ['psl-next-event', 'innschleife-next-event'],
            '--event' => 'next-event', '--into' => 'next-event', '--order' => ['ours,psl,innschleife'],
            '--systems' => ['systems-apart'], '--stacks' => '1', '--align' => ['center'], '--shape' => ['pyramid'],
            '--mirror-style' => ['alternate'], '--low-end' => ['low'], '--dry-run' => true, '--jobs' => '1',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('--clearance=0.24', $display);
        self::assertStringContainsString('stacked-1-systems-apart-pyramid-stated--alternate-center-low-----possible', $display);
        self::assertSame(2, substr_count($display, '1× wsx-18 rolled 270° + 1× sbh-18 rolled 270° + 1× sbh-18 rolled 90° + 1× wsx-18 rolled 90°'));
        self::assertStringContainsString('4× kicker-15', $display);
        self::assertStringContainsString('1× tms2 + 1× top-70x93 + 1× tms2', $display);
    }

    public function testAnExplicitClearanceReplacesTheEvents(): void
    {
        $display = $this->invoke(self::OPTIONS + ['--clearance' => '0.6'])->getDisplay();

        self::assertStringContainsString('--clearance=0.6', $display);
        self::assertStringNotContainsString('--clearance=0.24', $display);
    }

    public function testAnExplicitRoomOptionCannotLoosenTheSavedEventLimit(): void
    {
        $tester = $this->invoke(self::OPTIONS + ['--room-width' => '100']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--room-width=13', $tester->getDisplay());
        self::assertStringNotContainsString('--room-width=100', $tester->getDisplay());
    }

    public function testARigOutsideTheRoomIsRefusedInsteadOfWrittenAsImpossible(): void
    {
        $tester = $this->invoke(self::OPTIONS + ['--room-width' => '0.5']);

        self::assertSame(SceneStackCommand::NOTHING_TO_WRITE, $tester->getStatusCode());
        self::assertStringContainsString('room width', $tester->getDisplay());
    }

    public function testAnInvalidRoomOptionIsRefusedBeforeSolving(): void
    {
        $tester = $this->invoke(self::OPTIONS + ['--room-height' => 'not-a-number']);

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('finite positive number', $tester->getDisplay());
    }

    /** PSL's roster brings the deco panel, and the event hangs it from our truss behind the rig. */
    public function testABroughtDecoPanelHangsFromTheEventsTruss(): void
    {
        $tester = $this->invoke(self::PSL);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('--backdrop=truss-f33-2m:5:truss-tower-4m', $display);
        self::assertStringContainsString('--quantity=deco-panel-10x3-03:1', $display);
        self::assertStringContainsString('device: deco-panel-10x3-03', $display);
        self::assertStringContainsString('extend_to_m: 3.742', $display);
        self::assertStringNotContainsString('-impossible', $display);
    }

    public function testADecoPanelWithNoTrussIsRefused(): void
    {
        $tester = $this->invoke(array_diff_key(self::PSL, ['--event' => true]));

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('nothing names a truss', $tester->getDisplay());
    }

    /** The event names a truss for every run, and only a run that hangs something records it. */
    public function testARunWithoutDecoRecordsNoBackdrop(): void
    {
        $tester = $this->invoke(self::OPTIONS);

        self::assertStringNotContainsString('--backdrop', $tester->getDisplay());
        self::assertStringNotContainsString('backdrop-truss', $tester->getDisplay());
    }

    public function testAnUnknownEventIsRefused(): void
    {
        $tester = $this->invoke(array_replace(self::OPTIONS, ['--event' => 'no-such-event']));

        self::assertSame(SceneStackCommand::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('no event named no-such-event', $tester->getDisplay());
    }
}
