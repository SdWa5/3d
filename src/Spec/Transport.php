<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * How a device travels, for the load side: the box it packs into and whether it may be laid down.
 *
 * **A device has an erected size and a transport size, and `dimensions_m` is the erected one.** A scene needs the
 * Wind Up stand at its full 4 m, and a van needs it at the 1.75 m it folds to. Until this block existed one field
 * carried both, so the packed convoy showed a mast standing out of a trailer (SPEC-15).
 *
 * **The box is in the device's own axes**, so the folded stand is still 1.75 m along its own height. Laying it down
 * is the pack's decision, not a fact about the stand, and {@see \App\Load\PackLayout} makes it.
 *
 * **`upright` is a fact the owner states**, such as a rack whose amplifiers hang from the front rails or a generator
 * full of fuel. Everything else may be turned onto a side or an end.
 *
 * **A packed box states where it comes from**, the way `provenance` does for the erected one, because no packed
 * size in this library was published as such. Both of ours are worked out from a datasheet length and a case or a
 * parts list, so they say `estimated`.
 *
 * Every key is optional, and a device with no block packs at its erected box and may be turned.
 */
final class Transport
{
    public const KEYS = ['dimensions_m', 'provenance', 'upright'];

    public function __construct(
        /** The packed box, or null when the device packs at its erected size. */
        public readonly ?Dimensions $dimensions,
        public readonly bool $upright,
        /** Where the packed box comes from. Required with a box, and null without one. */
        public readonly ?Provenance $provenance = null,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $unknown = $reader->unknownKeys(self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("transport: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', self::KEYS)));
        }

        $dimensions = $reader->optionalSection('dimensions_m');

        return new self(
            null !== $dimensions ? Dimensions::fromReader($dimensions) : null,
            $reader->optionalBool('upright'),
            null !== $dimensions || $reader->has('provenance') ? $reader->requireEnum('provenance', Provenance::class) : null,
        );
    }

    /**
     * @return array{dimensions_m: array{width: float, height: float, depth: float}|null, provenance: string|null, upright: bool}
     */
    public function toArray(): array
    {
        return ['dimensions_m' => $this->dimensions?->toArray(), 'provenance' => $this->provenance?->value, 'upright' => $this->upright];
    }
}
