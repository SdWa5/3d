<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How separately the sound systems stand, which is SWP-2 and the seventh axis of the sweep.
 *
 * **Every generated scene pooled the gear until 0.91.0, and that was verified rather than assumed**: not one of the
 * written files carried `--per-owner` in its recorded command, because {@see \App\Command\SceneStackCommand::isSweep}
 * treats naming it as collapsing the sweep to a single point. So a rig where two systems stand as two systems could
 * be asked for by hand and never came out of the sweep.
 *
 * The owner asked for three values and all three are here:
 *
 * | value | what stands where |
 * | --- | --- |
 * | `pooled` | every stack gets a share of every cabinet, whoever owns it |
 * | `systems-apart` | each system is its own stack, subs and tops together |
 * | `tops-shared` | each system's **subs** are its own stack, and the tops are one pool dealt across those walls |
 *
 * **What `tops-shared` shares is the pool and not the row, and that distinction was settled by measuring.** The
 * tempting reading is one tops row bridging two sub walls, and it cannot be built: in the `systems-apart` scenes the
 * three walls come out **2.31 / 2.383 / 1.8 m** high, and in the upright variant **2.44 / 3.61 / 1.8**. Two walls
 * drawn from two different inventories do not come out level, and {@see Stack} says why in its own docblock — our
 * cabinet heights are 0.600 / 0.763 / 0.836 / 0.914 / 0.960 m with no common module between them. A row resting on
 * both would hang in the air over the lower one, which no solver can fix. A row that really does bridge two walls
 * belongs to a **mirrored pair out of one pool**, whose walls are identical by construction, and that is SYM-3.
 *
 * **It cost no rename**, which is why shipping two of three first was not storing up work: `systems-apart` is the
 * longest value and already set this field's width in every id.
 */
enum SystemSplit: string
{
    /** One pool, dealt across the stacks. Every generated scene was this and nothing else until 0.91.0. */
    case Pooled = 'pooled';

    /**
     * One group per owner, each dealt across the stacks in turn — so `--stacks=1` puts each system in its own
     * stack, side by side, and `--stacks=2` gives each system a stereo pair.
     *
     * **A single-owner rig cannot tell this from `pooled`**, which is not a special case but the definition doing
     * its job: one system separated from nothing is one pool. The deduplication collapses those rather than any
     * rule here having to know about them.
     */
    case SystemsApart = 'systems-apart';

    /**
     * Each system's **subs** in their own stack, with every top in the rig dealt from one pool across those walls.
     *
     * **This is the rig neither other value can express**, and on the gear we own it is not a subtle difference:
     * there are exactly three top types and one belongs to each owner — three Tecnares to `sdwa5`, two 2-ways to
     * `sepp`, three turbo tops to `gmss`. So `pooled` mixes everything, `systems-apart` puts each owner's tops back
     * on that owner's own subs, and this is the only one of the three that can stand our Tecnares on Sepp's
     * Achenbach wall. Borrowing tops across systems is the normal shape of a shared gig and the repository supports
     * lending gear on purpose.
     *
     * **The deal is a second pass over solved walls, not a second list on the group.** Only a pass that runs after
     * the sub stacks are solved can see how much top face each wall actually offers, because the number of rows a
     * wall comes out with is the solver's decision rather than the inventory's. See {@see SharedTops} for the rule
     * and for what it deliberately leaves to the solver.
     */
    case TopsShared = 'tops-shared';

    /**
     * Whether this value groups the inventory by who owns each cabinet.
     *
     * **Both separated values do**, and that is the point of asking it as a question rather than comparing cases:
     * `tops-shared` groups the subs by owner exactly as `systems-apart` groups everything, and the two differ only
     * in what happens to the tops afterwards. {@see sharesTops} is the half that differs.
     */
    public function isPerOwner(): bool
    {
        return $this !== self::Pooled;
    }

    /**
     * Whether the tops come out of the owner groups and are dealt from one pool instead.
     */
    public function sharesTops(): bool
    {
        return $this === self::TopsShared;
    }

    /**
     * The values worth sweeping for a rig drawn from this many owners.
     *
     * **Separation means nothing below two owners**, so a single-owner rig is offered `pooled` alone. Left to the
     * deduplication instead, every single-owner rig would be solved twice to produce one file — 153 of today's 543
     * scenes are single-owner, so that is a third of the sweep's work spent proving that one system standing apart
     * from itself is one system.
     *
     * @return list<self>
     */
    public static function forOwnerCount(int $owners): array
    {
        return $owners > 1 ? self::cases() : [self::Pooled];
    }
}
