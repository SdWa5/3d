<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;

final class SceneStackEventTest extends SceneStackTestCase
{
    private const OPTIONS = [
        '--owner' => ['innschleife'],
        '--event' => 'next-event', '--orientation' => ['mixed'], '--stacks' => '1',
        '--align' => ['center'], '--shape' => ['pyramid'], '--mirror-style' => ['alternate'],
        '--systems' => ['pooled'], '--low-end' => ['low'], '--dry-run' => true, '--jobs' => '1',
    ];

    private const PSL = [
        '--owner' => ['psl'],
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
        self::assertStringContainsString('1× tms2 + 1× tms4 + 1× tms2', $tester->getDisplay());
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
     * Innschleife states its low end, so a run of Innschleife alone has no low end left to sweep, whatever `--low-end`
     * asks for, and its one candidate is named `stated` there too.
     */
    public function testTheEventsLowEndReplacesTheSweptOne(): void
    {
        $display = $this->invoke([...self::OPTIONS, '--low-end' => ['low', 'central']])->getDisplay();

        self::assertStringContainsString('center-stated--', $display);
        self::assertStringNotContainsString('center-central', $display);
        self::assertStringContainsString('--system-low-end=innschleife:low', $display);
        self::assertStringContainsString('--system-low-end=sdwa5:central', $display);
    }

    /**
     * **The combined next-event rig carries both wanted layouts at once.** Ours is central, two rows of [3 Flexy |
     * SKRAM | 3 Flexy] over the Achenbach, and Innschleife's is low, the photo. Both low ends are still swept for PSL,
     * which states none, and they build the same rig, so the second is dropped as a duplicate of the first.
     */
    public function testTheCombinedRigCarriesEachSystemsOwnLowEnd(): void
    {
        $tester = $this->invoke([
            '--owner' => ['sdwa5', 'sepp', 'psl', 'innschleife'],
            '--event' => 'next-event', '--into' => 'next-event', '--order' => ['ours,psl,innschleife'],
            '--systems' => ['systems-apart'], '--stacks' => '1', '--align' => ['center'], '--shape' => ['pyramid'],
            '--mirror-style' => ['alternate'], '--dry-run' => true, '--jobs' => '1',
        ]);

        $display = $tester->getDisplay();
        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(2, substr_count($display, '3× flexy-folded-horn-hybrid + 1× skram + 3× flexy-folded-horn-hybrid'));
        self::assertSame(2, substr_count($display, '1× wsx-18 rolled 270° + 1× sbh-18 rolled 270° + 1× sbh-18 rolled 90° + 1× wsx-18 rolled 90°'));
        self::assertStringContainsString('center-central-possible — the same rig as stacked-1-systems-apart-pyramid-stated--alternate-center-low-----possible', $display);
    }

    /**
     * **The combined next-event rig carries Innschleife's photo layout**, which it could not at 0.5 m between stacks:
     * our 4.275 m, PSL's 3.58 m and the photo rig's 4.66 m are 13.515 m with two 0.5 m gaps, and 12.995 m with the
     * event's 0.24 m.
     */
    public function testTheCombinedRigFitsThePhotoLayoutIntoTheRoom(): void
    {
        $tester = $this->invoke([
            '--owner' => ['sdwa5', 'sepp', 'psl', 'innschleife'],
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
        self::assertStringContainsString('1× tms2 + 1× tms4 + 1× tms2', $display);
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

    /** PSL brings the deco panel to the event, and the event hangs it from our truss behind the rig. */
    public function testABroughtDecoPanelHangsFromTheEventsTruss(): void
    {
        $tester = $this->invoke(self::PSL);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('--backdrop=truss-f33-2m:5:truss-tower-4m', $display);
        self::assertStringContainsString('--quantity=deco-panel-9x1-8:1', $display);
        self::assertStringContainsString('device: deco-panel-9x1-8', $display);
        self::assertStringContainsString('extend_to_m: 3.742', $display);
        self::assertStringNotContainsString('-impossible', $display);
    }

    public function testADecoPanelWithNoTrussIsRefused(): void
    {
        $tester = $this->invoke(array_diff_key(self::PSL, ['--event' => true]) + [
            '--quantity' => ['deco-panel-9x1-8:1'], '--into' => self::THROWAWAY_ID,
        ]);

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
