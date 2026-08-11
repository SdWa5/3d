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

    /**
     * The second rule: an output built with other settings is stale even though no input moved. This is the case
     * mtimes cannot see at all — raising the default resolution changes nothing on disk.
     */
    public function testAnOutputBuiltWithOtherSettingsIsStale(): void
    {
        $output = $this->write('out.png', 200);
        Staleness::recordSettings($output, ['samples' => 64, 'resolution' => [1600, 900]]);

        self::assertFalse(Staleness::settingsChanged($output, ['samples' => 64, 'resolution' => [1600, 900]]));
        self::assertTrue(Staleness::settingsChanged($output, ['samples' => 128, 'resolution' => [1920, 1080]]));
    }

    /** Any one setting differing is enough; there is no such thing as a difference that does not show. */
    public function testASingleChangedSettingIsEnough(): void
    {
        $output = $this->write('out.png', 200);
        $settings = ['lighting' => 'studio', 'samples' => 128, 'ground' => true, 'aim_lines' => 'none'];
        Staleness::recordSettings($output, $settings);

        foreach (['lighting' => 'stage', 'samples' => 129, 'ground' => false, 'aim_lines' => 'tops'] as $key => $value) {
            self::assertTrue(
                Staleness::settingsChanged($output, [$key => $value] + $settings),
                "a changed {$key} should be stale",
            );
        }
    }

    /**
     * What makes it self-healing. Every render made before stamps existed has none, so each re-renders once at
     * whatever is now being asked for and carries a stamp afterwards — no `--force` sweep needed.
     */
    public function testAnOutputWithNoStampAtAllIsStale(): void
    {
        $output = $this->write('unstamped.png', 200);

        self::assertTrue(Staleness::settingsChanged($output, ['samples' => 128]));
    }

    /** A stamp nobody can read tells us nothing, so rebuild rather than trust it. */
    public function testAnUnreadableStampIsStale(): void
    {
        $output = $this->write('out.png', 200);
        file_put_contents(Staleness::stampFor($output), '{ this is not json');

        self::assertTrue(Staleness::settingsChanged($output, ['samples' => 128]));
    }

    /**
     * A missing output is {@see Staleness::outOfDate}'s answer, not this rule's — otherwise a caller that asked
     * only this question would be told a file it never built is fine.
     */
    public function testAMissingOutputIsNotThisRulesBusiness(): void
    {
        self::assertFalse(Staleness::settingsChanged($this->path('gone.png'), ['samples' => 128]));
    }

    /** The order the caller built the array in is not a change worth re-rendering for. */
    public function testKeyOrderIsNotADifference(): void
    {
        $output = $this->write('out.png', 200);
        Staleness::recordSettings($output, ['samples' => 128, 'lighting' => 'studio']);

        self::assertFalse(Staleness::settingsChanged($output, ['lighting' => 'studio', 'samples' => 128]));
    }

    /** Hidden, and beside the file it describes, so nothing that lists renders trips over it. */
    public function testTheStampSitsBesideItsOutputAndIsHidden(): void
    {
        $stamp = Staleness::stampFor('/build/renders/studio/full-rig-three-quarter.png');

        self::assertSame('/build/renders/studio', dirname($stamp));
        self::assertStringStartsWith('.', basename($stamp));
    }

    /** Two renders in one folder must not share a stamp, or each would report the other's settings. */
    public function testEachOutputGetsItsOwnStamp(): void
    {
        self::assertNotSame(
            Staleness::stampFor('/r/full-rig-side.png'),
            Staleness::stampFor('/r/full-rig-three-quarter.png'),
        );
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
