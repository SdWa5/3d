<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Which of two subs plays lower, by its output per square metre of front in the lowest octave of the pair.
 *
 * **The measure the owner set on 2026-10-02**, and the one both the fill order and the low-end axis ask. Each
 * cabinet's level is its continuous power over its whole front area, `10·log10(P / A)`, the front measured as
 * width × height of the box rather than the horn mouth. It is taken as flat down to its `low_hz` and falling
 * {@see ROLL_OFF_DB_PER_OCTAVE} below, and averaged as power over the octave above the deeper cabinet's corner.
 * That octave is where the question "which one plays lower" lives. Above it both play and power per area decides on
 * its own, which the base level already says.
 *
 * **Averaged as power, not as decibels.** An average of decibels counts a cabinet that is 30 dB down for far more
 * than the energy it actually lacks, and that is what an earlier proposal got wrong.
 *
 * **It replaced the driven corner.** `driven_from_hz` existed so the Achenbach, which reaches 35 Hz, would sort above
 * the Flexy at 38. On its own 35 Hz it still loses to the Flexy by 1.4 dB, because a Flexy has 3991 W/m² against
 * 2778, so the measure keeps that order without being told to.
 */
final class LowOctave
{
    /**
     * Below this a cabinet counts as low end at all. 120 Hz is where a sub stops being a sub in every crossover
     * anybody here runs.
     */
    public const LOW_END_HZ = 120.0;

    /**
     * How fast a sub falls below its corner, in dB per octave.
     *
     * 24, the slope of a fourth-order high-pass, which is what a vented or horn-loaded box rolls off at below its
     * tuning. It is an assumption, not a measurement of any of our cabinets, and it sets the size of a reach
     * advantage. At 12 dB a cabinet reaching 38 Hz would need 7.6 times a 15 Hz cabinet's power per area to beat it
     * rather than 36.
     */
    public const ROLL_OFF_DB_PER_OCTAVE = 24.0;

    /**
     * Negative when `$a` plays lower, positive when `$b` does, and null when the measure cannot decide.
     *
     * Null is any pair where either cabinet lacks a passband under {@see LOW_END_HZ} or a power rating, which takes
     * in every top and every unrated sub. The caller decides those the way it did before, which is the owner's
     * choice for a sub without a figure. An exact tie returns 0 and is the caller's to break as well.
     */
    public static function compare(DeviceSpec $a, DeviceSpec $b): ?int
    {
        if (!self::rated($a) || !self::rated($b)) {
            return null;
        }

        \assert(null !== $a->passband && null !== $b->passband);
        $from = min($a->passband->lowHz, $b->passband->lowHz);

        return self::level($b, $b->passband->lowHz, $from) <=> self::level($a, $a->passband->lowHz, $from);
    }

    /**
     * How loud a cabinet is per square metre of front over the octave from `$fromHz`, in dB relative to 1 W/m².
     *
     * The roll-off is integrated in closed form over the octave on a log-frequency axis, with `d` the number of
     * octaves the cabinet's corner sits above the octave's start. Below its corner the power falls by `10^(-k·u)`
     * with `k` the roll-off in bels per octave, and above it the power is flat.
     */
    public static function level(DeviceSpec $device, float $cornerHz, float $fromHz): float
    {
        \assert(null !== $device->power);
        $area = $device->dimensions->width * $device->dimensions->height;
        $k = self::ROLL_OFF_DB_PER_OCTAVE / 10.0;
        $c = $k * M_LN10;
        $d = max(0.0, log($cornerHz / $fromHz, 2));
        $mean = $d >= 1.0
            ? 10 ** (-$k * $d) * (10 ** $k - 1.0) / $c
            : (1.0 - 10 ** (-$k * $d)) / $c + (1.0 - $d);

        return 10.0 * log10($device->power->rmsW / $area * $mean);
    }

    private static function rated(DeviceSpec $device): bool
    {
        return null !== $device->power && null !== $device->passband && $device->passband->lowHz < self::LOW_END_HZ;
    }
}
