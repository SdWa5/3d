<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One solved row of a {@see Stack} — a left-to-right run of cabinets, not necessarily all the same one.
 *
 * A tier started out as one device and a count, and that was wrong for the case the whole feature exists
 * to handle. Two SKRAMs are the widest and second-tallest cabinets we own and there are only two of them,
 * so a row of nothing but SKRAMs is 1.240 m — narrower than the Achenbach row that would stand on it, and
 * a stack whose tiers get *wider* as they go up is not a stack. Mixing them into the bottom row instead —
 * the two SKRAMs in the middle, Flexys either side — makes that row 3.684 m and the rig a pyramid again.
 *
 * So a tier is a list of segments. Where it sits is still not its business: the tiers come out in order and
 * each stands on the one below, so a height would only be a second copy of what `on:` already works out
 * from the specs.
 */
final class Tier
{
    /**
     * @param list<array{DeviceSpec, int}|array{DeviceSpec, int, float}> $segments left to right; more than one
     *                                                                             makes a mixed row. The third element is the segment's roll in degrees, absent meaning upright — and
     *                                                                             absent rather than required because PHP's list destructuring ignores what it is not given, so every
     *                                                                             `[$device, $count]` reader in the solver kept working when the roll arrived.
     * @param float|null $gapM the air between every neighbouring pair in this row, null meaning the stack's own
     *                         gap. Set only when {@see StackSolver} gaps a row out to reach a width its cabinets
     *                         cannot reach packed — one even pitch across the whole row, never a gap per pair
     */
    public function __construct(
        public readonly array $segments,
        public readonly ?float $gapM = null,
        /**
         * One roll per cabinet, left to right, where {@see MouthPairing} turned this row so horn mouths meet, or null.
         *
         * **Kept beside the segments rather than written into them**, because the segments are what gravity solves
         * on. Gravity merges neighbours into one run only when their rolls agree, and a run settles as one. Pairing
         * written into the segments split every run into single cabinets, each settling on its own, and that stepped
         * a Flexy row by 10 mm and put two aimed tops into each other in twelve `v` rigs. So the row is solved as dealt
         * and {@see Stack::expand} hands these rolls to the finished runs, which moves nothing.
         *
         * @var list<float>|null
         */
        public readonly ?array $mouthRolls = null,
    ) {
    }

    public static function of(DeviceSpec $device, int $count, float $rollDeg = 0.0): self
    {
        return new self([[$device, $count, $rollDeg]]);
    }

    /**
     * This segment's roll, 0 when it does not state one.
     *
     * @param array{DeviceSpec, int}|array{DeviceSpec, int, float} $segment
     */
    public static function rollOf(array $segment): float
    {
        return $segment[2] ?? 0.0;
    }

    /** The air this row leaves between neighbours: its own when it was gapped out, else the stack's `$stackGapM`. */
    public function gapFor(float $stackGapM): float
    {
        return $this->gapM ?? $stackGapM;
    }

    /** This row gapped out to `$gapM` between every neighbouring pair. */
    public function withGap(float $gapM): self
    {
        return new self($this->segments, $gapM, $this->mouthRolls);
    }

    /**
     * This row with its cabinets turned to `$rolls`, one per cabinet left to right, by {@see MouthPairing}.
     *
     * @param list<float> $rolls
     */
    public function withMouthRolls(array $rolls): self
    {
        \assert(count($rolls) === $this->count());

        return new self($this->segments, $this->gapM, $rolls);
    }

    /**
     * The roll every cabinet stands at, left to right, the paired one where the row was paired.
     *
     * @return list<float>
     */
    public function cabinetRolls(): array
    {
        if (null !== $this->mouthRolls) {
            return $this->mouthRolls;
        }

        $rolls = [];
        foreach ($this->segments as $segment) {
            for ($i = 0; $i < $segment[1]; ++$i) {
                $rolls[] = self::rollOf($segment);
            }
        }

        return $rolls;
    }

    /**
     * The segments as the row will stand, regrouped by the paired rolls. The segments themselves where unpaired.
     *
     * @return list<array{DeviceSpec, int}|array{DeviceSpec, int, float}>
     */
    public function standingSegments(): array
    {
        if (null === $this->mouthRolls) {
            return $this->segments;
        }

        $segments = [];
        $cabinet = 0;
        foreach ($this->segments as $segment) {
            for ($i = 0; $i < $segment[1]; ++$i) {
                $roll = $this->mouthRolls[$cabinet++];
                $last = array_key_last($segments);
                if (null !== $last && $segments[$last][0] === $segment[0] && $segments[$last][2] === $roll) {
                    ++$segments[$last][1];
                    continue;
                }
                $segments[] = [$segment[0], 1, $roll];
            }
        }

        return $segments;
    }

    /** How wide the cabinets alone are, without any air between them. */
    public function cabinetWidthM(): float
    {
        $width = 0.0;
        foreach ($this->segments as $segment) {
            [$device, $count] = $segment;
            $width += $count * RolledBox::widthOf($device, self::rollOf($segment));
        }

        return $width;
    }

    /** How many cabinets stand in this row, whatever they are. */
    public function count(): int
    {
        return array_sum(array_map(static fn (array $segment): int => $segment[1], $this->segments));
    }

    /**
     * How wide this row stands, cabinets plus one working gap between each neighbouring pair — including
     * across a segment boundary, because a SKRAM beside a Flexy needs the same air as two Flexys do.
     *
     * Nominal widths, deliberately: a tier is decided before anything is aimed, and an unaimed cabinet's
     * box is its box. Once a tier is spread by {@see Alignment} the real edges are solved against the
     * rotated boxes, which is a different question asked later.
     *
     * A gapped row uses its own gap and ignores `$gapM`, which is the stack's.
     */
    public function widthM(float $gapM): float
    {
        return $this->cabinetWidthM() + max(0, $this->count() - 1) * $this->gapFor($gapM);
    }

    /**
     * The height anything stacked on this row rests at — the **tallest** cabinet in it.
     *
     * For a mixed row of unequal cabinets that is a claim worth being uneasy about, and
     * {@see StackSolver} warns about it: the next tier really does sit on the tall ones and bridge over the
     * short ones. Taking the tallest is the only honest answer, since `on:` reads a top face and a stepped
     * row has two of them.
     */
    public function heightM(): float
    {
        $height = 0.0;
        foreach ($this->segments as $segment) {
            $height = max($height, RolledBox::heightOf($segment[0], self::rollOf($segment)));
        }

        return $height;
    }

    /** How far the tallest and shortest cabinet in this row differ — 0 for an even row. */
    public function heightStepM(): float
    {
        $tallest = 0.0;
        $shortest = INF;
        foreach ($this->segments as $segment) {
            $own = RolledBox::heightOf($segment[0], self::rollOf($segment));
            $tallest = max($tallest, $own);
            $shortest = min($shortest, $own);
        }

        return INF === $shortest ? 0.0 : $tallest - $shortest;
    }

    /** The width of the cabinet at the end of the row — what an overhang is measured against. */
    public function outerWidthM(): float
    {
        return RolledBox::widthOf($this->segments[0][0], self::rollOf($this->segments[0]));
    }

    public function isSub(): bool
    {
        foreach ($this->segments as [$device, $count]) {
            if ('sub' !== $device->subtype) {
                return false;
            }
        }

        return true;
    }

    public function isMixed(): bool
    {
        return count($this->segments) > 1;
    }

    /**
     * The device ids in this row, left to right, for a message or a scene comment. A gapped row names its gap,
     * which also keeps {@see StackSolver}'s fingerprint from sharing one verdict between two gaps of one row. The rolls
     * are the ones the row stands at, so a paired row reads as it is built.
     */
    public function label(): string
    {
        $label = implode(' + ', array_map(
            static fn (array $segment): string => sprintf(
                '%d× %s%s',
                $segment[1],
                $segment[0]->id,
                0.0 === self::rollOf($segment) ? '' : sprintf(' rolled %d°', (int) self::rollOf($segment)),
            ),
            $this->standingSegments(),
        ));

        return null === $this->gapM ? $label : sprintf('%s at %d mm gaps', $label, (int) round($this->gapM * 1000));
    }

    /**
     * This row with every rolled segment **split about the row's own centre** — the mirror.
     *
     * A segment's roll arrives as the quarter turn its right-hand half wants; this is where the left half gets
     * the mirror image of it. Split about the *row's* midpoint rather than each segment's, because the rig is
     * meant to be symmetric about its centre line and a mixed row would otherwise mirror three times: the two
     * SKRAMs in the middle of a Flexy row have to roll one way on the left of centre and the other way on the
     * right, not each pair about itself.
     *
     * Upright segments are left alone, so a row that mixes rolled and upright cabinets keeps the upright ones
     * where they were — the heights differ and gravity deals with that, exactly as it does for any stepped row.
     *
     * An **odd** cabinet count cannot be mirrored exactly, and `$style` decides what happens to the one that is left
     * over — see {@see MirrorStyle} for why none of the answers is free. `alternate` sends it to one side and expects
     * the row above to send it to the other, which `$row` selects; `centred` leaves it standing in the middle, which is
     * symmetric and 172 mm proud; `column` sends it to the same side every row and lets the stack be lopsided.
     *
     * @param int $row this tier's index in the stack, so `alternate` can flip sides as the wall rises
     */
    public function mirrored(MirrorStyle $style = MirrorStyle::Alternate, int $row = 0): self
    {
        $count = $this->count();
        $odd = 1 === $count % 2;

        // Which half the extra cabinet joins. Even counts split exactly, so the flip has nothing to act on; odd ones
        // alternate with the row index, which is what makes the *stack* balanced when no single row can be.
        //
        // **`column` is this same line with the row ignored**, and that is the whole of the third style: the spare goes
        // to the same side on every row, so the spares stand in one straight column and the seam between the two
        // mirrored halves runs straight up the wall instead of zig-zagging. The stack ends up lopsided by one cabinet,
        // which is precisely what `alternate` spends the zig-zag to avoid.
        $flips = MirrorStyle::Column !== $style && 1 === $row % 2;
        $midpoint = $odd && $flips ? intdiv($count, 2) + 1 : intdiv($count, 2);

        // `centred` keeps the middle cabinet unrolled, so both halves are the same size and the row is a palindrome.
        $centre = $odd && MirrorStyle::Centred === $style ? intdiv($count, 2) : null;
        if (null !== $centre) {
            $midpoint = $centre;
        }

        $segments = [];
        $index = 0;
        foreach ($this->segments as $segment) {
            [$device, $take] = $segment;
            $roll = self::rollOf($segment);

            if (90.0 !== fmod(abs($roll), 180.0)) {
                $segments[] = $segment;
                $index += $take;
                continue;
            }

            $left = max(0, min($take, $midpoint - $index));
            if ($left > 0) {
                $segments[] = [$device, $left, fmod(360.0 - $roll, 360.0)];
            }

            $rest = $take - $left;
            // The middle cabinet, when this style asks for one and it falls inside this segment: emitted upright
            // between the two mirrored halves rather than joining either.
            if (null !== $centre && $rest > 0 && $index + $left === $centre) {
                $segments[] = [$device, 1, 0.0];
                --$rest;
            }
            if ($rest > 0) {
                $segments[] = [$device, $rest, $roll];
            }
            $index += $take;
        }

        return new self($segments, $this->gapM);
    }

    /**
     * This row as its own **mirror image** — segment order reversed, every quarter turn handed the other way.
     *
     * The sibling of {@see mirrored}, and the difference is where the axis sits. `mirrored()` splits a row at its
     * *own* middle, so each row comes out symmetric about itself. This reflects the whole row end to end, which
     * is what one stack of a side-by-side pair needs: without it, `--stacks=2` builds the same rig twice and
     * calls it stereo, with both stacks' SKRAM ports facing the same way and both tops rows in the same
     * left-to-right order. Two duplicates measure identically to a mirrored pair and read wrong immediately.
     *
     * Upright segments pass through untouched — there is no handedness to reverse in a cabinet that is not on its
     * side, only a position, and the reversal takes care of that.
     */
    public function flipped(): self
    {
        return new self(array_values(array_map(
            static function (array $segment): array {
                $roll = self::rollOf($segment);

                return [
                    $segment[0],
                    $segment[1],
                    90.0 === fmod(abs($roll), 180.0) ? fmod(360.0 - $roll, 360.0) : $roll,
                ];
            },
            array_reverse($this->segments),
        )), $this->gapM, null === $this->mouthRolls ? null : array_map(
            static fn (float $roll): float => 90.0 === fmod(abs($roll), 180.0) ? fmod(360.0 - $roll, 360.0) : $roll,
            array_reverse($this->mouthRolls),
        ));
    }

    /**
     * Each segment with the x offset of its own centre, relative to the row's centre.
     *
     * This is what lets a mixed row expand into one placement per segment: each segment is a plain `row`
     * of identical cabinets, and the offsets put them side by side with the row centred on `at`.
     *
     * @return list<array{DeviceSpec, int, float, float}> device, count, centre x, roll
     */
    public function seats(float $gapM): array
    {
        $x = -$this->widthM($gapM) / 2;
        $gapM = $this->gapFor($gapM);

        $seats = [];
        foreach ($this->segments as $segment) {
            [$device, $count] = $segment;
            $roll = self::rollOf($segment);
            $span = $count * RolledBox::widthOf($device, $roll) + ($count - 1) * $gapM;
            $seats[] = [$device, $count, $x + $span / 2, $roll];
            $x += $span + $gapM;
        }

        return $seats;
    }
}
