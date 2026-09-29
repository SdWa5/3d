<?php

declare(strict_types=1);

namespace App\Signal;

/**
 * One DSP output's limiter threshold, derived from what the cabinet on the far end can take.
 *
 * **This is the arithmetic the `AmpLimiterCalc` sheet in Drive does in formulas**, and it is four lines:
 *
 *     Vspk = sqrt(ohm x watt)              the RMS voltage the cabinet is rated for
 *     Vin  = Vspk / 10^(gain / 20)         the DSP output voltage that drives the amp to exactly that
 *     thr  = 20 x log10(Vin / 0.775)       the same voltage as a dBu threshold, which is what a DSP asks for
 *     head = dsp_max_dbu - thr             what is left above the threshold before the DSP itself clips
 *
 * It lives here rather than in a spreadsheet because a formula in a workbook cannot be tested and cannot be
 * diffed, and the Drive folder shows what that costs: four files named `AmpLimiterCalc.csv` in one directory
 * with three distinct sizes, and no way to say which one the rig is currently set to. See `docs/signal-chain.md`.
 *
 * **PARALLELLING CABINETS DOES NOT MOVE THE THRESHOLD**, and this is the one thing about the calculation that
 * is routinely got wrong. Two 8 ohm cabinets on one amplifier channel are a 4 ohm load drawing twice the power,
 * but each cabinet still sees the same voltage, and the voltage is what the limiter clamps. So `nominalOhm` and
 * `powerRmsW` are always **one cabinet's** figures, never the channel's total, and a channel driving six subs
 * gets the identical threshold to a channel driving one. What the extra cabinets change is whether the
 * amplifier can still deliver that voltage into the lower load, which is a question about the amplifier and is
 * not answered here.
 *
 * **A higher amplifier gain is not a better setting.** Gain moves the threshold down one-for-one, so every
 * 3 dB step up throws away 3 dB of the DSP's usable output range and runs the whole group that much closer to
 * the converter's noise floor. {@see lowestUsableGain()} picks from the other end: the lowest step on the DIP
 * switch that still leaves a stated margin.
 */
final class LimiterSetting
{
    /** 0 dBu, the reference every DSP threshold is stated against. */
    public const REFERENCE_VOLTAGE = 0.775;

    /**
     * @param float $nominalOhm one cabinet's nominal impedance, not the channel's total load
     * @param float $powerRmsW one cabinet's continuous RMS rating
     * @param float $ampGainDb the amplifier's voltage gain, as its DIP switches are actually set
     * @param float $dspMaxOutDbu the maximum output level of the DSP driving this amplifier
     *
     * @throws \InvalidArgumentException when an impedance or a power is not positive
     */
    public function __construct(
        public readonly float $nominalOhm,
        public readonly float $powerRmsW,
        public readonly float $ampGainDb,
        public readonly float $dspMaxOutDbu,
    ) {
        if ($nominalOhm <= 0.0) {
            throw new \InvalidArgumentException(sprintf('nominalOhm is %s — an impedance has to be positive', $nominalOhm));
        }

        if ($powerRmsW <= 0.0) {
            throw new \InvalidArgumentException(sprintf('powerRmsW is %s — a power rating has to be positive', $powerRmsW));
        }
    }

    /**
     * The RMS voltage across the cabinet at its rated power.
     */
    public function speakerVoltage(): float
    {
        return sqrt($this->nominalOhm * $this->powerRmsW);
    }

    /**
     * The DSP output voltage that drives the amplifier to exactly {@see speakerVoltage()}.
     */
    public function inputVoltage(): float
    {
        return $this->speakerVoltage() / 10 ** ($this->ampGainDb / 20);
    }

    /**
     * The limiter threshold to type into the DSP.
     */
    public function thresholdDbu(): float
    {
        return 20 * log10($this->inputVoltage() / self::REFERENCE_VOLTAGE);
    }

    /**
     * What is left between the threshold and the DSP's own ceiling.
     *
     * Negative means the DSP cannot reach the threshold at all, so the limiter would never engage and the
     * DSP would clip first. That is a broken setting rather than a tight one.
     */
    public function headroomDb(): float
    {
        return $this->dspMaxOutDbu - $this->thresholdDbu();
    }

    /**
     * Whether the DSP can actually produce the threshold this setting asks for.
     */
    public function withinDspOutputLimit(): bool
    {
        return $this->thresholdDbu() <= $this->dspMaxOutDbu;
    }

    /**
     * The same group at a different amplifier gain, for sweeping a DIP switch's steps.
     */
    public function atGain(float $ampGainDb): self
    {
        return new self($this->nominalOhm, $this->powerRmsW, $ampGainDb, $this->dspMaxOutDbu);
    }

    /**
     * The lowest gain step that still leaves `$minHeadroomDb` above the threshold, or null when none does.
     *
     * Lowest rather than highest on purpose: see the class docblock. Steps are read in ascending order, so an
     * unsorted DIP list is sorted here rather than trusted.
     *
     * @param list<float> $steps the gains the amplifier's DIP switches offer
     */
    public function lowestUsableGain(array $steps, float $minHeadroomDb = 6.0): ?float
    {
        sort($steps);

        foreach ($steps as $step) {
            if ($this->atGain($step)->headroomDb() >= $minHeadroomDb) {
                return $step;
            }
        }

        return null;
    }
}
