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

        if ($spec->meshOverride !== null) {
            $path = $this->resolve($spec->meshOverride);
            if (!is_file($path)) {
                $add("mesh_override '{$spec->meshOverride}' does not exist");
            }
        }

        return $violations;
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

        // Datasheet and plans values can only come from an original, so they need one named.
        if (in_array($spec->provenance, [Provenance::Datasheet, Provenance::Plans], true) && $spec->cloneOf === null) {
            $messages[] = "provenance is '{$spec->provenance->value}' but no clone_of names where those numbers came from";
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
