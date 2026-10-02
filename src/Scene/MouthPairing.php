<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;
use App\Spec\MouthSide;

/**
 * Turns horn subs so their mouths meet, without moving any of them.
 *
 * **GEO-16, as the owner narrowed it on 2026-10-02.** Two horns mouth to mouth act as one larger mouth, and a solved
 * rig is full of pairs that could be turned that way. This pass finds them and changes rolls only. A half turn and a
 * swap of the two quarter turns leave every box exactly where it was, so no width, no height, no bearing and no
 * seating check can come out differently, and it runs after the solve rather than inside its search.
 *
 * Two kinds of pair, both read off {@see MouthSide} and nothing else. A spec that states no mouth side is never turned.
 *
 * * **Side by side, in a turned row.** A run of neighbouring cabinets of one device, all on their sides, is paired
 *   from its outer end inwards, so the leftover of an odd run is the one nearest the centre line. A run centred on
 *   the row pairs from both ends, which keeps a symmetric row symmetric. Cabinets of different devices never pair.
 * * **One above the other, in upright rows.** Two stacked rows of the same cabinets in the same order with the same
 *   gap pair bottom-up. The lower row's mouths turn up and the upper row's down. An odd row at the top stays as it is.
 *
 * Mirror images stay mirror images. Every rule here reads the row from its own ends and its own centre, so the
 * {@see Tier::flipped} copy of a row pairs into the flipped copy of its pairing.
 *
 * **The rolls go into {@see Tier::$mouthRolls}, not into the segments**, and that is what keeps the promise. The
 * segments are what gravity solves on, and a run there is cabinets of one device, one support and one roll. Written
 * into the segments, pairing cut every run into single cabinets that settled one by one, so twelve `v` rigs changed
 * their verdict on the first regeneration. {@see Stack::expand} applies the rolls to the finished runs instead.
 */
final class MouthPairing
{
    /**
     * @param list<Tier> $tiers bottom row first, as the solver deals them
     *
     * @return list<Tier>
     */
    public static function pair(array $tiers): array
    {
        return array_map(self::sideBySide(...), self::stacked($tiers));
    }

    /**
     * Upright rows paired bottom-up with the row above them.
     *
     * @param list<Tier> $tiers
     *
     * @return list<Tier>
     */
    private static function stacked(array $tiers): array
    {
        $row = 0;
        while ($row + 1 < count($tiers)) {
            if (!self::stackable($tiers[$row], $tiers[$row + 1])) {
                ++$row;
                continue;
            }

            $tiers[$row] = self::facingAll($tiers[$row], [0, 1]);
            $tiers[$row + 1] = self::facingAll($tiers[$row + 1], [0, -1]);
            $row += 2;
        }

        return $tiers;
    }

    /**
     * Whether `$upper` stands column for column on `$lower` with nothing on its side and at least one mouth to pair.
     */
    private static function stackable(Tier $lower, Tier $upper): bool
    {
        if ($lower->gapM !== $upper->gapM || count($lower->segments) !== count($upper->segments)) {
            return false;
        }

        $mouths = false;
        foreach ($lower->segments as $index => $segment) {
            $above = $upper->segments[$index];
            if ($segment[0]->id !== $above[0]->id || $segment[1] !== $above[1]
                || 0.0 !== Tier::rollOf($segment) || 0.0 !== Tier::rollOf($above)) {
                return false;
            }
            $mouths = $mouths || null !== $segment[0]->mouthSide;
        }

        return $mouths;
    }

    /**
     * Every cabinet with a mouth side turned so its mouth faces `$facing`.
     *
     * @param array{int, int} $facing
     */
    private static function facingAll(Tier $tier, array $facing): Tier
    {
        $rolls = [];
        foreach ($tier->segments as $segment) {
            $roll = null === $segment[0]->mouthSide
                ? Tier::rollOf($segment)
                : self::rollFacing($segment[0]->mouthSide, $facing);
            for ($i = 0; $i < $segment[1]; ++$i) {
                $rolls[] = $roll;
            }
        }

        return $rolls === $tier->cabinetRolls() ? $tier : $tier->withMouthRolls($rolls);
    }

    /**
     * Neighbouring turned cabinets of one device paired mouth to mouth.
     */
    private static function sideBySide(Tier $tier): Tier
    {
        /** @var list<array{DeviceSpec, float}> $cabinets */
        $cabinets = [];
        foreach ($tier->segments as $segment) {
            for ($i = 0; $i < $segment[1]; ++$i) {
                $cabinets[] = [$segment[0], Tier::rollOf($segment)];
            }
        }

        $rolls = $tier->cabinetRolls();
        foreach (self::runs($cabinets) as [$first, $last]) {
            foreach (self::pairsOf($first, $last, count($cabinets)) as $left) {
                $side = $cabinets[$left][0]->mouthSide;
                \assert(null !== $side);
                $rolls[$left] = self::rollFacing($side, [1, 0]);
                $rolls[$left + 1] = self::rollFacing($side, [-1, 0]);
            }
        }

        return $rolls === $tier->cabinetRolls() ? $tier : $tier->withMouthRolls($rolls);
    }

    /**
     * The first and last index of every run of two or more neighbouring cabinets that can pair side by side.
     *
     * @param list<array{DeviceSpec, float}> $cabinets
     *
     * @return list<array{int, int}>
     */
    private static function runs(array $cabinets): array
    {
        $runs = [];
        $start = null;
        foreach ($cabinets as $index => [$device, $roll]) {
            $turned = null !== $device->mouthSide && 90.0 === fmod(abs($roll), 180.0);
            $continues = $turned && null !== $start && $cabinets[$start][0]->id === $device->id;
            if ($continues) {
                continue;
            }
            if (null !== $start && $index - 1 > $start) {
                $runs[] = [$start, $index - 1];
            }
            $start = $turned ? $index : null;
        }
        if (null !== $start && count($cabinets) - 1 > $start) {
            $runs[] = [$start, count($cabinets) - 1];
        }

        return $runs;
    }

    /**
     * The left index of every pair a run makes, paired from its outer end, or from both ends when it is centred.
     *
     * @return list<int>
     */
    private static function pairsOf(int $first, int $last, int $rowCount): array
    {
        $offset = ($first + $last) - ($rowCount - 1);
        $pairs = [];

        if (0 === $offset) {
            // Centred: from both ends at once, two pairs a step, so a leftover can only be in the middle.
            while ($last - $first >= 3) {
                $pairs[] = $first;
                $pairs[] = $last - 1;
                $first += 2;
                $last -= 2;
            }
            if (1 === $last - $first) {
                $pairs[] = $first;
            }
            sort($pairs);

            return $pairs;
        }

        // Left of centre the outer end is the first cabinet, right of it the last.
        $count = intdiv($last - $first + 1, 2);
        for ($i = 0; $i < $count; ++$i) {
            $pairs[] = $offset < 0 ? $first + 2 * $i : $last - 1 - 2 * $i;
        }
        sort($pairs);

        return $pairs;
    }

    /**
     * The roll that points a mouth on `$side` towards `$facing`.
     *
     * @param array{int, int} $facing
     */
    private static function rollFacing(MouthSide $side, array $facing): float
    {
        foreach ([0.0, 90.0, 180.0, 270.0] as $roll) {
            if ($side->facing($roll) === $facing) {
                return $roll;
            }
        }

        throw new \LogicException('every unit direction is reached by one of the four rolls');
    }
}
