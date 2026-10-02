<?php

declare(strict_types=1);

namespace App\Spec;

/** Checks optional images and imported mesh edits against the project files. */
final class AssetValidator
{
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * A declared-but-absent photograph is a warning, exactly as an absent override mesh is: the files
     * are binary and often somebody else's, so this repository does not commit them and not having
     * them in a checkout is the normal case rather than a fault.
     *
     * @return list<Violation>
     */
    public function warnAboutMissingFrontImage(DeviceSpec $spec): array
    {
        $image = $spec->frontImage;
        if (null === $image || is_file($this->resolve($image->path))) {
            return [];
        }

        return [new Violation(
            $spec->sourcePath,
            sprintf(
                "front_image.path '%s' is not in this checkout — the front stays plain (see meshes/README.md)",
                $image->path,
            ),
            Violation::WARNING,
        )];
    }

    /**
     * @return list<string>
     */
    public function validateFrontImage(DeviceSpec $spec): array
    {
        $image = $spec->frontImage;
        if (null === $image) {
            return [];
        }

        $messages = [];

        // The condition the owner set, and the geometry agrees with it. A layout has already cut real
        // openings into the baffle, so a photograph over them fights geometry that is there; an
        // override has no front plane this builder knows about, because the CAD decides where its
        // front is. Rejected rather than skipped, because a spec that asks for an image and silently
        // does not get one is the worse failure.
        if (null !== $spec->layout) {
            $messages[] = "front_image is only for a cabinet with no interior, and this spec has an audio.layout — the baffle's real openings would fight the photograph";
        }
        if (null !== $spec->meshOverride) {
            $messages[] = 'front_image needs the generated block, whose front plane is known, and this spec has a mesh_override — the imported shell decides where its own front is';
        }

        if (!$image->isImportable()) {
            $messages[] = sprintf(
                "front_image.path '%s' has no importable extension (allowed: %s)",
                $image->path,
                implode(', ', FrontImage::IMPORTABLE),
            );
        }

        if ($image->cutout && $image->isImportable() && FrontImage::CUTOUT_EXTENSION !== $image->extension()) {
            $messages[] = sprintf(
                "front_image.cutout needs a PNG, the only format here with an alpha channel, and '%s' is not one",
                $image->path,
            );
        }

        if (!$image->hasValidRotation()) {
            $messages[] = sprintf(
                'front_image.rotate_deg is %d, which is not one of %s — a photograph off a right angle was not taken square to the cabinet, and the fix is a better crop',
                $image->rotateDeg,
                implode(', ', FrontImage::ROTATIONS),
            );
        }

        if ($image->pxPerCm <= 0.0) {
            $messages[] = sprintf('front_image.px_per_cm is %s, which has to be greater than zero', (string) $image->pxPerCm);
        }

        // The size check only runs when the file is here. Its absence is the warning above, and
        // reporting both would say the same thing twice.
        $absolute = $this->resolve($image->path);
        if ([] === $messages && is_file($absolute) && $image->pxPerCm > 0.0) {
            $implied = $image->impliedSizeM($absolute);
            if (null === $implied) {
                $messages[] = sprintf("front_image.path '%s' cannot be read as an image", $image->path);
            } elseif (!$image->matchesFront($absolute, $spec->dimensions->width, $spec->dimensions->height)) {
                $messages[] = sprintf(
                    'front_image implies a %.3f × %.3f m front at %s px/cm, but the cabinet is %.3f × %.3f m — a photograph of the wrong cabinet looks exactly like this',
                    $implied[0],
                    $implied[1],
                    (string) $image->pxPerCm,
                    $spec->dimensions->width,
                    $spec->dimensions->height,
                );
            }
        }

        return $messages;
    }

    public function warnAboutMissingMesh(DeviceSpec $spec): array
    {
        $override = $spec->meshOverride;
        if (null === $override || is_file($this->resolve($override->path))) {
            return [];
        }

        return [new Violation(
            $spec->sourcePath,
            sprintf(
                "mesh_override.path '%s' is not in this checkout — building the generated block instead (see meshes/README.md)",
                $override->path,
            ),
            Violation::WARNING,
        )];
    }

    /**
     * @return list<string>
     */
    public function validateMeshOverride(DeviceSpec $spec): array
    {
        $override = $spec->meshOverride;
        if (null === $override) {
            return [];
        }

        $messages = [];

        if (!$override->isImportable()) {
            $allowed = implode(', ', MeshOverride::IMPORTABLE);
            $messages[] = sprintf(
                "mesh_override.path '%s' has no importable extension (allowed: %s)%s",
                $override->path,
                $allowed,
                'fcstd' === $override->extension() ? ' — Blender cannot read FreeCAD; export to .obj or .glb first' : '',
            );
        }
        if (null === $override->unitScale()) {
            $messages[] = sprintf(
                "mesh_override.units '%s' is unknown (allowed: %s)",
                $override->units,
                implode(', ', array_keys(MeshOverride::UNITS)),
            );
        }
        if ($override->toleranceM < 0) {
            $messages[] = "mesh_override.tolerance_m must not be negative, got {$override->toleranceM}";
        }
        foreach ($override->paint as $region) {
            array_push($messages, ...$this->validatePaintRegion($region, $spec->dimensions));
        }
        foreach ($override->remove as $removal) {
            array_push($messages, ...$this->validateMeshRemoval($removal, $spec->dimensions));
        }

        return $messages;
    }

    /**
     * A removal's prism may start in the air in front of the cabinet, so it is not held to the bounding box. What
     * it must not do is miss the cabinet altogether or have no volume, because either builds a model that silently
     * still has the part the spec says is gone.
     *
     * @return list<string>
     */
    private function validateMeshRemoval(MeshRemoval $removal, Dimensions $dimensions): array
    {
        $label = "mesh_override.remove '{$removal->id}'";
        $messages = [];

        if ($removal->x[0] >= $removal->x[1]) {
            $messages[] = "{$label}: x_m must run from the smaller x to the larger";
        } elseif ($removal->x[1] <= -$dimensions->width / 2 || $removal->x[0] >= $dimensions->width / 2) {
            $messages[] = "{$label}: x_m [{$removal->x[0]}, {$removal->x[1]}] misses the {$dimensions->width} wide cabinet";
        }
        if (count($removal->section) < 3) {
            $messages[] = "{$label}: section_m needs at least three [setback, z] corners";
        } elseif ($removal->sectionArea() < 1e-9) {
            $messages[] = "{$label}: section_m encloses no area";
        } else {
            $setbacks = array_column($removal->section, 0);
            $heights = array_column($removal->section, 1);
            if (max($setbacks) <= 0.0 || min($setbacks) >= $dimensions->depth
                || max($heights) <= -$dimensions->height / 2 || min($heights) >= $dimensions->height / 2) {
                $messages[] = "{$label}: section_m lies outside the cabinet — setback runs from the front plane and z from the centre of the front face";
            }
        }

        return $messages;
    }

    /**
     * A paint region is a box in the bounding box's own frame, so it has to lie inside that box. One that reaches
     * past it would paint nothing there and suggests a region measured in another frame — from the floor rather
     * than from the centre, which is the mistake the CAD probes invite.
     *
     * @return list<string>
     */
    private function validatePaintRegion(PaintRegion $region, Dimensions $dimensions): array
    {
        $label = "mesh_override.paint '{$region->id}'";
        $messages = [];

        if (1 !== preg_match(ValidationRules::COLOR_PATTERN, $region->color)) {
            $messages[] = "{$label}: color '{$region->color}' must be a #rrggbb hex colour";
        }
        if ($region->size[0] <= 0 || $region->size[1] <= 0 || $region->depthM <= 0) {
            $messages[] = "{$label}: size_m and depth_m must be greater than 0";

            return $messages;
        }
        if ($region->setbackM < 0) {
            $messages[] = "{$label}: setback_m must not be negative, got {$region->setbackM}";
        }

        $epsilon = 1e-6;
        if (abs($region->at[0]) + $region->size[0] / 2 > $dimensions->width / 2 + $epsilon
            || abs($region->at[1]) + $region->size[1] / 2 > $dimensions->height / 2 + $epsilon) {
            $messages[] = sprintf(
                '%s: [%s, %s] ± [%s, %s] reaches past the %s × %s front — at_m is measured from the centre of the front face',
                $label,
                $region->at[0],
                $region->at[1],
                $region->size[0] / 2,
                $region->size[1] / 2,
                $dimensions->width,
                $dimensions->height,
            );
        }
        if ($region->setbackM + $region->depthM > $dimensions->depth + $epsilon) {
            $messages[] = sprintf(
                '%s: setback_m %s plus depth_m %s reaches past the %s deep cabinet',
                $label,
                $region->setbackM,
                $region->depthM,
                $dimensions->depth,
            );
        }

        return $messages;
    }

    private function resolve(string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($this->projectDir, '/').'/'.$path;
    }
}
