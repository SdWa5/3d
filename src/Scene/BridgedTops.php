<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One tops row standing on both walls of a pair, at an equal pitch. SYM-3.
 *
 * {@see StackTops::topRow} builds each stack's tops from that stack, so the tops of two walls move with their subs and
 * the stereo image is as wide as one wall. A row that bridges both walls is as wide as the pair. It needs level walls,
 * and only a mirrored pair out of one pool has them, so {@see \App\Command\SceneStackCommand} asks for it on such a
 * pair alone. The walls themselves are not checked for being mirrored here. What is checked is the physical claim
 * the mirroring stands for, which is that every top lands level and is carried.
 *
 * **Equal pitch was settled by the owner, against a recommendation of equal air.** Our tops are 0.450, 0.4656 and
 * 0.500 m wide, so the two disagree. A uniform pitch has to clear the widest adjacent pair, which makes the tightest
 * eight-top row 169 mm wider than the tightest equal-air one.
 *
 * The layouts are tried in this order, and the first one whose every top is carried wins:
 *
 * 1. **Equal pitch, spread to the outer edges** of the walls' top faces.
 * 2. **Equal air, spread to the same edges**, for a row too long for the pitch but short enough to pack.
 * 3. Both again with the walls **5 mm closer**, down to the working gap. The stated clearance is the most air the
 *    walls get, and they close in only as far as the tops need.
 * 4. **The walls moved apart** until the row fits at its tightest pitch, for a row too long for the pair.
 *
 * Carried means what it means everywhere else here. Each top lands on the highest face under it, at the walls' top
 * height, on at least {@see Gravity::MIN_BEARING} of its width, without settling past
 * {@see Stability::MAX_SETTLE_DEG} and with its centre over what it touches. That rule is also what bounds the gap
 * between the walls, since a top over the gap hangs off both inner edges. Two meeting 0.450 m tops cap it near
 * 0.600 m. A row that no layout carries is null, and the pair keeps a tops row per wall.
 */
final readonly class BridgedTops
{
    /** How far a landing may sit off the walls' top height and still be at it. A shim, not a step. */
    private const LEVEL_M = 0.001;

    /** How far the walls close in per try, the same step {@see Gravity} slides a row by. */
    private const STEP_M = 0.005;

    /**
     * @param list<array{device: DeviceSpec, x: float, y: float, wall: int, aim: string}> $seats left to right. `x` is
     *                                                                                           measured from the pair's centre and `y` from its `at`. `wall` is the index of the wall the top lands on
     * @param float $clearanceM the air between the walls, the stated clearance or more
     * @param float|null $pitchM the equal pitch, or null when the row is spaced by equal air
     */
    private function __construct(
        public array $seats,
        public float $clearanceM,
        public ?float $pitchM,
    ) {
    }

    /**
     * The shared row on the pair, or null when no layout carries every top.
     *
     * @param Tier $row the tops in the order the alignment wants, from {@see StackTops::topRow}
     * @param list<string> $nearFills device ids aimed at the near focus rather than the far one
     */
    public static function solve(
        StackBlock $left,
        StackBlock $right,
        Tier $row,
        float $clearanceM,
        array $nearFills = [],
    ): ?self {
        /** @var list<array{DeviceSpec, float, float}> $cabinets device, width, roll */
        $cabinets = [];
        foreach ($row->segments as $segment) {
            $roll = Tier::rollOf($segment);
            for ($i = 0; $i < $segment[1]; ++$i) {
                $cabinets[] = [$segment[0], RolledBox::widthOf($segment[0], $roll), $roll];
            }
        }
        if ([] === $cabinets) {
            return null;
        }

        $gapM = $left->stack->gapM;
        $widths = array_column($cabinets, 1);
        $count = count($widths);
        $floor = 0.0;
        for ($i = 0; $i + 1 < $count; ++$i) {
            $floor = max($floor, ($widths[$i] + $widths[$i + 1]) / 2 + $gapM);
        }
        $ends = ($widths[0] + $widths[$count - 1]) / 2;

        $local = [self::localFaces($left), self::localFaces($right)];

        // **CLOSER FIRST, AS FAR AS THE TOPS NEED AND NO FURTHER.** The stated clearance is where the walls start and
        // the most air they get. A top over the gap needs a third of its width carried, so an odd row's middle top
        // caps the gap at two thirds of its width, 0.333 m for a Tecnare, and the walls close in by 5 mm steps until
        // the row is carried. Only a row too long for the pair at the stated clearance moves them further apart.
        $layouts = [];
        for ($step = 0;; ++$step) {
            $clearance = max($gapM, $clearanceM - $step * self::STEP_M);
            $faces = self::faces($left, $right, $local, $clearance);
            [$lo, $hi] = self::envelope($faces);
            $layouts[] = [$clearance, $faces, self::pitched($lo, $hi, $widths, $floor), true];
            $layouts[] = [$clearance, $faces, self::aired($lo, $hi, $widths, $gapM), false];
            if ($clearance <= $gapM) {
                break;
            }
        }
        $faces = self::faces($left, $right, $local, $clearanceM);
        [$lo, $hi] = self::envelope($faces);
        $short = ($count - 1) * $floor + $ends - ($hi - $lo);
        if ($count > 1 && $short > 1e-9) {
            $wider = self::faces($left, $right, $local, $clearanceM + $short);
            [$wideLo, $wideHi] = self::envelope($wider);
            $layouts[] = [$clearanceM + $short, $wider, self::pitched($wideLo, $wideHi, $widths, $floor), true];
        }

        foreach ($layouts as [$clearance, $under, $centres, $pitched]) {
            if (null === $centres) {
                continue;
            }
            $walls = self::carried($under, $cabinets, $centres);
            if (null === $walls) {
                continue;
            }

            $deepest = max(self::deepest($left), self::deepest($right));
            $seats = [];
            foreach ($cabinets as $i => [$device]) {
                $seats[] = [
                    'device' => $device,
                    'x' => $centres[$i],
                    // Flush with the front of the deepest wall cabinet, which is where a stack stands its tops.
                    'y' => ($device->dimensions->depth - $deepest) / 2,
                    'wall' => $walls[$i],
                    'aim' => in_array($device->id, $nearFills, true) ? 'near' : StackSceneWriter::AIM,
                ];
            }

            return new self($seats, $clearance, $pitched && $count > 1 ? $centres[1] - $centres[0] : null);
        }

        return null;
    }

    /** How the row reads in a header: its cabinets left to right, and how they are spaced. */
    public function describe(): string
    {
        return implode(' + ', array_map(static fn (array $seat): string => $seat['device']->id, $this->seats))
            .', '.$this->spacing();
    }

    /** How the row is spaced, as the end of a sentence. */
    public function spacing(): string
    {
        return match (true) {
            null !== $this->pitchM => sprintf('at an equal pitch of %s m', rtrim(rtrim(sprintf('%.4f', $this->pitchM), '0'), '.')),
            1 === count($this->seats) => 'centred over the gap',
            default => 'with equal air between them',
        };
    }

    /**
     * Centres at an equal pitch with the end cabinets flush with the envelope, or null when that pitch would put two
     * neighbours closer than the floor allows. One cabinet stands in the middle.
     *
     * @param list<float> $widths
     *
     * @return list<float>|null
     */
    private static function pitched(float $lo, float $hi, array $widths, float $floor): ?array
    {
        $count = count($widths);
        if (1 === $count) {
            return [($lo + $hi) / 2];
        }
        $pitch = ($hi - $lo - ($widths[0] + $widths[$count - 1]) / 2) / ($count - 1);
        if ($pitch < $floor - 1e-9) {
            return null;
        }

        $centres = [];
        for ($i = 0; $i < $count; ++$i) {
            $centres[] = $lo + $widths[0] / 2 + $i * $pitch;
        }

        return $centres;
    }

    /**
     * Centres with the same air between every pair of neighbours and the end cabinets flush with the envelope, or
     * null when that air would be less than the working gap.
     *
     * @param list<float> $widths
     *
     * @return list<float>|null
     */
    private static function aired(float $lo, float $hi, array $widths, float $gapM): ?array
    {
        $count = count($widths);
        if ($count < 2) {
            return null;
        }
        $air = ($hi - $lo - array_sum($widths)) / ($count - 1);
        if ($air < $gapM - 1e-9) {
            return null;
        }

        $centres = [];
        $x = $lo;
        foreach ($widths as $width) {
            $centres[] = $x + $width / 2;
            $x += $width + $air;
        }

        return $centres;
    }

    /**
     * The wall each top lands on, or null when any of them is not carried.
     *
     * @param list<array{id: string, lo: float, hi: float, top: float, wall: int}> $faces
     * @param list<array{DeviceSpec, float, float}> $cabinets
     * @param list<float> $centres
     *
     * @return list<int>|null
     */
    private static function carried(array $faces, array $cabinets, array $centres): ?array
    {
        $height = max(array_column($faces, 'top'));
        $runs = [];
        foreach ($cabinets as $i => [$device, $width, $roll]) {
            $runs[] = [
                'id' => '',
                'device' => $device,
                'count' => 1,
                'lo' => $centres[$i] - $width / 2,
                'hi' => $centres[$i] + $width / 2,
                'top' => 0.0,
                'on' => null,
                'bearing' => 0.0,
                'settle' => 0.0,
                'roll' => $roll,
            ];
        }

        $wallOf = array_column($faces, 'wall', 'id');
        $level = array_values(array_filter(
            $faces,
            static fn (array $face): bool => $face['top'] >= $height - self::LEVEL_M,
        ));

        $walls = [];
        foreach (Gravity::reseat($runs, $faces) as $run) {
            if (null === $run['on']
                || abs($run['top'] - $height) > self::LEVEL_M
                || $run['bearing'] < Gravity::MIN_BEARING - 1e-9
                || $run['settle'] > Stability::MAX_SETTLE_DEG
            ) {
                return null;
            }
            $walls[] = $wallOf[$run['on']];
        }

        return Stability::tips($runs, $level, true) ? null : $walls;
    }

    /**
     * Every top face of one wall, measured from the wall's own `at`.
     *
     * @return list<array{id: string, lo: float, hi: float, top: float}>
     */
    private static function localFaces(StackBlock $wall): array
    {
        $faces = [];
        $resolved = Gravity::resolve(
            $wall->tiers,
            $wall->stack->gapM,
            $wall->placementId,
            $wall->stack->slideSlackM,
            $wall->stack->maxWidthM,
        );
        foreach ($resolved as $runs) {
            array_push($faces, ...Gravity::topFacesOf($runs));
        }

        return $faces;
    }

    /**
     * Both walls' top faces, standing where the writer puts them with this much air between them.
     *
     * @param array{list<array{id: string, lo: float, hi: float, top: float}>, list<array{id: string, lo: float, hi: float, top: float}>} $local
     *
     * @return list<array{id: string, lo: float, hi: float, top: float, wall: int}>
     */
    private static function faces(StackBlock $left, StackBlock $right, array $local, float $clearanceM): array
    {
        $centres = StackSceneWriter::centres([$left, $right], 0.0, $clearanceM);

        $faces = [];
        foreach ($local as $wall => $own) {
            foreach ($own as $face) {
                $face['lo'] += $centres[$wall];
                $face['hi'] += $centres[$wall];
                $faces[] = $face + ['wall' => $wall];
            }
        }

        return $faces;
    }

    /**
     * The outer edges of the highest faces, which is what a row spreads to.
     *
     * @param list<array{lo: float, hi: float, top: float, ...}> $faces
     *
     * @return array{float, float}
     */
    private static function envelope(array $faces): array
    {
        $height = max(array_column($faces, 'top'));
        $lo = INF;
        $hi = -INF;
        foreach ($faces as $face) {
            if ($face['top'] >= $height - self::LEVEL_M) {
                $lo = min($lo, $face['lo']);
                $hi = max($hi, $face['hi']);
            }
        }

        return [$lo, $hi];
    }

    /** The deepest cabinet in a wall, whose front the stack's other cabinets stand flush with. */
    private static function deepest(StackBlock $wall): float
    {
        $deepest = 0.0;
        foreach ($wall->tiers as $tier) {
            foreach ($tier->segments as $segment) {
                $deepest = max($deepest, $segment[0]->dimensions->depth);
            }
        }

        return $deepest;
    }
}
