<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Top-level equipment class. Speakers come first because they are what the library starts
 * with; the rest exist so truss, amp racks and stands can be added without a schema change.
 */
enum Category: string
{
    case Speaker = 'speaker';
    case Truss = 'truss';
    case Rack = 'rack';
    case Stand = 'stand';

    /**
     * A transporter. **The only category that is a container rather than a thing to be placed.**
     *
     * It earns a case of its own rather than living under `other/vehicle`, which would have needed no schema change
     * at all. Two reasons, and the second is the real one. A van is not "gear that does not fit the taxonomy yet",
     * which is what `Other` means. And it needs fields nothing else has — a legal permitted mass and an inside as
     * well as an outside — so the validated block was owed whatever the category said, and `other` would only have
     * bought a category that lied about it. Stated by the owner: a transporter belongs in `specs/`.
     *
     * @see \App\Spec\Vehicle
     */
    case Vehicle = 'vehicle';

    case Other = 'other';

    /**
     * Whether a device of this category is turned into geometry — a `.glb`, a `.blend` and a slot in the asset
     * library — or exists only as numbers.
     *
     * **Only a vehicle answers no, and it is the first category that ever has.** Everything else in the library is
     * something you put in a picture. A transporter is the thing the picture's contents are driven to the gig in: it
     * is never placed in a scene, and a 6.8 m white box parked beside a rig would be the largest object in every
     * render that included it. Without this, `models:build` hands Blender a van, `library:build` puts it on the
     * shelf beside the cabinets, and both are work nobody asked for producing an asset nobody wants.
     */
    public function producesAModel(): bool
    {
        return $this !== self::Vehicle;
    }

    /**
     * Subtypes considered valid for this category. `Other` accepts anything, which is the
     * escape hatch for gear that does not fit the taxonomy yet.
     *
     * @return list<string>|null null = any subtype allowed
     */
    public function allowedSubtypes(): ?array
    {
        return match ($this) {
            self::Speaker => ['top', 'sub', 'monitor', 'line-array-element'],
            self::Truss => ['straight', 'corner', 'base', 'tower'],
            self::Rack => ['amp', 'network', 'power', 'shipping'],
            self::Stand => ['speaker-pole', 'tripod', 'riser', 'scaffold'],
            // A trailer is a load bay with no cab and no payload of its own, so it reads the same block. Neither
            // is modelled or placed today; what they are here for is the pack.
            self::Vehicle => ['van', 'trailer'],
            self::Other => null,
        };
    }
}
