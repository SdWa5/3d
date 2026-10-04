<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Spec\Category;
use App\Spec\DeviceSpec;
use App\Spec\Provenance;

/**
 * Renders the equipment library as a table plus totals.
 *
 * The totals are the practical payoff of keeping weight and dimensions in the specs: they answer
 * "how heavy is the PA" and "what still needs measuring" without opening Blender.
 */
final class CatalogRenderer
{
    private const HEADERS = ['ID', 'Name', 'Category', 'Qty', 'Owner', 'W×H×D (m)', 'kg', 'Σ kg', 'Clone of', 'Provenance'];

    /**
     * @param list<DeviceSpec> $specs
     *
     * @return list<list<string>>
     */
    public function rows(array $specs): array
    {
        $rows = [];
        foreach ($specs as $spec) {
            $rows[] = [
                $spec->id,
                $spec->name,
                $spec->category->value.'/'.$spec->subtype,
                (string) $spec->quantity,
                $spec->owner,
                $this->formatDimensions($spec),
                $this->formatNumber($spec->weightKg),
                $this->formatNumber($spec->totalWeightKg()),
                $spec->originalLabel(),
                $spec->provenance->label(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return self::HEADERS;
    }

    /**
     * **Every weight and volume here is what has to be CARRIED**, so the transporters are excluded from them and
     * reported separately as `fleet`. See the note in the loop: counting two vans as gear takes the library from
     * 3493.7 kg to 8269.7, and `owner sdwa5` from 1856.5 kg to 4332.5 — which is precisely the figure somebody would
     * hold up against a 1024 kg payload to decide what goes in one load.
     *
     * @param list<DeviceSpec> $specs
     *
     * @return array{
     *     devices: int,
     *     fleet: list<array{id: string, owner: string, payload_kg: float, bay_m3: float|null}>,
     *     units: int,
     *     total_weight_kg: float,
     *     total_volume_m3: float,
     *     by_category: array<string, int>,
     *     by_owner: array<string, array{units: int, weight_kg: float}>,
     *     unmeasured: int,
     *     unmeasured_ids: list<string>,
     *     dimensions_unmeasured_ids: list<string>,
     *     weight_unmeasured_ids: list<string>
     * }
     */
    public function summary(array $specs): array
    {
        $units = 0;
        $weight = 0.0;
        $volume = 0.0;
        $byCategory = [];
        $byOwner = [];
        $unmeasuredIds = [];
        $dimensionsUnmeasured = [];
        $weightUnmeasured = [];
        $fleet = [];

        foreach ($specs as $spec) {
            $byCategory[$spec->category->value] = ($byCategory[$spec->category->value] ?? 0) + $spec->quantity;

            // **A VEHICLE IS THE CONTAINER, NEVER THE LOAD**, and counting it as gear breaks the one comparison this
            // whole summary exists to support. Adding two vans took the library from 3493.7 kg to 8269.7 and from
            // 26.586 m³ to 97.401 — and `owner sdwa5` from 1856.5 kg to 4332.5, which is exactly the figure somebody
            // would hold up against a 1024 kg payload. It is still counted in `by_category`, because "we own two
            // vans" is true and useful; what it may not join is a weight or a volume that means "what has to be
            // carried".
            if (Category::Vehicle === $spec->category) {
                $fleet[] = [
                    'id' => $spec->id,
                    'owner' => $spec->owner,
                    // Derived rather than stored, so it cannot drift from the two masses it comes out of.
                    'payload_kg' => $spec->vehicle?->payloadKg($spec->weightKg) ?? 0.0,
                    'bay_m3' => $spec->vehicle?->loadBayVolumeM3(),
                ];
            } else {
                $units += $spec->quantity;
                $weight += $spec->totalWeightKg();
                // Shipping volume, so the packed box where the spec states one.
                $volume += $spec->transportDimensions()->volumeM3() * $spec->quantity;

                $owner = $byOwner[$spec->owner] ?? ['units' => 0, 'weight_kg' => 0.0];
                $byOwner[$spec->owner] = [
                    'units' => $owner['units'] + $spec->quantity,
                    'weight_kg' => $owner['weight_kg'] + $spec->totalWeightKg(),
                ];
            }

            // **THE PROVENANCE TALLY COUNTS VEHICLES AND THE WEIGHT TOTALS DO NOT**, which is the distinction this
            // `else` exists to draw. A van is not cargo, so it may not join a weight; it is very much a thing nobody
            // has measured, so it must join the count of things nobody has measured — and LOAD-2 is literally the
            // job of going and measuring it. Skipping the whole iteration got this wrong in the obvious direction:
            // the report read `2 of 20 measured` with an open list of 18, so the two least-measured devices in the
            // library counted as done.

            if (!$spec->provenance->isFullyMeasured()) {
                $unmeasuredIds[] = $spec->id;
            }
            if (!$spec->provenance->dimensions->isMeasured()) {
                $dimensionsUnmeasured[] = $spec->id;
            }
            if (!$spec->provenance->weight->isMeasured()) {
                $weightUnmeasured[] = $spec->id;
            }
        }
        ksort($byCategory);
        ksort($byOwner);

        return [
            'devices' => count($specs),
            'fleet' => $fleet,
            'units' => $units,
            'total_weight_kg' => $weight,
            'total_volume_m3' => $volume,
            'by_category' => $byCategory,
            'by_owner' => $byOwner,
            'unmeasured' => count($unmeasuredIds),
            'unmeasured_ids' => $unmeasuredIds,
            'dimensions_unmeasured_ids' => $dimensionsUnmeasured,
            'weight_unmeasured_ids' => $weightUnmeasured,
        ];
    }

    /**
     * Markdown for `docs/catalog.md`. Generated, so it carries a header saying so — nobody should
     * be editing it by hand when the specs are the source of truth.
     *
     * @param list<DeviceSpec> $specs
     */
    public function renderMarkdown(array $specs): string
    {
        $lines = [
            '# Equipment catalog',
            '',
            'Generated by `bin/console catalog --write`. Do not edit — the spec files under'
                .' [`specs/`](../specs) are the source of truth.',
            '',
        ];

        if ([] === $specs) {
            $lines[] = 'No specs yet.';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '| '.implode(' | ', self::HEADERS).' |';
        $lines[] = '|'.str_repeat('---|', count(self::HEADERS));
        foreach ($this->rows($specs) as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        $summary = $this->summary($specs);
        $lines[] = '';
        $lines[] = '## Totals';
        $lines[] = '';
        $lines[] = sprintf('- Devices: %d (%d units)', $summary['devices'], $summary['units']);
        $lines[] = sprintf('- Total weight: %s kg', $this->formatNumber($summary['total_weight_kg']));
        $lines[] = sprintf('- Total volume: %s m³', $this->formatNumber($summary['total_volume_m3'], 3));
        foreach ($summary['by_category'] as $category => $count) {
            $lines[] = sprintf('- %s: %d units', ucfirst($category), $count);
        }
        foreach ($summary['by_owner'] as $owner => $totals) {
            $lines[] = sprintf(
                '- Owner %s: %d units, %s kg',
                $owner,
                $totals['units'],
                $this->formatNumber($totals['weight_kg']),
            );
        }
        // **The payload beside the load it has to carry**, which is the one comparison the totals above cannot make
        // on their own. 3493.7 kg of gear against 1024 kg of Movano is three and a half loads, and that is the sort
        // of thing somebody should read off the catalog rather than work out.
        foreach ($summary['fleet'] as $vehicle) {
            $lines[] = sprintf(
                '- Vehicle %s (%s): %s kg payload%s',
                $vehicle['id'],
                $vehicle['owner'],
                $this->formatNumber($vehicle['payload_kg']),
                null === $vehicle['bay_m3']
                    ? ', load bay not measured'
                    : sprintf(', %s m³ bay', $this->formatNumber($vehicle['bay_m3'], 2)),
            );
        }
        $lines[] = sprintf(
            '- Dimensions measured: %d of %d%s',
            $summary['devices'] - count($summary['dimensions_unmeasured_ids']),
            $summary['devices'],
            [] === $summary['dimensions_unmeasured_ids']
                ? ''
                : ' — open: '.implode(', ', $summary['dimensions_unmeasured_ids']),
        );
        $lines[] = sprintf(
            '- Weights measured: %d of %d%s',
            $summary['devices'] - count($summary['weight_unmeasured_ids']),
            $summary['devices'],
            [] === $summary['weight_unmeasured_ids']
                ? ''
                : ' — open: '.implode(', ', $summary['weight_unmeasured_ids']),
        );
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<DeviceSpec> $specs
     *
     * @return list<DeviceSpec> specs with anything still not measured on the actual cabinet
     */
    public function unmeasured(array $specs): array
    {
        return array_values(array_filter(
            $specs,
            static fn (DeviceSpec $spec): bool => !$spec->provenance->isFullyMeasured(),
        ));
    }

    private function formatDimensions(DeviceSpec $spec): string
    {
        return sprintf(
            '%s × %s × %s',
            $this->formatNumber($spec->dimensions->width, 3),
            $this->formatNumber($spec->dimensions->height, 3),
            $this->formatNumber($spec->dimensions->depth, 3),
        );
    }

    /**
     * Trailing zeros are noise in a table, so they go — 0.60 reads as 0.6, 34.0 as 34.
     */
    private function formatNumber(float $value, int $decimals = 1): string
    {
        $formatted = number_format($value, $decimals, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
