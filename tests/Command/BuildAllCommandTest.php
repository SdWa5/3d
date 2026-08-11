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
     * The whole sweep with nothing asked for: four lighting presets times two aim modes, each into a folder
     * named after what makes it different. These were opt-in flags that every invocation in the repository
     * passed, so the useful behaviour was the one nobody got by default.
     */
    public function testEveryVariantIsRenderedWithNoFlagsAtAll(): void
    {
        $display = $this->dryRun(['--dry-run' => true]);

        self::assertStringContainsString('8 render passes', $display);
        self::assertStringContainsString('build/renders/studio-aim', $display);
        self::assertStringContainsString('build/renders/flat', $display);
    }

    /**
     * Naming a lighting narrows the sweep to it — which is the whole of the opt-out. There is no
     * `--no-lighting-variants`, because "just this lighting" is what stating a lighting already means.
     */
    public function testNamingALightingLeavesOnlyItsTwoAimModes(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--lighting' => 'studio']);

        self::assertStringContainsString('2 render passes', $display);
        self::assertStringContainsString('--aim-lines=none', $display);
        self::assertStringContainsString('--aim-lines=tops', $display);
        self::assertStringNotContainsString('build/renders/flat', $display);
    }

    public function testNamingAnAimModeLeavesOneFolderPerLightingPreset(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--aim-lines' => 'none']);

        self::assertStringContainsString('4 render passes', $display);
        foreach (['studio', 'stage', 'daylight', 'flat'] as $preset) {
            self::assertStringContainsString('build/renders/'.$preset, $display);
        }
    }

    /**
     * Narrowed to one pass, the output goes exactly where `scene:render` always put it — so the single-variant
     * case does not quietly move anybody's renders into a subfolder.
     */
    public function testNarrowingToASinglePassWritesToThePlainFolder(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--lighting' => 'studio', '--aim-lines' => 'none']);

        self::assertStringContainsString('1 render pass', $display);
        self::assertStringNotContainsString('--out-dir', $display);
    }

    /**
     * A dry run runs no stage, so nothing downstream would catch the typo — and by the time the real sweep
     * reached `scene:render` it would have spent every earlier stage first.
     */
    public function testAnUnknownAimModeIsRefusedBeforeAnythingRuns(): void
    {
        $display = $this->invoke(['--dry-run' => true, '--aim-lines' => 'top'], Command::FAILURE);

        self::assertStringContainsString("Unknown --aim-lines value 'top'", $display);
        self::assertStringNotContainsString('render pass', $display);
    }

    /**
     * A quality level reaches every pass in the sweep, since choosing preview quality is a statement about the
     * run rather than about one variant of it.
     */
    public function testAQualityLevelIsForwardedToEveryPass(): void
    {
        $display = $this->dryRun(['--dry-run' => true, '--quick-preview' => true]);

        self::assertSame(8, substr_count($display, '--quick-preview'));
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
        return $this->invoke($arguments, Command::SUCCESS);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function invoke(array $arguments, int $expected): string
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

        self::assertSame($expected, $exit, $tester->getDisplay());

        return $tester->getDisplay();
    }
}
