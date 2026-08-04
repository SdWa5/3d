<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * How the physical device came to exist. Most SdWa5 cabinets are `SelfBuilt` — built in-house from
 * somebody else's design — which is why `clone_of` is mandatory for them: the original's datasheet
 * is then a legitimate source for dimensions, weight and coverage.
 *
 * `SelfBuilt` and `OwnDesign` both mean "we built it" and differ in whose drawing it came from, which
 * is the difference that decides where a number may be sourced from. `Original` is factory gear, whose
 * own datasheet is the source and which therefore names no original.
 */
enum BuildKind: string
{
    case SelfBuilt = 'self-built';
    case OwnDesign = 'own-design';
    case Original = 'original';
}
