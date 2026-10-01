<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The truss adapter on top of a {@see Mast}: a square bar across the truss with a spigot down into the stand's
 * receiver and a clamp under each bottom chord.
 *
 * `height_m` is all of it, from the top of the top stage to the face the truss rests on. The bar lies across the
 * truss, which a backdrop lays along X, so the bar runs along Y and `clamp_spacing_m` is the distance between the two
 * bottom chords it carries.
 */
final class MastAdapter
{
    public function __construct(
        public readonly float $length,
        public readonly float $bar,
        public readonly float $height,
        public readonly float $clampSpacing,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('length_m'),
            $reader->requireFloat('bar_m'),
            $reader->requireFloat('height_m'),
            $reader->requireFloat('clamp_spacing_m'),
        );
    }

    /**
     * @return array{length_m: float, bar_m: float, height_m: float, clamp_spacing_m: float}
     */
    public function toArray(): array
    {
        return [
            'length_m' => $this->length,
            'bar_m' => $this->bar,
            'height_m' => $this->height,
            'clamp_spacing_m' => $this->clampSpacing,
        ];
    }
}
