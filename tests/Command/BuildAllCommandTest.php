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

        // `scene:stack` first: the generated scenes are input to everything after them, and they were the one
        // thing in the pipeline that did not follow the specs — re-measuring the GMSS cabinets left eleven of them
        // describing rows that no longer existed and nothing in a full build noticed.
        $order = ['scene:stack', 'specs:validate', 'models:build', 'library:build', 'scene:build', 'scene:render'];
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
     * The regeneration stage says how many files a real run would rewrite.
     *
     * Worth naming in the dry run specifically because it is the only stage that writes outside `build/`: everything
     * else a full build produces is disposable, and these are tracked files somebody may be part-way through
     * reviewing. A dry run is where you find that out.
     */
    public function testTheDryRunSaysHowManyGeneratedScenesWouldBeRewritten(): void
    {
        $count = count(glob(dirname(__DIR__, 2).'/scenes/generated/*.yaml') ?: []);
        self::assertGreaterThan(0, $count, 'there should be generated scenes to regenerate');

        self::assertStringContainsString(
            sprintf("replaying %d generated scenes' own commands", $count),
            $this->dryRun(['--dry-run' => true]),
        );
    }

    /**
     * A derived file whose scene no longer exists is pruned; everything else is left alone.
     *
     * The scene ids changed shape wholesale in 0.68.0 — `stacked-center` became `stacked-sdwa5-2-center` — and nothing
     * pruned, because every stage only added. That left 38 orphaned `.blend` files and 11 orphaned renders, and a
     * reader could not tell which pictures belonged to the current rigs.
     *
     * Written as a unit test on the naming rather than by running the whole pipeline, because the pipeline needs
     * Blender. What matters is that a camera suffix is stripped from a known list and not by cutting at the last dash:
     * scene ids contain dashes, so `stacked-sdwa5-2-center-three-quarter.png` would otherwise be read as belonging to
     * a scene called `stacked-sdwa5-2-center-three` and pruned as an orphan of a rig that does exist.
     */
    public function testADerivedFileIsMatchedToItsSceneById(): void
    {
        $method = new \ReflectionMethod(BuildAllCommand::class, 'sceneIdOf');

        foreach ([
            'build/scenes/generated/stacked-sdwa5-2-center.blend' => 'stacked-sdwa5-2-center',
            'build/plans/generated/_scene-stacked-sdwa5-2-center.json' => 'stacked-sdwa5-2-center',
            'build/renders/generated/stacked-sdwa5-2-center-three-quarter.png' => 'stacked-sdwa5-2-center',
            'build/renders/studio/generated/stacked-gmss-1-free-center-front.png' => 'stacked-gmss-1-free-center',
        ] as $file => $expected) {
            self::assertSame($expected, $method->invoke(null, $file), $file);
        }

        // A picture whose name carries no camera says nothing about which scene it belongs to, and guessing would risk
        // pruning a file that is not an orphan.
        self::assertNull($method->invoke(null, 'build/renders/generated/mystery.png'));
    }

    /**
     * Every generated scene carries a runnable `scene:stack` line, because that line is what the stage replays.
     *
     * There is deliberately no list of commands in the code — a second copy would drift from the files — so this is
     * the invariant that keeps the stage able to do its job: a generated scene with no recorded command is skipped,
     * and a skipped scene silently stops following the specs.
     */
    public function testEveryGeneratedSceneRecordsTheCommandThatMadeIt(): void
    {
        $files = glob(dirname(__DIR__, 2).'/scenes/generated/*.yaml') ?: [];
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $yaml = (string)file_get_contents($file);
            self::assertMatchesRegularExpression(
                '/^#\s{3}bin\/console scene:stack .+$/m',
                $yaml,
                basename($file).' records no command, so `build:all` cannot regenerate it',
            );
            // The id has to be in there too, or a replay writes `stacked-<mode>` over the top of another scene.
            self::assertMatchesRegularExpression(
                '/(--id=|bin\/console scene:stack(?!.*--id=))/',
                $yaml,
                basename($file).' records no --id',
            );
        }
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
