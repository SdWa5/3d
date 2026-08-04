<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * One of the three world axes, by the names a scene uses for them: X right, Y depth, Z up.
 *
 * An enum rather than a string compare so "which axis" is a closed set with an index attached, the same
 * shape {@see ArcMode} uses for its sign.
 */
enum Axis: string
{
    case X = 'x';
    case Y = 'y';
    case Z = 'z';

    public function index(): int
    {
        return match ($this) {
            self::X => 0,
            self::Y => 1,
            self::Z => 2,
        };
    }
}
