<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SceneLoader;
use PHPUnit\Framework\TestCase;

/**
 * A folder names every scene below it, so an event's inventory can be built and rendered without naming 422 files.
 */
final class SceneLoaderFolderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/sdwa5-scenes-'.bin2hex(random_bytes(4));
        foreach (['a.yaml', 'generated/gmss/b.yaml', 'generated/gmss/deeper/c.yml', 'generated/sepp/d.yaml'] as $file) {
            @mkdir(dirname($this->root.'/'.$file), 0o777, true);
            file_put_contents($this->root.'/'.$file, "id: x\n");
        }
        @mkdir($this->root.'/generated/empty', 0o777, true);
        file_put_contents($this->root.'/generated/gmss/notes.txt', 'not a scene');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
    }

    public function testAFolderUnderScenesSelectsEverySceneBelowIt(): void
    {
        $files = (new SceneLoader($this->root))->filesUnder('generated/gmss');

        self::assertSame(
            [$this->root.'/generated/gmss/b.yaml', $this->root.'/generated/gmss/deeper/c.yml'],
            $files,
        );
    }

    public function testAPathToTheFolderSelectsTheSameScenes(): void
    {
        $loader = new SceneLoader($this->root);

        self::assertSame($loader->filesUnder('generated/gmss'), $loader->filesUnder($this->root.'/generated/gmss/'));
    }

    public function testAFolderWithoutScenesIsEmptyAndAFileOrMissingFolderIsNone(): void
    {
        $loader = new SceneLoader($this->root);

        self::assertSame([], $loader->filesUnder('generated/empty'));
        self::assertNull($loader->filesUnder('generated/nope'));
        self::assertNull($loader->filesUnder('a.yaml'));
    }
}
