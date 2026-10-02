<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The order cabinets are dealt into a stack: **subs before tops, and each group lowest-reaching first.**.
 *
 * Split out of {@see \App\Command\SceneStackCommand} because it is a fact about loudspeakers rather than about a
 * command — `scene:stack` asks it, and so does anything else that has to turn an inventory into a fill order.
 *
 * The comparator's own docblock carries the argument for what the key is and why the fallback is weight. It is
 * worth reading before changing it: an earlier version sorted on `quantity × width`, which is "the most numerous
 * cabinet on the floor", and put 40 kg subs under 220 kg ones.
 */
final class FillOrder
{
    /**
     * Every speaker in the library, subs before tops — the order the fill needs and the one nobody should
     * have to type out.
     *
     * @param list<DeviceSpec> $specs
     *
     * @return list<string>
     */
    public static function everySpeaker(array $specs): array
    {
        $subs = [];
        $tops = [];
        foreach ($specs as $spec) {
            if ('speaker' !== $spec->category->value || $spec->quantity < 1) {
                continue;
            }
            'sub' === $spec->subtype ? $subs[] = $spec : $tops[] = $spec;
        }

        usort($subs, self::byFillOrder());
        usort($tops, self::byFillOrder());

        return array_map(static fn (DeviceSpec $s): string => $s->id, [...$subs, ...$tops]);
    }

    /**
     * Deepest first, so the lowest cabinets end up on the floor carrying everything.
     *
     * **THE KEY IS FREQUENCY, STATED BY THE OWNER, AND IT DECIDES ONLY BETWEEN TWO CABINETS THAT BOTH STATE ONE.**
     * That second half is what makes it work, because it is the half the earlier frequency-first sort did not have.
     * That version read a missing passband as `INF` and fell back to `quantity × width`, which is "the most numerous
     * cabinet on the floor" and put the 40 kg IQ subs under the 220 kg wall basses with all four GMSS subs above six
     * Achenbachs. Nine of our ten speakers have no passband at all, so the fallback was doing nearly all of the work
     * and doing it on a row-making heuristic rather than on anything physical.
     *
     * **Weight is the fallback and it is a good one**, which is why nothing breaks. It is stated for every cabinet,
     * and it is what {@see \App\Scene\Gravity} and three separate comments in {@see \App\Scene\StackSolver} already
     * appeal to when they say weight belongs low and central.
     *
     * **Where both cabinets state a power figure, {@see LowOctave} decides**, since 0.137.0. Output per square metre
     * of front in the pair's lowest octave is the owner's answer to "which one plays lower", and a stack that put a
     * different cabinet on the floor than the low-end axis calls the lowest would contradict itself. On our gear it
     * orders SKRAM, Flexy, Achenbach, which is the order the driven corner used to force. Any pair where one side has
     * no power figure, which takes in every top, is ordered on the corner and then on mass as before.
     *
     * @return callable(DeviceSpec, DeviceSpec): int
     */
    public static function byFillOrder(): callable
    {
        return static function (DeviceSpec $a, DeviceSpec $b): int {
            // Power per area where both state it. A tie falls through to the corners, as an unrated pair does.
            $octave = LowOctave::compare($a, $b);
            if (null !== $octave && 0 !== $octave) {
                return $octave;
            }

            // **BOTH SIDES OR NEITHER, AND THAT GUARD IS THE WHOLE DIFFERENCE BETWEEN THIS AND THE VERSION THAT
            // BROKE.** The earlier frequency-first sort read a missing passband as `INF` and fell back to
            // `quantity × width`, which sorted every cabinet without one *above* every cabinet with one: the 40 kg
            // IQ subs went under the 220 kg wall basses and all four GMSS subs above six Achenbachs. Absence of a
            // measurement is not a measurement, so a pair where either side is silent is left for the mass to
            // decide rather than being ranked on a number one of them does not have.
            if (null !== $a->passband && null !== $b->passband) {
                $low = $a->passband->lowHz <=> $b->passband->lowHz;
                if (0 !== $low) {
                    return $low;
                }

                $high = $a->passband->highHz <=> $b->passband->highHz;
                if (0 !== $high) {
                    return $high;
                }
            }

            $mass = $b->weightKg <=> $a->weightKg;
            if (0 !== $mass) {
                return $mass;
            }

            return $b->quantity * $b->dimensions->width <=> $a->quantity * $a->dimensions->width;
        };
    }
}
