<?php

declare(strict_types=1);

namespace App\Spec;

/** Checks castor geometry and materials. */
final class PhysicalValidator
{
    /**
     * Four wheels have to fit their face with room between them, and only as many can lock as there are.
     *
     * @return list<string>
     */
    public function validate(Castors $castors, Dimensions $dimensions): array
    {
        $messages = [];
        if (!in_array($castors->face, Castors::FACES, true)) {
            $messages[] = sprintf("physical.castors.face '%s' must be one of %s", $castors->face, implode(', ', Castors::FACES));
        }
        if ($castors->diameterM <= 0.0 || $castors->diameterM > 0.2) {
            $messages[] = "physical.castors.diameter_m must be above 0 and at most 0.2 m, got {$castors->diameterM}";
        }
        if ($castors->locking < 0 || $castors->locking > Castors::COUNT) {
            $messages[] = sprintf('physical.castors.locking must be between 0 and %d, got %d', Castors::COUNT, $castors->locking);
        }
        if (null !== $castors->color && 1 !== preg_match(ValidationRules::COLOR_PATTERN, $castors->color)) {
            $messages[] = "physical.castors.color '{$castors->color}' must be a #rrggbb hex colour";
        }
        $across = 'back' === $castors->face ? $dimensions->width : $dimensions->depth;
        if (min($across, $dimensions->height) < 3 * $castors->diameterM) {
            $messages[] = "physical.castors: four {$castors->diameterM} m wheels do not fit the {$castors->face} face";
        }

        return $messages;
    }
}
