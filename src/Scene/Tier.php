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
     *     makes a mixed row. The third element is the segment's roll in degrees, absent meaning upright — and
     *     absent rather than required because PHP's list destructuring ignores what it is not given, so every
     *     `[$device, $count]` reader in the solver kept working when the roll arrived.
     */
    public function __construct(public readonly array $segments)
    {
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
     */
    public function widthM(float $gapM): float
    {
        $width = 0.0;
        foreach ($this->segments as $segment) {
            [$device, $count] = $segment;
            $width += $count * RolledBox::widthOf($device, self::rollOf($segment));
        }

        return $width + max(0, $this->count() - 1) * $gapM;
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

        return $shortest === INF ? 0.0 : $tallest - $shortest;
    }

    /** The width of the cabinet at the end of the row — what an overhang is measured against. */
    public function outerWidthM(): float
    {
        return RolledBox::widthOf($this->segments[0][0], self::rollOf($this->segments[0]));
    }

    public function isSub(): bool
    {
        foreach ($this->segments as [$device, $count]) {
            if ($device->subtype !== 'sub') {
                return false;
            }
        }

        return true;
    }

    public function isMixed(): bool
    {
        return count($this->segments) > 1;
    }

    /** The device ids in this row, left to right, for a message or a scene comment. */
    public function label(): string
    {
        return implode(' + ', array_map(
            static fn (array $segment): string => sprintf(
                '%d× %s%s',
                $segment[1],
                $segment[0]->id,
                self::rollOf($segment) === 0.0 ? '' : sprintf(' rolled %d°', (int)self::rollOf($segment)),
            ),
            $this->segments,
        ));
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
     * An **odd** cabinet count cannot be mirrored exactly: `intdiv(n, 2)` go left and the rest right, so the
     * middle cabinet joins the right-hand half.
     */
    public function mirrored(): self
    {
        $midpoint = intdiv($this->count(), 2);

        $segments = [];
        $index = 0;
        foreach ($this->segments as $segment) {
            [$device, $count] = $segment;
            $roll = self::rollOf($segment);

            if (fmod(abs($roll), 180.0) !== 90.0) {
                $segments[] = $segment;
                $index += $count;
                continue;
            }

            $left = max(0, min($count, $midpoint - $index));
            if ($left > 0) {
                $segments[] = [$device, $left, fmod(360.0 - $roll, 360.0)];
            }
            if ($count - $left > 0) {
                $segments[] = [$device, $count - $left, $roll];
            }
            $index += $count;
        }

        return new self($segments);
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
                    fmod(abs($roll), 180.0) === 90.0 ? fmod(360.0 - $roll, 360.0) : $roll,
                ];
            },
            array_reverse($this->segments),
        )));
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
