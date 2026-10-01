<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\InvalidSpecException;

/** Room limits apply to the finished rig, including aiming, spacing and flown equipment. */
final class RoomBounds
{
    public function __construct(
        public readonly ?float $widthM = null,
        public readonly ?float $heightM = null,
    ) {
        foreach (['room-width' => $widthM, 'room-height' => $heightM] as $name => $value) {
            if (null !== $value && (!is_finite($value) || $value <= 0.0)) {
                throw new InvalidSpecException($name.' must be a finite positive number');
            }
        }
    }

    /** @param list<PlacedDevice> $placed */
    public function problem(array $placed): ?string
    {
        if ([] === $placed) {
            return null;
        }
        $left = INF;
        $right = -INF;
        $top = -INF;
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            $left = min($left, $box['min'][0]);
            $right = max($right, $box['max'][0]);
            $top = max($top, $box['max'][2]);
        }
        if (null !== $this->widthM && $right - $left > $this->widthM + 1e-6) {
            return sprintf('the whole rig is %.3f m wide and exceeds the %.3f m room width', $right - $left, $this->widthM);
        }
        if (null !== $this->heightM && $top > $this->heightM + 1e-6) {
            return sprintf('the whole rig reaches %.3f m and exceeds the %.3f m room height', $top, $this->heightM);
        }

        return null;
    }
}
