<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Nominal dispersion in degrees. Optional, but worth filling in from the cloned original's
 * datasheet: it is what makes a 3D setup answer coverage questions instead of only looking
 * right, and it drives the optional coverage cones.
 */
final class Coverage
{
    public function __construct(
        public readonly float $horizontal,
        public readonly float $vertical,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('horizontal'),
            $reader->requireFloat('vertical'),
        );
    }

    /**
     * How wide and how tall the pattern is by the time it reaches `$distance`, in metres.
     *
     * Straight trigonometry — `2·d·tan(θ/2)` per axis — but worth having here rather than in the geometry
     * builder, because it is the one part of a coverage cone that can be checked without opening Blender.
     * A 60° × 40° cabinet at 10 m covers 11.55 m across and 7.28 m high.
     *
     * @return array{float, float}
     */
    public function spreadAt(float $distance): array
    {
        return [
            2 * $distance * tan(deg2rad($this->horizontal) / 2),
            2 * $distance * tan(deg2rad($this->vertical) / 2),
        ];
    }

    /**
     * @return array{horizontal: float, vertical: float}
     */
    public function toArray(): array
    {
        return ['horizontal' => $this->horizontal, 'vertical' => $this->vertical];
    }
}
