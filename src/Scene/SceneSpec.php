<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;

/**
 * A PA setup as a file: which devices, where, stacked on what.
 *
 * The point of writing a setup down rather than assembling it by hand in Blender is that it becomes
 * reviewable and repeatable — a setup that worked at an event is a commit, not somebody's memory,
 * and next year's variation is a diff.
 */
final class SceneSpec
{
    /**
     * @param list<Placement> $placements
     * @param array<string, Focus> $focusByName every focus this scene defines, by the name `aim` uses
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $id,
        public readonly string $name,
        public readonly array $placements,
        /** @var array<string, Focus> */
        public readonly array $focusByName,
        public readonly ?string $notes,
        /** One of RenderPlan's AIM_* modes: the scene's own default for drawing where cabinets point. */
        public readonly string $aimLines = \App\Render\RenderPlan::AIM_NONE,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        $reader = new ArrayReader($data);

        $placements = [];
        foreach ($reader->sectionList('placements') as $index => $entry) {
            $placements[] = Placement::fromReader($entry, $index + 1);
        }

        return new self(
            sourcePath: $sourcePath,
            id: $reader->requireString('id'),
            name: $reader->requireString('name'),
            placements: $placements,
            focusByName: self::readFoci($reader),
            notes: $reader->optionalString('notes'),
            aimLines: $reader->optionalString('aim_lines', \App\Render\RenderPlan::AIM_NONE)
                ?? \App\Render\RenderPlan::AIM_NONE,
        );
    }

    /**
     * One focus or several, told apart by their shape.
     *
     * `focus: { distance_m: 10 }` is the single unnamed one every scene written so far uses, and it keeps
     * the name `focus` so `aim: focus` still means it. `focus: { near: {...}, far: {...} }` is a map, and
     * the discrimination is simply whether every value is itself a mapping — the same shorthand-or-expanded
     * test {@see \App\Spec\ArrayReader::isSection} already makes for `provenance`.
     *
     * A focus is a decision about the room rather than about a cabinet, which is why they live here and are
     * referenced by name instead of being written into each placement: two clusters sharing one near-field
     * point is the normal case, and duplicated numbers drift apart silently.
     *
     * @return array<string, Focus>
     */
    private static function readFoci(ArrayReader $reader): array
    {
        $section = $reader->optionalSection('focus');
        if ($section === null) {
            return ['focus' => new Focus()];
        }

        $names = $section->keys();
        $named = $names !== [] && array_reduce(
            $names,
            static fn (bool $carry, string $key): bool => $carry && $section->isSection($key),
            true,
        );

        if (!$named) {
            return ['focus' => Focus::fromReader($section)];
        }

        $foci = [];
        foreach ($names as $name) {
            $foci[$name] = Focus::fromReader($section->requireSection($name));
        }

        return $foci;
    }
}
