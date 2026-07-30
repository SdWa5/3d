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
    /**
     * How far off its own axis a target may sit before the tilt towards it stops meaning anything.
     */
    public const MAX_OFF_AXIS_DEG = 80.0;

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

        // A cabinet faces −Y at yaw 0, so this is the turn that swings that onto the target.
        $yaw = ($dx === 0.0 && $dy === 0.0) ? 0.0 : rad2deg(atan2($dx, -$dy));

        return new self(self::pitchTowards($from, $target, $cabinetHeight, $yaw, $rollDeg), $rollDeg, $yaw);
    }

    /**
     * The down-tilt that puts $target at the height a cabinet is pointing, for a cabinet whose yaw is
     * already decided.
     *
     * A cabinet in an arc cannot point *at* the focus — its yaw belongs to the arc — so the meaningful
     * tilt is the one that brings the target into the cabinet's own vertical plane. That is measured
     * along the cabinet's own axis, not along the straight line to the target: only the component of the
     * horizontal distance that lies ahead of the cabinet counts. For a three-wide arc that is 4.75°
     * rather than 4.48°; for a five-wide cluster splayed 30° the outer boxes need **2.28×** the tilt, so
     * ignoring it would leave them aimed well over the target.
     *
     * @param array{float, float, float} $from bottom-center of the cabinet
     * @param array{float, float, float} $target
     */
    public static function pitchTowards(
        array $from,
        array $target,
        float $cabinetHeight,
        float $yawDeg,
        float $rollDeg = 0.0,
    ): float {
        $dx = $target[0] - $from[0];
        $dy = $target[1] - $from[1];
        $dz = $target[2] - ($from[2] + $cabinetHeight / 2);

        $horizontal = sqrt($dx ** 2 + $dy ** 2);
        if ($horizontal === 0.0 && $dz === 0.0) {
            return 0.0;
        }

        if ($horizontal > 0.0) {
            $offAxis = deg2rad($yawDeg) - atan2($dx, -$dy);
            // Past the cap the tilt runs away towards vertical for a target that is nearly abeam, which
            // is a cabinet pointing somewhere else entirely rather than a tilt worth computing.
            $horizontal *= max(cos($offAxis), cos(deg2rad(self::MAX_OFF_AXIS_DEG)));
        }

        $pitch = rad2deg(atan2(-$dz, $horizontal));

        // Roll turns the cabinet over, which turns nose-down into nose-up. Only multiples of 180 are
        // handled: a cabinet rolled onto its side has no down-tilt to speak of.
        return fmod(abs($rollDeg), 360.0) === 180.0 ? -$pitch : $pitch;
    }

    /**
     * @return array{pitch_deg: float, roll_deg: float, yaw_deg: float}
     */
    public function toArray(): array
    {
        return [
            'pitch_deg' => self::tidy($this->pitchDeg),
            'roll_deg' => self::tidy($this->rollDeg),
            'yaw_deg' => self::tidy($this->yawDeg),
        ];
    }

    /**
     * Negative zero prints as `-0` in the build plan, so the middle cabinet of a concave arc would show
     * up as a diff every time the file is written. In a repository whose point is that a setup is a
     * commit, that is worth one comparison.
     */
    private static function tidy(float $value): float
    {
        return $value === 0.0 ? 0.0 : $value;
    }
}
