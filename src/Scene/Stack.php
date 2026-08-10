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
 * 3.646 m that way, and `full-rig-three-tier` at a 2.126 m sub/top interface. Both are consequences of the
 * cabinets we own and a bound on the rig, so both can be worked out — and then they stay right when a
 * cabinet is finally measured, instead of quietly becoming a rig that no longer fits the stage.
 *
 * The constraints, any of which may be left out:
 *
 * * **`max_width_m`** — how wide the stage or the truss lets the rig be. The row width falls out of it.
 * * **`interface_height_m`** — how high the sub stack's top face has to reach, so the tops fire over a
 *   standing crowd rather than into it. Defaults to {@see DEFAULT_INTERFACE_HEIGHT_M}; state `0` for a rig
 *   that deliberately sits low. Against what we own, two Flexy tiers reach 1.526 m and miss, and two Flexy
 *   tiers plus an Achenbach row reach 2.126 m and clear — which is exactly what `full-rig-three-tier`
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
     * @param list<string> $from device ids, **low frequency first** — the order is the fill order
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

        $from = $reader->stringList('from');
        if ($from === []) {
            throw new InvalidSpecException('stack.from: expected a list of device ids, low frequency first');
        }

        return new self(
            from: $from,
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
        $placements = [];
        $previous = null;

        foreach ($tiers as $index => $tier) {
            $id = sprintf('%s/%d', $placement->id, $index + 1);
            $isFirst = $previous === null;

            $placements[] = new Placement(
                id: $id,
                deviceId: $tier->device->id,
                at: $placement->at,
                yawDeg: $placement->yawDeg,
                pitchDeg: $placement->pitchDeg,
                rollDeg: $placement->rollDeg,
                aimAt: $tier->isSub() ? null : $placement->aimAt,
                aimFocus: $tier->isSub() ? null : $placement->aimFocus,
                on: $previous,
                fly: null,
                group: new GroupStack([new Lattice([$tier->count, 1, 1], [$this->gapM, 0.0, 0.0], cycleAxis: Axis::X)]),
                aimLines: $placement->aimLines,
                // The bottom tier has nothing below it to be measured against, and a tier of one has
                // nothing to distribute — both would be violations rather than sensible defaults.
                align: $isFirst || $tier->count < 2 ? null : $placement->align?->orAcross($placements[0]->id),
            );

            $previous = $id;
        }

        return $placements;
    }
}
