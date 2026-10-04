<?php

declare(strict_types=1);

namespace App\Tests\Render;

use App\Build\CompiledScene;
use App\Render\CameraPreset;
use App\Render\RenderPlan;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **The guard on TOOL-11.** A render plan drawn from the record `scene:build` stored is the plan a fresh solve gives.
 *
 * `scene:render` reads {@see \App\Render\RenderPlacement}s instead of solving, so anything {@see RenderPlan} reads
 * of a placement that the record does not carry would silently change the picture. Compared with `assertSame` on
 * the whole plan, every camera, every aim mode and labels on, against the hand-written scenes, the packed convoy
 * (vehicles, packed cabinets, labels) and a cranked-down tower (a device that differs from its spec).
 */
final class RenderPlanFromCompiledSceneTest extends TestCase
{
    private const SCENES = [
        'scenes/full-rig-stereo.yaml',
        'scenes/full-rig-arc-turned.yaml',
        'scenes/full-rig-truss.yaml',
        'scenes/flown-array.yaml',
        'scenes/end-fire-lattice.yaml',
        'scenes/everything.yaml',
        'scenes/packs/packed-convoy.yaml',
        'scenes/_solo/fav-ours-flipped.yaml',
    ];

    /** @var array<string, DeviceSpec>|null */
    private static ?array $devices = null;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = SpecFactory::tempDir();
    }

    protected function tearDown(): void
    {
        SpecFactory::removeDir($this->dir);
    }

    #[DataProvider('scenes')]
    public function testAStoredSolveDrawsThePictureAFreshOneWould(string $path): void
    {
        $project = dirname(__DIR__, 2);
        $scene = (new SceneLoader($project.'/scenes'))->load($project.'/'.$path);
        ['placed' => $placed, 'violations' => $violations] = (new SceneCompiler(self::devices()))->compile($scene);
        self::assertSame([], Violation::errorsIn($violations), "{$path} has to compile to be worth comparing");
        self::assertNotSame([], $placed);

        $file = CompiledScene::fileIn($this->dir, $scene->id);
        CompiledScene::write($file, $scene->id, $placed, Violation::warningsIn($violations));
        $stored = CompiledScene::read($file, $scene->id, []);
        self::assertNotNull($stored);

        foreach (CameraPreset::cases() as $camera) {
            foreach ([RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS, RenderPlan::AIM_ALL] as $aim) {
                self::assertSame(
                    RenderPlan::forScene($placed, $camera, aimLines: $aim, labels: true),
                    RenderPlan::forScene($stored['placements'], $camera, aimLines: $aim, labels: true),
                    "{$path}, {$camera->value} camera, aim lines {$aim}",
                );
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scenes(): iterable
    {
        foreach (self::SCENES as $path) {
            yield $path => [$path];
        }
    }

    /**
     * @return array<string, DeviceSpec>
     */
    private static function devices(): array
    {
        if (null === self::$devices) {
            self::$devices = [];
            foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
                self::$devices[$spec->id] = $spec;
            }
        }

        return self::$devices;
    }
}
