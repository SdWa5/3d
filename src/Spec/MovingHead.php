<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * How a moving head splits into base, yoke and head, for {@see Shape::MovingHead}.
 *
 * **A moving head's shape is its identity.** A rack really is a box and loses nothing by being drawn as one; a
 * moving head drawn as a box is unrecognisable, and four of them hung on a truss would read as four suitcases.
 * So the three parts are stated and built: a base, two yoke arms rising from it, and the head slung between them.
 *
 * **These numbers are the estimated part of an otherwise sourced spec, and that is worth knowing.** Manufacturers
 * publish the fixture's overall dimensions and its weight — which is what `dimensions_m` and `weight_kg` carry —
 * but not how much of the height is base and how much is head. Those come off product photographs. The schema has
 * a single `provenance.dimensions`, so it cannot say "outer box from a datasheet, internal split from a photo";
 * the spec has to say it in prose. See docs/sources.md.
 *
 * PAN AND TILT ARE ZERO. The head points straight up, which is the pose every datasheet quotes its height in, and
 * the pose that makes `dimensions_m` true. A fixture's aim is a cue, not a dimension, and a scene that wants one
 * aimed has `pitch_deg` and `yaw_deg` like anything else.
 */
final class MovingHead
{
    public function __construct(
        public readonly float $baseHeight,
        public readonly float $yokeArmThickness,
        public readonly float $headDiameter,
        public readonly float $headLength,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('base_height_m'),
            $reader->requireFloat('yoke_arm_thickness_m'),
            $reader->requireFloat('head_diameter_m'),
            $reader->requireFloat('head_length_m'),
        );
    }

    /**
     * @return array{base_height_m: float, yoke_arm_thickness_m: float, head_diameter_m: float, head_length_m: float}
     */
    public function toArray(): array
    {
        return [
            'base_height_m' => $this->baseHeight,
            'yoke_arm_thickness_m' => $this->yokeArmThickness,
            'head_diameter_m' => $this->headDiameter,
            'head_length_m' => $this->headLength,
        ];
    }
}
