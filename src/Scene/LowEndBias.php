<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Where the lowest-reaching cabinets belong: as central as the rig allows, or as low as it allows.
 *
 * **The eighth axis of the sweep, and the one that is an acoustic preference rather than a shape.** In every rig
 * this repository has produced, the lowest cabinets end up low because {@see \App\Spec\FillOrder::byFillOrder}
 * deals them first, and central only by accident: {@see StackMetrics::centred} puts the **tallest** segment in the
 * middle because that is what carries the row above — measured at 14 % bearing when it sits outboard instead —
 * and nothing anywhere aims a low-frequency cabinet at the centre line. So the SKRAMs come out in the middle of
 * the floor row and it reads as luck, because it is.
 *
 * **Both values are preferences, never gates.** Nothing is refused for missing one and no rig comes out narrower,
 * which is the same ruling the sub height band got: what stays a gate is whether the rig stands up. The bearing
 * rules keep their keys — re-keying `centred()` or `mixedBottomRow()` on frequency would break the reason they
 * exist — and this only ranks the arrangements they already accept.
 *
 * **Weighted, not strictly ordered.** The leader is worth {@see LEAD} times the follower, so it decides wherever
 * the two disagree and the follower still breaks a near-tie. Strict priority would consult the second measure
 * only on an exact tie of the first, and exact ties on metre floats are rare enough that it would decide nothing.
 *
 * **The key is frequency and the fallback is mass**, under the guard `byFillOrder()` already uses: frequency
 * decides only between two cabinets that both state a passband. Nine of ten of our speakers state none, so a rule
 * that ranked on absence would rank nearly everything on nothing. **Power decides which type is the lowest**, per
 * square metre of front, where both cabinets of a pair state it. See {@see LowEndCost::lowestType}.
 */
enum LowEndBias: string
{
    /**
     * What the solver has always done: the lowest-reaching type is dealt first and lands on the floor.
     *
     * **Declared first on purpose.** The deduplication keeps the earliest of two candidates with identical
     * geometry, so wherever the two values agree the file is named `low` and the rig keeps the name it had. Put
     * `central` first and every unchanged rig in the repository would be renamed to claim a preference it merely
     * happens to satisfy.
     */
    case Low = 'low';

    /**
     * Pull the lowest-reaching cabinets onto the centre line, and pay for it in height where that is the trade.
     */
    case Central = 'central';

    /**
     * What the leading measure is worth against the following one.
     *
     * Four, which is enough that the leader decides every disagreement worth calling one — the two measures are
     * both metres, and a quarter of a metre of the leader is not something the follower should be able to buy —
     * while leaving the follower to settle arrangements the leader cannot tell apart. The number is the same kind
     * of statement as {@see StackChecks::OUT_OF_BAND_PENALTY} and is worth the same scepticism: it is a weight
     * nobody measured, chosen to make one term dominant without making the other inert.
     */
    public const LEAD = 4.0;

    /**
     * What a metre of low-end height costs under `low`, in metres of target miss. The owner chose it on 2026-10-02.
     * Like {@see LEAD} it is a weight, small enough that height decides wherever the low end stays put. Unlike LEAD
     * it was measured against one alternative. At 0.1 eighteen `low` rigs missed their target by 13.5 m more in total
     * and two next-event rigs no longer fitted their room, where 0.02 left five rigs 3.3 m worse and thirteen better.
     */
    public const LOW_PRICE = 0.02;

    /**
     * The cost of an arrangement under this bias — lower is better.
     *
     * @param float $lowness metres the low-frequency mass sits above the floor
     * @param float $centrality metres it sits from the centre line
     */
    public function cost(float $lowness, float $centrality): float
    {
        // **`low` PAYS FOR HEIGHT TOO, BUT LITTLE.** It used to cost nothing, on the grounds that the fill deals the
        // lowest-reaching type first and it lands on the floor anyway. A pyramid orders for width and breaks that,
        // and with nothing to pay a rig 9 mm nearer the target stood one SKRAM in the top sub row of five. At
        // {@see LOW_PRICE} a metre of low-end height costs what 20 mm of target miss does, so height still decides
        // between rigs that keep the low end where it is.
        //
        // `central` prices distance from the centre line and pays for it in height.
        return self::Central === $this ? self::LEAD * $centrality + $lowness : self::LOW_PRICE * $lowness;
    }
}
