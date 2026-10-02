<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\DeviceSpec;
use App\Spec\LowOctave;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The pair measure the fill order and the low-end axis share.
 */
final class LowOctaveTest extends TestCase
{
    /**
     * **The measure is consistent over the whole library**, which `usort` relies on and the measure does not promise.
     * Each pair is judged over its own octave, so three cabinets could in principle beat each other in a circle. On
     * today's rated subs they do not, and once sorted every cabinet beats every one after it.
     */
    public function testEveryRatedSubBeatsEveryOneSortedAfterIt(): void
    {
        $rated = array_values(array_filter(
            (new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'],
            static fn (DeviceSpec $s): bool => null !== LowOctave::compare($s, $s),
        ));
        self::assertCount(7, $rated, 'seven subs state both a passband and a power figure');

        usort($rated, static fn (DeviceSpec $a, DeviceSpec $b): int => LowOctave::compare($a, $b) ?? 0);
        foreach ($rated as $i => $a) {
            foreach (\array_slice($rated, $i + 1) as $b) {
                self::assertLessThan(0, LowOctave::compare($a, $b), "{$a->id} before {$b->id}");
            }
        }
    }

    /** A top states no power and a sub with no passband has no corner, so neither pair can be decided here. */
    public function testAPairWithoutBothFiguresIsLeftToTheCaller(): void
    {
        $rated = self::sub('rated', ['low_hz' => 38, 'high_hz' => 200, 'provenance' => 'estimated'], 1800.0);

        self::assertNull(LowOctave::compare($rated, self::sub('unrated', ['low_hz' => 30, 'high_hz' => 200, 'provenance' => 'estimated'], null)));
        self::assertNull(LowOctave::compare(self::sub('silent', null, 1600.0), $rated));
        self::assertNull(LowOctave::compare($rated, self::sub('top', ['low_hz' => 130, 'high_hz' => 18000, 'provenance' => 'estimated'], 400.0)));
    }

    /** @param ?array<string, mixed> $passband */
    private static function sub(string $id, ?array $passband, ?float $rmsW): DeviceSpec
    {
        $audio = [];
        if (null !== $passband) {
            $audio['passband_hz'] = $passband;
        }
        if (null !== $rmsW) {
            $audio['power_w'] = ['rms' => $rmsW, 'provenance' => 'estimated'];
        }

        return SpecFactory::spec(['id' => $id, 'subtype' => 'sub', 'audio' => $audio]);
    }
}
