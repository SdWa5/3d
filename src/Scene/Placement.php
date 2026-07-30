<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;

/**
 * One entry in a scene: a device put somewhere, optionally on top of another entry, optionally
 * repeated along a step vector.
 *
 * `on` and `repeat` are what make a scene file worth writing instead of dragging cabinets around by
 * hand. A 14-cabinet sub wall is two lines, and a stack does not have to be re-measured every time
 * a spec's height changes — the compiler works the heights out from the specs.
 */
final class Placement
{
    /**
     * @param array{float, float}|null $at ground position; null means "inherit from `on`"
     * @param array{float, float, float}|null $repeatStep offset between repeats
     */
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly ?array $at,
        public readonly float $yawDeg,
        public readonly ?string $on,
        public readonly int $repeatCount,
        public readonly ?array $repeatStep,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $repeat = $reader->optionalSection('repeat');

        return new self(
            id: $reader->optionalString('id') ?? 'placement-'.$index,
            deviceId: $reader->requireString('device'),
            at: $reader->has('at') ? self::readGround($reader) : null,
            yawDeg: $reader->optionalFloat('yaw_deg', 0.0) ?? 0.0,
            on: $reader->optionalString('on'),
            repeatCount: $repeat?->optionalInt('count', 1) ?? 1,
            repeatStep: $repeat !== null && $repeat->has('step') ? $repeat->requireVector3('step') : null,
        );
    }

    /**
     * `at` is the ground position: two numbers, because the height comes from the floor or from
     * whatever the device sits on. A third number is accepted and ignored on purpose — writing
     * `[x, y, 0]` is a natural mistake and rejecting it would be pedantry.
     *
     * @return array{float, float}
     */
    private static function readGround(ArrayReader $reader): array
    {
        $raw = $reader->numberList('at');
        if (count($raw) < 2) {
            throw new \App\Spec\InvalidSpecException('at: expected [x, y]');
        }

        return [$raw[0], $raw[1]];
    }
}
