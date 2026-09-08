<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * A cabinet's silhouette in one plane, as a convex hull — the shape every contact solve works on.
 *
 * Two cabinets touch or they do not, and the answer depends on the outline of the *turned* cabinet
 * rather than on its nominal dimensions. Three things move it and none is cosmetic:
 *
 * * the taper — `back_width_m` narrower than `width` is what makes a cluster of tops possible at all;
 * * `appearance.grille.inset_m`, a **full-width** slab across the very front, so the taper only runs
 *   over `depth − inset`. Ignoring it, a Tecnare's flush arc comes out at 16.95° when the built meshes
 *   need 17.35°, and they overlap by 3.5 mm;
 * * rotation. Down-tilt swings the front-top edge forward into a full-width prow — ignoring *that*, a
 *   concave cluster at 4.4° of tilt interpenetrates by 20.6 mm. And roll rotates the taper out of the
 *   plan view entirely: on its side, a Tecnare's plan outline is a plain 0.960 × 0.520 rectangle.
 *
 * Hulling them into one convex polygon is what lets a solve be written once. It also matters
 * arithmetically: a quarter turn collapses twelve corners onto six distinct plan points, and duplicate
 * vertices break any walk over the edges.
 *
 * Two projections, because contact happens in two planes:
 *
 * * {@see plan} — looking down. Cabinets meet side to side; this is what an arc, a row and a lattice
 *   solve on.
 * * {@see elevation} — looking from the side. Cabinets meet top to bottom; this is what a line array
 *   solves on. Pitch is deliberately not a parameter there, because an array's whole geometry *is* the
 *   pitch and each element carries its own.
 */
final class Outline
{
    /**
     * @param list<array{float, float}> $points counter-clockwise convex hull, no duplicates and no
     *                                          collinear runs
     */
    private function __construct(public readonly array $points)
    {
    }

    /**
     * Plan view: the cabinet's contact corners rolled in their own frame and then tilted, flattened
     * onto XY.
     *
     * The rotation is `Rx(pitch)·Ry(roll)`, which is {@see Orientation}'s order minus the yaw — and the
     * yaw is exactly what the arc is solving for. Solving contact on any other rotation would be solving
     * it for a cabinet that never gets built.
     */
    public static function plan(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): self
    {
        $pitch = deg2rad($pitchDeg);
        $roll = deg2rad($rollDeg);

        $points = [];
        foreach ($device->contactCorners() as [$x, $y, $z]) {
            $points[] = [
                $x * cos($roll) + $z * sin($roll),
                $y * cos($pitch) + $x * sin($roll) * sin($pitch) - $z * cos($roll) * sin($pitch),
            ];
        }

        return new self(self::hull($points));
    }

    /**
     * Side view: the same corners rolled in their own frame, flattened onto YZ, with −Y still the
     * front and +Z still up.
     */
    public static function elevation(DeviceSpec $device, float $rollDeg = 0.0): self
    {
        $roll = deg2rad($rollDeg);

        $points = [];
        foreach ($device->contactCorners() as [$x, $y, $z]) {
            $points[] = [$y, -$x * sin($roll) + $z * cos($roll)];
        }

        return new self(self::hull($points));
    }

    /**
     * How much room the outline takes along the first axis — the spacing two neighbours need to stand
     * side by side and just touch.
     *
     * This is the number `width` is usually a stand-in for, and it stops being one as soon as anything
     * is turned: it is 0.500 m for an upright Tecnare, 0.960 m for one on its side, and it counts the
     * grille frame in both cases.
     */
    public function widthX(): float
    {
        $min = INF;
        $max = -INF;
        foreach ($this->points as [$x]) {
            $min = min($min, $x);
            $max = max($max, $x);
        }

        return $max - $min;
    }

    /**
     * The same outline with `$gap` of air added along the first axis — half of it on each side.
     *
     * A working gap is a property of how cabinets are actually stacked, not of the cabinet, and every
     * sub row in this repository has one: they all state a 0.611 m step for a 0.591 m cabinet. Growing
     * the outline rather than adding the gap to a spacing afterwards means one definition serves a
     * straight row and a splayed seam alike — the seam simply opens by the gap measured across it.
     */
    public function dilatedX(float $gap): self
    {
        if ($gap <= 0.0) {
            return $this;
        }

        $half = $gap / 2;
        $points = [];
        foreach ($this->points as [$x, $y]) {
            $points[] = [$x - $half, $y];
            $points[] = [$x + $half, $y];
        }

        return new self(self::hull($points));
    }

    /**
     * The hull's edges as point pairs, counter-clockwise.
     *
     * @return list<array{array{float, float}, array{float, float}}>
     */
    public function edges(): array
    {
        $count = count($this->points);
        if ($count < 2) {
            return [];
        }

        $edges = [];
        for ($index = 0; $index < $count; ++$index) {
            $edges[] = [$this->points[$index], $this->points[($index + 1) % $count]];
        }

        return $edges;
    }

    /**
     * The edges facing one side or the other along the first axis — a cabinet's left and right flanks,
     * which are the faces two neighbours in a row or an arc present to each other.
     *
     * On a counter-clockwise hull an edge `p → q` has outward normal `(qy − py, px − qx)`, so the sign
     * of `qy − py` is which way the face looks.
     *
     * @return list<array{array{float, float}, array{float, float}}>
     */
    public function facingEdges(float $sign): array
    {
        $edges = [];
        foreach ($this->edges() as [$p, $q]) {
            if (($q[1] - $p[1]) * $sign > 1e-12) {
                $edges[] = [$p, $q];
            }
        }

        return $edges;
    }

    /**
     * The grid coordinates are snapped to before hulling: one picometre.
     *
     * Not a fudge factor — it is what makes "the same point" decidable. A quarter turn sends a corner to
     * `x = 4.6e-17` rather than to zero, because `cos(270°)` is not exactly zero in binary, and four
     * corners that are physically one point arrive as four different floats. Comparing with a tolerance
     * instead does not work: the near-zero values then do not sort next to each other, so a sequential
     * pass cannot see that they are duplicates, and the hull comes out degenerate. Snapping decides it
     * once, before anything depends on the answer.
     *
     * It only ever decides *which* points are the same point. The hull keeps the original coordinates of
     * the ones it retains, so no angle derived from it inherits the grid's coarseness — otherwise a
     * picometre grid would move a flush splay read off a few centimetres of taper by about 1e-13 degrees,
     * which is nothing physically and still a spurious diff in a committed build plan every time.
     *
     * The size needs only to sit well above the rounding it absorbs, a few times 1e-16 on coordinates of
     * this size, and well below the millimetre that cabinet dimensions are actually known to.
     */
    private const GRID_M = 1e-12;

    /**
     * Andrew's monotone chain. Collinear points are dropped, so a quarter-turned cabinet's twelve
     * coincident plan points come out as the four corners of a rectangle.
     *
     * @param list<array{float, float}> $points
     *
     * @return list<array{float, float}>
     */
    private static function hull(array $points): array
    {
        // Sorted and deduplicated on the grid, but carrying the original coordinate along, so the hull
        // that comes out is made of points the cabinet actually has.
        $keyed = [];
        foreach ($points as $point) {
            $keyed[] = [
                round($point[0] / self::GRID_M) + 0.0,
                round($point[1] / self::GRID_M) + 0.0,
                $point,
            ];
        }

        usort($keyed, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $unique = [];
        $previous = null;
        foreach ($keyed as [$keyX, $keyY, $point]) {
            if (null === $previous || $previous[0] !== $keyX || $previous[1] !== $keyY) {
                $unique[] = $point;
            }
            $previous = [$keyX, $keyY];
        }
        if (count($unique) < 3) {
            return $unique;
        }

        $build = static function (array $ordered): array {
            $chain = [];
            foreach ($ordered as $point) {
                while (count($chain) >= 2 && self::cross($chain[count($chain) - 2], $chain[count($chain) - 1], $point) <= 1e-12) {
                    array_pop($chain);
                }
                $chain[] = $point;
            }

            return $chain;
        };

        $lower = $build($unique);
        $upper = $build(array_reverse($unique));

        // Each chain repeats the other's endpoints, so both are dropped once.
        return array_values(array_merge(
            array_slice($lower, 0, -1),
            array_slice($upper, 0, -1),
        ));
    }

    /**
     * @param array{float, float} $origin
     * @param array{float, float} $a
     * @param array{float, float} $b
     */
    private static function cross(array $origin, array $a, array $b): float
    {
        return ($a[0] - $origin[0]) * ($b[1] - $origin[1]) - ($a[1] - $origin[1]) * ($b[0] - $origin[0]);
    }
}
