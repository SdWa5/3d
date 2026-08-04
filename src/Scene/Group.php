<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One way of making copies of a cabinet: a plain repeat, an arc, a lattice, a row.
 *
 * Before this existed there were exactly two ways and they were mutually exclusive, both expanded in one
 * method in the compiler. That is fine while "a placement is one group" holds, and it stops being fine the
 * moment the *cell* of an arrangement is itself an arrangement — an arc of tops repeated in two tiers, a
 * lattice of lattices — because then the same expansion has to happen at every level and the levels have
 * to multiply.
 *
 * So a group is a rigid body. Given a cabinet and the attitude that cabinet stands at, it says where its
 * copies sit relative to a single point and how each is turned relative to the group. It knows nothing
 * about where that point is in the world, nothing about aiming, and nothing about the group outside it.
 * That is what lets {@see GroupStack} nest any of them inside any other.
 */
interface Group
{
    /**
     * Where each copy sits and how it is turned, relative to the group's own anchor point.
     *
     * `$pitchDeg` and `$rollDeg` are the attitude the *cabinets* stand at, which several groups need: an
     * arc solves contact on the tilted, rolled plan outline, and a lattice has to know how big a turned
     * cabinet actually is before it can space itself.
     *
     * `$cellBox` is the extent of the arrangement this group is replicating — one cabinet's own rotated
     * box for the innermost group, and the union of the whole inner arrangement's boxes for anything above
     * it. It is the one thing a group cannot work out for itself, because it depends on what was nested
     * inside it, so the stack hands it down. Groups whose spacing is stated outright ignore it.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return list<PlacementCopy>
     */
    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array;

    /**
     * Everything wrong with this group for this cabinet, as messages that name their own keys
     * (`arc.count`, `lattice.step_m`) but not the placement. The compiler adds the
     * `placement '<id>': ` prefix.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return list<string>
     */
    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array;

    /**
     * How many copies this group makes of whatever is inside it. Needed before `copies()` runs, because
     * the rule that only a group gets numbered ids is decided from the total.
     */
    public function copyCount(): int;

    /** The YAML key this group is written under, for error messages. */
    public function kind(): string;

    /**
     * Whether this group decides which way its cabinets face.
     *
     * An arc does: the fan's geometry *is* the yaw, which is why stating `yaw_deg` alongside it is a
     * contradiction rather than something to add on. A repeat and a lattice do not — they move copies
     * without turning them, so a lattice plus `yaw_deg` is a whole wall turned, which is legitimate.
     */
    public function decidesYaw(): bool;

    /**
     * Whether this group decides how far its cabinets are tilted.
     *
     * A {@see LineArray} does: it is one rigid hang, so every element shares the hang's own attitude and
     * differs only by the splay accumulated down to it. That makes the aim a decision about the *hang*
     * rather than about each element — resolved once, at the anchor. Resolving it per element instead lets
     * each one turn towards the target on its own and cancels the splay out, which is the difference
     * between a J array and four cabinets all pointing at the same spot.
     *
     * An arc, a lattice and a repeat do not: they move cabinets without tilting them, so each one aims for
     * itself.
     */
    public function decidesPitch(): bool;
}
