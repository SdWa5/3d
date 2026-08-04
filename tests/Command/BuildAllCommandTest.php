<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BuildAllCommand;
use App\Command\CatalogCommand;
use App\Command\LibraryBuildCommand;
use App\Command\ModelsBuildCommand;
use App\Command\SceneBuildCommand;
use App\Command\SceneRenderCommand;
use App\Command\SpecsValidateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\TestCase;

/**
 * The order of the pipeline and the shape of a variant sweep, checked with `--dry-run` — which is the only
 * way to check them at all here, since every stage but the first needs Blender and CI has none.
 */
final class BuildAllCommandTest extends TestCase
{
    public function testTheStagesRunInDependencyOrder(): void
    {
        $display = $this->dryRun(['--dry-run' => true]);

        $order = ['specs:validate', 'models:build', 'library:build', 'scene:build', 'scene:render'];
        $positions = array_map(static fn (string $stage): int|false => strpos($display, $stage), $order);

        foreach ($positions as $index => $position) {
            self::assertNotFalse($position, "{$order[$index]} is missing");
            if ($index > 0) {
                self::assertGreaterThan(
                    $positions[$index - 1],
                    $position,
                    "{$order[$index]} should come after {$order[$index - 1]}",
                );
            }
        }
    }

    public function testADryRunRunsNothing(): void
    {
        self::assertStringContainsString('Nothing was run', $this->dryRun(['--dry-run' => true]));
    }

    /**
     * With no variant asked for the output tree is exactly where `scene:render` always wrote, so the plain
     * case does not quietly move anybody's renders into a subfolder.
     */
    public function testThePlainCaseRendersWhereItAlwaysDidWithNoOutDir(): void
    {
        $display = $this->dryRun(['--dry-run' => true]);

        self::assertStringContainsString('1 render pass', $display);
        self::assertStringNotContainsString('--out-dir', $display);
    }

    public function testAimLineVariantsRenderTwiceIntoSeparateFolders(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--aim-line-variants' => true]);

        self::assertStringContainsString('2 render passes', $display);
        self::assertStringContainsString('--aim-lines=none', $display);
        self::assertStringContainsString('--aim-lines=tops', $display);
        self::assertStringContainsString('build/renders/default-aim', $display);
    }

    public function testLightingVariantsRenderOneFolderPerPreset(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--lighting-variants' => true]);

        self::assertStringContainsString('4 render passes', $display);
        foreach (['studio', 'stage', 'daylight', 'flat'] as $preset) {
            self::assertStringContainsString('build/renders/'.$preset, $display);
        }
    }

    public function testBothKindsOfVariantMultiply(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--lighting-variants' => true, '--aim-line-variants' => true]);

        self::assertStringContainsString('8 render passes', $display);
        self::assertStringContainsString('build/renders/studio-aim', $display);
        self::assertStringContainsString('build/renders/flat', $display);
    }

    public function testSkipRenderLeavesTheRenderStageOut(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--skip-render' => true]);

        self::assertStringContainsString('0 render passes', $display);
        self::assertStringNotContainsString('scene:render', $display);
    }

    public function testForceIsForwardedToTheModelBuild(): void
    {
        self::assertStringContainsString('--force', $this->dryRun(['--dry-run' => true, '--force' => true]));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function dryRun(array $arguments): string
    {
        $application = new Application('sdwa5-3d', 'test');
        $application->addCommands([
            new SpecsValidateCommand(),
            new ModelsBuildCommand(),
            new LibraryBuildCommand(),
            new SceneBuildCommand(),
            new SceneRenderCommand(),
            new CatalogCommand(),
            new BuildAllCommand(),
        ]);

        $tester = new CommandTester($application->find('build:all'));
        $exit = $tester->execute($arguments);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());

        return $tester->getDisplay();
    }
}
