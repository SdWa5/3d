<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\Staleness;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The one freshness rule the whole pipeline shares.
 *
 * Worth its own tests because getting it wrong is invisible in one direction and expensive in the other: too
 * eager and every build re-renders 72 frames nobody asked for; too lazy and a measured cabinet never reaches
 * the picture.
 */
final class StalenessTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = SpecFactory::tempDir();
    }

    protected function tearDown(): void
    {
        SpecFactory::removeDir($this->dir);
    }

    public function testAMissingOutputIsAlwaysStale(): void
    {
        self::assertTrue(Staleness::outOfDate([$this->path('gone.png')], [$this->write('in.yaml', 100)]));
    }

    public function testNoOutputsAtAllIsStale(): void
    {
        self::assertTrue(Staleness::outOfDate([], []), 'nothing built cannot be up to date');
    }

    public function testAnOutputNewerThanEveryInputIsFresh(): void
    {
        $input = $this->write('in.yaml', 100);
        $output = $this->write('out.png', 200);

        self::assertFalse(Staleness::outOfDate([$output], [$input]));
    }

    public function testAnInputNewerThanTheOutputIsStale(): void
    {
        $output = $this->write('out.png', 100);
        $input = $this->write('in.yaml', 200);

        self::assertTrue(Staleness::outOfDate([$output], [$input]));
    }

    /**
     * The **oldest** output decides, so a stage with several — a model is a `.glb` and a `.blend` — is fresh
     * only when every one of them is. Taking the newest would call a half-finished build done.
     */
    public function testTheOldestOutputDecides(): void
    {
        $input = $this->write('in.yaml', 150);
        $fresh = $this->write('a.glb', 200);
        $stale = $this->write('b.blend', 100);

        self::assertTrue(Staleness::outOfDate([$fresh, $stale], [$input]));
    }

    /**
     * A missing input cannot have changed, and complaining about it is not this check's job — the stage that
     * needs it fails with a better message than "stale" would be.
     */
    public function testAMissingInputIsIgnoredRatherThanTreatedAsChanged(): void
    {
        $output = $this->write('out.png', 200);

        self::assertFalse(Staleness::outOfDate([$output], [$this->path('never-existed.py')]));
    }

    /** Editing anything under `blender/lib` counts, because every script imports from it. */
    public function testBlenderInputsIncludeTheWholeLibrary(): void
    {
        $inputs = Staleness::blenderInputs(dirname(__DIR__, 2), 'blender/render_scene.py');

        self::assertContains(dirname(__DIR__, 2).'/blender/render_scene.py', $inputs);
        self::assertGreaterThan(1, count($inputs), 'blender/lib/*.py should be in there too');
    }

    private function path(string $name): string
    {
        return $this->dir.'/'.$name;
    }

    private function write(string $name, int $mtime): string
    {
        $path = $this->path($name);
        file_put_contents($path, 'x');
        touch($path, $mtime);

        return $path;
    }
}
