<?php

declare(strict_types=1);

namespace App\Tests\Signal;

use App\Signal\LimiterSetting;
use PHPUnit\Framework\TestCase;

/**
 * The limiter arithmetic, checked against the figures the `AmpLimiterCalc` sheet in Drive produces.
 *
 * Every expected value below is one the spreadsheet already computed, so these cases are a port check as much
 * as a unit test. If one of them ever has to change, the sheet and this class have diverged and the reason
 * belongs in `docs/signal-chain.md` before either side is touched.
 */
final class LimiterSettingTest extends TestCase
{
    /** The eight steps both the GISEN MM14K and the Tulun TIP10000q offer on their DIP switches. */
    private const DIP_STEPS = [23.0, 26.0, 29.0, 32.0, 35.0, 38.0, 41.0, 44.0];

    /**
     * A Flexy folded-horn sub on an MM14K at 32 dB, off the 8x8 whose ceiling is 18 dBu.
     *
     * The sheet reports 120 V, 3.014263718 V and 11.79759087 dBu for this row, and these are those numbers.
     */
    public function testAFlexySubMatchesTheSheet(): void
    {
        $setting = new LimiterSetting(nominalOhm: 8.0, powerRmsW: 1800.0, ampGainDb: 32.0, dspMaxOutDbu: 18.0);

        self::assertSame(120.0, $setting->speakerVoltage());
        self::assertEqualsWithDelta(3.0142637, $setting->inputVoltage(), 1e-7);
        self::assertEqualsWithDelta(11.7975909, $setting->thresholdDbu(), 1e-7);
        self::assertEqualsWithDelta(6.2024091, $setting->headroomDb(), 1e-7);
        self::assertTrue($setting->withinDspOutputLimit());
    }

    /** The Tecnare's LF channel is the only 4 ohm group in the rig, and it runs off the DCX at 22 dBu. */
    public function testTheTecnareLowChannelMatchesTheSheet(): void
    {
        $setting = new LimiterSetting(nominalOhm: 4.0, powerRmsW: 1200.0, ampGainDb: 26.0, dspMaxOutDbu: 22.0);

        self::assertEqualsWithDelta(69.2820323, $setting->speakerVoltage(), 1e-7);
        self::assertEqualsWithDelta(13.0263783, $setting->thresholdDbu(), 1e-7);
        self::assertEqualsWithDelta(8.9736217, $setting->headroomDb(), 1e-7);
    }

    /**
     * Two cabinets on one amplifier channel get the same threshold as one.
     *
     * The load halves and the power doubles, but the voltage across each cabinet does not move, and the
     * voltage is the whole of what a limiter clamps. This is the mistake the class exists to prevent, so it
     * is stated as a test rather than only as a comment: passing the channel's 4 ohm total instead of the
     * cabinet's 8 would set the threshold 3 dB low and quietly cost the rig half its output.
     */
    public function testParallellingCabinetsDoesNotMoveTheThreshold(): void
    {
        $one = new LimiterSetting(8.0, 1800.0, 32.0, 18.0);
        $six = new LimiterSetting(8.0, 1800.0, 32.0, 18.0);

        self::assertSame($one->thresholdDbu(), $six->thresholdDbu());

        $wrong = new LimiterSetting(nominalOhm: 4.0, powerRmsW: 1800.0, ampGainDb: 32.0, dspMaxOutDbu: 18.0);
        self::assertEqualsWithDelta(-3.0103, $wrong->thresholdDbu() - $one->thresholdDbu(), 1e-4, 'the channel total is 3 dB wrong');
    }

    /**
     * At 23 dB the sub's threshold lands above the 8x8's own ceiling, so the DSP clips before the limiter works.
     */
    public function testAGainTooLowPutsTheThresholdOutOfTheDspsReach(): void
    {
        $setting = new LimiterSetting(8.0, 1800.0, 23.0, 18.0);

        self::assertEqualsWithDelta(20.7975909, $setting->thresholdDbu(), 1e-7);
        self::assertFalse($setting->withinDspOutputLimit());
        self::assertLessThan(0.0, $setting->headroomDb());
    }

    /**
     * The recommendation for the Mark Salzburg rig: 32 dB on the MM14K, 26 dB on the TIP.
     *
     * Both are the lowest step on the same eight-position DIP switch that still clears 6 dB, which is what
     * keeps the group as far above the converter's noise floor as the margin allows.
     */
    public function testTheLowestStepClearingTheMarginIsTheOneToSet(): void
    {
        $sub = new LimiterSetting(8.0, 1800.0, 32.0, 18.0);
        $tecnareLow = new LimiterSetting(4.0, 1200.0, 26.0, 22.0);
        $tecnareHigh = new LimiterSetting(8.0, 350.0, 26.0, 22.0);

        self::assertSame(32.0, $sub->lowestUsableGain(self::DIP_STEPS));
        self::assertSame(26.0, $tecnareLow->lowestUsableGain(self::DIP_STEPS));
        self::assertSame(23.0, $tecnareHigh->lowestUsableGain(self::DIP_STEPS), 'the high channel would take a lower step');
    }

    /** One amplifier, one DIP switch: the binding group decides, and here that is the LF channel. */
    public function testOneAmplifierTakesTheStepItsMostDemandingGroupNeeds(): void
    {
        $tecnareLow = new LimiterSetting(4.0, 1200.0, 26.0, 22.0);
        $tecnareHigh = new LimiterSetting(8.0, 350.0, 26.0, 22.0);

        $shared = max(
            $tecnareLow->lowestUsableGain(self::DIP_STEPS),
            $tecnareHigh->lowestUsableGain(self::DIP_STEPS),
        );

        self::assertSame(26.0, $shared);
        self::assertGreaterThanOrEqual(6.0, $tecnareHigh->atGain($shared)->headroomDb());
    }

    /** A margin nothing on the switch can meet is reported as nothing, not as the least bad step. */
    public function testAnUnreachableMarginReturnsNull(): void
    {
        self::assertNull((new LimiterSetting(8.0, 1800.0, 32.0, 18.0))->lowestUsableGain(self::DIP_STEPS, 30.0));
    }

    /** A DIP list in whatever order the manual prints it still yields the lowest usable step. */
    public function testTheStepsAreSortedRatherThanTrusted(): void
    {
        $setting = new LimiterSetting(8.0, 1800.0, 32.0, 18.0);

        self::assertSame(32.0, $setting->lowestUsableGain([44.0, 32.0, 41.0, 23.0, 35.0, 26.0, 38.0, 29.0]));
    }

    public function testAnImpossibleImpedanceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('an impedance has to be positive');

        new LimiterSetting(0.0, 1800.0, 32.0, 18.0);
    }

    public function testAnImpossiblePowerRatingIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('a power rating has to be positive');

        new LimiterSetting(8.0, -1.0, 32.0, 18.0);
    }
}
