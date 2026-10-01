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
     * @param string|null $frontImagePath absolute path to the front photograph, resolved by the
     *                                    caller and null whenever the feature is off for this run,
     *                                    so the bpy side needs no switch of its own
     *
     * @return array<string, mixed>
     */
    public static function forSpec(
        DeviceSpec $spec,
        string $glbPath,
        string $blendPath,
        ?string $meshOverridePath = null,
        ?string $frontImagePath = null,
    ): array {
        return [
            // Bumped when the plan's shape changes in a way the bpy side must react to. 2 added
            // `geometry.truss`, which the bpy side branches on to build tubes instead of a shell; 3 added
            // `moving_head` and `scaffold`, which do the same for two more open-frame shapes; 4 added
            // `load_bay`, which draws a transporter as a cage rather than a solid; 5 added
            // `front_image`, which puts a photograph on a plain cabinet's front face; 6 added `mast`, a wind-up
            // stand whose stages are separate objects so a scene can slide them; 7 added the `cell` and `fin` baffle
            // features, a `color` on every feature and a dome and a rim on a round grille, which the bpy side carves,
            // draws and paints.
            'plan_version' => 7,
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
                // Null for every cabinet; the parts to build for the shapes that have no shell at all.
                'truss' => $spec->truss?->toArray(),
                'moving_head' => $spec->movingHead?->toArray(),
                'scaffold' => $spec->scaffold?->toArray(),
                // With every tube length already worked out, so the bpy side derives nothing.
                'mast' => $spec->mast?->planArray($spec->dimensions),
                // The inside of a transporter, for `shape: load-bay`. Null for everything else, like the three
                // above it — a vehicle is the fourth open-frame shape and needs no new mechanism.
                'load_bay' => $spec->vehicle?->loadBayPlan(),
                'origin' => $spec->origin->value,
                'chamfer_m' => $spec->chamfer,
            ],
            'appearance' => [
                'color' => $spec->color,
                'front_color' => $spec->frontColor,
                'grille' => [
                    'inset_m' => $spec->grilleInset,
                    'color' => $spec->grilleColor ?? $spec->color,
                ],
                // A guessed *shape* gets the visible marker. An estimated weight does not distort
                // the model, so it is reported by `catalog` instead of tagged in the viewport.
                'mark_estimated' => Provenance::Estimated === $spec->provenance->dimensions,
            ],
            'physical' => [
                'weight_kg' => $spec->weightKg,
                'handles' => $spec->handles,
                'castors' => $spec->castors?->toArray(),
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
                'coverage_throw_m' => null === $spec->coverage ? null : Focus::DEFAULT_DISTANCE_M,
                'coverage_spread_m' => $spec->coverage?->spreadAt(Focus::DEFAULT_DISTANCE_M),
                'drivers' => array_map(static fn (Driver $driver): array => $driver->toArray(), $spec->drivers),
            ],
            // The baffle features, already reduced to metres so the bpy side does no unit maths.
            'baffle_layout' => $spec->layout?->toArray(),
            // Absolute so the bpy side never has to know where the project root is. Null when the
            // spec names a mesh this checkout does not have — the builder then generates the block.
            'mesh_override' => (null === $spec->meshOverride || null === $meshOverridePath) ? null : [
                ...$spec->meshOverride->toArray(),
                'path' => $meshOverridePath,
            ],
            // Absolute, for the same reason. Null covers three cases the bpy side does not have to
            // tell apart: the spec names none, the file is not in this checkout, or the feature is
            // off for this run. In all three the front stays plain.
            'front_image' => (null === $spec->frontImage || null === $frontImagePath) ? null : [
                ...$spec->frontImage->toArray(),
                'path' => $frontImagePath,
            ],
            'metadata' => $spec->toMetadataArray(),
            'outputs' => [
                'glb' => $glbPath,
                'blend' => $blendPath,
            ],
        ];
    }
}
