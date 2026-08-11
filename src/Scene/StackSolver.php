<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Fills a {@see Stack}'s cabinets into rows, bottom up, and says so when the constraints cannot be met.
 *
 * Deliberately pure — inventory and bounds in, tiers out — so the awkward cases can be tested against the
 * real gear list without a scene, a focus or a Blender run anywhere near it.
 *
 * Three rules do the work, and each of them exists because the naive version produced a rig nobody could
 * build:
 *
 * * **The bottom row may be mixed.** A row of nothing but the two SKRAMs is 1.240 m, and the Achenbach row
 *   above it is 2.460 m — a stack that gets wider as it rises. Putting the widest sub in the *middle* of the
 *   bottom row and flanking it with the next sub makes that row 3.684 m and the rig a pyramid again.
 * * **Rows of one device are balanced, not greedy.** Eight Flexys at six-per-row used to come out 6 + 2,
 *   and a 1.222 m row cannot carry the 2.460 m one above it. Balanced, they are 4 + 4 at 2.444 m and it can.
 * * **Support is checked.** A tier wider than the one it stands on is reported with the overhang, because
 *   nothing else in the pipeline notices: `on:` only reads a top face, and the shipped-scene check only
 *   catches cabinets *inside* each other, never one standing on air.
 *
 * The one thing to keep in mind while reading it: **no step of this may assume a common module.** Five
 * cabinets, five widths, five heights, nothing a multiple of anything, so every row count is worked out per
 * device and every height is summed rather than multiplied.
 */
final class StackSolver
{
    /** Float slack when comparing a fit — a micrometre, far below anything a cabinet is measured to. */
    private const EPSILON_M = 1e-9;


    /**
     * @param list<array{DeviceSpec, int}> $inventory device and how many of it, low frequency first
     * @return array{tiers: list<Tier>, problems: list<string>, warnings: list<string>}
     */
    public static function solve(array $inventory, Stack $stack): array
    {
        $ordering = self::orderingProblems($inventory);
        if ($ordering !== []) {
            return ['tiers' => [], 'problems' => $ordering, 'warnings' => []];
        }

        $tiers = self::fill($inventory, $stack);
        if ($stack->mirror) {
            // Reflected before anything is checked, and it changes none of the answers: every check reads widths,
            // heights and labels, and a mirror image has exactly the ones its original had.
            $tiers = array_map(static fn (Tier $tier): Tier => $tier->flipped(), $tiers);
        }
        if ($tiers === []) {
            return [
                'tiers' => [],
                'problems' => ['stack.from: none of the devices listed has anything to place'],
                'warnings' => [],
            ];
        }

        ['problems' => $unsupported, 'warnings' => $support] = StackChecks::supportChecks($tiers, $stack);
        ['problems' => $bounds, 'warnings' => $missedInterface] = StackChecks::boundsProblems($tiers, $stack);

        return [
            'tiers' => $tiers,
            'problems' => [...$bounds, ...$unsupported, ...self::mixProblems($inventory, $stack)],
            'warnings' => [...$missedInterface, ...$support],
        ];
    }

    /**
     * Subs before tops, because the fill is bottom-up and the sub/top interface is a boundary in the
     * finished stack rather than a filter over the list. A top listed before a sub would put a Tecnare
     * under a Flexy and still satisfy every height check, which is the sort of plausible nonsense worth
     * refusing outright.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<string>
     */
    private static function orderingProblems(array $inventory): array
    {
        $seenTop = null;
        foreach ($inventory as [$device, $count]) {
            if ($count < 1) {
                continue;
            }
            if ($device->subtype !== 'sub') {
                $seenTop ??= $device->id;
                continue;
            }
            if ($seenTop !== null) {
                return [sprintf(
                    "stack.from: subs come before tops, and '%s' is listed after '%s'",
                    $device->id,
                    $seenTop,
                )];
            }
        }

        return [];
    }

    /**
     * The rows themselves, bottom up.
     *
     * A search over one number: how many cabinets go in a row. Wider rows mean fewer of them, so the sub
     * stack gets *shorter* as the row gets wider — and the tops have to clear the interface height. So the
     * answer is the **widest row that still gets the tops up**, and it is found by trying the widest first
     * and narrowing.
     *
     * That search is what `max_width_m` alone could not do. A bound is a *maximum*, not a target: on a 10 m
     * stage every device fits in one row, which leaves two sub tiers at 1.363 m and a 2 m interface out of
     * reach forever. Narrowing the rows is the only way to gain height out of a fixed pile of cabinets, and
     * refusing to narrow made a wide stage strictly worse than a narrow one.
     *
     * **Support outranks the interface**, and the order of those two preferences is the whole design. The
     * interface height is an optimum; a tier standing on air is impossible. Narrowing far enough to chase a
     * tall interface eventually leaves the tops overhanging a one-wide sub column — at a 12 m interface the
     * Flexys go to single columns and the three-wide Tecnare row ends up 470 mm off each edge. So an
     * arrangement with an unsupported tier is never chosen while any supported one exists, even if the
     * unsupported one would have reached the height.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fill(array $inventory, Stack $stack): array
    {
        $widest = 0;
        foreach ($inventory as [$device, $count]) {
            $widest = max($widest, min($count, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack))));
        }

        $tallestCarried = [];
        $tallestCarriedSubs = -INF;
        $widestAttempt = [];

        for ($perRow = $widest; $perRow >= 1; --$perRow) {
            $flanking = self::flankingPairs($inventory, $stack, $perRow);
            for ($pairs = $flanking; $pairs >= 0; --$pairs) {
                $tiers = self::fillWith($inventory, $stack, $perRow, $pairs);
                $widestAttempt = $widestAttempt === [] ? $tiers : $widestAttempt;

                if (StackChecks::supportChecks($tiers, $stack)['problems'] !== []) {
                    continue;
                }
                if (self::reachesInterface($tiers, $stack)) {
                    return $tiers;
                }

                $subs = self::subHeight($tiers);
                if ($subs > $tallestCarriedSubs) {
                    $tallestCarriedSubs = $subs;
                    $tallestCarried = $tiers;
                }
            }
        }
        if ($tallestCarried !== []) {
            // Stands up but sits lower than asked for, which is a warning.
            return $tallestCarried;
        }

        // Nothing stands up at any row width. Hand back the **widest** attempt rather than the tallest, so the
        // error names the most favourable case there was: "even at its widest it overhangs 610 mm" tells you
        // the rig is impossible, where the narrowest attempt's 956 mm would just look like a bad guess.
        return $widestAttempt;
    }

    /**
     * One arrangement: the mixed bottom row at `$pairs` per side, then a balanced set of rows per device.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fillWith(array $inventory, Stack $stack, int $perRow, int $pairs): array
    {
        $remaining = [];
        foreach ($inventory as $index => [$device, $count]) {
            $remaining[$index] = [$device, $count];
        }

        $tiers = [];
        if ($pairs > 0) {
            $bottom = self::mixedBottomRow($remaining, $stack, $perRow, $pairs);
            if ($bottom !== null) {
                [$tiers[], $remaining] = $bottom;
            }
        }

        [$lifts, $remaining] = self::reserveLifts($remaining, $stack, $perRow);

        // Subs stack; tops do not. A sub row carries the row above it, so running out of width means another
        // tier. Tops carry nothing and stand side by side on the sub stack — putting a 2-way *on* a Tecnare
        // is what produced a fill hovering over the middle of the rig, and it is not how anybody rigs a PA.
        foreach ($remaining as $index => [$device, $count]) {
            if ($count < 1 || $device->subtype !== 'sub') {
                continue;
            }

            // A tier that asked to share its row does so here, wherever it sits — mixing used to be the
            // bottom row's privilege alone, decided by a heuristic. `mix_with` names it outright, and the
            // same two gates still apply: matching heights, and the devices have to exist and be free.
            $stated = self::statedMix($remaining, $index, $stack, $perRow);
            if ($stated !== null) {
                [$tiers[], $remaining] = $stated;
                continue;
            }

            // Cabinets held back from the rows below, standing either side of this one to close the step.
            if (isset($lifts[$index])) {
                [$source, $lift] = $lifts[$index];
                $tiers[] = new Tier([
                    [$source, $lift, self::rollFor($source, $stack)],
                    [$device, $count, self::rollFor($device, $stack)],
                    [$source, $lift, self::rollFor($source, $stack)],
                ]);
                $remaining[$index] = [$device, 0];
                continue;
            }

            $roll = self::rollFor($device, $stack);
            $perTier = self::rowSizeFor($device, $count, $stack, $perRow, $roll);
            // Balanced rather than greedy: the same number of rows, but no short one left at the top to
            // fail to carry whatever is above it.
            $rows = (int)ceil($count / $perTier);
            foreach (self::share($count, $rows) as $row) {
                $tiers[] = Tier::of($device, $row, $roll);
            }
        }

        $tops = self::topRow($remaining, $stack);
        if ($tops !== null) {
            $tiers[] = $tops;
        }

        // The mirror last, in one place, so it catches every tier however it was built — a plain row, a mixed
        // bottom row, a flanked one, the tops. Splitting a rolled segment about the row's own centre is what
        // makes the rig symmetric about its centre line rather than about each segment.
        return array_map(static fn (Tier $tier): Tier => $tier->mirrored(), $tiers);
    }

    /**
     * Cabinets held back from a lower device's rows to stand either side of the tier above it.
     *
     * **This is {@see mixedBottomRow}'s rule read one word differently.** That one mixes "only to remove an
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
     * @return array{array<int, array{DeviceSpec, int}>, list<array{DeviceSpec, int}>} lifts by target index,
     *     and what is left to fill rows with
     */
    private static function reserveLifts(array $remaining, Stack $stack, int $perRow): array
    {
        $lifts = [];

        foreach (array_keys($remaining) as $source) {
            [$device, $count] = $remaining[$source];
            // A device already being flanked has no rows of its own left to lend from.
            if ($count < 1 || $device->subtype !== 'sub' || isset($lifts[$source])) {
                continue;
            }

            $lift = self::liftAbove($remaining, $source, $stack, $perRow);
            if ($lift === null) {
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
     * Split out because **two callers have to agree**: the reservation pass above, and {@see widthAbove}, which
     * decides how wide the mixed bottom row may grow. That one asks how wide the row above the bottom will be,
     * and the answer changes if some of those cabinets are about to be lifted a tier — which is exactly the
     * coupling that kept the flat wall out of reach. Left to itself the bottom row grew to 3 pairs and 4.906 m,
     * because the eight Flexys left over came to 4.868 m in one row and anything narrower would have been
     * overhung. Knowing two of them go up instead, six come to 3.646 m and 2 pairs is enough.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     * @return array{int, int}|null target index and pairs per side
     */
    private static function liftAbove(array $remaining, int $source, Stack $stack, int $perRow): ?array
    {
        [$sourceDevice, $sourceCount] = $remaining[$source];
        if ($sourceCount < 2 || ($stack->entryFor($sourceDevice->id)?->mixWith ?? []) !== []) {
            return null;
        }

        foreach ($remaining as $index => [$device, $count]) {
            if ($index <= $source || $count < 1 || $device->subtype !== 'sub') {
                continue;
            }

            // A tier that names its own row-mates has already said what it wants, and one that needs more than
            // a single row would have to say *which* of its rows gets the flanks. Neither is a guess to make.
            if (($stack->entryFor($device->id)?->mixWith ?? []) !== []) {
                return null;
            }
            if ($count > min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack)))) {
                return null;
            }

            $lift = self::liftPairs($device, $count, $sourceDevice, $sourceCount, $stack, $perRow);

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
    private static function liftPairs(
        DeviceSpec $target,
        int $targetCount,
        DeviceSpec $source,
        int $sourceCount,
        Stack $stack,
        int $perRow,
    ): int {
        $lift = 0;

        while (2 * ($lift + 1) <= $sourceCount) {
            $candidate = new Tier([
                [$source, $lift + 1, self::rollFor($source, $stack)],
                [$target, $targetCount, self::rollFor($target, $stack)],
                [$source, $lift + 1, self::rollFor($source, $stack)],
            ]);
            $width = $candidate->widthM($stack->gapM);

            if ($stack->maxWidthM !== null && $width > $stack->maxWidthM + self::EPSILON_M) {
                break;
            }

            $support = self::lastRowWidth($source, $sourceCount - 2 * ($lift + 1), $stack, $perRow);
            if (($width - $support) / 2 > $candidate->outerWidthM() / 2) {
                break;
            }

            ++$lift;

            if ($width + self::EPSILON_M >= $support) {
                break;
            }
        }

        return $lift;
    }

    /**
     * The quarter turn this device's tiers lie on, or 0 for upright.
     *
     * Read off the entry rather than passed around, so every place that builds a `Tier` measures the same
     * cabinet the expansion will place. A stack built without entries — which is how the solver's own tests
     * construct one — has nothing to say and everything stays upright.
     */
    private static function rollFor(DeviceSpec $device, Stack $stack): float
    {
        return $stack->entryFor($device->id)?->rollMirror ?? 0.0;
    }

    /**
     * How wide the source's **last** row comes out — the one that ends up directly under the flanked tier.
     *
     * Last rather than first because {@see share} puts the fuller row at the bottom, so the top of a device's
     * own stack is its narrowest row and that is what the tier above actually stands on.
     */
    private static function lastRowWidth(DeviceSpec $device, int $count, Stack $stack, int $perRow): float
    {
        if ($count < 1) {
            return 0.0;
        }

        $perTier = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack)));
        $shares = self::share($count, (int)ceil($count / $perTier));

        return Tier::of($device, $shares[count($shares) - 1], self::rollFor($device, $stack))->widthM($stack->gapM);
    }

    /**
     * Every `mix_with` that cannot be honoured, so it is refused rather than quietly ignored.
     *
     * A mix that silently does not happen is the worst outcome available: the rig still builds, the row is
     * just not the row that was asked for, and nothing in a render says so.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<string>
     */
    private static function mixProblems(array $inventory, Stack $stack): array
    {
        $heights = [];
        foreach ($inventory as [$device, $count]) {
            $heights[$device->id] = $device->dimensions->height;
        }

        $messages = [];
        foreach ($inventory as [$device, $count]) {
            foreach ($stack->entryFor($device->id)?->mixWith ?? [] as $otherId) {
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
     * @param list<array{DeviceSpec, int}> $remaining
     * @return array{Tier, list<array{DeviceSpec, int}>}|null
     */
    private static function statedMix(array $remaining, int $index, Stack $stack, int $perRow): ?array
    {
        [$device, $count] = $remaining[$index];
        $wanted = $stack->entryFor($device->id)?->mixWith ?? [];
        if ($wanted === []) {
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
        if ($segments === []) {
            return null;
        }

        // The named device in the middle, the rest split symmetrically around it — the same shape a mixed
        // bottom row and a top row both take, because the biggest cluster belongs in the middle.
        $left = [];
        $right = [];
        foreach ($segments as $other => [$otherDevice, $otherCount]) {
            $share = intdiv($otherCount, 2) + ($otherCount % 2);
            if ($share > 0) {
                $left[] = [$otherDevice, $share, self::rollFor($otherDevice, $stack)];
            }
            if ($otherCount - $share > 0) {
                $right[] = [$otherDevice, $otherCount - $share, self::rollFor($otherDevice, $stack)];
            }
            $remaining[$other] = [$otherDevice, 0];
        }
        $remaining[$index] = [$device, 0];

        return [
            new Tier([...array_reverse($left), [$device, $count, self::rollFor($device, $stack)], ...$right]),
            $remaining,
        ];
    }

    /**
     * Every top in **one** row, widest in the middle and the rest split symmetrically around it.
     *
     * Not split across tiers however wide it comes out, because that is what the caller asked for and it is
     * also the physical truth: nothing stands on the tops, so width is the only thing they cost. If the row
     * is wider than `max_width_m` that is reported by {@see StackChecks::boundsProblems} rather than quietly turned into
     * a second tier of tops balanced on the first.
     *
     * The widest goes in the middle for the same reason it does in a mixed bottom row: it is the main
     * cluster, and the smaller boxes are fills that belong outboard of it.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    private static function topRow(array $remaining, Stack $stack): ?Tier
    {
        $tops = [];
        foreach ($remaining as [$device, $count]) {
            if ($count > 0 && $device->subtype !== 'sub') {
                $tops[] = [$device, $count];
            }
        }
        if ($tops === []) {
            return null;
        }

        usort($tops, static fn (array $a, array $b): int => RolledBox::widthOf($b[0], self::rollFor($b[0], $stack))
            <=> RolledBox::widthOf($a[0], self::rollFor($a[0], $stack)));
        $centre = array_shift($tops);
        $centre[] = self::rollFor($centre[0], $stack);

        $left = [];
        $right = [];
        foreach ($tops as [$device, $count]) {
            $share = intdiv($count, 2);
            if ($count - 2 * $share > 0) {
                ++$share;
            }
            if ($share > 0) {
                $left[] = [$device, $share, self::rollFor($device, $stack)];
            }
            if ($count - $share > 0) {
                $right[] = [$device, $count - $share, self::rollFor($device, $stack)];
            }
        }

        return new Tier([...array_reverse($left), $centre, ...$right]);
    }

    /**
     * The most flanking pairs a mixed bottom row could take — the widest it is allowed to be.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    private static function flankingPairs(array $inventory, Stack $stack, int $perRow): int
    {
        $centre = self::widestSub($inventory, $stack);
        if ($centre === null) {
            return 0;
        }
        [$device, $available] = $inventory[$centre];

        $flank = self::flankingSub($inventory, $centre, $device);
        if ($flank === null) {
            return 0;
        }

        [$flankDevice, $flankAvailable] = $inventory[$flank];

        $pairs = 0;
        while (2 * ($pairs + 1) <= $flankAvailable) {
            $candidate = new Tier([
                [$flankDevice, $pairs + 1, self::rollFor($flankDevice, $stack)],
                [$device, $available, self::rollFor($device, $stack)],
                [$flankDevice, $pairs + 1, self::rollFor($flankDevice, $stack)],
            ]);
            $width = $candidate->widthM($stack->gapM);

            if ($stack->maxWidthM !== null && $width > $stack->maxWidthM + self::EPSILON_M) {
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

            if ($width + self::EPSILON_M >= self::widthAbove($flankDevice, $flankAvailable - 2 * $pairs, $probe, $flank, $stack, $perRow)) {
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
    private static function widthAbove(
        DeviceSpec $flankDevice,
        int $leftOver,
        array $inventory,
        int $flank,
        Stack $stack,
        int $perRow,
    ): float {
        if ($leftOver > 0) {
            // Cabinets destined for the tier above are not in this row, see {@see liftAbove}.
            $leftOver -= 2 * (self::liftAbove($inventory, $flank, $stack, $perRow)[1] ?? 0);
        }
        if ($leftOver > 0) {
            $perTier = min($perRow, self::perTier($flankDevice, $stack->maxWidthM, $stack->gapM, self::rollFor($flankDevice, $stack)));
            $rows = (int)ceil($leftOver / $perTier);

            return Tier::of($flankDevice, self::share($leftOver, $rows)[0], self::rollFor($flankDevice, $stack))
                ->widthM($stack->gapM);
        }

        return self::rowAbove($inventory, $flank, $stack, $perRow) ?? 0.0;
    }

    /**
     * How high the sub tiers reach.
     *
     * @param list<Tier> $tiers
     */
    private static function subHeight(array $tiers): float
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
     * Whether the sub tiers get the tops above the stated interface — the constraint the flanking search is
     * trying to satisfy. Vacuously true when there are no tops to lift, or no interface asked for.
     *
     * @param list<Tier> $tiers
     */
    private static function reachesInterface(array $tiers, Stack $stack): bool
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

        return !$hasTop || self::subHeight($tiers) + self::EPSILON_M >= $stack->interfaceHeightM;
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
     * `$pairs` is how wide the flanks may be, decided by {@see fill} rather than grown here: a wider bottom
     * row is paid for out of the tiers above it, so how much to spend is a question about the whole stack.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     * @return array{Tier, list<array{DeviceSpec, int}>}|null
     */
    private static function mixedBottomRow(array $remaining, Stack $stack, int $perRow, int $pairs): ?array
    {
        $centre = self::widestSub($remaining, $stack);
        if ($centre === null) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        $fits = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack)));

        $ownRow = Tier::of($device, min($available, $fits), self::rollFor($device, $stack))->widthM($stack->gapM);
        $rowAbove = self::rowAbove($remaining, $centre, $stack, $perRow);
        if ($rowAbove === null || $ownRow + self::EPSILON_M >= $rowAbove) {
            // Nothing stands on it, or what does is no wider — there is no inversion to remove, so leave
            // the order the author wrote alone.
            return null;
        }

        $flank = self::flankingSub($remaining, $centre, $device);
        if ($flank === null) {
            return null;
        }

        [$flankDevice, $flankAvailable] = $remaining[$flank];

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
                [$flankDevice, $pairs, self::rollFor($flankDevice, $stack)],
                [$device, $available, self::rollFor($device, $stack)],
                [$flankDevice, $pairs, self::rollFor($flankDevice, $stack)],
            ]),
            $remaining,
        ];
    }

    /**
     * How wide the first row of the next device with anything left would be — the row that comes to stand
     * on `$index`'s. Null when nothing does, which is the top of the stack.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    private static function rowAbove(array $remaining, int $index, Stack $stack, int $perRow): ?float
    {
        foreach ($remaining as $next => [$device, $count]) {
            if ($next <= $index || $count < 1) {
                continue;
            }

            $perTier = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack)));
            $rows = (int)ceil($count / $perTier);

            return Tier::of($device, self::share($count, $rows)[0], self::rollFor($device, $stack))
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
    private static function widestSub(array $remaining, Stack $stack): ?int
    {
        $best = null;
        foreach ($remaining as $index => [$device, $count]) {
            if ($count < 1 || $device->subtype !== 'sub') {
                continue;
            }
            if ($best === null) {
                $best = $index;
                continue;
            }
            $incumbent = $remaining[$best][0];
            if (RolledBox::widthOf($device, self::rollFor($device, $stack))
                > RolledBox::widthOf($incumbent, self::rollFor($incumbent, $stack)) + self::EPSILON_M) {
                $best = $index;
            }
        }

        return $best;
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
    private static function flankingSub(array $remaining, int $centre, DeviceSpec $centreDevice): ?int
    {
        $best = null;
        foreach ($remaining as $index => [$device, $count]) {
            if ($index === $centre || $count < 2 || $device->subtype !== 'sub') {
                continue;
            }
            if ($best === null || $count > $remaining[$best][1]) {
                $best = $index;
            }
        }

        return $best;
    }

    /**
     * `$count` cabinets over `$rows` rows, as evenly as they go — 8 over 2 is 4 + 4, and 3 over 2 is
     * 2 + 1 with the fuller row at the bottom where it belongs.
     *
     * @return list<int>
     */
    private static function share(int $count, int $rows): array
    {
        $shares = [];
        for ($row = 0; $row < $rows; ++$row) {
            $shares[] = (int)ceil(($count - array_sum($shares)) / ($rows - $row));
        }

        return $shares;
    }

    /**
     * How many of a device go in a row — the search's row count, **widened if that would leave a pillar**.
     *
     * `$perRow` is the search variable: {@see fill} narrows it to buy height, because narrower rows mean more of
     * them. It is not a stated constraint, and applying it to *every* device is what produced the one arrangement
     * that could not be built. Three Achenbachs at two per row are dealt `2 + 1`, and a one-wide sub tier is
     * refused as a pillar — while all three in one row are 1.840 m and fit the stage with two metres to spare.
     *
     * So a pillar is worth one step of widening, and only as far as the width the scene actually stated. It is the
     * search's own preference being overruled by its own rule, not a constraint being relaxed: nothing here can
     * exceed `max_width_m`, and a device with fewer than two cabinets is left alone because a single cabinet *is*
     * a single column and there is nothing else it could be.
     */
    private static function rowSizeFor(DeviceSpec $device, int $count, Stack $stack, int $perRow, float $roll): int
    {
        $byWidth = self::perTier($device, $stack->maxWidthM, $stack->gapM, $roll);
        $perTier = min($perRow, $byWidth);

        if ($count < 2 || $perTier >= $count) {
            return $perTier;
        }

        // Widen only while the balanced split would still strand a row of one.
        for ($size = $perTier; $size <= min($count, $byWidth); ++$size) {
            if (!in_array(1, self::share($count, (int)ceil($count / $size)), true)) {
                return $size;
            }
        }

        return $perTier;
    }

    /**
     * How many of one cabinet fit across the stated width.
     *
     * `n` cabinets and `n − 1` gaps fit when `n·w + (n−1)·g ≤ W`, i.e. `n ≤ (W + g) / (w + g)`. At least
     * one, always: a stage narrower than a single cabinet is a bound the caller has to hear about as a
     * width failure, not something to silently turn into an empty rig.
     */
    private static function perTier(DeviceSpec $device, ?float $maxWidthM, float $gapM, float $rollDeg = 0.0): int
    {
        if ($maxWidthM === null) {
            return PHP_INT_MAX;
        }

        // The **rolled** width. Four Flexys on their sides fill a 3.70 m stage where six standing up do, and
        // fitting them by their nominal 591 mm put 3.895 m of cabinet on a 3.70 m stage.
        $fit = (int)floor(($maxWidthM + $gapM) / (RolledBox::widthOf($device, $rollDeg) + $gapM) + self::EPSILON_M);

        return max(1, $fit);
    }
}
