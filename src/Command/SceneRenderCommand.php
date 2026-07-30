<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use App\Render\CameraPreset;
use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\SceneSpec;
use App\Spec\InvalidSpecException;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders an assembled scene to a PNG, with camera and lighting presets.
 *
 * The point is that a preview costs one command and no Blender knowledge — the framing is derived
 * from the scene's own bounding box, so nobody has to place a camera to see whether a setup looks
 * right.
 */
final class SceneRenderCommand extends BaseCommand
{
    protected function configure(): void
    {
        $cameras = implode(', ', array_column(CameraPreset::cases(), 'value'));
        $lightings = implode(', ', array_column(LightingPreset::cases(), 'value'));

        $this
            ->setName('scene:render')
            ->setDescription('Render a scene to build/renders/<id>-<camera>.png')
            ->addArgument('scene', InputArgument::OPTIONAL, 'Scene id or path; omit to render every scene')
            ->addOption('camera', 'c', InputOption::VALUE_REQUIRED, "Camera preset ({$cameras})", CameraPreset::ThreeQuarter->value)
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, "Lighting preset ({$lightings})", LightingPreset::Studio->value)
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Cycles samples', (string)RenderPlan::DEFAULT_SAMPLES)
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, 'WIDTHxHEIGHT', implode('x', RenderPlan::DEFAULT_RESOLUTION))
            ->addOption('no-ground', null, InputOption::VALUE_NONE, 'Leave out the ground plane')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output PNG path (single scene only)')
            ->addOption('presets', null, InputOption::VALUE_NONE, 'List the presets and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if ($input->getOption('presets')) {
            $this->listPresets();

            return self::SUCCESS;
        }

        $settings = $this->settings($input);
        if ($settings === null) {
            return self::FAILURE;
        }

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ($errors !== []) {
            $this->io->error('Some specs could not be read — fix them first (see `specs:validate`)');

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
        if (count($scenes) > 1 && $input->getOption('out') !== null) {
            $this->io->error('--out only makes sense when rendering a single scene');

            return self::FAILURE;
        }

        $devicesById = [];
        foreach ($specs as $spec) {
            $devicesById[$spec->id] = $spec;
        }

        $builder = new ModelBuilder($this->projectDir(), new BlenderRunner($this->runner));
        $exit = self::SUCCESS;

        foreach ($scenes as $scene) {
            ['placed' => $placed, 'violations' => $violations] = (new SceneCompiler($devicesById))->compile($scene);
            if ($violations !== []) {
                $this->reportViolations($violations);
                $exit = self::FAILURE;
                continue;
            }
            if ($placed === []) {
                $this->io->warning("Scene '{$scene->id}' has no placements");
                continue;
            }

            $sceneBlend = $builder->buildDir().'/scenes/'.$scene->id.'.blend';
            if (!is_file($sceneBlend)) {
                $this->io->error(sprintf(
                    "No assembled scene at %s\nRun `bin/console scene:build %s` first.",
                    $this->relative($sceneBlend),
                    $scene->id,
                ));
                $exit = self::FAILURE;
                continue;
            }

            $target = $input->getOption('out')
                ?? sprintf('%s/renders/%s-%s.png', $builder->buildDir(), $scene->id, $settings['camera']->value);

            $plan = RenderPlan::forScene(
                $placed,
                $settings['camera'],
                $settings['lighting'],
                $settings['samples'],
                $settings['resolution'],
                !$settings['noGround'],
            ) + [
                'scene_id' => $scene->id,
                'scene_blend' => $sceneBlend,
                'output' => $target,
            ];

            $this->io->text(sprintf(
                '<info>→</info> %s — %s camera, %s lighting, %d samples, %dx%d',
                $scene->id,
                $settings['camera']->value,
                $settings['lighting']->value,
                $settings['samples'],
                $settings['resolution'][0],
                $settings['resolution'][1],
            ));

            try {
                $this->renderPlan($plan, $target, $output);
            } catch (RuntimeException $e) {
                $this->io->error($e->getMessage());
                $exit = self::FAILURE;
                continue;
            }

            $this->io->success('Wrote '.$this->relative($target));
        }

        return $exit;
    }

    protected function scenesDir(): string
    {
        return $this->projectDir().'/scenes';
    }

    /**
     * @return array{camera: CameraPreset, lighting: LightingPreset, samples: int, resolution: array{int, int}, noGround: bool}|null
     */
    private function settings(InputInterface $input): ?array
    {
        $camera = CameraPreset::tryFrom((string)$input->getOption('camera'));
        if ($camera === null) {
            $this->io->error(sprintf(
                "Unknown camera preset '%s'. Available: %s",
                $input->getOption('camera'),
                implode(', ', array_column(CameraPreset::cases(), 'value')),
            ));

            return null;
        }

        $lighting = LightingPreset::tryFrom((string)$input->getOption('lighting'));
        if ($lighting === null) {
            $this->io->error(sprintf(
                "Unknown lighting preset '%s'. Available: %s",
                $input->getOption('lighting'),
                implode(', ', array_column(LightingPreset::cases(), 'value')),
            ));

            return null;
        }

        $samples = (int)$input->getOption('samples');
        if ($samples < 1) {
            $this->io->error('--samples must be at least 1');

            return null;
        }

        $resolution = $this->resolution((string)$input->getOption('resolution'));
        if ($resolution === null) {
            return null;
        }

        return [
            'camera' => $camera,
            'lighting' => $lighting,
            'samples' => $samples,
            'resolution' => $resolution,
            'noGround' => (bool)$input->getOption('no-ground'),
        ];
    }

    /**
     * @return array{int, int}|null
     */
    private function resolution(string $raw): ?array
    {
        if (preg_match('/^(\d+)\s*[x×]\s*(\d+)$/i', trim($raw), $matches) !== 1) {
            $this->io->error("--resolution must look like 1600x900, got '{$raw}'");

            return null;
        }

        $width = (int)$matches[1];
        $height = (int)$matches[2];
        if ($width < 16 || $height < 16) {
            $this->io->error('--resolution must be at least 16x16');

            return null;
        }

        return [$width, $height];
    }

    /**
     * @return list<SceneSpec>|null
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

    private function listPresets(): void
    {
        $this->io->text('<comment>Cameras</comment> (--camera)');
        foreach (CameraPreset::cases() as $preset) {
            $this->io->text(sprintf('  %-14s %s', $preset->value, $preset->describe()));
        }
        $this->io->newLine();
        $this->io->text('<comment>Lighting</comment> (--lighting)');
        foreach (LightingPreset::cases() as $preset) {
            $this->io->text(sprintf('  %-14s %s', $preset->value, $preset->describe()));
        }
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function renderPlan(array $plan, string $target, OutputInterface $output): void
    {
        $planFile = sprintf(
            '%s/build/plans/_render-%s-%s.json',
            $this->projectDir(),
            $plan['scene_id'],
            $plan['camera']['preset'],
        );

        $dir = dirname($planFile);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory {$dir}");
        }

        try {
            $json = json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('Cannot encode the render plan: '.$e->getMessage(), 0, $e);
        }
        if (@file_put_contents($planFile, $json."\n") === false) {
            throw new RuntimeException("Cannot write the render plan to {$planFile}");
        }

        (new BlenderRunner($this->runner))->run(
            $this->projectDir().'/blender/render_scene.py',
            $planFile,
            $this->blenderOutputSink($output),
        );

        if (!is_file($target)) {
            throw new RuntimeException("Blender did not write the render at {$target}");
        }
    }
}
