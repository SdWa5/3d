<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Cabinets held back from their own row to be flanked into the row above.
 *
 * A lift is what makes a tapering top possible when the inventory does not taper: eight Flexys at four per row
 * are two square rows, and lifting two of them puts a 2.444 m row under a 1.222 m one. The reservation happens
 * **before any tier exists**, which is why {@see StackMetrics::pyramidCeiling} cannot cap it — there is nothing to measure a
 * proposed row against yet, so the flank is reserved on the inventory and the shape rules judge the result.
 *
 * {@see reserveLifts} is the entry point and the other three are its arithmetic: which pairs can be lifted at
 * all, what the row they leave behind then measures, and how wide the flank on top comes out.
 *
 * Depends on {@see StackMetrics} alone. {@see StackTops::widthAbove} calls in here rather than the reverse,
 * because the two have to agree on what a lifted flank measures and only one of them may own the answer.
 */
final class StackLifts
{
    /**
     * Cabinets held back from a lower device's rows to stand either side of the tier above it.
     *
     * **This is {@see StackMix::mixedBottomRow}'s rule read one word differently.** That one mixes "only to remove an
     * inverted step" — a *support* rule, which is why it can only ever fire on the bottom row: the question it
     * asks is whether this row would be narrower than the row coming to stand on it. Asking instead whether it
     * is narrower than the row it stands *on* is the same mechanism pointed the other way, and it closes the
     * step that support alone does not care about. Four Achenbachs on six Flexys is 2.460 m on 3.646 m —
     * perfectly carried, and a 593 mm shoulder on each side. One Flexy either side of them makes it 3.682 m and
     * the wall face flat.
     *
     * **The reservation has to happen before the source's own rows are built**, which is why this is a pass of
     * its own rather than a decision made in the loop: by the time the fill reaches the Achenbachs every Flexy
     * is already spoken for, and there is nothing left to borrow.
     *
     * Two devices at a time, each promotion measured against what is left of the source afterwards — taking a
     * pair is not free, it comes out of the row below and can cost that row a whole tier.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{array<int, array{DeviceSpec, int}>, list<array{DeviceSpec, int}>} lifts by target index,
     *                                                                                 and what is left to fill rows with
     */
    public static function reserveLifts(array $remaining, Stack $stack, RowBudget $budget): array
    {
        $lifts = [];

        foreach (array_keys($remaining) as $source) {
            [$device, $count] = $remaining[$source];
            // A device already being flanked has no rows of its own left to lend from.
            if ($count < 1 || 'sub' !== $device->subtype || isset($lifts[$source])) {
                continue;
            }

            $lift = self::liftAbove($remaining, $source, $stack, $budget);
            if (null === $lift) {
                continue;
            }

            [$target, $pairs] = $lift;
            $lifts[$target] = [$device, $pairs];
            $remaining[$source][1] -= 2 * $pairs;
        }

        return [$lifts, $remaining];
    }

    /**
     * The tier standing on `$source` that wants flanking, and how many pairs it takes — or null for none.
     *
     * Split out because **two callers have to agree**: the reservation pass above, and {@see StackTops::widthAbove}, which
     * decides how wide the mixed bottom row may grow. That one asks how wide the row above the bottom will be,
     * and the answer changes if some of those cabinets are about to be lifted a tier — which is exactly the
     * coupling that kept the flat wall out of reach. Left to itself the bottom row grew to 3 pairs and 4.906 m,
     * because the eight Flexys left over came to 4.868 m in one row and anything narrower would have been
     * overhung. Knowing two of them go up instead, six come to 3.646 m and 2 pairs is enough.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{int, int}|null target index and pairs per side
     */
    public static function liftAbove(array $remaining, int $source, Stack $stack, RowBudget $budget): ?array
    {
        [$sourceDevice, $sourceCount] = $remaining[$source];
        if ($sourceCount < 2 || ($stack->entryFor($sourceDevice->id)->mixWith ?? []) !== []) {
            return null;
        }

        foreach ($remaining as $index => [$device, $count]) {
            if ($index <= $source || $count < 1 || 'sub' !== $device->subtype) {
                continue;
            }

            // A tier that names its own row-mates has already said what it wants, and one that needs more than
            // a single row would have to say *which* of its rows gets the flanks. Neither is a guess to make.
            if (($stack->entryFor($device->id)->mixWith ?? []) !== []) {
                return null;
            }
            $fitsOneRow = StackMetrics::perTier(
                $device,
                RowBudget::narrower($budget->ceilingFor($device, $stack, StackMetrics::rollFor($device, $stack)), $stack->maxWidthM),
                $stack->gapM,
                StackMetrics::rollFor($device, $stack),
            );
            if ($count > $fitsOneRow) {
                return null;
            }

            // **A LIFT MAY NOT SWALLOW WHAT STANDS ON THE TIER IT FLANKS.** A lift is taller than what it flanks most
            // of the time and that is the whole point of it — one Flexy at 0.763 either side of four Achenbachs at
            // 0.600 is a 163 mm shoulder and makes the wall face flat. What breaks is not the step but its *depth*
            // relative to what comes next: an Achenbach standing on those Achenbachs reaches 1.200 and rises clear of
            // the Flexy's 0.763, where an IQ sub standing on the 0.500 m mid bass reaches 1.170 and is still 230 mm
            // below the 1.400 m wall bass lifted beside it. It is in a crater, at the same height as the cabinet next
            // to it, and it came out 130 mm INSIDE it.
            //
            // So the test is whether the next cabinet up clears the flank's top. Measured against the shortest sub
            // still to be placed, because that is the worst case among the candidates for standing there.
            $shortest = INF;
            foreach ($remaining as $later => [$laterDevice, $laterCount]) {
                if ($later > $index && $laterCount > 0 && 'sub' === $laterDevice->subtype) {
                    $shortest = min($shortest, RolledBox::heightOf($laterDevice, StackMetrics::rollFor($laterDevice, $stack)));
                }
            }
            if (StackMetrics::swallows(
                RolledBox::heightOf($device, StackMetrics::rollFor($device, $stack)),
                RolledBox::heightOf($sourceDevice, StackMetrics::rollFor($sourceDevice, $stack)),
                $shortest,
            )) {
                return null;
            }

            $lift = self::liftPairs($device, $count, $sourceDevice, $sourceCount, $stack, $budget);

            return $lift > 0 ? [$index, $lift] : null;
        }

        return null;
    }

    /**
     * How many pairs to promote: enough to close the step, and not one cabinet more.
     *
     * The same converging-widths criterion the bottom row's flanks use, and for the same reason. Every cabinet
     * the flanks take is one fewer in the row below, so the flanked row grows while its support shrinks and the
     * two widths approach from opposite ends. Past the crossing point a promotion no longer flattens anything —
     * it just moves the step down a tier and makes the rig top-heavy.
     *
     * A promotion that would leave the flanked row standing more than half a cabinet off its own support is
     * refused outright: closing a step is worth doing, and not worth doing by hanging the row in the air. An
     * overhang inside that limit is left to {@see StackChecks::supportChecks} to warn about, which is what happens to the
     * 18 mm the Achenbach row ends up proud of the Flexy row under it.
     */
    public static function liftPairs(
        DeviceSpec $target,
        int $targetCount,
        DeviceSpec $source,
        int $sourceCount,
        Stack $stack,
        RowBudget $budget,
    ): int {
        $lift = 0;

        while (2 * ($lift + 1) <= $sourceCount) {
            $candidate = new Tier([
                [$source, $lift + 1, StackMetrics::rollFor($source, $stack)],
                [$target, $targetCount, StackMetrics::rollFor($target, $stack)],
                [$source, $lift + 1, StackMetrics::rollFor($source, $stack)],
            ]);
            $width = $candidate->widthM($stack->gapM);

            if (null !== $stack->maxWidthM && $width > $stack->maxWidthM + StackMetrics::EPSILON_M) {
                break;
            }

            // What is under the flanked row once the flanks are taken out of it. When the promotion uses up the
            // source device entirely there is no row of it left, and the flanked tier comes to sit on whatever
            // was under *that* — the mixed bottom row, typically, which is wider than the flanked row rather
            // than narrower. `lastRowWidth` answers 0 for "none left", which read as "supported by nothing" and
            // refused the promotion outright: it is what kept the Achenbachs in a row of their own above a
            // 1.202 m pair of Flexys instead of sharing a row with them. Nothing here can judge that support, so
            // it does not try — the arrangement is handed to {@see StackChecks::supportChecks}, which is the
            // authority on it either way.
            $left = $sourceCount - 2 * ($lift + 1);
            if ($left > 0) {
                $support = self::lastRowWidth($source, $left, $stack, $budget);
                if (($width - $support) / 2 > $candidate->outerWidthM() / 2) {
                    break;
                }

                // **THE PYRAMID BOUND, PREDICTED HERE RATHER THAN ENFORCED AT EMISSION.** A lift reserves its flanks
                // before a single tier exists, so there is nothing yet to measure against; and enforcing it later does
                // not work either, since by the time the flanked tier is emitted the source's own rows are already
                // built and handing surplus cabinets back would strand them with nowhere left to go. Without this a
                // lift was the one way a pyramid could still step outward.
                //
                // **A width, like the rule it predicts** ({@see StackChecks::silhouetteProblem}) — this used to compare
                // cabinet counts through a `lastRowCount()` helper, since gone, and a count is the premise the owner
                // ruled out. The
                // support's width is already computed on the line above for the bearing test, so the same number
                // answers both questions.
                if (StackShape::Pyramid === $stack->shape
                    && ($width - $support) / 2 > StackChecks::PYRAMID_SHOULDER * $candidate->outerWidthM()
                ) {
                    break;
                }
            } else {
                $support = INF;
            }

            ++$lift;

            if ($width + StackMetrics::EPSILON_M >= $support) {
                break;
            }
        }

        return $lift;
    }

    /**
     * How wide the source's **last** row comes out — the one that ends up directly under the flanked tier.
     *
     * Last rather than first because {@see StackMetrics::share} puts the fuller row at the bottom, so the top of a device's
     * own stack is its narrowest row and that is what the tier above actually stands on.
     *
     * **The count half used to be a method of its own** and no longer needs to be. It existed because the pyramid cap
     * was a cabinet count while everything around it was a width, so a lift had to predict its support in both units.
     * Both are widths now — see {@see StackMetrics::pyramidCeiling} and {@see liftPairs} — and the count is a step on the way to
     * this one answer rather than an answer anybody asks for.
     *
     * Zero for nothing left, which no caller may treat as a cap.
     */
    public static function lastRowWidth(DeviceSpec $device, int $count, Stack $stack, RowBudget $budget): float
    {
        if ($count < 1) {
            return 0.0;
        }

        $roll = StackMetrics::rollFor($device, $stack);
        $perTier = StackMetrics::perTier(
            $device,
            RowBudget::narrower($budget->ceilingFor($device, $stack, $roll), $stack->maxWidthM),
            $stack->gapM,
            $roll,
        );
        $shares = StackMetrics::share($count, (int) ceil($count / $perTier));

        return Tier::of($device, $shares[count($shares) - 1], $roll)->widthM($stack->gapM);
    }
}
