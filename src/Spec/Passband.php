<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The band a cabinet covers.
 *
 * Together with {@see Power} it orders a {@see \App\Scene\Stack} by {@see LowOctave}, so the deepest cabinets end
 * up on the floor carrying everything. A pair where either cabinet has no power figure is ordered on `low_hz` and
 * then `high_hz`, see {@see FillOrder::byFillOrder}.
 *
 * There used to be a third number, `driven_from_hz`, for where a cabinet is high-passed in practice. It existed only
 * to sort the Achenbach above the Flexy, and power per area does that on its own since 0.137.0.
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
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $unknown = $reader->unknownKeys(['low_hz', 'high_hz', 'provenance']);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("audio.passband_hz: unknown key '%s' (allowed: low_hz, high_hz, provenance)", $unknown[0]));
        }

        return new self(
            lowHz: $reader->requireFloat('low_hz'),
            highHz: $reader->requireFloat('high_hz'),
            provenance: $reader->requireEnum('provenance', Provenance::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'low_hz' => $this->lowHz,
            'high_hz' => $this->highHz,
            'provenance' => $this->provenance->value,
        ];
    }
}
