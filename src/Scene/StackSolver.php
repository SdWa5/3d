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
     * How far a tier may hang over the one below it before it is worth saying so, per side.
     *
     * A centimetre. Below that it is a cabinet edge sitting proud of a joint, which is normal and is what
     * the rubber feet and the working gaps absorb. Above it, something is standing on air.
     */
    private const OVERHANG_TOLERANCE_M = 0.01;

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
        if ($tiers === []) {
            return [
                'tiers' => [],
                'problems' => ['stack.from: none of the devices listed has anything to place'],
                'warnings' => [],
            ];
        }

        ['problems' => $unsupported, 'warnings' => $support] = self::supportChecks($tiers, $stack);
        ['problems' => $bounds, 'warnings' => $missedInterface] = self::boundsProblems($tiers, $stack);

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
            $widest = max($widest, min($count, self::perTier($device, $stack->maxWidthM, $stack->gapM)));
        }

        $tallestCarried = [];
        $tallestCarriedSubs = -INF;
        $widestAttempt = [];

        for ($perRow = $widest; $perRow >= 1; --$perRow) {
            $flanking = self::flankingPairs($inventory, $stack, $perRow);
            for ($pairs = $flanking; $pairs >= 0; --$pairs) {
                $tiers = self::fillWith($inventory, $stack, $perRow, $pairs);
                $widestAttempt = $widestAttempt === [] ? $tiers : $widestAttempt;

                if (self::supportChecks($tiers, $stack)['problems'] !== []) {
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

            $perTier = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM));
            // Balanced rather than greedy: the same number of rows, but no short one left at the top to
            // fail to carry whatever is above it.
            $rows = (int)ceil($count / $perTier);
            foreach (self::share($count, $rows) as $row) {
                $tiers[] = Tier::of($device, $row);
            }
        }

        $tops = self::topRow($remaining);
        if ($tops !== null) {
            $tiers[] = $tops;
        }

        return $tiers;
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
                $left[] = [$otherDevice, $share];
            }
            if ($otherCount - $share > 0) {
                $right[] = [$otherDevice, $otherCount - $share];
            }
            $remaining[$other] = [$otherDevice, 0];
        }
        $remaining[$index] = [$device, 0];

        return [
            new Tier([...array_reverse($left), [$device, $count], ...$right]),
            $remaining,
        ];
    }

    /**
     * Every top in **one** row, widest in the middle and the rest split symmetrically around it.
     *
     * Not split across tiers however wide it comes out, because that is what the caller asked for and it is
     * also the physical truth: nothing stands on the tops, so width is the only thing they cost. If the row
     * is wider than `max_width_m` that is reported by {@see boundsProblems} rather than quietly turned into
     * a second tier of tops balanced on the first.
     *
     * The widest goes in the middle for the same reason it does in a mixed bottom row: it is the main
     * cluster, and the smaller boxes are fills that belong outboard of it.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    private static function topRow(array $remaining): ?Tier
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

        usort($tops, static fn (array $a, array $b): int => $b[0]->dimensions->width <=> $a[0]->dimensions->width);
        $centre = array_shift($tops);

        $left = [];
        $right = [];
        foreach ($tops as [$device, $count]) {
            $share = intdiv($count, 2);
            if ($count - 2 * $share > 0) {
                ++$share;
            }
            if ($share > 0) {
                $left[] = [$device, $share];
            }
            if ($count - $share > 0) {
                $right[] = [$device, $count - $share];
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
        $centre = self::widestSub($inventory);
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
                [$flankDevice, $pairs + 1],
                [$device, $available],
                [$flankDevice, $pairs + 1],
            ]);
            if ($stack->maxWidthM !== null && $candidate->widthM($stack->gapM) > $stack->maxWidthM + self::EPSILON_M) {
                break;
            }
            ++$pairs;
        }

        return $pairs;
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
        $centre = self::widestSub($remaining);
        if ($centre === null) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        $fits = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM));

        $ownRow = Tier::of($device, min($available, $fits))->widthM($stack->gapM);
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
            new Tier([[$flankDevice, $pairs], [$device, $available], [$flankDevice, $pairs]]),
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

            $perTier = min($perRow, self::perTier($device, $stack->maxWidthM, $stack->gapM));
            $rows = (int)ceil($count / $perTier);

            return Tier::of($device, self::share($count, $rows)[0])->widthM($stack->gapM);
        }

        return null;
    }

    /**
     * Which entry holds the widest sub, or null when there are no subs. Ties go to the earlier entry, so
     * the answer never depends on array order alone.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     */
    private static function widestSub(array $remaining): ?int
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
            if ($device->dimensions->width > $incumbent->dimensions->width + self::EPSILON_M) {
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
     * How many of one cabinet fit across the stated width.
     *
     * `n` cabinets and `n − 1` gaps fit when `n·w + (n−1)·g ≤ W`, i.e. `n ≤ (W + g) / (w + g)`. At least
     * one, always: a stage narrower than a single cabinet is a bound the caller has to hear about as a
     * width failure, not something to silently turn into an empty rig.
     */
    private static function perTier(DeviceSpec $device, ?float $maxWidthM, float $gapM): int
    {
        if ($maxWidthM === null) {
            return PHP_INT_MAX;
        }

        $fit = (int)floor(($maxWidthM + $gapM) / ($device->dimensions->width + $gapM) + self::EPSILON_M);

        return max(1, $fit);
    }


    /**
     * Every bound the finished stack misses, each naming the number it reached and the number it needed.
     *
     * @param list<Tier> $tiers
     * @return array{problems: list<string>, warnings: list<string>}
     */
    private static function boundsProblems(array $tiers, Stack $stack): array
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
    private static function supportChecks(array $tiers, Stack $stack): array
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

            if ($overhang > $tier->outerWidthM() / 2) {
                $problems[] = $message.' — more than half of the outer cabinet is off the edge, so it stands '
                    .'on nothing. Narrow the tier, widen what carries it, or take the odd cabinets out of '
                    .'the stack';
                continue;
            }

            $warnings[] = $message;
        }

        return ['problems' => $problems, 'warnings' => $warnings];
    }
}