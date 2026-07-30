<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One driver complement of a cabinet — what is fitted, for the catalog and the exported metadata.
 *
 * Sizes stay in inches because that is how the audio world names them (a "15 inch" woofer). Where the
 * drivers and horns physically sit on the baffle is a separate matter, and a richer one: see
 * BaffleLayout.
 */
final class Driver
{
    public function __construct(
        public readonly float $sizeIn,
        public readonly string $type,
        public readonly int $count,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('size_in'),
            $reader->requireString('type'),
            $reader->optionalInt('count', 1) ?? 1,
        );
    }

    /**
     * @return array{size_in: float, type: string, count: int}
     */
    public function toArray(): array
    {
        return ['size_in' => $this->sizeIn, 'type' => $this->type, 'count' => $this->count];
    }
}
