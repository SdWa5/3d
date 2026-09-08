<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;

/**
 * Copies along a stated step vector — the one-dimensional shorthand, and the oldest group there is.
 *
 * Kept as itself rather than folded into {@see Lattice}, which can express the same thing, for one reason
 * that matters and one that is merely convenient. The reason that matters is the anchor: a repeat anchors
 * on its **last** copy, so `on:` a sub row stacks on the far end of it, and seven of the shipped scenes
 * depend on that. A lattice anchors on the middle cell, because that is what lets a single cabinet be
 * swapped for a group without moving. One class cannot honestly own both rules without a flag whose only
 * purpose is to remember which spelling it was written as.
 *
 * The convenient reason: a repeat derives nothing. Its step is stated outright, so it ignores the cell box
 * entirely, and a class whose whole point is derived spacing would carry a "do not derive" branch forever.
 *
 * New scenes are better off with `row` or `lattice`, which work the spacing out from the cabinets. This
 * stays because `repeat: { count: 2, step: [0.64, 0, 0] }` is still the clearest way to say exactly that.
 */
final class Repeat implements Group
{
    /**
     * @param array{float, float, float}|null $step
     */
    public function __construct(
        public readonly int $count,
        public readonly ?array $step,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['count', 'step'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("repeat: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        return new self(
            count: $reader->optionalInt('count', 1) ?? 1,
            step: $reader->has('step') ? $reader->requireVector3('step') : null,
        );
    }

    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $step = $this->step ?? [0.0, 0.0, 0.0];
        $last = $this->count - 1;
        $numbered = $this->count > 1;

        $copies = [];
        for ($index = 0; $index <= $last; ++$index) {
            $copies[] = new PlacementCopy(
                $numbered ? [$index + 1] : [],
                [$step[0] * $index, $step[1] * $index, $step[2] * $index],
                null,
                $index === $last,
            );
        }

        return $copies;
    }

    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        if ($this->count < 1) {
            return ["repeat.count must be at least 1, got {$this->count}"];
        }
        if ($this->count > 1 && null === $this->step) {
            return ['repeat.count > 1 needs a repeat.step'];
        }

        return [];
    }

    public function copyCount(): int
    {
        return $this->count;
    }

    public function kind(): string
    {
        return 'repeat';
    }

    /** A step vector moves copies without turning them. */
    /** A step vector moves copies without tilting them. */
    public function decidesPitch(): bool
    {
        return false;
    }

    public function decidesYaw(): bool
    {
        return false;
    }
}
