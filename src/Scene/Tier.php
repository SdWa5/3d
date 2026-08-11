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
     * @param list<array{DeviceSpec, int}> $segments left to right; more than one makes a mixed row
     */
    public function __construct(public readonly array $segments)
    {
    }

    public static function of(DeviceSpec $device, int $count): self
    {
        return new self([[$device, $count]]);
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
        foreach ($this->segments as [$device, $count]) {
            $width += $count * $device->dimensions->width;
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
        foreach ($this->segments as [$device, $count]) {
            $height = max($height, $device->dimensions->height);
        }

        return $height;
    }

    /** How far the tallest and shortest cabinet in this row differ — 0 for an even row. */
    public function heightStepM(): float
    {
        $tallest = 0.0;
        $shortest = INF;
        foreach ($this->segments as [$device, $count]) {
            $tallest = max($tallest, $device->dimensions->height);
            $shortest = min($shortest, $device->dimensions->height);
        }

        return $shortest === INF ? 0.0 : $tallest - $shortest;
    }

    /** The width of the cabinet at the end of the row — what an overhang is measured against. */
    public function outerWidthM(): float
    {
        return $this->segments[0][0]->dimensions->width;
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
            static fn (array $segment): string => sprintf('%d× %s', $segment[1], $segment[0]->id),
            $this->segments,
        ));
    }

    /**
     * Each segment with the x offset of its own centre, relative to the row's centre.
     *
     * This is what lets a mixed row expand into one placement per segment: each segment is a plain `row`
     * of identical cabinets, and the offsets put them side by side with the row centred on `at`.
     *
     * @return list<array{DeviceSpec, int, float}>
     */
    public function seats(float $gapM): array
    {
        $x = -$this->widthM($gapM) / 2;

        $seats = [];
        foreach ($this->segments as [$device, $count]) {
            $span = $count * $device->dimensions->width + ($count - 1) * $gapM;
            $seats[] = [$device, $count, $x + $span / 2];
            $x += $span + $gapM;
        }

        return $seats;
    }
}
