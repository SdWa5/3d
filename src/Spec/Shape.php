<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Outer body form the geometry builder produces. `Box` covers most cabinets; `Trapezoid`
 * is the classic tapered top; `Wedge` is a floor monitor. All three are driven purely by the
 * spec's dimensions, so a shape change never invents new measurements.
 */
enum Shape: string
{
    case Box = 'box';
    case Trapezoid = 'trapezoid';
    case Wedge = 'wedge';
}
