<?php

declare(strict_types=1);

namespace App\Render;

/** A straight camera route between the outer stacks' far focus points. */
final class FlyThroughPlan
{
    /**
     * @param array<string, array{float, float, float}> $points stack identities and their focus coordinates
     * @param array{float, float, float} $target the rig's centre
     *
     * @return array<string, mixed>
     */
    public static function between(array $points, array $target, float $seconds = 6.0, int $fps = 24, string $cameraAim = 'rig-centre'): array
    {
        if (!in_array($cameraAim, ['rig-centre', 'perpendicular'], true)) {
            throw new \InvalidArgumentException('Camera aim must be rig-centre or perpendicular');
        }
        if (count($points) < 2) {
            throw new \InvalidArgumentException('A fly-through needs at least two stacks with far focus points');
        }
        if (!is_finite($seconds) || $seconds <= 0.0 || $seconds > 3600.0 || $fps < 1 || $fps > 60) {
            throw new \InvalidArgumentException('Use a duration above 0 and at most 3600 seconds, and 1 to 60 fps');
        }
        uasort($points, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $start = array_key_first($points);
        $end = array_key_last($points);
        if ($points[$end][0] - $points[$start][0] < 1e-6) {
            throw new \InvalidArgumentException('The outer far focus points have no horizontal separation');
        }

        return [
            'start_stack' => $start,
            'end_stack' => $end,
            'start' => $points[$start],
            'end' => $points[$end],
            'target' => $target,
            'camera_aim' => $cameraAim,
            'fps' => $fps,
            'frames' => max(2, (int) round($seconds * $fps)),
        ];
    }
}
