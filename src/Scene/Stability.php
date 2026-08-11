<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Whether a row stays where it is put — **weighed**, rather than measured as a fraction of a footprint.
 *
 * The rule this replaces was a proxy: refuse a tier more than half its outer cabinet off the tier below. It turns
 * out to *be* the per-cabinet centre-of-mass rule — the outer Flexy of a 2.424 m row on an 1.832 m one has its
 * mass 916.5 mm out against a support edge at 916.0 — which is why that arrangement was refused by half a
 * millimetre. The arithmetic was right. The body was wrong.
 *
 * **A row is one body.** Its cabinets touch, they are strapped, and a crew moves them as a wall, so the question
 * is whether the *row's* combined mass sits over what carries it. By that question the row stands, with its end
 * cabinets reaching past the support and held by the neighbours they lean on. `weight_kg` is required on every
 * spec, so this is derived from what the repository already knows rather than from a constant somebody picked.
 *
 * What a row-level test cannot see is a cabinet **lifted onto a shoulder** above its neighbours, standing on a
 * corner. Nothing here catches that and nothing here should: it is {@see Gravity} reporting a settle angle, which
 * separates the two on their own numbers — 1.7° for a 19 mm step against 19.5° for a 163 mm shoulder — where no
 * fraction of a footprint could tell them apart at all.
 */
final class Stability
{
    /**
     * How far out of level a cabinet may come to rest before it is standing on a corner rather than on a shim.
     *
     * Five degrees, and the cases it separates are nowhere near it: a Flexy left 19 mm proud over a 630 mm
     * overhang settles at 1.7°, a 2-way on a 163 mm shoulder over 460 mm at 19.5°. An order of magnitude apart, so
     * this is a boundary rather than a tuning — anything from 3° to 10° gives the same answer on every
     * arrangement this inventory can build.
     */
    public const MAX_SETTLE_DEG = 5.0;

    /**
     * Whether a tier's combined mass falls outside the span of what it stands on.
     *
     * Weighted by `weight_kg`, against the union of the tier below. A tier on the floor cannot tip, and a tier
     * with nothing under it at all is {@see StackChecks}' own error rather than a question about balance.
     *
     * @param list<array{device: \App\Spec\DeviceSpec, count: int, lo: float, hi: float, ...}> $runs one tier
     * @param list<array{lo: float, hi: float, ...}> $below the tier under it
     */
    public static function tips(array $runs, array $below): bool
    {
        if ($below === [] || $runs === []) {
            return false;
        }

        $mass = 0.0;
        $moment = 0.0;
        foreach ($runs as $run) {
            $own = $run['count'] * $run['device']->weightKg;
            $mass += $own;
            $moment += $own * ($run['lo'] + $run['hi']) / 2;
        }
        if ($mass <= 0.0) {
            return false;
        }

        $low = INF;
        $high = -INF;
        foreach ($below as $support) {
            $low = min($low, $support['lo']);
            $high = max($high, $support['hi']);
        }

        $centre = $moment / $mass;

        return $centre < $low || $centre > $high;
    }
}
