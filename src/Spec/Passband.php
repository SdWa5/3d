<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The band a cabinet covers, and the band it is actually driven over.
 *
 * Two numbers rather than one, because they are two different facts and conflating them loses the more
 * useful of the two. `low_hz`/`high_hz` are what the cabinet *can* do; `driven_from_hz` is where it is
 * high-passed in practice. Our Achenbachs reach 35 Hz and are run from 38 — the same corner as the Flexys —
 * on purpose, so that they sit *above* the Flexys in a stack rather than under them. Overwriting the 35 with
 * the 38 would have thrown away a real property of the cabinet to record an operating choice.
 *
 * This is what orders a {@see \App\Scene\Stack}: lowest first, so the deepest cabinets end up on the floor
 * carrying everything. Ordering on **`orderingLowHz`** — the driven corner where there is one — is
 * deliberate, because a stack is built the way the rig is actually driven, not the way a datasheet reads.
 *
 * `provenance` is required for the same reason {@see BaffleLayout} requires one: a frequency is trivially
 * easy to invent, impossible to check by looking at a render, and it silently decides the order every
 * generated rig comes out in.
 */
final class Passband
{
    public function __construct(
        public readonly float $lowHz,
        public readonly float $highHz,
        public readonly Provenance $provenance,
        /** Where it is high-passed in practice, when that is deliberately not its low corner. */
        public readonly ?float $drivenFromHz = null,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $unknown = $reader->unknownKeys(['low_hz', 'high_hz', 'driven_from_hz', 'provenance']);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("audio.passband_hz: unknown key '%s' (allowed: low_hz, high_hz, driven_from_hz, provenance)", $unknown[0]));
        }

        return new self(
            lowHz: $reader->requireFloat('low_hz'),
            highHz: $reader->requireFloat('high_hz'),
            provenance: $reader->requireEnum('provenance', Provenance::class),
            drivenFromHz: $reader->optionalFloat('driven_from_hz'),
        );
    }

    /**
     * The corner a stack is ordered on: how it is driven where that is stated, otherwise what it reaches.
     */
    public function orderingLowHz(): float
    {
        return $this->drivenFromHz ?? $this->lowHz;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'low_hz' => $this->lowHz,
            'high_hz' => $this->highHz,
            'driven_from_hz' => $this->drivenFromHz,
            'provenance' => $this->provenance->value,
        ];
    }
}
