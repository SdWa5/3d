<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Outer body form the geometry builder produces. `Box` covers most cabinets; `Trapezoid`
 * is the classic tapered top; `Wedge` is a floor monitor. All three are driven purely by the
 * spec's dimensions, so a shape change never invents new measurements.
 *
 * The first three are one hexahedron with different corners, and their outer dimensions *are* the
 * object. The rest are not hexahedra at all, and each is built from its own parts:
 *
 * - `Truss` — chords and bracing, because a truss's outer dimensions are a volume it barely fills
 *   and a solid box would hide the rig behind it. See {@see Truss}.
 * - `MovingHead` — base, yoke and head, because a moving head's shape is its identity and a box
 *   would be unrecognisable. See {@see MovingHead}.
 * - `Scaffold` — posts, bracing and a platform. See {@see Scaffold}.
 *
 * None of them is a loudspeaker, which is why {@see isCabinet} exists and why the builder skips
 * grille, handles, chamfer, drivers and the coverage cone for all three.
 */
enum Shape: string
{
    case Box = 'box';
    case Trapezoid = 'trapezoid';
    case Wedge = 'wedge';
    case Truss = 'truss';
    case MovingHead = 'moving-head';
    case Scaffold = 'scaffold';

    /**
     * A transporter's **load bay**, drawn as a wireframe volume inside a wireframe of the vehicle.
     *
     * **What you pack into is the inside, so the inside is what the model shows.** Stated by the owner: the vans
     * need at least wire-type models so a pack can be planned. A solid 6 m van would be the largest object in any
     * picture that included it and would hide the very thing it is there to help with; a cage shows the volume and
     * lets cabinets be seen inside it.
     */
    case LoadBay = 'load-bay';

    /**
     * Whether the shape is a **shell you put things inside**, so that something standing in it is the intended
     * state rather than two solids buried in each other.
     *
     * Only the load bay is, and the list is written out for the same reason {@see isCabinet}'s is: a truss and a
     * scaffold are *open* rather than hollow, their chords and posts are solid, and two of them in the same place
     * is a real fault that must go on being reported.
     *
     * **This is what makes a pack scene checkable at all.** Every unit in a pack stands inside its vehicle's box,
     * so the geometry sweep called each one 1.09 m inside the van and `scene:build` would have caged the whole
     * load in red. Asked of the shape rather than of {@see Category} because it is the geometry that decides: the
     * van is drawn as a cage precisely so the cabinets can be seen inside it.
     */
    public function isHollow(): bool
    {
        return match ($this) {
            self::LoadBay => true,
            self::Box, self::Trapezoid, self::Wedge, self::Truss, self::MovingHead, self::Scaffold => false,
        };
    }

    /**
     * Whether the shape is a loudspeaker cabinet, and so gets a grille, handle recesses and drivers.
     *
     * Asked as a question about the shape rather than about {@see Category}, because it is the *geometry*
     * that decides: a rack is `Box` and gets the same shell treatment a cabinet does, while a truss
     * cannot take a grille no matter what category it is filed under.
     *
     * Written as the list of shapes that ARE cabinets rather than as "not truss". There are three that are not
     * now, and a fourth added without thinking would otherwise quietly inherit a grille and a coverage cone.
     */
    public function isCabinet(): bool
    {
        return match ($this) {
            self::Box, self::Trapezoid, self::Wedge => true,
            self::Truss, self::MovingHead, self::Scaffold, self::LoadBay => false,
        };
    }
}
