<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * One cabinet produced by a placement — the shared currency of every {@see Group}.
 *
 * Before this existed, expansion was written out twice in the compiler (once to place cabinets, once to
 * find the rig's front face) and the two had to be kept in step by hand. Both now walk the same list.
 *
 * `rotation` is the groups' whole contribution to how this cabinet is turned, already composed across
 * however many levels it is nested in. Null means the groups decided nothing and the placement's own
 * angles or its aim decide — which is what a plain `repeat` produces, since a step vector leaves every
 * copy pointing the same way. It is one rotation rather than three loose angles because composition is
 * matrix multiplication: rolling a group over reverses the yaw of everything inside it, and no amount of
 * adding angles up expresses that.
 *
 * `path` is this copy's position in the nest, outermost first, and only counts the dimensions that have
 * more than one value — so a row of seven is `[1] … [7]` exactly as it always was, and two tiers of that
 * row are `[1, 1] … [2, 7]`. It is what a cabinet's id is built from.
 *
 * `isAnchor` marks the copy that `on:` stacks onto and that ground inheritance reads. Every group promises
 * exactly one, so nesting needs no arbitration: the anchor of the whole is the copy that is its level's
 * anchor at every level. Which copy that is belongs to the group — the last of a `repeat`, matching what
 * scenes already rely on, but the *middle* of an arc or a row, which is the one sitting on the stated `at`.
 */
final class PlacementCopy
{
    /**
     * `pitchIncrementDeg` is a change to the cabinet's *own* down-tilt rather than a turn of its cell, and
     * it needs its own channel for a reason worth stating: composing it as a rotation would put it outside
     * the placement's yaw, and `Rx(σ)·Rz(ψ)·Rx(θ)` is not `Rz(ψ)·Rx(θ + σ)`. A {@see LineArray}'s splay is
     * the second thing — every element of a hang shares the hang's yaw and differs only in how far it is
     * tilted — so it adds to the pitch and lets the aim, the roll and the yaw stay where they are.
     *
     * `seated` is false for a cabinet that does not rest on the floor. {@see PlacedDevice::zLift} exists so
     * a tilted or upside-down cabinet still sits on its slot, which is right for anything standing on
     * something and wrong for a flown one: each element of an array is tilted differently, so lifting each
     * back onto its own slot would pull the array apart at every joint.
     *
     * @param list<int> $path 1-based index at each nesting level that has more than one value
     * @param array{float, float, float} $offset from the placement's resolved base position
     */
    public function __construct(
        public readonly array $path,
        public readonly array $offset,
        public readonly ?Orientation $rotation,
        public readonly bool $isAnchor,
        public readonly float $pitchIncrementDeg = 0.0,
        public readonly bool $seated = true,
    ) {
    }

    /**
     * The suffix this copy adds to its placement's id, empty when the placement makes only one cabinet.
     */
    public function idSuffix(): string
    {
        return [] === $this->path ? '' : '-'.implode('-', $this->path);
    }

    /**
     * This copy with its offset swung about Z, for a group whose arrangement has to follow where the
     * placement points rather than lying along the world's axes.
     *
     * Only the offset moves. The rotation is untouched because the yaw being turned by is the placement's
     * own, which {@see SceneCompiler::orientationFor} already gives every copy — turning it here as well
     * would apply it twice.
     *
     * The distinction this draws is between a group that *arranges* cabinets and one that *is* a body. A
     * yawed `row` is a straight line of toed-in cabinets, which is what a toed-in sub wall is, and swinging
     * its step would be wrong. A {@see LineArray} is a frame: aim the hang 11° off-axis and the whole frame
     * turns with it, elements and joints together.
     */
    public function yawedBy(float $yawDeg): self
    {
        if (0.0 === $yawDeg) {
            return $this;
        }

        $turned = (new Orientation(0.0, 0.0, $yawDeg))->apply($this->offset);

        return new self(
            $this->path,
            [self::snap($turned[0]), self::snap($turned[1]), self::snap($turned[2])],
            $this->rotation,
            $this->isAnchor,
            $this->pitchIncrementDeg,
            $this->seated,
        );
    }

    /**
     * This copy slid along x, for an {@see Alignment} spreading a tier across a stated width.
     *
     * Only x moves and nothing turns: how far a cabinet stands from `at` is a question about the tier's
     * distribution, while which way it points is still the aim's to answer — and it is answered afterwards,
     * against the position this produces. That ordering is the fixed point, and keeping the two separate is
     * what lets one scalar drive the whole solve.
     */
    public function movedInX(float $x): self
    {
        return new self(
            $this->path,
            [self::snap($x), $this->offset[1], $this->offset[2]],
            $this->rotation,
            $this->isAnchor,
            $this->pitchIncrementDeg,
            $this->seated,
        );
    }

    /**
     * This copy slid along y, for a stack standing flush at the front once its tops are aimed.
     *
     * The same contract as {@see movedInX}: only y moves, and the aim is answered afterwards against the position
     * this produces. See {@see SceneCompiler::flushFront}.
     */
    public function movedInY(float $y): self
    {
        return new self(
            $this->path,
            [$this->offset[0], self::snap($y), $this->offset[2]],
            $this->rotation,
            $this->isAnchor,
            $this->pitchIncrementDeg,
            $this->seated,
        );
    }

    /**
     * This copy nested inside `$outer` — `$outer` places it, so `$outer`'s turn applies to this one's
     * offset as well as to its rotation.
     *
     * Turning the offset is not optional. A group is a rigid body, and nesting one places a rigid body
     * rather than scattering its parts: put a touching pair inside a convex arc of Tecnares and the outer
     * seats sit at ±17.35°, so the pair's 0.52 m step has to run along the *cabinet's* x. Left unturned it
     * runs along the world's, and the second cabinet of each pair lands 155 mm behind its own seam.
     */
    public function nestedIn(self $outer): self
    {
        $turned = $outer->rotation?->apply($this->offset) ?? $this->offset;

        return new self(
            [...$outer->path, ...$this->path],
            [
                self::snap($outer->offset[0] + $turned[0]),
                self::snap($outer->offset[1] + $turned[1]),
                self::snap($outer->offset[2] + $turned[2]),
            ],
            self::compose($outer->rotation, $this->rotation),
            $outer->isAnchor && $this->isAnchor,
            // Tilts add: they are all changes to the same cabinet's own down-tilt, whatever level asked
            // for them. And a cabinet is only seated if nothing anywhere in the nest has flown it.
            $outer->pitchIncrementDeg + $this->pitchIncrementDeg,
            $outer->seated && $this->seated,
        );
    }

    /**
     * Rounds a composed offset to the nearest picometre.
     *
     * Turning a group by half a turn should leave its cabinets' heights alone, and very nearly does:
     * `sin(180°)` is 1.2e-16 rather than zero in binary, so a rolled row picks up about 1e-16 m of z per
     * cabinet. Physically that is nothing. In a repository whose point is that a setup is a commit it is a
     * seventeen-digit number in a build plan that should read `0`, so it is rounded off at a scale a
     * thousand times finer than anything a cabinet is measured to — the same grid {@see Outline} snaps to,
     * and for the same reason.
     */
    private static function snap(float $value): float
    {
        return round($value / 1e-12) * 1e-12 + 0.0;
    }

    /**
     * `$outer` applied after `$inner`, with either side possibly having nothing to say.
     *
     * Returns null only when both are null. A composition that cannot be split back into angles — a
     * cabinet pitched exactly on end — throws, because there is no sensible copy to place and the
     * compiler has to name the placement it came from.
     */
    private static function compose(?Orientation $outer, ?Orientation $inner): ?Orientation
    {
        if (null === $outer) {
            return $inner;
        }
        if (null === $inner) {
            return $outer;
        }

        return $outer->after($inner) ?? throw new UnresolvableRotationException('a group turns a cabinet onto its end, where its roll and its yaw become the same turn');
    }
}
