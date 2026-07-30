<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Semantic checks on already-parsed specs. Everything here is a rule that a well-formed spec
 * file can still get wrong — impossible dimensions, a clone with no original, a rigging point
 * outside the cabinet. All violations of all specs are collected, because fixing a library one
 * error per run is miserable.
 *
 * The rules exist to protect the promise that makes the library useful: every model shares one
 * scale, one orientation and one origin convention, and every number knows where it came from.
 */
final class SpecValidator
{
    private const ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @param list<DeviceSpec> $specs
     * @return list<Violation>
     */
    public function validate(array $specs): array
    {
        $violations = [];
        foreach ($specs as $spec) {
            foreach ($this->validateOne($spec) as $violation) {
                $violations[] = $violation;
            }
            foreach ($this->warnAboutMissingMesh($spec) as $violation) {
                $violations[] = $violation;
            }
        }
        foreach ($this->validateUniqueIds($specs) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function validateOne(DeviceSpec $spec): array
    {
        $violations = [];
        $add = static function (string $message) use ($spec, &$violations): void {
            $violations[] = new Violation($spec->sourcePath, $message);
        };

        if (preg_match(self::ID_PATTERN, $spec->id) !== 1) {
            $add("id '{$spec->id}' must be lowercase words separated by single dashes");
        }
        $expectedId = pathinfo($spec->sourcePath, PATHINFO_FILENAME);
        if ($spec->id !== $expectedId) {
            $add("id '{$spec->id}' does not match the filename '{$expectedId}'");
        }

        if ($spec->quantity < 1) {
            $add("quantity must be at least 1, got {$spec->quantity}");
        }

        if (preg_match(self::ID_PATTERN, $spec->owner) !== 1) {
            $add("owner '{$spec->owner}' must be lowercase words separated by single dashes");
        }

        $allowedSubtypes = $spec->category->allowedSubtypes();
        if ($allowedSubtypes !== null && !in_array($spec->subtype, $allowedSubtypes, true)) {
            $allowed = implode(', ', $allowedSubtypes);
            $add("subtype '{$spec->subtype}' is not valid for category '{$spec->category->value}' (allowed: {$allowed})");
        }

        foreach (['width' => $spec->dimensions->width, 'height' => $spec->dimensions->height, 'depth' => $spec->dimensions->depth] as $axis => $value) {
            if ($value <= 0) {
                $add("geometry.dimensions_m.{$axis} must be greater than 0, got {$value}");
            }
        }

        if ($spec->chamfer < 0) {
            $add("geometry.chamfer_m must not be negative, got {$spec->chamfer}");
        } elseif ($spec->dimensions->smallestEdge() > 0 && $spec->chamfer >= $spec->dimensions->smallestEdge() / 2) {
            $add(sprintf(
                'geometry.chamfer_m (%s) must stay below half the smallest edge (%s)',
                $spec->chamfer,
                $spec->dimensions->smallestEdge() / 2,
            ));
        }

        if ($spec->weightKg <= 0) {
            $add("physical.weight_kg must be greater than 0, got {$spec->weightKg}");
        }

        if (preg_match(self::COLOR_PATTERN, $spec->color) !== 1) {
            $add("appearance.color '{$spec->color}' must be a #rrggbb hex colour");
        }
        if ($spec->grilleColor !== null && preg_match(self::COLOR_PATTERN, $spec->grilleColor) !== 1) {
            $add("appearance.grille.color '{$spec->grilleColor}' must be a #rrggbb hex colour");
        }
        if ($spec->grilleInset !== null) {
            if ($spec->grilleInset < 0) {
                $add("appearance.grille.inset_m must not be negative, got {$spec->grilleInset}");
            } elseif ($spec->dimensions->depth > 0 && $spec->grilleInset >= $spec->dimensions->depth / 2) {
                $add(sprintf(
                    'appearance.grille.inset_m (%s) must stay below half the depth (%s)',
                    $spec->grilleInset,
                    $spec->dimensions->depth / 2,
                ));
            }
        }

        foreach ($this->validateShape($spec) as $message) {
            $add($message);
        }
        foreach ($this->validateProvenance($spec) as $message) {
            $add($message);
        }
        foreach ($this->validateRigging($spec) as $message) {
            $add($message);
        }
        foreach ($this->validateAudio($spec) as $message) {
            $add($message);
        }

        foreach ($this->validateMeshOverride($spec) as $message) {
            $add($message);
        }
        foreach ($this->validateLayout($spec) as $message) {
            $add($message);
        }

        return $violations;
    }

    /**
     * A declared-but-absent override mesh is a warning, not an error. Override meshes are third-party
     * CAD that this repository deliberately does not commit, so a spec naming a file the current
     * checkout lacks is normal — the build falls back to the generated block and says so.
     *
     * @return list<Violation>
     */
    private function warnAboutMissingMesh(DeviceSpec $spec): array
    {
        $override = $spec->meshOverride;
        if ($override === null || is_file($this->resolve($override->path))) {
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
    private function validateMeshOverride(DeviceSpec $spec): array
    {
        $override = $spec->meshOverride;
        if ($override === null) {
            return [];
        }

        $messages = [];

        if (!$override->isImportable()) {
            $allowed = implode(', ', MeshOverride::IMPORTABLE);
            $messages[] = sprintf(
                "mesh_override.path '%s' has no importable extension (allowed: %s)%s",
                $override->path,
                $allowed,
                $override->extension() === 'fcstd' ? ' — Blender cannot read FreeCAD; export to .obj or .glb first' : '',
            );
        }
        if ($override->unitScale() === null) {
            $messages[] = sprintf(
                "mesh_override.units '%s' is unknown (allowed: %s)",
                $override->units,
                implode(', ', array_keys(MeshOverride::UNITS)),
            );
        }
        if ($override->toleranceM < 0) {
            $messages[] = "mesh_override.tolerance_m must not be negative, got {$override->toleranceM}";
        }

        return $messages;
    }

    /**
     * Baffle features: the drivers and horns drawn on the front face.
     *
     * The rules here protect two things. A feature that reaches past the baffle would break the promise
     * that a model's bounding box equals its declared dimensions — the one guarantee the library rests
     * on. And a feature deeper than the cabinet, or a throat wider than its own mouth, is not a horn at
     * all; it is a typo that would still render as something plausible-looking.
     *
     * @return list<string>
     */
    private function validateLayout(DeviceSpec $spec): array
    {
        $layout = $spec->layout;
        if ($layout === null) {
            return [];
        }

        $messages = [];
        $dimensions = $spec->dimensions;
        $frontHeight = $spec->frontHeight ?? $dimensions->height;

        if ($layout->insetM < 0) {
            $messages[] = "audio.layout.inset_m must not be negative, got {$layout->insetM}";
        }

        $seen = [];
        foreach ($layout->features as $index => $feature) {
            $label = "audio.layout.features[{$index}] '{$feature->id}'";

            if (isset($seen[$feature->id])) {
                $messages[] = "{$label}: duplicate feature id";
            }

            if (!in_array($feature->kind, BaffleFeature::KINDS, true)) {
                $allowed = implode(', ', BaffleFeature::KINDS);
                $messages[] = "{$label}: unknown kind '{$feature->kind}' (allowed: {$allowed})";
                $seen[$feature->id] = true;
                continue;
            }

            if ($feature->isHorn() && $feature->throatIn === null) {
                $messages[] = "{$label}: a horn needs throat_in";
            }
            if ($feature->isCone() && $feature->diameterIn === null) {
                $messages[] = "{$label}: a cone needs diameter_in";
            }

            foreach ($this->validateHornShape($feature, $label) as $message) {
                $messages[] = $message;
            }
            if ($feature->depthM <= 0) {
                $messages[] = "{$label}: depth_m must be greater than 0, got {$feature->depthM}";
            } elseif ($feature->depthM > $dimensions->depth) {
                $messages[] = sprintf(
                    '%s: depth_m (%s) is deeper than the cabinet (%s)',
                    $label,
                    $feature->depthM,
                    $dimensions->depth,
                );
            }

            $opening = $feature->openingM();
            if ($opening === null) {
                $messages[] = "{$label}: needs mouth_m, or diameter_in on a cone";
            } else {
                foreach (['width' => $opening[0], 'height' => $opening[1]] as $axis => $value) {
                    if ($value <= 0) {
                        $messages[] = "{$label}: mouth {$axis} must be greater than 0, got {$value}";
                    }
                }

                $throat = $feature->throatM();
                if ($throat !== null && $throat >= min($opening[0], $opening[1])) {
                    $messages[] = sprintf(
                        '%s: throat (%s m) must be smaller than its mouth (%s x %s m)',
                        $label,
                        round($throat, 4),
                        $opening[0],
                        $opening[1],
                    );
                }
            }

            foreach ($this->validateFeaturePlacement($feature, $layout, $seen, $label, $dimensions->width, $frontHeight, $opening) as $message) {
                $messages[] = $message;
            }

            $seen[$feature->id] = true;
        }

        return $messages;
    }

    /**
     * Where a feature sits: on the baffle at `at_m`, or nested in an earlier one via `inside`.
     *
     * @param array<string, bool> $seen features already declared, so `inside` can only look backwards
     * @param array{float, float}|null $opening
     * @return list<string>
     */
    /**
     * A horn's mouth shape and flare law.
     *
     * These only mean something on a horn: a driver cone is round with a straight profile, so accepting
     * the fields there and quietly ignoring them would leave a spec that reads as if it had been honoured.
     *
     * @return list<string>
     */
    private function validateHornShape(BaffleFeature $feature, string $label): array
    {
        $messages = [];

        if (!$feature->isHorn()) {
            if ($feature->sides !== null) {
                $messages[] = "{$label}: sides only applies to a horn";
            }
            if ($feature->throatProfile !== null) {
                $messages[] = "{$label}: throat_profile only applies to a horn";
            }

            return $messages;
        }

        foreach (['profile' => $feature->profile, 'throat_profile' => $feature->throatProfile] as $field => $value) {
            if ($value !== null && !in_array($value, BaffleFeature::PROFILES, true)) {
                $messages[] = sprintf(
                    "%s: unknown %s '%s' (allowed: %s)",
                    $label,
                    $field,
                    $value,
                    implode(', ', BaffleFeature::PROFILES),
                );
            }
        }
        if (!in_array($feature->flare, BaffleFeature::FLARES, true)) {
            $messages[] = sprintf(
                "%s: unknown flare '%s' (allowed: %s)",
                $label,
                $feature->flare,
                implode(', ', BaffleFeature::FLARES),
            );
        }
        if ($feature->sides !== null) {
            if (!$feature->isPyramid()) {
                $messages[] = sprintf(
                    '%s: sides has no meaning when neither the mouth nor the throat is a %s',
                    $label,
                    BaffleFeature::PYRAMID,
                );
            } elseif ($feature->sides < 3) {
                $messages[] = "{$label}: sides must be at least 3, got {$feature->sides}";
            }
        }

        return $messages;
    }

    private function validateFeaturePlacement(
        BaffleFeature $feature,
        BaffleLayout $layout,
        array $seen,
        string $label,
        float $width,
        float $height,
        ?array $opening,
    ): array {
        $messages = [];

        if ($feature->inside !== null) {
            if ($feature->at !== null) {
                $messages[] = "{$label}: `inside` already places it — remove at_m";
            }
            if (!isset($seen[$feature->inside])) {
                $messages[] = sprintf(
                    "%s: `inside: %s` must name an earlier feature",
                    $label,
                    $feature->inside,
                );

                return $messages;
            }

            $parent = $layout->feature($feature->inside);
            if ($parent !== null && !$parent->isHorn()) {
                $messages[] = "{$label}: `inside` only works within a horn, and '{$parent->id}' is a {$parent->kind}";
            }
            // A nested feature must fit its parent's throat region, or it would poke through the flare.
            $parentOpening = $parent?->openingM();
            if ($parentOpening !== null && $opening !== null) {
                if ($opening[0] > $parentOpening[0] || $opening[1] > $parentOpening[1]) {
                    $messages[] = "{$label}: its mouth is larger than the horn it sits inside";
                }
            }
            if ($parent !== null && $feature->depthM > $parent->depthM) {
                $messages[] = "{$label}: it is deeper than the horn it sits inside";
            }

            return $messages;
        }

        if ($feature->at === null) {
            $messages[] = "{$label}: needs either at_m or inside";

            return $messages;
        }
        if ($opening === null) {
            return $messages;
        }

        // Staying within the baffle is what keeps the bounding box equal to the declared dimensions.
        $limits = [
            ['x', $feature->at[0], $opening[0] / 2, $width / 2],
            ['z', $feature->at[1], $opening[1] / 2, $height / 2],
        ];
        foreach ($limits as [$axis, $centre, $half, $limit]) {
            if (abs($centre) + $half > $limit + 1e-9) {
                $messages[] = sprintf(
                    '%s: reaches past the baffle on %s — %s +/- %s exceeds +/-%s',
                    $label,
                    $axis,
                    $centre,
                    $half,
                    $limit,
                );
            }
        }

        return $messages;
    }

    /**
     * A tapered shape needs its taper stated outright. Deriving it from a ratio would invent a
     * measurement, and an invented measurement is exactly what the provenance rules exist to stop.
     *
     * @return list<string>
     */
    private function validateShape(DeviceSpec $spec): array
    {
        $messages = [];

        $extras = [
            'back_width_m' => [Shape::Trapezoid, $spec->backWidth, $spec->dimensions->width, 'width'],
            'front_height_m' => [Shape::Wedge, $spec->frontHeight, $spec->dimensions->height, 'height'],
        ];

        foreach ($extras as $key => [$requiredBy, $value, $limit, $limitName]) {
            if ($spec->shape === $requiredBy) {
                if ($value === null) {
                    $messages[] = "geometry.{$key} is required for shape '{$requiredBy->value}'";
                    continue;
                }
                if ($value <= 0) {
                    $messages[] = "geometry.{$key} must be greater than 0, got {$value}";
                } elseif ($limit > 0 && $value > $limit) {
                    $messages[] = "geometry.{$key} ({$value}) must not exceed geometry.dimensions_m.{$limitName} ({$limit})";
                }
            } elseif ($value !== null) {
                $messages[] = "geometry.{$key} only applies to shape '{$requiredBy->value}', not '{$spec->shape->value}'";
            }
        }

        return $messages;
    }

    /**
     * Provenance rules. A clone without a named original is the one that matters most: it means
     * nobody can look the numbers up, so the spec is stuck at whatever was guessed.
     *
     * @return list<string>
     */
    private function validateProvenance(DeviceSpec $spec): array
    {
        $messages = [];

        if ($spec->isClone()) {
            if ($spec->cloneOf === null) {
                $messages[] = "build is 'clone' but clone_of is missing — name the original's manufacturer and model";
            }
        } elseif ($spec->cloneOf !== null) {
            $messages[] = "clone_of is set but build is '{$spec->build->value}' — only clones copy an original";
        }

        if ($spec->cloneOf !== null) {
            $allowedReferences = ['datasheet', 'plans', 'cad', 'none'];
            if (!in_array($spec->cloneOf->reference, $allowedReferences, true)) {
                $allowed = implode(', ', $allowedReferences);
                $messages[] = "clone_of.reference '{$spec->cloneOf->reference}' is unknown (allowed: {$allowed})";
            }
        }

        // A clone's datasheet numbers must come from the original it copies, so that original has
        // to be named. Factory gear is exempt: the datasheet is its own, and there is no clone.
        if ($spec->isClone() && $spec->cloneOf === null) {
            foreach (['dimensions' => $spec->provenance->dimensions, 'weight' => $spec->provenance->weight] as $field => $provenance) {
                if (in_array($provenance, [Provenance::Datasheet, Provenance::Plans], true)) {
                    $messages[] = "provenance.{$field} is '{$provenance->value}' but no clone_of names where that came from";
                }
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function validateRigging(DeviceSpec $spec): array
    {
        $messages = [];

        if ($spec->flyable && $spec->riggingPoints === []) {
            $messages[] = 'rigging.flyable is true but no rigging.points are defined';
        }
        if (!$spec->flyable && $spec->riggingPoints !== []) {
            $messages[] = 'rigging.points are defined but rigging.flyable is false';
        }
        if ($spec->origin === Origin::RiggingPoint && $spec->riggingPoints === []) {
            $messages[] = "geometry.origin is 'rigging-point' but no rigging.points are defined";
        }

        // With an impossible dimension the box inverts and every point looks out of bounds. The
        // dimension is the real error; reporting four more is just noise.
        $boxIsMeaningful = $spec->dimensions->smallestEdge() > 0;

        $seen = [];
        $box = $spec->measuringBox();
        foreach ($spec->riggingPoints as $point) {
            if (isset($seen[$point->id])) {
                $messages[] = "duplicate rigging point id '{$point->id}'";
            }
            $seen[$point->id] = true;

            if (!$boxIsMeaningful) {
                continue;
            }

            foreach (['x', 'y', 'z'] as $axis => $label) {
                $value = $point->position[$axis];
                $min = $box['min'][$axis];
                $max = $box['max'][$axis];
                // Tolerance: points sit on the cabinet surface, where float maths is rarely exact.
                if ($value < $min - 1e-9 || $value > $max + 1e-9) {
                    $messages[] = sprintf(
                        "rigging point '%s' is outside the cabinet on %s: %s not within [%s, %s]",
                        $point->id,
                        $label,
                        $value,
                        $min,
                        $max,
                    );
                }
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function validateAudio(DeviceSpec $spec): array
    {
        $messages = [];

        if ($spec->coverage !== null) {
            foreach (['horizontal' => $spec->coverage->horizontal, 'vertical' => $spec->coverage->vertical] as $axis => $value) {
                if ($value <= 0 || $value > 360) {
                    $messages[] = "audio.coverage_deg.{$axis} must be between 0 and 360, got {$value}";
                }
            }
        }

        foreach ($spec->drivers as $index => $driver) {
            if ($driver->sizeIn <= 0) {
                $messages[] = "audio.drivers[{$index}].size_in must be greater than 0, got {$driver->sizeIn}";
            }
            if ($driver->count < 1) {
                $messages[] = "audio.drivers[{$index}].count must be at least 1, got {$driver->count}";
            }
        }

        return $messages;
    }

    /**
     * Ids name Blender objects and exported files, so a duplicate would silently overwrite a
     * model instead of producing two.
     *
     * @param list<DeviceSpec> $specs
     * @return list<Violation>
     */
    private function validateUniqueIds(array $specs): array
    {
        $byId = [];
        foreach ($specs as $spec) {
            $byId[$spec->id][] = $spec;
        }

        $violations = [];
        foreach ($byId as $id => $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $spec) {
                $others = array_filter(
                    $group,
                    static fn (DeviceSpec $other): bool => $other->sourcePath !== $spec->sourcePath,
                );
                $paths = implode(', ', array_map(
                    fn (DeviceSpec $other): string => (new Violation($other->sourcePath, ''))->shortFile($this->projectDir),
                    $others,
                ));
                $violations[] = new Violation($spec->sourcePath, "duplicate id '{$id}', also used by {$paths}");
            }
        }

        return $violations;
    }

    private function resolve(string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($this->projectDir, '/').'/'.$path;
    }
}
