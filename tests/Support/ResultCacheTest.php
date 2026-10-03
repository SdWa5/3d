<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

final class ResultCacheTest extends TestCase
{
    private string $project;

    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/sdwa5-result-cache-'.bin2hex(random_bytes(4));
        mkdir($this->project.'/src/Scene', 0o777, true);
        file_put_contents($this->project.'/src/Scene/Solver.php', '<?php // one');
        file_put_contents($this->project.'/composer.lock', '{}');

        foreach (['CI', 'SDWA5_TEST_CACHE'] as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv(false === $value ? $name : $name.'='.$value);
        }
        exec('rm -rf '.escapeshellarg($this->project));
    }

    public function testARecordedPassIsFoundAgainAndNothingElseIs(): void
    {
        $cache = $this->open();

        self::assertFalse($cache->passed('scene-a'));
        $cache->recordPass('scene-a');

        self::assertTrue($this->open()->passed('scene-a'), 'a second open of the same tree lost the entry');
        self::assertFalse($this->open()->passed('scene-b'));
    }

    /**
     * **Any change under an input misses every entry**, which is the whole safety argument: the key does not guess
     * which file a check reads.
     */
    public function testAChangedInputFileMissesTheEntry(): void
    {
        $this->open()->recordPass('scene-a');

        file_put_contents($this->project.'/src/Scene/Solver.php', '<?php // two');

        self::assertFalse($this->open()->passed('scene-a'));
    }

    /** A file moved to another folder is a different tree, even with the same bytes. */
    public function testThePathOfAnInputFileIsPartOfTheFingerprint(): void
    {
        $before = ResultCache::fingerprint($this->project, ['src']);

        mkdir($this->project.'/src/Spec');
        rename($this->project.'/src/Scene/Solver.php', $this->project.'/src/Spec/Solver.php');

        self::assertNotSame($before, ResultCache::fingerprint($this->project, ['src']));
    }

    public function testAnInputThatDoesNotExistIsIgnoredRatherThanFatal(): void
    {
        self::assertSame(
            ResultCache::fingerprint($this->project, ['src']),
            ResultCache::fingerprint($this->project, ['src', 'events']),
        );
    }

    /** CI must run cold, and so must a local run that asks for it. */
    public function testCiAndAnExplicitZeroSwitchItOff(): void
    {
        putenv('CI=true');
        self::assertNull(ResultCache::open($this->project, 'checks', ['src']));

        putenv('CI');
        putenv('SDWA5_TEST_CACHE=0');
        self::assertNull(ResultCache::open($this->project, 'checks', ['src']));
    }

    /** The cache holds one tree's answers, so a run after a change deletes what the old tree recorded. */
    public function testOpeningDeletesTheEntriesOfEveryOtherFingerprint(): void
    {
        $this->open()->recordPass('scene-a');
        $old = glob($this->project.'/build/test-cache/checks/*', GLOB_ONLYDIR) ?: [];
        self::assertCount(1, $old);

        file_put_contents($this->project.'/src/Scene/Solver.php', '<?php // two');
        $this->open();

        self::assertDirectoryDoesNotExist($old[0]);
    }

    private function open(): ResultCache
    {
        $cache = ResultCache::open($this->project, 'checks', ['src', 'composer.lock']);
        self::assertNotNull($cache);

        return $cache;
    }
}
