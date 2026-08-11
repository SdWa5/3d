<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Where a solved stack's cabinets actually come to rest — **gravity, one cabinet at a time**.
 *
 * This is the whole of it: a cabinet falls until it hits whatever is under *it*, not until it reaches the
 * height of the tallest thing in the row below. A row of Flexys with two SKRAMs in the middle is 151 mm
 * taller in the middle, so the Flexys above the SKRAMs rest at 0.914 m and the ones above Flexys at 0.763 —
 * an uneven top, and nothing hanging in the air. Resting the whole row at the tallest height was what left
 * four Flexys floating; refusing to mix heights at all was the wrong fix for it.
 *
 * It lives in its own class because **two callers have to agree exactly**: {@see Stack::expand} builds the
 * placements, and {@see StackSolver} has to reject an arrangement whose cabinets would land badly. A second
 * copy of falling that drifted from the first would let the solver bless a rig the expansion then builds
 * differently, which is the one failure no render would show.
 */
final class Gravity
{
    /**
     * How much two cabinets must overlap in x before one counts as standing on the other.
     *
     * A micrometre. Cabinets in neighbouring runs are separated by a working gap, so this only has to rule out
     * the case where two spans touch exactly at an edge — which happens, because a row's cabinets are laid out
     * by repeated addition and a boundary can land on a hair.
     */
    private const CONTACT_EPSILON_M = 1e-6;

    /**
     * How much of a cabinet has to be over its support before it counts as carried.
     *
     * Half. It is the companion of {@see StackSolver::OVERHANG_TOLERANCE_M}'s half-a-cabinet rule rather than a
     * second opinion: that one refuses a *tier* more than half off the tier below, this one refuses a *cabinet*
     * more than half off whatever it personally landed on. A stepped row needs the second.
     */
    public const MIN_BEARING = 0.5;

    /**
     * How close to the landing height a second support has to be before it carries the cabinet too.
     *
     * A centimetre, the same figure {@see StackSolver::OVERHANG_TOLERANCE_M} calls "what the rubber feet and the
     * working gaps absorb". Requiring supports to be *exactly* level was too strict to be physical: a cabinet
     * bridging two neighbours a millimetre apart in height rests on both of them, and crediting it with only the
     * taller one understates what is holding it up.
     */
    private const LEVEL_TOLERANCE_M = 0.01;

    /**
     * The runs of every tier, bottom up, each one already knowing what it stands on and how well.
     *
     * @param list<Tier> $tiers
     * @param string $prefix the placement id the run ids hang off
     * @return list<list<array{
     *     id: string, device: DeviceSpec, count: int, lo: float, hi: float,
     *     top: float, on: string|null, bearing: float, settle: float, roll: float
     * }>> one entry per tier, in the same order
     */
    public static function resolve(array $tiers, float $gapM, string $prefix): array
    {
        /** @var list<array{id: string, lo: float, hi: float, top: float}> $below what the next tier lands on */
        $below = [];
        $resolved = [];

        foreach ($tiers as $index => $tier) {
            $isTop = $index === count($tiers) - 1;
            $runs = self::runs($tier->seats($gapM), $below, $gapM);

            // Only when the ordinary row would leave a cabinet hanging. Rearranging a tier that is already
            // carried would be a change for its own sake, and every rig that stands up today keeps its layout.
            if ($isTop && self::worstBearing($runs) < self::MIN_BEARING) {
                $outboard = self::outboardSeats($tier->seats($gapM), $below, $gapM);
                $rescued = $outboard === null ? null : self::runs($outboard, $below, $gapM);
                if ($rescued !== null && self::worstBearing($rescued) > self::worstBearing($runs)) {
                    $runs = $rescued;
                }
            }

            $current = [];
            foreach ($runs as $slot => $run) {
                // Letters when a tier lands in more than one place, so they cannot be confused with the
                // numeric `-1`, `-2` suffixes a group appends to every copy it makes.
                $runs[$slot]['id'] = $id = sprintf(
                    '%s/%d%s',
                    $prefix,
                    $index + 1,
                    count($runs) > 1 ? chr(ord('a') + $slot) : '',
                );

                $current[] = [
                    'id' => $id,
                    'lo' => $run['lo'],
                    'hi' => $run['hi'],
                    // The **rolled** height: a Flexy on its side raises what stands on it by 591 mm, not 763.
                    'top' => $run['top'] + RolledBox::heightOf($run['device'], $run['roll']),
                ];
            }

            $resolved[] = $runs;
            $below = $current;
        }

        return $resolved;
    }

    /**
     * The worst-carried cabinet in a tier, as a fraction of its own width.
     *
     * @param list<array{bearing: float, ...}> $runs
     */
    private static function worstBearing(array $runs): float
    {
        $worst = 1.0;
        foreach ($runs as $run) {
            $worst = min($worst, $run['bearing']);
        }

        return $worst;
    }

    /**
     * The top tier laid out **fills outboard**: the end segments over the end supports, the rest centred on
     * what is between them.
     *
     * This is what makes flanking a load-bearing row usable at all. A mixed Achenbach row is 163 mm lower in
     * its middle than at its Flexy shoulders, and the ordinary contiguous top row laid across that step clips a
     * shoulder by 5.6 mm — whereupon falling does what falling does and lifts a whole 2-way onto 1.2 % of its
     * own footprint. Seating each end segment on the shoulder it was going to catch lands the fills squarely,
     * raised and outboard, with the Tecnares centred on the Achenbachs between them. Which is how anybody rigs
     * outboard fills anyway, and it is the shape {@see StackSolver::topRow} already builds — widest cluster in
     * the middle, the small boxes outside it — finally given the x positions to match.
     *
     * Null unless each of the three groups fits the span it would be given. A segment wider than its shoulder
     * would only trade one bad landing for another, and inventing some other distribution is guessing.
     *
     * @param list<array{DeviceSpec, int, float, float}> $seats
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return list<array{DeviceSpec, int, float, float}>|null
     */
    private static function outboardSeats(array $seats, array $below, float $gapM): ?array
    {
        $last = count($seats) - 1;
        $outer = count($below) - 1;
        if ($last < 1 || $outer < 1 || ($last > 1 && $outer < 2)) {
            return null;
        }

        $middle = array_slice($seats, 1, $last - 1);
        $groups = [[[$seats[0]], $below[0]['lo'], $below[0]['hi']]];
        if ($middle !== []) {
            $groups[] = [$middle, $below[1]['lo'], $below[$outer - 1]['hi']];
        }
        $groups[] = [[$seats[$last]], $below[$outer]['lo'], $below[$outer]['hi']];

        $placed = [];
        $reach = -INF;
        foreach ($groups as [$segments, $lo, $hi]) {
            $span = self::spanOf($segments, $gapM);
            if ($span > $hi - $lo + self::CONTACT_EPSILON_M) {
                return null;
            }

            $x = ($lo + $hi) / 2 - $span / 2;
            if ($reach > $x + self::CONTACT_EPSILON_M) {
                // The groups would be seated into each other. Two support runs can sit closer together than the
                // segments they are being given — a one-wide tower is the case, where the outer runs are a
                // single column each and the fills end up 10 mm inside the tops. Falling back to the contiguous
                // row is the honest answer: this layout is a repair, and it cannot repair everything.
                return null;
            }

            foreach ($segments as [$device, $count, , $roll]) {
                $own = $count * RolledBox::widthOf($device, $roll) + ($count - 1) * $gapM;
                $placed[] = [$device, $count, $x + $own / 2, $roll];
                $x += $own + $gapM;
            }
            $reach = $x - $gapM + $gapM;
        }

        return $placed;
    }

    /**
     * How wide a set of segments stands side by side — cabinets plus one gap between every neighbouring pair,
     * across a segment boundary as much as within one.
     *
     * @param list<array{DeviceSpec, int, float, float}> $segments
     */
    private static function spanOf(array $segments, float $gapM): float
    {
        $width = 0.0;
        $cabinets = 0;
        foreach ($segments as [$device, $count, , $roll]) {
            $width += $count * RolledBox::widthOf($device, $roll);
            $cabinets += $count;
        }

        return $width + max(0, $cabinets - 1) * $gapM;
    }

    /**
     * One tier's cabinets grouped into the placements they land as.
     *
     * Adjacent cabinets sharing a device **and** a support become one placement, so a tier standing on level
     * ground is still a single row and only a stepped one splits. `on:` then does the rest: it reads the
     * support's own top face, so every height still comes out of the specs and none is written down.
     *
     * @param list<array{DeviceSpec, int, float, float}> $seats
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return list<array{
     *     id: string, device: DeviceSpec, count: int, lo: float, hi: float,
     *     top: float, on: string|null, bearing: float, settle: float, roll: float
     * }>
     */
    private static function runs(array $seats, array $below, float $gapM): array
    {
        $runs = [];

        foreach ($seats as [$device, $count, $centreX, $roll]) {
            $width = RolledBox::widthOf($device, $roll);
            $span = $count * $width + ($count - 1) * $gapM;
            $x = $centreX - $span / 2;

            for ($seat = 0; $seat < $count; ++$seat) {
                ['on' => $on, 'top' => $top, 'bearing' => $bearing, 'settle' => $settle]
                    = self::landsOn($below, $x, $x + $width);

                $last = $runs === [] ? null : $runs[count($runs) - 1];
                // Device, support **and roll**: the two halves of a mirrored tier are turned opposite ways, so
                // they are two placements however level the ground under them is.
                if ($last !== null && $last['device'] === $device && $last['on'] === $on && $last['roll'] === $roll) {
                    $runs[count($runs) - 1]['count'] = $last['count'] + 1;
                    $runs[count($runs) - 1]['hi'] = $x + $width;
                    // The worst-carried cabinet speaks for the run: they share a support, so the ones at its
                    // ends are the only ones that can be hanging off it.
                    $runs[count($runs) - 1]['bearing'] = min($last['bearing'], $bearing);
                    $runs[count($runs) - 1]['settle'] = max($last['settle'], $settle);
                } else {
                    $runs[] = [
                        'id' => '',
                        'device' => $device,
                        'count' => 1,
                        'lo' => $x,
                        'hi' => $x + $width,
                        'top' => $top,
                        'on' => $on,
                        'bearing' => $bearing,
                        'settle' => $settle,
                        'roll' => $roll,
                    ];
                }

                $x += $width + $gapM;
            }
        }

        return $runs;
    }

    /**
     * How far out of level a cabinet ends up, in degrees — and **zero unless it would actually tilt**.
     *
     * That gate is the whole of it, and getting it wrong once produced nonsense: a cabinet with a 20 mm sliver
     * hanging over a 19 mm step reads 43.5° by `atan(drop / overhang)` and does not move at all in reality,
     * because its weight is still over its support. A cabinet only rotates if its **own centre** is off what
     * holds it up. So:
     *
     * * centre over the support → sits flat, whatever is beside it. Zero.
     * * centre off it, and the overhang is over **air** → not a tilt but a cantilever, held by the neighbours it
     *   is strapped to. Zero here too, and {@see Stability::tips} decides it by weighing the row.
     * * centre off it, and the overhang catches a **lower surface** → it tilts until it touches, and that angle
     *   is what tells a shim from a cantilever: a 19 mm step over a 630 mm overhang is 1.7°, a 163 mm shoulder
     *   over 460 mm is 19.5°.
     *
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @param list<array{float, float}> $held the spans covered by supports level with the landing
     */
    private static function settleOf(array $below, float $lo, float $hi, float $top, array $held): float
    {
        $centre = ($lo + $hi) / 2;
        foreach ($held as [$from, $to]) {
            if ($centre >= $from - self::CONTACT_EPSILON_M && $centre <= $to + self::CONTACT_EPSILON_M) {
                return 0.0;
            }
        }

        $worst = 0.0;
        foreach (self::gapsIn($held, $lo, $hi) as [$from, $to]) {
            $overhang = $to - $from;
            if ($overhang <= self::CONTACT_EPSILON_M) {
                continue;
            }

            $under = -INF;
            foreach ($below as $candidate) {
                if (min($to, $candidate['hi']) - max($from, $candidate['lo']) > self::CONTACT_EPSILON_M) {
                    $under = max($under, $candidate['top']);
                }
            }
            if ($under > -INF) {
                $worst = max($worst, rad2deg(atan2($top - $under, $overhang)));
            }
        }

        return $worst;
    }

    /**
     * The stretches of `$lo`..`$hi` no support covers, left to right.
     *
     * @param list<array{float, float}> $taken
     * @return list<array{float, float}>
     */
    private static function gapsIn(array $taken, float $lo, float $hi): array
    {
        usort($taken, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $gaps = [];
        $reach = $lo;
        foreach ($taken as [$from, $to]) {
            if ($from > $reach) {
                $gaps[] = [$reach, $from];
            }
            $reach = max($reach, $to);
        }
        if ($reach < $hi) {
            $gaps[] = [$reach, $hi];
        }

        return $gaps;
    }

    /**
     * What a cabinet spanning `$lo`..`$hi` comes to rest on: the **highest** thing under it, or the floor.
     *
     * Highest rather than first, because that is what falling does — a cabinet bridging a Flexy and a SKRAM
     * settles on the SKRAM and leaves a gap over the Flexy, which is exactly the shim a crew would put in.
     *
     * `bearing` is how much of the cabinet is carried at that height, summed across every support level with
     * it, as a fraction of its own width. It is
     * it is reported rather than acted on here: falling is not the place to decide whether a landing is
     * acceptable. The floor carries everything, so a cabinet on the ground bears 1.
     *
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return array{on: string|null, top: float, bearing: float, settle: float}
     */
    private static function landsOn(array $below, float $lo, float $hi): array
    {
        $on = null;
        $top = 0.0;

        foreach ($below as $candidate) {
            $overlap = min($hi, $candidate['hi']) - max($lo, $candidate['lo']);
            if ($overlap <= self::CONTACT_EPSILON_M) {
                continue;
            }

            if ($on === null || $candidate['top'] > $top) {
                $on = $candidate['id'];
                $top = $candidate['top'];
            }
        }
        if ($on === null) {
            return ['on' => null, 'top' => 0.0, 'bearing' => 1.0, 'settle' => 0.0];
        }

        // **Every** support at that height carries it, not just the one it is named after. A cabinet spanning
        // two neighbours of equal height rests on both, and crediting it with only the larger overlap read as
        // 35 % where it was really 93 % — which then refused arrangements that were perfectly well carried.
        // Only supports level with the landing count, within a shim's worth: a lower one is not touching it.
        $bearing = 0.0;
        $held = [];
        foreach ($below as $candidate) {
            if (abs($candidate['top'] - $top) > self::LEVEL_TOLERANCE_M) {
                continue;
            }
            $overlap = min($hi, $candidate['hi']) - max($lo, $candidate['lo']);
            if ($overlap > self::CONTACT_EPSILON_M) {
                $bearing += $overlap;
                $held[] = [max($lo, $candidate['lo']), min($hi, $candidate['hi'])];
            }
        }

        return [
            'on' => $on,
            'top' => $top,
            'bearing' => $bearing / ($hi - $lo),
            'settle' => self::settleOf($below, $lo, $hi, $top, $held),
        ];
    }
}
