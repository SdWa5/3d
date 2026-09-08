<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;
use App\Spec\FillOrder;

/**
 * The axes the sweep walks, and what each of them means when nobody names a value.
 *
 * Seven questions get asked of every rig — whose gear, how many stacks, how separately the systems stand, which
 * alignment, which shape, which cabinets lie down, and what a rolled row does with its odd cabinet — and this is where
 * six of them are turned from what somebody typed into what gets built. The seventh, the stack count, is a literal
 * `[1, 2, 3]` and has nothing to decide.
 *
 * **Why this is not in the command.** `SceneStackCommand` grew past 1900 lines and the parsing was 250 of them, but
 * size was the symptom rather than the reason: none of this touches the solve. An axis is a fact about *what the sweep
 * covers*, so it can be reasoned about — and tested — without a rig, an inventory or a console. What is left in the
 * command is the part that genuinely needs all three.
 *
 * **Errors come back as a string rather than being printed.** Each parser returns its values or the message naming what
 * was misspelled and what was allowed, and the caller decides what to do with it. That is the whole of the seam: the
 * rule "a misspelled value is refused and the alternatives are named" belongs with the axis, and "an error is red text
 * on stderr" belongs with the command.
 *
 * Two axes are **not independent** and are enumerated as pairs rather than multiplied out — see {@see pairs}, which is
 * the one piece of judgement in this file.
 */
final class SweepAxes
{
    /**
     * The inventory a bare `scene:stack` builds from: **our own gear and Sepp's, pooled as one rig.**.
     *
     * Stated by the owner, and it is a statement about how this repository is used rather than a default nobody
     * thought about. The two systems travel together, they are what stands on a stage when we play, and every other
     * inventory in the sweep is a specific question somebody asks with `--owner`.
     *
     * Names rather than a count, so adding a fifth owner's specs cannot silently change what the default sweep
     * builds — which is exactly what the powerset this replaced did.
     */
    public const DEFAULT_OWNERS = ['sdwa5', 'sepp'];

    /**
     * What the orientation axis is called in a scene name when `--roll-mirror` named the cabinets outright.
     *
     * **The one axis value with no enum case behind it, and it needs one anyway.** `--orientation=MODE` says *which*
     * cabinets lie down by a rule — every sub, or only the ones that get wider on their side — where `--roll-mirror`
     * lists them, and {@see SweepAxes::orientations} represents that as a null orientation. Left unnamed it was the
     * one gap left in a scheme whose whole point is that a reader never has to know what a missing field meant.
     *
     * **Not a {@see StackOrientation} case**, deliberately. An enum case would be offerable as `--orientation=stated`,
     * which means nothing without a `--roll-mirror` beside it and would have to be refused wherever it appeared alone.
     * The name is a fact about how the rig was *asked for* rather than about which cabinets ended up on their sides,
     * so it belongs to the naming rather than to the axis.
     */
    public const STATED_ORIENTATION = 'stated';

    /**
     * The filler that pads an axis value out to its axis's widest one.
     *
     * A dash, so a name is one alphabet rather than two. The fields are fixed-width and positional, so nothing reads
     * a name by splitting on the separator any more and a run of dashes costs no ambiguity.
     */
    public const NAME_PAD = '-';

    /**
     * The alignments to write a scene for, or the reason one of them is not an alignment.
     *
     * All three by default. `center` and `block` are both mono and differ in how a tier is spread; `stereo` splits a
     * tier onto the edges of what carries it.
     *
     * @param list<string> $raw
     *
     * @return list<LayoutMode>|string
     */
    public static function modes(array $raw): array|string
    {
        $stated = self::of($raw, LayoutMode::class, '--align');

        return is_string($stated) || [] !== $stated ? $stated : LayoutMode::cases();
    }

    /**
     * The shapes to write a scene for, or the reason one of them is not a shape.
     *
     * All three by default, the same way `--align` defaults to all three modes: the shapes answer different questions
     * — {@see StackShape::Pyramid} takes the silhouette and the height, {@see StackShape::Free} keeps the deepest and
     * heaviest cabinets on the floor, {@see StackShape::V} reverses the pyramid so the wall widens as it rises — and
     * which matters more is the sort of thing to decide by looking at three renders rather than by reading a docblock.
     *
     * @param list<string> $raw
     *
     * @return list<StackShape>|string
     */
    public static function shapes(array $raw): array|string
    {
        $stated = self::of($raw, StackShape::class, '--shape');

        return is_string($stated) || [] !== $stated ? $stated : StackShape::cases();
    }

    /**
     * How separately the systems stand, or the reason one of them is not a value — the seventh axis.
     *
     * Returns `[]` when nothing was named, which the caller reads per rig rather than here: separation means nothing
     * below two owners, so a single-owner rig is offered `pooled` alone and the default cannot be a flat list of
     * cases. See {@see SystemSplit::forOwnerCount}.
     *
     * **`--systems` narrows the axis, it does not collapse the sweep**, which is the same line `--align` and
     * `--shape` sit on. `--per-owner` is the older way to ask for one value of it and does collapse, because it was
     * built as "this rig, separated" rather than as an axis — and it stays exactly as it was, since 433 written
     * scenes record their regeneration with it.
     *
     * @param list<string> $raw
     *
     * @return list<SystemSplit>|string `[]` when none was named
     */
    public static function systemSplits(array $raw): array|string
    {
        return self::of($raw, SystemSplit::class, '--systems');
    }

    /**
     * The mirror styles somebody named, or `[]` for "sweep whatever the orientation gives them to differ on".
     *
     * All three by default, like `--align` and `--shape`, and for the same reason: an odd cabinet in a turned row has no
     * arrangement that is both symmetric and flat, so the choice is a trade rather than an answer. See
     * {@see MirrorStyle}.
     *
     * **THE DEFAULT IS DECIDED PER ORIENTATION RATHER THAN HERE**, which is why this returns an empty list instead of
     * every case. {@see Tier::mirrored} acts only on segments lying on a quarter turn, so the styles are *vacuous*
     * wherever nothing is rolled and come out byte-identical, and the measurement was unambiguous: sweeping the axis
     * unconditionally gave **66 `centred` candidates, 0 written** — 18 caught afterwards by `deduplicate()` and the
     * other 48 refused identically to their `alternate` twin. `deduplicate()` catching them is not good enough, because
     * the cost is not a file, it is *every candidate solved twice*, and a candidate is a full solve plus a compile plus
     * an interpenetration sweep.
     *
     * {@see pairs} is where that judgement now lives, since which cabinets roll is exactly what the orientation axis
     * decides.
     *
     * @param list<string> $raw
     *
     * @return list<MirrorStyle>|string `[]` when none was named
     */
    public static function mirrorStyles(array $raw): array|string
    {
        return self::of($raw, MirrorStyle::class, '--mirror-style');
    }

    /**
     * The orientations to write a scene for, or the reason one of them is not an orientation.
     *
     * All three by default, and this is the axis that pays best of any in the sweep: **the default sweep writes 61
     * scenes where upright alone writes 11**, three-stack rigs among them for the first time, because a rolled sub is
     * wider and shorter and both of those help a wall land inside the sub height band. See {@see StackOrientation}.
     *
     * **`--roll-mirror` turns the axis off**, which keeps every hand invocation that names cabinets working exactly as
     * it did. The two options answer the same question at different resolutions — which cabinets lie down — so a line
     * naming `--roll-mirror=skram` means *those* and not "sweep three modes and ignore what I said". A `null`
     * orientation is how that is carried: it says "the rolled set is stated on the command line", and the caller reads
     * `--roll-mirror` in that case.
     *
     * @param list<string> $raw
     * @param list<string> $rolled the device ids `--roll-mirror` named
     *
     * @return list<StackOrientation|null>|string
     */
    public static function orientations(array $raw, array $rolled): array|string
    {
        $stated = self::of($raw, StackOrientation::class, '--orientation');
        if (is_string($stated)) {
            return $stated;
        }

        return [] !== $stated ? $stated : ([] === $rolled ? StackOrientation::cases() : [null]);
    }

    /**
     * The orientation and mirror style **as pairs**, because the two are not independent axes.
     *
     * Orientation says which cabinets lie down; mirror style says what a rolled row does with the odd cabinet it cannot
     * split in half. Different questions, and a turned rig genuinely has three different forms — but the style is
     * *vacuous* when nothing is rolled, since {@see Tier::mirrored} only ever acts on a segment lying on a quarter turn.
     * Multiplied out as two axes, one third of every candidate would be a duplicate of another by construction. Paired,
     * the vacuous combinations are **unrepresentable** rather than guarded:
     *
     * | # | pair |
     * | --- | --- |
     * | 1 | `upright` — nothing rolled, so no odd cabinet to place |
     * | 2–4 | `turned` × (`alternate`, `centred`, `column`) |
     * | 5–7 | `mixed` × (`alternate`, `centred`, `column`) |
     *
     * **A mode that rolls nothing in *this* rig is dropped as well**, and that is not the same rule. `mixed` rolls only
     * the cabinets that get wider on their side, so an inventory of a cube and a top has nothing for it to turn — and
     * the candidate it would produce is `upright` under another name. Dropping it here rather than letting
     * `deduplicate()` find it afterwards is what keeps the names honest: a `-turned-` file always has something turned
     * in it.
     *
     * Asked per rig rather than once, because the rigs are different inventories. Asked on the rig's whole device list
     * rather than per stack, because a split can hand one stack no rollable cabinet while the rig plainly has one, and
     * the scene is named for the rig.
     *
     * **A style is always part of the pair, so it is always part of the scene's name.** It was tempting to leave it out
     * of the name where nothing is rolled, on the grounds that the sweep only ever pairs `upright` with `alternate` and
     * a style decides nothing there. That is true of the sweep and false of the command:
     * `--orientation=upright --mirror-style=centred` is accepted and honoured — see
     * {@see \App\Tests\Command\SceneStackCommandTest::testAnExplicitCentredStyleIsHonouredWithNothingRolled} — so a
     * name that dropped it would give two different rigs the same file name.
     *
     * @param list<StackOrientation|null> $orientations
     * @param list<MirrorStyle> $stated the styles somebody named, `[]` to sweep as each orientation allows
     * @param list<string> $rolled the device ids `--roll-mirror` named, which is what a null orientation defers to
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $from
     *
     * @return list<array{StackOrientation|null, MirrorStyle}>
     */
    public static function pairs(
        array $orientations,
        array $stated,
        array $rolled,
        array $devices,
        array $from,
    ): array {
        $pairs = [];
        foreach ($orientations as $orientation) {
            $rolls = $orientation?->rolls($devices, $from) ?? array_values(array_intersect($from, $rolled));
            if ([] === $rolls && null !== $orientation && StackOrientation::Upright !== $orientation) {
                continue;
            }

            $styles = [] !== $stated ? $stated : ([] === $rolls ? [MirrorStyle::Alternate] : MirrorStyle::cases());
            foreach ($styles as $style) {
                $pairs[] = [$orientation, $style];
            }
        }

        return $pairs;
    }

    /**
     * One axis value padded to the width of the widest value that axis has, so the fields line up down a listing.
     *
     * **A directory of 396 files is read in columns or not at all.** Unpadded, `stacked-all-3-v-mixed-centred-block`
     * and `stacked-gmss-sdwa5-2-pyramid-upright-alternate-center` share a scheme that nothing about looking at them
     * reveals: every field starts at a different place, so comparing two rigs means parsing both names first. Padded,
     * the shape column is the shape column in every row.
     *
     * **The width comes from the enum rather than from a number written here**, so a new case widens the column by
     * existing. That renames every scene the day an axis gains a value, which is the honest price and is a thing that
     * already happens for other reasons — the same release that adds a shape regenerates the set anyway.
     *
     * @param class-string<\BackedEnum> $axis
     */
    public static function padded(string $value, string $axis): string
    {
        $width = 0;
        foreach ($axis::cases() as $case) {
            $width = max($width, strlen((string) $case->value));
        }
        // The orientation axis carries one value that is not a case of it, so the column has to clear that too.
        if (StackOrientation::class === $axis) {
            $width = max($width, strlen(self::STATED_ORIENTATION));
        }

        return str_pad($value, $width, self::NAME_PAD);
    }

    /**
     * Every rig worth trying — which gear, and how many stacks to split it into.
     *
     * **THIS IS THE PROJECT'S GOAL EXPRESSED AS A DEFAULT.** As many *sensible* configurations as possible out of one
     * command in its default settings. Before this, the bare command wrote **nothing at all**: `--from` defaulted to
     * every speaker in the repository, which since the GMSS cabinets arrived means two sound systems in one stack —
     * a rig nobody would build, whose eight tops alone are 3.921 m and refuse on any stage we own. Meanwhile every
     * generated scene had to spell four to six flags out to get anywhere.
     *
     * So absence now means *sweep*, the way it already does for `--align` and `--shape`:
     *
     * * **one rig from one inventory** ({@see SweepAxes::inventory}) — the subset `--owner` named, or
     *   {@see SweepAxes::DEFAULT_OWNERS}, which is our gear and Sepp's pooled. It used to be every non-empty
     *   combination of owners, and the reason it is not any more is that five owners make 31 of them, 26 of which are
     *   rigs nobody will ever build. `owner` is the only discriminator the specs carry, and it is admittedly not
     *   quite the right one — the repository deliberately supports borrowing gear between owners, so "owner" and
     *   "system" are not the same question. It is what exists, it separates the systems in practice, and inventing a
     *   `system:` field to serve a sweep would be inventing a property to serve a layout.
     * * **one, two and three stacks** — the counts somebody actually varies when planning a gig.
     *
     * Naming each rig into the scene id is what keeps the files apart, and it reads as what it is:
     * `stacked-sdwa5-2-free-stereo` is our gear, two stacks, free shape, stereo.
     *
     * **Naming any of `--from`, `--stacks` or `--per-owner` collapses the sweep to that single point**, exactly as
     * naming `--align` collapses it to one mode. Nothing that worked before works differently; the only change is what
     * *silence* means. `--owner` is the exception and narrows one axis instead of collapsing the sweep, since it says
     * whose gear to build from and nothing about the rig — see {@see SweepAxes::inventory}.
     *
     * @param list<DeviceSpec> $specs
     *
     * @return list<array{from: list<string>, stacks: int, inventory: string, suffix: string}>
     */
    public static function rigsToTry(
        array $specs,
        array $stated,
        ?string $statedStacks,
        array $owners,
        bool $perOwner,
        SystemGrouping $grouping,
        array $splits = [],
    ): array {
        // **NAMING A VALUE ON AN AXIS SWITCHES THE OTHER VALUES OF THAT AXIS OFF. IT DOES NOT SWITCH THE SWEEP
        // OFF.** That is SWP-3's first ask and it used to be true of five axes and false of three: `--shape`,
        // `--align`, `--orientation`, `--mirror-style` and `--owner` narrowed, while `--from`, `--stacks` and
        // `--per-owner` were read as "the caller has one specific rig in mind" and collapsed the whole cross
        // product to a single candidate. So "sweep everything, but only two stacks" could not be asked for.
        //
        // There is one path now. `--from` narrows the inventory axis to a stated cabinet list, `--stacks` narrows
        // the stack-count axis, `--systems` narrows the separation axis, and whatever is left keeps walking.
        // **A replay narrows every axis to one value and therefore still writes exactly one file**, which is the
        // property {@see \App\Command\BuildAllCommand} rests on.
        $named = [] !== $stated;

        // **`--owner` binds whether or not other axes are narrowed**, which is what stops it being silently
        // ignored the moment somebody writes `--owner=gmss --stacks=2`. `--from` names the cabinets outright and
        // wins; naming both is refused in `execute()` rather than resolved here.
        $subset = $named ? [] : self::inventory(self::speakerOwners($specs), $owners);

        $groups = [];
        $ownersOf = [];
        if ($named) {
            // **A NAMED RIG HAS NO INVENTORY TO BE FILED UNDER, WHICH IS NOT THE SAME AS HAVING AN EMPTY ONE.**
            // `--from` names cabinets across owners, so there is no subset to label. Such a run writes to
            // `scenes/generated/` itself unless `--into` says otherwise, and a replay of a swept scene is told its
            // directory by `--into` for exactly that reason.
            $groups[''] = $stated;
            $ownersOf[''] = [];
        } else {
            $byOwner = [];
            foreach ($specs as $spec) {
                if ('speaker' === $spec->category->value && $spec->quantity > 0) {
                    $byOwner[$spec->owner][] = $spec;
                }
            }
            ksort($byOwner);
            $inventory = self::inventory(array_keys($byOwner), $owners);
            $owned = [];
            foreach ($inventory as $owner) {
                $owned = [...$owned, ...$byOwner[$owner]];
            }
            $label = self::labelFor($inventory);
            $groups[$label] = FillOrder::everySpeaker($owned);
            $ownersOf[$label] = $inventory;
        }

        // **The stack-count axis, narrowed rather than collapsed.** NOT clamped: an explicit `--stacks=0` is a
        // mistake worth refusing, and {@see StackDeal::groups} is where that refusal lives. Clamping it here
        // silently solved a one-stack rig instead.
        $counts = null === $statedStacks ? [1, 2, 3] : [(int) $statedStacks];

        // **THE INVENTORY IS A DIRECTORY NOW AND THAT IS WHY THERE IS NO WIDTH HERE ANY MORE.** It used to be a
        // dash-padded field in the file name, padded to the widest label the *specs* could produce rather than the
        // widest this run needs — because a width measured after `--owner` narrowed the run would give one rig two
        // different file names depending on how it was asked for. Both the padding and that trap are gone: the label
        // names the folder, so it is never abbreviated, never padded, and adding an owner cannot rename a single
        // existing scene. **The system name is also redundant inside a folder named after the system**, which is why
        // the suffix below carries only the stack count and the separation.
        $rigs = [];
        foreach ($groups as $label => $from) {
            foreach ($counts as $stacks) {
                // A stack cannot hold fewer than one device type, so a *swept* stack count higher than the type
                // count is not a rig worth reporting a refusal for — it is arithmetic, and skipping it silently is
                // what stops every small inventory printing the same sentence twice.
                //
                // **A STATED COUNT IS NOT SKIPPED, BECAUSE SOMEBODY TYPED IT.** `--stacks=9` on five device types
                // is a request that cannot be met, and the caller is owed the sentence saying so —
                // {@see \App\Scene\StackDeal::groups} writes it. Skipping it here instead reported "none of these
                // rigs stands up", which is a different answer to a different question.
                if (null === $statedStacks && count($from) < $stacks) {
                    continue;
                }
                // **SWP-2's axis, and it is offered only where it can mean something.** A rig drawn from one owner
                // has nothing to separate, so it gets `pooled` alone — see {@see SystemSplit::forOwnerCount}. That
                // is a third of the sweep not solved twice to produce one file.
                //
                // `--systems` then narrows what is left, in the enum's own order rather than the order it was typed,
                // so `--systems=tops-shared --systems=pooled` and the reverse name the same rigs. Intersected rather
                // than replacing the list, because "offered where it can mean something" is a fact about the rig and
                // an option may not talk a single-owner rig into a separation it has nothing to separate.
                //
                // **Intersected by value rather than with `array_intersect`, which threw.** That function casts
                // every element to a string to compare it, and an enum case is not stringable — so
                // `--systems=pooled` on a sweep died with "Object of class SystemSplit could not be converted to
                // string" for the whole of 0.96.0, in the one code path the option exists for. It went unnoticed
                // because the option was only ever exercised on the named-rig path, where `$splits[0]` is read
                // directly and no intersection happens.
                // **COUNTED IN SYSTEMS, NOT IN OWNERS.** `sdwa5-sepp` is two owners and one system, so all three
                // separations used to be offered for it and two of them solved a rig standing our own gear apart
                // from itself — 118 files of it. One system has nothing to separate. See {@see SystemGrouping}.
                // **A NAMED RIG IS OFFERED EVERY SEPARATION**, because `--from` says which cabinets and nothing
                // about whose they are — there is no owner count to read, and answering `pooled` from a count of
                // zero would rebuild every replayed `systems-apart` scene pooled, under the separated rig's name.
                $offered = $named
                    ? SystemSplit::cases()
                    : SystemSplit::forOwnerCount($grouping->countIn($ownersOf[$label] ?? []));
                $wanted = [] === $splits ? ($perOwner ? [SystemSplit::SystemsApart] : $offered) : array_values(array_filter(
                    $offered,
                    static fn (SystemSplit $split): bool => in_array($split, $splits, true),
                ));
                foreach ($wanted as $split) {
                    $rigs[] = [
                        'from' => $from,
                        'stacks' => $stacks,
                        'split' => $split,
                        'inventory' => (string) $label,
                        'suffix' => sprintf(
                            '-%d-%s',
                            $stacks,
                            self::padded($split->value, SystemSplit::class),
                        ),
                    ];
                }
            }
        }

        return $rigs;
    }

    /**
     * Every owner with speakers in the library, sorted — the inventory axis's own alphabet.
     *
     * Sorted rather than in spec order, because the sort is what makes `--owner=sepp --owner=gmss` and the reverse
     * name the same rig: {@see SweepAxes::inventory} intersects against this list and takes its order from
     * it. Speakers only, and only where something is owned, because a rig is built out of cabinets — an owner with
     * nothing but a van is not an inventory to sweep.
     *
     * @param list<DeviceSpec> $specs
     *
     * @return list<string>
     */
    public static function speakerOwners(array $specs): array
    {
        $owners = [];
        foreach ($specs as $spec) {
            if ('speaker' === $spec->category->value && $spec->quantity > 0) {
                $owners[$spec->owner] = true;
            }
        }
        $names = array_keys($owners);
        sort($names);

        return $names;
    }

    /**
     * Where the low end goes, or the reason one of the stated values is not a value.
     *
     * Both cases when nothing is stated, because the axis exists to put the two rigs side by side: one that pulls
     * the lowest cabinets onto the centre line and one that keeps them on the floor. Neither is a default in the
     * sense of being right — they are two answers to a question this repository could not previously ask.
     *
     * @return list<LowEndBias>|string
     */
    public static function lowEndBiases(array $raw): array|string
    {
        $stated = self::of($raw, LowEndBias::class, '--low-end');

        return is_string($stated) || [] !== $stated ? $stated : LowEndBias::cases();
    }

    /**
     * The inventory the sweep builds from — **one subset of owners, not a powerset of them.**.
     *
     * **THIS USED TO RETURN EVERY NON-EMPTY COMBINATION AND THAT IS WHY IT NO LONGER DOES.** With three owners the
     * powerset is seven inventories and reads as generosity; the fourth and fifth owner make it 31, the sweep goes
     * past its own fuse, and 26 of those 31 are rigs nobody will ever build — a wall of Innschleife subs under PSL
     * tops with our Tecnare on top is arithmetic, not a gig. **Stated by the owner: the sweep is always run against a
     * subset, and the default subset is our own gear and Sepp's as one combined rig.** So silence means
     * {@see DEFAULT_OWNERS} and `--owner` means what it names, and either way the answer is a single inventory.
     *
     * What is lost with the powerset is the discovery it bought once: `sdwa5 + sepp` was found to write more scenes
     * than any single owner, and nobody had thought to ask for that pair. That finding is why the pair is now the
     * default rather than a reason to keep enumerating 31 of them — the answer was worth having and does not need
     * re-deriving every run.
     *
     * **The default is intersected with the owners that exist rather than asserted**, so a repository holding only
     * GMSS gear sweeps GMSS instead of sweeping nothing. If the default names none of the owners present, every
     * owner present is the inventory: one rig out of whatever there is beats a refusal nobody asked for.
     *
     * **Named `inventory` rather than `ownerCombinations` because it returns one.** The old name promised a list of
     * subsets and delivered one wrapped in an array, which is the kind of signature a caller indexes with `[0]` and
     * then wonders about.
     *
     * @param list<string> $owners every owner with speakers, already sorted
     * @param list<string> $stated what `--owner` named, validated by the caller
     *
     * @return list<string> one subset, in the specs' own sorted order
     */
    public static function inventory(array $owners, array $stated): array
    {
        // Intersected in the specs' own order rather than in the order they were typed, so `--owner=sepp
        // --owner=gmss` and the reverse name the same rig and write the same files.
        $wanted = [] !== $stated ? $stated : self::DEFAULT_OWNERS;
        $subset = array_values(array_intersect($owners, $wanted));

        return [] === $subset ? $owners : $subset;
    }

    /**
     * What an inventory is called: **the owners in it, joined, and nothing cleverer than that.**.
     *
     * `sdwa5-sepp` is our gear and Sepp's, `gmss-sdwa5-sepp` is all three of the systems we had figures for before
     * PSL and Innschleife arrived. It names a directory under `scenes/generated/` rather than a field in a file
     * name, which is SWP-3's rule that an axis value appears in the path or in the name and never in both.
     *
     * **THERE USED TO BE AN `all` SHORTCUT AND REMOVING IT IS THE POINT OF THIS METHOD NOW.** A subset covering
     * every owner was labelled `all`, which was shorter and stayed correct only as long as the owner list did:
     * `all` meant three systems and 39 cabinets, and the day two more systems were specced the same word meant five
     * systems and 95 cabinets. Every one of the 331 files carrying it described a rig that no longer had that name.
     * A label built from the owners in it cannot go stale that way — `gmss-sdwa5-sepp` means the same rig whoever
     * else gets specced later.
     *
     * @param list<string> $subset
     */
    public static function labelFor(array $subset): string
    {
        return implode('-', $subset);
    }

    /**
     * One repeatable enum option, parsed — the shape all four of them share.
     *
     * Returns `[]` for "nothing was named", which each caller reads as its own default, and the error message otherwise.
     * **The message names every allowed value**, which is the half worth keeping in one place: a refusal that says only
     * "unknown value" sends somebody to the source to find out what is allowed, and the enum already knows.
     *
     * @param list<string> $raw
     * @param class-string $enum
     *
     * @return list<mixed>|string
     */
    private static function of(array $raw, string $enum, string $option): array|string
    {
        $values = [];
        foreach ($raw as $value) {
            /** @var \BackedEnum|null $case */
            $case = $enum::tryFrom($value);
            if (null === $case) {
                return sprintf(
                    "%s: unknown value '%s' (allowed: %s)",
                    $option,
                    $value,
                    implode(', ', array_column($enum::cases(), 'value')),
                );
            }
            $values[] = $case;
        }

        return $values;
    }
}
