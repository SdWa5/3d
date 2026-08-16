<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How separately the sound systems stand, which is SWP-2 and the seventh axis of the sweep.
 *
 * **Every generated scene pooled the gear until now, and that was verified rather than assumed**: not one of the
 * written files carried `--per-owner` in its recorded command, because {@see \App\Command\SceneStackCommand::isSweep}
 * treats naming it as collapsing the sweep to a single point. So a rig where two systems stand as two systems could
 * be asked for by hand and never came out of the sweep.
 *
 * The owner asked for three values. Two are here and the third is not, which is a statement about the code rather
 * than about the idea:
 *
 * | value | what stands where |
 * | --- | --- |
 * | `pooled` | every stack gets a share of every cabinet, whoever owns it |
 * | `systems-apart` | each system is its own stack, subs and tops together |
 * | *subs apart, tops shared* | **not buildable yet** — see below |
 *
 * **The missing value breaks an assumption the code holds everywhere: that a stack's tops come from the same pool
 * its subs came from.** {@see \App\Command\SceneStackCommand::groups} returns one id list per stack and the solver
 * builds the whole stack from it, so "these subs, those tops" cannot be expressed at all. The design decision it
 * needs is recorded in TODO under SWP-2 — a second pass that deals the tops after the sub stacks are solved, rather
 * than a second list on the group, because only the second pass can see the sub wall heights the tops row has to
 * sit on.
 *
 * **Adding it later costs no rename**, which is why shipping two of three is not storing up work: `systems-apart`
 * is the longest value and therefore already sets this field's width in every id.
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
     * Whether this value groups the inventory by who owns each cabinet.
     */
    public function isPerOwner(): bool
    {
        return $this === self::SystemsApart;
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
