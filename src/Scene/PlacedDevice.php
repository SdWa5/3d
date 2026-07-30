<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One cabinet resolved to an absolute world position and orientation — the output of the compiler and
 * the input the Blender script places.
 *
 * Everything derived from the orientation goes through `box()`: where the cabinet has to be lifted to
 * so it still rests on its slot, how tall it now stands, and how much floor it covers. One exact
 * computation, so the compiler, the report and the camera framing cannot disagree.
 */
final class PlacedDevice
{
    /**
     * @param array{float, float, float} $position bottom-center of the cabinet's slot, in metres
     */
    public function __construct(
        public readonly string $placementId,
        public readonly DeviceSpec $device,
        public readonly array $position,
        public readonly Orientation $orientation,
    ) {
    }

    /**
     * The cabinet's extent relative to its slot, after rotation and after being lifted back onto it.
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public function box(): array
    {
        $dimensions = $this->device->dimensions;
        $halfWidth = $dimensions->width / 2;
        $halfDepth = $dimensions->depth / 2;

        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach ([-$halfWidth, $halfWidth] as $x) {
            foreach ([-$halfDepth, $halfDepth] as $y) {
                foreach ([0.0, $dimensions->height] as $z) {
                    $corner = $this->orientation->apply([$x, $y, $z]);
                    for ($axis = 0; $axis < 3; ++$axis) {
                        $min[$axis] = min($min[$axis], $corner[$axis]);
                        $max[$axis] = max($max[$axis], $corner[$axis]);
                    }
                }
            }
        }

        // Rotating about the origin drops part of the cabinet below zero; put it back on its slot.
        $lift = -$min[2];
        $min[2] += $lift;
        $max[2] += $lift;

        /** @var array{float, float, float} $min */
        /** @var array{float, float, float} $max */
        return ['min' => $min, 'max' => $max];
    }

    /**
     * How far the model has to be raised so an angled or upside-down cabinet still rests on its slot
     * rather than sinking through it.
     */
    public function zLift(): float
    {
        $dimensions = $this->device->dimensions;
        $lowest = 0.0;

        foreach ([-$dimensions->width / 2, $dimensions->width / 2] as $x) {
            foreach ([-$dimensions->depth / 2, $dimensions->depth / 2] as $y) {
                foreach ([0.0, $dimensions->height] as $z) {
                    $lowest = min($lowest, $this->orientation->apply([$x, $y, $z])[2]);
                }
            }
        }

        return -$lowest;
    }

    /**
     * Width, depth and height the cabinet actually occupies once turned. A cabinet on its side is as
     * tall as it is wide; a tilted one is both taller and deeper than it was.
     *
     * @return array{float, float, float}
     */
    public function extent(): array
    {
        ['min' => $min, 'max' => $max] = $this->box();

        return [$max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]];
    }

    /**
     * Absolute bounding box in world space.
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public function worldBox(): array
    {
        ['min' => $min, 'max' => $max] = $this->box();

        return [
            'min' => [
                $this->position[0] + $min[0],
                $this->position[1] + $min[1],
                $this->position[2] + $min[2],
            ],
            'max' => [
                $this->position[0] + $max[0],
                $this->position[1] + $max[1],
                $this->position[2] + $max[2],
            ],
        ];
    }

    /**
     * Height of this cabinet's highest point — what anything stacked on it stands on.
     */
    public function topZ(): float
    {
        return $this->worldBox()['max'][2];
    }

    /**
     * The slot position with the rotation lift applied — where the model actually sits.
     *
     * @return array{float, float, float}
     */
    public function liftedPosition(): array
    {
        return [$this->position[0], $this->position[1], $this->position[2] + $this->zLift()];
    }

    /**
     * Centre of the cabinet's front face in world space — near enough to where it radiates from, and
     * where an aim line should start.
     *
     * @return array{float, float, float}
     */
    public function frontFaceCentre(): array
    {
        $local = [0.0, -$this->device->dimensions->depth / 2, $this->device->dimensions->height / 2];
        $rotated = $this->orientation->apply($local);
        $origin = $this->liftedPosition();

        return [$origin[0] + $rotated[0], $origin[1] + $rotated[1], $origin[2] + $rotated[2]];
    }

    /**
     * Unit vector the cabinet points along. Cabinets face −Y before any rotation, so this is that
     * vector turned — which means it reflects where the cabinet *ends up* aimed, not where it was
     * asked to aim. A mistake in the aiming shows up rather than being drawn over.
     *
     * @return array{float, float, float}
     */
    public function frontDirection(): array
    {
        return $this->orientation->apply([0.0, -1.0, 0.0]);
    }

    public function yawDeg(): float
    {
        return $this->orientation->yawDeg;
    }

    public function rollDeg(): float
    {
        return $this->orientation->rollDeg;
    }

    public function pitchDeg(): float
    {
        return $this->orientation->pitchDeg;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'placement_id' => $this->placementId,
            'device' => $this->device->id,
            // The z the Blender side should use: the slot, plus whatever the rotation costs.
            'position_m' => [
                $this->position[0],
                $this->position[1],
                $this->position[2] + $this->zLift(),
            ],
        ] + $this->orientation->toArray();
    }
}
