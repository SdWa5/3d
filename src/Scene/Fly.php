<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Spec\RiggingPoint;

/**
 * A placement hung from a point in the air rather than stood on something.
 *
 * `at` is a ground position and `on` is the top of another placement; between them they cover everything
 * that stands up, and nothing that hangs. That gap is why {@see LineArray} existed but could not be used:
 * its elements take negative z relative to their placement's base, so an array anchored on the floor ends
 * up under it.
 *
 * `point` is what makes this more than "an absolute z". A cabinet does not hang from its own bottom-centre;
 * it hangs from a piece of hardware somewhere on its shell, and `rigging.points` already says where those
 * are. Naming one means the scene states the thing that is actually true — *this* fly point is at 6 m — and
 * the cabinet's slot is worked out from it. Rigging positions are given in the measuring frame, the same
 * frame a slot position is in, so it is a plain subtraction and `geometry.origin` is not involved.
 *
 * `id` is what the weight is grouped under in the report. It defaults to the placement's own id, and is
 * worth stating when two placements share one bar: a truss cares about the total, not about which of the
 * two hangs contributed it.
 */
final class Fly
{
    public function __construct(
        public readonly float $heightM,
        public readonly ?string $point = null,
        public readonly ?string $id = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['height_m', 'point', 'id'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("fly: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        return new self(
            heightM: $reader->requireFloat('height_m'),
            point: $reader->optionalString('point'),
            id: $reader->optionalString('id'),
        );
    }

    /**
     * Where the cabinet's slot sits, given the ground position the hang is over.
     *
     * Without a named point the height *is* the slot height — the cabinet's bottom-centre hangs there,
     * which is the honest reading of "no hardware named". With one, the point is what is at that height,
     * so its own offset comes back off: a point 0.96 m up its cabinet, hung at 6 m, leaves the slot at
     * 5.04 m.
     *
     * @param array{float, float} $ground
     *
     * @return array{float, float, float}
     */
    public function slot(array $ground, ?RiggingPoint $point): array
    {
        if (null === $point) {
            return [$ground[0], $ground[1], $this->heightM];
        }

        return [
            $ground[0] - $point->position[0],
            $ground[1] - $point->position[1],
            $this->heightM - $point->position[2],
        ];
    }

    /**
     * The rigging point this hang names, or null when it names none.
     *
     * Returns null for an unknown name too — {@see SceneCompiler} reports that against the placement,
     * where it can also say which points the device does have.
     */
    public function pointOn(DeviceSpec $device): ?RiggingPoint
    {
        foreach ($device->riggingPoints as $candidate) {
            if ($candidate->id === $this->point) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * What the report groups this hang's weight under.
     */
    public function label(string $placementId): string
    {
        return $this->id ?? $placementId;
    }
}
