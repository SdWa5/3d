<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;

/**
 * The point a cluster aims at, stated the way you actually think about it: how far out in front of the
 * rig, and how high.
 *
 * Absolute coordinates would work too — `aim_at: [x, y, z]` still exists — but "ten metres out at ear
 * height" is what a decision about aiming really is, and it stays right when the rig moves or grows.
 */
final class Focus
{
    /** Far enough out to be past the front rows, which is where aiming actually matters. */
    public const DEFAULT_DISTANCE_M = 10.0;

    /** Roughly ear height for a standing audience. */
    public const DEFAULT_HEIGHT_M = 1.8;

    public function __construct(
        public readonly float $distanceM = self::DEFAULT_DISTANCE_M,
        public readonly float $heightM = self::DEFAULT_HEIGHT_M,
        public readonly ?float $xM = null,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): self
    {
        if ($reader === null) {
            return new self();
        }

        return new self(
            $reader->optionalFloat('distance_m', self::DEFAULT_DISTANCE_M) ?? self::DEFAULT_DISTANCE_M,
            $reader->optionalFloat('height_m', self::DEFAULT_HEIGHT_M) ?? self::DEFAULT_HEIGHT_M,
            $reader->optionalFloat('x_m'),
        );
    }

    /**
     * The absolute point, given where the rig stands.
     *
     * Distance is measured from the rig's **front face**, not from the world origin, so a deeper rig
     * does not quietly pull the focus closer. Cabinets face −Y, so "in front" is decreasing y.
     *
     * @param array{float, float} $frontCentre the x centre of the rig and the y of its front face
     * @return array{float, float, float}
     */
    public function point(array $frontCentre): array
    {
        return [
            $this->xM ?? $frontCentre[0],
            $frontCentre[1] - $this->distanceM,
            $this->heightM,
        ];
    }

    /**
     * @return array{distance_m: float, height_m: float, x_m: float|null}
     */
    public function toArray(): array
    {
        return ['distance_m' => $this->distanceM, 'height_m' => $this->heightM, 'x_m' => $this->xM];
    }
}
