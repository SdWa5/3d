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
    case Other = 'other';

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
            self::Rack => ['amp', 'network', 'shipping'],
            self::Stand => ['speaker-pole', 'tripod', 'riser', 'scaffold'],
            self::Other => null,
        };
    }
}
