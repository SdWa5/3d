<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A part of an imported mesh that the real cabinet does not have, cut away before the model is built.
 *
 * CAD is drawn for building a cabinet, and what it draws as solid is not always what stands there: the Flexy's CAD
 * carries a welded cross of plywood in its horn mouth where the real cabinets have a steel brace further back.
 * A removal is a prism that one exact boolean subtracts from the mesh. Its cross-section is a polygon in the side
 * view, given as `[setback, z]` points, and it runs across `x_m` from one end to the other. A polygon rather than
 * a box, because what is to go usually meets a sloped or bent panel that has to stay, and a box would either
 * leave a wedge of the removed part under it or cut a groove into it.
 *
 * Positions are those of a {@see PaintRegion}: `x` and `z` from the centre of the bounding box's front face, and
 * the setback from its front plane. A setback may be negative, so the prism can start in the air in front of the
 * cabinet and cut cleanly through a front face.
 */
final class MeshRemoval
{
    public const KEYS = ['id', 'x_m', 'section_m'];

    /**
     * @param array{float, float} $x from and to, across the cabinet
     * @param list<array{float, float}> $section `[setback, z]` corners of the side-view polygon, in order
     */
    public function __construct(
        public readonly string $id,
        public readonly array $x,
        public readonly array $section,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $unknown = $reader->unknownKeys(self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("mesh_override.remove %d: unknown key '%s' (allowed: %s)", $index, $unknown[0], implode(', ', self::KEYS)));
        }

        $x = $reader->numberList('x_m');
        if (2 !== count($x)) {
            throw new InvalidSpecException("mesh_override.remove {$index}: x_m is [from, to]");
        }

        return new self(
            $reader->optionalString('id') ?? 'remove-'.$index,
            [$x[0], $x[1]],
            $reader->pointList('section_m'),
        );
    }

    /**
     * Twice the signed area of the side-view polygon, by the shoelace formula. Zero for a polygon that encloses
     * nothing, which would make a cutter with no volume.
     */
    public function sectionArea(): float
    {
        $area = 0.0;
        $count = count($this->section);
        for ($index = 0; $index < $count; ++$index) {
            [$ax, $ay] = $this->section[$index];
            [$bx, $by] = $this->section[($index + 1) % $count];
            $area += $ax * $by - $bx * $ay;
        }

        return abs($area) / 2.0;
    }

    /**
     * @return array{id: string, x_m: array{float, float}, section_m: list<array{float, float}>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'x_m' => $this->x, 'section_m' => $this->section];
    }
}
