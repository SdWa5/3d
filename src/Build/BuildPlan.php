<?php

declare(strict_types=1);

namespace App\Build;

use App\Spec\DeviceSpec;
use App\Spec\Driver;
use App\Spec\Provenance;
use App\Spec\RiggingPoint;

/**
 * The hand-off between PHP and Blender: a spec plus its output paths, flattened into the JSON
 * the bpy builder consumes. Keeping it an explicit, testable structure means the Python side
 * never has to know about YAML, defaults or validation — it just builds what it is told.
 */
final class BuildPlan
{
    /**
     * @param string|null $meshOverridePath absolute path to the override mesh, resolved by the caller
     * @return array<string, mixed>
     */
    public static function forSpec(
        DeviceSpec $spec,
        string $glbPath,
        string $blendPath,
        ?string $meshOverridePath = null,
    ): array {
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
                // A guessed *shape* gets the visible marker. An estimated weight does not distort
                // the model, so it is reported by `catalog` instead of tagged in the viewport.
                'mark_estimated' => $spec->provenance->dimensions === Provenance::Estimated,
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
            // Absolute so the bpy side never has to know where the project root is.
            'mesh_override' => $spec->meshOverride === null ? null : [
                ...$spec->meshOverride->toArray(),
                'path' => $meshOverridePath ?? $spec->meshOverride->path,
            ],
            'metadata' => $spec->toMetadataArray(),
            'outputs' => [
                'glb' => $glbPath,
                'blend' => $blendPath,
            ],
        ];
    }
}
