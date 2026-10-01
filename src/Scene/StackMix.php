<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * The mixed row, which is the one place a row may hold two device types at once.
 *
 * A row of nothing but the two SKRAMs is 1.240 m and the Achenbach row above it is 2.460 m, which is a stack
 * that gets wider as it rises. Putting the widest sub in the middle of the bottom row and flanking it with the
 * next sub makes that row 3.684 m and the rig a pyramid again — so mixing is a repair for a specific shape
 * problem rather than a freedom the solver enjoys.
 *
 * Two callers with the same mechanism and different authority, which is the distinction to keep while reading:
 *
 * * {@see mixedBottomRow} is the heuristic. It fires only to remove an inversion and refuses to widen a rig
 *   that was already the right shape, because a mix nobody asked for is a rig nobody recognises.
 * * {@see statedMix} is the instruction. A scene that names `mix_with` gets the mix whether or not it repairs
 *   anything, and the rules it still has to obey are the bearing ones rather than the shape ones.
 *
 * Depends on {@see StackMetrics} alone, so it can be read without any of the rest of the solve.
 */
final class StackMix
{
    /**
     * Every `mix_with` that cannot be honoured, so it is refused rather than quietly ignored.
     *
     * A mix that silently does not happen is the worst outcome available: the rig still builds, the row is
     * just not the row that was asked for, and nothing in a render says so.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     *
     * @return list<string>
     */
    public static function mixProblems(array $inventory, Stack $stack): array
    {
        $heights = [];
        foreach ($inventory as [$device, $count]) {
            $heights[$device->id] = $device->dimensions->height;
        }

        $messages = [];
        foreach ($inventory as [$device, $count]) {
            foreach ($stack->entryFor($device->id)->mixWith ?? [] as $otherId) {
                if (!isset($heights[$otherId])) {
                    $messages[] = sprintf(
                        "stack.from '%s': mix_with names '%s', which is not in this stack",
                        $device->id,
                        $otherId,
                    );
                    continue;
                }
            }
        }

        return $messages;
    }

    /**
     * The tier `$index` asked for by name with `mix_with`, or null when it asked for nothing.
     *
     * The difference from {@see mixedBottomRow} is who decided. That one is a heuristic and only ever fires on
     * the bottom row, to rescue a device whose own row would be narrower than the row above it. This one is
     * the scene author saying "these share a row", at whatever height that device sits — which is what lifts
     * mixing off the bottom.
     *
     * Heights need not match: a mixed row simply has an uneven top, and {@see Stack::runsFor} lands each
     * cabinet above it on whatever is actually under that cabinet.
     *
     * Takes only as many flanking cabinets as fit the row, in pairs, and leaves the rest in `$remaining`. The search
     * budget and the support are both widths now, so the row answers to the tighter of the two rather than to a count
     * on one side and a width on the other.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{Tier, list<array{DeviceSpec, int}>}|null
     */
    public static function statedMix(array $remaining, int $index, Stack $stack, RowBudget $budget, float $supportM = INF): ?array
    {
        [$device, $count] = $remaining[$index];
        $wanted = $stack->entryFor($device->id)->mixWith ?? [];
        if ([] === $wanted) {
            return null;
        }

        $segments = [];
        foreach ($wanted as $otherId) {
            foreach ($remaining as $other => [$otherDevice, $otherCount]) {
                if ($other === $index || $otherDevice->id !== $otherId || $otherCount < 1) {
                    continue;
                }
                $segments[$other] = [$otherDevice, $otherCount];
            }
        }
        if ([] === $segments) {
            return null;
        }

        // AS MANY FLANKERS AS FIT, IN PAIRS — not the whole stock. Taking every cabinet of the flanking device put
        // all eight GMSS turbo subs either side of three middle subs and made a 6.065 m row on a 5 m stage, which
        // the bounds check then refused; the mix that was supposed to widen a narrow tier killed the whole
        // arrangement instead. Pairs, because a row with one more cabinet on the left than the right is not the
        // symmetric shape this is for, and whatever does not fit stays in `$remaining` for its own rows.
        $roll = StackMetrics::rollFor($device, $stack);
        $gap = $stack->gapM;
        // Device-independent for the same reason {@see StackSolver::packTo} is: this row holds the flanking types as well as its
        // own, so a per-device reading of the budget would widen it every time a wider flanker was tried.
        $ceiling = RowBudget::narrower(
            $budget->widthM,
            StackMetrics::ceilingFor($device, $stack, $roll, $supportM),
        ) ?? INF;
        $rowCount = $count;

        $rowWidth = $count * RolledBox::widthOf($device, $roll) + ($count - 1) * $gap;
        $used = [];

        $added = true;
        while ($added) {
            $added = false;
            foreach ($segments as $other => [$otherDevice, $otherCount]) {
                $taken = $used[$other] ?? 0;
                if ($otherCount - $taken < 2 || $rowCount + 2 > $budget->seats) {
                    continue;
                }
                $width = $rowWidth + 2 * (RolledBox::widthOf($otherDevice, StackMetrics::rollFor($otherDevice, $stack)) + $gap);
                if ($width > $ceiling + StackMetrics::EPSILON_M) {
                    continue;
                }
                $used[$other] = $taken + 2;
                $rowWidth = $width;
                $rowCount += 2;
                $added = true;
            }
        }

        // Nothing fitted, so this is not a mixed row at all — hand the device back and let the ordinary path deal
        // it. Emitting a "mix" of one segment would be a row with a misleading label and no flanks.
        if ([] === $used) {
            return null;
        }

        $left = [];
        $right = [];
        foreach ($used as $other => $take) {
            $otherDevice = $segments[$other][0];
            $half = intdiv($take, 2);
            $left[] = [$otherDevice, $half, StackMetrics::rollFor($otherDevice, $stack)];
            $right[] = [$otherDevice, $half, StackMetrics::rollFor($otherDevice, $stack)];
            $remaining[$other] = [$otherDevice, $segments[$other][1] - $take];
        }
        $remaining[$index] = [$device, 0];

        return [
            new Tier([...array_reverse($left), [$device, $count, $roll], ...$right]),
            $remaining,
        ];
    }

    /**
     * The bottom row, with the widest sub centred and the next sub flanking it, or null when there is
     * nothing to mix.
     *
     * **Mixing happens only to remove an inverted step**, and that gate matters more than it looks. The
     * obvious rule — "mix whenever the widest sub cannot fill a row on its own" — restructures rigs that
     * were already fine. With the SKRAMs left out, the widest sub is the *Achenbach* (0.600 m against the
     * Flexy's 0.591 m) with four owned against a six-per-row fit, so that rule would drag the Achenbachs
     * into the bottom row and stand them *under* the Flexys.
     *
     * The real question is whether this device's own row would be narrower than the row that comes to stand
     * on it. The SKRAM's would (1.240 m under the Achenbachs' 2.460 m) and it is mixed; the Achenbach's
     * would not (2.460 m under the Tecnares' 1.540 m) and it is left where the author put it.
     *
     * `$pairs` is how wide the flanks may be, decided by {@see StackSolver::fill} rather than grown here: a wider bottom
     * row is paid for out of the tiers above it, so how much to spend is a question about the whole stack.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{Tier, list<array{DeviceSpec, int}>}|null
     */
    public static function mixedBottomRow(array $remaining, Stack $stack, RowBudget $budget, int $pairs): ?array
    {
        $centre = self::widestSub($remaining, $stack);
        if (null === $centre) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        $fits = StackMetrics::perTier(
            $device,
            RowBudget::narrower($budget->ceilingFor($device, $stack, StackMetrics::rollFor($device, $stack)), $stack->maxWidthM),
            $stack->gapM,
            StackMetrics::rollFor($device, $stack),
        );

        $ownRow = Tier::of($device, min($available, $fits), StackMetrics::rollFor($device, $stack))->widthM($stack->gapM);
        $rowAbove = self::rowAbove($remaining, $centre, $stack, $budget);
        if (null === $rowAbove || $ownRow + StackMetrics::EPSILON_M >= $rowAbove) {
            // Nothing stands on it, or what does is no wider — there is no inversion to remove, so leave
            // the order the author wrote alone.
            return null;
        }

        $flank = StackMetrics::flankingSub($remaining, $centre, $device);
        if (null === $flank) {
            return null;
        }

        [$flankDevice, $flankAvailable] = $remaining[$flank];

        // **THE CENTRE MAY NOT BE SHORTER THAN ITS FLANKS**, or the row has a crater in the middle of it rather than
        // a step at its shoulders. This rule centres the *widest* sub, which was safe while width and height ran
        // together, and the GMSS mid bass broke that: at 1.200 × 0.500 it is the widest cabinet in either system and
        // also by far the shortest, so it was centred between two 1.400 m wall basses. What then stands on the row
        // lands on the flanks and hangs over a 900 mm void — the IQ subs above it came out 130 mm *inside* a wall
        // bass, which is the overlap sweep's job to catch and the fill's job not to propose.
        //
        // Refused rather than reordered: the mid bass genuinely is the widest thing here, so there is nothing to
        // swap it with, and its own row is the honest answer. See {@see StackMetrics::centred}, where the packed path solves the
        // same problem by putting the tallest in the middle instead of the widest.
        if (RolledBox::heightOf($device, StackMetrics::rollFor($device, $stack))
            + StackMetrics::EPSILON_M < RolledBox::heightOf($flankDevice, StackMetrics::rollFor($flankDevice, $stack))) {
            return null;
        }

        // Symmetric pairs, so the row stays centred and the widest cabinets stay in the middle where the
        // weight belongs. How many is `fill`'s decision, capped by what is actually in the building.
        $pairs = min($pairs, intdiv($flankAvailable, 2));
        if ($pairs < 1) {
            return null;
        }

        $remaining[$centre] = [$device, 0];
        $remaining[$flank] = [$flankDevice, $flankAvailable - 2 * $pairs];

        return [
            new Tier([
                [$flankDevice, $pairs, StackMetrics::rollFor($flankDevice, $stack)],
                [$device, $available, StackMetrics::rollFor($device, $stack)],
                [$flankDevice, $pairs, StackMetrics::rollFor($flankDevice, $stack)],
            ]),
            $remaining,
        ];
    }

    /**
     * How far apart in height two sub types may be and still count as one row height to {@see flankedRows}.
     *
     * **Three centimetres, because that is what the rig on Innschleife's own photo needs and nothing more.** Their
     * SBH lies at 0.550 m and their WSX at 0.570 m, and they build them in one row anyway. Each cabinet lands on the
     * one under it, see {@see Gravity}, so a repeated row stands as columns and the 20 mm step only reaches the row
     * that is not repeated, where {@see StackChecks::supportChecks} weighs it like any other step.
     */
    public const LEVEL_ROWS_TOLERANCE_M = 0.03;

    /**
     * The sub types that could flank the widest one row after row, because they stand as high as it does.
     *
     * Every one is a candidate, since which flank makes the better rig is the ranking's question. Innschleife's
     * kicker stands 0.570 m high like the WSX, so both are offered around the SBH and the photo picks the WSX.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return list<int>
     */
    public static function levelFlanks(array $remaining, Stack $stack): array
    {
        $centre = self::widestSub($remaining, $stack);
        if (null === $centre) {
            return [];
        }
        $device = $remaining[$centre][0];
        $height = RolledBox::heightOf($device, StackMetrics::rollFor($device, $stack));

        $flanks = [];
        foreach ($remaining as $index => [$flank, $count]) {
            if ($index === $centre || $count < 2 || 'sub' !== $flank->subtype) {
                continue;
            }
            $flankHeight = RolledBox::heightOf($flank, StackMetrics::rollFor($flank, $stack));
            if (abs($flankHeight - $height) <= self::LEVEL_ROWS_TOLERANCE_M + StackMetrics::EPSILON_M) {
                $flanks[] = $index;
            }
        }

        return $flanks;
    }

    /**
     * The widest sub split evenly over several rows, **each row flanked by the same pairs of `$flank`**, or null
     * when that cannot be built.
     *
     * **The arrangement on Innschleife's photo of 2026-10-01**, two rows of [WSX | SBH SBH | WSX], and one nothing
     * else here proposes. {@see mixedBottomRow} flanks a single row and puts the whole centre type in it, which
     * makes [WSX | 4× SBH | WSX] at 7.0 m. {@see StackSolver::spreadRows} deals the centre type one to a row and only
     * under the `central` bias. Neither repeats a flanked row.
     *
     * **The fewest rows that fit, from two up.** The centre count has to divide evenly so every row is the same, and
     * every row takes the same pairs of flanks so the wall stays symmetric. Fewer rows are wider and therefore lower,
     * so the first row count whose row fits the budget is the one built. Flanks left over are dealt above it like
     * any other cabinet.
     *
     * **The centre may be shorter than its flanks here, by {@see LEVEL_ROWS_TOLERANCE_M} at most.** The crater
     * guard of {@see mixedBottomRow} exists because a different row lands on such a row and hangs over the hole. A
     * repeated row lands centre on centre and flank on flank, so the hole only reaches the last of them.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{list<Tier>, list<array{DeviceSpec, int}>}|null
     */
    public static function flankedRows(array $remaining, Stack $stack, RowBudget $budget, int $flank): ?array
    {
        $centre = self::widestSub($remaining, $stack);
        if (null === $centre || !in_array($flank, self::levelFlanks($remaining, $stack), true)) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        [$flankDevice, $flankAvailable] = $remaining[$flank];
        $roll = StackMetrics::rollFor($device, $stack);
        $flankRoll = StackMetrics::rollFor($flankDevice, $stack);
        $ceiling = RowBudget::narrower($budget->ceilingFor($device, $stack, $roll), $stack->maxWidthM);

        for ($rows = 2; $rows <= $available; ++$rows) {
            $pairs = intdiv($flankAvailable, 2 * $rows);
            if (0 !== $available % $rows || $pairs < 1) {
                continue;
            }
            $row = new Tier([
                [$flankDevice, $pairs, $flankRoll],
                [$device, intdiv($available, $rows), $roll],
                [$flankDevice, $pairs, $flankRoll],
            ]);
            if (null !== $ceiling && $row->widthM($stack->gapM) > $ceiling + StackMetrics::EPSILON_M) {
                continue;
            }

            $remaining[$centre] = [$device, 0];
            $remaining[$flank] = [$flankDevice, $flankAvailable - 2 * $pairs * $rows];

            return [array_fill(0, $rows, $row), $remaining];
        }

        return null;
    }

    /**
     * How wide the first row of the next device with anything left would be — the row that comes to stand
     * on `$index`'s. Null when nothing does, which is the top of the stack.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    public static function rowAbove(array $remaining, int $index, Stack $stack, RowBudget $budget): ?float
    {
        foreach ($remaining as $next => [$device, $count]) {
            if ($next <= $index || $count < 1) {
                continue;
            }

            $perTier = StackMetrics::perTier(
                $device,
                RowBudget::narrower($budget->ceilingFor($device, $stack, StackMetrics::rollFor($device, $stack)), $stack->maxWidthM),
                $stack->gapM,
                StackMetrics::rollFor($device, $stack),
            );
            $rows = (int) ceil($count / $perTier);

            return Tier::of($device, StackMetrics::share($count, $rows)[0], StackMetrics::rollFor($device, $stack))
                ->widthM($stack->gapM);
        }

        return null;
    }

    /**
     * Which entry holds the widest sub, or null when there are no subs. Ties go to the earlier entry, so
     * the answer never depends on array order alone.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    public static function widestSub(array $remaining, Stack $stack): ?int
    {
        $best = null;
        foreach ($remaining as $index => [$device, $count]) {
            if ($count < 1 || 'sub' !== $device->subtype) {
                continue;
            }
            if (null === $best) {
                $best = $index;
                continue;
            }
            $incumbent = $remaining[$best][0];
            if (RolledBox::widthOf($device, StackMetrics::rollFor($device, $stack))
                > RolledBox::widthOf($incumbent, StackMetrics::rollFor($incumbent, $stack)) + StackMetrics::EPSILON_M) {
                $best = $index;
            }
        }

        return $best;
    }
}
