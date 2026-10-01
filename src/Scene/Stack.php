<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;

/**
 * A rig described by what it has to satisfy, instead of by a tier per row written out by hand.
 *
 * Every `full-rig*` scene is the same decision made by a person: subs low, 18" above them, tops on top, and
 * a count per row chosen so the wall comes out a sensible width. `full-rig` arrived at 2 × 6 Flexys at
 * 3.646 m that way, and the hand-built three-tier rig at a 2.126 m sub/top interface. Both are consequences of the
 * cabinets we own and a bound on the rig, so both can be worked out — and then they stay right when a
 * cabinet is finally measured, instead of quietly becoming a rig that no longer fits the stage.
 *
 * The constraints, any of which may be left out:
 *
 * * **`max_width_m`** — how wide the stage or the truss lets the rig be. The row width falls out of it. **Left out
 *   means no bound at all** rather than a generous one, and that is a real distinction: how wide a rig comes out
 *   does not matter unless somebody says it does, which is stated by the owner and is why
 *   {@see \App\Command\SceneStackCommand} stopped defaulting the option.
 * * **`interface_height_m`** — how high the sub stack's top face has to reach, so the tops fire over a
 *   standing crowd rather than into it. Defaults to {@see DEFAULT_INTERFACE_HEIGHT_M}; state `0` for a rig
 *   that deliberately sits low. Against what we own, two Flexy tiers reach 1.526 m and miss, and two Flexy
 *   tiers plus an Achenbach row reach 2.126 m and clear — which is exactly what the three-tier rig
 *   arrived at by hand.
 * * **`max_sub_height_m`** — how high the sub stack's top face is allowed to reach, which is the *mirror* of
 *   `interface_height_m` and the reason both exist. An interface height is a floor and the solver chases it by
 *   narrowing rows; this is a ceiling, and stating one changes what the solver optimises for — the **shortest**
 *   arrangement that stands up rather than the tallest that fits. It also lets a row hold more than one device
 *   type, which is the only thing that can make a stack of many types short: see {@see StackSolver::packedRows}.
 * * **`shape`** — `pyramid` forbids a row from being wider than the row below it, `free` (the default, and what
 *   the solver always did) lets the bearing rule decide. Worth stating because the bearing rule permits *growth*:
 *   two thirds of a cabinet past each end, which is legally carried and reads as a V balanced on its point. See
 *   {@see StackShape}.
 * * **`min_width_m` / `max_height_m`** — the other two bounds. A minimum width is how you ask for a wide
 *   short wall rather than a tall narrow one out of the same cabinets; a maximum height is a ceiling or a
 *   rigging limit.
 *
 * Either a width bound or an interface height is enough on its own. Both together is a solve that can fail,
 * and {@see StackSolver} says so rather than shipping a near miss.
 *
 * **There is no common module to lean on**, and that is the real hazard. Our five cabinets have five widths
 * (0.4656 / 0.500 / 0.591 / 0.600 / 0.610 m) and five heights (0.600 / 0.763 / 0.836 / 0.914 / 0.960 m), no
 * two of them multiples of anything. Nothing in the solver may assume a grid: an arrangement that looks
 * right on the Flexys alone falls apart the moment an Achenbach or a SKRAM is in the same stack.
 */
final class Stack
{
    /**
     * Above head height for a standing audience, with room for the cabinet's own mouth.
     *
     * A default rather than a required key because a rig whose tops fire into the crowd is the mistake
     * worth defaulting against, and because leaving it out silently would make the constraint decorative.
     */
    public const DEFAULT_INTERFACE_HEIGHT_M = 2.0;

    /**
     * The sub/top transition this rig **aims at**, as opposed to the two bounds it has to stay between.
     *
     * **A bound says which arrangements are allowed and a target says which of them is best**, and until this existed
     * the solver had no answer to the second question — so it took the *shortest* arrangement that cleared the
     * interface, on the reasoning that a lower rig is a safer rig. That is a defensible tie-break and it is not what
     * anybody wants: it parks the transition just over 2.0 m whenever it can, when the useful place for it is the
     * middle of the band, where the tops clear a standing crowd with room to spare and the wall is still well under a
     * truss.
     *
     * 2.5 m is the middle of the 2–3 m band and is stated by the owner of the gear. It is a *preference* and never a
     * refusal: an arrangement is legal if it sits between {@see DEFAULT_INTERFACE_HEIGHT_M} and `max_sub_height_m`,
     * and the target only decides which of the legal ones is returned.
     */
    public const DEFAULT_TARGET_SUB_HEIGHT_M = 2.5;

    /**
     * @param list<StackEntry> $from **low frequency first** — the order is the fill order
     * @param bool $mirror build this stack as the mirror image of how it solves, so one of a side-by-side pair
     *                     reflects the other instead of duplicating it — see {@see Tier::flipped}
     * @param float|null $slideSlackM how far sideways a badly-carried row may be moved, or null for "it may not
     *                                move". **This is a statement about neighbours, not about gravity.** A row does not have to be centred on what
     *                                carries it, and refusing to move it refuses rigs that stand up: GMSS's only arrangement inside the sub height
     *                                band puts a 2.400 m packed row on a 1.310 m support, where centred the outboard nuke lands on 45 mm of its
     *                                590 and slid 150 mm both ends are carried. What makes moving it unsafe is everything *else* in the scene:
     *                                stacks are spaced on their widest tier and their envelopes deliberately overlap in x, so an unbounded slide
     *                                reaches into the stack beside it — measured as 180 mm of interpenetration across five `all-3` scenes.
     *                                **A stack in a rig therefore gets half the clearance to its neighbour, less a working gap**, so two rows
     *                                sliding towards each other still leave air between them. A stack with nothing beside it is bounded only by
     *                                the stage. See {@see Gravity::resolve}
     *
     *     **A scene states it as `slide_slack_m`, and it has to.** This was the last solve input `scene:stack` used
     *     that the schema could not express, so every solo rig was written by a solve that allowed sliding and rebuilt
     *     by one that forbade it. Measured on `stacked-all--------1-free----turned--alternate-center`, the same
     *     inventory came out as four rows reaching 1.860 m of subs with the slack and as two rows reaching 0.660 m
     *     without it — a 23.5 m line of cabinet whose own header comment described a different rig.
     */
    public function __construct(
        public readonly array $from,
        public readonly ?float $maxWidthM = null,
        public readonly ?float $minWidthM = null,
        public readonly ?float $maxHeightM = null,
        public readonly float $interfaceHeightM = self::DEFAULT_INTERFACE_HEIGHT_M,
        public readonly float $gapM = 0.0,
        public readonly bool $mirror = false,
        public readonly ?float $maxSubHeightM = null,
        public readonly StackShape $shape = StackShape::Free,
        public readonly MirrorStyle $mirrorStyle = MirrorStyle::Alternate,
        public readonly ?float $slideSlackM = null,
        public readonly float $targetSubHeightM = self::DEFAULT_TARGET_SUB_HEIGHT_M,
        /**
         * Where the lowest-reaching cabinets belong — as central as the rig allows, or as low as it allows.
         *
         * **Stated in the file and not only on the command line, because a `stack:` block is re-solved on every
         * build.** An axis that existed at sweep time alone would come back as the default the first time
         * anything rebuilt the scene, and the two variants would be one file. See {@see LowEndBias}.
         */
        public readonly LowEndBias $lowEnd = LowEndBias::Low,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = [
            'from', 'max_width_m', 'min_width_m', 'max_height_m', 'interface_height_m', 'gap_m', 'mirror',
            'max_sub_height_m', 'target_sub_height_m', 'shape', 'mirror_style', 'slide_slack_m', 'low_end',
        ];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("stack: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        $entries = $reader->entryList('from');
        if ([] === $entries) {
            throw new InvalidSpecException('stack.from: expected a list of device ids, low frequency first');
        }

        return new self(
            from: array_map(StackEntry::fromValue(...), $entries),
            maxWidthM: $reader->optionalFloat('max_width_m'),
            minWidthM: $reader->optionalFloat('min_width_m'),
            maxHeightM: $reader->optionalFloat('max_height_m'),
            interfaceHeightM: $reader->optionalFloat('interface_height_m', self::DEFAULT_INTERFACE_HEIGHT_M)
                ?? self::DEFAULT_INTERFACE_HEIGHT_M,
            gapM: $reader->optionalFloat('gap_m', 0.0) ?? 0.0,
            mirror: $reader->optionalBool('mirror'),
            maxSubHeightM: $reader->optionalFloat('max_sub_height_m'),
            targetSubHeightM: $reader->optionalFloat('target_sub_height_m', self::DEFAULT_TARGET_SUB_HEIGHT_M)
                ?? self::DEFAULT_TARGET_SUB_HEIGHT_M,
            // **The key that was missing, and its absence rebuilt every solo rig as a different one.** Unstated it
            // reads as null, which is "a row may not move", so a file solved with sliding allowed re-solved without
            // it. `.inf` is the value a solo stack carries, and YAML parses it to a float INF.
            slideSlackM: $reader->optionalFloat('slide_slack_m'),
            shape: self::shapeFrom($reader->optionalString('shape')),
            lowEnd: LowEndBias::tryFrom($reader->optionalString('low_end') ?? LowEndBias::Low->value)
                ?? throw new InvalidSpecException(sprintf("stack.low_end: unknown value '%s' (allowed: %s)", (string) $reader->optionalString('low_end'), implode(', ', array_column(LowEndBias::cases(), 'value')))),
            mirrorStyle: MirrorStyle::tryFrom($reader->optionalString('mirror_style') ?? MirrorStyle::Alternate->value)
                ?? throw new InvalidSpecException(sprintf("stack.mirror_style: unknown value '%s' (allowed: %s)", (string) $reader->optionalString('mirror_style'), implode(', ', array_column(MirrorStyle::cases(), 'value')))),
        );
    }

    /**
     * The stated shape, or the default when a scene says nothing.
     *
     * An unknown value is **refused rather than defaulted**, because the three shapes differ in what they build and a
     * silently-ignored `shape: pyramide` would ship the other rig with nothing to say so. `free` is the default
     * because it is what the solver always did, so an existing scene keeps the rig it had.
     */
    private static function shapeFrom(?string $stated): StackShape
    {
        if (null === $stated) {
            return StackShape::Free;
        }

        $shape = StackShape::tryFrom($stated);
        if (null === $shape) {
            throw new InvalidSpecException(sprintf("stack.shape: unknown value '%s' (allowed: %s)", $stated, implode(', ', array_column(StackShape::cases(), 'value'))));
        }

        return $shape;
    }

    /**
     * Everything wrong with the keys themselves, before any cabinet is looked at.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $messages = [];

        foreach ($this->from as $entry) {
            $messages = [...$messages, ...$entry->problems()];
        }

        if (null === $this->maxWidthM && $this->interfaceHeightM <= 0.0) {
            // With neither, nothing decides how many cabinets go in a row, and a stack of one-wide tiers
            // is not what anybody meant by leaving both out.
            $messages[] = 'stack needs either max_width_m or interface_height_m to decide how wide a tier is';
        }
        foreach ([
            'max_width_m' => $this->maxWidthM,
            'min_width_m' => $this->minWidthM,
            'max_height_m' => $this->maxHeightM,
            'max_sub_height_m' => $this->maxSubHeightM,
            'target_sub_height_m' => $this->targetSubHeightM,
        ] as $key => $value) {
            if (null !== $value && $value <= 0.0) {
                $messages[] = sprintf('stack.%s must be positive, got %s', $key, $value);
            }
        }
        if (null !== $this->maxSubHeightM && $this->interfaceHeightM > $this->maxSubHeightM) {
            // A floor above its own ceiling. Not a preference to resolve quietly in either direction: the two keys
            // say opposite things about the same number, and picking one would ship a rig whose author asked for
            // the other. Named with both numbers, because which one is the mistake is the author's to decide.
            $messages[] = sprintf(
                'stack.interface_height_m (%s) is above stack.max_sub_height_m (%s) — the tops cannot be required '
                .'to clear a height the subs are forbidden to reach',
                $this->interfaceHeightM,
                $this->maxSubHeightM,
            );
        }
        if ($this->gapM < 0.0) {
            $messages[] = sprintf('stack.gap_m must not be negative, got %s', $this->gapM);
        }
        if (null !== $this->maxWidthM && null !== $this->minWidthM && $this->minWidthM > $this->maxWidthM) {
            $messages[] = sprintf(
                'stack.min_width_m (%s) is wider than stack.max_width_m (%s)',
                $this->minWidthM,
                $this->maxWidthM,
            );
        }

        return $messages;
    }

    /**
     * How this tier is distributed: its own `align` if its entry states one, otherwise the stack's.
     *
     * Worth knowing what per-tier alignment can and cannot do here. It **states** which tier the alignment
     * belongs to, at the point that tier is declared, instead of one setting for the whole rig whose effect
     * you have to work out. What it cannot do is spread a tier that carries another one — that rule is
     * unchanged and absolute, because spreading a tier turns it into gaps and the tier above then stands over
     * air. In a plain tower only the top tier carries nothing, so today exactly one tier can actually be
     * spread; per-tier `align` decides *which alignment* that tier uses, not *how many* tiers may spread.
     */
    private function alignFor(Tier $tier, ?Alignment $fallback): ?Alignment
    {
        foreach ($tier->segments as [$device, $count]) {
            $mode = $this->entryFor($device->id)?->align;
            if (null !== $mode) {
                return new Alignment($mode);
            }
        }

        return $fallback;
    }

    /**
     * The entry naming `$deviceId`, so the solver and the expansion can ask what that tier wants without
     * either of them carrying a copy of the list.
     *
     * Null when the stack was built without entries at all, which is how the solver's own tests construct it —
     * and the fallbacks that answers are exactly the behaviour there was before per-tier options existed.
     */
    public function entryFor(string $deviceId): ?StackEntry
    {
        foreach ($this->from as $entry) {
            if ($entry->device === $deviceId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The solved tiers as ordinary placements, which is the whole trick: nothing downstream — the compiler,
     * the report, the render, the build plan — ever learns that a stack was involved.
     *
     * Each tier stands `on` the one below, so no height is written; every tier is given the stack's own
     * `at`, so a short top tier stays centred on the rig's centre line rather than on whatever the row
     * below happened to anchor at. Front to back every cabinet stands flush with the front of the deepest one,
     * see {@see Placement::$frontYM}. The aim is copied onto **top** tiers only, because that is what every
     * hand-written rig does: the subs fire straight ahead and the tops are turned into the room.
     *
     * @param list<Tier> $tiers
     *
     * @return list<Placement>
     */
    public function expand(Placement $placement, array $tiers): array
    {
        $at = $placement->at ?? [0.0, 0.0];
        $placements = [];

        $resolved = Gravity::resolve($tiers, $this->gapM, $placement->id, $this->slideSlackM, $this->maxWidthM);

        // Flush at the front, on the plane the deepest cabinet's front stands on when centred on `at`. So the rig's
        // front face does not move, and every shallower cabinet stands back by half of what it lacks in depth.
        $deepest = 0.0;
        foreach ($resolved as $runs) {
            foreach ($runs as $run) {
                $deepest = max($deepest, $run['device']->dimensions->depth);
            }
        }
        $frontY = $at[1] - $deepest / 2;

        foreach ($resolved as $index => $runs) {
            $tier = $tiers[$index];
            $isTop = $index === count($tiers) - 1;

            // **A STEREO TOPS ROW IS PUSHED APART**, and until now it was not. `align` cannot spread a tier that
            // landed in several runs — a mixed row always does — so a stereo tops row came out at natural spacing in
            // the middle of the rig: five tops spanning 2.011 m over an Achenbach row spanning 3.100. The ordering
            // was right and the image was still narrow, which is the opposite of what stereo is for.
            //
            // Spread here rather than through {@see Alignment} because there is nothing to *solve*. Alignment exists
            // for landing an aimed cabinet's edge exactly on an envelope, which is a fixed point; this only has to
            // hand out the slack between the clusters, and moving them apart can only increase the clearance an
            // aimed cabinet needs. Each run keeps its own internal spacing, which is what `stereo` means — "natural
            // spacing kept within each column".
            if ($isTop && $index > 0 && LayoutMode::Stereo === $this->alignFor($tier, $placement->align)?->mode) {
                // **RESEATED AFTER THE SPREAD, BECAUSE MOVING A ROW CHANGES WHAT IT STANDS ON.** The spread walks the
                // runs out across the *whole* support span, and that span routinely straddles supports at different
                // heights — a stepped wall is the normal case here, not the exception. Without the reseat a run pushed
                // outboard keeps the height of the support it left: `stacked-all-2-stereo` put a tecnare at 2.347 m
                // with nothing under it and `stacked-all-3-free-stereo` a turbo top at 4.668 m, both refused by the
                // sweep and neither visible to the tier checks, which had already read the pre-move bearings.
                $runs = Gravity::reseat(
                    self::spreadApart($runs, $resolved[$index - 1], $tier, $tier->gapFor($this->gapM)),
                    Gravity::topFacesOf($resolved[$index - 1]),
                );
            }

            // The long throw first, then the fills beside it — because a fill is solved `outside` the long
            // throw, and `outside` has to name a placement that already exists. Order is otherwise irrelevant:
            // a placement's geometry does not depend on when it was emitted, only its references do.
            $outward = LayoutMode::Stereo === $this->alignFor($tier, $placement->align)?->mode;
            [$runs, $throw] = $isTop ? self::throwFirst($runs, $outward) : [$runs, null];

            foreach ($runs as $run) {
                $own = $this->entryFor($run['device']->id)?->aim;

                $placements[] = new Placement(
                    id: $run['id'],
                    deviceId: $run['device']->id,
                    // A run's `lo`..`hi` is where its **bodies** go, and a rolled cabinet does not sit centred
                    // on its own origin — at 90 the body is entirely to the right of it, at 270 entirely to the
                    // left. So the origin is worked back from the body rather than assumed to be its middle.
                    at: [
                        $at[0] + RolledBox::originFor(
                            $run['device'],
                            $run['roll'],
                            ($run['lo'] + $run['hi']) / 2,
                        ),
                        $frontY + $run['device']->dimensions->depth / 2,
                    ],
                    yawDeg: $placement->yawDeg,
                    pitchDeg: $placement->pitchDeg,
                    rollDeg: $placement->rollDeg + $run['roll'],
                    // A tier may name a focus of its own — the long throw in the middle of a top row wants the
                    // far one and the fills outboard of it are near-field. A stated focus replaces the
                    // placement's aim outright, `aim_at` included: they are two ways of saying the same thing.
                    aimAt: $tier->isSub() || null !== $own ? null : $placement->aimAt,
                    aimFocus: $tier->isSub() ? null : ($own ?? $placement->aimFocus),
                    on: $run['on'],
                    fly: null,
                    // Plain uniform spacing: every cabinet in a run shares one roll, so their bodies do step
                    // evenly. Only the seam *between* the two halves collapses to the gap, and that is already
                    // in `lo`..`hi` because the halves are separate runs.
                    group: new GroupStack([
                        new Lattice([$run['count'], 1, 1], [$tier->gapFor($this->gapM), 0.0, 0.0], cycleAxis: Axis::X),
                    ]),
                    aimLines: $placement->aimLines,
                    // Only the **top** tier is spread, and only as wide as what holds it up. Both halves of
                    // that matter, and each was learned the hard way:
                    //
                    // * Spreading a tier turns it into gaps, and a tier that carries another then holds it
                    //   up over thin air — justifying every tier of the whole inventory put two Flexys
                    //   6.76 m apart with the middle Tecnare floating over the space between them.
                    // * Spreading even the top tier to the *bottom* row's width is no better: the two
                    //   2-ways went to ±1.84 m while the Tecnare row carrying them spans 1.54 m, so they
                    //   stood on nothing at all.
                    //
                    // A tier that landed in several places is left alone: each run has its own support and
                    // its own width, and there is no single envelope to justify them into.
                    align: $this->alignmentFor($tier, $placement, $run, $runs, $throw, $isTop, $index),
                    frontYM: $frontY,
                );
            }
        }

        return $placements;
    }

    /**
     * A stereo tops row's runs pushed apart until they span what carries them.
     *
     * The slack — how much wider the support is than the row — is handed out equally between **neighbouring segments
     * of the tier**, so each cluster keeps its own internal spacing and only the air between clusters grows.
     *
     * **Segments, not runs, and the difference is measured.** {@see StackTops} builds a stereo row as a palindrome,
     * PSL's five EF 6 as `2× | 1× | 2×`, and gravity merges neighbouring cabinets of one device on one support into
     * one run without regard to where a segment ends. Over three 1.18 m ESX columns that is runs of 2, 2 and 1, and
     * equal air between those put the odd top 0.31 m right of the centre line with a pair beside it. So a run is cut
     * where its cabinets change segment, and every segment moves as one. Where the runs already were the segments,
     * which is most rows, nothing changes. A tier whose cabinets no longer match its segments, because gravity
     * rearranged it, is spread by its runs as before. That is what `stereo`
     * has always meant ({@see LayoutMode::Stereo}: "natural spacing kept within each column"); the only thing new is
     * that a row which landed in several runs can now do it, where {@see alignmentFor} had to give up on one.
     *
     * **Bounded by the support, not by `max_width_m`.** The row is spread until its outer edges reach the edges of
     * the tier below and no further, so every top stays over something. Spreading to a stage bound instead would put
     * the outer cabinets past the sub wall — the exact failure {@see StackChecks::bearingProblems} exists to catch,
     * and there is no point proposing it. A row already as wide as its support, or a row of one run, is returned
     * untouched.
     *
     * The centre of the row does not move, which keeps {@see SceneCompiler::frontCentre}'s focus resolution and the
     * rig's centre line where they were.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $below
     *
     * @return list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>
     */
    private static function spreadApart(array $runs, array $below, Tier $tier, float $gapM): array
    {
        if ([] === $below) {
            return $runs;
        }

        usort($runs, static fn (array $a, array $b): int => $a['lo'] <=> $b['lo']);
        $clusters = self::bySegment($runs, $tier, $gapM) ?? array_map(static fn (array $run): array => [$run], $runs);
        if (count($clusters) < 2) {
            return $runs;
        }

        $rowLo = $clusters[0][0]['lo'];
        $last = $clusters[count($clusters) - 1];
        $rowHi = $last[count($last) - 1]['hi'];
        $supportLo = min(array_column($below, 'lo'));
        $supportHi = max(array_column($below, 'hi'));

        $slack = ($supportHi - $supportLo) - ($rowHi - $rowLo);
        if ($slack <= 1e-9) {
            return $runs;
        }

        // Equal air between each neighbouring pair, and the whole row re-centred on the support afterwards so the
        // spread is symmetric however the clusters happened to be sized.
        $step = $slack / (count($clusters) - 1);
        $centre = ($rowLo + $rowHi) / 2;

        $spread = [];
        foreach ($clusters as $position => $cluster) {
            $shift = ($position - (count($clusters) - 1) / 2) * $step
                + (($supportLo + $supportHi) / 2 - $centre);
            foreach ($cluster as $run) {
                $run['lo'] += $shift;
                $run['hi'] += $shift;
                $spread[] = $run;
            }
        }

        return self::relettered($spread, $runs);
    }

    /**
     * The runs cut where their cabinets change segment and grouped by segment, or null when the cabinets do not match
     * the tier's segments one for one.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs ordered left to right
     *
     * @return list<list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>>|null
     */
    private static function bySegment(array $runs, Tier $tier, float $gapM): ?array
    {
        $owners = [];
        foreach ($tier->segments as $segment => [$device, $count]) {
            for ($seat = 0; $seat < $count; ++$seat) {
                $owners[] = [$segment, $device->id];
            }
        }
        if (array_sum(array_column($runs, 'count')) !== count($owners)) {
            return null;
        }

        $clusters = [];
        $cabinet = 0;
        foreach ($runs as $run) {
            $pitch = RolledBox::widthOf($run['device'], $run['roll']) + $gapM;
            $from = 0;
            while ($from < $run['count']) {
                [$segment, $device] = $owners[$cabinet + $from];
                if ($device !== $run['device']->id) {
                    return null;
                }
                $to = $from;
                while ($to + 1 < $run['count'] && $owners[$cabinet + $to + 1][0] === $segment) {
                    ++$to;
                }
                $piece = $run;
                $piece['count'] = $to - $from + 1;
                $piece['lo'] = $run['lo'] + $from * $pitch;
                $piece['hi'] = $piece['lo'] + $piece['count'] * $pitch - $gapM;
                // One segment is one placement wherever it can be, so a pair that gravity seated on two supports
                // toes in as one pair, the same as its mirror image on the other side. The reseat lands it afterwards.
                $previous = isset($clusters[$segment]) ? count($clusters[$segment]) - 1 : null;
                if (null !== $previous && $clusters[$segment][$previous]['device'] === $piece['device']
                    && $clusters[$segment][$previous]['roll'] === $piece['roll']) {
                    $clusters[$segment][$previous]['count'] += $piece['count'];
                    $clusters[$segment][$previous]['hi'] = $piece['hi'];
                } else {
                    $clusters[$segment][] = $piece;
                }
                $from = $to + 1;
            }
            $cabinet += $run['count'];
        }

        return array_values($clusters);
    }

    /**
     * Fresh letters for a tier whose runs a cut regrouped, in the `<tier>a`, `<tier>b` form {@see Gravity::resolve}
     * uses, so every placement id stays unique and runs left to right. Untouched when the runs did not change.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $spread
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     *
     * @return list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>
     */
    private static function relettered(array $spread, array $runs): array
    {
        if (array_column($spread, 'count') === array_column($runs, 'count')) {
            return $spread;
        }
        $base = 1 === count($runs) ? $runs[0]['id'] : substr($runs[0]['id'], 0, -1);
        foreach ($spread as $slot => $run) {
            $spread[$slot]['id'] = $base.chr(ord('a') + $slot);
        }

        return $spread;
    }

    /**
     * A top tier's runs in emission order, and what each one is spaced against.
     *
     * **EVERY RUN AFTER THE INNERMOST IS SOLVED AGAINST ITS INNER NEIGHBOUR**, and that uniformity is the fix rather
     * than an aesthetic. This used to chain the *fills* only, on the reasoning that the long throw is positioned by
     * gravity on its own support and should not be moved. True of one throw run, and wrong the moment the throw lands
     * in several: nothing spaced those against each other at all, and two runs of the same device came out **360 mm
     * inside each other** — over half a cabinet — because each was placed independently and neither knew the other
     * was there. Chaining fills to a throw and then leaving the throws unspaced is a star with a hole in the middle.
     *
     * The mechanism is the one that already worked for fills: `align.outside` names an earlier placement and
     * {@see SceneCompiler::clearedOutside} bisects the **real rotated boxes** until the working gap is genuinely
     * there. That is what nominal widths cannot do — two tops aimed at one focus from different x take different
     * yaws, the outer one turns further, and it turns *into* its neighbour.
     *
     * **The innermost run keeps gravity's position**, so the centre of the row does not move and the cluster the rig
     * is built around stays where the solver put it. Ordering by distance from the row's centre is what makes the
     * chain buildable in one pass: `align.outside` can only name a placement that has already been resolved, and
     * everything inboard of a run sorts before it.
     *
     * Null when there is nothing to chain — a row that landed in one run has no neighbour to clear.
     *
     * **`$outward` CHAINS A STEREO ROW FROM ITS ENDS INSTEAD**, see {@see outwardChain}.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     *
     * @return array{list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>, array<string, array{id: string, side: float, hug: bool}>|null} the runs in emission order, and what each is spaced against
     */
    private static function throwFirst(array $runs, bool $outward = false): array
    {
        if (count($runs) < 2) {
            return [$runs, null];
        }

        $lo = min(array_column($runs, 'lo'));
        $hi = max(array_column($runs, 'hi'));
        $centre = ($lo + $hi) / 2;

        // Innermost first. Ties broken by position so the order is deterministic rather than dependent on how the
        // tier happened to be segmented.
        usort($runs, static function (array $a, array $b) use ($centre): int {
            $byDistance = abs(($a['lo'] + $a['hi']) / 2 - $centre) <=> abs(($b['lo'] + $b['hi']) / 2 - $centre);

            return 0 !== $byDistance ? $byDistance : $a['lo'] <=> $b['lo'];
        });

        if ($outward && self::mirroredRuns($runs)) {
            return self::outwardChain($runs);
        }

        $ordered = $runs;
        $references = [[
            'id' => $ordered[0]['id'],
            'lo' => $ordered[0]['lo'],
            'hi' => $ordered[0]['hi'],
        ]];

        $chain = [];
        foreach (array_slice($ordered, 1) as $run) {
            $against = self::nearest($references, $run);
            $chain[$run['id']] = ['id' => $against['id'], 'side' => $against['side'], 'hug' => false];
            $references[] = ['id' => $run['id'], 'lo' => $run['lo'], 'hi' => $run['hi']];
        }

        return [$ordered, $chain];
    }

    /**
     * Whether both halves have matching cabinets at matching support heights.
     *
     * An asymmetric row keeps gravity's inward chain. Reversing one across a support step put a turbo top
     * 32.4 mm into the raised fill beside it on the pooled GMSS rig. Mirrored rows can pack outward without
     * using one side's support arrangement as an assumption about the other.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     */
    private static function mirroredRuns(array $runs): bool
    {
        usort($runs, static fn (array $a, array $b): int => $a['lo'] <=> $b['lo']);
        $centre = ($runs[0]['lo'] + $runs[count($runs) - 1]['hi']) / 2;
        foreach ($runs as $index => $left) {
            $right = $runs[count($runs) - 1 - $index];
            if ($left['device']->id !== $right['device']->id || $left['count'] !== $right['count']
                || abs($left['top'] - $right['top']) > StepSolver::TOLERANCE_M
                || abs($left['lo'] + $right['hi'] - 2 * $centre) > StepSolver::TOLERANCE_M) {
                return false;
            }
        }

        return true;
    }

    /**
     * A stereo tops row chained from its ends: the innermost run and the outermost run on each side keep gravity's
     * position, and every run between is pulled out against its outer neighbour until the working gap is all the
     * air between them.
     *
     * **THE NEAR-FIELD TOPS STAND AS FAR OUT AS THEY CAN, AGAINST THE LONG THROWS AT THE ENDS**, as the owner stated
     * on 2026-10-01. A stereo row already deals the long throws to its ends, see {@see StackTops::topRow}, and gravity
     * seats those on the ends of the row below. Chained inward-out as {@see throwFirst} does, a fill only had to clear
     * the middle and stayed where the row dealt it. Chained from the ends it hugs the long throw, with the air of the
     * row collected in the middle. A side with only one run beside the innermost has no fill to pull, so that run is
     * chained to the innermost the usual way, which keeps two throw runs from landing inside each other.
     *
     * Emission order is innermost first, then each side from the outside in, so every reference is placed before
     * the run that names it.
     *
     * @param non-empty-list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs innermost first
     *
     * @return array{list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>, array<string, array{id: string, side: float, hug: bool}>}
     */
    private static function outwardChain(array $runs): array
    {
        $inner = $runs[0];
        $middle = ($inner['lo'] + $inner['hi']) / 2;
        $ordered = [$inner];
        $chain = [];

        foreach ([-1.0, 1.0] as $direction) {
            $side = array_values(array_filter(
                array_slice($runs, 1),
                static fn (array $run): bool => (($run['lo'] + $run['hi']) / 2 - $middle) * $direction > 0.0,
            ));
            // Outside in: the furthest from the middle first.
            usort($side, static fn (array $a, array $b): int => abs(($b['lo'] + $b['hi']) / 2 - $middle) <=> abs(($a['lo'] + $a['hi']) / 2 - $middle));

            if (1 === count($side)) {
                $ordered[] = $side[0];
                $chain[$side[0]['id']] = ['id' => $inner['id'], 'side' => $direction, 'hug' => false];

                continue;
            }

            foreach ($side as $index => $run) {
                $ordered[] = $run;
                if (0 < $index) {
                    // Inboard of its reference, so the side that is out of it points back at the middle.
                    $chain[$run['id']] = ['id' => $side[$index - 1]['id'], 'side' => -$direction, 'hug' => true];
                }
            }
        }

        return [$ordered, $chain];
    }

    /**
     * The reference run this one has to clear: the nearest already-placed run, and which way out is.
     *
     * Nearest, because clearing that one clears every other on the same side — the rest lie further away in the
     * same direction by construction. Which side comes out of the geometry rather than being stated, since a fill
     * is either left or right of what it flanks and nothing else is possible in a row.
     *
     * @param non-empty-list<array{id: string, lo: float, hi: float}> $references
     * @param array{lo: float, hi: float, ...} $run
     *
     * @return array{id: string, side: float, distance: float}
     */
    private static function nearest(array $references, array $run): array
    {
        $centre = ($run['lo'] + $run['hi']) / 2;

        $best = null;
        foreach ($references as $reference) {
            $own = ($reference['lo'] + $reference['hi']) / 2;
            $distance = abs($centre - $own);
            if (null === $best || $distance < $best['distance']) {
                $best = ['id' => $reference['id'], 'side' => $centre < $own ? -1.0 : 1.0, 'distance' => $distance];
            }
        }

        return $best;
    }

    /**
     * What this run is justified against, or null for the spacing the tier already gives it.
     *
     * Two different jobs share the key, and they are worth telling apart:
     *
     * * **A fill beside the long throw** is solved `outside` it, with the working gap as the clearance. This is
     *   what a nominal gap cannot do: two tops aimed at one focus from different x take different *yaws*, the
     *   outer one turns more, and it turns *into* its neighbour — 1.7 mm at the far focus and 80 mm at the near
     *   one, on a 20 mm gap. The solve pushes the fill out until the air is really there.
     * * **A whole top tier that landed in one run** is spread across its own support, which is the older rule and
     *   unchanged: only a tier nothing stands on may be spread, and only as wide as what holds it up.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     * @param array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float} $run
     * @param array<string, array{id: string, side: float, hug: bool}>|null $throw what each fill clears, from {@see throwFirst}
     */
    private function alignmentFor(
        Tier $tier,
        Placement $placement,
        array $run,
        array $runs,
        ?array $throw,
        bool $isTop,
        int $index,
    ): ?Alignment {
        if (!$isTop || 0 === $index) {
            return null;
        }

        $nearest = $throw[$run['id']] ?? null;
        if (null !== $nearest) {
            // `clear_of` rather than `outside`, and the difference is measured rather than cosmetic. `outside` reduces
            // the reference to the x span it covers, and an aimed cabinet's span runs far wider than its body — a 2-way
            // yawed 29.4° presents 0.8523 m on a 0.5 m cabinet. Down a chain of fills that compounds, and it drove a
            // GMSS turbo top 514 mm off the run that had given it its height, leaving it hanging 228 mm over a step
            // while gravity still believed it was carried. A fill only has to not *touch* its neighbour, which is what
            // {@see Alignment::$clearOf} asks and what {@see Interpenetration::gapBetween} measures on the shells.
            return new Alignment(
                mode: LayoutMode::Stereo,
                clearOf: $nearest['id'],
                insetM: $tier->gapFor($this->gapM),
                side: $nearest['side'],
                hug: $nearest['hug'],
            );
        }

        if (count($runs) > 1 || $run['count'] < 2 || null === $run['on']) {
            return null;
        }

        return self::envelopeFor($this->alignFor($tier, $placement->align), [$run['on'], null]);
    }

    /**
     * @param array{?string, ?float} $envelope
     */
    private static function envelopeFor(?Alignment $align, array $envelope): ?Alignment
    {
        [$across, $widthM] = $envelope;

        if (null === $align) {
            return null;
        }
        if (null !== $widthM) {
            return $align->orWidth($widthM);
        }

        return null === $across ? $align : $align->orAcross($across);
    }
}
