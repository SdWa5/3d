<?php

declare(strict_types=1);

namespace App\Render;

use App\Scene\PlacedDevice;

/**
 * Works out where the camera and lights go for a scene, from the scene itself.
 *
 * Nothing here is hardcoded to a particular rig: the framing comes from the bounding box of the
 * placed cabinets, so one preset frames a single monitor and a fourteen-wide sub wall equally well.
 * Doing it in PHP rather than in the Blender script means the maths is unit-testable — and camera
 * framing is exactly the kind of thing that silently drifts when nobody can assert on it.
 */
final class RenderPlan
{
    public const DEFAULT_SAMPLES = 64;

    public const DEFAULT_RESOLUTION = [1600, 900];

    /** Blender's sensor width in mm, which its default camera also uses. */
    private const SENSOR_MM = 36.0;

    /**
     * @param list<PlacedDevice> $placed
     * @param array{int, int} $resolution
     * @return array<string, mixed>
     */
    public static function forScene(
        array $placed,
        CameraPreset $camera = CameraPreset::ThreeQuarter,
        LightingPreset $lighting = LightingPreset::Studio,
        int $samples = self::DEFAULT_SAMPLES,
        array $resolution = self::DEFAULT_RESOLUTION,
        bool $ground = true,
    ): array {
        ['min' => $min, 'max' => $max] = self::bounds($placed);

        $centre = [
            ($min[0] + $max[0]) / 2,
            ($min[1] + $max[1]) / 2,
            ($min[2] + $max[2]) / 2,
        ];
        $radius = self::radius($min, $max);

        return [
            'plan_version' => 1,
            'camera' => self::camera($camera, $centre, $radius, $resolution),
            'lighting' => self::lighting($lighting, $centre, $radius),
            'ground' => [
                'enabled' => $ground,
                'size' => max(20.0, $radius * 8),
                'color' => $lighting->ground(),
            ],
            'world' => ['color' => $lighting->background()],
            'render' => [
                'samples' => $samples,
                'resolution' => [$resolution[0], $resolution[1]],
            ],
            'scene_bounds' => ['min' => $min, 'max' => $max, 'radius' => $radius],
        ];
    }

    /**
     * Bounding box of every cabinet, including its own extent rather than just its centre.
     *
     * Yaw is ignored on purpose: accounting for it exactly would mean rotating four corners per
     * cabinet for a result the framing margin absorbs anyway. A rotated cabinet is at most
     * `max(w, d)` across, so using the larger of the two keeps the box generous rather than tight.
     *
     * @param list<PlacedDevice> $placed
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public static function bounds(array $placed): array
    {
        if ($placed === []) {
            return ['min' => [-1.0, -1.0, 0.0], 'max' => [1.0, 1.0, 1.0]];
        }

        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach ($placed as $entry) {
            $dimensions = $entry->device->dimensions;
            $spread = ($entry->yawDeg === 0.0 ? $dimensions->width : max($dimensions->width, $dimensions->depth)) / 2;
            $depth = ($entry->yawDeg === 0.0 ? $dimensions->depth : max($dimensions->width, $dimensions->depth)) / 2;

            $min[0] = min($min[0], $entry->position[0] - $spread);
            $max[0] = max($max[0], $entry->position[0] + $spread);
            $min[1] = min($min[1], $entry->position[1] - $depth);
            $max[1] = max($max[1], $entry->position[1] + $depth);
            $min[2] = min($min[2], $entry->position[2]);
            $max[2] = max($max[2], $entry->topZ());
        }

        /** @var array{float, float, float} $min */
        /** @var array{float, float, float} $max */
        return ['min' => $min, 'max' => $max];
    }

    /**
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     */
    private static function radius(array $min, array $max): float
    {
        $half = [
            ($max[0] - $min[0]) / 2,
            ($max[1] - $min[1]) / 2,
            ($max[2] - $min[2]) / 2,
        ];

        return max(0.5, sqrt($half[0] ** 2 + $half[1] ** 2 + $half[2] ** 2));
    }

    /**
     * Camera position and aim. The distance fits a sphere of the scene's radius into the narrower of
     * the two fields of view, so nothing is cropped whatever the aspect ratio.
     *
     * @param array{float, float, float} $centre
     * @param array{int, int} $resolution
     * @return array<string, mixed>
     */
    private static function camera(CameraPreset $preset, array $centre, float $radius, array $resolution): array
    {
        $lens = $preset->lensMm();
        $aspect = $resolution[1] / max(1, $resolution[0]);

        $fovWide = 2 * atan(self::SENSOR_MM / (2 * $lens));
        $fovNarrow = 2 * atan((self::SENSOR_MM * $aspect) / (2 * $lens));
        $fov = min($fovWide, $fovNarrow);

        $distance = ($radius / sin($fov / 2)) * $preset->margin();

        $direction = $preset->direction();
        $length = sqrt($direction[0] ** 2 + $direction[1] ** 2 + $direction[2] ** 2) ?: 1.0;
        $position = [
            $centre[0] + $direction[0] / $length * $distance,
            $centre[1] + $direction[1] / $length * $distance,
            $centre[2] + $direction[2] / $length * $distance,
        ];

        $target = $centre;
        $eyeHeight = $preset->eyeHeightM();
        if ($eyeHeight !== null) {
            // Standing on the ground rather than floating: keep the aim slightly low so the rig
            // towers over the viewer the way it does in person.
            $position[2] = $eyeHeight;
            $target = [$centre[0], $centre[1], $centre[2] * 0.75];
        }

        return [
            'location' => $position,
            'target' => $target,
            'lens_mm' => $lens,
            'preset' => $preset->value,
        ];
    }

    /**
     * Light positions are multiples of the scene radius from its centre, and area-light power scales
     * with the square of that radius — otherwise a big rig comes out dark at the same settings that
     * light one cabinet nicely.
     *
     * @param array{float, float, float} $centre
     * @return array<string, mixed>
     */
    private static function lighting(LightingPreset $preset, array $centre, float $radius): array
    {
        $lights = [];
        foreach ($preset->lights() as $index => $light) {
            $isSun = $light['kind'] === 'SUN';

            $lights[] = [
                'name' => sprintf('%s-%d', $preset->value, $index + 1),
                'kind' => $light['kind'],
                'location' => [
                    $centre[0] + $light['at'][0] * $radius,
                    $centre[1] + $light['at'][1] * $radius,
                    max($isSun ? 1.0 : 0.5, $centre[2] + $light['at'][2] * $radius),
                ],
                'target' => $centre,
                // A sun's strength is irradiance and does not fall off, so it is left alone.
                'energy' => $isSun ? $light['energy'] : $light['energy'] * ($radius ** 2),
                'size' => $light['size'] * $radius,
                'color' => $light['color'],
            ];
        }

        return ['preset' => $preset->value, 'lights' => $lights];
    }
}
