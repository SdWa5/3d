<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Where a spec's dimensions and its weight each come from, tracked separately.
 *
 * They diverge in practice: a hanging scale settles a cabinet's weight in a minute, while taping
 * fourteen subs is an afternoon. One field for both meant a weighed-but-unmeasured cabinet still
 * reported `plans`, so the easy half of the work showed no progress at all.
 */
final class ProvenanceSet
{
    public function __construct(
        public readonly Provenance $dimensions,
        public readonly Provenance $weight,
    ) {
    }

    /**
     * Accepts either the split form or a single value standing for both — the shorthand is worth
     * keeping, because a fresh spec usually has one source for everything.
     *
     * ```yaml
     * provenance: plans                 # both
     * provenance:                       # or per field
     *   dimensions: plans
     *   weight: measured
     * ```
     */
    public static function fromReader(ArrayReader $reader, string $key): self
    {
        if (!$reader->isSection($key)) {
            $both = $reader->requireEnum($key, Provenance::class);

            return new self($both, $both);
        }

        $section = $reader->requireSection($key);

        return new self(
            $section->requireEnum('dimensions', Provenance::class),
            $section->requireEnum('weight', Provenance::class),
        );
    }

    /**
     * The weakest of the two — what to show when only one value fits, e.g. a table column.
     */
    public function weakest(): Provenance
    {
        $order = [
            Provenance::Estimated->value => 0,
            Provenance::Datasheet->value => 1,
            Provenance::Plans->value => 2,
            Provenance::Measured->value => 3,
        ];

        return $order[$this->dimensions->value] <= $order[$this->weight->value]
            ? $this->dimensions
            : $this->weight;
    }

    public function isFullyMeasured(): bool
    {
        return $this->dimensions->isMeasured() && $this->weight->isMeasured();
    }

    /**
     * Compact label for tables: one word when both agree, `dims/weight` when they do not.
     */
    public function label(): string
    {
        return $this->dimensions === $this->weight
            ? $this->dimensions->value
            : $this->dimensions->value.'/'.$this->weight->value;
    }

    /**
     * @return array{dimensions: string, weight: string}
     */
    public function toArray(): array
    {
        return ['dimensions' => $this->dimensions->value, 'weight' => $this->weight->value];
    }
}
