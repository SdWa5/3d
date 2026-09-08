<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * How an inventory is dealt out into stacks — which cabinets go to which wall, and how many of each.
 *
 * **The step between "here is the gear" and "here is a stack to solve".** It answers three questions in order:
 * which system each cabinet belongs to ({@see groups}), how many stacks that system's share is split into, and
 * how many of each device this particular stack gets ({@see inventoryFor}).
 *
 * Beside {@see SharedTops} rather than inside it, deliberately. `SharedTops` is the same concern one level up —
 * it deals a pool of tops across walls that are already solved, because only a solved wall knows how much top face
 * it offers. This deals the inventory before anything is solved. Two passes, two rules, and merging them would
 * hide that the second one has to run later.
 */
final class StackDeal
{
    /**
     * The device ids to build each stack from, in order.
     *
     * `--per-owner` groups by **system** rather than by owner, which is a distinction that took until SWP-3 to
     * arrive. Who owns a cabinet is already recorded and is nearly the right answer — `sdwa5` runs the Flexys,
     * SKRAMs and M2122s, `sepp` the Achenbachs and 2-ways — but those two owners are **one** system, and
     * separating them put our own gear in two walls. {@see SystemGrouping} says which owners are one system,
     * stated at invocation time rather than in the specs, because lending gear across systems is normal here and
     * a `system:` field on a cabinet would be a fact about a gig written onto an object.
     *
     * `--stacks=N` then splits each group into that many, evenly, which is how a stereo pair is asked for.
     *
     * **`tops-shared` takes the tops out of the groups altogether**, which is the one thing here that is not simply a
     * partition of the device list: each group keeps its owner's subs and the tops come back separately as a pool for
     * {@see SharedTops} to deal once the walls are solved. A group is still the fill order it arrived in, since
     * {@see \App\Spec\FillOrder::everySpeaker} emits subs low-frequency-first and then tops, so removing the tops is a truncation rather
     * than a re-sort.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     *
     * @return array{stacks: array<string, array{ids: list<string>, index: int, of: int}>, tops: array<string, int>}|string
     */
    public static function groups(
        array $devices,
        array $from,
        int $stacks,
        float $clearanceM,
        string $splitValue,
        // **THE GROUPING, NOT THE OWNER FIELD, AND THAT IS THE WHOLE OF SWP-3'S SECOND ASK.** `sdwa5` and `sepp`
        // are two owners and one system: partitioning on `owner` stood our own gear apart from Sepp's as two
        // separate walls, in every `systems-apart` and `tops-shared` rig ever generated. See {@see SystemGrouping}.
        SystemGrouping $grouping,
        // Named `$systems` and not `$split`, because `--split` is a different axis entirely — it decides whether a
        // stack gets a share of every device type or whole types each, and it is resolved a few lines below.
        SystemSplit $systems = SystemSplit::Pooled,
    ): array|string {
        if ($stacks < 1) {
            return '--stacks must be at least 1';
        }
        if ($clearanceM < 0.0) {
            return '--clearance must not be negative';
        }

        $split = SplitMode::tryFrom($splitValue);
        if (null === $split) {
            return sprintf(
                "--split: unknown value '%s' (allowed: %s)",
                $splitValue,
                implode(', ', array_column(SplitMode::cases(), 'value')),
            );
        }

        // **THE TOPS COME OUT OF THE INVENTORY BEFORE IT IS GROUPED, and only for `tops-shared`.** Taken out here
        // rather than after the grouping because the pool is a fact about the rig: every top in it is dealt across
        // every wall, so which owner it came from stops mattering the moment the value is chosen. What is left in
        // `$from` is the subs, and each owner's share of those is its wall.
        $pool = [];
        if ($systems->sharesTops()) {
            $subs = [];
            foreach ($from as $id) {
                if ('sub' === $devices[$id]->subtype) {
                    $subs[] = $id;
                    continue;
                }
                $pool[$id] = $devices[$id]->quantity;
            }
            $from = $subs;
        }

        // **Read off the rig rather than off the option, which is what lets one sweep offer all three.**
        // `--per-owner` still decides it for a caller who names a rig by hand; on the sweep the axis decides, and the
        // value is recorded into each written scene's regenerate line so a replay rebuilds the same grouping.
        $groups = [];
        if ($systems->isPerOwner()) {
            foreach ($from as $id) {
                $groups[$grouping->systemOf($devices[$id]->owner)][] = $id;
            }
        } else {
            $groups[''] = $from;
        }
        if ([] === $groups) {
            // Only reachable with `tops-shared`, and only on an inventory of nothing but tops. Worth its own refusal
            // rather than an empty-stack error further down: there is nothing wrong with the request except that
            // there are no subs in it, and nothing stands a top up but a sub.
            return 'tops-shared: this inventory is all tops — there are no subs to make a wall out of';
        }

        // Splitting shares each device out rather than each *group*, so both halves of a stereo pair get some
        // of every cabinet instead of one taking the subs and the other the tops. The index and count travel
        // with the group so the share can be worked out **without losing the remainder**: three M2122s over two
        // stacks is 2 + 1, not one each with the third quietly unplaced.
        $dealt = [];
        foreach ($groups as $label => $ids) {
            // By type, the stack's own list *is* its share, so `of` is 1 and nothing is divided further. That is
            // what makes a by-type stack low: it holds two or three types where a by-count stack holds all nine.
            $perStack = SplitMode::ByType === $split && $stacks > 1
                ? self::byType($devices, $ids, $stacks)
                : null;
            if (is_string($perStack)) {
                return $perStack;
            }

            for ($stack = 0; $stack < $stacks; ++$stack) {
                $key = 1 === $stacks
                    ? (string) $label
                    : ('' === $label ? (string) ($stack + 1) : $label.'-'.($stack + 1));
                $dealt[$key] = null === $perStack
                    ? ['ids' => $ids, 'index' => $stack, 'of' => $stacks]
                    : ['ids' => $perStack[$stack], 'index' => $stack, 'of' => 1];
            }
        }

        return ['stacks' => $dealt, 'tops' => $pool];
    }

    /**
     * Whole device types dealt one stack each, balanced by how much **row** each type is.
     *
     * Balanced on `quantity × width` — the linear metres a type needs — and not on cabinet count, because that is
     * what decides how many rows a stack ends up with and so how tall it is. It is also the measure
     * {@see \App\Spec\FillOrder::byFillOrder} already breaks its own ties on, so nothing new is being invented to rank cabinets.
     *
     * Longest-processing-time greedy: the biggest type goes to the emptiest stack, repeatedly. It is the standard
     * answer to this shape of problem and within a third of optimal for it, which is far inside the accuracy of
     * the cabinet dimensions themselves.
     *
     * **A stack of tops alone is folded away**, because nothing stands on the floor to hold them up: the tops go
     * to whichever stack carries the most sub, which is the one best able to take them. A stack of subs and no
     * tops is left alone — that is a sub wing, and a perfectly ordinary thing to build.
     *
     * Each stack's list is then put back into the fill order it arrived in, so the deepest cabinets still end up on
     * the floor and the tops still come last. `by-type` decides *which* stack a type is in; it never reorders one.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     *
     * @return list<list<string>>|string one list per stack, or why the split cannot be made
     */
    public static function byType(array $devices, array $ids, int $stacks): array|string
    {
        if (count($ids) < $stacks) {
            return sprintf(
                '--split=by-type: %d device types cannot fill %d stacks — each stack gets whole types, so there '
                .'has to be at least one each. Use --split=by-count, or fewer stacks',
                count($ids),
                $stacks,
            );
        }

        $bySize = $ids;
        usort(
            $bySize,
            static fn (string $a, string $b): int => $devices[$b]->quantity * $devices[$b]->dimensions->width
                <=> $devices[$a]->quantity * $devices[$a]->dimensions->width,
        );

        $assigned = array_fill(0, $stacks, []);
        $load = array_fill(0, $stacks, 0.0);
        foreach ($bySize as $id) {
            $target = (int) array_search(min($load), $load, true);
            $assigned[$target][] = $id;
            $load[$target] += $devices[$id]->quantity * $devices[$id]->dimensions->width;
        }

        $subLoad = [];
        foreach ($assigned as $stack => $own) {
            $subLoad[$stack] = 0.0;
            foreach ($own as $id) {
                if ('sub' === $devices[$id]->subtype) {
                    $subLoad[$stack] += $devices[$id]->quantity * $devices[$id]->dimensions->width;
                }
            }
        }
        $carries = (int) array_search(max($subLoad), $subLoad, true);
        foreach ($assigned as $stack => $own) {
            if ($subLoad[$stack] > 0.0 || $stack === $carries) {
                continue;
            }
            $assigned[$carries] = [...$assigned[$carries], ...$own];
            $assigned[$stack] = [];
        }

        $ordered = [];
        foreach ($assigned as $own) {
            $ordered[] = array_values(array_filter($ids, static fn (string $id): bool => in_array($id, $own, true)));
        }

        foreach ($ordered as $stack => $own) {
            if ([] === $own) {
                return sprintf(
                    '--split=by-type: stack %d ends up empty — its only types were tops, which have nothing to '
                    .'stand on, and they went to the stack carrying the most sub. Use fewer stacks',
                    $stack + 1,
                );
            }
        }

        return $ordered;
    }

    /**
     * This stack's share of each device — **symmetric, and never split below what a row needs**.
     *
     * Two rules, both learned from what the even split produced.
     *
     * **A device too small to split is not split.** Fewer than two per stack cannot flank a mixed row
     * ({@see StackSolver} needs two to make a pair) and cannot be flanked into one either, so one SKRAM per
     * half left the row above it standing on 49.9 % of its own width and the solver dropped the pair
     * altogether — 180 kg of sub in no rig at all. The pair goes whole to the **middle** stack instead:
     * `intdiv($of, 2)`, which is the middle of three and the right-hand one of two.
     *
     * **The rest is shared evenly, and `$placeAll` decides what happens to the remainder.** Three M2122s over two
     * stacks split evenly are 1 + 1 with the third out of the rig: symmetric, and a stereo pair that really is a
     * pair — one side would otherwise get a wider top row, a different interface height and a different rig. But
     * a cabinet in no rig at all is its own kind of wrong, so `$placeAll` deals the remainder instead
     * ({@see dealAll}) and the rig comes out 1 + 2. Both are offered, the one that stands up more cabinets wins,
     * and `--no-asymmetry` withdraws the offer. Either way the odd cabinet is **named** by
     * {@see splitRemainder} rather than silently dropped or silently lopsided.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @param array<string, int> $dealt device id => a count decided elsewhere, which overrides the share
     *
     * @return list<array{DeviceSpec, int}>
     */
    public static function inventoryFor(
        array $devices,
        array $ids,
        int $index,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
        array $dealt = [],
    ): array {
        $middle = intdiv($of, 2);

        return array_map(
            static function (string $id) use ($devices, $index, $of, $middle, $evenSplit, $placeAll, $dealt): array {
                // **A DEALT COUNT IS AN ANSWER AND NOT A SHARE**, so it wins outright over everything below. The
                // shared tops of SWP-2 were dealt to *this* stack by {@see SharedTops} across the whole rig, and
                // dividing that share again by the stack count would deal the pool twice — once across the walls and
                // once inside each of them.
                if (isset($dealt[$id])) {
                    return [$devices[$id], $dealt[$id]];
                }

                $quantity = $devices[$id]->quantity;
                $share = intdiv($quantity, $of);

                // Two cases keep a device whole. **Fewer than one per stack** — there are simply not enough to
                // go round, and splitting three stacks' worth out of two 2-ways leaves every one of them out.
                // **Fewer than two per stack, for a sub** — a sub has to flank a mixed row or be flanked into
                // one, and neither works with one cabinet. Tops are exempt from the second: nothing stands on a
                // top, so one Tecnare per stack is a perfectly good top row, and applying the rule to them made
                // the middle stack hoard every one and left the outer stacks a row of subs with nothing above.
                if ($share < 1 || (!$evenSplit && $share < 2 && 'sub' === $devices[$id]->subtype)) {
                    // A **sub** goes to the middle: weight belongs low and central, and a sub has to be part of a
                    // row that carries something. A **top** goes to the outermost stacks instead, because the tops
                    // too few to give every stack one are the small boxes — near-field fill, which belongs at the
                    // edges of the rig rather than stacked in its centre.
                    return 'sub' === $devices[$id]->subtype
                        ? [$devices[$id], $index === $middle ? $quantity : 0]
                        : [$devices[$id], self::outerShare($quantity, $index, $of)];
                }

                return [$devices[$id], $placeAll ? self::dealAll($quantity, $index, $of) : $share];
            },
            $ids,
        );
    }

    /**
     * How many of `$quantity` this stack gets when they are dealt **outermost first, in pairs**.
     *
     * Pairs, so the rig stays symmetric: `(0, of-1)`, then `(1, of-2)`, and so on. An odd one left at the end goes
     * to the middle stack of an odd-numbered rig rather than to one side, since a lone fill on the left is worse
     * than a lone fill in the centre. Two 2-ways across three stacks come out one, none, one.
     */
    public static function outerShare(int $quantity, int $index, int $of): int
    {
        for ($pair = 0; $quantity >= 2 && $pair < intdiv($of, 2); ++$pair) {
            if ($index === $pair || $index === $of - 1 - $pair) {
                return 1;
            }
            $quantity -= 2;
        }

        return $quantity > 0 && 1 === $of % 2 && $index === intdiv($of, 2) ? $quantity : 0;
    }

    /**
     * This stack's share when **every cabinet is placed** — the even share, plus its part of the remainder.
     *
     * The remainder goes through {@see outerShare}, so the leftovers land outermost-first in pairs and the split
     * stays as symmetric as the counts allow: three Tecnares across two stacks come out one and two rather than
     * one each with the third unplaced.
     *
     * `outerShare` leaves a single cabinet out when the rig has an **even** number of stacks, because there is no
     * middle stack to give it to. Placing everything means it has to go somewhere, and it goes to the stack just
     * right of the centre line — the same side {@see Tier::mirrored} and
     * {@see StackSolver::centred} put an odd cabinet, so a rig is asymmetric the same way throughout
     * rather than one way per rule.
     */
    public static function dealAll(int $quantity, int $index, int $of): int
    {
        $share = intdiv($quantity, $of);
        $rest = $quantity - $share * $of;
        $odd = 1 === $rest % 2 && 0 === $of % 2 && $index === intdiv($of, 2) ? 1 : 0;

        return $share + self::outerShare($rest, $index, $of) + $odd;
    }

    /**
     * What an even split leaves over, per device, so the report can name it instead of it just being absent.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     * @param array<string, int> $dealt device ids whose count was dealt rather than split, which have no remainder
     *
     * @return array<string, string> device id => why some are not in any stack
     */
    public static function splitRemainder(
        array $devices,
        array $ids,
        int $of,
        bool $evenSplit = true,
        bool $placeAll = false,
        array $dealt = [],
    ): array {
        if ($of < 2) {
            return [];
        }

        $left = [];
        foreach ($ids as $id) {
            // A dealt device has no remainder to report, because it was never split: {@see SharedTops} handed this
            // stack a count across the whole rig. Reporting one would describe an arithmetic the rig did not do.
            if (isset($dealt[$id])) {
                continue;
            }

            $quantity = $devices[$id]->quantity;
            $share = intdiv($quantity, $of);

            // The same condition {@see inventoryFor} keeps a device whole on: those are all placed, in one
            // stack, so there is no remainder to report. Guarding on the share alone skipped the tops with one
            // per stack, which is exactly the case this exists for — the odd third M2122.
            if ($share < 1) {
                continue;
            }

            // A sub kept whole because it could not be split. Said out loud, because the header otherwise shows
            // both SKRAMs in one stack and gives no hint that the other arrangement was tried and refused — which
            // is the single thing about a split rig people ask about.
            if (!$evenSplit && $share < 2 && 'sub' === $devices[$id]->subtype) {
                $left[$id] = sprintf(
                    'KEPT TOGETHER, all %d in one stack — %d stacks would take one each, and one on its own cannot be '
                    .'flanked into a row that carries anything: the row above it ends up half off its support. '
                    .'Turning them makes the split work, which is what the -turned rig does',
                    $quantity,
                    $of,
                );
                continue;
            }

            $over = $quantity - $share * $of;
            if ($over < 1) {
                continue;
            }

            // Placed but unevenly, which is worth saying for the same reason leaving it out was: a reader comparing
            // two stacks needs to know the difference is the remainder rather than a solve that went differently.
            $left[$id] = $placeAll
                ? sprintf(
                    'SPLIT UNEVENLY, %d of %d over %d stacks — %d each and the remaining %d dealt outermost first, '
                    .'so the stacks are not identical. --no-asymmetry leaves them out instead',
                    $over,
                    $quantity,
                    $of,
                    $share,
                    $over,
                )
                : sprintf(
                    'LEFT OUT, %d of %d — %d stacks take %d each, and an odd cabinet would make one stack '
                    .'a different rig from the others',
                    $over,
                    $quantity,
                    $of,
                    $share,
                );
        }

        return $left;
    }
}
