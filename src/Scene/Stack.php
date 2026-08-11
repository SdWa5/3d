<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
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
 * * **`max_width_m`** — how wide the stage or the truss lets the rig be. The row width falls out of it.
 * * **`interface_height_m`** — how high the sub stack's top face has to reach, so the tops fire over a
 *   standing crowd rather than into it. Defaults to {@see DEFAULT_INTERFACE_HEIGHT_M}; state `0` for a rig
 *   that deliberately sits low. Against what we own, two Flexy tiers reach 1.526 m and miss, and two Flexy
 *   tiers plus an Achenbach row reach 2.126 m and clear — which is exactly what the three-tier rig
 *   arrived at by hand.
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
     * @param list<StackEntry> $from **low frequency first** — the order is the fill order
     * @param bool $mirror build this stack as the mirror image of how it solves, so one of a side-by-side pair
     *     reflects the other instead of duplicating it — see {@see Tier::flipped}
     */
    public function __construct(
        public readonly array $from,
        public readonly ?float $maxWidthM = null,
        public readonly ?float $minWidthM = null,
        public readonly ?float $maxHeightM = null,
        public readonly float $interfaceHeightM = self::DEFAULT_INTERFACE_HEIGHT_M,
        public readonly float $gapM = 0.0,
        public readonly bool $mirror = false,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['from', 'max_width_m', 'min_width_m', 'max_height_m', 'interface_height_m', 'gap_m', 'mirror'];
        $unknown = $reader->unknownKeys($allowed);
        if ($unknown !== []) {
            throw new InvalidSpecException(sprintf(
                "stack: unknown key '%s' (allowed: %s)",
                $unknown[0],
                implode(', ', $allowed),
            ));
        }

        $entries = $reader->entryList('from');
        if ($entries === []) {
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
        );
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

        if ($this->maxWidthM === null && $this->interfaceHeightM <= 0.0) {
            // With neither, nothing decides how many cabinets go in a row, and a stack of one-wide tiers
            // is not what anybody meant by leaving both out.
            $messages[] = 'stack needs either max_width_m or interface_height_m to decide how wide a tier is';
        }
        foreach (['max_width_m' => $this->maxWidthM, 'min_width_m' => $this->minWidthM, 'max_height_m' => $this->maxHeightM] as $key => $value) {
            if ($value !== null && $value <= 0.0) {
                $messages[] = sprintf('stack.%s must be positive, got %s', $key, $value);
            }
        }
        if ($this->gapM < 0.0) {
            $messages[] = sprintf('stack.gap_m must not be negative, got %s', $this->gapM);
        }
        if ($this->maxWidthM !== null && $this->minWidthM !== null && $this->minWidthM > $this->maxWidthM) {
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
            if ($mode !== null) {
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
     * below happened to anchor at. The aim is copied onto **top** tiers only, because that is what every
     * hand-written rig does: the subs fire straight ahead and the tops are turned into the room.
     *
     * @param list<Tier> $tiers
     * @return list<Placement>
     */
    public function expand(Placement $placement, array $tiers): array
    {
        $at = $placement->at ?? [0.0, 0.0];
        $placements = [];

        foreach (Gravity::resolve($tiers, $this->gapM, $placement->id) as $index => $runs) {
            $tier = $tiers[$index];
            $isTop = $index === count($tiers) - 1;

            // The long throw first, then the fills beside it — because a fill is solved `outside` the long
            // throw, and `outside` has to name a placement that already exists. Order is otherwise irrelevant:
            // a placement's geometry does not depend on when it was emitted, only its references do.
            [$runs, $throw] = $isTop ? self::throwFirst($runs) : [$runs, null];

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
                        $at[1],
                    ],
                    yawDeg: $placement->yawDeg,
                    pitchDeg: $placement->pitchDeg,
                    rollDeg: $placement->rollDeg + $run['roll'],
                    // A tier may name a focus of its own — the long throw in the middle of a top row wants the
                    // far one and the fills outboard of it are near-field. A stated focus replaces the
                    // placement's aim outright, `aim_at` included: they are two ways of saying the same thing.
                    aimAt: $tier->isSub() || $own !== null ? null : $placement->aimAt,
                    aimFocus: $tier->isSub() ? null : ($own ?? $placement->aimFocus),
                    on: $run['on'],
                    fly: null,
                    // Plain uniform spacing: every cabinet in a run shares one roll, so their bodies do step
                    // evenly. Only the seam *between* the two halves collapses to the gap, and that is already
                    // in `lo`..`hi` because the halves are separate runs.
                    group: new GroupStack([
                        new Lattice([$run['count'], 1, 1], [$this->gapM, 0.0, 0.0], cycleAxis: Axis::X),
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
                );
            }
        }

        return $placements;
    }

    /**
     * A top tier's runs with the **long throw** first, and which one that is.
     *
     * The long throw is the widest top in the row — the same choice {@see StackSolver::topRow} makes when it
     * centres the widest and puts "the smaller boxes, which are fills, outboard of it". Emitting it first is what
     * lets the fills be solved against it: `align.outside` names a placement, and a placement can only be named
     * once it has been resolved.
     *
     * The long throw may itself land in **several** runs — a stepped tier below splits the three M2122s of a tops
     * row into two — so this returns all of them and each fill is later solved against whichever is nearest on its
     * own side. Clearing the nearest one clears the rest, since the others are further away by construction.
     *
     * Null when there are no fills at all: a row of one kind of top has nothing to solve outboard of.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, roll: float}> $runs
     * @return array{list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, roll: float}>, list<array{id: string, lo: float, hi: float}>|null}
     */
    private static function throwFirst(array $runs): array
    {
        if (count($runs) < 2) {
            return [$runs, null];
        }

        $widest = null;
        foreach ($runs as $run) {
            $width = RolledBox::widthOf($run['device'], $run['roll']);
            if ($widest === null || $width > RolledBox::widthOf($widest['device'], $widest['roll']) + 1e-9) {
                $widest = $run;
            }
        }
        if ($widest === null) {
            return [$runs, null];
        }

        $throwRuns = array_values(array_filter(
            $runs,
            static fn (array $run): bool => $run['device'] === $widest['device'],
        ));
        $rest = array_values(array_filter($runs, static fn (array $run): bool => $run['device'] !== $widest['device']));
        if ($rest === []) {
            return [$runs, null];
        }

        return [
            [...$throwRuns, ...$rest],
            array_map(
                static fn (array $run): array => ['id' => $run['id'], 'lo' => $run['lo'], 'hi' => $run['hi']],
                $throwRuns,
            ),
        ];
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
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, roll: float}> $runs
     * @param array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, roll: float} $run
     * @param list<array{id: string, lo: float, hi: float}>|null $throw
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
        if (!$isTop || $index === 0) {
            return null;
        }

        $nearest = $throw === null ? null : self::nearestThrow($throw, $run);
        if ($nearest !== null) {
            return new Alignment(
                mode: LayoutMode::Stereo,
                outside: $nearest['id'],
                insetM: $this->gapM,
                side: $nearest['side'],
            );
        }

        if (count($runs) > 1 || $run['count'] < 2 || $run['on'] === null) {
            return null;
        }

        return self::envelopeFor($this->alignFor($tier, $placement->align), [$run['on'], null]);
    }

    /**
     * The long-throw run this fill has to clear, and which way out is — or null when this run *is* a long throw.
     *
     * Nearest on the fill's own side, because clearing that one clears every other: the rest of the long throw
     * lies further away in the same direction. Which side comes out of the geometry rather than being stated,
     * since a fill is either left or right of the cluster it flanks and nothing else is possible in a row.
     *
     * @param list<array{id: string, lo: float, hi: float}> $throw
     * @param array{id: string, lo: float, hi: float, ...} $run
     * @return array{id: string, side: float}|null
     */
    private static function nearestThrow(array $throw, array $run): ?array
    {
        $centre = ($run['lo'] + $run['hi']) / 2;
        $best = null;
        $distance = INF;

        foreach ($throw as $candidate) {
            if ($candidate['id'] === $run['id']) {
                return null;
            }

            $own = abs($centre - ($candidate['lo'] + $candidate['hi']) / 2);
            if ($own < $distance) {
                $distance = $own;
                $best = [
                    'id' => $candidate['id'],
                    'side' => $centre < ($candidate['lo'] + $candidate['hi']) / 2 ? -1.0 : 1.0,
                ];
            }
        }

        return $best;
    }

    /**
     * @param array{?string, ?float} $envelope
     */
    private static function envelopeFor(?Alignment $align, array $envelope): ?Alignment
    {
        [$across, $widthM] = $envelope;

        if ($align === null) {
            return null;
        }
        if ($widthM !== null) {
            return $align->orWidth($widthM);
        }

        return $across === null ? $align : $align->orAcross($across);
    }
}
