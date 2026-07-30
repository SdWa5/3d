<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One driver complement of a cabinet. Sizes stay in inches because that is how the audio world
 * names them (a "15 inch" woofer), and the builder uses them only to size the grille cut-outs —
 * no internal components are modelled.
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
