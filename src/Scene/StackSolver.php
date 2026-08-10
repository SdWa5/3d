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

        return [
            'tiers' => $tiers,
            'problems' => self::boundsProblems($tiers, $stack),
            'warnings' => self::supportWarnings($tiers, $stack),
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
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fill(array $inventory, Stack $stack): array
    {
        $uniform = $stack->maxWidthM === null ? self::widestThatReaches($inventory, $stack) : null;

        // A mixed bottom row is paid for out of the flanking device's stock, and those are the very cabinets
        // the sub tiers above are made of — so a wide row can eat the rig's own height. On a 10 m stage the
        // greedy answer swallowed all twelve Flexys into one 8.572 m row, left two sub tiers at 1.514 m and
        // could never reach a 2 m interface however the tops were arranged.
        //
        // So the flanking width is not taken greedily: try the widest bottom row first, and give a pair back
        // for as long as the stack misses the interface. Widest-that-works, which is the same rule
        // {@see widestThatReaches} already applies to the row count. The first attempt is what a bounded
        // stage produces anyway, so nothing that already fitted changes.
        $widest = null;
        for ($pairs = self::flankingPairs($inventory, $stack, $uniform); $pairs >= 0; --$pairs) {
            $tiers = self::fillWith($inventory, $stack, $uniform, $pairs);
            $widest ??= $tiers;

            if (self::reachesInterface($tiers, $stack)) {
                return $tiers;
            }
        }

        // Nothing reaches it. Hand back the widest attempt so the failure names the height it did reach
        // rather than the height of some narrower arrangement nobody asked for.
        return $widest ?? [];
    }

    /**
     * One arrangement: the mixed bottom row at `$pairs` per side, then a balanced set of rows per device.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fillWith(array $inventory, Stack $stack, ?int $uniform, int $pairs): array
    {
        $remaining = [];
        foreach ($inventory as $index => [$device, $count]) {
            $remaining[$index] = [$device, $count];
        }

        $tiers = [];
        if ($pairs > 0) {
            $bottom = self::mixedBottomRow($remaining, $stack, $uniform, $pairs);
            if ($bottom !== null) {
                [$tiers[], $remaining] = $bottom;
            }
        }

        foreach ($remaining as [$device, $count]) {
            if ($count < 1) {
                continue;
            }
            $perTier = $uniform ?? self::perTier($device, $stack->maxWidthM, $stack->gapM);
            // Balanced rather than greedy: the same number of rows, but no short one left at the top to
            // fail to carry whatever is above it.
            $rows = (int)ceil($count / $perTier);
            foreach (self::share($count, $rows) as $row) {
                $tiers[] = Tier::of($device, $row);
            }
        }

        return $tiers;
    }

    /**
     * The most flanking pairs a mixed bottom row could take — the widest it is allowed to be.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    private static function flankingPairs(array $inventory, Stack $stack, ?int $uniform): int
    {
        $centre = self::widestSub($inventory);
        if ($centre === null) {
            return 0;
        }
        $flank = self::flankingSub($inventory, $centre);
        if ($flank === null) {
            return 0;
        }

        [$device, $available] = $inventory[$centre];
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

        $subHeight = 0.0;
        $hasTop = false;
        foreach ($tiers as $tier) {
            $tier->isSub() ? $subHeight += $tier->heightM() : $hasTop = true;
        }

        return !$hasTop || $subHeight + self::EPSILON_M >= $stack->interfaceHeightM;
    }

    /**
     * The bottom row, with the widest sub centred and the next sub flanking it, or null when there is
     * nothing to mix.
     *
     * **Mixing happens only to remove an inverted step**, and that gate matters more than it looks. The
     * obvious rule — "mix whenever the widest sub cannot fill a row on its own" — restructures rigs that
     * were already fine: in `full-rig-stacked` the widest sub is the *Achenbach* (0.600 m against the
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
    private static function mixedBottomRow(array $remaining, Stack $stack, ?int $uniform, int $pairs): ?array
    {
        $centre = self::widestSub($remaining);
        if ($centre === null) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        $fits = $uniform ?? self::perTier($device, $stack->maxWidthM, $stack->gapM);

        $ownRow = Tier::of($device, min($available, $fits))->widthM($stack->gapM);
        $rowAbove = self::rowAbove($remaining, $centre, $stack, $uniform);
        if ($rowAbove === null || $ownRow + self::EPSILON_M >= $rowAbove) {
            // Nothing stands on it, or what does is no wider — there is no inversion to remove, so leave
            // the order the author wrote alone.
            return null;
        }

        $flank = self::flankingSub($remaining, $centre);
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
    private static function rowAbove(array $remaining, int $index, Stack $stack, ?int $uniform): ?float
    {
        foreach ($remaining as $next => [$device, $count]) {
            if ($next <= $index || $count < 1) {
                continue;
            }

            $perTier = $uniform ?? self::perTier($device, $stack->maxWidthM, $stack->gapM);
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
     * @param list<array{DeviceSpec, int}> $remaining
     */
    private static function flankingSub(array $remaining, int $centre): ?int
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
     * With no width bound, the widest row the subs can afford and still reach the interface height.
     *
     * Narrower rows mean more of them, so the sub stack gets taller as the count goes down: the reachable
     * height is non-increasing in the row count, and the answer is simply the largest count that still
     * clears. That is what "as wide as possible, while still getting the tops up" means, and stating it
     * this way is what keeps the two constraints from needing an arbitrary tie-break.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     */
    private static function widestThatReaches(array $inventory, Stack $stack): int
    {
        $subs = array_values(array_filter(
            $inventory,
            static fn (array $entry): bool => $entry[0]->subtype === 'sub' && $entry[1] > 0,
        ));

        $total = array_sum(array_map(static fn (array $entry): int => $entry[1], $subs));
        if ($subs === [] || $total < 1) {
            return 1;
        }

        $widest = 1;
        for ($count = 1; $count <= $total; ++$count) {
            $height = 0.0;
            foreach ($subs as [$device, $quantity]) {
                $height += (int)ceil($quantity / $count) * $device->dimensions->height;
            }
            if ($height + self::EPSILON_M >= $stack->interfaceHeightM) {
                $widest = $count;
            }
        }

        return $widest;
    }

    /**
     * Every bound the finished stack misses, each naming the number it reached and the number it needed.
     *
     * @param list<Tier> $tiers
     * @return list<string>
     */
    private static function boundsProblems(array $tiers, Stack $stack): array
    {
        $messages = [];

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

        // Nothing to fire over anybody's head means nothing to check: a stack of subs alone has no
        // interface, and demanding one would refuse a perfectly good sub wall.
        if ($hasTop && $stack->interfaceHeightM > 0.0 && $subHeight + self::EPSILON_M < $stack->interfaceHeightM) {
            $messages[] = sprintf(
                'stack.interface_height_m (%.3f): the subs stack %.3f m high, so the tops would fire into the '
                .'crowd — add a sub tier, narrow the rig with max_width_m, or state a lower interface',
                $stack->interfaceHeightM,
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

        return $messages;
    }

    /**
     * What is buildable-but-worth-knowing about the finished stack.
     *
     * Warnings rather than errors, because both of these are things a crew routinely deals with — a bar
     * across a stepped row, a cabinet edge proud of a joint — and refusing them would make the solver
     * useless for real gear. Silence, though, is not an option: neither shows up anywhere else in the
     * pipeline, and a render makes an overhanging tier look deliberate.
     *
     * @param list<Tier> $tiers
     * @return list<string>
     */
    private static function supportWarnings(array $tiers, Stack $stack): array
    {
        $warnings = [];

        foreach ($tiers as $index => $tier) {
            if ($tier->isMixed() && $tier->heightStepM() > self::EPSILON_M) {
                $warnings[] = sprintf(
                    'the %s row is stepped by %.0f mm, so whatever stands on it rests on the tall cabinets '
                    .'and bridges the short ones',
                    $tier->label(),
                    $tier->heightStepM() * 1000,
                );
            }

            if ($index === 0) {
                continue;
            }

            $below = $tiers[$index - 1]->widthM($stack->gapM);
            $overhang = ($tier->widthM($stack->gapM) - $below) / 2;
            if ($overhang > self::OVERHANG_TOLERANCE_M) {
                $warnings[] = sprintf(
                    'the %s row is %.3f m on a %.3f m row, so it overhangs %.0f mm each side',
                    $tier->label(),
                    $tier->widthM($stack->gapM),
                    $below,
                    $overhang * 1000,
                );
            }
        }

        return $warnings;
    }
}