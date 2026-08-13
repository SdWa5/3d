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
    private const OVERHANG_PER_SIDE = 2 / 3;


    /**
     * @param list<array{DeviceSpec, int}> $inventory device and how many of it, low frequency first
     * @param ?LayoutMode $align how the placement spreads its tiers, which decides the ORDER of the tops row —
     *     see {@see topRow}. Null for a placement that states none, which is `center`.
     * @return array{tiers: list<Tier>, problems: list<string>, warnings: list<string>}
     */
    public static function solve(array $inventory, Stack $stack, ?LayoutMode $align = null): array
    {
        $ordering = self::orderingProblems($inventory);
        if ($ordering !== []) {
            return ['tiers' => [], 'problems' => $ordering, 'warnings' => []];
        }

        $tiers = self::fill($inventory, $stack, $align);
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
    private static function fill(array $inventory, Stack $stack, ?LayoutMode $align = null): array
    {
        // **A PYRAMID IS ORDERED FOR WIDTH, NOT FOR WEIGHT**, and that is the whole difference between the two
        // shapes rather than a detail of them. The taper below can only ever *narrow* a wall, so a pyramid is decided
        // by how wide its bottom row can be — and that is decided by which type is on the floor. Two wall basses are
        // 220 kg each and 1.34 m of row between them: deepest and heaviest, so weight puts them on the ground, and
        // then nothing above can be wider than 1.34 m. The same twelve cabinets with the six IQ subs on the floor
        // come out 3.28 → 2.56 → 1.54 in three rows and 2.070 m, against 1.34 → 1.20 → 1.63 → 1.63 → 1.54 in five
        // rows and 3.240 m. A metre and a sixth of height, and the V gone, out of nothing but the order.
        //
        // The price is stated rather than hidden: a wide-but-shallow type ends up UNDER a deep one, which is the
        // inversion {@see \App\Command\SceneStackCommand::byFillOrder} exists to prevent. That is why both shapes
        // are generated — `free` keeps the deepest and heaviest cabinets on the floor and accepts the V, `pyramid`
        // takes the shape and the height and gives up the ordering. Neither is right for every rig.
        //
        // Subs only, and their block stays before the tops, so {@see orderingProblems} is unaffected.
        if ($stack->shape === StackShape::Pyramid) {
            $inventory = self::widestFirst($inventory, $stack);
        }

        $widest = 0;
        foreach ($inventory as [$device, $count]) {
            $widest = max($widest, min($count, self::perTier($device, $stack->maxWidthM, $stack->gapM, self::rollFor($device, $stack))));
        }

        $tallestCarried = [];
        $tallestCarriedSubs = -INF;
        $shortestCarried = [];
        $shortestCarriedSubs = INF;
        $widestAttempt = [];

        for ($perRow = $widest; $perRow >= 1; --$perRow) {
            // PACKING IS AN EXTRA CANDIDATE, NOT A REPLACEMENT, and measuring says so plainly: on 2 SKRAMs, 3
            // middle subs, 2 mid-bass and 2 2-ways the ordinary deal finds 1.445 m and the pack 2.465 m, because
            // one mixed row of the three tall types beats splitting them. On 6 Flexys and 8 turbo subs the pack
            // wins. Neither wins everywhere, so under a ceiling both are proposed and the shortest that stands up
            // is taken — which also means `mix_with` keeps working there, since the ordinary path still honours it.
            //
            // No flanking search for a packed pass: {@see packedRows} ignores `$pairs` outright, so every pass but
            // the first would re-solve the identical arrangement at the cost of a checker run per candidate pack.
            foreach ($stack->maxSubHeightM !== null ? [true, false] : [false] as $packed) {
                $flanking = $packed ? 0 : self::flankingPairs($inventory, $stack, $perRow);
                for ($pairs = $flanking; $pairs >= 0; --$pairs) {
                    $tiers = self::fillWith($inventory, $stack, $perRow, $pairs, $packed, $align);
                    $widestAttempt = $widestAttempt === [] ? $tiers : $widestAttempt;

                    if (StackChecks::supportChecks($tiers, $stack)['problems'] !== []) {
                        continue;
                    }

                    $subs = self::subHeight($tiers);

                    // UNDER A CEILING THE PREFERENCE INVERTS, and that is the whole reason the key exists. Without
                    // one the answer is the widest row that still gets the tops up, so the search returns on its
                    // first hit and every later, narrower arrangement is ignored. With one, a hit is not the answer
                    // — a *shorter* hit may be further down the search — so the whole space is walked and the
                    // shortest arrangement that still clears the interface is kept. Ties keep the first, the widest.
                    if ($stack->maxSubHeightM !== null) {
                        if (self::reachesInterface($tiers, $stack) && $subs < $shortestCarriedSubs) {
                            $shortestCarriedSubs = $subs;
                            $shortestCarried = $tiers;
                        }
                    } elseif (self::reachesInterface($tiers, $stack)) {
                        return $tiers;
                    }

                    if ($subs > $tallestCarriedSubs) {
                        $tallestCarriedSubs = $subs;
                        $tallestCarried = $tiers;
                    }
                }
            }
        }
        if ($shortestCarried !== []) {
            return $shortestCarried;
        }
        if ($tallestCarried !== []) {
            // Stands up but sits lower than asked for, which is a warning. Under a ceiling this is the arrangement
            // that misses it — nothing cleared the interface, so there is no shortest-that-clears to prefer, and
            // {@see StackChecks::boundsProblems} names the miss rather than this silently picking a side.
            return $tallestCarried;
        }

        // Nothing stands up at any row width. Hand back the **widest** attempt rather than the tallest, so the
        // error names the most favourable case there was: "even at its widest it overhangs 610 mm" tells you
        // the rig is impossible, where the narrowest attempt's 956 mm would just look like a bad guess.
        return $widestAttempt;
    }

    /**
     * One arrangement: the mixed bottom row at `$pairs` per side, then a balanced set of rows per device — or, when
     * `$packed`, the subs packed into as few rows as the width allows regardless of how many types share one.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<Tier>
     */
    private static function fillWith(
        array $inventory,
        Stack $stack,
        int $perRow,
        int $pairs,
        bool $packed = false,
        ?LayoutMode $align = null,
    ): array {
        $remaining = [];
        foreach ($inventory as $index => [$device, $count]) {
            $remaining[$index] = [$device, $count];
        }

        // A PACKED PASS PACKS INSTEAD OF DEALING. The two are alternatives rather than layers: packing already puts
        // several device types in a row, which is what `mixedBottomRow` and `reserveLifts` each do for one special
        // case — an inverted step below, a step above. Running them first would spend the cabinets those rows need
        // before the packer sees them, and for no gain, since a packed row closes both steps by construction. Which
        // of the two passes wins is {@see fill}'s decision, made on the finished arrangements.
        if ($packed) {
            [$packed, $remaining] = self::packedRows($remaining, $stack, $perRow);
            $tops = self::topRow($remaining, $stack, $align);

            $rows = $tops === null ? $packed : [...$packed, $tops];

            return array_map(
                static fn (Tier $tier, int $row): Tier => $tier->mirrored($stack->mirrorStyle, $row),
                $rows,
                array_keys($rows),
            );
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
        // Indices, not values. A stated mix consumes the cabinets of the device it flanks WITH as well as its own,
        // so `$remaining` changes underneath this loop — and `foreach ($remaining as [$device, $count])` destructures
        // a snapshot taken before the first iteration. That is what dealt eight GMSS turbo subs into a mixed row and
        // then eight more into rows of their own, out of a stock of eight: the mix zeroed them, the stale count did
        // not know, and `scene:build`'s over-use warning was the only thing downstream that noticed.
        foreach (array_keys($remaining) as $index) {
            [$device, $count] = $remaining[$index];
            if ($count < 1 || $device->subtype !== 'sub') {
                continue;
            }

            // A tier that asked to share its row does so here, wherever it sits — mixing used to be the
            // bottom row's privilege alone, decided by a heuristic. `mix_with` names it outright, and the
            // same two gates still apply: matching heights, and the devices have to exist and be free.
            // Capped like every other row-building path, which it was not: a stated `mix_with` built its row from the
            // raw width and so could come out holding more cabinets than the row under it, which is the V the pyramid
            // exists to forbid. {@see reserveLifts} cannot be capped the same way, because it reserves its flanks before
            // any tier exists and {@see perRowCap} has nothing to measure against then.
            $stated = self::statedMix(
                $remaining,
                $index,
                $stack,
                self::perRowCap($tiers, $stack, $perRow),
                self::supportOf($tiers, $stack),
            );
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
            $perTier = self::rowSizeFor(
                $device,
                $count,
                $stack,
                self::perRowCap($tiers, $stack, $perRow),
                $roll,
                self::supportOf($tiers, $stack),
            );
            // Balanced rather than greedy: the same number of rows, but no short one left at the top to
            // fail to carry whatever is above it.
            $rows = (int)ceil($count / $perTier);
            foreach (self::share($count, $rows) as $row) {
                $tiers[] = Tier::of($device, $row, $roll);
            }
        }

        $tops = self::topRow($remaining, $stack, $align);
        if ($tops !== null) {
            $tiers[] = $tops;
        }

        // The mirror last, in one place, so it catches every tier however it was built — a plain row, a mixed
        // bottom row, a flanked one, the tops. Splitting a rolled segment about the row's own centre is what
        // makes the rig symmetric about its centre line rather than about each segment.
        return array_map(
            static fn (Tier $tier, int $row): Tier => $tier->mirrored($stack->mirrorStyle, $row),
            $tiers,
            array_keys($tiers),
        );
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
                if ($later > $index && $laterCount > 0 && $laterDevice->subtype === 'sub') {
                    $shortest = min($shortest, RolledBox::heightOf($laterDevice, self::rollFor($laterDevice, $stack)));
                }
            }
            if (self::swallows(
                RolledBox::heightOf($device, self::rollFor($device, $stack)),
                RolledBox::heightOf($sourceDevice, self::rollFor($sourceDevice, $stack)),
                $shortest,
            )) {
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
                $support = self::lastRowWidth($source, $left, $stack, $perRow);
                if (($width - $support) / 2 > $candidate->outerWidthM() / 2) {
                    break;
                }
            } else {
                $support = INF;
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
     * Takes only as many flanking cabinets as fit the row, in pairs, and leaves the rest in `$remaining`. `$perRow`
     * bounds the count and the support bounds the width, the same two limits an ordinary row answers to.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     * @return array{Tier, list<array{DeviceSpec, int}>}|null
     */
    private static function statedMix(array $remaining, int $index, Stack $stack, int $perRow, float $supportM = INF): ?array
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

        // AS MANY FLANKERS AS FIT, IN PAIRS — not the whole stock. Taking every cabinet of the flanking device put
        // all eight GMSS turbo subs either side of three middle subs and made a 6.065 m row on a 5 m stage, which
        // the bounds check then refused; the mix that was supposed to widen a narrow tier killed the whole
        // arrangement instead. Pairs, because a row with one more cabinet on the left than the right is not the
        // symmetric shape this is for, and whatever does not fit stays in `$remaining` for its own rows.
        $roll = self::rollFor($device, $stack);
        $gap = $stack->gapM;
        $budget = self::ceilingFor($device, $stack, $roll, $supportM) ?? INF;

        $rowWidth = $count * RolledBox::widthOf($device, $roll) + ($count - 1) * $gap;
        $rowCount = $count;
        $used = [];

        $added = true;
        while ($added) {
            $added = false;
            foreach ($segments as $other => [$otherDevice, $otherCount]) {
                $taken = $used[$other] ?? 0;
                if ($otherCount - $taken < 2 || $rowCount + 2 > $perRow) {
                    continue;
                }
                $width = $rowWidth + 2 * (RolledBox::widthOf($otherDevice, self::rollFor($otherDevice, $stack)) + $gap);
                if ($width > $budget + self::EPSILON_M) {
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
        if ($used === []) {
            return null;
        }

        $left = [];
        $right = [];
        foreach ($used as $other => $take) {
            $otherDevice = $segments[$other][0];
            $half = intdiv($take, 2);
            $left[] = [$otherDevice, $half, self::rollFor($otherDevice, $stack)];
            $right[] = [$otherDevice, $half, self::rollFor($otherDevice, $stack)];
            $remaining[$other] = [$otherDevice, $segments[$other][1] - $take];
        }
        $remaining[$index] = [$device, 0];

        return [
            new Tier([...array_reverse($left), [$device, $count, $roll], ...$right]),
            $remaining,
        ];
    }

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
    private static function topRow(array $remaining, Stack $stack, ?LayoutMode $align = null): ?Tier
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

        if ($align === LayoutMode::Stereo) {
            return self::stereoTopRow($tops, $stack);
        }

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
    private static function stereoTopRow(array $tops, Stack $stack): Tier
    {
        $left = [];
        $centre = [];
        $right = [];

        foreach ($tops as [$device, $count]) {
            $roll = self::rollFor($device, $stack);
            $half = intdiv($count, 2);

            if ($half > 0) {
                $left[] = [$device, $half, $roll];
                $right[] = [$device, $half, $roll];
            }
            if ($count % 2 === 1) {
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
     * Every sub in as few rows as the width allows, filling each row from **as many device types as it takes**.
     *
     * This is the one thing that can make a stack of many types short, and the reason is arithmetic rather than
     * clever: a row costs the height of its *tallest* cabinet, so two types in one row cost one height instead of
     * two. Dealt one type per row — which is what the rest of this class does — a stack holding six sub types is
     * six rows tall whatever the stage width, and no row count, mix or stage width gets it under 3 m. Packed, the
     * same six types come out in two or three rows.
     *
     * **LOW FREQUENCY STAYS LOW, and that is what stops this being bin-packing.** `$remaining` arrives in `from`
     * order, which the command builds with {@see \App\Command\SceneStackCommand::byFrequency} — deepest first, so
     * the deepest cabinets end up on the floor carrying everything. A row may therefore only take types that are
     * **adjacent in that order**: the rows are contiguous runs of the list, read bottom-up, and the only decision
     * left is where the cuts go. A 2-way can never land beside an Achenbach because it is nowhere near it in the
     * ordering, so a frequency inversion is not merely avoided — it is unreachable.
     *
     * **PACKED GREEDILY IS NOT GOOD ENOUGH, and the reason is the row that ends up on top.** Filling each row to
     * the width it can take leaves the leftovers in the last one: all 41 speakers on a 3.80 m stage came out with
     * a 1.825 m top sub row under a 3.744 m row of tops, overhanging 960 mm each side. That is the same failure
     * {@see share} exists to prevent for a single device — "balanced rather than greedy, so no short row is left
     * at the top to fail to carry whatever is above it" — so the packer balances the same way. Greedy decides how
     * many rows the wall needs; the rows are then packed again to the *average* width of that many, which is a
     * meaningful number rather than a tuned one: `total / rows` is what each row is if the wall is that tall.
     * Divisors below the greedy count are tried in turn until the pack still fits in no more rows than greedy
     * needed, so balancing never costs a tier.
     *
     * Each row is sized against the tier below it exactly as an ordinary row is, through {@see ceilingFor}, with
     * the allowance measured on the **candidate** cabinet: whatever is added last ends up outermost, so it is the
     * one whose overhang the bearing rule will judge.
     *
     * A row always takes at least one cabinet, even when the support is too narrow for it. That is deliberate:
     * refusing would loop forever, and {@see StackChecks} names an unsupported tier far better than this could —
     * the same reason {@see fill} hands back its widest attempt rather than nothing.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     * @return array{list<Tier>, list<array{DeviceSpec, int}>} the sub rows bottom-up, and the tops left to place
     */
    private static function packedRows(array $remaining, Stack $stack, int $perRow): array
    {
        $queue = [];
        foreach (array_keys($remaining) as $index) {
            [$device, $count] = $remaining[$index];
            if ($count < 1 || $device->subtype !== 'sub') {
                continue;
            }
            $queue[] = [$device, $count, self::rollFor($device, $stack)];
            $remaining[$index] = [$device, 0];
        }
        if ($queue === []) {
            return [[], $remaining];
        }

        $greedy = self::packTo($queue, $stack, $perRow, INF);
        $rows = count($greedy);

        $cabinets = 0;
        $total = 0.0;
        foreach ($queue as [$device, $count, $roll]) {
            $cabinets += $count;
            $total += $count * RolledBox::widthOf($device, $roll);
        }
        // Every cabinet but one per row has a gap before it, so the whole wall's linear metres are the bodies plus
        // `cabinets − rows` gaps. Worked out against the greedy row count because that is the count being balanced.
        $total += max(0, $cabinets - $rows) * $stack->gapM;

        $candidates = [$greedy];
        for ($divisor = (float)$rows; $divisor > 1.0 - self::EPSILON_M; $divisor -= 0.25) {
            $candidates[] = self::packTo($queue, $stack, $perRow, $total / $divisor);
        }

        // EACH CANDIDATE IS PUT THROUGH THE CHECKER, and that is not belt-and-braces — it is the only way the pack
        // can know whether its own rows stand up. A step anywhere in the wall propagates all the way up it: two
        // SKRAMs in the bottom row leave the level Flexy row above them sitting at three different heights, and the
        // Achenbach row above *that* straddles the seams and lands on 1.9 % of itself. Nothing local to a row can
        // see that coming — it depends on where every seam below happens to fall — so the pack proposes and
        // {@see StackChecks::supportChecks} disposes, exactly as {@see fill} does with whole arrangements.
        //
        // Shortest wins among the ones that stand up, because height is what a ceiling asked for; the row count
        // only breaks a tie. A pack that stands up always beats a shorter one that does not, which is the same
        // ordering {@see fill} states: support outranks the height.
        $best = $greedy;
        $bestStands = false;
        $bestHeight = INF;
        $bestRows = PHP_INT_MAX;

        foreach ($candidates as $pack) {
            $stands = StackChecks::supportChecks($pack, $stack)['problems'] === [];
            $height = self::subHeight($pack);
            $count = count($pack);

            if ($bestStands && !$stands) {
                continue;
            }
            if ($stands === $bestStands
                && ($height > $bestHeight + self::EPSILON_M
                    || (abs($height - $bestHeight) <= self::EPSILON_M && $count >= $bestRows))) {
                continue;
            }

            $best = $pack;
            $bestStands = $stands;
            $bestHeight = $height;
            $bestRows = $count;
        }

        return [$best, $remaining];
    }

    /**
     * One pack of the whole sub queue, every row held to `$budgetM` as well as to what carries it.
     *
     * Split out from {@see packedRows} because it is run several times with different budgets, and it has to be
     * the same pack each time for the comparison between them to mean anything.
     *
     * @param list<array{DeviceSpec, int, float}> $queue device, stock and roll, deepest first
     * @return list<Tier>
     */
    private static function packTo(array $queue, Stack $stack, int $perRow, float $budgetM): array
    {
        $tiers = [];
        $support = INF;
        $cursor = 0;
        $placed = 0;

        while ($cursor < count($queue)) {
            $row = [];
            $count = 0;
            $width = 0.0;
            // The pyramid rule: no more cabinets than the row below holds. See {@see perRowCap}.
            $seats = self::perRowCap($tiers, $stack, $perRow);

            while ($cursor < count($queue)) {
                [$device, $stock, $roll] = $queue[$cursor];
                if ($stock - $placed < 1) {
                    ++$cursor;
                    $placed = 0;
                    continue;
                }

                // A CABINET FAR SHORTER THAN THE ROW IT WOULD JOIN STARTS ITS OWN ROW. Packing a 0.500 m mid bass
                // beside two 1.400 m wall basses leaves a 900 mm crater at one end, and the IQ sub that lands in it
                // on the next row up sits at the same height as the wall bass next to it — 59 mm inside it, as the
                // overlap sweep found. Same rule as {@see liftAbove}'s, asked of a row rather than of a flank.
                if ($row !== [] && self::swallows(
                    RolledBox::heightOf($device, $roll),
                    self::tallestIn($row),
                    self::shortestAfter($queue, $cursor, $stack),
                )) {
                    break;
                }

                $ceiling = min(self::ceilingFor($device, $stack, $roll, $support) ?? INF, $budgetM);
                $own = RolledBox::widthOf($device, $roll);
                $take = 0;

                while ($placed + $take < $stock && $count + $take + 1 <= $seats) {
                    $step = $width + ($count + $take > 0 ? $stack->gapM : 0.0) + $own;
                    if ($step > $ceiling + self::EPSILON_M) {
                        break;
                    }
                    $width = $step;
                    ++$take;
                }

                // Nothing of this device fits the row as it stands. An empty row has to take one anyway — see
                // {@see packedRows} — and a row with something in it is simply finished, so the next one starts here.
                if ($take < 1) {
                    if ($row !== []) {
                        break;
                    }
                    $take = 1;
                    $width = $own;
                }

                $row[] = [$device, $take, $roll];
                $count += $take;
                $placed += $take;

                if ($placed >= $stock) {
                    ++$cursor;
                    $placed = 0;
                    continue;
                }
                // This device is not exhausted, so the row is: it stopped on width or on `$perRow`, and the rest
                // of this device is the bottom of the next row. Cutting mid-device is how a type spans two rows,
                // which the balanced deal has always done — see {@see share}.
                break;
            }

            $tier = self::centred($row);
            $tiers[] = $tier;
            // The row's **whole** width, not just its tall segments. Sizing the next row to the plateau instead was
            // tried and is wrong: a cabinet outboard of the plateau is not unsupported, it lands on the shoulder at
            // a lower height, which is exactly what {@see Gravity} does with it and what a stepped wall looks like.
            // Holding rows to the plateau drove the whole wall narrow — two SKRAMs in the bottom row would have
            // capped everything above them at 1.240 m — and cost 3.8 m of height on the full inventory.
            $support = $tier->widthM($stack->gapM);
        }

        return $tiers;
    }

    /**
     * How many cabinets the next row up may hold — the pyramid rule, expressed the way it was asked for.
     *
     * **A count, not a width, and that distinction is the whole rule working rather than not.** Capping the next
     * row's *width* at the row below was tried first and is too blunt: six Achenbachs are 3.700 m on six Flexys'
     * 3.646, a 27 mm shoulder per side that the bearing rule allows four hundred of, and forbidding it split them
     * into two rows of three — whereupon the 1.84 m row could not carry the tops and a 2-way was dropped from the
     * rig. A flush wall is not a V.
     *
     * Counting cabinets says what "narrows going up" actually means, and it is what was asked for: *"adapting the
     * amount of speakers each of the rows has"*. Six on six is flush and allowed; three on one is the V and is not.
     * Width then takes care of itself, because the cabinets are all 0.45–0.66 m wide and a row of `n` is about `n`
     * cabinets across whatever they are.
     *
     * `INF` for `free` and for the bottom row, which has nothing to narrow relative to.
     */
    private static function perRowCap(array $tiers, Stack $stack, int $perRow): int
    {
        $last = end($tiers);
        if ($stack->shape !== StackShape::Pyramid || $last === false) {
            return $perRow;
        }

        return min($perRow, $last->count());
    }

    /**
     * The sub block re-ordered so the type that can make the **widest row** is on the floor.
     *
     * `quantity × width` — the linear metres a type is worth — because that is what decides how wide its row comes
     * out and so how wide a base the rest of the wall gets to stand on. It is the same measure
     * {@see \App\Command\SceneStackCommand::byType} balances stacks on, and it used to be this sort's own tiebreak
     * before weight took over.
     *
     * Tops keep both their order and their position after the subs: nothing stands on a top, so the width of their
     * row buys nothing, and moving them would break the subs-before-tops rule {@see orderingProblems} enforces.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     * @return list<array{DeviceSpec, int}>
     */
    private static function widestFirst(array $inventory, Stack $stack): array
    {
        $subs = [];
        $tops = [];
        foreach ($inventory as $entry) {
            $entry[0]->subtype === 'sub' ? $subs[] = $entry : $tops[] = $entry;
        }

        usort(
            $subs,
            static fn (array $a, array $b): int
                => $b[1] * RolledBox::widthOf($b[0], self::rollFor($b[0], $stack))
                <=> $a[1] * RolledBox::widthOf($a[0], self::rollFor($a[0], $stack)),
        );

        return [...$subs, ...$tops];
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
    private static function swallows(float $lowTopM, float $flankTopM, float $nextHeightM): bool
    {
        if (is_infinite($nextHeightM)) {
            return false;
        }

        return $lowTopM + $nextHeightM < $flankTopM - self::EPSILON_M;
    }

    /**
     * The tallest cabinet among a packed row's takes so far.
     *
     * @param list<array{DeviceSpec, int, float}> $row
     */
    private static function tallestIn(array $row): float
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
    private static function shortestAfter(array $queue, int $cursor, Stack $stack): float
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
     * puts the heaviest cabinets centrally — the same reason {@see statedMix} and {@see topRow} centre theirs.
     *
     * An **odd** take cannot be halved: `intdiv` goes left and the remainder right, which is exactly what
     * `Tier::mirrored()` does with an odd cabinet count. A take of one is therefore all on the right, and that
     * asymmetry is the honest cost of placing a single cabinet rather than leaving it out.
     *
     * @param list<array{DeviceSpec, int, float}> $row
     */
    private static function centred(array $row): Tier
    {
        usort(
            $row,
            static fn (array $a, array $b): int => RolledBox::heightOf($b[0], $b[2])
                <=> RolledBox::heightOf($a[0], $a[2]),
        );

        $left = [];
        $right = [];

        foreach ($row as $position => [$device, $take, $roll]) {
            if ($position === 0) {
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

        // **THE CENTRE MAY NOT BE SHORTER THAN ITS FLANKS**, or the row has a crater in the middle of it rather than
        // a step at its shoulders. This rule centres the *widest* sub, which was safe while width and height ran
        // together, and the GMSS mid bass broke that: at 1.200 × 0.500 it is the widest cabinet in either system and
        // also by far the shortest, so it was centred between two 1.400 m wall basses. What then stands on the row
        // lands on the flanks and hangs over a 900 mm void — the IQ subs above it came out 130 mm *inside* a wall
        // bass, which is the overlap sweep's job to catch and the fill's job not to propose.
        //
        // Refused rather than reordered: the mid bass genuinely is the widest thing here, so there is nothing to
        // swap it with, and its own row is the honest answer. See {@see centred}, where the packed path solves the
        // same problem by putting the tallest in the middle instead of the widest.
        if (RolledBox::heightOf($device, self::rollFor($device, $stack))
            + self::EPSILON_M < RolledBox::heightOf($flankDevice, self::rollFor($flankDevice, $stack))) {
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
    private static function rowSizeFor(
        DeviceSpec $device,
        int $count,
        Stack $stack,
        int $perRow,
        float $roll,
        float $supportM = INF,
    ): int {
        // Two ceilings, and which one bounds what matters. The SUPPORT decides how wide a row starts, so the fill
        // stops handing {@see StackChecks} rows it is about to reject. The STAGE still bounds the widening below,
        // because a pillar is a worse failure than an overhang — the rule this method already existed for — and a
        // row narrowed to one cabinet by its support is exactly the pillar it is meant to avoid.
        $byStage = self::perTier($device, $stack->maxWidthM, $stack->gapM, $roll);
        $bySupport = self::perTier($device, self::ceilingFor($device, $stack, $roll, $supportM), $stack->gapM, $roll);
        $perTier = min($perRow, $bySupport);

        if ($count < 2 || $perTier >= $count) {
            return $perTier;
        }

        // Widen only while the balanced split would still strand a row of one.
        for ($size = $perTier; $size <= min($count, $byStage); ++$size) {
            if (!in_array(1, self::share($count, (int)ceil($count / $size)), true)) {
                return $size;
            }
        }

        return $perTier;
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
    private static function ceilingFor(DeviceSpec $device, Stack $stack, float $roll, float $supportM): ?float
    {
        if (is_infinite($supportM)) {
            return $stack->maxWidthM;
        }

        $allowed = $supportM + 2 * self::OVERHANG_PER_SIDE * RolledBox::widthOf($device, $roll);

        return $stack->maxWidthM === null ? $allowed : min($allowed, $stack->maxWidthM);
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
    private static function supportOf(array $tiers, Stack $stack): float
    {
        $last = end($tiers);

        return $last === false ? INF : $last->widthM($stack->gapM);
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
