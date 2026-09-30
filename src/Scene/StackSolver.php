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
    /**
     * @param list<array{DeviceSpec, int}> $inventory device and how many of it, low frequency first
     * @param ?LayoutMode $align how the placement spreads its tiers, which decides the ORDER of the tops row —
     *                           see {@see StackTops::topRow}. Null for a placement that states none, which is `center`.
     * @param ?callable(list<Tier>): bool $survives **WHETHER AN ARRANGEMENT SURVIVES BEING PLACED FOR REAL**, asked of
     *                                              every candidate the search is otherwise willing to accept. This is GEO-11's seam and it is deliberately a
     *                                              callback: nothing here can see a finished placement, because interpenetration is decided by yaw, taper and
     *                                              chamfer rather than by row widths, and teaching the solver that geometry would give the repository a second
     *                                              opinion about where a cabinet's edge is. {@see SceneCompiler} supplies it, since it is the one
     *                                              stage that already knows. **Null means today's behaviour**, which is what every caller that cannot place a
     *                                              cabinet — the solver's own tests among them — needs.
     *
     * @return array{tiers: list<Tier>, problems: list<string>, warnings: list<string>}
     */
    public static function solve(
        array $inventory,
        Stack $stack,
        ?LayoutMode $align = null,
        ?callable $survives = null,
    ): array {
        $ordering = self::orderingProblems($inventory);
        if ([] !== $ordering) {
            return ['tiers' => [], 'problems' => $ordering, 'warnings' => []];
        }

        // **THE SEATING CHECK IS ASKED INSIDE THE SEARCH, MEMOISED BY ARRANGEMENT**, and both halves of that were
        // measured rather than reasoned. Asked of every contender with no memo, a compile per arrangement stopped the
        // sweep finishing at all. Asked instead of the *answer*, with the arrangement struck out and the whole fill
        // re-run when it overlaps, the same test went to **58 minutes** — because a re-run is another walk of a
        // fifty-step ladder, where a memoised check is one compile per *distinct* arrangement and most ladder steps
        // deal rows that have already been judged. Memoised in the search it is **23m40s against 20m14s** with the
        // check off, so it costs three and a half minutes on a bill that is GEO-12's. See TOOL-9.
        $tiers = self::fill($inventory, $stack, $align, $survives);
        if ($stack->mirror) {
            // Reflected before anything is checked, and it changes none of the answers: every check reads widths,
            // heights and labels, and a mirror image has exactly the ones its original had.
            $tiers = array_map(static fn (Tier $tier): Tier => $tier->flipped(), $tiers);
        }
        if ([] === $tiers) {
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
            'problems' => [...$bounds, ...$unsupported, ...StackMix::mixProblems($inventory, $stack)],
            'warnings' => [...$missedInterface, ...$support],
        ];
    }

    /**
     * Whether the caller's placement check accepts this arrangement, or true when there is no caller to ask.
     *
     * @param ?callable(list<Tier>): bool $survives
     * @param list<Tier> $tiers
     */
    private static function survives(?callable $survives, array $tiers, array &$seen): bool
    {
        if (null === $survives) {
            return true;
        }

        $key = self::fingerprint($tiers);
        if (!isset($seen[$key])) {
            $seen[$key] = $survives($tiers);
        }

        return $seen[$key];
    }

    /**
     * One arrangement as a string, so it is placed once however many ladder steps propose it.
     *
     * The tier labels in order, which carry the device, the count and the roll of every segment — so two arrangements
     * sharing a fingerprint are the same cabinets in the same places, and judging one judges the other by right
     * rather than by accident. This is the same redundancy {@see \App\Command\SceneStackCommand} deduplicates whole
     * *scenes* for, one level further down: the ladder walks tens of budgets and most of them deal identical rows.
     *
     * @param list<Tier> $tiers
     */
    private static function fingerprint(array $tiers): string
    {
        return implode('|', array_map(static fn (Tier $tier): string => $tier->label(), $tiers));
    }

    /**
     * Subs before tops, because the fill is bottom-up and the sub/top interface is a boundary in the
     * finished stack rather than a filter over the list. A top listed before a sub would put a Tecnare
     * under a Flexy and still satisfy every height check, which is the sort of plausible nonsense worth
     * refusing outright.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     *
     * @return list<string>
     */
    private static function orderingProblems(array $inventory): array
    {
        $seenTop = null;
        foreach ($inventory as [$device, $count]) {
            if ($count < 1) {
                continue;
            }
            if ('sub' !== $device->subtype) {
                $seenTop ??= $device->id;
                continue;
            }
            if (null !== $seenTop) {
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
     *
     * @return list<Tier>
     */
    private static function fill(
        array $inventory,
        Stack $stack,
        ?LayoutMode $align = null,
        ?callable $survives = null,
    ): array {
        // **A PYRAMID IS ORDERED FOR WIDTH, NOT FOR WEIGHT**, and that is the whole difference between the two
        // shapes rather than a detail of them. The taper below can only ever *narrow* a wall, so a pyramid is decided
        // by how wide its bottom row can be — and that is decided by which type is on the floor. Two wall basses are
        // 220 kg each and 1.34 m of row between them: deepest and heaviest, so weight puts them on the ground, and
        // then nothing above can be wider than 1.34 m. The same twelve cabinets with the six IQ subs on the floor
        // come out 3.28 → 2.56 → 1.54 in three rows and 2.070 m, against 1.34 → 1.20 → 1.63 → 1.63 → 1.54 in five
        // rows and 3.240 m. A metre and a sixth of height, and the V gone, out of nothing but the order.
        //
        // The price is stated rather than hidden: a wide-but-shallow type ends up UNDER a deep one, which is the
        // inversion {@see \App\Spec\FillOrder::byFillOrder} exists to prevent. That is why all three shapes
        // are generated — `free` keeps the deepest and heaviest cabinets on the floor and accepts the V, `pyramid`
        // takes the shape and the height and gives up the ordering. None of them is right for every rig.
        //
        // Subs only, and their block stays before the tops, so {@see orderingProblems} is unaffected.
        // **And the V is the same re-ordering read the other way**, which is what makes it a shape rather than a bound.
        // A wall can only grow by two thirds of a cabinet per side per row, so a V asked for on a wide base has nowhere
        // to go — the base already fills the stage. Putting the type that makes the *narrowest* row on the floor is
        // what leaves it room, exactly as the pyramid puts the widest one there to have something to taper from.
        if (StackShape::Free !== $stack->shape) {
            $inventory = self::widestFirst($inventory, $stack, StackShape::V === $stack->shape);
        }

        $tallestCarried = [];
        $tallestCarriedSubs = -INF;
        $closestCarried = [];
        $closestCarriedMiss = INF;
        $closestCarriedLegal = false;
        $widestAttempt = [];
        // Placement answers already worked out this solve, see {@see survives}.
        $seated = [];

        // **THE SEARCH KNOB IS A ROW WIDTH IN METRES, NOT A CABINET COUNT**, and that is the difference between a
        // search that can express what our gear needs and one that cannot. `$budget` was one integer applied to every
        // device at once: `perRow: 7` meant seven Flexys at 4.3 m *and* seven mid-bass at 8.5 m, and no setting of it
        // reproduced "as many of each as fit 4.40 m", which is 7 Flexys and 3 mid-bass. Nine of our ten cabinets are
        // 0.45–0.66 m wide and `mid-bass` is 1.200 m, so a count stopped standing in for a width the day it
        // arrived. A budget divides by each cabinet's own width instead. See {@see budgetLadder} for where the steps
        // come from and why they are not written down anywhere.
        // The one type the low-end axis is about, named once for the whole search. See {@see LowEndCost::weightOf}
        // on why a centroid over every sub could not see the arrangement the axis exists to choose.
        $lowest = LowEndCost::lowestType($inventory);

        foreach (self::budgetLadder($inventory, $stack) as $budget) {
            // PACKING IS AN EXTRA CANDIDATE, NOT A REPLACEMENT, and measuring says so plainly: on 2 SKRAMs, 3
            // middle subs, 2 mid-bass and 2 2-ways the ordinary deal finds 1.445 m and the pack 2.465 m, because
            // one mixed row of the three tall types beats splitting them. On 6 Flexys and 8 turbo subs the pack
            // wins. Neither wins everywhere, so under a ceiling both are proposed and the shortest that stands up
            // is taken — which also means `mix_with` keeps working there, since the ordinary path still honours it.
            //
            // No flanking search for a packed pass: {@see packedRows} ignores `$pairs` outright, so every pass but
            // the first would re-solve the identical arrangement at the cost of a checker run per candidate pack.
            foreach (null !== $stack->maxSubHeightM ? [true, false] : [false] as $packed) {
                $flanking = $packed ? 0 : StackTops::flankingPairs($inventory, $stack, $budget);
                for ($pairs = $flanking; $pairs >= 0; --$pairs) {
                    // **THE SPREAD IS A CANDIDATE, OFFERED ONLY WHERE IT COULD WIN.** `central` is the only bias
                    // that can prefer a type dealt one to a row — it is strictly taller and strictly more central,
                    // and `low` would reject it every time — so offering it under `low` would double the search to
                    // produce arrangements nothing can choose. See {@see LowEndCost::lowestType}.
                    $spreads = $packed || LowEndBias::Central !== $stack->lowEnd
                        ? [null]
                        : [null, $lowest];
                    foreach (array_unique($spreads, SORT_REGULAR) as $spread) {
                        $tiers = self::fillWith($inventory, $stack, $budget, $pairs, $packed, $align, $spread);
                        // A spread that could not be built returns nothing rather than quietly falling back to the
                        // ordinary arrangement, which would enter the same candidate twice.
                        if ([] === $tiers) {
                            continue;
                        }
                        // **A ROW GAPPED OUT TO THE SHAPE IS A SECOND CANDIDATE, NEVER A REPLACEMENT.** It exists only
                        // where the packed arrangement breaks the width rule of its shape, so a rig that packs keeps
                        // its packed rows. See {@see gappedToShape}.
                        foreach (array_filter([$tiers, self::gappedToShape($tiers, $stack)]) as $tiers) {
                            $widestAttempt = [] === $widestAttempt ? $tiers : $widestAttempt;

                            if ([] !== StackChecks::supportChecks($tiers, $stack)['problems']) {
                                continue;
                            }

                            $subs = StackMetrics::subHeight($tiers);

                            // UNDER A CEILING THE PREFERENCE INVERTS, and that is the whole reason the key exists. Without
                            // one the answer is the widest row that still gets the tops up, so the search returns on its
                            // first hit and every later, narrower arrangement is ignored. With one, a hit is not the answer
                            // — a *better* hit may be further down the search — so the whole space is walked.
                            //
                            // **WHICH HIT IS BETTER IS `target_sub_height_m`, AND IT USED TO BE "THE SHORTEST".** That was a
                            // tie-break standing in for a preference nobody had stated, and it parked the transition just over
                            // 2.0 m wherever it could — legal, and never what anybody wanted, since the useful place for it is
                            // the middle of the band. Now the arrangement nearest the target wins, which is bidirectional: an
                            // arrangement 300 mm under the target loses to one 100 mm over it. Ties keep the first, the widest.
                            //
                            // **THE CEILING BINDS BEFORE THE TARGET DOES**, because a target is a preference between *legal*
                            // arrangements and may never reach past the bound to pick an illegal one. Offered 1.5 m and 3.2 m
                            // against a 3.0 m ceiling, plain distance takes the 3.2 m, which is not a rig at all.
                            //
                            // **Measured, and it is inert at the default target — deliberately kept anyway.** 2.5 m is the
                            // midpoint of the 2–3 m band, so every legal arrangement is within 0.5 m of the aim and every
                            // illegal one is further: distance alone already sorts them, and adding this changed not one scene
                            // of the 148. It stops being redundant the moment somebody states a target off the midpoint —
                            // `--target-sub-height=2.2` puts a 3.05 m arrangement nearer the aim than a 2.0 m one — which is
                            // exactly when the option is used and exactly when nobody would be watching for this.
                            if (null !== $stack->maxSubHeightM) {
                                $legal = $subs <= $stack->maxSubHeightM + StackMetrics::EPSILON_M;
                                // **When nothing is legal the aim is the ceiling, not the target**, and the two are different
                                // answers: against a 1.0 m ceiling this inventory can build 1.363 m or 2.126 m, and 2.126 is
                                // nearer 2.5 while 1.363 is nearer being a rig. Measured — the naive version reported a
                                // 1126 mm miss where the honest answer misses by 363. A preference cannot be allowed to pick
                                // the worse of two failures just because there is no success to choose between.
                                $miss = $legal
                                    ? abs($subs - $stack->targetSubHeightM)
                                    : $subs - $stack->maxSubHeightM;
                                // **THE LOW END'S TWO MEASURES RIDE ON THE SAME SCALAR**, which is the whole of GEO-14's
                                // second and third quarters: the shapes and the bearing rules decide what is allowed, and
                                // within that freedom the arrangement that puts the low end where the caller asked wins.
                                // Added rather than compared separately, because the height band is already a preference
                                // and two preferences that cannot be traded are two gates. See {@see LowEndCost}.
                                $miss += $stack->lowEnd->cost(
                                    LowEndCost::lowness($tiers, $stack, $lowest),
                                    LowEndCost::centrality($tiers, $stack, $lowest),
                                );
                                $better = $legal === $closestCarriedLegal
                                    ? $miss < $closestCarriedMiss
                                    : $legal;

                                // **ASKED LAST, AND ONLY OF A CANDIDATE THAT WOULD WIN.** Whether an arrangement survives
                                // being placed is the one question here that costs a whole compile, and it is the one no
                                // check above can answer: `supportChecks` reads row widths and bearings, where two cabinets
                                // end up inside each other because of yaw, taper and chamfer. Ordering it behind `$better`
                                // is not an optimisation detail — asked of every candidate it ran a compile per arrangement
                                // and the sweep stopped finishing at all. A loser's geometry changes nothing, so it is never
                                // built. See GEO-11.
                                if (StackTops::reachesInterface($tiers, $stack) && $better && self::survives($survives, $tiers, $seated)) {
                                    $closestCarriedLegal = $legal;
                                    $closestCarriedMiss = $miss;
                                    $closestCarried = $tiers;
                                }
                            } elseif (StackTops::reachesInterface($tiers, $stack) && self::survives($survives, $tiers, $seated)) {
                                // **NO CEILING USED TO MEAN NO RANKING AT ALL, AND THAT MADE THE LOW-END AXIS INERT.** This
                                // branch returned the first arrangement that stood up, so on a rig with no
                                // `max_sub_height_m` nothing was ever compared against anything and both values of the axis
                                // produced the same file. It ranks now, on the low end alone — there is no band to miss, so
                                // there is nothing else to weigh — and it still returns the first candidate when the axis
                                // has no opinion, which is what keeps an unaimed rig as cheap as it was.
                                $lowEnd = $stack->lowEnd->cost(
                                    LowEndCost::lowness($tiers, $stack, $lowest),
                                    LowEndCost::centrality($tiers, $stack, $lowest),
                                );
                                if ($lowEnd < $closestCarriedMiss) {
                                    $closestCarriedMiss = $lowEnd;
                                    $closestCarried = $tiers;
                                }
                            }

                            // The fallback is held to the same bar. It is what gets returned when nothing reached the
                            // interface, and returning an arrangement that overlaps would hand the caller a rig no render
                            // could show — the failure this whole seam exists to stop.
                            if ($subs > $tallestCarriedSubs && self::survives($survives, $tiers, $seated)) {
                                $tallestCarriedSubs = $subs;
                                $tallestCarried = $tiers;
                            }
                        }
                    }
                }
            }
        }
        if ([] !== $closestCarried) {
            return $closestCarried;
        }
        if ([] !== $tallestCarried) {
            // Stands up but sits lower than asked for, which is a warning. Under a ceiling this is the arrangement
            // that misses it — nothing cleared the interface, so there is no legal arrangement to rank, and
            // {@see StackChecks::boundsProblems} names the miss rather than this silently picking a side.
            return $tallestCarried;
        }

        // Nothing stands up at any row width. Hand back the **widest** attempt rather than the tallest, so the
        // error names the most favourable case there was: "even at its widest it overhangs 610 mm" tells you
        // the rig is impossible, where the narrowest attempt's 956 mm would just look like a bad guess.
        return $widestAttempt;
    }

    /**
     * This arrangement with the rows its shape refuses **gapped out** to the width the shape asks for, or null when
     * it needs none or cannot be mended that way.
     *
     * A row's width used to be its cabinet count, since every pair of neighbours stood one stack gap apart. So a
     * pyramid whose base is narrower than the row above it, and a V whose upper row is narrower than the one below,
     * were refused even where standing the cabinets further apart would reach the width. This offers that
     * arrangement. One even gap across the whole row, and only where the packed row breaks the rule, so a rig
     * that packs keeps its packed rows.
     *
     * * **Pyramid**, top down. A row may step out past its support by a tenth of its outboard cabinet, so the row
     *   under it is widened to that, and widening it may in turn ask the same of the row under that.
     * * **V**, bottom up. A sub row may not be narrower than its support, so it is widened to its full width.
     *
     * **The gap has no cap of its own**, which the owner settled on 2026-10-01. What limits it is whether the row
     * above is still carried, and {@see StackChecks::supportChecks} asks that of this candidate as of any other:
     * each cabinet on a third of its width, and each cabinet of a gapped row weighed on its own by
     * {@see Stability::tips}. Rounded up to a whole millimetre, so the label names the gap exactly.
     *
     * @param list<Tier> $tiers
     *
     * @return list<Tier>|null
     */
    private static function gappedToShape(array $tiers, Stack $stack): ?array
    {
        $gapped = $tiers;
        $changed = false;

        if (StackShape::Pyramid === $stack->shape) {
            for ($index = count($gapped) - 1; $index > 0; --$index) {
                $above = $gapped[$index];
                $need = $above->widthM($stack->gapM) - 2 * StackChecks::PYRAMID_SHOULDER * $above->outerWidthM();
                $row = self::gappedTo($gapped[$index - 1], $need, $stack);
                if (false === $row) {
                    return null;
                }
                if (null !== $row) {
                    $gapped[$index - 1] = $row;
                    $changed = true;
                }
            }
        } elseif (StackShape::V === $stack->shape) {
            for ($index = 1; $index < count($gapped); ++$index) {
                if (!$gapped[$index]->isSub()) {
                    continue;
                }
                // The support's full width, not less its tolerance, or every gapped row would come out that much
                // narrower than the one under it and a tall wall would narrow by a centimetre a row.
                $need = $gapped[$index - 1]->widthM($stack->gapM);
                $row = self::gappedTo($gapped[$index], $need, $stack);
                if (false === $row) {
                    return null;
                }
                if (null !== $row) {
                    $gapped[$index] = $row;
                    $changed = true;
                }
            }
        }

        return $changed ? $gapped : null;
    }

    /**
     * `$tier` gapped out to at least `$needM`, null when it is that wide already, false when it cannot get there:
     * a single cabinet has no gap to widen, and the stage bounds how wide a row may stand.
     */
    private static function gappedTo(Tier $tier, float $needM, Stack $stack): Tier|false|null
    {
        if ($tier->widthM($stack->gapM) >= $needM) {
            return null;
        }
        if ($tier->count() < 2) {
            return false;
        }

        $gapM = max(
            $stack->gapM,
            ceil(($needM - $tier->cabinetWidthM()) / ($tier->count() - 1) * 1000 - 1e-6) / 1000,
        );
        $row = $tier->withGap($gapM);

        return null !== $stack->maxWidthM && $row->widthM($stack->gapM) > $stack->maxWidthM + StackMetrics::EPSILON_M
            ? false
            : $row;
    }

    /**
     * One arrangement: the mixed bottom row at `$pairs` per side, then a balanced set of rows per device — or, when
     * `$packed`, the subs packed into as few rows as the width allows regardless of how many types share one.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     *
     * @return list<Tier>
     */
    private static function fillWith(
        array $inventory,
        Stack $stack,
        RowBudget $budget,
        int $pairs,
        bool $packed = false,
        ?LayoutMode $align = null,
        ?string $spreadId = null,
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
            [$packed, $remaining] = self::packedRows($remaining, $stack, $budget);
            $tops = StackTops::topRow($remaining, $stack, $align);

            $rows = null === $tops ? $packed : [...$packed, $tops];

            return array_map(
                static fn (Tier $tier, int $row): Tier => $tier->mirrored($stack->mirrorStyle, $row),
                $rows,
                array_keys($rows),
            );
        }

        $tiers = [];
        if (null !== $spreadId) {
            // **BEFORE THE MIXED BOTTOM ROW, BECAUSE BOTH WANT THE SAME CABINETS.** A mixed bottom row would
            // consume the very type this is spreading and the spread would have nothing left to deal. Where it
            // cannot be built the candidate is simply the ordinary one, which is what `null` means here.
            $spread = self::spreadRows($remaining, $stack, $budget, $spreadId);
            if (null === $spread) {
                return [];
            }
            [$spreadTiers, $remaining] = $spread;
            $tiers = $spreadTiers;
        } elseif ($pairs > 0) {
            $bottom = StackMix::mixedBottomRow($remaining, $stack, $budget, $pairs);
            if (null !== $bottom) {
                [$tiers[], $remaining] = $bottom;
            }
        }

        [$lifts, $remaining] = StackLifts::reserveLifts($remaining, $stack, $budget);

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
            if ($count < 1 || 'sub' !== $device->subtype) {
                continue;
            }

            // A tier that asked to share its row does so here, wherever it sits — mixing used to be the
            // bottom row's privilege alone, decided by a heuristic. `mix_with` names it outright, and the
            // same two gates still apply: matching heights, and the devices have to exist and be free.
            // Capped like every other row-building path, which it was not: a stated `mix_with` built its row from the
            // raw width and so could come out holding more cabinets than the row under it, which is the V the pyramid
            // exists to forbid. {@see StackLifts::reserveLifts} cannot be capped the same way, because it reserves its flanks before
            // any tier exists and {@see StackMetrics::pyramidCeiling} has nothing to measure against then.
            $stated = StackMix::statedMix(
                $remaining,
                $index,
                $stack,
                $budget->narrowedTo(
                    StackMetrics::pyramidCeiling($tiers, $stack, $device, StackMetrics::rollFor($device, $stack)),
                ),
                StackMetrics::supportOf($tiers, $stack),
            );
            if (null !== $stated) {
                [$tiers[], $remaining] = $stated;
                continue;
            }

            // Cabinets held back from the rows below, standing either side of this one to close the step.
            if (isset($lifts[$index])) {
                [$source, $lift] = $lifts[$index];
                $tiers[] = new Tier([
                    [$source, $lift, StackMetrics::rollFor($source, $stack)],
                    [$device, $count, StackMetrics::rollFor($device, $stack)],
                    [$source, $lift, StackMetrics::rollFor($source, $stack)],
                ]);
                $remaining[$index] = [$device, 0];
                continue;
            }

            $roll = StackMetrics::rollFor($device, $stack);
            $perTier = self::rowSizeFor(
                $device,
                $count,
                $stack,
                $budget->narrowedTo(StackMetrics::pyramidCeiling($tiers, $stack, $device, $roll)),
                $roll,
                StackMetrics::supportOf($tiers, $stack),
            );
            // **ONE PER ROW WHERE THE CALLER ASKED FOR IT**, which is the candidate the dealer would otherwise
            // never produce: it takes as many of a type as the budget allows, so two SKRAMs go side by side and
            // no arrangement anywhere in the search has one above the other. `central` needs that arrangement to
            // exist before it can prefer it — each cabinet on the centre line rather than the pair straddling it.
            // Offered as a candidate and ranked like every other, so it wins only where the cost says so.
            if ($spreadId === $device->id) {
                $perTier = 1;
            }
            // Balanced rather than greedy: the same number of rows, but no short one left at the top to
            // fail to carry whatever is above it.
            $rows = (int) ceil($count / $perTier);
            foreach (StackMetrics::share($count, $rows) as $row) {
                $tiers[] = Tier::of($device, $row, $roll);
            }
        }

        $tops = StackTops::topRow($remaining, $stack, $align);
        if (null !== $tops) {
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
     * The lowest-reaching type dealt **one to a row, each flanked** — the arrangement `central` exists to choose
     * and the one nothing else in this solver produces.
     *
     * **Two SKRAMs side by side straddle the centre line; one above the other sits on it.** That is the whole of
     * the difference, and until this existed the second arrangement was not in the search at all: the dealer takes
     * as many of a type as the budget allows, so both went in one row and the cost had a single candidate to rank.
     * Capping the type at one per row is not enough either — it gives the cabinet a row of its own, 0.61 m wide
     * under a 3.6 m row, which fails the support check and is never returned. **A spread cabinet has to be
     * flanked into a full-width row**, which is what this builds.
     *
     * The shape is {@see StackMix::mixedBottomRow}'s, once per cabinet rather than once per rig, and it obeys the same two
     * rules: **the centre may not be shorter than its flanks**, or the row has a crater in the middle that the row
     * above lands either side of, and the flanks come in pairs so the row stays symmetric about its own centre.
     *
     * Returns null wherever it cannot be built — no flank type, not enough of it to pair every row, or a flank
     * taller than the cabinet it stands beside — and null means the search simply does not get this candidate.
     *
     * @param array<int, array{DeviceSpec, int}> $remaining
     *
     * @return array{list<Tier>, array<int, array{DeviceSpec, int}>}|null
     */
    private static function spreadRows(array $remaining, Stack $stack, RowBudget $budget, string $spreadId): ?array
    {
        $centre = null;
        foreach ($remaining as $index => [$device, $count]) {
            if ($device->id === $spreadId && $count > 1) {
                $centre = $index;
            }
        }
        if (null === $centre) {
            return null;
        }

        [$device, $available] = $remaining[$centre];
        $flank = StackMetrics::flankingSub($remaining, $centre, $device);
        if (null === $flank) {
            return null;
        }

        [$flankDevice, $flankAvailable] = $remaining[$flank];
        $roll = StackMetrics::rollFor($device, $stack);
        $flankRoll = StackMetrics::rollFor($flankDevice, $stack);

        // The crater guard, in the same words {@see StackMix::mixedBottomRow} uses it: a centre shorter than its flanks
        // leaves the row above hanging over a hole in the middle.
        if (RolledBox::heightOf($device, $roll) + StackMetrics::EPSILON_M < RolledBox::heightOf($flankDevice, $flankRoll)) {
            return null;
        }

        // How wide a row of the flank device would naturally be, which is the width these rows are built to.
        // **Capped by the stock as well as by the budget**, because an unbounded budget answers `PHP_INT_MAX` and
        // a target width computed from that is not a number anybody can build to.
        $perRow = min(
            StackMetrics::perTier(
                $flankDevice,
                RowBudget::narrower($budget->ceilingFor($flankDevice, $stack, $flankRoll), $stack->maxWidthM),
                $stack->gapM,
                $flankRoll,
            ),
            $flankAvailable,
        );
        $flankWidth = RolledBox::widthOf($flankDevice, $flankRoll);
        $target = $perRow * ($flankWidth + $stack->gapM) - $stack->gapM;

        $room = $target - RolledBox::widthOf($device, $roll) - 2 * $stack->gapM;
        $pairs = (int) floor($room / (2 * ($flankWidth + $stack->gapM)));
        // Every row gets the same number of pairs, so the stock has to cover all of them — an arrangement whose
        // last row is thinner than the rest is the stepped wall the bearing rules refuse anyway.
        $pairs = min($pairs, intdiv($flankAvailable, 2 * $available));
        if ($pairs < 1) {
            return null;
        }

        $tiers = [];
        for ($row = 0; $row < $available; ++$row) {
            $tiers[] = new Tier([
                [$flankDevice, $pairs, $flankRoll],
                [$device, 1, $roll],
                [$flankDevice, $pairs, $flankRoll],
            ]);
        }

        $remaining[$centre] = [$device, 0];
        $remaining[$flank] = [$flankDevice, $flankAvailable - 2 * $pairs * $available];

        return [$tiers, $remaining];
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
     * order, which the command builds with {@see \App\Spec\FillOrder::byFillOrder} — deepest first, so
     * the deepest cabinets end up on the floor carrying everything. A row may therefore only take types that are
     * **adjacent in that order**: the rows are contiguous runs of the list, read bottom-up, and the only decision
     * left is where the cuts go. A 2-way can never land beside an Achenbach because it is nowhere near it in the
     * ordering, so a frequency inversion is not merely avoided — it is unreachable.
     *
     * **PACKED GREEDILY IS NOT GOOD ENOUGH, and the reason is the row that ends up on top.** Filling each row to
     * the width it can take leaves the leftovers in the last one: all 41 speakers on a 3.80 m stage came out with
     * a 1.825 m top sub row under a 3.744 m row of tops, overhanging 960 mm each side. That is the same failure
     * {@see StackMetrics::share} exists to prevent for a single device — "balanced rather than greedy, so no short row is left
     * at the top to fail to carry whatever is above it" — so the packer balances the same way. Greedy decides how
     * many rows the wall needs; the rows are then packed again to the *average* width of that many, which is a
     * meaningful number rather than a tuned one: `total / rows` is what each row is if the wall is that tall.
     * Divisors below the greedy count are tried in turn until the pack still fits in no more rows than greedy
     * needed, so balancing never costs a tier.
     *
     * Each row is sized against the tier below it exactly as an ordinary row is, through {@see StackMetrics::ceilingFor}, with
     * the allowance measured on the **candidate** cabinet: whatever is added last ends up outermost, so it is the
     * one whose overhang the bearing rule will judge.
     *
     * A row always takes at least one cabinet, even when the support is too narrow for it. That is deliberate:
     * refusing would loop forever, and {@see StackChecks} names an unsupported tier far better than this could —
     * the same reason {@see fill} hands back its widest attempt rather than nothing.
     *
     * @param list<array{DeviceSpec, int}> $remaining
     *
     * @return array{list<Tier>, list<array{DeviceSpec, int}>} the sub rows bottom-up, and the tops left to place
     */
    private static function packedRows(array $remaining, Stack $stack, RowBudget $budget): array
    {
        $queue = [];
        foreach (array_keys($remaining) as $index) {
            [$device, $count] = $remaining[$index];
            if ($count < 1 || 'sub' !== $device->subtype) {
                continue;
            }
            $queue[] = [$device, $count, StackMetrics::rollFor($device, $stack)];
            $remaining[$index] = [$device, 0];
        }
        if ([] === $queue) {
            return [[], $remaining];
        }

        $greedy = self::packTo($queue, $stack, $budget, INF);
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
        for ($divisor = (float) $rows; $divisor > 1.0 - StackMetrics::EPSILON_M; $divisor -= 0.25) {
            $candidates[] = self::packTo($queue, $stack, $budget, $total / $divisor);
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
            $stands = [] === StackChecks::supportChecks($pack, $stack)['problems'];
            $height = StackMetrics::subHeight($pack);
            $count = count($pack);

            if ($bestStands && !$stands) {
                continue;
            }
            if ($stands === $bestStands
                && ($height > $bestHeight + StackMetrics::EPSILON_M
                    || (abs($height - $bestHeight) <= StackMetrics::EPSILON_M && $count >= $bestRows))) {
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
     *
     * @return list<Tier>
     */
    private static function packTo(array $queue, Stack $stack, RowBudget $budget, float $budgetM): array
    {
        $tiers = [];
        $support = INF;
        $cursor = 0;
        $placed = 0;

        while ($cursor < count($queue)) {
            $row = [];
            $count = 0;
            $width = 0.0;
            // **THE SEARCH BOUND IS THE ROW'S, SO IT IS READ ONCE PER ROW AND NOT ONCE PER DEVICE.** A packed row holds
            // several types, and asking {@see RowBudget::ceilingFor} inside the loop below would re-answer it for each
            // one in turn — a row full at 3.28 m for six iq-subs becomes roomy again the moment a 0.670 m wall bass is
            // considered, because six of *those* are 4.12 m. Measured: it pulled a wall bass into the bottom row and
            // cost the GMSS pyramid its whole arrangement. The support and the pyramid ceilings stay per device, since
            // both are allowances scaled by the cabinet on the end of the row.
            $seats = $budget->seats;

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
                // overlap sweep found. Same rule as {@see StackLifts::liftAbove}'s, asked of a row rather than of a flank.
                if ([] !== $row && StackMetrics::swallows(
                    RolledBox::heightOf($device, $roll),
                    StackMetrics::tallestIn($row),
                    StackMetrics::shortestAfter($queue, $cursor, $stack),
                )) {
                    break;
                }

                // **FOUR BOUNDS THAT ARE ALL WIDTHS, SO THE ROW ANSWERS TO ONE NUMBER.** What the support carries, what
                // this pack is aiming at, what the search is offering, and the pyramid's own hint. The last two used to
                // be a seat count checked separately in the loop below, which is the premise GEO-12 removed: a row of
                // `n` is only "about `n` cabinets across" while the cabinets are one size, and ours run 0.45 m to
                // 1.200 m. A {@see RowBudget} that bounds seats rather than metres still arrives here as a width,
                // because the width of exactly `n` cabinets of this device admits exactly `n` of them.
                $ceiling = min(
                    RowBudget::narrower(
                        RowBudget::narrower(
                            $budget->widthM,
                            StackMetrics::ceilingFor($device, $stack, $roll, $support),
                        ),
                        StackMetrics::pyramidCeiling($tiers, $stack, $device, $roll),
                    ) ?? INF,
                    $budgetM,
                );
                $own = RolledBox::widthOf($device, $roll);
                $take = 0;

                while ($placed + $take < $stock && $count + $take + 1 <= $seats) {
                    $step = $width + ($count + $take > 0 ? $stack->gapM : 0.0) + $own;
                    if ($step > $ceiling + StackMetrics::EPSILON_M) {
                        break;
                    }
                    $width = $step;
                    ++$take;
                }

                // Nothing of this device fits the row as it stands. An empty row has to take one anyway — see
                // {@see packedRows} — and a row with something in it is simply finished, so the next one starts here.
                if ($take < 1) {
                    if ([] !== $row) {
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
                // This device is not exhausted, so the row is: it stopped on width or on `$budget`, and the rest
                // of this device is the bottom of the next row. Cutting mid-device is how a type spans two rows,
                // which the balanced deal has always done — see {@see StackMetrics::share}.
                break;
            }

            $tier = StackMetrics::centred($row);
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
     *
     * @return list<array{DeviceSpec, int}>
     */
    private static function widestFirst(array $inventory, Stack $stack, bool $reversed = false): array
    {
        $subs = [];
        $tops = [];
        foreach ($inventory as $entry) {
            'sub' === $entry[0]->subtype ? $subs[] = $entry : $tops[] = $entry;
        }

        // `$reversed` is the V: narrowest row-maker on the floor, so the wall has somewhere to grow. Same measure and
        // the same tops rule, read the other way round, because a V and a pyramid are one question with two answers
        // and writing the comparison out twice is how the two would drift apart.
        $direction = $reversed ? -1 : 1;

        usort(
            $subs,
            static fn (array $a, array $b): int => $direction * (
                $b[1] * RolledBox::widthOf($b[0], StackMetrics::rollFor($b[0], $stack))
                <=> $a[1] * RolledBox::widthOf($a[0], StackMetrics::rollFor($a[0], $stack))
            ),
        );

        return [...$subs, ...$tops];
    }

    /**
     * How many of a device go in a row — the search's row count, **widened if that would leave a pillar**.
     *
     * `$budget` is the search variable: {@see fill} narrows it to buy height, because narrower rows mean more of
     * them. **It is a width in metres and it is divided by this cabinet's own width**, so one budget deals as many of
     * each type as that type's size allows rather than the same integer to all of them. That is the whole of GEO-12:
     * 4.40 m is 7 Flexys and 3 mid-bass, and no cabinet count expresses both at once.
     *
     * It is not a stated constraint, and a pillar overrules it. Three Achenbachs at two per row are dealt `2 + 1`,
     * and a one-wide sub tier is refused as a pillar — while all three in one row are 1.840 m and fit the stage with
     * two metres to spare.
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
        RowBudget $budget,
        float $roll,
        float $supportM = INF,
    ): int {
        // Two ceilings, and which one bounds what matters. The SUPPORT decides how wide a row starts, so the fill
        // stops handing {@see StackChecks} rows it is about to reject. The STAGE still bounds the widening below,
        // because a pillar is a worse failure than an overhang — the rule this method already existed for — and a
        // row narrowed to one cabinet by its support is exactly the pillar it is meant to avoid.
        $byStage = StackMetrics::perTier($device, $stack->maxWidthM, $stack->gapM, $roll);
        $perTier = StackMetrics::perTier(
            $device,
            RowBudget::narrower(
                $budget->ceilingFor($device, $stack, $roll),
                StackMetrics::ceilingFor($device, $stack, $roll, $supportM),
            ),
            $stack->gapM,
            $roll,
        );

        if ($count < 2 || $perTier >= $count) {
            return $perTier;
        }

        // Widen only while the balanced split would still strand a row of one.
        for ($size = $perTier; $size <= min($count, $byStage); ++$size) {
            if (!in_array(1, StackMetrics::share($count, (int) ceil($count / $size)), true)) {
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
    /**
     * Every step the search walks, coarse to fine, with **no bound at all** first of them.
     *
     * **Two dimensions, walked as a union rather than as a product**: every row width with the seats unbounded, then
     * every cabinet count with the width unbounded. {@see RowBudget} has the measurement that says both are needed —
     * a width alone cost 49 rigs, all of them refused on bearing rather than on the search running out, because
     * "the same number of every type" and "the same metres of every type" reach different arrangements and neither
     * contains the other. Additive keeps the search a few times longer where a product would square it.
     *
     * **The widths are derived from the cabinets rather than written down**, and that is the whole difference between
     * this and the `WIDTH_LADDER_M` constant 0.82.0 deleted. That one was eight metre figures nobody could source, and
     * it doubled as a stage bound the owner had never asked for. These steps are the row widths the inventory can
     * actually make, so a budget no row can land on is never tried and a budget that does land on one is a real
     * arrangement rather than a round number.
     *
     * **The unbounded step is always first**, so nothing is bounded by this and no rig can be refused for missing a
     * budget. It also keeps the no-ceiling path returning the widest arrangement on its first hit, which is what
     * {@see fill} has always done.
     *
     * Widths descending and deduplicated within an epsilon, then counts descending. Runtime is explicitly not a
     * constraint on this project, and the arrangements this buys are the point.
     *
     * @param list<array{DeviceSpec, int}> $inventory
     *
     * @return list<RowBudget>
     */
    private static function budgetLadder(array $inventory, Stack $stack): array
    {
        $widths = [];
        $widest = 0;
        foreach ($inventory as [$device, $count]) {
            $roll = StackMetrics::rollFor($device, $stack);
            $fit = min($count, StackMetrics::perTier($device, $stack->maxWidthM, $stack->gapM, $roll));
            $widest = max($widest, $fit);
            for ($n = $fit; $n >= 1; --$n) {
                $widths[] = Tier::of($device, $n, $roll)->widthM($stack->gapM);
            }
        }

        rsort($widths);

        $ladder = [RowBudget::unbounded()];
        $last = null;
        foreach ($widths as $width) {
            if (null === $last || abs($last - $width) > StackMetrics::EPSILON_M) {
                $ladder[] = new RowBudget($width);
                $last = $width;
            }
        }

        for ($seats = $widest; $seats >= 1; --$seats) {
            $ladder[] = new RowBudget(null, $seats);
        }

        return $ladder;
    }
}
