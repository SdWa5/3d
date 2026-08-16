<?php

declare(strict_types=1);

namespace App\Load;

use App\Spec\DeviceSpec;

/**
 * The load plan as lines somebody can act on, with the two verdicts kept apart and the provenance beside them.
 *
 * **Space and weight are independent and a plan can pass one while failing the other**, so a single pass/fail would
 * hide exactly the case that matters — a load that fits the bay comfortably and is 300 kg over the axle. Each
 * vehicle therefore reports kilogrammes against its payload and cubic metres against its bay, as two statements.
 *
 * **And a verdict is only as good as the numbers under it.** No weight in this library is `measured`: they are
 * datasheet figures, arithmetic and the builder's own hedging. Half the fleet payload is Sepp's assumed 1200 kg,
 * both masses guessed rather than read off a registration document. So the report prints what it summed and where
 * those figures came from, and when a verdict lands inside that uncertainty it says so instead of pretending to
 * decide. A load plan is a planning aid, never a clearance.
 */
final class LoadReport
{
    /** How close to the payload counts as "inside the error bar" rather than as a verdict, as a fraction. */
    private const UNCERTAIN_MARGIN = 0.02;

    /**
     * @param list<LoadPlan> $plans
     * @param list<array{spec: DeviceSpec, count: int}> $leftovers
     * @return list<string>
     */
    public function lines(array $plans, array $leftovers): array
    {
        $lines = [];

        foreach ($plans as $plan) {
            $lines[] = sprintf(
                '%s (%s) — %d units',
                $plan->vehicle->id,
                $plan->vehicle->owner,
                $plan->units(),
            );
            $lines[] = sprintf(
                '  weight  %8.1f kg of %.1f kg payload%s',
                $plan->weightKg(),
                $plan->payloadKg,
                $plan->isOverloaded()
                    ? sprintf('  OVER BY %.1f kg', $plan->overloadKg())
                    : sprintf('  (%.1f kg spare)', $plan->payloadKg - $plan->weightKg()),
            );
            $lines[] = $plan->bayM3 === null
                ? '  space        bay not measured, so no space answer can be given'
                : sprintf(
                    '  space   %8.3f m³ of %.3f m³ bay  (%.0f %% by bounding box%s)',
                    $plan->volumeM3(),
                    $plan->bayM3,
                    100 * (float)$plan->bayFill(),
                    $plan->exceedsTheBay() === true ? ', WHICH ALREADY EXCEEDS IT' : '',
                );

            foreach ($plan->items as ['spec' => $spec, 'count' => $count]) {
                $lines[] = sprintf(
                    '    %2d × %-32s %7.1f kg',
                    $count,
                    $spec->id,
                    $spec->weightKg * $count,
                );
            }
            $lines[] = '';
        }

        if ($leftovers !== []) {
            $short = 0.0;
            $lines[] = 'NOT CARRIED — the fleet has no legal room for these:';
            foreach ($leftovers as ['spec' => $spec, 'count' => $count]) {
                $short += $spec->weightKg * $count;
                $lines[] = sprintf('    %2d × %-32s %7.1f kg', $count, $spec->id, $spec->weightKg * $count);
            }
            $lines[] = sprintf('  short by %.1f kg', $short);
            $lines[] = '';
        }

        return [...$lines, ...$this->provenanceLines($plans, $leftovers)];
    }

    /**
     * **What the verdict above is made of.** Named rather than implied, because every figure in it is an estimate
     * of some kind and a reader who does not know that will treat "1.4 kg spare" as a decision.
     *
     * @param list<LoadPlan> $plans
     * @param list<array{spec: DeviceSpec, count: int}> $leftovers
     * @return list<string>
     */
    private function provenanceLines(array $plans, array $leftovers): array
    {
        $devices = [];
        foreach ($plans as $plan) {
            foreach ($plan->items as ['spec' => $spec]) {
                $devices[$spec->id] = $spec;
            }
        }
        foreach ($leftovers as ['spec' => $spec]) {
            $devices[$spec->id] = $spec;
        }

        $measured = 0;
        foreach ($devices as $spec) {
            if ($spec->provenance->weight->isMeasured()) {
                ++$measured;
            }
        }

        $lines = [sprintf(
            'Weights: %d of %d devices weighed on a scale. The rest are datasheet figures or estimates',
            $measured,
            count($devices),
        )];

        foreach ($plans as $plan) {
            $vehicle = $plan->vehicle;
            $lines[] = sprintf(
                'Payload of %s: %s, from %s masses',
                $vehicle->id,
                $this->number($plan->payloadKg).' kg',
                $vehicle->provenance->weight->value,
            );
        }

        // **The verdict this report declines to give.** A margin inside the uncertainty of its own inputs is not a
        // near miss, it is an unanswered question, and saying "fits with 1.4 kg spare" off two estimated masses
        // would be the most confident sentence in the file resting on the least evidence.
        foreach ($plans as $plan) {
            $margin = abs($plan->payloadKg - $plan->weightKg());
            if ($plan->payloadKg > 0.0 && $margin < $plan->payloadKg * self::UNCERTAIN_MARGIN) {
                $lines[] = sprintf(
                    'UNDECIDED for %s: %s kg of margin is inside the error of the estimates it is made of, so this'
                        .' is neither a pass nor a refusal',
                    $plan->vehicle->id,
                    $this->number($margin),
                );
            }
        }

        return $lines;
    }

    private function number(float $value): string
    {
        $formatted = number_format($value, 1, '.', '');

        return str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted;
    }
}
