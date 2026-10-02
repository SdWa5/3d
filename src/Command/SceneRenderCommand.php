<?php

declare(strict_types=1);

namespace App\Command;

use App\Build\BlenderRunner;
use App\Build\ModelBuilder;
use App\Build\Staleness;
use App\Render\CameraPreset;
use App\Render\CameraStand;
use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\SceneCompiler;
use App\Spec\Violation;
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
    /** The Blender script this stage runs; an input for {@see Staleness}, so editing it redraws. */
    public const SCRIPT = 'blender/render_scene.py';

    protected function configure(): void
    {
        $cameras = implode(', ', array_column(CameraPreset::cases(), 'value'));
        $lightings = implode(', ', array_column(LightingPreset::cases(), 'value'));

        $this
            ->setName('scene:render')
            ->setDescription('Render a scene to build/renders/<id>-<camera>.png')
            ->addArgument('scene', InputArgument::OPTIONAL, 'Scene id, path or folder; omit to render every scene')
            ->addOption('camera', 'c', InputOption::VALUE_REQUIRED, "Camera preset ({$cameras})", CameraPreset::ThreeQuarter->value)
            ->addOption('distance', null, InputOption::VALUE_REQUIRED, 'Stand the camera this many metres from the rig\'s nearest face and zoom to fit, rather than fitting the distance')
            ->addOption('eye-height', null, InputOption::VALUE_REQUIRED, 'Put the camera this many metres above the floor')
            ->addOption('lighting', 'l', InputOption::VALUE_REQUIRED, "Lighting preset ({$lightings})", LightingPreset::Studio->value)
            // No defaults on these two: the level flags below supply them, and a default here could not be told
            // apart from a value somebody typed — which is what decides whether it overrules the level.
            ->addOption('samples', null, InputOption::VALUE_REQUIRED, sprintf(
                'Cycles samples (default %d, --quick-preview %d, --high-quality %d)',
                RenderPlan::DEFAULT_SAMPLES,
                RenderPlan::QUICK_SAMPLES,
                RenderPlan::HIGH_SAMPLES,
            ))
            ->addOption('resolution', 'r', InputOption::VALUE_REQUIRED, sprintf(
                'WIDTHxHEIGHT (default %s, --quick-preview %s, --high-quality %s)',
                implode('x', RenderPlan::DEFAULT_RESOLUTION),
                implode('x', RenderPlan::QUICK_RESOLUTION),
                implode('x', RenderPlan::HIGH_RESOLUTION),
            ))
            ->addOption('quick-preview', null, InputOption::VALUE_NONE, sprintf(
                'Least that answers "is this the rig I meant" — %s at %d samples',
                implode('x', RenderPlan::QUICK_RESOLUTION),
                RenderPlan::QUICK_SAMPLES,
            ))
            ->addOption('high-quality', null, InputOption::VALUE_NONE, sprintf(
                'Most worth spending on a still — %s at %d samples',
                implode('x', RenderPlan::HIGH_RESOLUTION),
                RenderPlan::HIGH_SAMPLES,
            ))
            ->addOption('no-ground', null, InputOption::VALUE_NONE, 'Leave out the ground plane')
            ->addOption(
                'aim-lines',
                'a',
                InputOption::VALUE_OPTIONAL,
                "Draw where cabinets point: tops (default) or all. Overrules the scene's own aim_lines",
                RenderPlan::AIM_NONE,
            )
            // **Beschriftungen and a legend, stated by the owner.** A pack render carries three vehicle cages and
            // twenty-five cabinets and said nowhere which was which. An annotation like `--aim-lines` rather than
            // geometry in the `.blend`, so the same assembled scene draws with or without and neither is canonical.
            ->addOption('labels', null, InputOption::VALUE_NONE, 'Name every device in the picture and add a colour legend')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output PNG path (single scene only)')
            ->addOption(
                'out-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to write into, keeping the <scene>-<camera>.png names. Works for every scene',
            )
            ->addOption('presets', null, InputOption::VALUE_NONE, 'List the presets and exit')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-render even when the PNG looks up to date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if ($input->getOption('presets')) {
            $this->listPresets();

            return self::SUCCESS;
        }

        $settings = $this->settings($input);
        if (null === $settings) {
            return self::FAILURE;
        }

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ([] !== $errors) {
            $this->io->error('Some specs could not be read — fix them first (see `specs:validate`)');

            return self::FAILURE;
        }

        $scenes = $this->selectScenes($input->getArgument('scene'));
        if (null === $scenes) {
            return self::FAILURE;
        }
        if ([] === $scenes) {
            $this->io->warning('No scenes found in '.$this->relative($this->scenesDir()));

            return self::SUCCESS;
        }
        if (count($scenes) > 1 && null !== $input->getOption('out')) {
            $this->io->error('--out only makes sense when rendering a single scene — use --out-dir instead');

            return self::FAILURE;
        }
        if (null !== $input->getOption('out') && null !== $input->getOption('out-dir')) {
            $this->io->error('use either --out or --out-dir, not both');

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
            $this->reportViolations($violations);
            // Errors only, as in `scene:build`: a warning is something to know about a buildable rig, not a
            // reason to refuse to render it.
            if ([] !== Violation::errorsIn($violations)) {
                $exit = self::FAILURE;
                continue;
            }
            if ([] === $placed) {
                $this->io->warning("Scene '{$scene->id}' has no placements");
                continue;
            }

            $sceneBlend = $this->derivedDir($builder->buildDir().'/scenes', $scene).'/'.$scene->id.'.blend';
            if (!is_file($sceneBlend)) {
                $this->io->error(sprintf(
                    "No assembled scene at %s\nRun `bin/console scene:build %s` first.",
                    $this->relative($sceneBlend),
                    $scene->id,
                ));
                $exit = self::FAILURE;
                continue;
            }

            // The `generated/` subdirectory is composed onto whatever directory was chosen, `--out-dir` included,
            // so `build:all`'s camera folders keep their generated renders separate too.
            $directory = $this->derivedDir(
                (string) ($input->getOption('out-dir') ?? $builder->buildDir().'/renders'),
                $scene,
            );
            $target = $input->getOption('out')
                ?? sprintf(
                    '%s/%s-%s%s.png',
                    rtrim((string) $directory, '/'),
                    $scene->id,
                    $settings['camera']->value,
                    $settings['stand']->suffix(),
                );

            // One manifest for the whole render tree, at its root — never one per variant folder, so that
            // `build:all`'s eight subfolders share a single record keyed `studio/full-rig-side.png` and so that
            // reading "what was everything drawn with" is opening one file.
            $manifest = Staleness::manifestIn($builder->buildDir().'/renders');

            // The flag if it was given, otherwise whatever the scene asks for. Resolved before the freshness
            // check rather than after, because the aim mode is part of what is being *asked for* and the check
            // now compares that too.
            $aimLines = $settings['aimLines'] ?? $scene->aimLines;

            // What this picture was drawn with, against what is being asked for now. Only the settings that can
            // differ for one path belong here — and that is most of them, since only the camera and the scene id
            // are in the filename. Two variants of a scene under different lighting are the same path, which is
            // why `build:all` puts them in separate folders.
            $builtWith = [
                'camera' => $settings['camera']->value,
                'lighting' => $settings['lighting']->value,
                'samples' => $settings['samples'],
                'resolution' => $settings['resolution'],
                'ground' => !$settings['noGround'],
                'aim_lines' => $aimLines ?? RenderPlan::AIM_NONE,
                // Part of the settings stamp, so turning labels on redraws a picture that is otherwise current.
                'labels' => (bool) $input->getOption('labels'),
            ];
            // Only when stated, so every picture drawn before the stand existed keeps a stamp that still matches.
            if ($settings['stand']->isStated()) {
                $builtWith['stand'] = [$settings['stand']->distanceM, $settings['stand']->eyeHeightM];
            }

            // Only redraw what has changed. A render is the most expensive thing in the pipeline — and `build:all`
            // now sweeps eight variants of every scene at Full HD by default — so a rig nobody has touched should
            // cost nothing. Two questions, because mtimes can only answer the first: did an *input* move, and is
            // this picture drawn with the settings now being asked for. The `.blend` answers the first for the
            // whole chain — `scene:build` only rewrites it when the scene file or one of the cabinet models
            // moved, so a spec edit still reaches the PNG.
            if (!$input->getOption('force')
                && !Staleness::outOfDate(
                    [$target],
                    [$sceneBlend, ...Staleness::blenderInputs($this->projectDir(), self::SCRIPT)],
                )
                && !Staleness::settingsChanged($manifest, $target, $builtWith)
            ) {
                $this->io->text(sprintf('<comment>up to date</comment> %s', $this->relative($target)));
                continue;
            }

            $plan = RenderPlan::forScene(
                $placed,
                $settings['camera'],
                $settings['lighting'],
                $settings['samples'],
                $settings['resolution'],
                !$settings['noGround'],
                $aimLines,
                (bool) $input->getOption('labels'),
                $settings['stand'],
            ) + [
                'scene_id' => $scene->id,
                'camera_stand' => $settings['stand']->suffix(),
                'scene_blend' => $sceneBlend,
                'output' => $target,
            ];

            $this->io->text(sprintf(
                '<info>→</info> %s — %s camera, %s lighting, %d samples, %dx%d%s',
                $scene->id,
                $settings['camera']->value.$settings['stand']->suffix(),
                $settings['lighting']->value,
                $settings['samples'],
                $settings['resolution'][0],
                $settings['resolution'][1],
                RenderPlan::AIM_NONE === $aimLines
                    ? ''
                    : sprintf(', aim lines: %s (%d)', $aimLines, count($plan['aim_lines'])),
            ).([] === $plan['labels'] ? '' : sprintf(', %d labels', count($plan['labels']))));

            try {
                $this->renderPlan($plan, $target, $output);
            } catch (\RuntimeException $e) {
                $this->io->error($e->getMessage());
                $exit = self::FAILURE;
                continue;
            }

            // After the render, never before: a stamp written ahead of a Blender run that then failed would claim
            // the old picture was drawn with the new settings, and that is the one way this could rebuild too
            // little. A stamp that cannot be written is a warning rather than a failure — the picture is good, and
            // the only cost is that it re-renders once more than it needed to.
            if (!Staleness::recordSettings($manifest, $target, $builtWith)) {
                $this->io->warning(sprintf(
                    'Rendered, but could not record the settings in %s — %s will re-render next time',
                    $this->relative($manifest),
                    $this->relative($target),
                ));
            }

            $this->io->success('Wrote '.$this->relative($target));
        }

        return $exit;
    }

    /**
     * @return array{camera: CameraPreset, stand: CameraStand, lighting: LightingPreset, samples: int, resolution: array{int, int}, noGround: bool, aimLines: string|null}|null
     */
    private function settings(InputInterface $input): ?array
    {
        $camera = CameraPreset::tryFrom((string) $input->getOption('camera'));
        if (null === $camera) {
            $this->io->error(sprintf(
                "Unknown camera preset '%s'. Available: %s",
                $input->getOption('camera'),
                implode(', ', array_column(CameraPreset::cases(), 'value')),
            ));

            return null;
        }

        $stated = [];
        foreach (['distance', 'eye-height'] as $name) {
            $value = $input->getOption($name);
            if (null !== $value && (!is_numeric($value) || (float) $value <= 0.0)) {
                $this->io->error(sprintf('--%s must be a number of metres above 0, got \'%s\'', $name, $value));

                return null;
            }
            $stated[$name] = null === $value ? null : (float) $value;
        }
        $stand = new CameraStand($stated['distance'], $stated['eye-height']);

        $lighting = LightingPreset::tryFrom((string) $input->getOption('lighting'));
        if (null === $lighting) {
            $this->io->error(sprintf(
                "Unknown lighting preset '%s'. Available: %s",
                $input->getOption('lighting'),
                implode(', ', array_column(LightingPreset::cases(), 'value')),
            ));

            return null;
        }

        $level = $this->qualityLevel($input);
        if (null === $level) {
            return null;
        }

        // A stated `--samples` or `--resolution` wins over the level, so the levels are a shorthand rather than a
        // constraint. That matters for the one thing a level cannot say — a 4K frame at 16 samples to check
        // framing, or a 960×540 one at 384 to look at a chamfer.
        $samples = null !== $input->getOption('samples')
            ? (int) $input->getOption('samples')
            : $level['samples'];
        if ($samples < 1) {
            $this->io->error('--samples must be at least 1');

            return null;
        }

        $resolution = $level['resolution'];
        if (null !== $input->getOption('resolution')) {
            $resolution = $this->resolution((string) $input->getOption('resolution'));
            if (null === $resolution) {
                return null;
            }
        }

        // Whether the flag was *given* has to be told apart from the value it defaults to, because a
        // scene can now state its own mode and the flag only overrules it when somebody typed it. The
        // option's default cannot answer that: bare `--aim-lines` already yields null, which is how it
        // means "tops".
        $aimLines = null;
        if ($input->hasParameterOption(['--aim-lines', '-a'], true)) {
            $aimLines = $input->getOption('aim-lines') ?? RenderPlan::AIM_TOPS;
        }

        $allowed = [RenderPlan::AIM_NONE, RenderPlan::AIM_TOPS, RenderPlan::AIM_ALL];
        if (null !== $aimLines && !in_array($aimLines, $allowed, true)) {
            $this->io->error(sprintf(
                "Unknown --aim-lines value '%s'. Available: %s",
                $aimLines,
                implode(', ', $allowed),
            ));

            return null;
        }

        return [
            'camera' => $camera,
            'stand' => $stand,
            'lighting' => $lighting,
            'samples' => $samples,
            'resolution' => $resolution,
            'noGround' => (bool) $input->getOption('no-ground'),
            'aimLines' => $aimLines,
        ];
    }

    /**
     * The samples and resolution the named level asks for, or the default when none is named.
     *
     * Both levels together is a contradiction rather than a precedence question — there is no sensible reading of
     * "the least that answers a question, and also the most worth spending" — so it is refused the same way
     * `align` refuses two envelopes.
     *
     * @return array{samples: int, resolution: array{int, int}}|null
     */
    private function qualityLevel(InputInterface $input): ?array
    {
        $quick = (bool) $input->getOption('quick-preview');
        $high = (bool) $input->getOption('high-quality');

        if ($quick && $high) {
            $this->io->error('--quick-preview and --high-quality ask for opposite things — pick one');

            return null;
        }

        return RenderPlan::quality(match (true) {
            $quick => RenderPlan::QUICK,
            $high => RenderPlan::HIGH,
            default => null,
        });
    }

    /**
     * @return array{int, int}|null
     */
    private function resolution(string $raw): ?array
    {
        if (1 !== preg_match('/^(\d+)\s*[x×]\s*(\d+)$/i', trim($raw), $matches)) {
            $this->io->error("--resolution must look like 1600x900, got '{$raw}'");

            return null;
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];
        if ($width < 16 || $height < 16) {
            $this->io->error('--resolution must be at least 16x16');

            return null;
        }

        return [$width, $height];
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
            '%s/build/plans/_render-%s-%s%s-%s%s.json',
            $this->projectDir(),
            $plan['scene_id'],
            $plan['camera']['preset'],
            $plan['camera_stand'] ?? '',
            // The lighting and the aim mode belong in the name: without them two variants of one scene
            // overwrite each other's plan, and a plan that does not match the picture beside it is worse
            // than no plan at all.
            $plan['lighting']['preset'],
            [] === $plan['aim_lines'] ? '' : '-aim',
        );

        $dir = dirname($planFile);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }

        try {
            $json = json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Cannot encode the render plan: '.$e->getMessage(), 0, $e);
        }
        if (false === @file_put_contents($planFile, $json."\n")) {
            throw new \RuntimeException("Cannot write the render plan to {$planFile}");
        }

        (new BlenderRunner($this->runner))->run(
            $this->projectDir().'/'.self::SCRIPT,
            $planFile,
            $this->blenderOutputSink($output),
        );

        if (!is_file($target)) {
            throw new \RuntimeException("Blender did not write the render at {$target}");
        }
    }
}
