<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Render\CameraPreset;
use App\Render\FlyThroughPlan;
use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\SceneCompiler;
use App\Spec\Violation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Renders a video along the outer stacks' far focus points, looking at the rig throughout. */
final class SceneFlyThroughCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('scene:fly-through')
            ->setDescription("Render a camera move between the outer stacks' far focus points")
            ->addArgument('scene', InputArgument::REQUIRED, 'One scene id or path')
            ->addOption('lens', null, InputOption::VALUE_REQUIRED, 'Camera focal length in mm', '24')
            ->addOption('camera-aim', null, InputOption::VALUE_REQUIRED, 'rig-centre or perpendicular', 'rig-centre')
            ->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Video duration in seconds', '6')
            ->addOption('fps', null, InputOption::VALUE_REQUIRED, 'Frames per second', '24')
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, 'Lighting preset', 'studio')
            ->addOption('quick-preview', null, InputOption::VALUE_NONE, 'Render at 960x540 and 16 samples')
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, 'Cycles samples')
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, 'WIDTHxHEIGHT')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output MP4 path')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write the plan without building or rendering');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $lighting = LightingPreset::tryFrom((string) $input->getOption('lighting'));
            $seconds = filter_var($input->getOption('seconds'), FILTER_VALIDATE_FLOAT);
            $fps = filter_var($input->getOption('fps'), FILTER_VALIDATE_INT);
            $lens = filter_var($input->getOption('lens'), FILTER_VALIDATE_FLOAT);
            $preview = (bool) $input->getOption('quick-preview');
            $samples = filter_var($input->getOption('samples') ?? ($preview ? 16 : 128), FILTER_VALIDATE_INT);
            $size = (string) ($input->getOption('resolution') ?? ($preview ? '960x540' : '1920x1080'));
            if (false === $lens || !is_finite($lens) || $lens < 1.0 || $lens > 500.0
                || null === $lighting || false === $seconds || false === $fps || false === $samples || $samples < 1
                || 1 !== preg_match('/^([1-9][0-9]*)x([1-9][0-9]*)$/', $size, $match)) {
                throw new \InvalidArgumentException('Invalid lens, lighting, duration, fps, samples or resolution');
            }
            $scenes = $this->selectScenes((string) $input->getArgument('scene'));
            if (null === $scenes || 1 !== count($scenes)) {
                throw new \InvalidArgumentException('Select exactly one scene for the fly-through');
            }
            $scene = $scenes[0];
            $devices = [];
            ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
            if ([] !== $errors) {
                return self::FAILURE;
            }
            foreach ($specs as $spec) {
                $devices[$spec->id] = $spec;
            }
            $compiler = new SceneCompiler($devices);
            ['placed' => $placed, 'violations' => $violations] = $compiler->compile($scene);
            if ([] !== Violation::errorsIn($violations) || [] === $placed) {
                $this->reportViolations($violations);

                return self::FAILURE;
            }
            $plan = RenderPlan::forScene(
                $placed,
                CameraPreset::Front,
                $lighting,
                $samples,
                [(int) $match[1], (int) $match[2]],
            );
            $route = FlyThroughPlan::between(
                $compiler->stackFocusPoints($scene),
                $plan['camera']['target'],
                $seconds,
                $fps,
                (string) $input->getOption('camera-aim'),
            );
            $plan['camera']['location'] = $route['start'];
            if ('perpendicular' === $route['camera_aim']) {
                $plan['camera']['target'] = [$route['start'][0], $route['start'][1] + 1.0, $route['start'][2]];
            }
            $plan['camera']['lens_mm'] = $lens;
            $plan['camera']['preset'] = 'fly-through';
            $target = $input->getOption('out') ?? $this->derivedDir($this->projectDir().'/build/renders', $scene)
                .'/'.$scene->id.'-fly-through'
                .('perpendicular' === $route['camera_aim'] ? '-perpendicular' : '').'.mp4';
            if ('mp4' !== strtolower(pathinfo((string) $target, PATHINFO_EXTENSION))) {
                throw new \InvalidArgumentException('The output must end in .mp4');
            }
            $plan += [
                'scene_id' => $scene->id,
                'scene_blend' => $this->derivedDir($this->projectDir().'/build/scenes', $scene).'/'.$scene->id.'.blend',
                'output' => (string) $target,
                'fly_through' => $route,
            ];
            $planFile = $this->derivedDir($this->projectDir().'/build/plans', $scene)
                .'/'.$scene->id.'-fly-through.json';
            if (!is_dir(dirname($planFile)) && !mkdir(dirname($planFile), 0o775, true) && !is_dir(dirname($planFile))) {
                throw new \RuntimeException('Cannot create the fly-through plan directory');
            }
            $json = json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
            if (false === file_put_contents($planFile, $json)) {
                throw new \RuntimeException('Cannot write the fly-through plan');
            }
            $this->io->text(sprintf(
                'Camera moves from %s to %s over %d frames at %d fps.',
                $route['start_stack'],
                $route['end_stack'],
                $route['frames'],
                $route['fps']
            ));
            if ($input->getOption('dry-run')) {
                $this->io->success('Wrote '.$planFile);

                return self::SUCCESS;
            }
            $builder = $this->getApplication()?->find('scene:build');
            if (null === $builder || self::SUCCESS !== $builder->run(new ArrayInput([
                'scene' => $this->sceneKey($scene),
            ]), $output)) {
                return self::FAILURE;
            }
            (new BlenderRunner($this->runner))->run(
                $this->projectDir().'/blender/render_scene.py',
                $planFile,
                static function (string $chunk) use ($output): void { $output->write($chunk); },
            );
            if (!is_file((string) $target) || 0 === filesize((string) $target)) {
                throw new \RuntimeException('Blender did not write the fly-through video');
            }
            $this->io->success('Wrote '.(string) $target);

            return self::SUCCESS;
        } catch (\InvalidArgumentException|\RuntimeException|\JsonException $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
