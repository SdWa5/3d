<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Semantic checks on already-parsed specs. Everything here is a rule that a well-formed spec
 * file can still get wrong — impossible dimensions, a clone with no original, a rigging point
 * outside the cabinet. All violations of all specs are collected, because fixing a library one
 * error per run is miserable.
 *
 * Asset, baffle, physical, shape and vehicle checks are delegated without changing violation order.
 *
 * The rules exist to protect the promise that makes the library useful: every model shares one
 * scale, one orientation and one origin convention, and every number knows where it came from.
 */
final class SpecValidator
{
    private readonly LayoutValidator $layout;
    private readonly ShapeValidator $shape;
    private readonly VehicleValidator $vehicle;
    private readonly AssetValidator $assets;
    private readonly PhysicalValidator $physical;

    public function __construct(private readonly string $projectDir)
    {
        $this->layout = new LayoutValidator();
        $this->shape = new ShapeValidator();
        $this->vehicle = new VehicleValidator();
        $this->assets = new AssetValidator($projectDir);
        $this->physical = new PhysicalValidator();
    }

    /**
     * @param list<DeviceSpec> $specs
     *
     * @return list<Violation>
     */
    public function validate(array $specs): array
    {
        $violations = [];
        foreach ($specs as $spec) {
            foreach ($this->validateOne($spec) as $violation) {
                $violations[] = $violation;
            }
            foreach ($this->assets->warnAboutMissingMesh($spec) as $violation) {
                $violations[] = $violation;
            }
            foreach ($this->assets->warnAboutMissingFrontImage($spec) as $violation) {
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
     *
     * @return list<Violation>
     */
    private function validateCarriedOn(array $specs): array
    {
        $transporters = [];
        foreach ($specs as $spec) {
            if (Category::Vehicle === $spec->category) {
                $transporters[] = $spec->id;
            }
        }

        $violations = [];
        foreach ($specs as $spec) {
            if (null === $spec->carriedOn) {
                continue;
            }
            if (Category::Vehicle === $spec->category) {
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
                    [] === $transporters ? 'none' : implode(', ', $transporters),
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

        if (1 !== preg_match(ValidationRules::ID_PATTERN, $spec->id)) {
            $add("id '{$spec->id}' must be lowercase words separated by single dashes");
        }
        $expectedId = pathinfo($spec->sourcePath, PATHINFO_FILENAME);
        if ($spec->id !== $expectedId) {
            $add("id '{$spec->id}' does not match the filename '{$expectedId}'");
        }

        if ($spec->quantity < 1) {
            $add("quantity must be at least 1, got {$spec->quantity}");
        }

        if (1 !== preg_match(ValidationRules::ID_PATTERN, $spec->owner)) {
            $add("owner '{$spec->owner}' must be lowercase words separated by single dashes");
        }

        $allowedSubtypes = $spec->category->allowedSubtypes();
        if (null !== $allowedSubtypes && !in_array($spec->subtype, $allowedSubtypes, true)) {
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

        if (1 !== preg_match(ValidationRules::COLOR_PATTERN, $spec->color)) {
            $add("appearance.color '{$spec->color}' must be a #rrggbb hex colour");
        }
        if (null !== $spec->grilleColor && 1 !== preg_match(ValidationRules::COLOR_PATTERN, $spec->grilleColor)) {
            $add("appearance.grille.color '{$spec->grilleColor}' must be a #rrggbb hex colour");
        }
        if (null !== $spec->castors) {
            foreach ($this->physical->validate($spec->castors, $spec->dimensions) as $message) {
                $add($message);
            }
        }
        if (null !== $spec->frontColor) {
            if (1 !== preg_match(ValidationRules::COLOR_PATTERN, $spec->frontColor)) {
                $add("appearance.front_color '{$spec->frontColor}' must be a #rrggbb hex colour");
            }
            // Painted on a generated shell's front face, with or without a layout, so an imported mesh would carry
            // a colour that nothing draws, and a front image would cover it.
            if (null !== $spec->meshOverride) {
                $add('appearance.front_color needs a generated shell, not a mesh_override');
            }
            if (null !== $spec->frontImage) {
                $add('appearance.front_color and front_image both colour the front, so state one');
            }
        }
        if (null !== $spec->grilleInset) {
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

        foreach ($this->shape->validate($spec) as $message) {
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

        foreach ($this->assets->validateMeshOverride($spec) as $message) {
            $add($message);
        }
        foreach ($this->assets->validateFrontImage($spec) as $message) {
            $add($message);
        }
        foreach ($this->layout->validate($spec) as $message) {
            $add($message);
        }
        foreach ($this->vehicle->validate($spec) as $message) {
            $add($message);
        }

        return $violations;
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
            if (null === $spec->cloneOf) {
                $messages[] = "build is 'self-built' but clone_of is missing — name the original's manufacturer and model";
            }
        } elseif (null !== $spec->cloneOf) {
            $messages[] = "clone_of is set but build is '{$spec->build->value}' — only clones copy an original";
        }

        if (null !== $spec->cloneOf) {
            $allowedReferences = ['datasheet', 'plans', 'cad', 'none'];
            if (!in_array($spec->cloneOf->reference, $allowedReferences, true)) {
                $allowed = implode(', ', $allowedReferences);
                $messages[] = "clone_of.reference '{$spec->cloneOf->reference}' is unknown (allowed: {$allowed})";
            }
        }

        // A clone's datasheet numbers must come from the original it copies, so that original has
        // to be named. Factory gear is exempt: the datasheet is its own, and there is no clone.
        if ($spec->isClone() && null === $spec->cloneOf) {
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

        if ($spec->flyable && [] === $spec->riggingPoints) {
            $messages[] = 'rigging.flyable is true but no rigging.points are defined';
        }
        if (!$spec->flyable && [] !== $spec->riggingPoints) {
            $messages[] = 'rigging.points are defined but rigging.flyable is false';
        }
        if (Origin::RiggingPoint === $spec->origin && [] === $spec->riggingPoints) {
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

        if (null !== $spec->coverage) {
            foreach (['horizontal' => $spec->coverage->horizontal, 'vertical' => $spec->coverage->vertical] as $axis => $value) {
                if ($value <= 0 || $value > 360) {
                    $messages[] = "audio.coverage_deg.{$axis} must be between 0 and 360, got {$value}";
                }
            }
        }

        $passband = $spec->passband;
        if (null !== $passband) {
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
        }

        if (null !== $spec->power && $spec->power->rmsW <= 0.0) {
            $messages[] = "audio.power_w.rms must be greater than 0, got {$spec->power->rmsW}";
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
     *
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
}
