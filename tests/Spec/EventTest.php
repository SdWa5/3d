<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Scene\StackOrientation;
use App\Spec\DeviceSpec;
use App\Spec\Event;
use App\Spec\InvalidSpecException;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testTheSavedNextEventHasTheUsersHardRoomLimits(): void
    {
        $event = Event::load(dirname(__DIR__, 2).'/events', 'next-event');

        self::assertSame(13.0, $event->room->widthM);
        self::assertSame(4.0, $event->room->heightM);
        self::assertSame(['interface_height_m' => 1.6, 'target_sub_height_m' => 1.75], $event->systems['innschleife']);
        self::assertSame('truss-f33-2m:5:truss-tower-4m', $event->backdrop);
    }

    /** Stated on 2026-10-01: ours and Sepp's upright, PSL and Innschleife turned, Innschleife's kickers as measured. */
    public function testTheNextEventStatesHowEachSystemIsSetUp(): void
    {
        $event = Event::load(dirname(__DIR__, 2).'/events', 'next-event');

        self::assertSame(
            ['sdwa5' => 'upright', 'sepp' => 'upright', 'psl' => 'turned', 'innschleife' => 'turned'],
            array_map(static fn (StackOrientation $o): string => $o->value, $event->orientations),
        );
        self::assertSame(['kicker-15'], $event->standing);
        self::assertArrayNotHasKey('psl', $event->systems);
    }

    /** 0.24 m between stacks is what fits the combined rig with Innschleife's photo layout into the 13 m room. */
    public function testTheNextEventNarrowsTheAirBetweenStacks(): void
    {
        self::assertSame(0.24, Event::load(dirname(__DIR__, 2).'/events', 'next-event')->clearanceM);
        self::assertNull(Event::fromArray(['id' => 'e', 'name' => 'E', 'room' => ['width_m' => 13, 'height_m' => 4]])->clearanceM);
    }

    public function testANegativeStackClearanceIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray(['id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => 13, 'height_m' => 4], 'stack_clearance_m' => -0.1]);
    }

    public function testAnInterfaceWithoutItsTargetIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray([
            'id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => 13, 'height_m' => 4],
            'systems' => ['psl' => ['interface_height_m' => 2.0, 'orientation' => 'turned']],
        ]);
    }

    public function testAnUnknownOrientationIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray([
            'id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => 13, 'height_m' => 4],
            'systems' => ['psl' => ['orientation' => 'sideways']],
        ]);
    }

    public function testAnEventWithoutABackdropNamesNone(): void
    {
        self::assertNull(Event::fromArray(['id' => 'e', 'name' => 'E', 'room' => ['width_m' => 13, 'height_m' => 4]])->backdrop);
    }

    public function testANegativeRoomDimensionIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray(['id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => -1, 'height_m' => 4]]);
    }

    public function testAMissingEventIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::load(dirname(__DIR__, 2).'/events', '../next-event');
    }

    /** What PSL and Innschleife bring to the next event, as the roster files stated it until 0.130.0. */
    public function testTheNextEventStatesWhatEachSystemBrings(): void
    {
        $event = Event::load(dirname(__DIR__, 2).'/events', 'next-event');

        self::assertSame(['psl', 'innschleife'], array_keys($event->brings));
        self::assertSame(12, $event->brings['psl']['concert-audio-esx']);
        self::assertSame(1, $event->brings['psl']['deco-panel-10x3-03']);
        self::assertSame(['wsx-18' => 4, 'sbh-18' => 4, 'kicker-15' => 4, 'sub-60x60' => 0, 'tms2' => 2, 'top-70x93' => 1, 'tms4' => 0], $event->brings['innschleife']);
    }

    /**
     * **Zero is a count, not an omission**, which is the whole of how a cabinet is left at home. A reader who cannot
     * tell "they are not bringing it" from "the file forgot to mention it" has no count at all.
     */
    public function testZeroSurvivesAsACountRatherThanBeingDropped(): void
    {
        $event = Event::fromArray(['id' => 'e', 'name' => 'E', 'systems' => ['psl' => ['brings' => ['a-top' => 0]]]]);

        self::assertSame(['psl' => ['a-top' => 0]], $event->brings);
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage('systems.psl.brings.a-sub is -1');

        Event::fromArray(['id' => 'e', 'name' => 'E', 'systems' => ['psl' => ['brings' => ['a-sub' => -1]]]]);
    }

    public function testACountThatIsNotAWholeNumberIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);

        Event::fromArray(['id' => 'e', 'name' => 'E', 'systems' => ['psl' => ['brings' => ['a-sub' => 'two']]]]);
    }

    /** A past event is worth recording for what was brought even when nobody measured the hall. */
    public function testAnEventWithoutARoomSetsNoLimit(): void
    {
        $event = Event::load(dirname(__DIR__, 2).'/events', 'mark-salzburg-2026-09-19');

        self::assertNull($event->room->widthM);
        self::assertNull($event->room->heightM);
        self::assertSame(['flexy-folded-horn-hybrid' => 12, 'skram' => 2, 'tecnare-m2122' => 2], $event->brings['sdwa5']);
    }

    /**
     * **Every committed event parses, and every system in it brings only devices that exist and are its own.** A
     * file that no longer parses would otherwise only surface in a sweep somebody runs by hand.
     */
    public function testTheCommittedEventsBringOnlyTheirOwnSystemsDevices(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $owners = [];
        foreach ((new SpecLoader($projectDir.'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $owners[$spec->id] = $spec->owner;
        }

        $files = glob($projectDir.'/events/*.yaml') ?: [];
        self::assertNotSame([], $files);
        foreach ($files as $file) {
            $event = Event::load($projectDir.'/events', basename($file, '.yaml'));
            foreach ($event->brings as $owner => $brings) {
                foreach (array_keys($brings) as $device) {
                    self::assertSame($owner, $owners[$device] ?? null, sprintf('%s: %s brings %s', $event->id, $owner, $device));
                }
            }
        }
    }

    public function testATargetBelowItsInterfaceIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray([
            'id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => 13, 'height_m' => 4],
            'systems' => ['innschleife' => ['interface_height_m' => 2.0, 'target_sub_height_m' => 1.0]],
        ]);
    }
}
