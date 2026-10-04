<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\CompiledScene;
use App\Render\RenderPlacement;
use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The solve `scene:build` leaves for `scene:render` (TOOL-11).
 *
 * The direction that matters is the lazy one. A record trusted after its inputs moved frames the camera on a rig the
 * `.blend` no longer holds, and nothing complains, so every way of going stale has a case here.
 */
final class CompiledSceneTest extends TestCase
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

    public function testTheRecordSitsBesideTheSceneBuildPlan(): void
    {
        self::assertSame('build/plans/gmss/_compiled-rig.json', CompiledScene::fileIn('build/plans/gmss/', 'rig'));
    }

    public function testWhatIsWrittenIsWhatIsRead(): void
    {
        $placed = [$this->placed('a', [0.0, 0.0, 0.0]), $this->placed('b', [1.3, 0.2, 0.6])];
        $warning = new Violation('/scenes/rig.yaml', 'a tier stands proud', Violation::WARNING);
        $input = $this->touched('rig.yaml', 100);
        $file = $this->record($placed, [$warning], 200);

        $read = CompiledScene::read($file, 'rig', [$input]);

        self::assertNotNull($read);
        self::assertEquals(RenderPlacement::listOf($placed), $read['placements']);
        self::assertEquals([$warning], $read['warnings']);
    }

    public function testAnInputNewerThanTheRecordMakesItUnusable(): void
    {
        $file = $this->record([$this->placed('a', [0.0, 0.0, 0.0])], [], 100);

        foreach (['rig.yaml', 'specs/top-a.yaml', 'src/Scene/SceneCompiler.php'] as $name) {
            self::assertNull(
                CompiledScene::read($file, 'rig', [$this->touched(str_replace('/', '-', $name), 200)]),
                "{$name} moved after the solve",
            );
        }
    }

    public function testAMissingRecordIsNoRecord(): void
    {
        self::assertNull(CompiledScene::read($this->dir.'/gone.json', 'rig', []));
    }

    public function testARecordOfAnotherSceneOrAnotherVersionIsNotTrusted(): void
    {
        $file = $this->record([$this->placed('a', [0.0, 0.0, 0.0])], [], 200);
        self::assertNull(CompiledScene::read($file, 'other-rig', []), 'same file name, another scene id');

        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $data['plan_version'] = CompiledScene::PLAN_VERSION + 1;
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        touch($file, 200);
        self::assertNull(CompiledScene::read($file, 'rig', []));
    }

    public function testAnUnreadableRecordFallsBackRatherThanFailing(): void
    {
        foreach (['{not json', '[]', '{"plan_version":1,"scene_id":"rig","placements":[{"device":"x"}],"warnings":[]}'] as $contents) {
            $file = $this->dir.'/broken.json';
            file_put_contents($file, $contents);
            touch($file, 200);

            self::assertNull(CompiledScene::read($file, 'rig', []), $contents);
        }
    }

    /**
     * The shared inputs are wide on purpose, so a change anywhere the solver could reach makes the record stale.
     */
    public function testTheSharedInputsCoverTheSolverItsDataAndItsDependencies(): void
    {
        $inputs = CompiledScene::sharedInputs(dirname(__DIR__, 2));
        $project = dirname(__DIR__, 2).'/';

        self::assertContains($project.'src/Scene/SceneCompiler.php', $inputs);
        self::assertContains($project.'src/Render/RenderPlacement.php', $inputs);
        self::assertContains($project.'composer.lock', $inputs);
        self::assertNotEmpty(array_filter($inputs, static fn (string $file): bool => str_starts_with($file, $project.'specs/')));
    }

    public function testWritingCreatesTheDirectoryAndLeavesNoTemporaryFile(): void
    {
        $file = CompiledScene::fileIn($this->dir.'/plans/generated/gmss', 'rig');
        CompiledScene::write($file, 'rig', [$this->placed('a', [0.0, 0.0, 0.0])], []);

        self::assertFileExists($file);
        self::assertFileDoesNotExist($file.'.tmp');
    }

    /**
     * @param list<PlacedDevice> $placed
     * @param list<Violation> $warnings
     */
    private function record(array $placed, array $warnings, int $mtime): string
    {
        $file = CompiledScene::fileIn($this->dir, 'rig');
        CompiledScene::write($file, 'rig', $placed, $warnings);
        touch($file, $mtime);

        return $file;
    }

    /**
     * @param array{float, float, float} $position
     */
    private function placed(string $id, array $position): PlacedDevice
    {
        return new PlacedDevice($id, SpecFactory::spec(), $position, new Orientation(-5.0, 0.0, 11.0));
    }

    private function touched(string $name, int $mtime): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, 'x');
        touch($path, $mtime);

        return $path;
    }
}
