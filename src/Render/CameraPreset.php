<?php

declare(strict_types=1);

namespace App\Render;

/**
 * Where the camera stands. Each preset is a direction to look from, not a fixed position — the
 * distance is worked out from the scene's own size, so the same preset frames a single cabinet and a
 * fourteen-wide sub wall equally well.
 */
enum CameraPreset: string
{
    case ThreeQuarter = 'three-quarter';
    case Front = 'front';
    case Side = 'side';
    case Top = 'top';
    case Crowd = 'crowd';

    /**
     * Unit-ish direction from the scene's centre towards the camera. Cabinets face −Y, so a viewer
     * standing in front of them is at negative Y.
     *
     * @return array{float, float, float}
     */
    public function direction(): array
    {
        return match ($this) {
            self::ThreeQuarter => [0.72, -1.0, 0.42],
            self::Front => [0.0, -1.0, 0.13],
            self::Side => [1.0, -0.22, 0.18],
            self::Top => [0.0, -0.18, 1.0],
            self::Crowd => [0.10, -1.0, 0.0],
        };
    }

    /**
     * Focal length in mm. Wider for the top view so a long wall still fits without retreating half
     * way across the field; longer for the crowd view, which should look like a photo of the rig
     * rather than a wide-angle exaggeration of it.
     */
    public function lensMm(): float
    {
        return match ($this) {
            self::ThreeQuarter, self::Front, self::Side => 42.0,
            self::Top => 32.0,
            self::Crowd => 50.0,
        };
    }

    /**
     * Eye height in metres for a preset that stands on the ground rather than floating, or null when
     * the camera should sit wherever the framing puts it.
     */
    public function eyeHeightM(): ?float
    {
        return self::Crowd === $this ? 1.65 : null;
    }

    /**
     * How much of the frame to leave around the rig.
     *
     * The top view gets more, because a plan view with the wall touching the edges reads badly. The
     * crowd view gets less than 1.0 on purpose — standing in front of a 4 m sub wall, it fills your
     * view, and a shot that politely fits the whole thing in undersells it.
     */
    public function margin(): float
    {
        return match ($this) {
            self::Top => 1.25,
            self::Crowd => 0.92,
            default => 1.15,
        };
    }

    public function describe(): string
    {
        return match ($this) {
            self::ThreeQuarter => 'from the front right and slightly above — the default',
            self::Front => 'straight on, as the audience sees it',
            self::Side => 'from stage right, showing cabinet depth',
            self::Top => 'plan view, for checking the footprint',
            self::Crowd => 'from eye height in the crowd',
        };
    }
}
