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

    /**
     * Slack allowed when checking that a shape's parts fit inside its stated bounding box.
     *
     * A tenth of a millimetre, which is below anything a tape measure or a datasheet reports, and exists only so a
     * part that fills its box exactly is not refused by floating-point noise — `0.408 - 2 * 0.204` is not reliably
     * zero. It is not a tolerance for sloppy numbers: a part a millimetre too big still fails.
     */
    private const FIT_TOLERANCE_M = 0.0001;

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
        foreach ($this->validateCarriedOn($specs) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * `carried_on` has to name a transporter that exists, and a transporter cannot itself be carried.
     *
     * **Cross-spec, so it lives up here with the id check rather than in `validateOne`.** A pin naming a device that
     * is not a vehicle, or not there at all, is the failure mode that matters: {@see \App\Load\LoadPlanner} leaves
     * a pinned device behind when it cannot find its bin, so a typo would quietly turn "this rides on the trailer"
     * into "this does not travel" — and the load plan would look complete while a 465 kg generator sat at home.
     *
     * @param list<DeviceSpec> $specs
     * @return list<Violation>
     */
    private function validateCarriedOn(array $specs): array
    {
        $transporters = [];
        foreach ($specs as $spec) {
            if ($spec->category === Category::Vehicle) {
                $transporters[] = $spec->id;
            }
        }

        $violations = [];
        foreach ($specs as $spec) {
            if ($spec->carriedOn === null) {
                continue;
            }
            if ($spec->category === Category::Vehicle) {
                $violations[] = new Violation(
                    $spec->sourcePath,
                    'a transporter is not cargo, so `carried_on` does not belong on one',
                );
                continue;
            }
            if (!in_array($spec->carriedOn, $transporters, true)) {
                $violations[] = new Violation($spec->sourcePath, sprintf(
                    "carried_on '%s' is not a transporter in this library. Available: %s",
                    $spec->carriedOn,
                    $transporters === [] ? 'none' : implode(', ', $transporters),
                ));
            }
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
        foreach ($this->validateVehicle($spec) as $message) {
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
            foreach ($this->validateFeatureJoin($feature, $layout, $seen, $label, $spec->meshOverride !== null, $opening) as $message) {
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
     * Two horns sharing one mouth: `join` removes the baffle between them down to a stated depth.
     *
     * Everything here guards the same thing — that there is material between the two flares to remove,
     * and that some of the divider survives behind it. A join that names an overlapping pair, or one that
     * reaches past a throat, describes no cabinet; it would still carve *something*, which is worse than
     * failing, because a plausible-looking cavity is not one anybody would go back and check.
     *
     * @param array<string, bool> $seen features already declared, so `join.with` can only look backwards
     * @param array{float, float}|null $opening
     * @return list<string>
     */
    private function validateFeatureJoin(
        BaffleFeature $feature,
        BaffleLayout $layout,
        array $seen,
        string $label,
        bool $hasMeshOverride,
        ?array $opening,
    ): array {
        $join = $feature->join;
        if ($join === null) {
            return [];
        }

        $messages = [];

        if (!$feature->isHorn()) {
            return ["{$label}: join only applies to a horn"];
        }
        if ($hasMeshOverride) {
            // With a mesh_override the horns are shells built behind holes the CAD already cut, so there
            // is no baffle of ours for a join to open up — honouring it is not something we could do.
            $messages[] = "{$label}: join only applies to a generated cabinet, and this spec has a mesh_override";
        }
        if ($join->with === $feature->id) {
            return [...$messages, "{$label}: join.with names the feature itself"];
        }
        if (!isset($seen[$join->with])) {
            return [...$messages, "{$label}: `join.with: {$join->with}` must name an earlier feature"];
        }

        $partner = $layout->feature($join->with);
        if ($partner === null) {
            return $messages;
        }
        if (!$partner->isHorn()) {
            $messages[] = "{$label}: join only works between horns, and '{$partner->id}' is a {$partner->kind}";
        }
        foreach ([$feature, $partner] as $side) {
            if ($side->inside !== null) {
                $messages[] = sprintf(
                    "%s: join needs both horns on the baffle, and '%s' sits inside '%s'",
                    $label,
                    $side->id,
                    $side->inside,
                );
            }
        }

        if ($join->depthM <= 0) {
            $messages[] = "{$label}: join.depth_m must be greater than 0, got {$join->depthM}";
        } else {
            $shallowest = min($feature->depthM, $partner->depthM);
            if ($join->depthM >= $shallowest) {
                $messages[] = sprintf(
                    '%s: join.depth_m (%s) reaches the throat of the shallower horn (%s m) — nothing of the wall between them would be left',
                    $label,
                    $join->depthM,
                    $shallowest,
                );
            }
        }

        $partnerOpening = $partner->openingM();
        if ($feature->at === null || $partner->at === null || $opening === null || $partnerOpening === null) {
            return $messages;
        }

        // How the two mouths sit on the baffle: they have to line up on one axis, so the join has a
        // cross-section to open up, and be apart on the other, so there is something between them.
        $overlaps = [];
        foreach ([0 => 'x', 1 => 'z'] as $axis => $name) {
            $overlaps[$name] =
                min($feature->at[$axis] + $opening[$axis] / 2, $partner->at[$axis] + $partnerOpening[$axis] / 2)
                - max($feature->at[$axis] - $opening[$axis] / 2, $partner->at[$axis] - $partnerOpening[$axis] / 2);
        }

        if ($overlaps['x'] > 1e-9 && $overlaps['z'] > 1e-9) {
            $messages[] = sprintf(
                "%s: its mouth already overlaps '%s' — there is nothing between them to remove",
                $label,
                $partner->id,
            );
        } elseif ($overlaps['x'] <= 1e-9 && $overlaps['z'] <= 1e-9) {
            $messages[] = sprintf(
                "%s: its mouth lines up with '%s' on neither axis, so a join would open no shared mouth",
                $label,
                $partner->id,
            );
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

        return [
            ...$messages,
            ...$this->validateShapeBlocks($spec),
            ...$this->validateTruss($spec),
            ...$this->validateMovingHead($spec),
            ...$this->validateScaffold($spec),
        ];
    }

    /**
     * Each shape that is not a hexahedron needs its own block, and refuses everyone else's.
     *
     * One loop rather than the same six lines in three methods: the rule is identical for all of them, and it is
     * the rule the taper fields already follow — a shape's extra geometry is stated outright or the spec is wrong.
     *
     * @return list<string>
     */
    private function validateShapeBlocks(DeviceSpec $spec): array
    {
        $blocks = [
            'truss' => [Shape::Truss, $spec->truss],
            'moving_head' => [Shape::MovingHead, $spec->movingHead],
            'scaffold' => [Shape::Scaffold, $spec->scaffold],
        ];

        $messages = [];
        foreach ($blocks as $key => [$requiredBy, $value]) {
            if ($spec->shape === $requiredBy && $value === null) {
                $messages[] = "geometry.{$key} is required for shape '{$requiredBy->value}'";
            } elseif ($spec->shape !== $requiredBy && $value !== null) {
                $messages[] = "geometry.{$key} only applies to shape '{$requiredBy->value}', not '{$spec->shape->value}'";
            }
        }

        return $messages;
    }

    /**
     * A moving head's parts have to fit the box the datasheet gave, and add up to it.
     *
     * The height check is the one that earns its place. `dimensions_m.height` is quoted by manufacturers with the
     * head straight up, so base + head really should account for it — and a base and head that together overflow
     * the box would put geometry outside the volume scene placement and the overlap sweep reason about.
     *
     * @return list<string>
     */
    private function validateMovingHead(DeviceSpec $spec): array
    {
        $head = $spec->movingHead;
        if ($head === null) {
            return [];
        }

        $messages = [];

        foreach ([
            'base_height_m' => $head->baseHeight,
            'yoke_arm_thickness_m' => $head->yokeArmThickness,
            'head_diameter_m' => $head->headDiameter,
            'head_length_m' => $head->headLength,
        ] as $key => $value) {
            if ($value <= 0.0) {
                $messages[] = "geometry.moving_head.{$key} must be greater than 0, got {$value}";
            }
        }

        if ($head->baseHeight >= $spec->dimensions->height) {
            $messages[] = sprintf(
                'geometry.moving_head.base_height_m (%s) leaves no room for a head inside the %s m height',
                $head->baseHeight,
                $spec->dimensions->height,
            );
        }

        if ($head->baseHeight + $head->headLength > $spec->dimensions->height + self::FIT_TOLERANCE_M) {
            $messages[] = sprintf(
                'geometry.moving_head: base %s + head %s is taller than geometry.dimensions_m.height (%s)',
                $head->baseHeight,
                $head->headLength,
                $spec->dimensions->height,
            );
        }

        // The head hangs between the yoke arms, so it has to be narrower than the gap they leave.
        $gap = $spec->dimensions->width - 2 * $head->yokeArmThickness;
        if ($head->headDiameter > $gap + self::FIT_TOLERANCE_M) {
            $messages[] = sprintf(
                'geometry.moving_head.head_diameter_m (%s) does not fit the %s m between the yoke arms',
                $head->headDiameter,
                $gap,
            );
        }

        return $messages;
    }

    /**
     * A scaffold's platform has to be inside its frame, and its posts have to fit the footprint.
     *
     * @return list<string>
     */
    private function validateScaffold(DeviceSpec $spec): array
    {
        $scaffold = $spec->scaffold;
        if ($scaffold === null) {
            return [];
        }

        $messages = [];

        foreach ([
            'post_diameter_m' => $scaffold->postDiameter,
            'brace_diameter_m' => $scaffold->braceDiameter,
            'platform_height_m' => $scaffold->platformHeight,
            'platform_thickness_m' => $scaffold->platformThickness,
        ] as $key => $value) {
            if ($value <= 0.0) {
                $messages[] = "geometry.scaffold.{$key} must be greater than 0, got {$value}";
            }
        }

        if ($scaffold->platformHeight > $spec->dimensions->height) {
            $messages[] = sprintf(
                'geometry.scaffold.platform_height_m (%s) is above the frame — geometry.dimensions_m.height is %s.'
                .' A working height is not a bounding box; keep the reach in the notes',
                $scaffold->platformHeight,
                $spec->dimensions->height,
            );
        }

        $footprint = min($spec->dimensions->width, $spec->dimensions->depth);
        if ($scaffold->postDiameter * 2 > $footprint) {
            $messages[] = sprintf(
                'geometry.scaffold.post_diameter_m (%s) leaves no span between posts across the %s m footprint',
                $scaffold->postDiameter,
                $footprint,
            );
        }

        if ($scaffold->braceDiameter > $scaffold->postDiameter && $scaffold->postDiameter > 0.0) {
            $messages[] = sprintf(
                'geometry.scaffold.brace_diameter_m (%s) is thicker than the posts (%s) — bracing is the thinner tube',
                $scaffold->braceDiameter,
                $scaffold->postDiameter,
            );
        }

        return $messages;
    }

    /**
     * The `vehicle:` block: whether it belongs on this spec at all, and whether the two boxes and the two masses
     * make sense together.
     *
     * **The payload check is the one with consequences outside this repository.** Every other rule here protects a
     * render. A permitted gross mass at or below the mass in service means the vehicle may legally carry nothing,
     * which is never true of a real van and is always a transcribed digit — and a packer reading it would either
     * refuse every load or, if the sign went the other way, cheerfully authorise an overloaded one. So it is an
     * error rather than a warning, and it names both fields of the Zulassungsbescheinigung so the reader knows which
     * document to go back to.
     *
     * **The bay is checked against the outer box** for the same reason a truss chord is: `geometry.dimensions_m` is
     * what the rest of the repository measures this device by, and an inside larger than the outside is a unit slip
     * or a copied row from the wrong body variant. That last one is not hypothetical — the front-wheel-drive H3 bay
     * is 2144 mm against the rear-wheel-drive 2048 mm, and the L4 body only comes rear-wheel drive.
     *
     * @return list<string>
     */
    private function validateVehicle(DeviceSpec $spec): array
    {
        $vehicle = $spec->vehicle;
        $isVehicle = $spec->category === Category::Vehicle;

        if ($vehicle === null) {
            return $isVehicle
                ? ["category '{$spec->category->value}' needs a `vehicle` block stating at least permitted_gross_kg"]
                : [];
        }
        if (!$isVehicle) {
            return ["a `vehicle` block belongs to category 'vehicle', not '{$spec->category->value}'"];
        }

        $messages = [];

        // **A vehicle is drawn as a cage, and a bay is still optional.** Requiring both would have made them imply
        // each other and quietly killed the reason the bay is optional at all: a van can be specified from its
        // papers before anybody has been inside it, and no registration document states a load bay. So a bayless
        // vehicle draws its outline alone — which is the honest picture of a van whose inside nobody has measured,
        // and is still a cage rather than a solid.
        if ($spec->shape !== Shape::LoadBay) {
            $messages[] = sprintf(
                "a vehicle is drawn as a cage, so geometry.shape should be `load-bay` rather than `%s` — a solid"
                .' van is the largest object in any picture that includes it and hides the rig it carries',
                $spec->shape->value,
            );
        }

        if ($vehicle->permittedGrossKg <= $spec->weightKg) {
            $messages[] = sprintf(
                'vehicle.permitted_gross_kg (%s, Zulassungsbescheinigung F.2) is not above physical.weight_kg'
                .' (%s, field G), so the payload works out at %s kg. One of the two is transcribed wrong',
                $vehicle->permittedGrossKg,
                $spec->weightKg,
                round($vehicle->payloadKg($spec->weightKg), 1),
            );
        }

        $bay = $vehicle->loadBay;
        if ($bay === null) {
            return $messages;
        }

        foreach ([
            'width' => [$bay->width, $spec->dimensions->width],
            'height' => [$bay->height, $spec->dimensions->height],
            'depth' => [$bay->depth, $spec->dimensions->depth],
        ] as $axis => [$inside, $outside]) {
            if ($inside <= 0.0) {
                $messages[] = "vehicle.load_bay_m.{$axis} must be greater than 0, got {$inside}";
            } elseif ($inside > $outside) {
                $messages[] = sprintf(
                    'vehicle.load_bay_m.%s (%s) is bigger than the vehicle — geometry.dimensions_m.%s is %s.'
                    .' The bay is the inside and the dimensions are the outside',
                    $axis,
                    $inside,
                    $axis,
                    $outside,
                );
            }
        }

        if ($vehicle->widthBetweenArchesM !== null && $vehicle->widthBetweenArchesM > $bay->width) {
            $messages[] = sprintf(
                'vehicle.load_bay_m.width_between_arches (%s) is wider than the bay itself (%s) — the arches are'
                .' what narrow it',
                $vehicle->widthBetweenArchesM,
                $bay->width,
            );
        }

        foreach ([
            'door_aperture_width' => [$vehicle->doorApertureWidthM, $bay->width],
            'door_aperture_height' => [$vehicle->doorApertureHeightM, $bay->height],
        ] as $key => [$aperture, $limit]) {
            if ($aperture !== null && $aperture > $limit) {
                $messages[] = sprintf(
                    'vehicle.load_bay_m.%s (%s) is bigger than the bay behind it (%s) — a doorway cannot open onto'
                    .' more than there is',
                    $key,
                    $aperture,
                    $limit,
                );
            }
        }

        return $messages;
    }

    /**
     * The truss block's own numbers: chord count, tube diameters and the bay pitch. Whether the block is required
     * at all belongs to {@see validateShapeBlocks}.
     *
     * The fitting check is the one worth having. `dimensions_m` is the bounding box the rest of the repository
     * measures a truss by — scene placement, the overlap sweep, the catalog's shipping volume — so a chord fatter
     * than the box it is declared to sit in would put geometry outside the volume everything else reasons about.
     * Two chords plus the gap between them is the box's own cross-section, so a single chord can never exceed it.
     *
     * @return list<string>
     */
    private function validateTruss(DeviceSpec $spec): array
    {
        $truss = $spec->truss;
        if ($truss === null) {
            // Whether it is required at all is {@see validateShapeBlocks}' question, asked once for all three shapes.
            return [];
        }

        $messages = [];

        // Two is a ladder, three a triangle, four a box. Anything else is not a truss anybody sells.
        if ($truss->chords < 2 || $truss->chords > 4) {
            $messages[] = "geometry.truss.chords must be 2, 3 or 4, got {$truss->chords}";
        }

        foreach (['chord_diameter_m' => $truss->chordDiameter, 'diagonal_diameter_m' => $truss->diagonalDiameter] as $key => $diameter) {
            if ($diameter <= 0.0) {
                $messages[] = "geometry.truss.{$key} must be greater than 0, got {$diameter}";
            }
        }

        // The cross-section, not the length: a chord runs along the segment, so what has to contain it is the
        // box's other two edges.
        $section = min($spec->dimensions->height, $spec->dimensions->depth);
        if ($truss->chordDiameter > 0.0 && $truss->chordDiameter > $section) {
            $messages[] = sprintf(
                'geometry.truss.chord_diameter_m (%s) does not fit the %s m cross-section of geometry.dimensions_m',
                $truss->chordDiameter,
                $section,
            );
        }

        if ($truss->diagonalDiameter > $truss->chordDiameter && $truss->chordDiameter > 0.0) {
            $messages[] = sprintf(
                'geometry.truss.diagonal_diameter_m (%s) is thicker than the chords (%s) — bracing is the thinner tube',
                $truss->diagonalDiameter,
                $truss->chordDiameter,
            );
        }

        if ($truss->bayLength <= 0.0) {
            $messages[] = "geometry.truss.bay_length_m must be greater than 0, got {$truss->bayLength}";
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
                $messages[] = "build is 'self-built' but clone_of is missing — name the original's manufacturer and model";
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

        $passband = $spec->passband;
        if ($passband !== null) {
            if ($passband->lowHz <= 0.0) {
                $messages[] = "audio.passband_hz.low_hz must be greater than 0, got {$passband->lowHz}";
            }
            if ($passband->highHz <= $passband->lowHz) {
                $messages[] = sprintf(
                    'audio.passband_hz.high_hz (%s) must be above low_hz (%s)',
                    $passband->highHz,
                    $passband->lowHz,
                );
            }
            if ($passband->drivenFromHz !== null && $passband->drivenFromHz < $passband->lowHz) {
                // High-passing *below* what the cabinet reaches is not a choice, it is a typo — and it would
                // silently reorder a stack, since the driven corner is what a `stack` sorts on.
                $messages[] = sprintf(
                    'audio.passband_hz.driven_from_hz (%s) is below low_hz (%s) — a cabinet cannot be driven '
                    .'lower than it reaches',
                    $passband->drivenFromHz,
                    $passband->lowHz,
                );
            }
            if ($passband->drivenFromHz !== null && $passband->drivenFromHz >= $passband->highHz) {
                $messages[] = sprintf(
                    'audio.passband_hz.driven_from_hz (%s) is at or above high_hz (%s), which leaves no band',
                    $passband->drivenFromHz,
                    $passband->highHz,
                );
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
