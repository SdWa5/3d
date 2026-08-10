<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One solved row of a {@see Stack} — this many of this cabinet, side by side.
 *
 * A tier holds a cabinet and a count and nothing else. Where it sits is not its business: the tiers come
 * out in order and each stands on the one below, so a height would only be a second copy of what `on:`
 * already works out from the specs.
 */
final class Tier
{
    public function __construct(
        public readonly DeviceSpec $device,
        public readonly int $count,
    ) {
    }

    /**
     * How wide this row stands, cabinets plus the working gaps between them.
     *
     * Nominal widths, deliberately: a tier is decided before anything is aimed, and an unaimed cabinet's
     * box is its box. Once a tier is spread by {@see Alignment} the real edges are solved against the
     * rotated boxes, which is a different question asked later.
     */
    public function widthM(float $gapM): float
    {
        return $this->count * $this->device->dimensions->width + ($this->count - 1) * $gapM;
    }

    public function heightM(): float
    {
        return $this->device->dimensions->height;
    }

    public function isSub(): bool
    {
        return $this->device->subtype === 'sub';
    }
}
