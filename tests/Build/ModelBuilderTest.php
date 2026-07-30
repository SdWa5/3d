<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use App\Spec\DeviceSpec;
use App\Tests\Support\FakeProcessRunner;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModelBuilderTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = SpecFactory::tempDir();
    }

    protected function tearDown(): void
    {
        SpecFactory::removeDir($this->project);
    }

    public function testOutputPathsLiveUnderBuild(): void
    {
        $builder = $this->builder();
        $spec = $this->spec();

        self::assertSame($this->project.'/build/glb/top-a.glb', $builder->glbPath($spec));
        self::assertSame($this->project.'/build/blend/top-a.blend', $builder->blendPath($spec));
        self::assertSame($this->project.'/build/library/sdwa5-3d.blend', $builder->libraryPath());
    }

    public function testMissingOutputIsStale(): void
    {
        self::assertTrue($this->builder()->isStale($this->spec()));
    }

    public function testUpToDateOutputIsNotStale(): void
    {
        $spec = $this->spec();
        $this->writeOutputs($spec, time() + 10);

        self::assertFalse($this->builder()->isStale($spec));
    }

    public function testAnEditedSpecMakesTheModelStale(): void
    {
        $spec = $this->spec();
        $this->writeOutputs($spec, time() - 100);
        touch($spec->sourcePath, time());

        self::assertTrue($this->builder()->isStale($spec));
    }

    public function testAnEditedBuilderScriptMakesEveryModelStale(): void
    {
        $spec = $this->spec();
        $this->writeOutputs($spec, time() - 100);
        touch($spec->sourcePath, time() - 200);
        self::assertFalse($this->builder()->isStale($spec), 'precondition: up to date before the script changes');

        // A change to the geometry builder has to rebuild everything, not just edited specs.
        $script = $this->project.'/'.ModelBuilder::MODEL_SCRIPT;
        mkdir(dirname($script), 0o775, true);
        file_put_contents($script, '# builder');
        touch($script, time());

        self::assertTrue($this->builder()->isStale($spec));
    }

    public function testBuildWritesThePlanForBlender(): void
    {
        $spec = $this->spec();

        try {
            $this->builder()->build($spec);
        } catch (RuntimeException) {
            // Expected: the fake Blender writes no files. The plan is what this test is about.
        }

        $planFile = $this->project.'/build/plans/top-a.json';
        self::assertFileExists($planFile);

        $plan = json_decode((string)file_get_contents($planFile), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('top-a', $plan['id']);
        self::assertSame($this->project.'/build/glb/top-a.glb', $plan['outputs']['glb']);
        self::assertSame(0.8, $plan['geometry']['dimensions_m']['width']);
    }

    public function testBuildFailsLoudlyWhenBlenderWritesNothing(): void
    {
        // Blender can exit 0 after a script error, so a silent no-op must not look like success.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/reported success but did not write/');
        $this->builder()->build($this->spec());
    }

    public function testLibraryPlanListsEveryDeviceWithItsWidth(): void
    {
        $spec = $this->spec();

        try {
            $this->builder()->buildLibrary([$spec]);
        } catch (RuntimeException) {
            // Expected: no real Blender, so no library file appears.
        }

        $planFile = $this->project.'/build/plans/_library.json';
        self::assertFileExists($planFile);

        $plan = json_decode((string)file_get_contents($planFile), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($this->project.'/build/library/sdwa5-3d.blend', $plan['output']);
        self::assertCount(1, $plan['devices']);
        self::assertSame('top-a', $plan['devices'][0]['id']);
        self::assertSame(0.8, $plan['devices'][0]['width_m']);
        self::assertSame($this->project.'/build/blend/top-a.blend', $plan['devices'][0]['blend']);
    }

    private function builder(): ModelBuilder
    {
        $fake = new FakeProcessRunner();
        $fake->on('which ', 0, "/usr/bin/blender\n");
        $fake->on('--background', 0, 'pretending to build');

        return new ModelBuilder($this->project, new BlenderRunner($fake));
    }

    private function spec(): DeviceSpec
    {
        $file = SpecFactory::writeYaml($this->project.'/specs/speakers');

        return SpecFactory::spec([], $file);
    }

    private function writeOutputs(DeviceSpec $spec, int $mtime): void
    {
        $builder = $this->builder();
        foreach ([$builder->glbPath($spec), $builder->blendPath($spec)] as $path) {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o775, true);
            }
            file_put_contents($path, 'x');
            touch($path, $mtime);
        }
    }
}
