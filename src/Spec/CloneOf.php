<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The commercial design a DIY cabinet copies. Mandatory for `build: clone`, because the
 * original's identity is what makes its datasheet usable as a source — without brand and model
 * there is nothing to look up, and the spec is stuck at guessed numbers.
 */
final class CloneOf
{
    /**
     * Placeholder for a cabinet known to be a copy of something whose original nobody has written
     * down yet. Allowed on purpose — it keeps the gap visible instead of pretending the build is an
     * own design — and `inventory:import` is what fills it in.
     */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $manufacturer,
        public readonly string $model,
        public readonly string $reference = 'none',
        public readonly ?string $url = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireString('manufacturer'),
            $reader->requireString('model'),
            $reader->optionalString('reference', 'none') ?? 'none',
            $reader->optionalString('url'),
        );
    }

    public function isIdentified(): bool
    {
        return $this->manufacturer !== self::UNKNOWN && $this->model !== self::UNKNOWN;
    }

    public function label(): string
    {
        return $this->isIdentified() ? "{$this->manufacturer} {$this->model}" : self::UNKNOWN;
    }

    /**
     * @return array{manufacturer: string, model: string, reference: string, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'reference' => $this->reference,
            'url' => $this->url,
        ];
    }
}
