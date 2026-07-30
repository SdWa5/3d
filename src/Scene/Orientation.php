<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How a cabinet is turned: down-tilt, roll and aim, in the order Blender applies them (X, then Y,
 * then Z).
 *
 * Kept as its own value object because the arithmetic that follows from it — where the lowest corner
 * ends up, how much floor the cabinet covers once it is angled — is the same for every consumer, and
 * getting it slightly different in the compiler, the report and the camera framing is exactly how a
 * library like this starts lying to you.
 */
final class Orientation
{
    public function __construct(
        public readonly float $pitchDeg = 0.0,
        public readonly float $rollDeg = 0.0,
        public readonly float $yawDeg = 0.0,
    ) {
    }

    public function isUpright(): bool
    {
        return $this->pitchDeg === 0.0 && $this->rollDeg === 0.0;
    }

    /**
     * Rotates a point the way Blender will: pitch about X, then roll about Y, then yaw about Z.
     *
     * @param array{float, float, float} $point
     * @return array{float, float, float}
     */
    public function apply(array $point): array
    {
        [$x, $y, $z] = $point;

        $pitch = deg2rad($this->pitchDeg);
        $y2 = $y * cos($pitch) - $z * sin($pitch);
        $z2 = $y * sin($pitch) + $z * cos($pitch);

        $roll = deg2rad($this->rollDeg);
        $x3 = $x * cos($roll) + $z2 * sin($roll);
        $z3 = -$x * sin($roll) + $z2 * cos($roll);

        $yaw = deg2rad($this->yawDeg);
        $x4 = $x3 * cos($yaw) - $y2 * sin($yaw);
        $y4 = $x3 * sin($yaw) + $y2 * cos($yaw);

        return [$x4, $y4, $z3];
    }

    /**
     * Yaw and pitch that make a cabinet at $from face $target.
     *
     * Aiming is measured from the cabinet's own mid-height rather than its base, because that is
     * roughly where it radiates from and it is what makes a row of tops converge sensibly on one
     * point rather than all tilting as if they sat on the floor.
     *
     * @param array{float, float, float} $from bottom-center of the cabinet
     * @param array{float, float, float} $target
     */
    public static function aimedAt(array $from, array $target, float $cabinetHeight, float $rollDeg = 0.0): self
    {
        $dx = $target[0] - $from[0];
        $dy = $target[1] - $from[1];
        $dz = $target[2] - ($from[2] + $cabinetHeight / 2);

        // A cabinet faces −Y at yaw 0, so this is the turn that swings that onto the target.
        $yaw = ($dx === 0.0 && $dy === 0.0) ? 0.0 : rad2deg(atan2($dx, -$dy));

        $horizontal = sqrt($dx ** 2 + $dy ** 2);
        // Positive pitch is nose-down, which is what a target below the cabinet needs.
        $pitch = ($horizontal === 0.0 && $dz === 0.0) ? 0.0 : rad2deg(atan2(-$dz, $horizontal));

        return new self($pitch, $rollDeg, $yaw);
    }

    /**
     * @return array{pitch_deg: float, roll_deg: float, yaw_deg: float}
     */
    public function toArray(): array
    {
        return [
            'pitch_deg' => $this->pitchDeg,
            'roll_deg' => $this->rollDeg,
            'yaw_deg' => $this->yawDeg,
        ];
    }
}
