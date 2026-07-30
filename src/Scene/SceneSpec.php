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
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $id,
        public readonly string $name,
        public readonly array $placements,
        public readonly ?string $notes,
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
            notes: $reader->optionalString('notes'),
        );
    }
}
