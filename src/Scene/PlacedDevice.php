<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One cabinet resolved to an absolute world position — the output of the compiler and the input the
 * Blender script places.
 */
final class PlacedDevice
{
    /**
     * @param array{float, float, float} $position bottom-center of the cabinet, in metres
     */
    public function __construct(
        public readonly string $placementId,
        public readonly DeviceSpec $device,
        public readonly array $position,
        public readonly float $yawDeg,
    ) {
    }

    /**
     * Height of this cabinet's top surface — what anything stacked on it stands on.
     */
    public function topZ(): float
    {
        return $this->position[2] + $this->device->dimensions->height;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'placement_id' => $this->placementId,
            'device' => $this->device->id,
            'position_m' => $this->position,
            'yaw_deg' => $this->yawDeg,
        ];
    }
}
