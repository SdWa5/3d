<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\Event;
use App\Spec\InvalidSpecException;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testTheSavedNextEventHasTheUsersHardRoomLimits(): void
    {
        $event = Event::load(dirname(__DIR__, 2).'/events', 'next-event');

        self::assertSame(13.0, $event->room->widthM);
        self::assertSame(4.0, $event->room->heightM);
        self::assertSame(['interface_height_m' => 1.6, 'target_sub_height_m' => 1.75], $event->systems['innschleife']);
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

    public function testATargetBelowItsInterfaceIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        Event::fromArray([
            'id' => 'bad', 'name' => 'Bad', 'room' => ['width_m' => 13, 'height_m' => 4],
            'systems' => ['innschleife' => ['interface_height_m' => 2.0, 'target_sub_height_m' => 1.0]],
        ]);
    }
}
