<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Groups nested inside groups — an arc of tops in two tiers, a lattice of lattices, a row turned over.
 *
 * The stack is itself a {@see Group}, so a placement holds exactly one group whatever its depth and
 * nothing downstream has to know how deep it went. An empty stack is a placement with no group at all:
 * one copy, no path, anchored. Expressing "a single cabinet" as the degenerate stack rather than as a
 * special case is what removes every null check from the compiler.
 *
 * Two things make nesting work, and both live here:
 *
 * * **The cell box grows as you go out.** The innermost group replicates one cabinet; the next one out
 *   replicates whatever the inner one produced. A lattice's spacing is derived from that box, so a lattice
 *   of three-wide fans spaces itself on the fan's 1.49 m and not on one cabinet's 0.5 m.
 * * **A group is a rigid body.** Composition turns the inner arrangement's offsets by the outer turn, so
 *   nesting a group *places* it rather than scattering its parts. See {@see PlacementCopy::nestedIn}.
 */
final class GroupStack implements Group
{
    /**
     * @param list<Group> $groups innermost first — the cell, then whatever it is nested in
     */
    public function __construct(public readonly array $groups = [])
    {
    }

    /**
     * Whether any level of this stack decides which way its cabinets face, which is what makes stating
     * `yaw_deg` as well a contradiction rather than an addition.
     */
    public function decidesYaw(): bool
    {
        foreach ($this->groups as $group) {
            if ($group->decidesYaw()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any level of this stack tilts its cabinets, which makes the aim a decision about the whole
     * arrangement rather than about each cabinet in it.
     */
    public function decidesPitch(): bool
    {
        foreach ($this->groups as $group) {
            if ($group->decidesPitch()) {
                return true;
            }
        }

        return false;
    }

    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $copies = [new PlacementCopy([], [0.0, 0.0, 0.0], null, true)];
        $box = $cellBox;

        foreach ($this->groups as $group) {
            $outer = $group->copies($device, $pitchDeg, $rollDeg, $box);

            $nested = [];
            foreach ($outer as $outerCopy) {
                foreach ($copies as $innerCopy) {
                    $nested[] = $innerCopy->nestedIn($outerCopy);
                }
            }

            $copies = $nested;
            $box = self::boxOf($device, $copies, $pitchDeg, $rollDeg);
        }

        return $copies;
    }

    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $messages = [];
        $box = $cellBox;
        $copies = [new PlacementCopy([], [0.0, 0.0, 0.0], null, true)];

        foreach ($this->groups as $depth => $group) {
            foreach ($group->problems($device, $pitchDeg, $rollDeg, $box) as $problem) {
                // Only the cell is written as a sibling key; everything above it lives in `in`, so a
                // message about level 1 has to say which entry of `in` it means.
                $messages[] = 0 === $depth ? $problem : sprintf('in[%d]: %s', $depth - 1, $problem);
            }
            if ([] !== $messages) {
                // Laying out a group whose numbers do not add up would only produce noise on top of the
                // real message, and the levels above it are measured from what this one produced.
                return $messages;
            }

            $outer = $group->copies($device, $pitchDeg, $rollDeg, $box);
            $nested = [];
            foreach ($outer as $outerCopy) {
                foreach ($copies as $innerCopy) {
                    try {
                        $nested[] = $innerCopy->nestedIn($outerCopy);
                    } catch (UnresolvableRotationException $e) {
                        return [$e->getMessage()];
                    }
                }
            }
            $copies = $nested;
            $box = self::boxOf($device, $copies, $pitchDeg, $rollDeg);
        }

        return $messages;
    }

    public function copyCount(): int
    {
        $count = 1;
        foreach ($this->groups as $group) {
            $count *= $group->copyCount();
        }

        return $count;
    }

    public function kind(): string
    {
        return [] === $this->groups ? 'placement' : $this->groups[0]->kind();
    }

    /**
     * How much room an arrangement takes up, at the attitude its cabinets actually stand at.
     *
     * Measured with {@see PlacedDevice}, which is the one exact rotated-corner computation in the codebase
     * — it already knows a wedge's front corners are only as tall as its front, and that treating a
     * trapezoid as a full-width box overstates a concave arc by 9%. Re-deriving an extent here is exactly
     * how the compiler, the report and the camera framing start disagreeing.
     *
     * The placement's own yaw is deliberately absent: turning a whole placement does not change how its
     * cabinets are spaced within it.
     *
     * @param list<PlacementCopy> $copies
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    private static function boxOf(DeviceSpec $device, array $copies, float $pitchDeg, float $rollDeg): array
    {
        $attitude = new Orientation($pitchDeg, $rollDeg, 0.0);

        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        foreach ($copies as $copy) {
            $box = (new PlacedDevice(
                '',
                $device,
                $copy->offset,
                null === $copy->rotation ? $attitude : ($copy->rotation->after($attitude) ?? $attitude),
                // A flown cell is measured where it hangs. Left seated, a line array nested in a lattice
                // would be sized as if every element stood on the floor, and the lattice would space its
                // cells on a height the hang does not have.
                $copy->seated,
            ))->worldBox();

            for ($axis = 0; $axis < 3; ++$axis) {
                $min[$axis] = min($min[$axis], $box['min'][$axis]);
                $max[$axis] = max($max[$axis], $box['max'][$axis]);
            }
        }

        /** @var array{float, float, float} $min */
        /** @var array{float, float, float} $max */
        return ['min' => $min, 'max' => $max];
    }

    /**
     * One cabinet's own rotated extent — where the innermost group starts from.
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public static function cabinetBox(DeviceSpec $device, float $pitchDeg, float $rollDeg, bool $seated = true): array
    {
        return (new PlacedDevice(
            '',
            $device,
            [0.0, 0.0, 0.0],
            new Orientation($pitchDeg, $rollDeg, 0.0),
            $seated,
        ))->worldBox();
    }

    /**
     * How far a whole arrangement has to be raised so none of it sits below the placement's own base.
     *
     * {@see PlacedDevice::zLift} does this for one cabinet, because rotating a cabinet about its origin
     * drops part of it below zero. A *group* has the same problem one level up and had nobody to solve it:
     * turning a cell over maps its offsets `z → −z`, so a `roll_cycle` on a lattice whose cell is more than
     * one tier tall puts the lower half of that cell underground. Same argument, same fix, one level out.
     *
     * Zero for a hang, which belongs below its anchor by construction.
     *
     * @param list<PlacementCopy> $copies
     */
    public static function zLift(DeviceSpec $device, array $copies, float $pitchDeg, float $rollDeg): float
    {
        $attitude = new Orientation($pitchDeg, $rollDeg, 0.0);

        $lowest = 0.0;
        foreach ($copies as $copy) {
            if (!$copy->seated) {
                return 0.0;
            }

            $lowest = min($lowest, (new PlacedDevice(
                '',
                $device,
                $copy->offset,
                null === $copy->rotation ? $attitude : ($copy->rotation->after($attitude) ?? $attitude),
            ))->worldBox()['min'][2]);
        }

        return -$lowest;
    }
}
