<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\LowEndCost;
use App\Spec\DeviceSpec;
use App\Spec\LowOctave;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Which sub the low-end axis is about: the one with the most output per square metre of front in the pair's lowest
 * octave, the measure the owner set on 2026-10-02.
 */
final class LowestTypeTest extends TestCase
{
    /** The owner's own reading of the gear list: the SKRAM goes lowest, and clearly. */
    public function testTheSkramIsTheLowestTypeOfOurGearInEitherOrder(): void
    {
        $d = self::devices();

        self::assertSame('skram', LowEndCost::lowestType([
            [$d['flexy-folded-horn-hybrid'], 12], [$d['achenbach-18'], 4], [$d['skram'], 2],
        ]));
        self::assertSame('skram', LowEndCost::lowestType([
            [$d['skram'], 2], [$d['achenbach-18'], 4], [$d['flexy-folded-horn-hybrid'], 12],
        ]));
    }

    /**
     * 14.7 dB over 15–30 Hz, so a Flexy would need 29 times its own power per area to take the floor from a SKRAM.
     * Averaged as decibels the margin would be meaningless, and averaged over a band reaching an octave past the
     * Flexy it was 2 dB, which the owner rejected as far too narrow for a cabinet reaching that much lower.
     */
    public function testTheSkramLeadsTheFlexyByFourteenPointSevenDecibelsInItsLowestOctave(): void
    {
        $d = self::devices();

        self::assertEqualsWithDelta(
            14.70,
            LowOctave::level($d['skram'], 15.0, 15.0)
                - LowOctave::level($d['flexy-folded-horn-hybrid'], 38.0, 15.0),
            0.01,
        );
    }

    /**
     * The Achenbach reaches 35 Hz against the Flexy's 38 and still loses the 35–70 Hz octave by 1.4 dB, on 1800 W over
     * 0.451 m² against 1000 W over 0.360 m². This is the order `driven_from_hz` used to force.
     */
    public function testTheFlexyBeatsTheDeeperAchenbachOnPowerPerArea(): void
    {
        $d = self::devices();

        self::assertSame('flexy-folded-horn-hybrid', LowEndCost::lowestType([
            [$d['achenbach-18'], 4], [$d['flexy-folded-horn-hybrid'], 12],
        ]));
        self::assertEqualsWithDelta(
            1.43,
            LowOctave::level($d['flexy-folded-horn-hybrid'], 38.0, 35.0) - LowOctave::level($d['achenbach-18'], 35.0, 35.0),
            0.01,
        );
    }

    /** From the same corner the octave is the same for both, so power per area decides on its own. */
    public function testTwoCabinetsFromTheSameCornerAreDecidedByPowerPerArea(): void
    {
        self::assertSame('loud', LowEndCost::lowestType([
            [self::sub('quiet', 38.0, 1000.0), 2], [self::sub('loud', 38.0, 1100.0), 2],
        ]));
    }

    /** Reach is worth 15.6 dB from 38 Hz down to 15 and not everything: 40 times the power per area beats it, 30 does not. */
    public function testACabinetFrom38HzNeedsAbout36TimesThePowerPerAreaOfOneFrom15(): void
    {
        $deep = self::sub('deep', 15.0, 1000.0);

        self::assertSame('deep', LowEndCost::lowestType([[$deep, 2], [self::sub('shallow', 38.0, 30000.0), 2]]));
        self::assertSame('shallow', LowEndCost::lowestType([[$deep, 2], [self::sub('shallow', 38.0, 40000.0), 2]]));
    }

    /** The owner's choice for a sub with no figure: the pair is decided as before, on the corner alone. */
    public function testAPairWithoutAPowerFigureFallsBackToTheCorner(): void
    {
        $unrated = self::sub('unrated', 30.0, null);

        self::assertSame('unrated', LowEndCost::lowestType([[self::sub('rated', 38.0, 99000.0), 2], [$unrated, 2]]));
    }

    /** One square metre of front, so power per area is the power itself. */
    private static function sub(string $id, float $lowHz, ?float $rmsW): DeviceSpec
    {
        $audio = ['passband_hz' => ['low_hz' => $lowHz, 'high_hz' => 200, 'provenance' => 'estimated']];
        if (null !== $rmsW) {
            $audio['power_w'] = ['rms' => $rmsW, 'provenance' => 'estimated'];
        }

        return SpecFactory::spec([
            'id' => $id,
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => 1.0, 'height' => 1.0, 'depth' => 0.8]],
            'audio' => $audio,
        ]);
    }

    /** @return array<string, DeviceSpec> */
    private static function devices(): array
    {
        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }

        return $devices;
    }
}
