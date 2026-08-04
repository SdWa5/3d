<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How a cabinet is turned: down-tilt, roll and aim, in the order a scene decides them.
 *
 * Kept as its own value object because the arithmetic that follows from it — where the lowest corner
 * ends up, how much floor the cabinet covers once it is angled — is the same for every consumer, and
 * getting it slightly different in the compiler, the report and the camera framing is exactly how a
 * library like this starts lying to you.
 *
 * The order, and why it is not Blender's
 * --------------------------------------
 * `R = Rz(yaw)·Rx(pitch)·Ry(roll)`: roll the cabinet in its own frame, *then* tilt it down, *then* aim
 * it. That is what a scene means by the three words. Blender's `rotation_euler` is `Rz·Ry·Rx`, a
 * different order, and the two agree only while roll is a multiple of 180 — so {@see eulerXYZ} factors
 * this rotation back into the triple Blender consumes rather than pretending they are the same thing.
 *
 * The order matters for one reason above all: in this order `Ry(roll)·(0,−1,0) = (0,−1,0)` for **any**
 * roll, so a cabinet's roll never changes where it points. In Blender's order roll is applied after
 * pitch and turns nose-down into nose-up, which this class used to paper over by flipping the sign of
 * the pitch when the roll was 180 — correct for that one case, silently wrong for a cabinet on its
 * side, and the reason `roll_deg: 90` could not be aimed at all.
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

    /**
     * Rotates a point: roll about Y, then pitch about X, then yaw about Z.
     *
     * @param array{float, float, float} $point
     * @return array{float, float, float}
     */
    public function apply(array $point): array
    {
        [$x, $y, $z] = $point;

        $roll = deg2rad($this->rollDeg);
        $x1 = $x * cos($roll) + $z * sin($roll);
        $z1 = -$x * sin($roll) + $z * cos($roll);

        $pitch = deg2rad($this->pitchDeg);
        $y2 = $y * cos($pitch) - $z1 * sin($pitch);
        $z2 = $y * sin($pitch) + $z1 * cos($pitch);

        $yaw = deg2rad($this->yawDeg);
        $x3 = $x1 * cos($yaw) - $y2 * sin($yaw);
        $y3 = $x1 * sin($yaw) + $y2 * cos($yaw);

        return [$x3, $y3, $z2];
    }

    /**
     * This rotation as a matrix, `Rz(yaw)·Rx(pitch)·Ry(roll)`.
     *
     * Built by turning the three basis vectors rather than by multiplying the closed form out, so
     * {@see apply} stays the single definition of what this rotation *is*. Multiplying it out would be
     * the same rotation to about one part in 1e16 — which is also enough to move a reported footprint's
     * last bit and put a spurious diff in a committed build plan.
     *
     * @return array{array{float, float, float}, array{float, float, float}, array{float, float, float}}
     */
    public function matrix(): array
    {
        [$xx, $xy, $xz] = $this->apply([1.0, 0.0, 0.0]);
        [$yx, $yy, $yz] = $this->apply([0.0, 1.0, 0.0]);
        [$zx, $zy, $zz] = $this->apply([0.0, 0.0, 1.0]);

        // Columns are where the basis vectors land.
        return [
            [$xx, $yx, $zx],
            [$xy, $yy, $zy],
            [$xz, $yz, $zz],
        ];
    }

    /**
     * The euler-XYZ triple that reproduces this orientation — the one form Blender consumes.
     *
     * Roll 0 and roll 180 are returned outright, because there the two orders coincide *exactly*:
     * `Rx(p)·Ry(180) === Ry(180)·Rx(−p)`, an identity rather than an approximation. Factoring them out
     * of the matrix below would give the same angles, but only after round-tripping each one through
     * `atan2`, which would move every committed scene by a few times 1e-16 for no gain.
     *
     * @return array{float, float, float}
     */
    public function eulerXYZ(): array
    {
        $roll = fmod($this->rollDeg, 360.0);
        if ($roll === 0.0) {
            return [$this->pitchDeg, $this->rollDeg, $this->yawDeg];
        }
        if (abs($roll) === 180.0) {
            return [self::tidy(-$this->pitchDeg), $this->rollDeg, $this->yawDeg];
        }

        return $this->eulerFrom($this->matrix());
    }

    /**
     * This rotation applied *after* `$inner` — the inner one first, then this one.
     *
     * How a group's own turn meets the turn of whatever is nested inside it. Adding the angles up would
     * be wrong and only looks right in the cases that happen to commute: rolling a group over reverses
     * the yaw of everything inside it (`Ry(180)·Rz(θ) = Rz(−θ)·Ry(180)`), which is physically what turning
     * an arrangement upside down does and arithmetically not addition.
     *
     * Null when the product cannot be split back into these three angles, which is a cabinet pitched
     * exactly on end. See {@see fromMatrix}.
     */
    public function after(self $inner): ?self
    {
        $outer = $this->matrix();
        $nested = $inner->matrix();

        $product = [];
        for ($row = 0; $row < 3; ++$row) {
            for ($column = 0; $column < 3; ++$column) {
                $product[$row][$column] = $outer[$row][0] * $nested[0][$column]
                    + $outer[$row][1] * $nested[1][$column]
                    + $outer[$row][2] * $nested[2][$column];
            }
        }

        /** @var array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $product */
        return self::fromMatrix($product);
    }

    /**
     * The pitch, roll and yaw of a rotation matrix, or null when they cannot be told apart.
     *
     * At a pitch of exactly ±90° the roll and the yaw turn about the same world axis and only their sum
     * is determined, so there are infinitely many answers and no reason to prefer one. Nothing in a PA
     * setup stands a cabinet on end, so this returns null and lets the compiler say so against the
     * placement's name — the same "reject rather than silently resolve one way" line that `aim` together
     * with `yaw_deg` already draws.
     *
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $m
     */
    public static function fromMatrix(array $m): ?self
    {
        // |cos(pitch)|, from the two entries of the bottom row that roll alone scales.
        $cosPitch = hypot($m[2][0], $m[2][2]);
        if ($cosPitch < 1e-12) {
            return null;
        }

        return new self(
            rad2deg(atan2($m[2][1], $cosPitch)),
            rad2deg(atan2(-$m[2][0], $m[2][2])),
            rad2deg(atan2(-$m[0][1], $m[1][1])),
        );
    }

    /**
     * Yaw and pitch that make a cabinet at $from face $target.
     *
     * Aiming is measured from the cabinet's own mid-height rather than its base, because that is
     * roughly where it radiates from and it is what makes a row of tops converge sensibly on one
     * point rather than all tilting as if they sat on the floor.
     *
     * `$rollDeg` is carried through untouched: roll does not affect where a cabinet points, so it
     * cannot affect the angles that aim it either.
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

        return new self(self::pitchTowards($from, $target, $cabinetHeight, $yaw), $rollDeg, $yaw);
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
     * Roll is deliberately not a parameter. It was one while rotations were composed in Blender's order,
     * where rolling a cabinet over inverted its tilt; in this class's order roll leaves the −Y axis fixed
     * and so cannot change the answer.
     *
     * @param array{float, float, float} $from bottom-center of the cabinet
     * @param array{float, float, float} $target
     */
    public static function pitchTowards(array $from, array $target, float $cabinetHeight, float $yawDeg): float
    {
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

        return rad2deg(atan2(-$dz, $horizontal));
    }

    /**
     * @return array{pitch_deg: float, roll_deg: float, yaw_deg: float, rotation_euler_deg: array{float, float, float}}
     */
    public function toArray(): array
    {
        return [
            'pitch_deg' => self::tidy($this->pitchDeg),
            'roll_deg' => self::tidy($this->rollDeg),
            'yaw_deg' => self::tidy($this->yawDeg),
            // The three angles above say what the scene asked for and are what the report and a human
            // read; this is the same rotation in the form Blender applies. They differ once a cabinet is
            // both rolled off a half turn and tilted, and the build script uses this one.
            'rotation_euler_deg' => array_map(self::tidy(...), $this->eulerXYZ()),
        ];
    }

    /**
     * Factors an arbitrary rotation matrix into euler XYZ.
     *
     * Two factorisations always exist, differing by `(a±180, 180−b, c±180)`. The one whose middle angle
     * lands nearest the roll that was asked for is the right one to return: it is what keeps a cabinet
     * rolled 180° reading as `roll 180` in the plan rather than as an equivalent triple nobody wrote.
     *
     * @param array{array{float, float, float}, array{float, float, float}, array{float, float, float}} $m
     * @return array{float, float, float}
     */
    private function eulerFrom(array $m): array
    {
        $hypotenuse = hypot($m[0][0], $m[1][0]);

        if ($hypotenuse < 1e-12) {
            // Euler XYZ's gimbal lock: at a middle angle of ±90° the outer two axes coincide and only
            // their difference is determined. A cabinet on its side with no tilt sits exactly here, so
            // this is a case to answer rather than an edge to guard — all of the turn goes to the yaw.
            return [
                0.0,
                $m[2][0] < 0.0 ? 90.0 : -90.0,
                rad2deg(atan2(-$m[0][1], $m[1][1])),
            ];
        }

        $best = null;
        foreach ([1.0, -1.0] as $branch) {
            $candidate = [
                rad2deg(atan2($branch * $m[2][1], $branch * $m[2][2])),
                rad2deg(atan2(-$m[2][0], $branch * $hypotenuse)),
                rad2deg(atan2($branch * $m[1][0], $branch * $m[0][0])),
            ];
            $distance = abs(fmod($candidate[1] - $this->rollDeg + 540.0, 360.0) - 180.0);
            if ($best === null || $distance < $best[0]) {
                $best = [$distance, $candidate];
            }
        }

        /** @var array{float, array{float, float, float}} $best */
        return $best[1];
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
