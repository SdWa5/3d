<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * The tops row, and the widths that decide what may stand on what above the subs.
 *
 * The tops are the one row whose order is an acoustic decision rather than a structural one, and the two
 * alignments want opposite things from it. {@see topRow} centres the long throw with the fills outboard, which
 * is what a mono rig is for; {@see stereoTopRow} puts the fills inboard nearest the centre line, because a
 * stereo rig exists for the width of its image. Both are built and the alignment picks between them.
 *
 * {@see widthAbove} and {@see flankingPairs} are here because they answer the same question one row lower:
 * how wide the thing standing on this tier actually is, once its cabinets are rolled and its flanks are
 * counted. {@see reachesInterface} is the sub/top interface height read as a preference rather than as a gate,
 * which is CVR-7 and is stated by the owner.
 *
 * The top layer of the four: it reads {@see StackMetrics}, {@see StackMix} and {@see StackLifts}, and nothing
 * reads it except {@see StackSolver}.
 */
final class StackTops
{
    /**
     * Every top in **one** row, ordered by what the placement's alignment is trying to achieve.
     *
     * Not split across tiers however wide it comes out, because that is what the caller asked for and it is
     * also the physical truth: nothing stands on the tops, so width is the only thing they cost. If the row
     * is wider than `max_width_m` that is reported by {@see StackChecks::boundsProblems} rather than quietly turned
     * into a second tier of tops balanced on the first.
     *
     * **WHICH WAY ROUND DEPENDS ON THE MODE, and the two orders are mirror opposites of each other:**
     *
     * * **`stereo`** puts the widest tops — the long throw — at the **outer ends** of the row and the near-field
     *   fills **inboard of them, nearest the centre line**. The point of a stereo rig is the width of its image, so
     *   the main clusters go as far apart as the envelope allows; the fills cover the middle ground the two clusters
     *   leave between them, which is where they are needed and also the shortest throw they make.
     * * **`center` and `block`** keep the older arrangement: the long throw centred with the fills outboard. That is
     *   the mono answer — one cluster carrying the room from the middle, fills widening the coverage — and `block`
     *   then justifies the spacing so the row is spread as broad and as evenly as the width allows.
     *
     * Ordered by width in both cases, just read from opposite ends: widest outermost for `stereo`, widest innermost
     * for the rest. Width stands in for throw because that is what it already stands for everywhere else here —
     * {@see \App\Command\SceneStackCommand::nearFieldFills} calls every top narrower than the widest a fill, and
     * this has to agree with it or a cabinet would be aimed as a fill and placed as a long throw.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    public static function topRow(array $remaining, Stack $stack, ?LayoutMode $align = null): ?Tier
    {
        $tops = [];
        foreach ($remaining as [$device, $count]) {
            if ($count > 0 && 'sub' !== $device->subtype) {
                $tops[] = [$device, $count];
            }
        }
        if ([] === $tops) {
            return null;
        }

        usort($tops, static fn (array $a, array $b): int => RolledBox::widthOf($b[0], StackMetrics::rollFor($b[0], $stack))
            <=> RolledBox::widthOf($a[0], StackMetrics::rollFor($a[0], $stack)));

        if (LayoutMode::Stereo === $align) {
            return self::stereoTopRow($tops, $stack);
        }

        $centre = array_shift($tops);
        $centre[] = StackMetrics::rollFor($centre[0], $stack);

        $left = [];
        $right = [];
        foreach ($tops as [$device, $count]) {
            $share = intdiv($count, 2);
            if ($count - 2 * $share > 0) {
                ++$share;
            }
            if ($share > 0) {
                $left[] = [$device, $share, StackMetrics::rollFor($device, $stack)];
            }
            if ($count - $share > 0) {
                $right[] = [$device, $count - $share, StackMetrics::rollFor($device, $stack)];
            }
        }

        return new Tier([...array_reverse($left), $centre, ...$right]);
    }

    /**
     * The tops row for a stereo rig: widest at the ends, narrowest meeting in the middle, symmetric about the centre.
     *
     * Every group is halved and dealt outward from the middle, so the row reads widest-to-narrowest inward on the
     * left and narrowest-to-widest outward on the right.
     *
     * **AN ODD CABINET GOES TO THE CENTRE, NOT TO ONE SIDE.** That is what keeps the two clusters equal, which is the
     * whole point of a stereo rig: giving the extra to the left instead makes one side a cabinet heavier and the image
     * lopsided. Three M2122s, two 2-ways and two turbo tops come out
     * `1× M2122 + 1× 2-way + 1× turbo [1× M2122] 1× turbo + 1× 2-way + 1× M2122` — a palindrome, with the odd M2122
     * on the centre line where it belongs.
     *
     * **More than one odd group cannot all be centred**, and then the centre holds one of each and the row is
     * symmetric everywhere except inside that block. Three M2122s and three turbo tops leave an M2122 and a turbo top
     * in the middle: two cabinets side by side rather than one on the centre line. That is the least asymmetry the
     * counts allow — a true palindrome needs every group even, or exactly one group odd — and it is a centimetre of
     * imbalance in the middle rather than a whole cabinet at one end.
     *
     * The centre block is ordered widest-first, the same direction as the halves around it.
     *
     * @param list<array{DeviceSpec, int}> $tops widest first
     */
    public static function stereoTopRow(array $tops, Stack $stack): Tier
    {
        $left = [];
        $centre = [];
        $right = [];

        foreach ($tops as [$device, $count]) {
            $roll = StackMetrics::rollFor($device, $stack);
            $half = intdiv($count, 2);

            if ($half > 0) {
                $left[] = [$device, $half, $roll];
                $right[] = [$device, $half, $roll];
            }
            if (1 === $count % 2) {
                $centre[] = [$device, 1, $roll];
            }
        }

        // `$left` is built widest-first, which is already outermost-first for the left-hand half, so it needs no
        // reversing. `$right` is the same list read the other way: narrowest nearest the middle, widest at the end.
        return new Tier([...$left, ...$centre, ...array_reverse($right)]);
    }

    /**
     * The most flanking pairs a mixed bottom row could take — the widest it is allowed to be.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    public static function flankingPairs(array $inventory, Stack $stack, RowBudget $budget): int
    {
        $centre = StackMix::widestSub($inventory, $stack);
        if (null === $centre) {
            return 0;
        }
        [$device, $available] = $inventory[$centre];

        $flank = StackMetrics::flankingSub($inventory, $centre, $device);
        if (null === $flank) {
            return 0;
        }

        [$flankDevice, $flankAvailable] = $inventory[$flank];

        $pairs = 0;
        while (2 * ($pairs + 1) <= $flankAvailable) {
            $candidate = new Tier([
                [$flankDevice, $pairs + 1, StackMetrics::rollFor($flankDevice, $stack)],
                [$device, $available, StackMetrics::rollFor($device, $stack)],
                [$flankDevice, $pairs + 1, StackMetrics::rollFor($flankDevice, $stack)],
            ]);
            $width = $candidate->widthM($stack->gapM);

            if (null !== $stack->maxWidthM && $width > $stack->maxWidthM + StackMetrics::EPSILON_M) {
                break;
            }
            ++$pairs;

            // Stop as soon as the row is no longer narrower than what will stand on it. Mixing exists to
            // remove an inverted step, so growing past the point where the step is gone is not a wider base,
            // it is cabinets stolen from the rows above: unbounded, the flanks ate eight of twelve Flexys and
            // left a 6.128 m bottom row carrying a 2.424 m one. A pancake with a tower on it, not a wall.
            //
            // Every Flexy the flanks take is one fewer in the row above, so the two widths converge from both
            // ends and the crossing point is where the taper stops. With a `max_width_m` the bound usually
            // bites first and this changes nothing.
            // The probe has the bottom row already taken out of it. Without that, `widthAbove` sees the
            // SKRAMs still sitting in the inventory, decides they are a tier waiting to be flanked, and
            // reserves Flexys for a mixed row that the bottom row is in the middle of building.
            $probe = $inventory;
            $probe[$centre] = [$device, 0];
            $probe[$flank] = [$flankDevice, $flankAvailable - 2 * $pairs];

            if ($width + StackMetrics::EPSILON_M >= self::widthAbove($flankDevice, $flankAvailable - 2 * $pairs, $probe, $flank, $stack, $budget)) {
                break;
            }
        }

        return $pairs;
    }

    /**
     * How wide the row standing on the mixed bottom row would be: the flanking device's leftovers if it has
     * any, otherwise the next device's first row.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    public static function widthAbove(
        DeviceSpec $flankDevice,
        int $leftOver,
        array $inventory,
        int $flank,
        Stack $stack,
        RowBudget $budget,
    ): float {
        if ($leftOver > 0) {
            // Cabinets destined for the tier above are not in this row, see {@see StackLifts::liftAbove}.
            $leftOver -= 2 * (StackLifts::liftAbove($inventory, $flank, $stack, $budget)[1] ?? 0);
        }
        if ($leftOver > 0) {
            $perTier = StackMetrics::perTier(
                $flankDevice,
                RowBudget::narrower($budget->ceilingFor($flankDevice, $stack, StackMetrics::rollFor($flankDevice, $stack)), $stack->maxWidthM),
                $stack->gapM,
                StackMetrics::rollFor($flankDevice, $stack),
            );
            $rows = (int) ceil($leftOver / $perTier);

            return Tier::of($flankDevice, StackMetrics::share($leftOver, $rows)[0], StackMetrics::rollFor($flankDevice, $stack))
                ->widthM($stack->gapM);
        }

        return StackMix::rowAbove($inventory, $flank, $stack, $budget) ?? 0.0;
    }

    /**
     * Whether the sub tiers get the tops above the stated interface — the constraint the flanking search is
     * trying to satisfy. Vacuously true when there are no tops to lift, or no interface asked for.
     *
     * @param list<Tier> $tiers
     */
    public static function reachesInterface(array $tiers, Stack $stack): bool
    {
        if ($stack->interfaceHeightM <= 0.0) {
            return true;
        }

        $hasTop = false;
        foreach ($tiers as $tier) {
            if (!$tier->isSub()) {
                $hasTop = true;
            }
        }

        return !$hasTop || StackMetrics::subHeight($tiers) + StackMetrics::EPSILON_M >= $stack->interfaceHeightM;
    }
}
