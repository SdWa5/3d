<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * How the physical device came to exist. Most SdWa5 cabinets are `Clone` — DIY-built copies
 * of a commercial design — which is why `clone_of` is mandatory for them: the original's
 * datasheet is then a legitimate source for dimensions, weight and coverage.
 */
enum BuildKind: string
{
    case Clone = 'clone';
    case OwnDesign = 'own-design';
    case Original = 'original';
}
