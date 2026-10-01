<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Scene\RoomBounds;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class RoomBoundsTest extends TestCase
{
    public function testTheCombinedWidthIncludesTheAirBetweenStacks(): void
    {
        $rig = [$this->box(-4.0), $this->box(4.0)];

        self::assertNull((new RoomBounds(13.0, 4.0))->problem($rig));
        self::assertStringContainsString('13.000 m wide', (new RoomBounds(12.99, 4.0))->problem($rig) ?? '');
    }

    public function testHeightUsesThePlacedTopIncludingItsElevation(): void
    {
        self::assertNull((new RoomBounds(13.0, 4.0))->problem([$this->box(0.0, 3.0)]));
        self::assertStringContainsString('room height', (new RoomBounds(13.0, 4.0))->problem([$this->box(0.0, 3.01)]) ?? '');
    }

    public function testYawChangesTheCheckedWidth(): void
    {
        $cabinet = $this->box(0.0, 0.0, 90.0);

        self::assertNull((new RoomBounds(1.0))->problem([$cabinet]));
        self::assertStringContainsString('room width', (new RoomBounds(0.99))->problem([$cabinet]) ?? '');
    }

    public function testNoBoundsKeepTheExistingBehavior(): void
    {
        self::assertNull((new RoomBounds())->problem([$this->box(100.0, 100.0)]));
    }

    public function testAnInfiniteLimitIsNotAccepted(): void
    {
        $this->expectException(InvalidSpecException::class);
        new RoomBounds(INF, 4.0);
    }

    private function box(float $x, float $z = 0.0, float $yaw = 0.0): PlacedDevice
    {
        return new PlacedDevice('box', SpecFactory::spec([
            'geometry' => ['dimensions_m' => ['width' => 5.0, 'height' => 1.0, 'depth' => 1.0]],
        ]), [$x, 0.0, $z], new Orientation(yawDeg: $yaw));
    }
}
