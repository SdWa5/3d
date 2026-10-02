<?php

declare(strict_types=1);

namespace App\Render;

/**
 * Where a camera preset stands when somebody states it, rather than where the framing puts it.
 *
 * A preset is a direction, and by default its distance is whatever fits the rig. That answers "show me the rig" and
 * not "show me the rig as somebody standing 12 m in front of it sees it", which is what a stated distance is for.
 * With one, the camera stands that far from the rig's front face along the preset's direction and the lens zooms to
 * fit, so two rigs rendered from the same stand are seen from the same place. A stated eye height puts the camera
 * at that height whether the distance is stated or fitted.
 */
final readonly class CameraStand
{
    public function __construct(
        /** Metres from the rig's face nearest the camera, measured on the ground, or null to fit. */
        public ?float $distanceM = null,
        /** Metres above the floor, or null for the preset's own. */
        public ?float $eyeHeightM = null,
    ) {
        if (null !== $distanceM && $distanceM <= 0.0) {
            throw new \InvalidArgumentException(sprintf('A camera distance must be above 0 m, got %s', $distanceM));
        }
        if (null !== $eyeHeightM && $eyeHeightM <= 0.0) {
            throw new \InvalidArgumentException(sprintf('An eye height must be above 0 m, got %s', $eyeHeightM));
        }
    }

    public function isStated(): bool
    {
        return null !== $this->distanceM || null !== $this->eyeHeightM;
    }

    /**
     * What goes after the preset in a file name, `-12m-2m` for both, so a stated stand never overwrites the fitted
     * picture of the same preset. Empty when nothing is stated.
     */
    public function suffix(): string
    {
        return (null === $this->distanceM ? '' : '-'.self::metres($this->distanceM))
            .(null === $this->eyeHeightM ? '' : '-'.self::metres($this->eyeHeightM).'-high');
    }

    private static function metres(float $value): string
    {
        return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.').'m';
    }
}
