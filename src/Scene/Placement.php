<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;

/**
 * One entry in a scene: a device put somewhere, optionally on top of another entry, optionally
 * repeated along a step vector.
 *
 * `pitch_deg` tilts a cabinet nose-down, which is how flown or stacked tops are aimed into an audience
 * instead of over their heads. `roll_deg` turns it over about its own front-to-back axis, which is how
 * horn-loaded subs get stacked in mirrored pairs so two mouths meet and act as one larger one; 180
 * leaves it facing forward, just upside down.
 *
 * `aim: focus` is the easier way to say the same thing for a cluster: every top turns towards the
 * scene's focus point and the compiler works out each cabinet's own yaw and down-tilt. That is what
 * "all the tops point at the middle of the dancefloor" means in practice, and it stays right when a
 * cabinet moves. `aim_at: [x, y, z]` names a point outright instead.
 *
 * `on` and `repeat` are what make a scene file worth writing instead of dragging cabinets around by
 * hand. A 14-cabinet sub wall is two lines, and a stack does not have to be re-measured every time
 * a spec's height changes — the compiler works the heights out from the specs.
 *
 * `arc` is the other way to make copies: a group seated on a circular arc, which is what a cluster of
 * tops actually is. Where `repeat` steps along a vector and leaves the cabinets pointing the same way,
 * an arc works out each cabinet's own yaw, so the two are alternatives rather than companions. An arc
 * combines with `aim: focus` — the arc owns the yaw, the focus owns the down-tilt. See {@see Arc}.
 */
final class Placement
{
    /**
     * @param array{float, float}|null $at ground position; null means "inherit from `on`"
     * @param array{float, float, float}|null $aimAt point to face, instead of stating yaw and pitch
     * @param array{float, float, float}|null $repeatStep offset between repeats
     */
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly ?array $at,
        public readonly float $yawDeg,
        public readonly float $pitchDeg,
        public readonly float $rollDeg,
        public readonly ?array $aimAt,
        public readonly bool $aimAtFocus,
        public readonly ?string $on,
        public readonly int $repeatCount,
        public readonly ?array $repeatStep,
        public readonly ?Arc $arc = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $repeat = $reader->optionalSection('repeat');

        return new self(
            id: $reader->optionalString('id') ?? 'placement-'.$index,
            deviceId: $reader->requireString('device'),
            at: $reader->has('at') ? self::readGround($reader) : null,
            yawDeg: $reader->optionalFloat('yaw_deg', 0.0) ?? 0.0,
            pitchDeg: $reader->optionalFloat('pitch_deg', 0.0) ?? 0.0,
            rollDeg: $reader->optionalFloat('roll_deg', 0.0) ?? 0.0,
            aimAt: $reader->has('aim_at') ? self::readAim($reader) : null,
            aimAtFocus: ($reader->optionalString('aim') ?? '') === 'focus',
            on: $reader->optionalString('on'),
            repeatCount: $repeat?->optionalInt('count', 1) ?? 1,
            repeatStep: $repeat !== null && $repeat->has('step') ? $repeat->requireVector3('step') : null,
            arc: ($arc = $reader->optionalSection('arc')) === null ? null : Arc::fromReader($arc),
        );
    }

    /**
     * How many cabinets this placement produces, so the rule that only a group gets numbered ids lives
     * in one place.
     */
    public function copyCount(): int
    {
        return $this->arc?->count ?? $this->repeatCount;
    }

    /**
     * A point to aim at. Two numbers aim horizontally only; three also set the down-tilt.
     *
     * @return array{float, float, float}
     */
    private static function readAim(ArrayReader $reader): array
    {
        $raw = $reader->numberList('aim_at');
        if (count($raw) === 2) {
            return [$raw[0], $raw[1], 0.0];
        }
        if (count($raw) === 3) {
            return [$raw[0], $raw[1], $raw[2]];
        }

        throw new \App\Spec\InvalidSpecException('aim_at: expected [x, y] or [x, y, z]');
    }

    /**
     * `at` is the ground position: two numbers, because the height comes from the floor or from
     * whatever the device sits on. A third number is accepted and ignored on purpose — writing
     * `[x, y, 0]` is a natural mistake and rejecting it would be pedantry.
     *
     * @return array{float, float}
     */
    private static function readGround(ArrayReader $reader): array
    {
        $raw = $reader->numberList('at');
        if (count($raw) < 2) {
            throw new \App\Spec\InvalidSpecException('at: expected [x, y]');
        }

        return [$raw[0], $raw[1]];
    }
}
