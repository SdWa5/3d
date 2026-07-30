<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Focus;
use PHPUnit\Framework\TestCase;

final class FocusTest extends TestCase
{
    public function testDefaultsAreTenMetresOutAtEarHeight(): void
    {
        $focus = new Focus();

        self::assertSame(10.0, $focus->distanceM);
        self::assertSame(1.8, $focus->heightM);
        self::assertNull($focus->xM);
    }

    public function testDistanceIsMeasuredFromTheRigsFrontFace(): void
    {
        // Cabinets face -Y, so "in front" is decreasing y. A rig whose front face is at y = -0.5 puts a
        // 10 m focus at y = -10.5, not -10 — otherwise a deeper rig would quietly pull the focus closer.
        $point = (new Focus(10.0, 1.8))->point([1.25, -0.5]);

        self::assertSame([1.25, -10.5, 1.8], $point);
    }

    public function testXDefaultsToTheRigCentreButCanBeOverridden(): void
    {
        self::assertSame(2.0, (new Focus())->point([2.0, 0.0])[0]);
        self::assertSame(-3.0, (new Focus(10.0, 1.8, -3.0))->point([2.0, 0.0])[0]);
    }
}
