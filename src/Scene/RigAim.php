<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * `scene:stack`'s half of aiming a pooled stack's seating check where the finished scene aims it (GEO-11).
 *
 * **A stack is solved before anybody knows where it will stand.** The command solves each block alone at the origin,
 * so the seating check aims its tops row from that block's own front centre. Once the blocks are laid out side by
 * side, the compiler aims every stack without a `focus:` of its own from the **rig's** front centre, which in a
 * two-stack rig lies off to one side of each. The tops then turn further in the scene than in the check, and 0.142.0
 * measured nine pooled rigs whose arrangement stood alone and overlapped once aimed for real.
 * {@see SceneCompiler::expandStacksAimedAtTheRig} re-solves those in the compiler. This re-solves them in the
 * command, so the rows a generated file states in its header are the rows its build produces.
 *
 * **Re-solved from what the file will say rather than from the deal.** The compiler reads each stack's inventory
 * off the written `from:` list, which holds the counts the block placed, so this solves the same counts in the same
 * order. Anything else would be a third opinion about the inventory.
 */
final class RigAim
{
    /** How often a block is re-solved at most to follow a rig centre its own new width moved. */
    private const ROUNDS = 3;

    /** How far a block's aim may stand from the rig's centre and count as aimed from it. */
    private const SAME_CENTRE_M = 1e-3;

    /**
     * The blocks, each re-solved with its seating check aimed from where the rig's centre will be.
     *
     * A block that stands on the rig's centre already keeps its tiers, which is every single-stack rig. A block whose
     * re-solve finds no arrangement keeps its own as well, and the compiler then gives the verdict on it.
     *
     * @param list<StackBlock> $blocks in the order they will be written, left to right
     * @param array<string, DeviceSpec> $devices
     *
     * @return list<StackBlock>
     */
    public static function reaimed(array $blocks, float $centreX, float $clearanceM, array $devices): array
    {
        if (count($blocks) < 2) {
            return $blocks;
        }

        $aimedFrom = array_fill(0, count($blocks), 0.0);
        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $centres = StackSceneWriter::centres($blocks, $centreX, $clearanceM);
            $moved = false;
            foreach ($blocks as $index => $block) {
                // The rig's centre as the block's own probe sees it, standing at the origin.
                $rigX = $centreX - $centres[$index];
                if (abs($rigX - $aimedFrom[$index]) <= self::SAME_CENTRE_M) {
                    continue;
                }
                $aimedFrom[$index] = $rigX;
                $solved = self::solved($block, $rigX, $devices);
                if (null === $solved || $solved->tiers == $block->tiers) {
                    continue;
                }
                $blocks[$index] = $solved;
                $moved = true;
            }
            if (!$moved) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * A stand-in for the placement a block will be written as, for the seating check alone.
     *
     * **Only what changes the stack's own geometry is carried**, which is its id, its `stack` and its alignment. The
     * ground position is not: {@see Interpenetration} compares cabinets against each other, so moving the whole rig
     * moves both sides of every pair and changes no answer. Standing it at the origin also keeps the check
     * independent of `--at`, which is what makes the same arrangement judged the same way wherever it is placed. Where
     * the rig's centre is relative to the block is the one thing that does change an answer, and
     * {@see reaimed} states it through the focus rather than through the position.
     *
     * The rest is the writer's default for a stack: no device of its own, no yaw, pitch or roll, nothing to stand on
     * and no fly. A stack that ever gains one of those has to gain it here too, and the sweep would say so
     * immediately — the command and the compiler would start disagreeing again, which is the failure this exists to
     * prevent.
     */
    public static function probePlacement(string $id, Stack $stack, ?LayoutMode $mode): Placement
    {
        return new Placement(
            id: $id,
            deviceId: null,
            at: [0.0, 0.0],
            yawDeg: 0.0,
            pitchDeg: 0.0,
            rollDeg: 0.0,
            aimAt: null,
            // **THE AIM MATTERS AND LEAVING IT OUT WAS MEASURED WRONG.** A top tier is turned towards the focus, and
            // a turned cabinet's outermost corner moves — so a probe that judged the tops firing straight ahead was
            // measuring a different rig from the one the file states. Left out, the `all` inventory's turned rigs
            // came back with 0.660 m of subs, because nearly every candidate was refused over an overlap that only
            // existed in the probe. {@see StackSceneWriter::focusPoints} is the one definition both sides read.
            aimFocus: StackSceneWriter::AIM,
            on: null,
            fly: null,
            group: new GroupStack([]),
            align: null === $mode || LayoutMode::Center === $mode ? null : new Alignment($mode),
            stack: $stack,
        );
    }

    /**
     * The scene's two foci with their x stated, so a probe at the origin aims at a rig centre somewhere else.
     *
     * @return array<string, Focus>
     */
    public static function focusPointsAt(float $x): array
    {
        return array_map(
            static fn (Focus $focus): Focus => new Focus($focus->distanceM, $focus->heightM, $x),
            StackSceneWriter::focusPoints(),
        );
    }

    /**
     * @param array<string, DeviceSpec> $devices
     */
    private static function solved(StackBlock $block, float $rigX, array $devices): ?StackBlock
    {
        $counts = $block->counts();
        $inventory = [];
        foreach ($block->from as $deviceId) {
            if (($counts[$deviceId] ?? 0) > 0 && isset($devices[$deviceId])) {
                $inventory[] = [$devices[$deviceId], $counts[$deviceId]];
            }
        }

        $solved = StackSolver::solve(
            $inventory,
            $block->stack,
            $block->align,
            SceneCompiler::seatingCheck(
                $devices,
                self::probePlacement($block->placementId, $block->stack, $block->align),
                self::focusPointsAt($rigX),
            ),
        );
        if ([] !== $solved['problems']) {
            return null;
        }

        return new StackBlock(
            placementId: $block->placementId,
            label: $block->label,
            stack: $block->stack,
            tiers: $solved['tiers'],
            from: $block->from,
            warnings: $solved['warnings'],
            omitted: $block->omitted,
            align: $block->align,
        );
    }
}
