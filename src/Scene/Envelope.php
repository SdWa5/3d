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
     * @return float|string
     */
    public static function widthFor(Alignment $align, array $placedById): float|string
    {
        $reference = $align->reference();

        if ($reference === null) {
            /** @var float $width */
            $width = $align->widthM;
        } else {
            $cabinets = $placedById[$reference] ?? null;
            if ($cabinets === null) {
                // Same rule and the same wording as `on`, and it falls out of the same mechanism: a
                // placement is only in the map once it has been placed, so a forward reference and a
                // self-reference both land here, as does one naming a placement that failed to compile.
                return sprintf(
                    "align.%s: '%s' must name an earlier placement",
                    $align->across !== null ? 'across' : 'inside',
                    $reference,
                );
            }

            $width = $align->across !== null ? self::extentOf($cabinets) : self::freeSpanOf($cabinets);

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
