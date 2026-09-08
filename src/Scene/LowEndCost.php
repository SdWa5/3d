<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * How low and how central a solved stack puts its lowest-reaching cabinets — the two measures
 * {@see LowEndBias} weighs against each other.
 *
 * **Both are a weighted centroid, and this repository already had one.** {@see Stability::tips} computes
 * `moment / mass` over each run's centre to decide whether a row topples. The shape is identical here and only
 * the weight changes: what matters is not how heavy a cabinet is but how low it reaches, so the weight is a
 * frequency key. That is the one idea in this class; everything else is arithmetic.
 *
 * **The weight has to fall back to mass and the guard is not optional.** `Passband::orderingLowHz()` exists for
 * nine of our thirty-eight devices, so a weight taken straight from it would read a missing passband as nothing
 * and put the whole rig's centroid on the three cabinets that happen to state one. {@see weightOf} therefore
 * scores a cabinet on its passband where it has one and on mass where it does not, normalised so the two cannot
 * be compared against each other — exactly the guard {@see \App\Spec\FillOrder::byFillOrder} states in words:
 * **frequency decides only between two cabinets that both state one.**
 */
final class LowEndCost
{
    /**
     * Below this the cabinet counts as low end at all. 120 Hz is where a sub stops being a sub in every crossover
     * anybody here runs, and the measure is about the low end rather than about the whole rig.
     */
    private const LOW_END_HZ = 120.0;

    /**
     * How high the low-frequency mass sits above the floor, in metres.
     *
     * Zero is the ideal — every low cabinet on the ground — and it rises as the rig puts them further up. The
     * height used is each row's own middle rather than its base, because a cabinet occupies its height and a
     * measure taken at the floor of a 1.2 m row would call a rolled SBH and an upright one the same.
     *
     * @param list<Tier> $tiers
     * @param ?string $onlyId measure this device alone; null weighs every sub by how low it reaches
     */
    public static function lowness(array $tiers, Stack $stack, ?string $onlyId = null): float
    {
        $mass = 0.0;
        $moment = 0.0;
        $base = 0.0;

        foreach ($tiers as $tier) {
            $height = $tier->heightM();
            foreach ($tier->segments as $segment) {
                [$device, $count] = $segment;
                $weight = $count * self::weightOf($device, $onlyId);
                $mass += $weight;
                $moment += $weight * ($base + $height / 2);
            }
            $base += $tier->heightStepM();
        }

        return $mass <= 0.0 ? 0.0 : $moment / $mass;
    }

    /**
     * How far that same mass sits from the centre line, in metres.
     *
     * **Absolute distance, summed rather than signed.** A signed centroid is nearly zero for any mirrored rig —
     * two low cabinets at the outer ends cancel each other out and score as perfectly central — which is the
     * opposite of what the measure is for. Each segment contributes its own distance, so a pair on the flanks
     * costs what it actually is.
     *
     * The centre line is the stack's own, which is what `seats()` measures from. For a rig of several stacks that
     * is the right answer for `stereo` and the incomplete half of it for `center` and `block`, where GEO-14 says
     * central means the *rig's* centre line. That half is filed rather than guessed at: dealing a device to an
     * inner stack is a decision {@see StackDeal} makes before any of this is solved.
     *
     * @param list<Tier> $tiers
     */
    public static function centrality(array $tiers, Stack $stack, ?string $onlyId = null): float
    {
        $mass = 0.0;
        $moment = 0.0;

        foreach ($tiers as $tier) {
            foreach ($tier->seats($stack->gapM) as [$device, $count, $centreX, $roll]) {
                $own = self::weightOf($device, $onlyId);
                if ($own <= 0.0) {
                    continue;
                }

                // **PER CABINET, NOT PER SEGMENT, AND THE DIFFERENCE IS NOT SUBTLE.** `seats()` reports a run at
                // its own centre, so two wall basses side by side read as one lump sitting wherever their midpoint
                // is — which scored an arrangement with the pair shoved up a row and off to one side as *more*
                // central than the same pair straddling the centre line on the floor. Expanded, each cabinet
                // carries its own distance and a pair either side of the middle costs what it actually is.
                $pitch = RolledBox::widthOf($device, $roll) + $stack->gapM;
                for ($i = 0; $i < $count; ++$i) {
                    $x = $centreX + ($i - ($count - 1) / 2) * $pitch;
                    $mass += $own;
                    $moment += $own * abs($x);
                }
            }
        }

        return $mass <= 0.0 ? 0.0 : $moment / $mass;
    }

    /**
     * The id of the lowest-reaching cabinet in an inventory, or null when nothing in it is low end at all.
     *
     * **What it is for is the one candidate the fill would otherwise never offer.** The dealer takes as many of a
     * type as the row budget allows, so two SKRAMs go side by side in one row and no arrangement in the whole
     * search has one of them above the other. That is the arrangement `central` exists to pick — each cabinet on
     * the centre line rather than the pair straddling it — so it has to be generated before it can be preferred.
     *
     * Ties keep the earlier entry, which is the fill order's own answer to "which of these is lower".
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    public static function lowestType(array $inventory): ?string
    {
        $best = null;
        $score = 0.0;
        foreach ($inventory as [$device, $count]) {
            $own = self::weightOf($device);
            if ($count > 1 && $own > $score) {
                $score = $own;
                $best = $device->id;
            }
        }

        return $best;
    }

    /**
     * How much this cabinet counts as low end, from 0 for a top to 1 for the lowest-reaching sub in the library.
     *
     * **Frequency where it is stated and mass where it is not**, and the two are deliberately kept on one scale
     * without being mixed: a cabinet with a passband under {@see LOW_END_HZ} scores on how far under it reaches,
     * and one without scores on mass alone, which is what `byFillOrder()` falls back to for the same reason. A
     * top scores zero either way and drops out of both measures, which is right — where a top sits is `topRow()`'s
     * question and it answers it on coverage.
     */
    private static function weightOf(DeviceSpec $device, ?string $onlyId = null): float
    {
        if ('sub' !== $device->subtype) {
            return 0.0;
        }

        // **NAMED, THE MEASURE IS ABOUT ONE TYPE AND NOTHING ELSE, AND THAT IS THE POINT.** A centroid over every
        // sub in the rig is dominated by whatever there are most of: twelve Flexys against two SKRAMs move it by
        // a few centimetres however the SKRAMs are placed, so the measure could not see the arrangement it exists
        // to choose. "The low end central" is a statement about the lowest-reaching cabinets, not about the
        // average of the whole wall. {@see lowestType} names which one.
        if (null !== $onlyId) {
            return $device->id === $onlyId ? 1.0 : 0.0;
        }

        $low = $device->passband?->orderingLowHz();
        if (null !== $low && $low > 0.0 && $low < self::LOW_END_HZ) {
            // 20 Hz scores 1.0 and 120 Hz scores 0. Linear, because nothing here needs more resolution than
            // "reaches lower than the other one" and a decibel-shaped curve would be a claim about hearing.
            return max(0.0, min(1.0, (self::LOW_END_HZ - $low) / (self::LOW_END_HZ - 20.0)));
        }

        // No passband: mass, normalised against the heaviest cabinet anybody here owns so the two paths land on
        // the same 0..1 scale. 220 kg is the GMSS wall bass, and a heavier cabinet simply saturates at 1.
        return max(0.0, min(1.0, $device->weightKg / 220.0));
    }
}
