<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\BlenderRunner;
use App\Tests\Support\FakeProcessRunner;
use PHPUnit\Framework\TestCase;

final class BlenderRunnerTest extends TestCase
{
    public function testBuildsABackgroundCommandWithTheScriptArgumentsAfterTheSeparator(): void
    {
        $runner = new BlenderRunner(new FakeProcessRunner());

        $cmd = $runner->buildCommand('/usr/bin/blender', '/p/blender/build_model.py', '/p/build/plans/top-a.json');

        self::assertSame(
            "'/usr/bin/blender' --background --factory-startup --python '/p/blender/build_model.py'"
            ." -- --plan '/p/build/plans/top-a.json'",
            $cmd,
        );
    }

    public function testResolvesTheBinaryOnceAndReusesIt(): void
    {
        $fake = new FakeProcessRunner();
        $fake->on('which ', 0, "/usr/bin/blender\n");
        $fake->on('--background', 0, 'ok');
        $runner = new BlenderRunner($fake);

        $runner->run('/p/script.py', '/p/plan.json');
        $runner->run('/p/script.py', '/p/plan.json');

        $whichCalls = array_filter($fake->commands, static fn (string $cmd): bool => str_contains($cmd, 'which '));
        self::assertCount(1, $whichCalls, 'the Blender binary should be looked up only once');
        self::assertSame('/usr/bin/blender', $runner->binary());
    }

    public function testThrowsWhenBlenderIsMissing(): void
    {
        $fake = new FakeProcessRunner();
        $fake->on('which ', 1, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Required binary not found: blender/');
        (new BlenderRunner($fake))->run('/p/script.py', '/p/plan.json');
    }

    public function testFailureMessageEndsWithBlendersLastOutputLines(): void
    {
        $fake = new FakeProcessRunner();
        $fake->on('which ', 0, "/usr/bin/blender\n");
        $fake->on('--background', 1, "noise\nTraceback (most recent call last)\nValueError: nope\n");

        try {
            (new BlenderRunner($fake))->run('/p/blender/build_model.py', '/p/plan.json');
            self::fail('expected a RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Blender failed (exit 1) running build_model.py', $e->getMessage());
            self::assertStringContainsString('ValueError: nope', $e->getMessage());
        }
    }

    public function testStreamsOutputToTheSink(): void
    {
        $fake = new FakeProcessRunner();
        $fake->on('which ', 0, "/usr/bin/blender\n");
        $fake->on('--background', 0, "sdwa5-3d: built top-a\n");

        $seen = '';
        (new BlenderRunner($fake))->run('/p/script.py', '/p/plan.json', function (string $chunk) use (&$seen): void {
            $seen .= $chunk;
        });

        self::assertStringContainsString('built top-a', $seen);
    }
}
