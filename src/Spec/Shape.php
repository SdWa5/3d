<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Outer body form the geometry builder produces. `Box` covers most cabinets; `Trapezoid`
 * is the classic tapered top; `Wedge` is a floor monitor. All three are driven purely by the
 * spec's dimensions, so a shape change never invents new measurements.
 *
 * `Truss` is the odd one out and the only one that is not a hexahedron. The first three describe
 * solid things whose outer dimensions are the object; a truss's outer dimensions are a volume it
 * barely fills, so it is built from tubes instead — see {@see Truss}. It is also the first shape
 * that is not a loudspeaker, which is why the builder skips grille, handles and drivers for it.
 */
enum Shape: string
{
    case Box = 'box';
    case Trapezoid = 'trapezoid';
    case Wedge = 'wedge';
    case Truss = 'truss';

    /**
     * Whether the shape is a loudspeaker cabinet, and so gets a grille, handle recesses and drivers.
     *
     * Asked as a question about the shape rather than about {@see Category}, because it is the *geometry*
     * that decides: a rack is `Box` and gets the same shell treatment a cabinet does, while a truss
     * cannot take a grille no matter what category it is filed under.
     */
    public function isCabinet(): bool
    {
        return $this !== self::Truss;
    }
}
