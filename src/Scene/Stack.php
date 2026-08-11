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
     */
    public function __construct(
        public readonly array $from,
        public readonly ?float $maxWidthM = null,
        public readonly ?float $minWidthM = null,
        public readonly ?float $maxHeightM = null,
        public readonly float $interfaceHeightM = self::DEFAULT_INTERFACE_HEIGHT_M,
        public readonly float $gapM = 0.0,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['from', 'max_width_m', 'min_width_m', 'max_height_m', 'interface_height_m', 'gap_m'];
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
        $support = null;

        foreach ($tiers as $index => $tier) {
            $seats = $tier->seats($this->gapM);
            $tallest = null;
            $tallestHeight = -INF;

            foreach ($seats as $segment => [$device, $count, $offsetX]) {
                // Letters for a mixed row's segments, so they cannot be confused with the numeric `-1`,
                // `-2` suffixes a group appends to every copy it makes.
                $id = sprintf(
                    '%s/%d%s',
                    $placement->id,
                    $index + 1,
                    $tier->isMixed() ? chr(ord('a') + $segment) : '',
                );

                $placements[] = new Placement(
                    id: $id,
                    deviceId: $device->id,
                    at: [$at[0] + $offsetX, $at[1]],
                    yawDeg: $placement->yawDeg,
                    pitchDeg: $placement->pitchDeg,
                    rollDeg: $placement->rollDeg,
                    aimAt: $tier->isSub() ? null : $placement->aimAt,
                    aimFocus: $tier->isSub() ? null : $placement->aimFocus,
                    on: $support,
                    fly: null,
                    group: new GroupStack([new Lattice([$count, 1, 1], [$this->gapM, 0.0, 0.0], cycleAxis: Axis::X)]),
                    aimLines: $placement->aimLines,
                    // Only the **top** tier is spread, and only as wide as the tier holding it up. Both
                    // halves of that matter, and each was learned the hard way:
                    //
                    // * Spreading a tier turns it into gaps, and a tier that carries another then holds it
                    //   up over thin air — justifying every tier of the whole inventory put two Flexys
                    //   6.76 m apart with the middle Tecnare floating over the space between them.
                    // * Spreading even the top tier to the *bottom* row's width is no better: the two
                    //   2-ways went to ±1.84 m while the Tecnare row carrying them spans 1.54 m, so they
                    //   stood on nothing at all. A tier can only be distributed across its own support.
                    //
                    // The bottom tier has no support to measure, a mixed tier is several placements with
                    // nothing sensible to distribute one at a time, and a segment of one cabinet has nothing
                    // to spread.
                    align: $index === 0 || $index !== count($tiers) - 1 || $tier->isMixed() || $count < 2
                        ? null
                        : self::envelopeFor(
                            $this->alignFor($tier, $placement->align),
                            self::supportEnvelope($tiers, $index, $this->gapM, $placement->id),
                        ),
                );

                if ($device->dimensions->height > $tallestHeight) {
                    $tallestHeight = $device->dimensions->height;
                    $tallest = $id;
                }
            }

            // Whatever comes next stands on the **tallest** segment of this row, because that is the top
            // face `on:` reads and the one the cabinets physically rest on. For a stepped row the tier above
            // bridges the short segments, which {@see StackSolver} warns about rather than hides.
            $support = $tallest;
        }

        return $placements;
    }

    /**
     * The tier directly below `$index` as an envelope: its id when it is one placement, or its width when it
     * is mixed and so has no single id to name.
     *
     * The tier below is the *support*, and that is the only honest envelope for a tier being spread — a row
     * distributed wider than what it stands on is a row standing on air.
     *
     * @param list<Tier> $tiers
     * @return array{?string, ?float}
     */
    private static function supportEnvelope(array $tiers, int $index, float $gapM, string $stackId): array
    {
        $below = $tiers[$index - 1] ?? null;
        if ($below === null) {
            return [null, null];
        }

        return $below->isMixed()
            ? [null, $below->widthM($gapM)]
            : [sprintf('%s/%d', $stackId, $index), null];
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
