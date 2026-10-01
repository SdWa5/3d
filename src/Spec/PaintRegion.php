<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A box on an imported mesh whose surfaces take their own colour.
 *
 * A CAD shell arrives as one object in one material, so a part of it that is a different thing in the real
 * cabinet — the steel cross in a Flexy's horn mouth, the painted port tubes of the 18Sound — renders as more of
 * the wooden cabinet. The builder cuts the mesh at the six faces of this box and paints whatever surface lies
 * inside it, which colours that part without inventing geometry the CAD does not have.
 *
 * `at_m` is `[x, z]` from the centre of the bounding box's front face, as a {@see BaffleFeature}'s is. The box runs
 * from `setback_m` behind that front plane to `depth_m` further back. The plane is the bounding box's and not the
 * layout's inset baffle, because a mesh can be painted without having a layout at all. A setback that reaches just
 * past the baffle keeps its front face out of the box, so a round port is painted inside and the square of baffle
 * around it is not.
 */
final class PaintRegion
{
    public const KEYS = ['id', 'at_m', 'size_m', 'depth_m', 'setback_m', 'color'];

    /**
     * @param array{float, float} $at
     * @param array{float, float} $size
     */
    public function __construct(
        public readonly string $id,
        public readonly array $at,
        public readonly array $size,
        public readonly float $depthM,
        public readonly float $setbackM,
        public readonly string $color,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $unknown = $reader->unknownKeys(self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("mesh_override.paint %d: unknown key '%s' (allowed: %s)", $index, $unknown[0], implode(', ', self::KEYS)));
        }

        $at = $reader->numberList('at_m');
        $size = $reader->numberList('size_m');
        if (2 !== count($at) || 2 !== count($size)) {
            throw new InvalidSpecException("mesh_override.paint {$index}: at_m is [x, z] and size_m is [width, height]");
        }

        return new self(
            $reader->optionalString('id') ?? 'paint-'.$index,
            [$at[0], $at[1]],
            [$size[0], $size[1]],
            $reader->requireFloat('depth_m'),
            $reader->optionalFloat('setback_m', 0.0) ?? 0.0,
            $reader->requireString('color'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'at_m' => $this->at,
            'size_m' => $this->size,
            'depth_m' => $this->depthM,
            'setback_m' => $this->setbackM,
            'color' => $this->color,
        ];
    }
}
