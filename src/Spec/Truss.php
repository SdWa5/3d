<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The tubes a truss segment is made of, for {@see Shape::Truss}.
 *
 * **A truss is mostly air, and that is the whole reason this exists.** Every other device in the library is a
 * hexahedron whose outer dimensions describe a solid thing. A truss's outer dimensions describe a volume it barely
 * occupies: drawn as a box, a 9 m run would hide the entire rig behind it in every render. So the geometry has to
 * be the chords and the bracing, and those need stating.
 *
 * `dimensions_m` still holds the true bounding box, exactly as for a cabinet — which is what keeps scene placement,
 * the overlap sweep and the catalog's shipping volume working without knowing a truss from a sub. What this block
 * adds is what goes *inside* that box.
 *
 * Nothing here is derived from anything else. `chords` and the two diameters are read off a datasheet, and
 * `bay_length_m` is the pitch of the zigzag — the one figure manufacturers rarely publish, so it is usually the
 * estimated part of an otherwise sourced spec.
 */
final class Truss
{
    public function __construct(
        public readonly int $chords,
        public readonly float $chordDiameter,
        public readonly float $diagonalDiameter,
        public readonly float $bayLength,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireInt('chords'),
            $reader->requireFloat('chord_diameter_m'),
            $reader->requireFloat('diagonal_diameter_m'),
            $reader->requireFloat('bay_length_m'),
        );
    }

    /**
     * @return array{chords: int, chord_diameter_m: float, diagonal_diameter_m: float, bay_length_m: float}
     */
    public function toArray(): array
    {
        return [
            'chords' => $this->chords,
            'chord_diameter_m' => $this->chordDiameter,
            'diagonal_diameter_m' => $this->diagonalDiameter,
            'bay_length_m' => $this->bayLength,
        ];
    }
}
