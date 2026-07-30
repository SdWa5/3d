<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Where the model's origin sits inside the cabinet. `BottomCenter` is the default because it
 * makes ground-stacking and snapping in Blender work without fiddling; flown elements use
 * `RiggingPoint` so they hang naturally from where they are actually suspended.
 */
enum Origin: string
{
    case BottomCenter = 'bottom-center';
    case RiggingPoint = 'rigging-point';
    case GeometricCenter = 'geometric-center';
}
