<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

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
     * The alignments to write a scene for, or the reason one of them is not an alignment.
     *
     * All three by default. `center` and `block` are both mono and differ in how a tier is spread; `stereo` splits a
     * tier onto the edges of what carries it.
     *
     * @param list<string> $raw
     * @return list<LayoutMode>|string
     */
    public static function modes(array $raw): array|string
    {
        $stated = self::of($raw, LayoutMode::class, '--align');

        return is_string($stated) || $stated !== [] ? $stated : LayoutMode::cases();
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
     * @return list<StackShape>|string
     */
    public static function shapes(array $raw): array|string
    {
        $stated = self::of($raw, StackShape::class, '--shape');

        return is_string($stated) || $stated !== [] ? $stated : StackShape::cases();
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
     * @return list<StackOrientation|null>|string
     */
    public static function orientations(array $raw, array $rolled): array|string
    {
        $stated = self::of($raw, StackOrientation::class, '--orientation');
        if (is_string($stated)) {
            return $stated;
        }

        return $stated !== [] ? $stated : ($rolled === [] ? StackOrientation::cases() : [null]);
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
            if ($rolls === [] && $orientation !== null && $orientation !== StackOrientation::Upright) {
                continue;
            }

            $styles = $stated !== [] ? $stated : ($rolls === [] ? [MirrorStyle::Alternate] : MirrorStyle::cases());
            foreach ($styles as $style) {
                $pairs[] = [$orientation, $style];
            }
        }

        return $pairs;
    }

    /**
     * Every non-empty combination of owners, smallest first — the inventory axis.
     *
     * **Combinations rather than the three fixed groups it used to be.** Before this the sweep offered each owner alone
     * and then everything at once, and the gap in the middle is a rig people actually build: borrowing one system's subs
     * to stand under another's tops is the normal shape of a shared gig, and the repository supports lending gear on
     * purpose. `sdwa5 + gmss` was simply not offered, and the pairs turned out to be the biggest inventories in the
     * sweep — `sdwa5-sepp` writes 50 scenes against `all`'s 17.
     *
     * Smallest first, so a reader sees the single-system rigs before the borrowed ones and the everything rig last —
     * the same ordering the old fixed list had, for the same reason: the single-system rigs are the ones most often
     * built.
     *
     * **`--owner` narrows the axis without collapsing the sweep**, exactly as `--align` narrows the alignment. It is the
     * one narrowing option that is *not* a rig somebody named: `--from` and `--stacks` mean "this rig, at this width",
     * where `--owner=gmss --owner=sepp` still asks the sweep to walk the stack counts, the shapes, the orientations and
     * the width ladder.
     *
     * @param list<string> $owners every owner with speakers, already sorted
     * @param list<string> $stated what `--owner` named, validated by the caller
     * @return list<list<string>>
     */
    public static function ownerCombinations(array $owners, array $stated): array
    {
        if ($stated !== []) {
            // Intersected in the specs' own order rather than in the order they were typed, so `--owner=sepp
            // --owner=gmss` and the reverse name the same rig and write the same file.
            return [array_values(array_intersect($owners, $stated))];
        }

        $subsets = [];
        for ($mask = 1, $end = 1 << count($owners); $mask < $end; ++$mask) {
            $subset = [];
            foreach ($owners as $bit => $owner) {
                if (($mask & (1 << $bit)) !== 0) {
                    $subset[] = $owner;
                }
            }
            $subsets[] = $subset;
        }

        // Stable within a size, because the bitmask order is not the reading order: masks 1, 2, 4 are the singles but
        // 3 sits between 2 and 4.
        usort($subsets, static fn (array $a, array $b): int => count($a) <=> count($b));

        return $subsets;
    }

    /**
     * What a combination of owners is called in a scene id.
     *
     * `all` for the whole inventory, the owner's own name for one owner, and the owners joined for anything between —
     * `stacked-gmss-sdwa5-2-turned-center` is both systems' gear, two stacks, subs on their sides.
     *
     * **`all` is only used where there is more than one owner to be all of**, which is not pedantry: in a repository
     * with a single owner that owner's subset *is* the whole inventory, and labelling it `all` would rename every
     * generated scene for a distinction that does not exist there.
     *
     * @param list<string> $subset
     */
    public static function labelFor(array $subset, int $owners): string
    {
        return $owners > 1 && count($subset) === $owners ? 'all' : implode('-', $subset);
    }

    /**
     * How wide the owner column has to be to hold every label these owners can produce.
     *
     * Asked of the **whole** owner list rather than of the subsets a given run walks, and that is the whole reason it
     * is a method rather than a `max()` at the call site: `--owner` narrows which combinations are built, and a width
     * measured after that narrowing would give one rig two different file names depending on how it was asked for.
     *
     * Every non-empty combination, because the widest label is rarely the obvious one — three owners make `all` out of
     * the biggest subset and `gmss-sdwa5` out of a middling one, so the longest name belongs to a pair rather than to
     * the whole. See {@see \App\Command\SceneStackCommand::padded} for what the width is used for.
     *
     * @param list<string> $owners
     */
    public static function labelWidth(array $owners): int
    {
        $width = 0;
        foreach (self::ownerCombinations($owners, []) as $subset) {
            $width = max($width, strlen(self::labelFor($subset, count($owners))));
        }

        return $width;
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
     * @return list<mixed>|string
     */
    private static function of(array $raw, string $enum, string $option): array|string
    {
        $values = [];
        foreach ($raw as $value) {
            /** @var \BackedEnum|null $case */
            $case = $enum::tryFrom($value);
            if ($case === null) {
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
