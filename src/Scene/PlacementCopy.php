<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * One cabinet produced by a placement — the shared currency of `repeat` and `arc`.
 *
 * Before this existed, expansion was written out twice in the compiler (once to place cabinets, once to
 * find the rig's front face) and the two had to be kept in step by hand. Both now walk the same list.
 *
 * `yawDeg` is null for a plain repeat, meaning "the placement's own yaw or its aim decides"; an arc sets
 * it, because the arc's geometry *is* the yaw. `isAnchor` marks the copy that `on:` stacks onto and that
 * ground inheritance reads — the last copy of a repeat, matching the behaviour scenes already rely on,
 * but the *middle* cabinet of an arc, which is the one sitting on the stated `at`.
 */
final class PlacementCopy
{
    /**
     * @param array{float, float, float} $offset from the placement's resolved base position
     */
    public function __construct(
        public readonly int $index,
        public readonly array $offset,
        public readonly ?float $yawDeg,
        public readonly bool $isAnchor,
    ) {
    }
}
