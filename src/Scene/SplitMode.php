<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How a rig's cabinets are shared out between its stacks.
 *
 * One choice, and it decides how **tall** the rig comes out — which is not obvious, so it is worth writing down.
 * A stack's sub height is the sum of its rows, and each device type in it costs at least one row unless the
 * solver can pack several into one ({@see StackSolver::packedRows}). So the thing that makes a stack tall is how
 * many *types* are in it, not how many cabinets.
 *
 * * **{@see ByCount}** gives every stack a share of every device. Two stacks of the same rig, which is what a
 *   stereo pair is, and the only sensible answer when the stacks are meant to match. Its cost is height: with
 *   nine device types and three stacks, every stack holds all nine and is nine rows tall.
 * * **{@see ByType}** gives each stack whole device types instead. The stacks stop matching — they are different
 *   rigs standing side by side — and in exchange each holds two or three types and comes out low. All 41 speakers
 *   both systems own are 3.146 m of subs split by count, and under 2.1 m split by type.
 *
 * Neither is better. A stereo pair wants `ByCount`; a wall that has to fit under a truss wants `ByType`.
 */
enum SplitMode: string
{
    /** A share of every device to every stack. */
    case ByCount = 'by-count';

    /** Whole device types to one stack each, balanced by how much row each type is. */
    case ByType = 'by-type';
}
