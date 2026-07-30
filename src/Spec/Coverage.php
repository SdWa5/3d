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
     * @return array{horizontal: float, vertical: float}
     */
    public function toArray(): array
    {
        return ['horizontal' => $this->horizontal, 'vertical' => $this->vertical];
    }
}
