<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * What a setup actually costs you: how many cabinets, how heavy, how much of it is borrowed, and how
 * much of it rests on numbers nobody has measured.
 *
 * This is the part of a scene that matters before the render does. A stack that looks right but needs
 * six cabinets you do not own, or weighs more than the floor allows, is worth knowing about while it
 * is still a text file.
 */
final class SceneReport
{
    /**
     * @param list<PlacedDevice> $placed
     * @return array{
     *     cabinets: int,
     *     total_weight_kg: float,
     *     by_device: array<string, array{count: int, weight_kg: float}>,
     *     by_owner: array<string, array{count: int, weight_kg: float}>,
     *     tallest_stack_m: float,
     *     footprint_m: array{float, float},
     *     unmeasured_devices: list<string>,
     *     over_inventory: array<string, array{used: int, owned: int}>
     * }
     */
    public function summarise(array $placed): array
    {
        $weight = 0.0;
        $byDevice = [];
        $byOwner = [];
        $top = 0.0;
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        $unmeasured = [];

        foreach ($placed as $entry) {
            $device = $entry->device;
            $weight += $device->weightKg;
            $top = max($top, $entry->topZ());

            $byDevice[$device->id] ??= ['count' => 0, 'weight_kg' => 0.0];
            $byDevice[$device->id]['count']++;
            $byDevice[$device->id]['weight_kg'] += $device->weightKg;

            $byOwner[$device->owner] ??= ['count' => 0, 'weight_kg' => 0.0];
            $byOwner[$device->owner]['count']++;
            $byOwner[$device->owner]['weight_kg'] += $device->weightKg;

            // Footprint from the cabinet's exact rotated box, not just its centre.
            $box = $entry->worldBox();
            $minX = min($minX, $box['min'][0]);
            $maxX = max($maxX, $box['max'][0]);
            $minY = min($minY, $box['min'][1]);
            $maxY = max($maxY, $box['max'][1]);

            if (!$device->provenance->isFullyMeasured() && !in_array($device->id, $unmeasured, true)) {
                $unmeasured[] = $device->id;
            }
        }

        ksort($byDevice);
        ksort($byOwner);

        return [
            'cabinets' => count($placed),
            'total_weight_kg' => $weight,
            'by_device' => $byDevice,
            'by_owner' => $byOwner,
            'tallest_stack_m' => $top,
            'footprint_m' => $placed === [] ? [0.0, 0.0] : [$maxX - $minX, $maxY - $minY],
            'unmeasured_devices' => $unmeasured,
            'over_inventory' => $this->overInventory($byDevice, $placed),
        ];
    }

    /**
     * Devices the scene uses more of than the inventory says we have. Easy to do by accident when
     * copying a stack, and expensive to discover on site.
     *
     * @param array<string, array{count: int, weight_kg: float}> $byDevice
     * @param list<PlacedDevice> $placed
     * @return array<string, array{used: int, owned: int}>
     */
    private function overInventory(array $byDevice, array $placed): array
    {
        $specs = [];
        foreach ($placed as $entry) {
            $specs[$entry->device->id] = $entry->device;
        }

        $over = [];
        foreach ($byDevice as $id => $totals) {
            $owned = $specs[$id]->quantity ?? 0;
            if ($totals['count'] > $owned) {
                $over[$id] = ['used' => $totals['count'], 'owned' => $owned];
            }
        }

        return $over;
    }

    /**
     * @param list<PlacedDevice> $placed
     * @return list<string>
     */
    public function lines(array $placed): array
    {
        $summary = $this->summarise($placed);

        $lines = [
            sprintf('Cabinets:      %d', $summary['cabinets']),
            sprintf('Total weight:  %.1f kg', $summary['total_weight_kg']),
            sprintf('Tallest stack: %.3f m', $summary['tallest_stack_m']),
            sprintf('Footprint:     %.2f × %.2f m', $summary['footprint_m'][0], $summary['footprint_m'][1]),
        ];
        foreach ($summary['by_device'] as $id => $totals) {
            $lines[] = sprintf('  %-26s %2d × = %7.1f kg', $id, $totals['count'], $totals['weight_kg']);
        }
        foreach ($summary['by_owner'] as $owner => $totals) {
            $lines[] = sprintf('  owner %-20s %2d cabinets, %.1f kg', $owner, $totals['count'], $totals['weight_kg']);
        }

        return $lines;
    }
}
