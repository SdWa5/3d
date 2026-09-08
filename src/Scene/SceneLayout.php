<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Which axes of the sweep are directory levels and which are fields in the file name.
 *
 * **SWP-3's third ask, and its rule is that an axis value appears in exactly one of the two, never in both.** A
 * file under `free/turned/column/` is not also called `stacked-1-free-turned-column-center`; it is
 * `stacked-1-center`. Otherwise every path states the same fact twice and a rename has two places to go wrong.
 *
 * **The nesting order is fixed and is the order the name already uses** — the rig first (which gear, then how many
 * stacks, then how separately the systems stand), then shape, orientation, mirror style, alignment, and last
 * whether the thing stands up. So switching a level on or off is a *move* rather than a rewrite: the field leaves
 * the name and becomes a directory in the same position, and every other field stays where it was.
 *
 * **The inventory is permanently a folder and is deliberately not configurable.** It is the only axis whose value
 * cannot be recovered from a scene's own recorded command line — that line names cabinets rather than owners, on
 * purpose, so that measuring a new sub does not change what a replay rebuilds. Allowing it in the name would mean
 * inventing a naming-only option to carry the label, which is a second copy of a fact; and it would bring back the
 * padding trap the folder removed, where speccing a fifth owner renamed 1374 files without changing one rig.
 *
 * **Three levels is the recommended ceiling and four is refused.** The axes have cardinalities 11 × 3 × 3 × 3 × 4
 * × 3 × 3 × 2 × 2, so switching them all on yields more directories than files — a trie rather than a tree, every leaf
 * holding one scene. A directory level earns its keep where it has many siblings *and* somebody wants to browse by
 * it, which on this tree is the inventory, plausibly feasibility ("show me the rigs that do not stand up") and
 * plausibly the stack count. Beyond that it is refused for the same reason `--max-scenes` refuses rather than
 * truncates: silently producing an unusable shape is worse than saying so.
 */
final class SceneLayout
{
    /** The nesting order, which is also the order the name reads in. The value is the axis's own option name. */
    public const AXES = [
        'inventory', 'stacks', 'systems', 'shape', 'orientation', 'mirror-style', 'align', 'low-end', 'feasibility',
    ];

    /**
     * The layout a run gets when nobody says otherwise: the inventory alone.
     *
     * **This is exactly today's tree**, which is the point. The mechanism ships without moving a single committed
     * file, and any deeper layout is one flag and one regeneration away.
     */
    public const DEFAULT = ['inventory'];

    /** More than this many directory levels is a trie. See the class docblock. */
    private const MAX_FOLDERS = 3;

    /**
     * @param list<string> $folders in {@see AXES} order
     */
    private function __construct(public readonly array $folders)
    {
    }

    /**
     * @param list<string> $stated comma-separated axis names, as `--folders` gives them
     *
     * @return self|string the layout, or the reason it cannot be read
     */
    public static function of(array $stated): self|string
    {
        $wanted = [];
        foreach ($stated as $group) {
            foreach (explode(',', $group) as $axis) {
                $axis = trim($axis);
                if ('' === $axis) {
                    continue;
                }
                if (!in_array($axis, self::AXES, true)) {
                    return sprintf(
                        "--folders: unknown axis '%s' (allowed: %s)",
                        $axis,
                        implode(', ', self::AXES),
                    );
                }
                $wanted[$axis] = true;
            }
        }

        if ([] === $wanted) {
            return new self(self::DEFAULT);
        }

        // The inventory is always a level, whether or not it was named — see the class docblock.
        $wanted['inventory'] = true;
        // **Normalised to the nesting order rather than kept in the order it was typed**, so that
        // `--folders=shape,stacks` and `--folders=stacks,shape` are the same layout and name the same files. A
        // comma list is a set; a repeatable option would have read as an ordered one, which is why this is not one.
        $folders = array_values(array_filter(self::AXES, static fn (string $axis): bool => isset($wanted[$axis])));

        if (count($folders) > self::MAX_FOLDERS) {
            return sprintf(
                '--folders: %d levels is a directory tree with more branches than files (%s). At most %d.',
                count($folders),
                implode(', ', $folders),
                self::MAX_FOLDERS,
            );
        }

        return new self($folders);
    }

    public function isDefault(): bool
    {
        return self::DEFAULT === $this->folders;
    }

    /** What `--folders` has to record for a replay to rebuild this layout. */
    public function stated(): string
    {
        return implode(',', $this->folders);
    }

    /**
     * The directory this scene goes in, relative to `scenes/generated/` — the folder axes' values, in order.
     *
     * **Given the raw values, never the padded ones.** The padding is a name convention and nothing else: a file
     * name is read in columns, so every field is padded to its axis's widest value and `v` becomes `v------`. A
     * directory has no column to line up with, and `v------/` would carry six dashes that mean nothing and would
     * change width the day an axis gains a longer case — renaming every directory under it.
     *
     * @param array<string, string> $values every axis's raw value, keyed by axis name
     */
    public function pathFor(array $values): string
    {
        $parts = [];
        foreach ($this->folders as $axis) {
            $value = $values[$axis] ?? '';
            if ('' !== $value) {
                $parts[] = $value;
            }
        }

        return implode('/', $parts);
    }

    /**
     * The file's own name: the base id, then every axis that is **not** a folder, in the same order.
     *
     * @param array<string, string> $values every axis's value, already padded where the name pads it
     */
    public function nameFor(string $baseId, array $values): string
    {
        $name = $baseId;
        foreach (self::AXES as $axis) {
            if (in_array($axis, $this->folders, true) || !isset($values[$axis]) || '' === $values[$axis]) {
                continue;
            }
            $name .= '-'.$values[$axis];
        }

        return $name;
    }
}
