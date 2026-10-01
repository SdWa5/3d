<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\LayoutMode;
use App\Scene\LowEndBias;
use App\Scene\MirrorStyle;
use App\Scene\SceneLayout;
use App\Scene\StackOrientation;
use App\Scene\StackShape;
use App\Scene\SystemSplit;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The runnable `scene:stack` line written into every generated scene's header.
 *
 * **The one thing in the pipeline that has to reproduce a single file rather than the sweep it came from.**
 * {@see BuildAllCommand} regenerates the whole set by replaying these lines, so a value this omits is
 * a value the replay invents — and the file it then writes is a different rig under the same name. Every axis is
 * therefore written out explicitly, even the ones the sweep chose rather than the caller.
 *
 * In `src/Command` rather than in `src/Scene` because it needs the command's own option defaults to know what not
 * to write, and those live on the `InputDefinition`. They are passed in as a map rather than read off a command
 * here, which is what keeps this a pure function of its arguments.
 */
final class RecordedCommand
{
    /**
     * The command that produced this scene, written out so the file can be regenerated without being read first.
     *
     * Reconstructed from what the command actually **used**, not echoed from the command line: `--from` is
     * expanded to the resolved device list rather than left implicit, and `--align` names the one mode this file
     * is, not the three that were tried. So the line reproduces this scene specifically, which is the only useful
     * thing it could say — and a default that changes later shows up here instead of being silently inherited.
     *
     * Value options are only emitted when they differ from their default, so an ordinary rig's line stays short
     * enough to read.
     *
     * **`--id` is emitted even though the file's own `id:` is right below it**, because the line has to be runnable
     * rather than merely informative: `build:all` replays it, and without the prefix every scene would regenerate as
     * `stacked-<mode>` and overwrite one file. The prefix is the id less the alignment suffix.
     *
     * @param list<string> $from
     */
    public static function line(
        InputInterface $input,
        LayoutMode $mode,
        StackShape $shape,
        MirrorStyle $style,
        ?StackOrientation $orientation,
        LowEndBias $lowEnd,
        int $stacks,
        string $baseId,
        ?float $maxWidthM,
        array $from,
        SystemSplit $systems,
        string $into,
        array $counts,
        array $defaults,
    ): string {
        $parts = ['bin/console scene:stack'];

        // **THE STATED WIDTH, AND NOTHING WHEN NONE WAS STATED.** `--max-width` is not in the loop below with the
        // other value options because it has no default to compare against any more: the option is either given, in
        // which case the replay has to be given it too or it would rebuild a different rig, or it is absent, in which
        // case writing one out would invent the bound this command just stopped inventing.
        if (null !== $maxWidthM) {
            $parts[] = sprintf('--max-width=%s', rtrim(rtrim(sprintf('%.2f', $maxWidthM), '0'), '.'));
        }

        foreach ([
            'min-width', 'max-height', 'interface-height', 'max-sub-height', 'target-sub-height', 'gap', 'at', 'split',
            'clearance', 'room-width', 'room-height', 'backdrop',
        ] as $option) {
            $value = $input->getOption($option);
            if (null !== $value && (string) $value !== (string) ($defaults[$option] ?? null)) {
                $parts[] = sprintf('--%s=%s', $option, $value);
            }
        }
        // Record the resolved numbers rather than the editable event id.
        foreach (['system-interface', 'system-target', 'system-orientation', 'system-low-end', 'stand'] as $option) {
            foreach ((array) $input->getOption($option) as $value) {
                $parts[] = '--'.$option.'='.$value;
            }
        }
        // **THE MODE, NOT THE CABINETS IT RESOLVED TO.** `--orientation=turned` means "every sub", and writing the
        // resolved list out instead would freeze today's inventory into the file: measure a new sub, or correct one whose
        // height turns out to be under its width, and the replay would rebuild the rig the mode no longer asks for. The
        // stated form is only recorded where it is what the caller actually said.
        if (null !== $orientation) {
            $parts[] = '--orientation='.$orientation->value;
        } else {
            /** @var list<string> $turned */
            $turned = $input->getOption('roll-mirror');
            foreach ($turned as $id) {
                $parts[] = '--roll-mirror='.$id;
            }
        }
        /** @var list<string> $mixes */
        $mixes = $input->getOption('mix');
        foreach ($mixes as $mix) {
            $parts[] = '--mix='.$mix;
        }
        // **THE SEPARATION HAS TO BE WRITTEN OUT, AND IT IS NOT AN OPTION THE SWEEP SET.** SWP-2's axis lives on
        // the rig rather than on the input, so reading `--per-owner` off the input records nothing for a swept
        // `systems-apart` rig — and the replay then rebuilds it pooled, under the separated rig's name, with
        // different geometry and sometimes a different feasibility. Measured before this line existed: 99 of the
        // 976 scenes replayed to a different file, most of them flipping `-possible` to `-impossible`.
        //
        // **`--per-owner` for `systems-apart` and `--systems` for the third value**, which is not inconsistency for
        // its own sake. `--per-owner` is what the 433 separated scenes already record, and emitting `--systems` for
        // them instead would rewrite every one of those files to say the same thing in different words. The value
        // that has no flag says so by name.
        // **ALWAYS WRITTEN OUT, AND NO LONGER AS `--per-owner`.** Every axis narrows now rather than collapsing
        // the sweep, so a replay reproduces one file only by naming one value on every axis — and a separation
        // left unstated would replay as all three of them under one name. `--per-owner` is an alias for one of
        // the three and cannot express the other two, so the value says itself.
        $parts[] = '--systems='.$systems->value;

        foreach (['no-asymmetry'] as $flag) {
            if ($input->getOption($flag)) {
                $parts[] = '--'.$flag;
            }
        }
        // **THE LAYOUT, AND ONLY WHEN IT IS NOT THE DEFAULT.** Every folder-able value except the inventory is
        // already somewhere in this line, so what a replay is missing is not the values but *which axes are
        // folders* — and without it the replay lands in the right directory and rebuilds a name still carrying the
        // value that moved out of it, writing a second file beside the first with neither reported stale.
        // Omitted at the default, which is what keeps a layout change a change and leaves every other file alone.
        /** @var list<string> $folders */
        $folders = $input->getOption('folders');
        $layout = SceneLayout::of($folders);
        if ($layout instanceof SceneLayout && !$layout->isDefault()) {
            $parts[] = '--folders='.$layout->stated();
        }
        // The stated order, for the same reason as the grouping: it decides which wall is where, and a replay
        // without it would rebuild the stacks in height order under the stated order's name.
        /** @var list<string> $order */
        $order = $input->getOption('order');
        foreach ($order as $name) {
            $parts[] = '--order='.$name;
        }
        // **A STATED GROUPING IS RECORDED AND THE DEFAULT ONE IS NOT**, the same rule every value option follows.
        // It has to be recorded at all because the grouping decides how many walls a `systems-apart` rig has: a
        // replay without it would rebuild three systems as four and write that under the three-wall rig's name.
        /** @var list<string> $groups */
        $groups = $input->getOption('group');
        foreach ($groups as $group) {
            $parts[] = '--group='.$group;
        }
        // **THE COUNTS, NOT THE EVENT THAT STATED THEM.** The same argument the `--from` list below is written out
        // on: a replay has to rebuild *this* scene, and an event is a file that can be edited. Recording
        // `--event=next-event` would make every replay of every scene in that folder depend on what the file says
        // today, and a count corrected next week would silently rewrite last week's rigs under their old names. The
        // event stays the human-facing record and the way the folder is generated in the first place; the line that
        // rebuilds one file pins the numbers.
        foreach ($counts as $device => $count) {
            $parts[] = sprintf('--quantity=%s:%d', $device, $count);
        }
        // The swept axes are written out explicitly, because the whole point of the recorded line is that it
        // reproduces THIS scene rather than the sweep it came from.
        foreach ($from as $id) {
            $parts[] = '--from='.$id;
        }
        $parts[] = '--stacks='.$stacks;
        $parts[] = '--align='.$mode->value;
        $parts[] = '--low-end='.$lowEnd->value;
        $parts[] = '--shape='.$shape->value;
        $parts[] = '--mirror-style='.$style->value;
        // **THE SWEPT NAME, not the base `--id`.** The sweep builds a scene's name from the base plus which rig it is
        // — `stacked` + `-gmss-1` — and a replay runs narrowed, so it contributes no suffix of its own. Recording the
        // bare base made every replay write `stacked-center` over the top of one file while the 37 real ones went
        // stale, and the prune then removed them: 742 files, because the derived artifacts went with them.
        $parts[] = '--id='.$baseId;
        // **THE FOLDER, BECAUSE THE LINE CANNOT WORK IT OUT FOR ITSELF.** The inventory is a directory under
        // `scenes/generated/` and this line names cabinets rather than owners — deliberately, so that measuring a
        // new sub does not change what a replay rebuilds. A named rig therefore has no inventory to derive, and
        // without this the whole set would replay into `scenes/generated/` itself, one flat pile with every
        // inventory's identically-named siblings overwriting each other.
        if ('' !== $into) {
            $parts[] = '--into='.$into;
        }

        return implode(' ', $parts);
    }
}
