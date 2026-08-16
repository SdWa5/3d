<?php

declare(strict_types=1);

namespace App\Load;

use App\Spec\DeviceSpec;

/**
 * One vehicle and what the planner put in it, plus the two verdicts that decide whether it may leave the yard.
 *
 * **The two verdicts are separate and one of them cannot be given.** Weight is a legal limit with a number on a
 * registration document behind it, so "over by 40 kg" is a fact. Space is a sum of bounding boxes, so it can say a
 * load definitely will *not* fit and can never say it will — boxes do not tessellate, a horn mouth is not a brick,
 * and nothing here places anything. Reporting one verdict, or a single pass/fail, hides the case that matters most:
 * a load that fits the bay comfortably and is 300 kg over the axle.
 */
final class LoadPlan
{
    /**
     * @param list<array{spec: DeviceSpec, count: int}> $items what is in this vehicle, heaviest unit first
     */
    public function __construct(
        public readonly DeviceSpec $vehicle,
        public readonly array $items,
        public readonly float $payloadKg,
        public readonly ?float $bayM3,
    ) {
    }

    public function weightKg(): float
    {
        $weight = 0.0;
        foreach ($this->items as ['spec' => $spec, 'count' => $count]) {
            $weight += $spec->weightKg * $count;
        }

        return $weight;
    }

    /**
     * **A lower bound on the space needed, never the space used.** It is the sum of bounding-box volumes, so it
     * counts the air around every wedge and inside every horn flare, and counts no aisle, no strapping and no
     * stacking rule. See {@see fitsTheBay}.
     */
    public function volumeM3(): float
    {
        $volume = 0.0;
        foreach ($this->items as ['spec' => $spec, 'count' => $count]) {
            $volume += $spec->dimensions->volumeM3() * $count;
        }

        return $volume;
    }

    public function units(): int
    {
        return array_sum(array_map(static fn (array $item): int => $item['count'], $this->items));
    }

    public function overloadKg(): float
    {
        return max(0.0, $this->weightKg() - $this->payloadKg);
    }

    public function isOverloaded(): bool
    {
        return $this->overloadKg() > 1e-9;
    }

    /**
     * Whether the bounding boxes alone already exceed the bay — the only space answer that is safe to state.
     *
     * Null when nobody has measured the bay. A missing measurement is not a pass: it is the absence of an answer,
     * and reporting it as `false` would read as "checked, and fine".
     */
    public function exceedsTheBay(): ?bool
    {
        if ($this->bayM3 === null) {
            return null;
        }

        return $this->volumeM3() > $this->bayM3;
    }

    /**
     * How much of the bay the bounding boxes account for, as a fraction. Null when the bay is unmeasured.
     *
     * **Well under 1.0 is not the same as "it fits".** Irregular cabinets in a fixed shape rarely beat about 0.7 in
     * practice, and this repository has no measurement of its own packing efficiency to offer instead.
     */
    public function bayFill(): ?float
    {
        if ($this->bayM3 === null || $this->bayM3 <= 0.0) {
            return null;
        }

        return $this->volumeM3() / $this->bayM3;
    }
}
