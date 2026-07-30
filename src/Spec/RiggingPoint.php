<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A single suspension point, positioned in the model's own coordinate system (metres, relative
 * to the spec's `origin`). The builder puts a small marker there so a flown setup can be
 * assembled by snapping to real hardware positions instead of eyeballing them.
 */
final class RiggingPoint
{
    /**
     * @param array{float, float, float} $position
     */
    public function __construct(
        public readonly string $id,
        public readonly array $position,
        public readonly ?string $thread = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireString('id'),
            $reader->requireVector3('position_m'),
            $reader->optionalString('thread'),
        );
    }

    /**
     * @return array{id: string, position_m: array{float, float, float}, thread: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'position_m' => $this->position, 'thread' => $this->thread];
    }
}
