<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * The measurements every part of the solve asks for, and the two tolerances they are asked against.
 *
 * **Nothing in here calls anything.** That is the whole point of the class rather than a coincidence of what
 * landed in it: these are the leaves of {@see StackSolver}'s call graph, so {@see StackMix}, {@see StackLifts},
 * {@see StackTops} and the solver itself can all reach them without any of the four reaching each other in a
 * circle. Adding a method here that calls out of it would put that property back where it was.
 *
 * What they have in common is that each answers one question about a cabinet, a row or a tier that several
 * unrelated rules need the same answer to. {@see rollFor} is asked sixteen times across the solve, because
 * every width in this repository is a width *after* the cabinet has been turned, and a rule that measured the
 * unrolled box would be measuring a rig nobody is going to build.
 */
final class StackMetrics
{
    /** Float slack when comparing a fit — a micrometre, far below anything a cabinet is measured to. */
    public const EPSILON_M = 1e-9;

    /**
     * How far a row may reach past the tier carrying it, as a multiple of one cabinet's width.
     *
     * **Derived from {@see Gravity::MIN_BEARING}, not chosen.** A row of `n` cabinets of width `w` centred on a
     * support `S` wide puts its outer cabinet's inner edge at `rowHalf − w`. That cabinet keeps a third of itself
     * on the support while `S/2 − (rowHalf − w) ≥ w/3`, which rearranges to `rowWidth ≤ S + (4/3)·w` — two thirds
     * of a cabinet hanging off each end.
     *
     * So this is the *checker's* rule expressed as a width the fill can size a row against, and it is deliberately
     * no stricter. {@see StackChecks::bearingProblems} enforces the same bound afterwards; a fill capped harder
     * than that would refuse rigs the repository already ships, and one capped softer hands the checker rows it is
     * about to reject — which is exactly the bug this exists to fix.
     */
    public const OVERHANG_PER_SIDE = 2 / 3;

    /**
     * The quarter turn this device's tiers lie on, or 0 for upright.
     *
     * Read off the entry rather than passed around, so every place that builds a `Tier` measures the same
     * cabinet the expansion will place. A stack built without entries — which is how the solver's own tests
     * construct one — has nothing to say and everything stays upright.
     */
    public static function rollFor(DeviceSpec $device, Stack $stack): float
    {
        return $stack->entryFor($device->id)->rollMirror ?? 0.0;
    }

    public static function perTier(DeviceSpec $device, ?float $maxWidthM, float $gapM, float $rollDeg = 0.0): int
    {
        if (null === $maxWidthM) {
            return PHP_INT_MAX;
        }

        // The **rolled** width. Four Flexys on their sides fill a 3.70 m stage where six standing up do, and
        // fitting them by their nominal 591 mm put 3.895 m of cabinet on a 3.70 m stage.
        $fit = (int) floor(($maxWidthM + $gapM) / (RolledBox::widthOf($device, $rollDeg) + $gapM) + self::EPSILON_M);

        return max(1, $fit);
    }

    /**
     * `$count` cabinets over `$rows` rows, as evenly as they go — 8 over 2 is 4 + 4, and 3 over 2 is
     * 2 + 1 with the fuller row at the bottom where it belongs.
     *
     * @return list<int>
     */
    public static function share(int $count, int $rows): array
    {
        $shares = [];
        for ($row = 0; $row < $rows; ++$row) {
            $shares[] = (int) ceil(($count - array_sum($shares)) / ($rows - $row));
        }

        return $shares;
    }

    /**
     * The sub to flank the middle with: the one with the most cabinets left, since it is the one that can
     * afford to give a pair away and still fill the rows above.
     *
     * **Heights need not match**, and that is worth stating because it was briefly forbidden. A row whose
     * cabinets differ in height has two top faces, and resting the whole row above at the taller of them left
     * six Flexys floating 151 mm over the SKRAMs' neighbours. The fix for that is gravity — each cabinet lands
     * on whatever is under *it*, see {@see Stack::runsFor} — not a ban on mixing. Banning it threw out the
     * arrangement the feature exists for: two SKRAMs in the middle of a Flexy bottom row.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    public static function flankingSub(array $remaining, int $centre, DeviceSpec $centreDevice): ?int
    {
        $best = null;
        foreach ($remaining as $index => [$device, $count]) {
            if ($index === $centre || $count < 2 || 'sub' !== $device->subtype) {
                continue;
            }
            if (null === $best || $count > $remaining[$best][1]) {
                $best = $index;
            }
        }

        return $best;
    }

    /**
     * How high the sub tiers reach.
     *
     * @param list<Tier> $tiers
     */
    public static function subHeight(array $tiers): float
    {
        $height = 0.0;
        foreach ($tiers as $tier) {
            if ($tier->isSub()) {
                $height += $tier->heightM();
            }
        }

        return $height;
    }

    /**
     * How wide the tier a new row would stand on is, or `INF` when there is none yet.
     *
     * Reads the tiers already dealt rather than being threaded through every branch, because tiers arrive from four
     * places — the mixed bottom row, a lift, a stated mix and a plain row — and the last one appended is the support
     * whichever of them produced it. One question asked in one place cannot fall out of step with them.
     *
     * @param list<Tier> $tiers
     */
    public static function supportOf(array $tiers, Stack $stack): float
    {
        $last = end($tiers);

        return false === $last ? INF : $last->widthM($stack->gapM);
    }

    /**
     * The widest a row of this device may be: the stated stage width, and what the tier below can carry.
     *
     * The second half is the fix for rows the fill used to hand its own checker to reject. Six Achenbachs are
     * 3.700 m and fit any stage this repository states; four Flexys under them are 2.424 m, and an Achenbach on the
     * end of that row has nothing beneath it at all. Capping by the support turns one six-wide row into rows the
     * tier below can actually carry.
     *
     * `INF` support means the bottom tier, where only the stage width applies — there is nothing under it but floor,
     * and floor carries anything.
     *
     * Returns `null` for "no bound at all", which is what {@see perTier} understands. Handing it `INF` instead casts
     * to `(int)floor(INF)` in there, which is undefined in PHP and came out as a row of one — every tier a pillar,
     * from a stack with no stated width at all.
     */
    public static function ceilingFor(DeviceSpec $device, Stack $stack, float $roll, float $supportM): ?float
    {
        if (is_infinite($supportM)) {
            return $stack->maxWidthM;
        }

        $allowed = $supportM + 2 * self::OVERHANG_PER_SIDE * RolledBox::widthOf($device, $roll);

        return null === $stack->maxWidthM ? $allowed : min($allowed, $stack->maxWidthM);
    }

    /**
     * The tallest cabinet among a packed row's takes so far.
     *
     * @param list<array{DeviceSpec, int, float}> $row
     */
    public static function tallestIn(array $row): float
    {
        $tallest = 0.0;
        foreach ($row as [$device, , $roll]) {
            $tallest = max($tallest, RolledBox::heightOf($device, $roll));
        }

        return $tallest;
    }

    /**
     * The shortest sub still queued after `$cursor` — the worst case for what will stand on the row being packed.
     *
     * INF when nothing is left, which {@see swallows} reads as "cannot be swallowed".
     *
     * @param list<array{DeviceSpec, int, float}> $queue
     */
    public static function shortestAfter(array $queue, int $cursor, Stack $stack): float
    {
        $shortest = INF;
        for ($later = $cursor + 1; $later < count($queue); ++$later) {
            [$device, $count, $roll] = $queue[$later];
            if ($count > 0) {
                $shortest = min($shortest, RolledBox::heightOf($device, $roll));
            }
        }

        return $shortest;
    }

    /**
     * Whether a cabinet standing on the low part of a stepped row would be **swallowed** by the tall part beside it.
     *
     * The one rule three different places in this class need, so it is written once. A step in a row is normal and
     * usually wanted — one Flexy at 0.763 either side of Achenbachs at 0.600 makes the wall face flat. What is not
     * survivable is a step deep enough that the *next* cabinet up fits entirely inside it: it then stands at the same
     * height as the cabinet beside it with nothing between them, and the two interpenetrate. An Achenbach on
     * Achenbachs reaches 1.200 and rises clear of the Flexy's 0.763, so that row is fine; an IQ sub on the 0.500 m
     * mid bass reaches 1.170 and is still 230 mm below the 1.400 m wall bass, so that one is not.
     *
     * Measured against the shortest cabinet that could stand there, which is the worst case. An unknown next cabinet
     * — nothing left to place — cannot be swallowed by anything, so it never refuses on no information.
     */
    public static function swallows(float $lowTopM, float $flankTopM, float $nextHeightM): bool
    {
        if (is_infinite($nextHeightM)) {
            return false;
        }

        return $lowTopM + $nextHeightM < $flankTopM - self::EPSILON_M;
    }

    /**
     * How wide the next row up may come out — the pyramid rule as a hint, in the unit the rule is written in.
     *
     * **THE SHOULDER IS WHAT MAKES THIS A WIDTH AT ALL, AND LEAVING IT OUT IS MEASURED WRONG.** A plain "no wider
     * than the row below" was tried before this and is too blunt: six Achenbachs are 3.700 m on six Flexys' 3.646 m,
     * a 27 mm shoulder per side that the bearing rule allows four hundred of, and forbidding it split them into two
     * rows of three — whereupon the 1.84 m row could not carry the tops and a 2-way was dropped from the rig. **A
     * flush wall is not a V.** That failure is the reason this was a cabinet count for two releases.
     *
     * The count worked because our cabinets were mostly one size, and it stopped being defensible the day
     * `mid-bass` arrived at 1.200 m beside nine cabinets of 0.45–0.66 m. So the allowance moves here instead of
     * the unit moving back: `below + 2 × PYRAMID_SHOULDER × cabinet width`, which is exactly what
     * {@see StackChecks::silhouetteProblem} permits. Six on six stays flush and allowed, three on one is still the V
     * and is still refused, and a 1.200 m cabinet is no longer counted as though it were a 0.450 m one.
     *
     * **A hint, not the rule.** The rule is the checker's, because the checker is the one that refuses. This only
     * decides where the search looks first, and a good starting width costs nothing: the arrangement the width rule
     * wants is nearly always the one this proposes, so the search finds it instead of walking down to it.
     *
     * Null for `free` and for the bottom row, which has nothing to narrow relative to.
     *
     * The V gets no mirror of this line and that is also measured. Reading it the other way round was the obvious
     * mirror and it does nothing: permitting a wider row does not make one, because a row's width is decided by what
     * cabinets are left. Built that way the V produced **21 stacks that narrow against 8 that widen**.
     *
     * @param list<Tier> $tiers
     */
    public static function pyramidCeiling(array $tiers, Stack $stack, DeviceSpec $device, float $roll): ?float
    {
        $last = end($tiers);
        if (!in_array($stack->shape, [StackShape::Pyramid, StackShape::Tower, StackShape::Mixed], true) || false === $last) {
            return null;
        }

        return $last->widthM($stack->gapM)
            + 2 * StackChecks::PYRAMID_SHOULDER * RolledBox::widthOf($device, $roll);
    }

    /**
     * A packed row's takes arranged **tallest in the middle**, the rest flanking it in pairs.
     *
     * Tallest, not widest, and that is a correction rather than a preference. A mixed row has as many top faces
     * as it has heights, and what stands on it lands on the **tall** segments only — so where those sit decides
     * whether the next row is carried at all. Put a 1.020 m middle sub either side of a 0.637 m turbo sub and the
     * row above has two 595 mm-high pads a metre apart to sit on: it bridges the middle and lands on 14 % of
     * itself. The same cabinets with the tall one central give one plateau with shoulders stepping down, which is
     * what a wall of unequal cabinets looks like when a crew builds it.
     *
     * The row is still symmetric about its own centre, which {@see Tier::mirrored} expects to split, and it still
     * puts the heaviest cabinets centrally — the same reason {@see StackMix::statedMix} and {@see StackTops::topRow} centre theirs.
     *
     * An **odd** take cannot be halved: `intdiv` goes left and the remainder right, which is exactly what
     * `Tier::mirrored()` does with an odd cabinet count. A take of one is therefore all on the right, and that
     * asymmetry is the honest cost of placing a single cabinet rather than leaving it out.
     *
     * @param list<array{DeviceSpec, int, float}> $row
     */
    public static function centred(array $row): Tier
    {
        usort(
            $row,
            static fn (array $a, array $b): int => RolledBox::heightOf($b[0], $b[2])
                <=> RolledBox::heightOf($a[0], $a[2]),
        );

        $left = [];
        $right = [];

        foreach ($row as $position => [$device, $take, $roll]) {
            if (0 === $position) {
                continue;
            }
            $half = intdiv($take, 2);
            if ($half > 0) {
                $left[] = [$device, $half, $roll];
            }
            if ($take - $half > 0) {
                $right[] = [$device, $take - $half, $roll];
            }
        }

        return new Tier([...array_reverse($left), $row[0], ...$right]);
    }
}
