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
        public readonly bool $seated = true,
        /**
         * Whether this cabinet's placement asked for an aim line either way, or left it to the mode.
         * Carried here so {@see \App\Render\RenderPlan} still reads nothing but placed devices.
         */
        public readonly ?bool $aimLines = null,
        /**
         * What the report groups this cabinet's weight under when it is flown, or null when it stands on
         * something. Carried here for the same reason {@see $aimLines} is: {@see SceneReport} is handed
         * nothing but placed devices.
         */
        public readonly ?string $flyPoint = null,
        /**
         * How far the model is stretched along its own height, 1.0 unless a telescoping tower was cranked down.
         * {@see $device} already carries the cranked height, so every check reads that. Only Blender, which
         * instances the one model built at full extension, needs the ratio.
         */
        public readonly float $scaleZ = 1.0,
    ) {
    }

    /**
     * The cabinet's extent relative to its slot, after rotation and after being lifted back onto it.
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public function box(): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach ($this->corners() as $corner) {
            $rotated = $this->orientation->apply($corner);
            for ($axis = 0; $axis < 3; ++$axis) {
                $min[$axis] = min($min[$axis], $rotated[$axis]);
                $max[$axis] = max($max[$axis], $rotated[$axis]);
            }
        }

        // Rotating about the origin drops part of the cabinet below zero; put it back on its slot —
        // unless it is flown, where the solved position is already where it hangs.
        $lift = $this->seated ? -$min[2] : 0.0;
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
        if (!$this->seated) {
            // A flown cabinet's slot is not the floor. Each element of a hang is tilted differently, so
            // lifting each one back onto its own slot would pull the array apart at every joint.
            return 0.0;
        }

        $lowest = 0.0;
        foreach ($this->corners() as $corner) {
            $lowest = min($lowest, $this->orientation->apply($corner)[2]);
        }

        return -$lowest;
    }

    /**
     * The cabinet's eight corners in its own frame. Lives on the spec, because the contact solve needs
     * the same shape and one cabinet cannot be two shapes.
     *
     * @return list<array{float, float, float}>
     */
    private function corners(): array
    {
        return $this->device->shellCorners();
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
     * World y of the frontmost point of the cabinet's bottom face, which is the edge it stands on.
     *
     * Not the frontmost point of the whole cabinet. A top tilted down towards its focus leans its upper front edge
     * out, and held to that, its foot stood back from the front of the wall it is on.
     */
    public function footFrontY(): float
    {
        $corners = $this->corners();
        $bottom = min(array_map(static fn (array $corner): float => $corner[2], $corners));

        $front = INF;
        foreach ($corners as $corner) {
            if ($corner[2] <= $bottom + 1e-9) {
                $front = min($front, $this->orientation->apply($corner)[1]);
            }
        }

        return $this->position[1] + $front;
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
        ] + (1.0 === $this->scaleZ ? [] : ['scale_z' => $this->scaleZ])
            // The packed model, `<id>@packed` in the device's .blend, rather than the erected one.
            + ($this->device->folded ? ['folded' => true] : [])
            + $this->orientation->toArray();
    }
}
