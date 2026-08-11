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
        Staleness::recordSettings($this->manifest(), $output, ['samples' => 64, 'resolution' => [1600, 900]]);

        self::assertFalse(
            Staleness::settingsChanged($this->manifest(), $output, ['samples' => 64, 'resolution' => [1600, 900]]),
        );
        self::assertTrue(
            Staleness::settingsChanged($this->manifest(), $output, ['samples' => 128, 'resolution' => [1920, 1080]]),
        );
    }

    /** Any one setting differing is enough; there is no such thing as a difference that does not show. */
    public function testASingleChangedSettingIsEnough(): void
    {
        $output = $this->write('out.png', 200);
        $settings = ['lighting' => 'studio', 'samples' => 128, 'ground' => true, 'aim_lines' => 'none'];
        Staleness::recordSettings($this->manifest(), $output, $settings);

        foreach (['lighting' => 'stage', 'samples' => 129, 'ground' => false, 'aim_lines' => 'tops'] as $key => $value) {
            self::assertTrue(
                Staleness::settingsChanged($this->manifest(), $output, [$key => $value] + $settings),
                "a changed {$key} should be stale",
            );
        }
    }

    /**
     * What makes it self-healing. Every render made before the manifest existed is absent from it, so each
     * redraws once at whatever is now being asked for and is recorded after — no `--force` sweep needed.
     */
    public function testAnOutputAbsentFromTheManifestIsStale(): void
    {
        $recorded = $this->write('recorded.png', 200);
        Staleness::recordSettings($this->manifest(), $recorded, ['samples' => 128]);

        $unrecorded = $this->write('unrecorded.png', 200);

        self::assertTrue(Staleness::settingsChanged($this->manifest(), $unrecorded, ['samples' => 128]));
    }

    /** A manifest nobody can parse tells us nothing, so everything in its tree redraws rather than be trusted. */
    public function testAnUnreadableManifestMakesEverythingStale(): void
    {
        $output = $this->write('out.png', 200);
        file_put_contents($this->manifest(), '{ this is not json');

        self::assertTrue(Staleness::settingsChanged($this->manifest(), $output, ['samples' => 128]));
    }

    /**
     * A missing output is {@see Staleness::outOfDate}'s answer, not this rule's — otherwise a caller that asked
     * only this question would be told a file it never built is fine.
     */
    public function testAMissingOutputIsNotThisRulesBusiness(): void
    {
        self::assertFalse(Staleness::settingsChanged($this->manifest(), $this->path('gone.png'), ['samples' => 1]));
    }

    /** The order the caller built the array in is not a change worth re-rendering for. */
    public function testKeyOrderIsNotADifference(): void
    {
        $output = $this->write('out.png', 200);
        Staleness::recordSettings($this->manifest(), $output, ['samples' => 128, 'lighting' => 'studio']);

        self::assertFalse(
            Staleness::settingsChanged($this->manifest(), $output, ['lighting' => 'studio', 'samples' => 128]),
        );
    }

    /**
     * The whole point of one manifest: recording one output must leave every other entry alone, or a sweep would
     * forget everything it rendered before the last picture.
     */
    public function testRecordingOneOutputLeavesTheOthersAlone(): void
    {
        $first = $this->write('first.png', 200);
        $second = $this->write('second.png', 200);

        Staleness::recordSettings($this->manifest(), $first, ['samples' => 16]);
        Staleness::recordSettings($this->manifest(), $second, ['samples' => 384]);

        self::assertFalse(Staleness::settingsChanged($this->manifest(), $first, ['samples' => 16]));
        self::assertFalse(Staleness::settingsChanged($this->manifest(), $second, ['samples' => 384]));
    }

    /** Two renders must not share an entry, or each would report the other's settings. */
    public function testTwoOutputsInOneFolderDoNotShareAnEntry(): void
    {
        $side = $this->write('full-rig-side.png', 200);
        $threeQuarter = $this->write('full-rig-three-quarter.png', 200);

        Staleness::recordSettings($this->manifest(), $side, ['camera' => 'side']);

        self::assertFalse(Staleness::settingsChanged($this->manifest(), $side, ['camera' => 'side']));
        self::assertTrue(
            Staleness::settingsChanged($this->manifest(), $threeQuarter, ['camera' => 'side']),
            'the other camera has no entry of its own yet',
        );
    }

    /**
     * One manifest covers a whole tree, so a render in a variant subfolder is keyed by its path relative to the
     * root — readable, and still true if the build directory moves to another machine.
     */
    public function testAnOutputInASubfolderIsKeyedRelativeToTheManifest(): void
    {
        mkdir($this->path('studio'));
        $output = $this->write('studio/full-rig-side.png', 200);

        Staleness::recordSettings($this->manifest(), $output, ['lighting' => 'studio']);

        $entries = json_decode((string)file_get_contents($this->manifest()), true);

        self::assertSame(['studio/full-rig-side.png'], array_keys($entries));
    }

    /** One file for the tree, at its root, named so that opening it explains itself. */
    public function testTheManifestSitsAtTheRootOfTheTree(): void
    {
        self::assertSame('/build/renders/built-with.json', Staleness::manifestIn('/build/renders'));
        self::assertSame('/build/renders/built-with.json', Staleness::manifestIn('/build/renders/'));
    }

    private function manifest(): string
    {
        return Staleness::manifestIn($this->dir);
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
