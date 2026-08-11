<?php

declare(strict_types=1);

namespace App\Build;

use App\Scene\Focus;
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
            // Bumped when the plan's shape changes in a way the bpy side must react to. 2 added
            // `geometry.truss`, which the bpy side branches on to build tubes instead of a shell.
            'plan_version' => 2,
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
                // Null for every cabinet; the tubes to build for `shape: truss`, which has no shell at all.
                'truss' => $spec->truss?->toArray(),
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
                // How far the coverage cone reaches. A cone is an angle, so something has to choose a
                // length, and the default focus distance is the one number in the library that already
                // means "out where aiming matters" — so a cone reaches exactly as far as a scene's
                // default aim, and "does the pattern cover the dancefloor" reads directly against it.
                'coverage_throw_m' => $spec->coverage === null ? null : Focus::DEFAULT_DISTANCE_M,
                'coverage_spread_m' => $spec->coverage?->spreadAt(Focus::DEFAULT_DISTANCE_M),
                'drivers' => array_map(static fn (Driver $driver): array => $driver->toArray(), $spec->drivers),
            ],
            // The baffle features, already reduced to metres so the bpy side does no unit maths.
            'baffle_layout' => $spec->layout?->toArray(),
            // Absolute so the bpy side never has to know where the project root is. Null when the
            // spec names a mesh this checkout does not have — the builder then generates the block.
            'mesh_override' => ($spec->meshOverride === null || $meshOverridePath === null) ? null : [
                ...$spec->meshOverride->toArray(),
                'path' => $meshOverridePath,
            ],
            'metadata' => $spec->toMetadataArray(),
            'outputs' => [
                'glb' => $glbPath,
                'blend' => $blendPath,
            ],
        ];
    }
}
