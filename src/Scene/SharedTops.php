<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Which sub wall each top stands on, when the tops are one pool rather than one system's own.
 *
 * **This is the second pass SWP-2 asked for**, and the reason it has to be a second pass rather than a second list
 * on the group is that the thing it deals against does not exist until the walls are solved.
 * {@see \App\Command\SceneStackCommand::groups} knows an inventory and a stack count; how much *top face* a wall
 * ends up offering is the solver's answer, since the number of rows and the width of each is what the fill searches
 * for. Deal before that and the deal is made against a guess.
 *
 * **THE RULE, IN ONE SENTENCE: widest top first, each cabinet to the wall with the most unused top face.**
 *
 * Three things follow from it, all of them deliberate:
 *
 * * **The long throw is placed before the fill.** Dealing widest-first means the biggest boxes get the best walls,
 *   which is the same reasoning {@see \App\Command\SceneStackCommand::byType} uses when it gives the biggest type to
 *   the emptiest stack. A fill squeezed onto a narrow wall is an ordinary rig; a long throw squeezed onto one is a
 *   row that cannot be aimed.
 * * **Cabinet by cabinet, so the tops spread rather than pile up.** Each deal spends that wall's budget, so the next
 *   cabinet of the same type goes to the next-emptiest wall. Three Tecnares across three *equal* walls come out one
 *   each instead of three on the widest, which is what {@see \App\Command\SceneStackCommand::inventoryFor} does with
 *   a share and for a similar reason.
 *
 *   **It is not a promise that every wall gets one, and that is deliberate.** A narrow wall beside two wide ones can
 *   come out with none of them: on the `all` rig at one stack per owner, all eight tops went to the sdwa5 and gmss
 *   walls and left Sepp's six Achenbachs as a **sub wing**, because the wide walls still had more unused face than
 *   the 1.22 m one had in total. That is an ordinary thing to build, the solver has always allowed it, and the
 *   alternative is worse: forcing a top onto a wall that cannot carry it means the solver drops the cabinet and it
 *   is in no stack at all, which this repository consistently treats as the worse failure. What a starved wall must
 *   do is **say so**, which {@see StackChecks::boundsProblems} now does — the suite caught it saying nothing.
 * * **Every top is dealt somewhere, even where no wall has room left.** The budget goes negative rather than the
 *   cabinet being held back, because a cabinet in no rig at all is the worse failure and the solver is the backstop:
 *   it re-solves each stack from subs plus its dealt share and reports whatever it cannot carry as `LEFT OUT`.
 *
 * **What this does NOT do is decide the geometry**, and that boundary is worth stating because the budget looks like
 * a geometric claim and is not one. The deal is made on the walls as pass one solved them, and pass two re-solves
 * each stack from a *different* inventory — subs plus tops — so the wall it measured may not be the wall it gets.
 * Bearing, height and interpenetration are all checked afterwards by the same rules every other rig goes through.
 * The budget's job is to spread the tops sensibly, not to prove they fit.
 */
final class SharedTops
{
    /**
     * The pooled tops dealt across the solved sub walls.
     *
     * @param list<StackBlock> $walls the sub stacks as pass one solved them
     * @param array<string, int> $pool device id => how many of it the rig holds
     * @param array<string, DeviceSpec> $devices
     * @return array<string, array<string, int>> wall label => device id => cabinets dealt to it
     */
    public static function deal(array $walls, array $pool, array $devices): array
    {
        if ($walls === [] || $pool === []) {
            return [];
        }

        // Widest first. Ties keep the order the pool arrived in, which is the fill order `everySpeaker` put the tops
        // in, so two tops of one width are dealt in a stated order rather than an accidental one.
        $order = array_keys($pool);
        usort(
            $order,
            static fn (string $a, string $b): int => RolledBox::widthOf($devices[$b], 0.0)
                <=> RolledBox::widthOf($devices[$a], 0.0),
        );

        $budget = [];
        foreach ($walls as $wall) {
            $budget[$wall->label] = $wall->topFaceWidthM();
        }

        $dealt = [];
        foreach ($order as $id) {
            $width = RolledBox::widthOf($devices[$id], 0.0);
            for ($cabinet = 0; $cabinet < $pool[$id]; ++$cabinet) {
                $label = self::emptiest($budget);
                $dealt[$label][$id] = ($dealt[$label][$id] ?? 0) + 1;
                // The working gap is not subtracted, deliberately. A tops row's spacing is solved rather than
                // stacked up out of widths — see {@see \App\Scene\Alignment} — so a budget that charged a gap per
                // cabinet would be pretending to a precision it has not got. What it has to be is monotonic, and
                // spending the cabinet's own width is that.
                $budget[$label] -= $width;
            }
        }

        return $dealt;
    }

    /**
     * The wall with the most unused top face, earliest first on a tie.
     *
     * Written as a loop taking a strict improvement rather than as `array_search(max(...))`, so the tie goes to the
     * wall that comes first in the rig. That ordering is the one thing here that has to be stable: the sweep writes
     * a file per candidate and two runs that dealt a tie differently would produce two files for one rig.
     *
     * @param array<string, float> $budget
     */
    private static function emptiest(array $budget): string
    {
        $best = null;
        $most = -INF;
        foreach ($budget as $label => $left) {
            if ($left > $most) {
                $most = $left;
                $best = $label;
            }
        }

        return (string)$best;
    }
}
