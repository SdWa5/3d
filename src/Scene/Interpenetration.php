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
        $worst = 0.0;
        $pair = '';
        foreach (self::overlapping($placed, 0.0) as [$a, $b, $separation]) {
            if ($separation < $worst) {
                $worst = $separation;
                $pair = sprintf('%s and %s', $a, $b);
            }
        }

        return ['separation' => $worst, 'pair' => $pair];
    }

    /**
     * Every pair that is inside the other by more than `$tolerance`, named rather than described.
     *
     * **The same sweep {@see worst} makes, answering with identities instead of with the single deepest pair.**
     * A rig where three cabinets bury each other has one worst pair and three cabinets worth marking, and a
     * picture that reddened two of the three would read as a diagnosis rather than as a partial one. `worst` is
     * now formatted from this, so the sentence and the marking cannot drift apart.
     *
     * @param list<PlacedDevice> $placed
     * @return list<Fault>
     */
    public static function faults(array $placed, float $tolerance): array
    {
        $faults = [];
        foreach (self::overlapping($placed, $tolerance) as [$a, $b, $separation]) {
            $faults[] = new Fault(
                Fault::INTERPENETRATION,
                [$a, $b],
                sprintf('%s and %s are %.4f m inside each other', $a, $b, -$separation),
            );
        }

        return $faults;
    }

    /**
     * Every intersecting pair as `[placementId, placementId, separation]`, most buried first.
     *
     * @param list<PlacedDevice> $placed
     * @return list<array{string, string, float}>
     */
    private static function overlapping(array $placed, float $tolerance): array
    {
        $hulls = array_map(static fn (PlacedDevice $entry): array => self::corners($entry), $placed);

        $found = [];
        foreach ($placed as $i => $a) {
            foreach ($placed as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                // **A HOLLOW SHELL CONTAINS RATHER THAN COLLIDES**, which is the one exemption in this sweep. A
                // load bay is drawn as a cage so the cabinets can be seen inside it, so every unit of a pack is
                // inside its vehicle's box on purpose — the sweep called the first one 1.09 m inside the Movano and
                // `scene:build` would have caged the whole load in red. See {@see \App\Spec\Shape::isHollow} for why
                // only the bay qualifies and a truss does not.
                //
                // **It is the pair that is skipped, not the check that is weakened.** Two cabinets in the same place
                // inside a bay are still reported, which is what makes a pack scene worth sweeping at all. What is
                // *not* checked anywhere is whether the load sticks out through a wall — that is a containment
                // question rather than a collision one, and {@see \App\Load\PackLayout} keeps the units inside the
                // bay it was given while {@see \App\Load\PackSceneWriter} names whatever stands proud of it.
                if ($a->device->shape->isHollow() || $b->device->shape->isHollow()) {
                    continue;
                }
                // Boxes that do not even share a bounding box cannot intersect, and skipping them is what keeps this
                // from being the slowest thing in the pipeline.
                if (!self::boxesTouch($a, $b)) {
                    continue;
                }

                $separation = self::separation($hulls[$i], $hulls[$j]);
                if ($separation < -$tolerance) {
                    $found[] = [$a->placementId, $b->placementId, $separation];
                }
            }
        }

        usort($found, static fn (array $x, array $y): int => $x[2] <=> $y[2]);

        return $found;
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
    /**
     * The narrowest air gap between any two x-adjacent cabinets, positive when there is room.
     *
     * **The companion to {@see worst}, and the two are not interchangeable.** `worst()` answers "how far inside each
     * other are they", starts at zero and only ever goes negative, which makes it a fine test and a useless metric:
     * nothing that clamps at zero can tell a solver how much air it has bought, so a bisection chasing a positive gap
     * never finds an upper bracket. This reads the same geometry the other way round and stays signed throughout, so it
     * is continuous through zero and a solve can chase it.
     *
     * **Measured on the shells, never on their bounding boxes**, and that is not a refinement but the whole
     * correctness of it. A bounding box grows by `depth × sin θ` as a cabinet toes in — about 26 mm on a 0.520 m deep
     * Tecnare at a few degrees — while a *tapered* cabinet's outermost point is its back bottom corner and moves the
     * other way: three aimed Tecnares span 1.5137 m where their nominal widths and gaps would give 1.540. An
     * axis-aligned measure therefore invents overlaps that do not exist, and reading it as "conservative" is wrong in
     * direction rather than merely imprecise.
     *
     * Adjacent pairs only, because a row is a sequence and no cabinet can be closer to a stranger than to its
     * neighbour. Sorted on the box because that is only deciding *who* is adjacent, which no rotation changes.
     *
     * @param list<PlacedDevice> $placed
     */
    public static function narrowestGap(array $placed): float
    {
        if (count($placed) < 2) {
            return INF;
        }

        $sorted = $placed;
        usort(
            $sorted,
            static fn (PlacedDevice $a, PlacedDevice $b): int
                => $a->worldBox()['min'][0] <=> $b->worldBox()['min'][0],
        );

        $narrowest = INF;
        for ($i = 1; $i < count($sorted); ++$i) {
            $narrowest = min(
                $narrowest,
                self::distance(self::corners($sorted[$i - 1]), self::corners($sorted[$i])),
            );
        }

        return $narrowest;
    }

    /**
     * The tightest gap between any cabinet of one group and any cabinet of another, positive when there is room.
     *
     * **The measure an `outside` clearance solve needs, and the reason it is not an x comparison.** That solve used to
     * reduce both sides to an x interval and subtract them, which over-demands clearance rather than merely
     * approximating it: two aimed cabinets whose x extents overlap **nest in y and never touch**, because a toed-in
     * trapezoid's outermost point is a back bottom corner and swings behind its neighbour rather than into it. Three
     * aimed Tecnares span 1.5137 m where their nominal widths and gaps give 1.540, and an x-interval solve pushes a
     * fill out until intervals that were never in conflict stop overlapping.
     *
     * Signed throughout and negative while the two overlap, which is what a bisection needs — see {@see narrowestGap}
     * for why {@see worst} cannot serve as an objective.
     *
     * **This reads y and z as well as x, and that is inherent rather than incidental.** Nesting *is* a y effect, so a
     * measure blind to y cannot see it. One consequence is worth knowing: two cabinets at different heights are now
     * correctly reported as clear, so a fill that gravity seated onto a lower shoulder is no longer pushed away from a
     * neighbour it cannot reach.
     *
     * @param list<PlacedDevice> $mine
     * @param list<PlacedDevice> $theirs
     */
    public static function gapBetween(array $mine, array $theirs): float
    {
        if ($mine === [] || $theirs === []) {
            return INF;
        }

        $hulls = array_map(static fn (PlacedDevice $entry): array => self::corners($entry), $theirs);

        $tightest = INF;
        foreach ($mine as $cabinet) {
            $hull = self::corners($cabinet);
            foreach ($hulls as $other) {
                $tightest = min($tightest, self::distance($hull, $other));
            }
        }

        return $tightest;
    }

    /**
     * The separating distance between two hulls across **every** axis, with no early exit.
     *
     * {@see separation} stops at the first axis that separates the pair, which is right for a yes-or-no answer and
     * wrong for a measurement: the positive number it returns is whichever axis happened to separate them rather than
     * the narrowest gap between them. This takes the maximum over all of them, which is the distance itself.
     *
     * @param list<array{float, float, float}> $a
     * @param list<array{float, float, float}> $b
     */
    private static function distance(array $a, array $b): float
    {
        $widest = -INF;
        foreach (self::axes($a, $b) as $axis) {
            $length = sqrt($axis[0] ** 2 + $axis[1] ** 2 + $axis[2] ** 2);
            if ($length < 1e-9) {
                continue;
            }

            $unit = [$axis[0] / $length, $axis[1] / $length, $axis[2] / $length];
            [$aMin, $aMax] = self::project($a, $unit);
            [$bMin, $bMax] = self::project($b, $unit);
            $widest = max($widest, max($bMin - $aMax, $aMin - $bMax));
        }

        return $widest;
    }

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
