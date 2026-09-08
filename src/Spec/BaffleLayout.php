<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * What is on a cabinet's front baffle, and how well it is known.
 *
 * `provenance` is mandatory: the positions of horns and drivers on a baffle are measurements like any
 * other, and two of the three layouts in this library are honest guesses off a photo and an outer size.
 * Without it, a generated baffle would look exactly as authoritative as one taken off a drawing.
 */
final class BaffleLayout
{
    /**
     * @param list<BaffleFeature> $features
     */
    public function __construct(
        public readonly Provenance $provenance,
        public readonly float $insetM,
        public readonly array $features,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        $features = [];
        foreach ($reader->sectionList('features') as $index => $entry) {
            $features[] = BaffleFeature::fromReader($entry, $index + 1);
        }

        return new self(
            $reader->requireEnum('provenance', Provenance::class),
            // How far the baffle sits behind the outer front face. A CAD cabinet often recesses it
            // well back, and a feature drawn at the outer plane would then float in front of the box.
            $reader->optionalFloat('inset_m', 0.0) ?? 0.0,
            $features,
        );
    }

    public function feature(string $id): ?BaffleFeature
    {
        foreach ($this->features as $feature) {
            if ($feature->id === $id) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provenance' => $this->provenance->value,
            'inset_m' => $this->insetM,
            'features' => array_map(
                static fn (BaffleFeature $feature): array => $feature->toArray(),
                $this->features,
            ),
        ];
    }
}
