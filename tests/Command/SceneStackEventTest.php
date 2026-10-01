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
        self::assertStringContainsString('--quantity=deco-panel-10x2-5:1', $display);
        self::assertStringContainsString('device: deco-panel-10x2-5', $display);
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
