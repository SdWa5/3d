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
     * @return array{tiers: list<Tier>, problems: list<string>}
     */
    public static function solve(array $inventory, Stack $stack): array
    {
        $ordering = self::orderingProblems($inventory);
        if ($ordering !== []) {
            return ['tiers' => [], 'problems' => $ordering];
        }

        $tiers = self::fill($inventory, $stack);
        if ($tiers === []) {
            return ['tiers' => [], 'problems' => ['stack.from: none of the devices listed has anything to place']];
        }

        return ['tiers' => $tiers, 'problems' => self::boundsProblems($tiers, $stack)];
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
     * The rows themselves: each device's cabinets dealt into rows of the width the bounds allow, in the
     * order they were listed, the last row of a device possibly short.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fill(array $inventory, Stack $stack): array
    {
        $uniform = $stack->maxWidthM === null ? self::widestThatReaches($inventory, $stack) : null;

        $tiers = [];
        foreach ($inventory as [$device, $count]) {
            $perTier = $uniform ?? self::perTier($device, $stack->maxWidthM, $stack->gapM);
            while ($count > 0) {
                $row = min($perTier, $count);
                $tiers[] = new Tier($device, $row);
                $count -= $row;
            }
        }

        return $tiers;
    }

    /**
     * How many of one cabinet fit across the stated width.
     *
     * `n` cabinets and `n − 1` gaps fit when `n·w + (n−1)·g ≤ W`, i.e. `n ≤ (W + g) / (w + g)`. At least
     * one, always: a stage narrower than a single cabinet is a bound the caller has to hear about as a
     * width failure, not something to silently turn into an empty rig.
     */
    private static function perTier(DeviceSpec $device, float $maxWidthM, float $gapM): int
    {
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
        $widestDevice = $tiers[0]->device->id;
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
                $widestDevice = $tier->device->id;
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
                'stack.max_width_m (%.3f): one %s is already %.3f m wide',
                $stack->maxWidthM,
                $widestDevice,
                $widest,
            );
        }

        return $messages;
    }
}
