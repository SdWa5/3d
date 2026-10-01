<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Four wheels on one face of a cabinet, one near each corner, of which `locking` have a brake.
 *
 * **The wheels lie outside the declared box**, and that is the one place a model is allowed to leave it. A
 * castor is what a cabinet rolls on, not part of its volume, and datasheets state a cabinet "ohne Rollen" for
 * the same reason. `protrusionM()` says how far they stand off the face, and tools/check-glb.py allows exactly
 * that much on the face's axis and nothing more. A scene still packs cabinets by their declared box, so two
 * cabinets back to back can show their wheels touching.
 *
 * Only a side or the back can carry them. Wheels under the bottom would change the height every stack is
 * built from, which is a rig change rather than a picture.
 */
final class Castors
{
    public const KEYS = ['face', 'diameter_m', 'color', 'locking'];

    public const FACES = ['back', 'left', 'right'];

    public const COUNT = 4;

    /**
     * How far a castor stands off its face as a multiple of its wheel's diameter: the plate, the swivel and the
     * fork above the wheel. A 100 mm castor is about 128 mm high, which is the usual catalogue figure.
     */
    public const HEIGHT_RATIO = 1.28;

    public function __construct(
        public readonly string $face,
        public readonly float $diameterM,
        public readonly ?string $color,
        public readonly int $locking,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $unknown = $reader->unknownKeys(self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("physical.castors: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', self::KEYS)));
        }

        return new self(
            $reader->requireString('face'),
            $reader->requireFloat('diameter_m'),
            $reader->optionalString('color'),
            $reader->optionalInt('locking') ?? 0,
        );
    }

    public function protrusionM(): float
    {
        return $this->diameterM * self::HEIGHT_RATIO;
    }

    /**
     * @return array{face: string, count: int, diameter_m: float, color: string|null, locking: int, protrusion_m: float}
     */
    public function toArray(): array
    {
        return [
            'face' => $this->face,
            'count' => self::COUNT,
            'diameter_m' => $this->diameterM,
            'color' => $this->color,
            'locking' => $this->locking,
            'protrusion_m' => $this->protrusionM(),
        ];
    }
}
