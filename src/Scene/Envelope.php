<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * The width an {@see Alignment} has to fill, worked out from what is already standing.
 *
 * Separated from the compiler because it is the only part of aligning that looks at *other* placements, and
 * because the distinction it draws is worth a file of its own:
 *
 * * **`across`** is a placement's own extent — outer edge to outer edge.
 * * **`inside`** is the clear span between its outermost cabinets' facing edges.
 *
 * They are different objects and the gap between them is a whole cabinet on each side:
 * `full-rig-stereo`'s tops are 4.678 m across and 3.628 m inside. A tier standing *beside* another wants
 * the first; a fill going *between* its outer cabinets wants the second. Making one of them the other with
 * an inset would have been the obvious shortcut and would have dropped every fill on top of a top.
 */
final class Envelope
{
    /**
     * The width to fill, or the message saying why it cannot be worked out.
     *
     * @param array<string, list<PlacedDevice>> $placedById every cabinet of each placement resolved so far
     */
    public static function widthFor(Alignment $align, array $placedById): float|string
    {
        $reference = $align->reference();

        if (null === $reference) {
            /** @var float $width */
            $width = $align->widthM;
        } else {
            $cabinets = $placedById[$reference] ?? null;
            if (null === $cabinets) {
                // Same rule and the same wording as `on`, and it falls out of the same mechanism: a
                // placement is only in the map once it has been placed, so a forward reference and a
                // self-reference both land here, as does one naming a placement that failed to compile.
                return sprintf(
                    "align.%s: '%s' must name an earlier placement",
                    null !== $align->across ? 'across' : (null !== $align->inside ? 'inside' : 'outside'),
                    $reference,
                );
            }

            $width = null !== $align->across ? self::extentOf($cabinets) : self::freeSpanOf($cabinets);

            if ($width <= 0.0) {
                return sprintf(
                    "align.inside: '%s' has no gap between its outermost cabinets to fit anything into",
                    $reference,
                );
            }
        }

        $inset = $width - 2 * $align->insetM;
        if ($inset <= 0.0) {
            return sprintf('align.inset_m (%s) leaves nothing of the %.4f m envelope', $align->insetM, $width);
        }

        return $inset;
    }

    /**
     * How much x the placement an `outside` alignment names covers — the thing its cabinets have to clear.
     *
     * Separate from {@see widthFor} because the objective is different in kind, not just in number: that one
     * returns a width the cabinets must *span*, this returns an obstacle they must *stay clear of*. The inset is
     * deliberately not applied here — under `outside` the inset is the target of the solve rather than something
     * taken off an envelope, so folding it in would count it twice.
     *
     * Returned as a **span** rather than a width, because where it is matters as much as how big it is: a lone
     * fill sits on one side of it and has to clear that side, while a pair straddles it and has to clear both.
     * A width alone was enough only while the arrangement was assumed symmetric, and a single cabinet is not.
     *
     * **A SPAN IS THE POINT OF `outside`, NOT AN APPROXIMATION OF A COLLISION TEST**, and that is worth stating
     * because replacing it with one broke this outright. `outside` means "past somebody's outer faces", so a
     * cabinet has to clear the whole span; a hull-to-hull measure is a minimum over pairs, which a cabinet can
     * satisfy while sitting in a *gap* between two of the obstacle's cabinets, nested inside the span it was told
     * to stay out of. Three 0.5 m tops on a 1.5 m step went from spanning 4.74 m to 1.74 m that way, the fills
     * having cleared the middle top alone. What genuinely needs a hull measure is a fill that must merely not
     * touch its neighbour, and that is {@see Alignment::$clearOf}.
     *
     * @param array<string, list<PlacedDevice>> $placedById every cabinet of each placement resolved so far
     *
     * @return array{float, float}|string
     */
    public static function obstacleFor(Alignment $align, array $placedById): array|string
    {
        /** @var string $reference */
        $reference = $align->outside;
        $cabinets = $placedById[$reference] ?? null;

        if (null === $cabinets) {
            return sprintf("align.outside: '%s' must name an earlier placement", $reference);
        }

        $min = INF;
        $max = -INF;
        foreach ($cabinets as $cabinet) {
            $box = $cabinet->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return INF === $min ? [0.0, 0.0] : [$min, $max];
    }

    /**
     * The cabinets a `clear_of` alignment names — what its own cabinets must not come within `inset_m` of.
     *
     * The companion to {@see obstacleFor} and deliberately a different shape of answer, because the objective is
     * different: that one hands back a **span** to get past, this hands back the **cabinets** to keep off. See
     * {@see Alignment::$clearOf} for why a fill wants the second and a hand-written envelope wants the first.
     *
     * @param array<string, list<PlacedDevice>> $placedById every cabinet of each placement resolved so far
     *
     * @return list<PlacedDevice>|string
     */
    public static function cabinetsFor(Alignment $align, array $placedById): array|string
    {
        /** @var string $reference */
        $reference = $align->clearOf;
        $cabinets = $placedById[$reference] ?? null;

        if (null === $cabinets) {
            return sprintf("align.clear_of: '%s' must name an earlier placement", $reference);
        }

        return $cabinets;
    }

    /**
     * How much x a placement's cabinets cover, outer edge to outer edge.
     *
     * @param list<PlacedDevice> $cabinets
     */
    public static function extentOf(array $cabinets): float
    {
        $min = INF;
        $max = -INF;
        foreach ($cabinets as $cabinet) {
            $box = $cabinet->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return $max - $min;
    }

    /**
     * The clear span between a placement's outermost cabinets — the *inner* face of the leftmost to the
     * inner face of the rightmost, which is the room something else can stand in.
     *
     * Measured against the outermost cabinets only, on purpose. A tier's middle cabinets are further back
     * from this line than its outer ones and a fill sitting between the outer pair clears them anyway:
     * `full-rig-all-tops` leaves 20 mm to the outer top and 323 mm to the middle one.
     *
     * @param list<PlacedDevice> $cabinets
     */
    public static function freeSpanOf(array $cabinets): float
    {
        $leftEdge = INF;
        $rightEdge = -INF;
        $leftInner = -INF;
        $rightInner = INF;

        foreach ($cabinets as $cabinet) {
            $box = $cabinet->worldBox();
            if ($box['min'][0] < $leftEdge) {
                $leftEdge = $box['min'][0];
                $leftInner = $box['max'][0];
            }
            if ($box['max'][0] > $rightEdge) {
                $rightEdge = $box['max'][0];
                $rightInner = $box['min'][0];
            }
        }

        return $rightInner - $leftInner;
    }
}
