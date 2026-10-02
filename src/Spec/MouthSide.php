<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Which half of an upright front a horn's mouth opens in, read as the cabinet stands with its feet on the floor.
 *
 * **The fact the mouth pairing needs, and the only one.** Two horns whose mouths meet act as one larger mouth, and
 * {@see \App\Scene\MouthPairing} turns cabinets so they do. It changes rolls and never positions, so all it has to
 * know is which way a roll sends the mouth. A spec that states nothing is never turned, because nothing says that
 * turning it pairs anything.
 *
 * Two values rather than a position on the baffle, because the pairing only ever asks which side. The owner chose
 * the simplest form on 2026-10-02.
 */
enum MouthSide: string
{
    /** The mouth opens in the lower half, as the Flexy's does. */
    case Low = 'low';

    /** The mouth opens in the upper half. */
    case High = 'high';

    /**
     * Which way the mouth faces once the cabinet is rolled by `$rollDeg`, as a unit vector in the front plane.
     *
     * `[x, z]` with +x to the audience's right and +z up. Upright a low mouth faces down, and a quarter turn of 270°
     * sends the bottom of the box to +x, which is what the A3 render of 2026-10-02 showed: Flexys at 270° and 90° side
     * by side meet mouth to mouth when the 270° one stands on the left.
     *
     * @return array{int, int}
     */
    public function facing(float $rollDeg): array
    {
        $sign = self::Low === $this ? 1 : -1;

        return match ((int) round(fmod(fmod($rollDeg, 360.0) + 360.0, 360.0))) {
            0 => [0, -$sign],
            90 => [-$sign, 0],
            180 => [0, $sign],
            270 => [$sign, 0],
            default => [0, 0],
        };
    }
}
