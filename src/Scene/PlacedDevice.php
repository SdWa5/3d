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
        public readonly float $rollDeg = 0.0,
    ) {
    }

    /**
     * Extent along the cabinet's own left-right and up axes after rolling about its front-to-back
     * axis. A cabinet turned on its side is as tall as it is wide, and the report and the camera
     * framing both need to know that.
     *
     * @return array{float, float, float} width, depth, height
     */
    public function extent(): array
    {
        $radians = deg2rad($this->rollDeg);
        $cos = abs(cos($radians));
        $sin = abs(sin($radians));
        $width = $this->device->dimensions->width;
        $height = $this->device->dimensions->height;

        return [
            $width * $cos + $height * $sin,
            $this->device->dimensions->depth,
            $width * $sin + $height * $cos,
        ];
    }

    /**
     * How far the model has to be lifted so a rolled cabinet still rests on `position` instead of
     * sinking through it. Geometry runs from z = 0 to the cabinet's height in its own frame, so any
     * roll drops part of it below zero.
     */
    public function zLift(): float
    {
        $radians = deg2rad($this->rollDeg);
        $width = $this->device->dimensions->width;
        $height = $this->device->dimensions->height;

        $lowest = 0.0;
        foreach ([-$width / 2, $width / 2] as $x) {
            foreach ([0.0, $height] as $z) {
                $lowest = min($lowest, -$x * sin($radians) + $z * cos($radians));
            }
        }

        return -$lowest;
    }

    /**
     * Height of this cabinet's top surface — what anything stacked on it stands on.
     */
    public function topZ(): float
    {
        return $this->position[2] + $this->extent()[2];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'placement_id' => $this->placementId,
            'device' => $this->device->id,
            // The z the Blender side should use: the slot, plus whatever the roll costs.
            'position_m' => [$this->position[0], $this->position[1], $this->position[2] + $this->zLift()],
            'yaw_deg' => $this->yawDeg,
            'roll_deg' => $this->rollDeg,
        ];
    }
}
