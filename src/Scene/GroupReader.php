<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;

/**
 * Reads a placement's groups: the cell it repeats, and whatever that cell is nested inside.
 *
 * The cell is a sibling key of `at`/`on`/`aim` — `repeat`, `arc`, `lattice` or `row` — and at most one of
 * them may appear. `in` is a list of further groups, read **inside-out**: the sibling group is the cell,
 * `in[0]` wraps it, `in[1]` wraps that, and so on.
 *
 * ```yaml
 *   arc: { mode: convex, count: 3 }      # the cell: three tops in a fan
 *   in:
 *     - lattice: { count: [1, 1, 2] }    # …and that fan, in two tiers
 * ```
 *
 * Why this shape rather than a recursive `of:` or a flat `groups:` list. Every scene written before it
 * existed is untouched, not merely still parsing, because `in` is simply absent. The nesting order is
 * stated by the word rather than by a convention somebody has to remember. Depth stays flat, so a lattice
 * of lattices of rows is three entries and not six levels of indent. And each entry is one mapping with
 * one key, which means `unknownKeys()` keeps working per group type exactly as {@see Arc} already uses it
 * — a `groups:` list would have needed the same dispatch without saying which way it read, and an `of:`
 * would put the same key at two depths and could no longer name the level that was actually wrong.
 */
final class GroupReader
{
    /**
     * Every group key, and how to read it. The array's order is the order error messages list them in.
     */
    private const GROUPS = ['arc', 'lattice', 'line_array', 'repeat', 'row'];

    public static function read(ArrayReader $reader): GroupStack
    {
        $cell = self::readCell($reader);

        $wrappers = [];
        foreach ($reader->sectionList('in') as $index => $entry) {
            $wrappers[] = self::readNested($entry, $index);
        }

        if ($cell === null) {
            if ($wrappers !== []) {
                throw new InvalidSpecException(
                    '`in` says what this group is nested inside, and there is no group here — '
                    .'move the outermost one out of `in`',
                );
            }

            return new GroupStack();
        }

        return new GroupStack([$cell, ...$wrappers]);
    }

    /**
     * The one group written as a sibling key, or null for a placement that is a single cabinet.
     */
    private static function readCell(ArrayReader $reader): ?Group
    {
        $present = array_values(array_filter(self::GROUPS, static fn (string $key): bool => $reader->has($key)));

        if (count($present) > 1) {
            throw new InvalidSpecException(sprintf(
                'use one group per placement, not both `%s` and `%s` — nest one inside the other with `in`',
                $present[0],
                $present[1],
            ));
        }

        return $present === [] ? null : self::build($present[0], $reader->requireSection($present[0]));
    }

    /**
     * One entry of `in`: exactly one group key and nothing else.
     *
     * Checked rather than assumed, because a mistyped group name would otherwise read as an empty wrapper
     * and silently drop a whole level of the nest — the same argument that makes `arc` reject unknown keys.
     */
    private static function readNested(ArrayReader $entry, int $index): Group
    {
        $present = array_values(array_filter(self::GROUPS, static fn (string $key): bool => $entry->has($key)));

        if (count($present) !== 1) {
            throw new InvalidSpecException(sprintf(
                'in[%d]: expected exactly one group — one of %s%s',
                $index,
                implode(', ', self::GROUPS),
                $present === [] ? '' : ', got '.count($present),
            ));
        }

        return self::build($present[0], $entry->requireSection($present[0]));
    }

    private static function build(string $key, ArrayReader $section): Group
    {
        return match ($key) {
            'arc' => Arc::fromReader($section),
            'repeat' => Repeat::fromReader($section),
            'lattice' => Lattice::fromReader($section),
            'row' => Lattice::rowFromReader($section),
            'line_array' => LineArray::fromReader($section),
            default => throw new InvalidSpecException("unknown group '{$key}'"),
        };
    }
}
