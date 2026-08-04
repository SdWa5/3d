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
 * `fly` is the third way to say where a placement's base is: `at` is a ground position, `on` is the top of
 * an earlier placement, and `fly` is a point in the air — which is the only one a hang can use, since a
 * {@see LineArray}'s elements sit *below* their base. See {@see Fly}.
 *
 * `on` and a group are what make a scene file worth writing instead of dragging cabinets around by hand.
 * A 14-cabinet sub wall is two lines, and a stack does not have to be re-measured every time a spec's
 * height changes — the compiler works the heights out from the specs.
 *
 * There are four ways to make copies and a placement uses one of them: `repeat` steps along a stated
 * vector, `arc` seats a fan whose angle comes from the cabinet's own taper, and `lattice` and its
 * one-dimensional shorthand `row` space a grid from the size of whatever they replicate. Any of them
 * nests inside any other with `in`. See {@see GroupReader} for the shape and {@see GroupStack} for what
 * nesting means.
 */
final class Placement
{
    /**
     * @param array{float, float}|null $at ground position; null means "inherit from `on`"
     * @param array{float, float, float}|null $aimAt point to face, instead of stating yaw and pitch
     */
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly ?array $at,
        public readonly float $yawDeg,
        public readonly float $pitchDeg,
        public readonly float $rollDeg,
        public readonly ?array $aimAt,
        public readonly ?string $aimFocus,
        public readonly ?string $on,
        /** Hung from a point in the air, instead of `at`'s floor or `on`'s cabinet top. */
        public readonly ?Fly $fly,
        public readonly GroupStack $group,
        /** true or false to overrule the scene's aim-line mode for this group; null to follow it. */
        public readonly ?bool $aimLines = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        return new self(
            id: $reader->optionalString('id') ?? 'placement-'.$index,
            deviceId: $reader->requireString('device'),
            at: $reader->has('at') ? self::readGround($reader) : null,
            yawDeg: $reader->optionalFloat('yaw_deg', 0.0) ?? 0.0,
            pitchDeg: $reader->optionalFloat('pitch_deg', 0.0) ?? 0.0,
            rollDeg: $reader->optionalFloat('roll_deg', 0.0) ?? 0.0,
            aimAt: $reader->has('aim_at') ? self::readAim($reader) : null,
            aimFocus: $reader->optionalString('aim'),
            on: $reader->optionalString('on'),
            fly: ($fly = $reader->optionalSection('fly')) === null ? null : Fly::fromReader($fly),
            group: GroupReader::read($reader),
            aimLines: $reader->has('aim_lines') ? $reader->requireBool('aim_lines') : null,
        );
    }

    /**
     * How many cabinets this placement produces, so the rule that only a group gets numbered ids lives
     * in one place.
     */
    public function copyCount(): int
    {
        return $this->group->copyCount();
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
