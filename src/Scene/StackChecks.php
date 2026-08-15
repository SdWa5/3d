<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Everything that can be wrong with a solved stack once the cabinets are dealt into rows.
 *
 * Split from {@see StackSolver} because the two share nothing but a `list<Tier>` and the {@see Stack} they came
 * from: one searches for an arrangement, the other judges one. The solver was 1110 lines with both jobs in it,
 * and the checks are the half that keeps growing — every bug this repository has had in the geometry ended up
 * as a rule in here.
 *
 * Four of them, and they catch genuinely different failures. Worth keeping straight, because more than once a
 * new rule has been written to catch something an existing one already covered from a different angle:
 *
 * * **{@see boundsProblems}** — the stated bounds: too wide, too tall, and the sub height it reached against both
 *   the floor `interface_height_m` asks for and the ceiling `max_sub_height_m` allows. Arithmetic against the
 *   numbers in the file.
 * * **{@see supportChecks}** — a *tier* against the tier below it. Blind to anything within a row.
 * * **{@see bearingProblems}** — a *cabinet* against whatever it personally landed on. This is the one that sees
 *   a stepped row, where tier widths look perfectly sensible and a cabinet is balanced on 5.6 mm of its
 *   neighbour's shoulder.
 * * **{@see pillarProblems}** — the shape of the whole rig, which every per-tier and per-cabinet rule passes: a
 *   tower of one-wide tiers has nothing overhanging and every cabinet carried.
 */
final class StackChecks
{
    /** Float slack when comparing a fit — a micrometre, far below anything a cabinet is measured to. */
    private const EPSILON_M = 1e-9;

    /**
     * How far a tier may hang over the one below it before it is worth saying so, per side.
     *
     * A centimetre. Below that it is a cabinet edge sitting proud of a joint, which is normal and is what
     * the rubber feet and the working gaps absorb. Above it, something is standing on air.
     */
    public const OVERHANG_TOLERANCE_M = 0.01;

    /**
     * How far a pyramid's row may sit proud of its support before the wall counts as widening, **as a fraction of the
     * outboard cabinet** rather than as a number of millimetres.
     *
     * A tenth. Derived from the two cases either side of it: six Achenbachs on six Flexys stand 27 mm proud per side
     * out of a 600 mm cabinet and are flush by any reading, and `2× gmss-nuke + 1× gmss-mid-bass` stands 265 mm proud
     * out of a 590 mm one and reads as a V. A fraction rather than a constant so it scales with whatever cabinet is on
     * the end of the row, the same way {@see Gravity::MIN_BEARING} and {@see StackSolver::OVERHANG_PER_SIDE} do.
     */
    public const PYRAMID_SHOULDER = 0.1;

    /**
     * Every bound the finished stack misses, each naming the number it reached and the number it needed.
     *
     * @param list<Tier> $tiers
     * @return array{problems: list<string>, warnings: list<string>}
     */
    public static function boundsProblems(array $tiers, Stack $stack): array
    {
        $messages = [];
        $warnings = [];

        $subHeight = 0.0;
        $totalHeight = 0.0;
        $widest = 0.0;
        $widestLabel = $tiers[0]->label();
        $hasTop = false;
        foreach ($tiers as $tier) {
            $totalHeight += $tier->heightM();
            if ($tier->isSub()) {
                $subHeight += $tier->heightM();
            } else {
                $hasTop = true;
            }
            if ($tier->widthM($stack->gapM) > $widest) {
                $widest = $tier->widthM($stack->gapM);
                $widestLabel = $tier->label();
            }
        }

        // The interface height is an **optimum, not a requirement**, so missing it is a warning.
        //
        // The solver already does everything it can to reach it — it tries the widest row first and narrows,
        // because narrower rows mean more of them — and hands back the tallest arrangement it managed when
        // none reach. What is left over is a rig lower than ideal, which is a judgement about coverage rather
        // than something impossible. Refusing it outright made small rigs unbuildable for no good reason:
        // four Achenbachs one-wide reach 2.400 m and two-wide only 1.200 m, and neither is absurd.
        //
        // An **unsupported** tier stays an error ({@see supportChecks}), and that is the line: a cabinet
        // hanging off the edge of its support cannot be built at any price, while tops a bit low can.
        //
        // Nothing to fire over anybody's head means nothing to say: a stack of subs alone has no interface,
        // and mentioning one would be noise.
        if ($hasTop && $stack->interfaceHeightM > 0.0 && $subHeight + self::EPSILON_M < $stack->interfaceHeightM) {
            // "while every tier is still carried" and not "at all": narrowing the rows further would stack
            // higher, but it would also leave the tops overhanging a one-wide column, and support outranks
            // the interface. Claiming this is the inventory's ceiling would be untrue.
            $warnings[] = sprintf(
                'the subs reach %.3f m against the %.3f m interface asked for, so the tops sit %.0f mm lower '
                .'than ideal — %.3f m is the most they reach while every tier is still carried',
                $subHeight,
                $stack->interfaceHeightM,
                ($stack->interfaceHeightM - $subHeight) * 1000,
                $subHeight,
            );
        }
        // And the same number from the other side. A warning for the same reason its mirror is one: the solver
        // already walks the whole search and keeps the best arrangement that stands up, so a miss here is not
        // a mistake to refuse but the inventory's own floor — a stack holding six sub types cannot be shorter than
        // the rows those types need, however they are packed. Refusing would make the key unusable on exactly the
        // rigs it was added for, where knowing the miss and by how much is the useful answer.
        //
        // Reported whether or not there are tops, unlike the interface: `max_sub_height_m` bounds the sub wall
        // itself, and a sub wing with no tops on it still has to fit under the truss.
        if ($stack->maxSubHeightM !== null && $subHeight > $stack->maxSubHeightM + self::EPSILON_M) {
            $warnings[] = sprintf(
                // **"the nearest the target" rather than "the shortest"**, because that is what the solver now
                // returns. Under a ceiling it keeps the arrangement closest to `target_sub_height_m` among those that
                // stand up, so a message promising the shortest one would be describing a rule that no longer exists —
                // and on a rig that misses the ceiling it would be describing it wrongly in the reader's favour.
                'the subs reach %.3f m against the %.3f m ceiling asked for, so they stand %.0f mm too high — '
                .'%.3f m is the nearest the %.3f m target that every tier is still carried at',
                $subHeight,
                $stack->maxSubHeightM,
                ($subHeight - $stack->maxSubHeightM) * 1000,
                $subHeight,
                $stack->targetSubHeightM,
            );
        }
        if ($stack->maxHeightM !== null && $totalHeight > $stack->maxHeightM + self::EPSILON_M) {
            $messages[] = sprintf(
                'stack.max_height_m (%.3f): the stack comes out %.3f m tall',
                $stack->maxHeightM,
                $totalHeight,
            );
        }
        if ($stack->minWidthM !== null && $widest + self::EPSILON_M < $stack->minWidthM) {
            $messages[] = sprintf(
                'stack.min_width_m (%.3f): the widest tier is only %.3f m',
                $stack->minWidthM,
                $widest,
            );
        }
        if ($stack->maxWidthM !== null && $widest > $stack->maxWidthM + self::EPSILON_M) {
            // Reachable only when one cabinet is wider than the whole bound, since `perTier` floors to at
            // least one — which is exactly the case worth naming rather than rounding away.
            $messages[] = sprintf(
                'stack.max_width_m (%.3f): the %s row is already %.3f m wide',
                $stack->maxWidthM,
                $widestLabel,
                $widest,
            );
        }

        return ['problems' => $messages, 'warnings' => $warnings];
    }

    /**
     * How well each tier is carried by the one below it, split by whether it is buildable.
     *
     * The line between the two is **half the outboard cabinet's width**, and it is the difference between a
     * cabinet sitting proud of a joint and a cabinet standing on nothing. Under it, the overhang is what feet
     * and working gaps absorb and a crew would not comment on it. Over it, more than half of that cabinet's
     * footprint is off the edge of its support — it is in the air, and no amount of shimming fixes it.
     *
     * Both are reported. Neither shows up anywhere else in the pipeline: `on:` only reads a top face, and the
     * shipped-scene check only catches cabinets *inside* each other, never one standing on air.
     *
     * @param list<Tier> $tiers
     * @return array{problems: list<string>, warnings: list<string>}
     */
    public static function supportChecks(array $tiers, Stack $stack): array
    {
        $problems = [];
        $warnings = [];

        foreach ($tiers as $index => $tier) {
            // No warning for a stepped row any more. It used to say the tier above "rests on the tall
            // cabinets and bridges the short ones", which was true of the old placement and is the thing
            // gravity fixed: each cabinet now lands on whatever is under it, so a stepped row simply has an
            // uneven top and everything above it is carried. See {@see Stack::runsFor}.

            if ($index === 0) {
                continue;
            }

            $below = $tiers[$index - 1]->widthM($stack->gapM);

            $problems = [...$problems, ...self::silhouetteProblem($tier, $below, $stack)];

            $overhang = ($tier->widthM($stack->gapM) - $below) / 2;
            if ($overhang <= self::OVERHANG_TOLERANCE_M) {
                continue;
            }

            $message = sprintf(
                'the %s row is %.3f m on a %.3f m row, so it overhangs %.0f mm each side',
                $tier->label(),
                $tier->widthM($stack->gapM),
                $below,
                $overhang * 1000,
            );

            $warnings[] = $message;
        }

        return [
            'problems' => [
                ...$problems,
                ...self::bearingProblems($tiers, $stack),
                ...self::pillarProblems($tiers, $stack),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * The shape rule for one row against the one under it — **in metres, never in cabinets**.
     *
     * **Stated by the owner: the pyramid, the V and the tower are all width rules.** Counting cabinets was how the
     * pyramid was written, and the premise it rests on is false — nine of our ten cabinets are 0.45–0.66 m wide and
     * `gmss-mid-bass` is **1.200 m**, so "no more cabinets than the row below" and "no wider than the row below"
     * stopped meaning the same thing the day it arrived. The V made the failure obvious rather than causing it: built
     * on a count rule it produced **21 stacks that narrow against 8 that widen**, and `free` widened more often than
     * the shape named after widening.
     *
     * The two rules are one line read in either direction:
     *
     * * **{@see StackShape::Pyramid}** — a row may not be wider than its support **by a whole cabinet of its own**.
     * * **{@see StackShape::V}** — a row may not be narrower than its support at all.
     *
     * **The pyramid's allowance is a tenth of its outboard cabinet per side**, and both halves of that were measured.
     * It cannot be zero: six Achenbachs are 3.700 m on six Flexys' 3.646 — 27 mm per side, a flush wall by any reading
     * — and a rule without an allowance splits them into two rows of three, whereupon the 1.84 m row cannot carry the
     * tops and a 2-way is dropped from the rig. It cannot be a whole cabinet either, which was the first thing tried
     * here: `2× gmss-nuke + 1× gmss-mid-bass` is 2.420 m on a 1.890 m row, 265 mm per side, and one nuke is 590 — so a
     * one-cabinet allowance passes a row that reads as a V to anybody looking at it.
     *
     * A tenth of a cabinet separates them cleanly: 27 mm against the 60 it allows, and 265 against the 59 it does not.
     * Stated as a fraction rather than a number of millimetres so it scales with whatever cabinet is on the end of the
     * row, the same way {@see Gravity::MIN_BEARING} and {@see StackSolver::OVERHANG_PER_SIDE} are fractions.
     *
     * A **problem** rather than a warning, so the search moves on: {@see StackSolver::fill} walks every row width and
     * this refuses the arrangements that are not the shape asked for, exactly as the bearing rules beside it refuse the
     * ones that do not stand up.
     *
     * **Tops are exempt from the V.** Nothing stands on a top, `topRow()` deliberately never caps their width, and a
     * tops row narrower than the wall carrying it is the normal case rather than a broken silhouette.
     *
     * @return list<string>
     */
    private static function silhouetteProblem(Tier $tier, float $below, Stack $stack): array
    {
        $width = $tier->widthM($stack->gapM);

        if ($stack->shape === StackShape::V) {
            return $tier->isSub() && $width + self::OVERHANG_TOLERANCE_M < $below
                ? [sprintf(
                    'the %s row is %.3f m on a %.3f m row, so the wall narrows as it rises — `shape: v` asks for the '
                    .'opposite and this is not one of its arrangements',
                    $tier->label(),
                    $width,
                    $below,
                )]
                : [];
        }

        if ($stack->shape !== StackShape::Pyramid) {
            return [];
        }

        $allowance = self::PYRAMID_SHOULDER * $tier->outerWidthM();

        return ($width - $below) / 2 > $allowance
            ? [sprintf(
                'the %s row is %.3f m on a %.3f m row, so it steps out %.0f mm each side against the %.0f mm a '
                .'%.3f m cabinet may sit proud — `shape: pyramid` asks for a wall that does not widen as it rises',
                $tier->label(),
                $width,
                $below,
                ($width - $below) / 2 * 1000,
                $allowance * 1000,
                $tier->outerWidthM(),
            )]
            : [];
    }

    /**
     * Sub tiers narrowed all the way to a single column, which is a pillar rather than a rig.
     *
     * The interface chase has no floor without this. Narrower rows mean more of them and so a taller stack, so
     * on a pile it cannot otherwise lift, {@see fill} keeps narrowing — and at a 12 m interface the Flexys end
     * up one wide and `--per-owner` gives `sdwa5` a rig 1.8 m across and 4.9 m tall. Every existing rule passes
     * it: each tier is exactly as wide as the one below, so nothing overhangs, and every cabinet is fully
     * carried.
     *
     * What it is not is a **rig**. A one-cabinet tier has no lateral stiffness, and the support rule goes
     * vacuous on it — a column is never more than half a cabinet wider than the column beneath it, so the check
     * that catches every other bad shape cannot see this one. It also came out geometrically marginal in the
     * ways only a full compile shows: two aimed tops 3 m up biting 10.6 mm into each other, and a top bearing on
     * 43 % of its footprint once its own down-tilt is applied.
     *
     * So this extends the ordering {@see fill} already states. Support outranks the interface; a rig that stands
     * up as a rig outranks reaching the height. Missing the interface is a warning, and the search reports that
     * instead of handing back a tower.
     *
     * Only when the device has more than one cabinet in this stack — a single Tecnare *is* a single column, and
     * there is nothing else it could be.
     *
     * @param list<Tier> $tiers
     * @return list<string>
     */
    private static function pillarProblems(array $tiers, Stack $stack): array
    {
        $held = [];
        foreach ($tiers as $tier) {
            foreach ($tier->segments as [$device, $count]) {
                $held[$device->id] = ($held[$device->id] ?? 0) + $count;
            }
        }

        $problems = [];
        foreach ($tiers as $tier) {
            if (!$tier->isSub() || $tier->count() > 1) {
                continue;
            }

            [$device] = $tier->segments[0];
            if (($held[$device->id] ?? 0) < 2) {
                continue;
            }

            $problems[] = sprintf(
                'the %s row is a single column, and %d of them are in this stack — a one-wide tier is a pillar '
                .'rather than a rig, however well each cabinet is carried. Widen the rows and accept a lower '
                .'interface, or take cabinets out of the stack',
                $tier->label(),
                $held[$device->id],
            );
            break;
        }

        return $problems;
    }

    /**
     * Every cabinet that would land on too little of its support to call itself carried.
     *
     * The rule above measures a *tier* against the tier below it, which is the right check for a level row and
     * blind to a stepped one. A mixed row is 163 mm taller at its shoulders than in its middle, and a row laid
     * across that step can clip a shoulder by 5.6 mm — whereupon falling does exactly what falling does and
     * lifts the whole cabinet onto that 5.6 mm. Tier widths say nothing is wrong: the row above is *narrower*
     * than the row below, and the floating-cabinet sweep is satisfied too, because there really is something
     * underneath. Only asking how much of the cabinet is over it catches this.
     *
     * Half its own width, the same line the overhang rule draws — "more than half off the edge is standing on
     * nothing" — applied per cabinet instead of per tier. Reusing {@see Gravity} rather than measuring again is
     * the point: the solver has to reject exactly the arrangement the expansion would build.
     *
     * @param list<Tier> $tiers
     * @return list<string>
     */
    private static function bearingProblems(array $tiers, Stack $stack): array
    {
        $problems = [];

        $resolved = Gravity::resolve($tiers, $stack->gapM, 'stack', $stack->slideSlackM, $stack->maxWidthM);
        foreach ($resolved as $index => $runs) {
            foreach ($runs as $run) {
                // Nothing underneath at all. Falling puts it on the floor, which for a tier above the bottom
                // means *inside* the tier below — and this is the one place that can say so. The rule is here
                // rather than left to the tier-width check because that check refusing the shapes which cause
                // it is a coincidence of two rules agreeing, not the invariant being held.
                if ($index > 0 && $run['on'] === null) {
                    $problems[] = sprintf(
                        'a %s in the %s row has nothing under it at all, so it would fall to the floor — '
                        .'inside the row below it',
                        $run['device']->id,
                        $tiers[$index]->label(),
                    );
                    continue;
                }
                // How much of it is over what it landed on. A **third**, not a half: see
                // {@see Gravity::MIN_BEARING} for why the boundary moved, and note what this is *not* asking —
                // whether a lower surface sits under the overhang is irrelevant here. A surface below can only
                // ever catch a cabinet that tilts; it cannot make one less stable than the same cabinet
                // cantilevered over thin air. Treating it as a hazard refused a Flexy row on a mixed
                // Flexy-and-SKRAM bottom row while allowing the identical row on a lone SKRAM, which is
                // backwards.
                if ($run['bearing'] + self::EPSILON_M < Gravity::MIN_BEARING) {
                    $problems[] = sprintf(
                        'a %s in the %s row would land on only %.0f%% of its own width — it is touching what '
                        .'carries it rather than sitting on it. Line the segments up with what carries them',
                        $run['device']->id,
                        $tiers[$index]->label(),
                        $run['bearing'] * 100,
                    );
                }
            }

            // And the row as one body: its combined mass has to sit over what carries it. This is the question a
            // per-cabinet rule cannot ask, and the one that matters for a row whose end cabinets reach past the
            // support and lean on the neighbours they are strapped to.
            if ($index > 0 && Stability::tips($runs, $resolved[$index - 1])) {
                $problems[] = sprintf(
                    'the %s row would tip: its combined centre of mass falls outside what carries it. Narrow the '
                    .'tier, widen what carries it, or take the odd cabinets out of the stack',
                    $tiers[$index]->label(),
                );
            }
        }

        return $problems;
    }
}
