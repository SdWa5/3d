<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * What silhouette a solved stack is allowed to have.
 *
 * The bearing rule permits a row to be *wider* than the one carrying it — two thirds of a cabinet past each end,
 * derived in {@see StackSolver::OVERHANG_PER_SIDE} — and for a long time nothing said it should not be. That is how
 * five of the twelve generated stacks came out with their rows widening as they rose: 1.34 m on the floor under
 * 1.82, 1.84, 2.32, 2.18 and 2.51 m. Every one of those rows is legally carried and the rig reads top-heavy, a V
 * balanced on its point rather than a wall.
 *
 * * **{@see Pyramid}** — no row wider than the row below it. What a crew builds, and what makes a stack *shorter*
 *   as well as better-looking: the taper only pays off if the bottom row is packed as wide as the stage allows, and
 *   a wide base is exactly what needs fewer rows above it.
 * * **{@see Free}** — as wide as the bearing rule allows, which is what the solver did before this existed. Kept
 *   because it is occasionally the only arrangement that stands up at all: a stack whose deepest cabinets are few
 *   and narrow has no wide base available, and forbidding growth there refuses the rig rather than improving it.
 * * **{@see V}** — the pyramid's mirror: no row *narrower* than the row below, and the fill ordered so there is room
 *   to grow. A real rig rather than an accident, which is the whole reason it is a value of its own — it throws the top
 *   boxes wider apart for coverage and keeps the weight low and central.
 *
 * **`free` is not a V and that is why this case exists.** `free` *permits* a rig to widen and never asks for it, so
 * the five stacks named above widened by accident; two of them would have been flush had the fill dealt their rows
 * differently. A shape somebody chose and a shape that fell out are different answers even when they measure the same.
 *
 * All five are offered by default. Arrangements with identical placed geometry are written once.
 */
enum StackShape: string
{
    /** Rows non-increasing in width from the floor up. */
    case Pyramid = 'pyramid';

    /** Rows as wide as {@see Gravity::MIN_BEARING} allows, growing upward if that is what fits. */
    case Free = 'free';

    /** Rows non-decreasing in width from the floor up — the pyramid read upside down. */
    case V = 'v';

    /** Sub rows retain their base width within the shoulder allowance. */
    case Tower = 'tower';

    /** The lower half of the sub rows retains its width; the upper half tapers. */
    case Mixed = 'mixed';

    /** How many bottom sub rows must form a flush base. */
    public function flatRows(int $subRows): int
    {
        return match ($this) {
            self::Tower => $subRows,
            self::Mixed => (int) ceil($subRows / 2),
            default => 0,
        };
    }
}
