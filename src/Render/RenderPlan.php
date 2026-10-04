<?php

declare(strict_types=1);

namespace App\Render;

use App\Scene\PlacedDevice;
use App\Spec\Category;

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
    /**
     * The quality a render gets when nothing asks for anything else — **Full HD at 128 samples**.
     *
     * Raised from 1600×900/64, which was the only quality there was. Two named levels sit either side of it, and
     * the three exist because the same command is used for two different jobs: checking that a rig is arranged
     * the way you meant, and producing something to look at.
     *
     * The numbers are per-frame costs relative to the old default, and they compound with `build:all`'s eight
     * variants — a full sweep at the default is around 23× what it used to be. That is the intended trade rather
     * than an accident, and {@see \App\Command\BuildAllCommand} is where the eight comes from.
     */
    public const DEFAULT_SAMPLES = 128;

    public const DEFAULT_RESOLUTION = [1920, 1080];

    /**
     * `--quick-preview`: the least that still answers "is this the rig I meant" — 0.09× a default frame.
     *
     * Sixteen samples is visibly noisy and 960×540 is small, and neither matters for the question it is for. It
     * is the level to use while iterating on a scene file.
     */
    public const QUICK_SAMPLES = 16;

    public const QUICK_RESOLUTION = [960, 540];

    /**
     * `--high-quality`: 4K at 384 samples, 17× a default frame.
     *
     * The most that is worth spending on a still of a grey rig. Past a few hundred samples Cycles is chasing
     * noise nobody can see on matte plywood, and past 4K the cabinets are not modelled finely enough to reward
     * it — the chamfers are 12 mm and the handles are plain cuts.
     */
    public const HIGH_SAMPLES = 384;

    public const HIGH_RESOLUTION = [3840, 2160];

    public const QUICK = 'quick';

    public const HIGH = 'high';

    public const AIM_NONE = 'none';

    public const AIM_TOPS = 'tops';

    public const AIM_ALL = 'all';

    /**
     * The samples and resolution a named level asks for; the default pair when none is named.
     *
     * Here rather than in the command because this is where the three pairs are written down, and a level that
     * resolved somewhere else could drift from the constants it is meant to name. The command's own job is only
     * turning two flags into one of these three answers — and refusing both flags at once.
     *
     * @return array{samples: int, resolution: array{int, int}}
     */
    public static function quality(?string $level): array
    {
        return match ($level) {
            self::QUICK => ['samples' => self::QUICK_SAMPLES, 'resolution' => self::QUICK_RESOLUTION],
            self::HIGH => ['samples' => self::HIGH_SAMPLES, 'resolution' => self::HIGH_RESOLUTION],
            null => ['samples' => self::DEFAULT_SAMPLES, 'resolution' => self::DEFAULT_RESOLUTION],
            default => throw new \InvalidArgumentException("Unknown quality level '{$level}'"),
        };
    }

    /** Subtypes that are aimed at an audience; subs are omnidirectional enough not to bother. */
    private const AIMED_SUBTYPES = ['top', 'monitor', 'line-array-element'];

    /** How far a line runs when it never meets the floor, and the furthest it runs when it does. */
    private const AIM_LENGTH_M = 12.0;

    private const AIM_MAX_LENGTH_M = 40.0;

    /** Blender's sensor width in mm, which its default camera also uses. */
    private const SENSOR_MM = 36.0;

    /**
     * Takes a fresh solve's placed devices or the render placements `scene:build` stored, see {@see RenderPlacement}.
     *
     * @param list<PlacedDevice|RenderPlacement> $placed
     * @param array{int, int} $resolution
     *
     * @return array<string, mixed>
     */
    public static function forScene(
        array $placed,
        CameraPreset $camera = CameraPreset::ThreeQuarter,
        LightingPreset $lighting = LightingPreset::Studio,
        int $samples = self::DEFAULT_SAMPLES,
        array $resolution = self::DEFAULT_RESOLUTION,
        bool $ground = true,
        string $aimLines = self::AIM_NONE,
        bool $labels = false,
        CameraStand $stand = new CameraStand(),
    ): array {
        $placed = RenderPlacement::listOf($placed);
        ['min' => $min, 'max' => $max] = self::bounds($placed);
        $lines = self::aimLines($placed, $aimLines);

        // Frame the rays too, otherwise the one thing they exist to show — where they converge and
        // land — sits outside the picture.
        foreach ($lines as $line) {
            foreach (['start', 'end'] as $point) {
                for ($axis = 0; $axis < 3; ++$axis) {
                    $min[$axis] = min($min[$axis], $line[$point][$axis]);
                    $max[$axis] = max($max[$axis], $line[$point][$axis]);
                }
            }
        }

        $centre = [
            ($min[0] + $max[0]) / 2,
            ($min[1] + $max[1]) / 2,
            ($min[2] + $max[2]) / 2,
        ];
        $radius = self::radius($min, $max);

        return [
            'plan_version' => 1,
            'camera' => self::camera($camera, $min, $max, $centre, $radius, $resolution, $stand),
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
                'exposure' => $lighting->exposure(),
            ],
            'aim_lines' => $lines,
            'labels' => $labels ? self::labels($placed, $min, $max, $radius) : [],
            'scene_bounds' => ['min' => $min, 'max' => $max, 'radius' => $radius],
        ];
    }

    /**
     * A name over every device, and a legend beside the scene.
     *
     * **A pack render carries three vehicle cages and twenty-five cabinets and said nowhere which was which.**
     * Stated by the owner: it needs Beschriftungen and a legend. A reader who did not write the code could see that
     * something was packed and not what.
     *
     * **One label per device rather than per unit, placed over the tallest of them.** Seven Flexys labelled seven
     * times is noise, not information — the count goes in the text instead, so `7x flexy-folded-horn-hybrid` reads
     * once. Grouped by device *and* by which vehicle's footprint it sits in, because the same cabinet in two vans is
     * two facts.
     *
     * **The legend is the thing that makes a cage picture readable at all.** It names what the colours mean, which
     * is knowledge that otherwise lives only in `blender/lib/materials.py`.
     *
     * @param list<RenderPlacement> $placed
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     *
     * @return list<array{text: string, at: array{float, float, float}, size: float}>
     */
    private static function labels(array $placed, array $min, array $max, float $radius): array
    {
        $vehicles = [];
        foreach ($placed as $entry) {
            if (Category::Vehicle === $entry->category) {
                $vehicles[$entry->placementId] = $entry;
            }
        }

        // Group the cargo: which vehicle it is standing in, then which device it is.
        $groups = [];
        foreach ($placed as $entry) {
            if (Category::Vehicle === $entry->category) {
                continue;
            }
            $inside = self::vehicleAround($entry, $vehicles);
            $key = $inside.'/'.$entry->deviceId;
            $box = $entry->worldBox;
            if (!isset($groups[$key])) {
                $groups[$key] = ['device' => $entry->deviceId, 'count' => 0, 'at' => $box['max'], 'x' => $box['min'][0], 'x2' => $box['max'][0]];
            }
            ++$groups[$key]['count'];
            // Anchored over the tallest of the group, centred across all of them.
            if ($box['max'][2] > $groups[$key]['at'][2]) {
                $groups[$key]['at'] = $box['max'];
            }
            $groups[$key]['x'] = min($groups[$key]['x'], $box['min'][0]);
            $groups[$key]['x2'] = max($groups[$key]['x2'], $box['max'][0]);
        }

        // **Sized from the scene rather than fixed, and the first attempt was half what it needed to be.** A label
        // has to be legible at `--quick-preview`, which is 960 x 540 — the resolution `build:all` uses for every
        // picture it draws. At `radius / 60` the device names came out around nine pixels tall and were a grey smear.
        $size = max(0.10, $radius / 30.0);
        $labels = [];

        foreach ($vehicles as $entry) {
            $box = $entry->worldBox;
            $labels[] = [
                'text' => $entry->deviceId,
                'at' => [
                    ($box['min'][0] + $box['max'][0]) / 2,
                    ($box['min'][1] + $box['max'][1]) / 2,
                    $box['max'][2] + $size * 0.6,
                ],
                'size' => $size * 1.7,
            ];
        }

        foreach ($groups as $group) {
            $labels[] = [
                'text' => sprintf('%d× %s', $group['count'], $group['device']),
                'at' => [
                    ($group['x'] + $group['x2']) / 2,
                    $group['at'][1],
                    $group['at'][2] + $size * 0.4,
                ],
                'size' => $size,
            ];
        }

        return [...$labels, ...self::legend($min, $max, $radius, $size, [] !== $vehicles)];
    }

    /**
     * Which vehicle's footprint a placement stands in, or `-` for none.
     *
     * Footprint rather than full box, because a cabinet inside a van is inside it in plan and may stick out of the
     * top of a trailer — see the packed convoy, where a 4 m mast does exactly that.
     *
     * @param array<string, RenderPlacement> $vehicles
     */
    private static function vehicleAround(RenderPlacement $entry, array $vehicles): string
    {
        $box = $entry->worldBox;
        $x = ($box['min'][0] + $box['max'][0]) / 2;
        $y = ($box['min'][1] + $box['max'][1]) / 2;

        foreach ($vehicles as $id => $vehicle) {
            $outer = $vehicle->worldBox;
            if ($x >= $outer['min'][0] && $x <= $outer['max'][0] && $y >= $outer['min'][1] && $y <= $outer['max'][1]) {
                return $id;
            }
        }

        return '-';
    }

    /**
     * The colour key, standing beside the scene like a placard.
     *
     * **On the ground at one end rather than floating above the middle.** The first version put it high over the
     * far corner, where it read as text hanging in the sky detached from anything — and being high it was also the
     * part of the frame the camera gives least room to. Standing at one end, starting just off the floor, it reads
     * as a sign next to the thing it explains.
     *
     * Only the lines that can appear. A rig has no cages and no arches, so a rig's legend would be three lines about
     * things that are not in the picture — worse than no legend, because a reader then trusts it.
     *
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     *
     * @return list<array{text: string, at: array{float, float, float}, size: float}>
     */
    private static function legend(array $min, array $max, float $radius, float $size, bool $hasVehicles): array
    {
        $rows = ['LEGEND'];
        if ($hasVehicles) {
            $rows[] = 'grey cage = vehicle outline';
            $rows[] = 'blue cage = load bay';
            $rows[] = 'pale box = wheel arch';
        }
        $rows[] = 'red cage = fails a geometry check';

        $labels = [];
        $lineHeight = $size * 1.45;
        // **Clear of the vehicles in x, which the first offset was not.** `radius * 0.10` is 0.9 m on the packed
        // convoy against vans 2 m wide, so the placard landed on top of the Movano and read as text painted across
        // its side. The offset has to be measured against the scene rather than guessed at: half a radius puts it
        // beside the convoy with air around it.
        $top = $lineHeight * count($rows) + $size;
        foreach ($rows as $index => $row) {
            $labels[] = [
                'text' => $row,
                'at' => [
                    $max[0] + $radius * 0.5,
                    $min[1] + $radius * 0.1,
                    $top - $index * $lineHeight,
                ],
                'size' => 0 === $index ? $size * 1.25 : $size,
            ];
        }

        return $labels;
    }

    /**
     * Rays showing where each cabinet points, from the centre of its front face along its own axis.
     *
     * A line stops where it meets the floor, which turns "these all aim at one point" from a claim
     * into something visible: the rays either converge on that spot or they do not.
     *
     * @param list<RenderPlacement> $placed
     *
     * @return list<array{placement_id: string, device: string, start: array{float, float, float}, end: array{float, float, float}, hits_floor: bool}>
     */
    private static function aimLines(array $placed, string $mode): array
    {
        if (self::AIM_NONE === $mode) {
            return [];
        }

        $lines = [];
        foreach ($placed as $entry) {
            // A placement may ask for a line either way; absent, the mode decides from the subtype. The
            // one thing a placement cannot overrule is `none`, which stays the way to get a clean render
            // of a scene that normally draws them.
            $wanted = $entry->aimLines
                ?? (self::AIM_ALL === $mode || in_array($entry->subtype, self::AIMED_SUBTYPES, true));

            if (!$wanted) {
                continue;
            }

            $start = $entry->frontFaceCentre;
            $direction = $entry->frontDirection;

            // A nearly level ray meets the floor a very long way out — 1° of tilt from 2 m up needs
            // over 100 m. Past the cap the ray is simply truncated and must NOT claim to have landed,
            // or the marker ends up on the floor below a line that stopped in mid-air.
            $toFloor = $direction[2] < -1e-6 && $start[2] > 0.0
                ? $start[2] / -$direction[2]
                : INF;

            $hitsFloor = $toFloor <= self::AIM_MAX_LENGTH_M;
            $length = $hitsFloor ? $toFloor : min(self::AIM_MAX_LENGTH_M, max(self::AIM_LENGTH_M, 0.0));

            $lines[] = [
                'placement_id' => $entry->placementId,
                'device' => $entry->deviceId,
                'start' => $start,
                'end' => [
                    $start[0] + $direction[0] * $length,
                    $start[1] + $direction[1] * $length,
                    $start[2] + $direction[2] * $length,
                ],
                'hits_floor' => $hitsFloor,
            ];
        }

        return $lines;
    }

    /**
     * Bounding box of every cabinet, including its own extent rather than just its centre.
     *
     * Every rotation is accounted for exactly, since PlacedDevice already rotates the eight corners.
     *
     * @param list<PlacedDevice|RenderPlacement> $placed
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public static function bounds(array $placed): array
    {
        if ([] === $placed) {
            return ['min' => [-1.0, -1.0, 0.0], 'max' => [1.0, 1.0, 1.0]];
        }

        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach (RenderPlacement::listOf($placed) as $entry) {
            // The cabinet's exact rotated box, so an angled or turned-over one is bounded correctly
            // rather than approximated.
            $box = $entry->worldBox;
            for ($axis = 0; $axis < 3; ++$axis) {
                $min[$axis] = min($min[$axis], $box['min'][$axis]);
                $max[$axis] = max($max[$axis], $box['max'][$axis]);
            }
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
     * Camera position and aim. The distance fits the bounding box as projected into the camera's own
     * frame, so a wide shallow rig fills the frame instead of sitting in the middle of it. A stated
     * distance turns that round, see {@see standing()}, and a stated eye height replaces the preset's.
     *
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     * @param array{float, float, float} $centre
     * @param array{int, int} $resolution
     *
     * @return array<string, mixed>
     */
    private static function camera(
        CameraPreset $preset,
        array $min,
        array $max,
        array $centre,
        float $radius,
        array $resolution,
        CameraStand $stand,
    ): array {
        $lens = $preset->lensMm();
        $aspect = $resolution[1] / max(1, $resolution[0]);
        if (null !== $stand->distanceM) {
            return self::standing($preset, $stand->distanceM, $stand->eyeHeightM, $min, $max, $centre, $aspect);
        }

        $fovHorizontal = 2 * atan(self::SENSOR_MM / (2 * $lens));
        $fovVertical = 2 * atan((self::SENSOR_MM * $aspect) / (2 * $lens));

        $direction = $preset->direction();
        $length = sqrt($direction[0] ** 2 + $direction[1] ** 2 + $direction[2] ** 2) ?: 1.0;
        $unit = [$direction[0] / $length, $direction[1] / $length, $direction[2] / $length];

        // Fit the bounding *box* as the camera actually sees it, not its bounding sphere. A sub wall
        // is wide and shallow, so its sphere is far bigger than its silhouette — fitting the sphere
        // pushed the camera back until the rig was a smudge in the middle of the frame.
        $extent = self::projectedExtent($min, $max, $centre, $unit);
        $distance = max(
            $extent['right'] / tan($fovHorizontal / 2),
            $extent['up'] / tan($fovVertical / 2),
        ) * $preset->margin() + $extent['forward'];
        $distance = max($distance, $radius * 0.6);

        $position = [
            $centre[0] + $unit[0] * $distance,
            $centre[1] + $unit[1] * $distance,
            $centre[2] + $unit[2] * $distance,
        ];

        $target = $centre;
        if (null !== $stand->eyeHeightM) {
            $position[2] = $stand->eyeHeightM;
        }
        $eyeHeight = $stand->eyeHeightM ?? $preset->eyeHeightM();
        if (CameraPreset::Crowd === $preset && null !== $eyeHeight) {
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
     * A camera at a stated distance from the rig's nearest face, with the lens zoomed to fit.
     *
     * The distance runs on the ground along the preset's direction, from the face of the bounding box nearest the
     * camera, so "12 m in front" means 12 m from the front row and not from the middle of the rig. Without an eye
     * height the camera keeps the preset's elevation. It aims at the box centre, or low like the fitted crowd view,
     * and the lens is the longest whose frame still holds every corner of the box with the preset's margin. A lens
     * longer than the preset's is a zoom, which is the point, and one the rig would not fit even at the widest is
     * clamped at 10 mm, so a camera stood inside the rig still renders something.
     *
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     * @param array{float, float, float} $centre
     *
     * @return array<string, mixed>
     */
    private static function standing(
        CameraPreset $preset,
        float $distanceM,
        ?float $eyeHeightM,
        array $min,
        array $max,
        array $centre,
        float $aspect,
    ): array {
        $direction = $preset->direction();
        $ground = hypot($direction[0], $direction[1]) ?: 1.0;
        $across = [$direction[0] / $ground, $direction[1] / $ground, 0.0];

        $front = 0.0;
        foreach (self::corners($min, $max) as $corner) {
            $front = max($front, ($corner[0] - $centre[0]) * $across[0] + ($corner[1] - $centre[1]) * $across[1]);
        }
        $reach = $front + $distanceM;
        $position = [
            $centre[0] + $across[0] * $reach,
            $centre[1] + $across[1] * $reach,
            $eyeHeightM ?? $centre[2] + $direction[2] / $ground * $reach,
        ];

        $eyeHeight = $eyeHeightM ?? $preset->eyeHeightM();
        $target = CameraPreset::Crowd === $preset && null !== $eyeHeight
            ? [$centre[0], $centre[1], $centre[2] * 0.75]
            : $centre;

        $view = self::normalise([$target[0] - $position[0], $target[1] - $position[1], $target[2] - $position[2]]);
        $back = [-$view[0], -$view[1], -$view[2]];
        $worldUp = abs($back[2]) > 0.99 ? [0.0, 1.0, 0.0] : [0.0, 0.0, 1.0];
        $right = self::normalise(self::cross($worldUp, $back));
        $up = self::normalise(self::cross($back, $right));

        $tanAcross = 0.0;
        $tanUp = 0.0;
        foreach (self::corners($min, $max) as $corner) {
            $offset = [$corner[0] - $position[0], $corner[1] - $position[1], $corner[2] - $position[2]];
            $depth = max(0.01, self::dot($offset, $view));
            $tanAcross = max($tanAcross, abs(self::dot($offset, $right)) / $depth);
            $tanUp = max($tanUp, abs(self::dot($offset, $up)) / $depth);
        }
        $margin = $preset->margin();
        $lens = min(
            0.0 < $tanAcross ? self::SENSOR_MM / (2 * $tanAcross * $margin) : INF,
            0.0 < $tanUp ? self::SENSOR_MM * $aspect / (2 * $tanUp * $margin) : INF,
        );

        return [
            'location' => $position,
            'target' => $target,
            'lens_mm' => is_finite($lens) ? max(10.0, $lens) : $preset->lensMm(),
            'preset' => $preset->value,
        ];
    }

    /**
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     *
     * @return list<array{float, float, float}>
     */
    private static function corners(array $min, array $max): array
    {
        $corners = [];
        for ($corner = 0; $corner < 8; ++$corner) {
            $corners[] = [
                ($corner & 1) ? $max[0] : $min[0],
                ($corner & 2) ? $max[1] : $min[1],
                ($corner & 4) ? $max[2] : $min[2],
            ];
        }

        return $corners;
    }

    /**
     * Half-extents of the bounding box in the camera's own frame: how far it reaches across the
     * frame (right), up it (up), and towards the camera (forward).
     *
     * Projecting the eight corners onto the camera basis is what lets the framing be tight for a
     * wide rig and still correct for a tall one, instead of settling for whatever a sphere allows.
     *
     * @param array{float, float, float} $min
     * @param array{float, float, float} $max
     * @param array{float, float, float} $centre
     * @param array{float, float, float} $unit direction from the centre towards the camera
     *
     * @return array{right: float, up: float, forward: float}
     */
    private static function projectedExtent(array $min, array $max, array $centre, array $unit): array
    {
        // World up, unless we are looking almost straight down — then any horizontal axis will do.
        $worldUp = abs($unit[2]) > 0.99 ? [0.0, 1.0, 0.0] : [0.0, 0.0, 1.0];

        $right = self::normalise(self::cross($worldUp, $unit));
        $up = self::normalise(self::cross($unit, $right));

        $extent = ['right' => 0.0, 'up' => 0.0, 'forward' => 0.0];

        foreach (self::corners($min, $max) as $point) {
            $offset = [$point[0] - $centre[0], $point[1] - $centre[1], $point[2] - $centre[2]];

            $extent['right'] = max($extent['right'], abs(self::dot($offset, $right)));
            $extent['up'] = max($extent['up'], abs(self::dot($offset, $up)));
            $extent['forward'] = max($extent['forward'], self::dot($offset, $unit));
        }

        return $extent;
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
     *
     * @return array{float, float, float}
     */
    private static function cross(array $a, array $b): array
    {
        return [
            $a[1] * $b[2] - $a[2] * $b[1],
            $a[2] * $b[0] - $a[0] * $b[2],
            $a[0] * $b[1] - $a[1] * $b[0],
        ];
    }

    /**
     * @param array{float, float, float} $v
     *
     * @return array{float, float, float}
     */
    private static function normalise(array $v): array
    {
        $length = sqrt($v[0] ** 2 + $v[1] ** 2 + $v[2] ** 2) ?: 1.0;

        return [$v[0] / $length, $v[1] / $length, $v[2] / $length];
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
     */
    private static function dot(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
    }

    /**
     * Light positions are multiples of the scene radius from its centre, and area-light power scales
     * with the square of that radius — otherwise a big rig comes out dark at the same settings that
     * light one cabinet nicely.
     *
     * @param array{float, float, float} $centre
     *
     * @return array<string, mixed>
     */
    private static function lighting(LightingPreset $preset, array $centre, float $radius): array
    {
        $lights = [];
        foreach ($preset->lights() as $index => $light) {
            $isSun = 'SUN' === $light['kind'];

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
