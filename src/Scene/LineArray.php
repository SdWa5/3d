<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;

/**
 * A vertical hang: elements chained below one another, each tilted a little further than the one above.
 *
 * A sibling of {@see Arc} rather than a rolled version of it, and the difference is structural. An arc
 * rests on **one** centre of curvature shared by every cabinet, which is what makes its wedge argument
 * work and its radius solve a single maximum. An array is a **chain**: every gap has its own angle, so
 * there is no shared centre and nothing to take one maximum over. Forcing it into an arc would mean either
 * a constant splay — which is not a line array — or a list of radii, at which point the arc's whole
 * argument stops being true.
 *
 * What it does share is the contact machinery, transposed into the other plane. An arc solves on
 * {@see Outline::plan}, where cabinets meet **side to side** and the turn is about Z; an array solves on
 * {@see Outline::elevation}, where they meet **top to bottom** and the turn is about X. The wedge story
 * transposes with it: an arc's flush splay is the angle between the outline's two side edges, and an
 * array's is the angle between its top and bottom edges — `atan((height − front_height) / depth)` for a
 * wedge-shaped element, the vertical twin of `atan(taper / (depth − inset))`.
 *
 * How a joint is solved
 * ---------------------
 * Adjacent elements are pinned at one edge and the angle set from there, which is what a real frame does.
 * So for each candidate hinge — each depth the silhouette has an edge at — the position that closes the
 * joint is
 *
 *     O(i+1) = O(i) + R(Θi)·(y, z_bottom) − R(Θi+1)·(y, z_top)
 *
 * and the one to use is whichever drops furthest. That is not a preference: any shallower candidate leaves
 * the two elements inside each other. For a plain cabinet it picks the **rear** edge when the array curves
 * downward and the **front** edge when it curves up, and the two coincide at a wedge's own taper, where the
 * faces meet flat. Pinning the front edge of a downward curve instead overlaps a 0.52 m deep cabinet by
 * 45 mm — the vertical twin of the concave interpenetration {@see Arc} warns about.
 *
 * Θ is the tilt each element **ends up at**, aim included, which is why the chain is seeded with the
 * placement's `$pitchDeg` rather than with plumb. The joints are not scale-free in the angle: the same 2° of
 * splay closes differently at 14° than at 0°, so a chain solved plumb and then tilted as an afterthought has
 * every one of its joints solved for angles the array never reaches.
 *
 * The splay is *not* a turn of the group, which is why it comes out as
 * {@see PlacementCopy::$pitchIncrementDeg} rather than as a rotation: every element of a hang shares the
 * hang's yaw and differs only in tilt. The base tilt is the placement's own `pitch_deg` or its `aim`, on
 * the same division of labour an arc already uses — the array owns the increments, the aim owns where the
 * whole hang points.
 *
 * Out of scope, and worth saying out loud: an array hangs *below* its anchor, so its elements take negative
 * z and land under the floor unless the placement is raised. `at` and `on` can only name a ground position
 * or the top of something, so a fly-point anchor is a separate feature.
 */
final class LineArray implements Group
{
    /** Past this a cumulative tilt is a cabinet on its nose, where its yaw and roll stop being separable. */
    private const MAX_TILT_DEG = 89.0;

    /**
     * @param list<float> $splayDeg one angle per gap, so `count - 1` of them
     */
    public function __construct(
        public readonly int $count,
        public readonly array $splayDeg,
        public readonly ?float $gapM = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['count', 'splay_deg', 'gap_m'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("line_array: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        $count = $reader->requireInt('count');

        // One angle repeated, or one per gap. A J array is the second: [1, 2, 3, 5, 8] opens up as it goes
        // down, which is the whole point of writing them out.
        $splay = $reader->isList('splay_deg')
            ? $reader->numberList('splay_deg')
            : array_fill(0, max($count - 1, 0), $reader->optionalFloat('splay_deg', 0.0) ?? 0.0);

        return new self($count, array_values($splay), $reader->optionalFloat('gap_m'));
    }

    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        if ($this->count < 2) {
            return [new PlacementCopy([], [0.0, 0.0, 0.0], null, true, 0.0, false)];
        }

        $hinges = self::hinges(Outline::elevation($device, $rollDeg), $this->gapM ?? 0.0);

        // The chain starts at the tilt the top element actually hangs at, not at plumb. A hang is aimed as
        // one body, so `$pitchDeg` is already the whole array's down-tilt by the time this runs — and a
        // joint solved at 0° and 2° is simply not the joint that exists at 14° and 16°. Solving plumb and
        // then tilting each element about its own origin drove the rear corners of `flown-array`'s elements
        // 21.7 mm into one another, which is the interpenetration this class exists to avoid, arrived at
        // from the other direction.
        $position = [0.0, 0.0];
        $tilt = $pitchDeg;
        // The splay is accumulated on its own rather than subtracted back out of the tilt. Both would
        // describe the same hang, but `14.263 + 2 − 14.263` is 1.9999999999999982, and these increments end
        // up in a committed build plan where a gap the scene wrote as 2° has to read as 2°.
        $splay = 0.0;
        $copies = [new PlacementCopy([1], [0.0, 0.0, 0.0], null, true, 0.0, false)];

        for ($index = 1; $index < $this->count; ++$index) {
            $splay += $this->splayDeg[$index - 1];
            $next = $pitchDeg + $splay;
            $position = self::joint($position, $tilt, $next, $hinges);
            $tilt = $next;

            $copies[] = new PlacementCopy(
                [$index + 1],
                [0.0, $position[0], $position[1]],
                null,
                false,
                // The splay accumulated down to here and nothing else: the base tilt is the aim's to add,
                // and counting it twice would tilt every element further than it hangs.
                $splay,
                false,
            );
        }

        return $copies;
    }

    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $messages = [];

        if ($this->count < 1) {
            $messages[] = "line_array.count must be at least 1, got {$this->count}";
        }
        if (null !== $this->gapM && $this->gapM < 0.0) {
            $messages[] = "line_array.gap_m must not be negative, got {$this->gapM}";
        }
        if ($this->count > 1 && count($this->splayDeg) !== $this->count - 1) {
            $messages[] = sprintf(
                'line_array.splay_deg needs one angle per gap — %d for a count of %d, got %d',
                $this->count - 1,
                $this->count,
                count($this->splayDeg),
            );
        }

        if ([] !== $messages) {
            return $messages;
        }

        // The running tilt, not each step: twelve elements at 8° reach 88° between them, and the last of
        // them is on its nose whatever any single gap says. Counted from the hang's own down-tilt, because
        // that is where the first element already starts — 14° of aim plus 76° of splay is a cabinet on its
        // nose just as surely as 90° of splay is.
        $tilt = $pitchDeg;
        foreach ($this->splayDeg as $step) {
            $tilt += $step;
            if (abs($tilt) > self::MAX_TILT_DEG) {
                $messages[] = sprintf(
                    'line_array.splay_deg reaches %.1f° of tilt, which stands an element on its nose — '
                    .'the running tilt, aim included, has to stay within %.0f°',
                    $tilt,
                    self::MAX_TILT_DEG,
                );
                break;
            }
        }

        return $messages;
    }

    public function copyCount(): int
    {
        return $this->count;
    }

    public function kind(): string
    {
        return 'line_array';
    }

    /** An array tilts its elements; where the hang points is still the placement's or its aim's business. */
    public function decidesYaw(): bool
    {
        return false;
    }

    /**
     * Yes — and it is why a hang is aimed once rather than element by element. Let each element turn towards
     * the target on its own and they all converge on it, which cancels the splay out exactly.
     */
    public function decidesPitch(): bool
    {
        return true;
    }

    /**
     * Where the element below sits, given both tilts: the hinge that drops furthest.
     *
     * @param array{float, float} $above origin of the element above, in the elevation plane
     * @param list<array{float, float, float}> $hinges depth, bottom height, top height
     *
     * @return array{float, float}
     */
    private static function joint(array $above, float $tiltDeg, float $nextTiltDeg, array $hinges): array
    {
        $best = null;
        foreach ($hinges as [$y, $bottom, $top]) {
            $foot = self::turn([$y, $bottom], $tiltDeg);
            $head = self::turn([$y, $top], $nextTiltDeg);
            $candidate = [
                $above[0] + $foot[0] - $head[0],
                $above[1] + $foot[1] - $head[1],
            ];
            if (null === $best || $candidate[1] < $best[1]) {
                $best = $candidate;
            }
        }

        /** @var array{float, float} $best */
        return $best;
    }

    /**
     * A point turned by a down-tilt, in the elevation plane. Positive tilt drops the front, which sits at
     * negative depth — the same sense {@see Orientation} gives pitch.
     *
     * @param array{float, float} $point depth and height
     *
     * @return array{float, float}
     */
    private static function turn(array $point, float $tiltDeg): array
    {
        $tilt = deg2rad($tiltDeg);

        return [
            $point[0] * cos($tilt) - $point[1] * sin($tilt),
            $point[0] * sin($tilt) + $point[1] * cos($tilt),
        ];
    }

    /**
     * Each depth the silhouette has an edge at, with the height of its lowest and highest point.
     *
     * `$gap` is added to the top, which opens every joint by that much measured along the element's own
     * vertical — a working gap between flown boxes is air along the hang, not air front to back.
     *
     * @return list<array{float, float, float}>
     */
    private static function hinges(Outline $outline, float $gap): array
    {
        /** @var array<string, array{float, float, float}> $columns */
        $columns = [];
        foreach ($outline->points as [$y, $z]) {
            $key = (string) round($y / 1e-12);
            if (!isset($columns[$key])) {
                $columns[$key] = [$y, $z, $z];
                continue;
            }
            $columns[$key][1] = min($columns[$key][1], $z);
            $columns[$key][2] = max($columns[$key][2], $z);
        }

        $hinges = [];
        foreach ($columns as [$y, $bottom, $top]) {
            $hinges[] = [$y, $bottom, $top + $gap];
        }

        return $hinges;
    }
}
