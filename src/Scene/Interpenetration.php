<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Whether any two placed cabinets are inside each other, and by how much.
 *
 * **A separating-axis test on the rotated shells, not on bounding boxes**, and that distinction is the whole reason
 * this is more than a few `min`/`max` comparisons. An aimed cabinet is toed in, so its axis-aligned box overlaps its
 * neighbour's long before the cabinets do — a bounding-box check on a properly built top row reports collisions
 * everywhere. And a Tecnare is a trapezoid, so once it is turned its outermost point is a *back* bottom corner rather
 * than a front one; nothing that reasons about widths gets that right.
 *
 * **This lives in `src/` because two callers need the same answer.** It began as private helpers inside
 * `ShippedScenesTest`, which is the right place to *check* shipped scenes and the wrong place to be the only copy:
 * `scene:stack` promises that "a generator that emits a scene the compiler rejects is worse than no generator", and a
 * scene the *sweep* rejects is no better — the failure just surfaces one command later. The generator now refuses a
 * candidate on the same test the sweep will apply to it, out of the same data, so the two cannot drift into
 * disagreeing.
 *
 * The compiler does not catch this on its own: `on:` only reads a top face, and nothing downstream compares two
 * finished placements.
 */
final class Interpenetration
{
    /**
     * The four corner indices of each face of a shell, in winding order.
     *
     * Face normals are tested before edge pairs because for boxes standing on a floor one of them almost always
     * separates, which keeps the 144 edge-pair crosses below from being reached at all.
     */
    private const FACES = [
        [0, 1, 3, 2], // front
        [4, 5, 7, 6], // back
        [0, 1, 5, 4], // left
        [2, 3, 7, 6], // right
        [0, 2, 6, 4], // bottom
        [1, 3, 7, 5], // top
    ];

    private const EDGES = [
        [0, 1], [2, 3], [4, 5], [6, 7],  // vertical
        [0, 2], [1, 3], [4, 6], [5, 7],  // across
        [0, 4], [1, 5], [2, 6], [3, 7],  // front to back
    ];

    /**
     * The worst separation between any two of these placements, and which two they were.
     *
     * Negative means they interpenetrate by that many metres, which is what a caller checks against its own
     * tolerance. Positive or zero means nothing touches. The pair is named so a message can say *which* cabinets.
     *
     * @param list<PlacedDevice> $placed
     * @return array{separation: float, pair: string}
     */
    public static function worst(array $placed): array
    {
        $hulls = array_map(static fn (PlacedDevice $entry): array => self::corners($entry), $placed);

        $worst = 0.0;
        $pair = '';
        foreach ($placed as $i => $a) {
            foreach ($placed as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                // Boxes that do not even share a bounding box cannot intersect, and skipping them is what keeps this
                // from being the slowest thing in the pipeline.
                if (!self::boxesTouch($a, $b)) {
                    continue;
                }

                $separation = self::separation($hulls[$i], $hulls[$j]);
                if ($separation < $worst) {
                    $worst = $separation;
                    $pair = sprintf('%s and %s', $a->placementId, $b->placementId);
                }
            }
        }

        return ['separation' => $worst, 'pair' => $pair];
    }

    /**
     * A placement's eight shell corners in world space.
     *
     * @return list<array{float, float, float}>
     */
    private static function corners(PlacedDevice $entry): array
    {
        $origin = $entry->liftedPosition();

        $corners = [];
        foreach ($entry->device->shellCorners() as $corner) {
            $rotated = $entry->orientation->apply($corner);
            $corners[] = [
                $origin[0] + $rotated[0],
                $origin[1] + $rotated[1],
                $origin[2] + $rotated[2],
            ];
        }

        return $corners;
    }

    private static function boxesTouch(PlacedDevice $a, PlacedDevice $b): bool
    {
        $boxA = $a->worldBox();
        $boxB = $b->worldBox();
        for ($axis = 0; $axis < 3; ++$axis) {
            if ($boxA['min'][$axis] - $boxB['max'][$axis] > 0.0 || $boxB['min'][$axis] - $boxA['max'][$axis] > 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * The widest gap along any separating axis — negative when the two hulls overlap.
     *
     * @param list<array{float, float, float}> $a
     * @param list<array{float, float, float}> $b
     */
    private static function separation(array $a, array $b): float
    {
        $widest = -INF;
        foreach (self::axes($a, $b) as $axis) {
            $length = sqrt($axis[0] ** 2 + $axis[1] ** 2 + $axis[2] ** 2);
            if ($length < 1e-9) {
                // Parallel edges or a degenerate face give no axis to test.
                continue;
            }

            $unit = [$axis[0] / $length, $axis[1] / $length, $axis[2] / $length];
            [$aMin, $aMax] = self::project($a, $unit);
            [$bMin, $bMax] = self::project($b, $unit);

            $gap = max($bMin - $aMax, $aMin - $bMax);
            if ($gap > $widest) {
                $widest = $gap;
            }
            if ($widest > 0.0) {
                // One separating axis is proof enough; the rest cannot make them intersect.
                break;
            }
        }

        return $widest;
    }

    /**
     * Every axis worth testing: both hulls' face normals, then every cross product of their edge pairs.
     *
     * @param list<array{float, float, float}> $a
     * @param list<array{float, float, float}> $b
     * @return list<array{float, float, float}>
     */
    private static function axes(array $a, array $b): array
    {
        $normals = [];
        $edges = [];
        foreach ([$a, $b] as $hull) {
            foreach (self::FACES as $face) {
                $normals[] = self::cross(
                    self::minus($hull[$face[1]], $hull[$face[0]]),
                    self::minus($hull[$face[2]], $hull[$face[0]]),
                );
            }
            $own = [];
            foreach (self::EDGES as [$from, $to]) {
                $own[] = self::minus($hull[$to], $hull[$from]);
            }
            $edges[] = $own;
        }

        $axes = $normals;
        foreach ($edges[0] as $one) {
            foreach ($edges[1] as $other) {
                $axes[] = self::cross($one, $other);
            }
        }

        return $axes;
    }

    /**
     * @param list<array{float, float, float}> $hull
     * @param array{float, float, float} $axis
     * @return array{float, float}
     */
    private static function project(array $hull, array $axis): array
    {
        $min = INF;
        $max = -INF;
        foreach ($hull as $point) {
            $along = $point[0] * $axis[0] + $point[1] * $axis[1] + $point[2] * $axis[2];
            $min = min($min, $along);
            $max = max($max, $along);
        }

        return [$min, $max];
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
     * @return array{float, float, float}
     */
    private static function minus(array $a, array $b): array
    {
        return [$a[0] - $b[0], $a[1] - $b[1], $a[2] - $b[2]];
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
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
}
