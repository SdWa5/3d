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
        /** Null only on a `stack`, which names its cabinets in `stack.from` and is expanded before use. */
        public readonly ?string $deviceId,
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
        /** How this tier is distributed across a stated width; null is the natural spacing. */
        public readonly ?Alignment $align = null,
        /** A rig stated as constraints instead of tiers; expanded into real placements before compiling. */
        public readonly ?Stack $stack = null,
        /**
         * This placement's own focus points, which override the scene's for everything inside it.
         *
         * **A SCENE-WIDE FOCUS AIMS EVERY CLUSTER AT ONE POINT, AND THAT IS WRONG THE MOMENT TWO SOUND SYSTEMS
         * STAND SIDE BY SIDE.** `Focus::point()` measures from the *rig's* front centre, so in a `systems-apart`
         * rig the outer walls toe inward at a point in front of the middle one — three systems covering one spot
         * instead of three systems each covering the room in front of it. Stated here, the distance and height
         * mean the same thing they always did and are measured from **this** placement's own front face.
         *
         * Null is the scene's own focus, which is what every hand-written scene wants: a near-fill beside a main
         * cluster is part of that cluster and aims where it aims.
         *
         * @var array<string, Focus>
         */
        public readonly array $focusByName = [],
    ) {
    }

    /**
     * This placement's own `focus:` block, read exactly the way the scene's own is.
     *
     * Absent is the common case and returns nothing, which leaves {@see \App\Scene\SceneSpec::$focusByName} in
     * charge. The two forms {@see \App\Scene\SceneSpec::readFoci} accepts are both accepted here — one unnamed
     * focus, or a map of named ones — because a placement that states a focus is stating the same kind of thing
     * the scene does, one level down.
     *
     * @return array<string, Focus>
     */
    private static function focusIn(ArrayReader $reader): array
    {
        $section = $reader->optionalSection('focus');
        if ($section === null) {
            return [];
        }

        $names = $section->keys();
        $named = $names !== [] && array_reduce(
            $names,
            static fn (bool $carry, string $key): bool => $carry && $section->isSection($key),
            true,
        );

        if (!$named) {
            return ['focus' => Focus::fromReader($section)];
        }

        $foci = [];
        foreach ($names as $name) {
            $foci[$name] = Focus::fromReader($section->requireSection($name));
        }

        return $foci;
    }

    /** Everything a placement may say that is not a group. {@see GroupReader::keys} supplies the rest. */
    private const KEYS = [
        'id', 'device', 'at', 'yaw_deg', 'pitch_deg', 'roll_deg',
        'aim_at', 'aim', 'on', 'fly', 'aim_lines', 'align', 'stack', 'focus',
    ];

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        // A placement used to accept anything, which meant a mistyped key was simply not there: `algn:`
        // would read as "not aligned", `aim_lies:` as "follow the scene mode", and both would render
        // perfectly plausibly with nothing to see. It is the same argument `arc` and `lattice` already
        // make for their own keys — a block whose every field changes the geometry cannot afford a typo.
        $unknown = $reader->unknownKeys([...self::KEYS, ...GroupReader::keys()]);
        if ($unknown !== []) {
            throw new \App\Spec\InvalidSpecException(sprintf(
                "placement '%s': unknown key '%s'",
                $reader->optionalString('id') ?? 'placement-'.$index,
                $unknown[0],
            ));
        }

        $stack = $reader->optionalSection('stack');
        if ($stack !== null) {
            // A stack names its cabinets in `stack.from` and writes its own rows, so `device` and a group
            // are not merely redundant here — either would have to lose an argument with the solver.
            foreach ([...GroupReader::keys(), 'device'] as $key) {
                if ($reader->has($key)) {
                    throw new \App\Spec\InvalidSpecException(
                        "stack: `{$key}` is decided by the stack — remove it, or write the tiers out by hand",
                    );
                }
            }
        }

        return new self(
            id: $reader->optionalString('id') ?? 'placement-'.$index,
            deviceId: $stack === null ? $reader->requireString('device') : null,
            at: $reader->has('at') ? self::readGround($reader) : null,
            yawDeg: $reader->optionalFloat('yaw_deg', 0.0) ?? 0.0,
            pitchDeg: $reader->optionalFloat('pitch_deg', 0.0) ?? 0.0,
            rollDeg: $reader->optionalFloat('roll_deg', 0.0) ?? 0.0,
            aimAt: $reader->has('aim_at') ? self::readAim($reader) : null,
            aimFocus: $reader->optionalString('aim'),
            focusByName: self::focusIn($reader),
            on: $reader->optionalString('on'),
            fly: ($fly = $reader->optionalSection('fly')) === null ? null : Fly::fromReader($fly),
            group: GroupReader::read($reader),
            aimLines: $reader->has('aim_lines') ? $reader->requireBool('aim_lines') : null,
            align: ($align = $reader->optionalSection('align')) === null ? null : Alignment::fromReader($align),
            stack: $stack === null ? null : Stack::fromReader($stack),
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
