<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * The checks that run on **compiled placements**, after the solver has had its say.
 *
 * The sibling of {@see StackChecks} and the distinction between them is the data, not the severity. `StackChecks` reads
 * *tiers* — a solved stack, where "the row below" is a thing you can point at — and answers questions about bearing,
 * overhang and the height band. This reads {@see PlacedDevice}s, which are cabinets at world coordinates with their
 * rotations applied and no memory of which row they came from. Everything the compiler does between those two
 * representations is exactly what these checks exist to catch.
 *
 * **A scene the sweep rejects is no better than a scene the compiler rejects**, which is why this is not merely a
 * warning. `scene:stack` promises that "a generator that emits a scene the compiler rejects is worse than no
 * generator"; a rig that compiles and then fails `ShippedScenesTest` breaks the same promise one command later.
 */
final class PlacementChecks
{
    /**
     * How close a top face has to be to a bottom face to count as carrying it, and how far off the floor a cabinet has
     * to be before it is standing on something rather than on the ground.
     *
     * A millimetre. The mistakes this catches are a 151 mm gap and a cabinet 1.261 m up over open air, so the value is
     * two orders of magnitude below anything that has ever gone wrong — it is here to absorb the compiler's own
     * arithmetic rather than to make a judgement.
     */
    public const CONTACT_TOLERANCE_M = 0.001;

    /**
     * The first cabinet standing on nothing, or null when every one of them is over something.
     *
     * **The compiler does not catch this and the shipped-scene sweep does**, which is exactly the gap this closes.
     * A tops row narrower than the tier below it can still land off the end of it once the row is split into runs by
     * a stepped support — `all-speakers-three-turned` came out with a top 1.261 m up over open air, compiling
     * cleanly and failing the sweep.
     *
     * Deliberately the narrow question — is there something under it, in both plan axes — and not how far it would
     * tilt. Tilt is the solver's question, enforced by {@see Stability} against the tier it knows is below;
     * re-deriving it from world boxes alone needs "the tier immediately below", which they do not tell you.
     * The same reasoning is written out at length in `ShippedScenesTest`, and this is the same check from the same
     * data so the two cannot drift into disagreeing.
     *
     * @param list<PlacedDevice> $placed
     */
    public static function floating(array $placed): ?string
    {
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            if ($box['min'][2] < self::CONTACT_TOLERANCE_M || $entry->flyPoint !== null) {
                continue;
            }

            foreach ([0, 1] as $axis) {
                if (self::coveredFraction($entry, $placed, $axis) > 0.0) {
                    continue;
                }

                return sprintf(
                    'a %s would stand at %.3f m with nothing under it across %s — the compiler allows it and the '
                    .'shipped-scene sweep does not, so it is not one of the possibilities',
                    $entry->device->id,
                    $box['min'][2],
                    $axis === 0 ? 'x' : 'y',
                );
            }
        }

        return null;
    }

    /**
     * How much of a cabinet's extent along `$axis` has something level underneath it, as a fraction.
     *
     * Overlapping supports are merged rather than summed, so two neighbours a cabinet bridges count the span once.
     *
     * @param list<PlacedDevice> $placed
     */
    public static function coveredFraction(PlacedDevice $entry, array $placed, int $axis): float
    {
        $box = $entry->worldBox();
        $extent = $box['max'][$axis] - $box['min'][$axis];
        if ($extent <= 0.0) {
            return 0.0;
        }

        $spans = [];
        foreach ($placed as $other) {
            if ($other === $entry) {
                continue;
            }
            $under = $other->worldBox();
            if (abs($under['max'][2] - $box['min'][2]) > self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $overlap = [];
            foreach ([0, 1] as $plan) {
                $overlap[$plan] = min($box['max'][$plan], $under['max'][$plan])
                    - max($box['min'][$plan], $under['min'][$plan]);
            }
            if ($overlap[0] <= self::CONTACT_TOLERANCE_M || $overlap[1] <= self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $spans[] = [
                max($box['min'][$axis], $under['min'][$axis]),
                min($box['max'][$axis], $under['max'][$axis]),
            ];
        }

        usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $covered = 0.0;
        $reach = -INF;
        foreach ($spans as [$lo, $hi]) {
            $lo = max($lo, $reach);
            if ($hi > $lo) {
                $covered += $hi - $lo;
                $reach = $hi;
            }
        }

        return $covered / $extent;
    }
}
