<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The continuous electrical power a cabinet takes, in watts.
 *
 * **One number, the continuous rating.** Datasheets state it as RMS, AES or continuous, which are the same claim
 * made under different test signals, and that is the figure recorded here. Programme and peak ratings are not, so a
 * datasheet that publishes only those gets a value derived from them and `provenance: estimated`, with the
 * derivation written in the spec's notes.
 *
 * It is what {@see LowOctave} weighs, per square metre of front, to decide which of two subs plays lower, for the fill
 * order and for the lowest type of an inventory. It is an electrical rating and says nothing about sensitivity, which
 * no spec records.
 *
 * `provenance` is required for the same reason {@see Passband} requires one: a wattage is trivially easy to invent
 * and silently decides which cabinet the low-end axis is about.
 */
final class Power
{
    public function __construct(
        public readonly float $rmsW,
        public readonly Provenance $provenance,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $unknown = $reader->unknownKeys(['rms', 'provenance']);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("audio.power_w: unknown key '%s' (allowed: rms, provenance)", $unknown[0]));
        }

        return new self(
            rmsW: $reader->requireFloat('rms'),
            provenance: $reader->requireEnum('provenance', Provenance::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rms' => $this->rmsW,
            'provenance' => $this->provenance->value,
        ];
    }
}
