<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use App\Build\Staleness;
use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\SceneReport;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use JsonException;
use App\Spec\Violation;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Assembles a scene file into a `.blend` full of correctly placed cabinets.
 *
 * This is what turns "try a different setup" from an afternoon of dragging boxes into an edit and a
 * rebuild — and what makes a setup that worked reviewable next year instead of remembered.
 */
final class SceneBuildCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('scene:build')
            ->setDescription('Assemble a scene from scenes/<name>.yaml into build/scenes/<id>.blend')
            ->addArgument('scene', InputArgument::OPTIONAL, 'Scene id or path; omit to build every scene')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report the setup without running Blender')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Reassemble even when the .blend looks up to date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ($errors !== []) {
            $this->io->error('Some specs could not be read — fix them first (see `specs:validate`)');

            return self::FAILURE;
        }

        $violations = $this->validator()->validate($specs);
        $this->reportViolations($violations);
        $violations = Violation::errorsIn($violations);
        if ($violations !== []) {
            $this->io->error('Specs are invalid — refusing to build a scene from them');

            return self::FAILURE;
        }

        $scenes = $this->selectScenes($input->getArgument('scene'));
        if ($scenes === null) {
            return self::FAILURE;
        }
        if ($scenes === []) {
            $this->io->warning('No scenes found in '.$this->relative($this->scenesDir()));

            return self::SUCCESS;
        }

        $devicesById = [];
        foreach ($specs as $spec) {
            $devicesById[$spec->id] = $spec;
        }

        $builder = new ModelBuilder($this->projectDir(), new BlenderRunner($this->runner));
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');
        $exit = self::SUCCESS;

        foreach ($scenes as $scene) {
            $this->io->section($scene->name.' ('.$scene->id.')');

            ['placed' => $placed, 'violations' => $sceneViolations] = (new SceneCompiler($devicesById))->compile($scene);
            $this->reportViolations($sceneViolations);
            // Only errors stop a build, exactly as the spec check above already does. A scene can now carry
            // *warnings* — a stack whose mixed row is stepped, a tier standing slightly proud of the one
            // below it — and those are things to know about a rig that is otherwise perfectly buildable.
            // Treating them as fatal swallowed the whole build report, which is where the numbers are.
            if (Violation::errorsIn($sceneViolations) !== []) {
                $exit = self::FAILURE;
                continue;
            }
            if ($placed === []) {
                $this->io->warning('Scene has no placements');
                continue;
            }

            $this->report($placed);

            if ($dryRun) {
                continue;
            }
            if (!$this->modelsReady($specs, $builder, $placed)) {
                $exit = self::FAILURE;
                continue;
            }
            if (!$force && !$this->needsAssembling($scene, $placed, $builder)) {
                $this->io->text(sprintf('<comment>up to date</comment> %s', $this->relative($this->blendFor($scene, $builder))));
                continue;
            }

            try {
                $target = $this->assemble($scene, $placed, $builder, $output);
            } catch (RuntimeException $e) {
                $this->io->error($e->getMessage());
                $exit = self::FAILURE;
                continue;
            }

            $this->io->success('Wrote '.$this->relative($target));
        }

        return $exit;
    }

    /**
     * Whether this scene's `.blend` is older than anything it is built from.
     *
     * The inputs are the scene file, the assembly script, and **the model of every cabinet the scene places** —
     * that last one is what makes the chain work: measure a cabinet, its model rebuilds, and every scene
     * standing on it reassembles without anybody having to remember which.
     *
     * @param list<PlacedDevice> $placed
     */
    private function needsAssembling(\App\Scene\SceneSpec $scene, array $placed, ModelBuilder $builder): bool
    {
        $inputs = Staleness::blenderInputs($this->projectDir(), 'blender/build_scene.py');
        $inputs[] = $scene->sourcePath;

        $seen = [];
        foreach ($placed as $entry) {
            $seen[$entry->device->id] = $builder->blendPath($entry->device);
        }

        return Staleness::outOfDate([$this->blendFor($scene, $builder)], [...$inputs, ...array_values($seen)]);
    }

    private function blendFor(\App\Scene\SceneSpec $scene, ModelBuilder $builder): string
    {
        return $this->derivedDir($builder->buildDir().'/scenes', $scene).'/'.$scene->id.'.blend';
    }

    /**
     * @return list<\App\Scene\SceneSpec>|null null when a named scene does not exist
     */
    private function selectScenes(?string $nameOrPath): ?array
    {
        $loader = new SceneLoader($this->scenesDir());

        try {
            if ($nameOrPath === null) {
                return array_map([$loader, 'load'], $loader->files());
            }

            ['scene' => $scene, 'known' => $known] = $loader->find($nameOrPath);
            if ($scene === null) {
                $this->io->error(sprintf(
                    "Unknown scene '%s'%s",
                    $nameOrPath,
                    $known === [] ? '' : '. Available: '.implode(', ', $known),
                ));

                return null;
            }

            return [$scene];
        } catch (InvalidSpecException $e) {
            $this->io->error('Cannot read scene: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function report(array $placed): void
    {
        $report = new SceneReport();
        $this->io->text($report->lines($placed));

        $summary = $report->summarise($placed);

        if ($summary['over_inventory'] !== []) {
            $parts = [];
            foreach ($summary['over_inventory'] as $id => $counts) {
                $parts[] = sprintf('%s: uses %d, we own %d', $id, $counts['used'], $counts['owned']);
            }
            // Cheap to fix here, expensive to discover on site.
            $this->io->warning("Scene uses more cabinets than the inventory has:\n".implode("\n", $parts));
        }

        $borrowed = array_diff(array_keys($summary['by_owner']), [DeviceSpec::DEFAULT_OWNER]);
        if ($borrowed !== []) {
            $this->io->note('Depends on borrowed gear from: '.implode(', ', $borrowed));
        }
        if ($summary['unmeasured_devices'] !== []) {
            $this->io->note(
                'Positions rely on un-measured cabinets: '.implode(', ', $summary['unmeasured_devices']),
            );
        }
    }

    /**
     * A scene is only as good as the models under it, so refuse rather than assemble a stale one.
     *
     * @param list<DeviceSpec> $specs
     * @param list<PlacedDevice> $placed
     */
    private function modelsReady(array $specs, ModelBuilder $builder, array $placed): bool
    {
        $needed = [];
        foreach ($placed as $entry) {
            $needed[$entry->device->id] = $entry->device;
        }

        $stale = [];
        foreach ($needed as $id => $device) {
            if ($builder->isStale($device)) {
                $stale[] = $id;
            }
        }
        if ($stale === []) {
            return true;
        }

        $this->io->error(sprintf(
            "These models are missing or out of date: %s\nRun `bin/console models:build` first.",
            implode(', ', $stale),
        ));

        return false;
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function assemble(
        \App\Scene\SceneSpec $scene,
        array $placed,
        ModelBuilder $builder,
        OutputInterface $output,
    ): string {
        // Both the assembled scene and its plan follow the source file into `generated/`, so a regenerated rig
        // never overwrites the build output of a hand-written scene that happens to share its id.
        $target = $this->derivedDir($builder->buildDir().'/scenes', $scene).'/'.$scene->id.'.blend';
        $planFile = $this->derivedDir($builder->buildDir().'/plans', $scene).'/_scene-'.$scene->id.'.json';

        $entries = [];
        foreach ($placed as $entry) {
            $entries[] = $entry->toArray() + ['blend' => $builder->blendPath($entry->device)];
        }

        $dir = dirname($planFile);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory {$dir}");
        }

        try {
            $json = json_encode(
                ['plan_version' => 1, 'scene_id' => $scene->id, 'output' => $target, 'placements' => $entries],
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Cannot encode the scene plan: '.$e->getMessage(), 0, $e);
        }
        if (@file_put_contents($planFile, $json."\n") === false) {
            throw new RuntimeException("Cannot write the scene plan to {$planFile}");
        }

        (new BlenderRunner($this->runner))->run(
            $this->projectDir().'/blender/build_scene.py',
            $planFile,
            $this->blenderOutputSink($output),
        );

        if (!is_file($target)) {
            throw new RuntimeException("Blender did not write the scene at {$target}");
        }

        return $target;
    }
}
