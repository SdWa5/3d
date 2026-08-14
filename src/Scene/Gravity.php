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
     * **A third**, and where it sits is the whole point. It used to be a half, chosen as the companion of the
     * half-a-cabinet overhang rule — and a half falls exactly between the two arrangements it has to separate,
     * which is the worst possible place for a boundary:
     *
     * * a Flexy resting on a SKRAM with the rest of it cantilevered outward bears **49.9 %** — marginal, and
     *   buildable: crews stack and strap exactly this, and the row's own mass is over its support
     * * a 2-way perched on a 163 mm shoulder, touching by one corner, bears **1.2 %**
     *
     * Forty times apart, and a half refused both. A third refuses the perch and passes the cantilever, and
     * anywhere from 5 % to 45 % gives the same answers on every arrangement this inventory can build. Whether
     * the *row* then stands is a different question, asked by {@see Stability::tips}.
     */
    public const MIN_BEARING = 1 / 3;

    /**
     * How close to the landing height a second support has to be before it carries the cabinet too.
     *
     * A centimetre, the same figure {@see StackSolver::OVERHANG_TOLERANCE_M} calls "what the rubber feet and the
     * working gaps absorb". Requiring supports to be *exactly* level was too strict to be physical: a cabinet
     * bridging two neighbours a millimetre apart in height rests on both of them, and crediting it with only the
     * taller one understates what is holding it up.
     */
    private const LEVEL_TOLERANCE_M = 0.01;

    /** How finely {@see slidSeats} scans for the offset that carries the worst-carried cabinet best. */
    private const SLIDE_STEP_M = 0.005;

    /**
     * The runs of every tier, bottom up, each one already knowing what it stands on and how well.
     *
     * @param list<Tier> $tiers
     * @param string $prefix the placement id the run ids hang off
     * @param float|null $slideSlackM how far sideways a badly-carried row may be moved, or null for "it may not
     *     move". See {@see Stack::$slideSlackM} — it is a statement about what else is in the scene, not about gravity.
     * @param float|null $stageM the width the stack itself may occupy, which a slid row also stays inside
     * @return list<list<array{
     *     id: string, device: DeviceSpec, count: int, lo: float, hi: float,
     *     top: float, on: string|null, bearing: float, settle: float, roll: float
     * }>> one entry per tier, in the same order
     */
    public static function resolve(
        array $tiers,
        float $gapM,
        string $prefix,
        ?float $slideSlackM = null,
        ?float $stageM = null,
    ): array {
        /** @var list<array{id: string, lo: float, hi: float, top: float}> $below what the next tier lands on */
        $below = [];
        $resolved = [];

        foreach ($tiers as $index => $tier) {
            $runs = self::runs($tier->seats($gapM), $below, $gapM);

            // Only when the ordinary row would leave a cabinet hanging. Rearranging a tier that is already
            // carried would be a change for its own sake, and every rig that stands up today keeps its layout.
            //
            // **ANY TIER, NOT ONLY THE TOP ONE.** The rescue was written for outboard fills and gated to the last tier,
            // which read as though it were a property of tops rows. It is not: a *packed sub* row lands the same way and
            // had no repair at all, which is what refused GMSS's one arrangement inside the sub height band.
            //
            // **Two repairs, best of**, because they answer different shapes and neither subsumes the other: seating the
            // ends outboard needs a stepped support with at least two runs to seat onto, and sliding needs nothing but
            // room beside the row — so the packed row `outboardSeats` returns null for is exactly the one the slide
            // carries. Both are discarded unless they improve the worst bearing, which is what keeps this safe rather
            // than the tier index.
            if (self::worstBearing($runs) < self::MIN_BEARING) {
                foreach ([
                    self::outboardSeats($tier->seats($gapM), $below, $gapM),
                    self::slidSeats($tier->seats($gapM), $below, $gapM, $slideSlackM, $stageM),
                ] as $repair) {
                    $rescued = $repair === null ? null : self::runs($repair, $below, $gapM);
                    if ($rescued !== null && self::worstBearing($rescued) > self::worstBearing($runs)) {
                        $runs = $rescued;
                    }
                }
            }

            foreach ($runs as $slot => $run) {
                // Letters when a tier lands in more than one place, so they cannot be confused with the
                // numeric `-1`, `-2` suffixes a group appends to every copy it makes.
                $runs[$slot]['id'] = sprintf(
                    '%s/%d%s',
                    $prefix,
                    $index + 1,
                    count($runs) > 1 ? chr(ord('a') + $slot) : '',
                );
            }

            $resolved[] = $runs;
            $below = self::topFacesOf($runs);
        }

        return $resolved;
    }

    /**
     * The top faces a tier's runs offer to whatever stands on them.
     *
     * Public because {@see Stack::expand} needs the same answer when it moves a row after the fact and has to ask
     * {@see reseat} what the row now stands on. One construction in one place, so the caller cannot get the rolled
     * height wrong.
     *
     * @param list<array{id: string, device: DeviceSpec, lo: float, hi: float, top: float, roll: float, ...}> $runs
     * @return list<array{id: string, lo: float, hi: float, top: float}>
     */
    public static function topFacesOf(array $runs): array
    {
        $faces = [];
        foreach ($runs as $run) {
            $faces[] = [
                'id' => $run['id'],
                'lo' => $run['lo'],
                'hi' => $run['hi'],
                // The **rolled** height: a Flexy on its side raises what stands on it by 591 mm, not 763.
                'top' => $run['top'] + RolledBox::heightOf($run['device'], $run['roll']),
            ];
        }

        return $faces;
    }

    /**
     * The same runs, asked again what they stand on — for a caller that has moved them sideways.
     *
     * **A row that is moved after it has been seated keeps a height it is no longer entitled to**, and that is a
     * whole family of bugs rather than one. `landsOn` picks the *highest* support a run overlaps, so a run shifted
     * off that support and over a lower one still carries the taller one's height and hangs in the air. Nothing
     * downstream notices, because the compiler reads the run's stated height and the tier checks read bearings that
     * were computed before the move. A GMSS turbo top ended up **228 mm** over the achenbach beneath it that way.
     *
     * {@see resolve} never has this problem, because its own two repairs — {@see outboardSeats} and
     * {@see slidSeats} — hand back *seats* and are re-run through {@see runs}, which re-asks the question. This is
     * that same discipline for a caller holding finished runs: move them, then reseat them.
     *
     * `lo` and `hi` are taken as given and everything derived from them is recomputed, so it is idempotent and safe
     * to call on runs that did not move.
     *
     * @param list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}> $runs
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below from {@see topFacesOf}
     * @return list<array{id: string, device: DeviceSpec, count: int, lo: float, hi: float, top: float, on: string|null, bearing: float, settle: float, roll: float}>
     */
    public static function reseat(array $runs, array $below): array
    {
        foreach ($runs as $slot => $run) {
            ['on' => $on, 'top' => $top, 'bearing' => $bearing, 'settle' => $settle]
                = self::landsOn($below, $run['lo'], $run['hi']);

            $runs[$slot]['on'] = $on;
            $runs[$slot]['top'] = $top;
            $runs[$slot]['bearing'] = $bearing;
            $runs[$slot]['settle'] = $settle;
        }

        return $runs;
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
     * The same row, slid along its support to wherever the worst-carried cabinet is carried best.
     *
     * **A ROW DOES NOT HAVE TO BE CENTRED ON WHAT CARRIES IT**, and assuming it did was refusing rigs that stand up.
     * Centring is only optimal when the row overhangs a *symmetric* amount of cabinet at each end; a mixed row is
     * asymmetric by construction, so its two ends need different amounts of support and the middle is the wrong place
     * for it. GMSS's packed `2× nuke + 1× mid-bass` row is 2.400 m on a 1.310 m support: centred, the outboard nuke
     * lands on 45 mm of its 590 mm — 8 %, refused. Slid 150 mm towards the mid-bass end, the nuke has a third of itself
     * over the support and the mid-bass, being 1.200 m wide, still has 42 % of its own. Nothing about the rig changed
     * but where the row sits, which is what a crew would do without discussing it.
     *
     * Bounded twice, and **null slack means it may not move at all**: by `$slackM`, how far the neighbouring stacks
     * leave it room to move, and by `$stageM`, the width the stack itself may occupy. See {@see Stack::$slideSlackM}
     * for why gravity is not what makes this unsafe. Within both bounds the row also stays over its support, since the
     * scan runs between "left edges flush" and "right edges flush" and no further.
     *
     * Scanned in 5 mm steps rather than solved, because the objective is a min over segments of a piecewise-linear
     * function — the closed form is a case analysis per segment pair, and the scan is a few hundred evaluations on a row
     * that is otherwise refused outright.
     *
     * @param list<array{DeviceSpec, int, float, float}> $seats
     * @param list<array{id: string, lo: float, hi: float, top: float}> $below
     * @param float|null $slackM how far the row may move sideways, or null for not at all
     * @param float|null $stageM the width the stack may occupy, or null for unbounded
     * @return list<array{DeviceSpec, int, float, float}>|null
     */
    private static function slidSeats(
        array $seats,
        array $below,
        float $gapM,
        ?float $slackM,
        ?float $stageM,
    ): ?array {
        if ($slackM === null || $slackM <= 0.0 || $below === [] || $seats === []) {
            return null;
        }

        $rowLo = INF;
        $rowHi = -INF;
        foreach ($seats as [$device, $count, $centre, $roll]) {
            $own = $count * RolledBox::widthOf($device, $roll) + ($count - 1) * $gapM;
            $rowLo = min($rowLo, $centre - $own / 2);
            $rowHi = max($rowHi, $centre + $own / 2);
        }

        // The row arrives centred on the stack's own origin, so the room the stage leaves it each way is what is left
        // once the row itself is taken out of the stage width. The neighbours' allowance bounds it as well, and the
        // tighter of the two wins.
        $slack = $stageM === null ? $slackM : min($slackM, max(0.0, ($stageM - ($rowHi - $rowLo)) / 2));
        $ends = [$below[0]['lo'] - $rowLo, $below[count($below) - 1]['hi'] - $rowHi];
        $from = max(min($ends), -$slack);
        $to = min(max($ends), $slack);

        if ($to - $from < self::CONTACT_EPSILON_M) {
            return null;
        }

        $best = null;
        $bestBearing = -INF;
        for ($offset = $from; $offset <= $to + self::SLIDE_STEP_M / 2; $offset += self::SLIDE_STEP_M) {
            $slid = array_map(
                static fn (array $seat): array => [$seat[0], $seat[1], $seat[2] + $offset, $seat[3]],
                $seats,
            );
            $bearing = self::worstBearing(self::runs($slid, $below, $gapM));
            if ($bearing > $bestBearing) {
                $bestBearing = $bearing;
                $best = $slid;
            }
        }

        return $best;
    }

    /**
     * A tier laid out **fills outboard**: the end segments over the end supports, the rest centred on
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
