<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Outer dimensions in metres — width across the front, height, depth front to back. Values are
 * kept exactly as written in the spec, including nonsense ones: SpecValidator is what rejects
 * those, so a single run can report every problem in the library at once.
 */
final class Dimensions
{
    public function __construct(
        public readonly float $width,
        public readonly float $height,
        public readonly float $depth,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('width'),
            $reader->requireFloat('height'),
            $reader->requireFloat('depth'),
        );
    }

    public function smallestEdge(): float
    {
        return min($this->width, $this->height, $this->depth);
    }

    /**
     * Shipping volume, handy for working out what fits in the van.
     */
    public function volumeM3(): float
    {
        return $this->width * $this->height * $this->depth;
    }

    /**
     * @return array{width: float, height: float, depth: float}
     */
    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height, 'depth' => $this->depth];
    }
}
