<?php

declare(strict_types=1);

namespace App\Build;

use App\Spec\DeviceSpec;
use App\Spec\Driver;
use App\Spec\RiggingPoint;

/**
 * The hand-off between PHP and Blender: a spec plus its output paths, flattened into the JSON
 * the bpy builder consumes. Keeping it an explicit, testable structure means the Python side
 * never has to know about YAML, defaults or validation — it just builds what it is told.
 */
final class BuildPlan
{
    /**
     * @return array<string, mixed>
     */
    public static function forSpec(DeviceSpec $spec, string $glbPath, string $blendPath): array
    {
        return [
            // Bumped when the plan's shape changes in a way the bpy side must react to.
            'plan_version' => 1,
            'id' => $spec->id,
            'name' => $spec->name,
            'category' => $spec->category->value,
            'subtype' => $spec->subtype,
            'geometry' => [
                'shape' => $spec->shape->value,
                'dimensions_m' => $spec->dimensions->toArray(),
                // Taper dimensions: null for a plain box, which is what the builder falls back to.
                'back_width_m' => $spec->backWidth,
                'front_height_m' => $spec->frontHeight,
                'origin' => $spec->origin->value,
                'chamfer_m' => $spec->chamfer,
            ],
            'appearance' => [
                'color' => $spec->color,
                'grille' => [
                    'inset_m' => $spec->grilleInset,
                    'color' => $spec->grilleColor ?? $spec->color,
                ],
                // Estimated specs get a visible marker so nobody mistakes a guess for a measurement.
                'mark_estimated' => $spec->provenance->value === 'estimated',
            ],
            'physical' => [
                'weight_kg' => $spec->weightKg,
                'handles' => $spec->handles,
            ],
            'rigging' => [
                'flyable' => $spec->flyable,
                'points' => array_map(
                    static fn (RiggingPoint $point): array => $point->toArray(),
                    $spec->riggingPoints,
                ),
            ],
            'audio' => [
                'coverage_deg' => $spec->coverage?->toArray(),
                'drivers' => array_map(static fn (Driver $driver): array => $driver->toArray(), $spec->drivers),
            ],
            'mesh_override' => $spec->meshOverride,
            'metadata' => $spec->toMetadataArray(),
            'outputs' => [
                'glb' => $glbPath,
                'blend' => $blendPath,
            ],
        ];
    }
}
