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
     * The runs of every tier, bottom up, each one already knowing what it stands on and how well.
     *
     * @param list<Tier> $tiers
     * @param string $prefix the placement id the run ids hang off
     * @return list<list<array{
     *     id: string, device: DeviceSpec, count: int, lo: float, hi: float,
     *     top: float, on: string|null, bearing: float
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
                    'top' => $run['top'] + $run['device']->dimensions->height,
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
     * @param list<array{DeviceSpec, int, float}> $seats
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return list<array{DeviceSpec, int, float}>|null
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
        foreach ($groups as [$segments, $lo, $hi]) {
            $span = self::spanOf($segments, $gapM);
            if ($span > $hi - $lo + self::CONTACT_EPSILON_M) {
                return null;
            }

            $x = ($lo + $hi) / 2 - $span / 2;
            foreach ($segments as [$device, $count]) {
                $own = $count * $device->dimensions->width + ($count - 1) * $gapM;
                $placed[] = [$device, $count, $x + $own / 2];
                $x += $own + $gapM;
            }
        }

        return $placed;
    }

    /**
     * How wide a set of segments stands side by side — cabinets plus one gap between every neighbouring pair,
     * across a segment boundary as much as within one.
     *
     * @param list<array{DeviceSpec, int, float}> $segments
     */
    private static function spanOf(array $segments, float $gapM): float
    {
        $width = 0.0;
        $cabinets = 0;
        foreach ($segments as [$device, $count]) {
            $width += $count * $device->dimensions->width;
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
     * @param list<array{DeviceSpec, int, float}> $seats
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return list<array{
     *     id: string, device: DeviceSpec, count: int, lo: float, hi: float,
     *     top: float, on: string|null, bearing: float
     * }>
     */
    private static function runs(array $seats, array $below, float $gapM): array
    {
        $runs = [];

        foreach ($seats as [$device, $count, $centreX]) {
            $width = $device->dimensions->width;
            $span = $count * $width + ($count - 1) * $gapM;
            $x = $centreX - $span / 2;

            for ($seat = 0; $seat < $count; ++$seat) {
                ['on' => $on, 'top' => $top, 'bearing' => $bearing] = self::landsOn($below, $x, $x + $width);

                $last = $runs === [] ? null : $runs[count($runs) - 1];
                if ($last !== null && $last['device'] === $device && $last['on'] === $on) {
                    $runs[count($runs) - 1]['count'] = $last['count'] + 1;
                    $runs[count($runs) - 1]['hi'] = $x + $width;
                    // The worst-carried cabinet speaks for the run: they share a support, so the ones at its
                    // ends are the only ones that can be hanging off it.
                    $runs[count($runs) - 1]['bearing'] = min($last['bearing'], $bearing);
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
                    ];
                }

                $x += $width + $gapM;
            }
        }

        return $runs;
    }

    /**
     * What a cabinet spanning `$lo`..`$hi` comes to rest on: the **highest** thing under it, or the floor.
     *
     * Highest rather than first, because that is what falling does — a cabinet bridging a Flexy and a SKRAM
     * settles on the SKRAM and leaves a gap over the Flexy, which is exactly the shim a crew would put in.
     *
     * `bearing` is how much of the cabinet that support actually carries, as a fraction of its own width, and
     * it is reported rather than acted on here: falling is not the place to decide whether a landing is
     * acceptable. The floor carries everything, so a cabinet on the ground bears 1.
     *
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @return array{on: string|null, top: float, bearing: float}
     */
    private static function landsOn(array $below, float $lo, float $hi): array
    {
        $on = null;
        $top = 0.0;
        $bearing = 1.0;

        foreach ($below as $candidate) {
            $overlap = min($hi, $candidate['hi']) - max($lo, $candidate['lo']);
            if ($overlap <= self::CONTACT_EPSILON_M) {
                continue;
            }

            if ($on === null || $candidate['top'] > $top) {
                $on = $candidate['id'];
                $top = $candidate['top'];
                $bearing = $overlap / ($hi - $lo);
            }
        }

        return ['on' => $on, 'top' => $top, 'bearing' => $bearing];
    }
}
