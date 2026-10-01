<?php

declare(strict_types=1);

namespace App\Tests\Render;

use App\Render\FlyThroughPlan;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\SpecLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FlyThroughPlanTest extends TestCase
{
    public function testTheOuterStacksDecideTheRouteRegardlessOfInputOrder(): void
    {
        $route = FlyThroughPlan::between([
            'middle' => [0.0, -10.0, 1.8], 'right' => [5.0, -11.0, 1.8], 'left' => [-4.0, -9.0, 1.8],
        ], [0.0, 0.0, 2.0], 6.0, 24);
        self::assertSame('left', $route['start_stack']);
        self::assertSame('right', $route['end_stack']);
        self::assertSame([-4.0, -9.0, 1.8], $route['start']);
        self::assertSame([5.0, -11.0, 1.8], $route['end']);
        self::assertSame(144, $route['frames']);
    }

    public function testPerpendicularAimIsCarriedIntoTheAnimationPlan(): void
    {
        $route = FlyThroughPlan::between([
            'left' => [-4.0, -10.0, 1.8], 'right' => [4.0, -10.0, 1.8],
        ], [0.0, 0.0, 2.0], cameraAim: 'perpendicular');
        self::assertSame('perpendicular', $route['camera_aim']);
        self::assertSame([-4.0, -10.0, 1.8], $route['start']);
        self::assertSame([4.0, -10.0, 1.8], $route['end']);
    }

    public function testTheRealSceneUsesEachSystemsOwnFrontPlane(): void
    {
        $root = dirname(__DIR__, 2);
        $devices = [];
        foreach ((new SpecLoader($root.'/specs'))->loadAll()['specs'] as $spec) {
            $devices[$spec->id] = $spec;
        }
        $scene = (new SceneLoader($root.'/scenes'))->load($root.'/scenes/generated/next-event-light/'
            .'stacked-1-systems-apart-pyramid-stated--alternate-stereo-low-----possible.yaml');
        $points = (new SceneCompiler($devices))->stackFocusPoints($scene);
        self::assertCount(3, $points);
        self::assertEqualsWithDelta(-0.494, $points['main-psl'][0], 1e-6);
        self::assertEqualsWithDelta(-10.4575, $points['main-psl'][1], 1e-6);
        self::assertSame(1.8, $points['main-psl'][2]);
        $route = FlyThroughPlan::between($points, [0.0, 0.0, 2.0]);
        self::assertSame('main-ours', $route['start_stack']);
        self::assertSame('main-innschleife', $route['end_stack']);
    }

    #[DataProvider('invalidRoutes')]
    public function testInvalidRoutesAreRejected(array $points, float $seconds, int $fps): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FlyThroughPlan::between($points, [0.0, 0.0, 2.0], $seconds, $fps);
    }

    public static function invalidRoutes(): array
    {
        $pair = ['left' => [-1.0, -10.0, 1.8], 'right' => [1.0, -10.0, 1.8]];

        return [
            'one stack' => [['one' => [0.0, -10.0, 1.8]], 6.0, 24],
            'no horizontal move' => [['a' => [0.0, -10.0, 1.8], 'b' => [0.0, -11.0, 1.8]], 6.0, 24],
            'zero duration' => [$pair, 0.0, 24],
            'infinite duration' => [$pair, INF, 24],
            'zero fps' => [$pair, 6.0, 0],
            'too many fps' => [$pair, 6.0, 61],
        ];
    }
}
