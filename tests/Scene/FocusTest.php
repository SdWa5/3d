<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Focus;
use App\Scene\SceneSpec;
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

    /**
     * One focus or a map of them, told apart by shape alone: if every value under `focus` is itself a
     * mapping it is a map of named ones, otherwise it is the single unnamed one. That keeps every scene
     * written before names existed working verbatim.
     */
    public function testAMapOfMapsIsReadAsNamedFociAndAMapOfNumbersAsOne(): void
    {
        $single = SceneSpec::fromArray([
            'id' => 's', 'name' => 'S',
            'focus' => ['distance_m' => 4.0, 'height_m' => 1.2],
            'placements' => [],
        ], '/scenes/s.yaml');

        self::assertSame(['focus'], array_keys($single->focusByName));
        self::assertSame(4.0, $single->focusByName['focus']->distanceM);

        $named = SceneSpec::fromArray([
            'id' => 's', 'name' => 'S',
            'focus' => ['near' => ['distance_m' => 2.0], 'far' => ['distance_m' => 10.0]],
            'placements' => [],
        ], '/scenes/s.yaml');

        self::assertSame(['near', 'far'], array_keys($named->focusByName));
        self::assertSame(2.0, $named->focusByName['near']->distanceM);
        self::assertSame(10.0, $named->focusByName['far']->distanceM);
        // The unstated height still falls back to ear height rather than to the other focus's.
        self::assertSame(Focus::DEFAULT_HEIGHT_M, $named->focusByName['near']->heightM);
    }

    public function testASceneWithNoFocusAtAllStillHasTheDefaultOne(): void
    {
        $scene = SceneSpec::fromArray(['id' => 's', 'name' => 'S', 'placements' => []], '/scenes/s.yaml');

        self::assertSame(['focus'], array_keys($scene->focusByName));
        self::assertSame(Focus::DEFAULT_DISTANCE_M, $scene->focusByName['focus']->distanceM);
    }
}
