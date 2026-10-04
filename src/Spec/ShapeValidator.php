<?php

declare(strict_types=1);

namespace App\Spec;

/** Checks shape-specific geometry and component bounds. */
final class ShapeValidator
{
    /** The shapes `blender/lib` can draw in their packed form, see {@see validateTransport()}. */
    private const FOLDING_SHAPES = [Shape::Mast, Shape::Scaffold];

    /**
     * A tapered shape needs its taper stated outright. Deriving it from a ratio would invent a
     * measurement, and an invented measurement is exactly what the provenance rules exist to stop.
     *
     * @return list<string>
     */
    public function validate(DeviceSpec $spec): array
    {
        $messages = [];

        $extras = [
            'back_width_m' => [Shape::Trapezoid, $spec->backWidth, $spec->dimensions->width, 'width'],
            'front_height_m' => [Shape::Wedge, $spec->frontHeight, $spec->dimensions->height, 'height'],
        ];

        foreach ($extras as $key => [$requiredBy, $value, $limit, $limitName]) {
            if ($spec->shape === $requiredBy) {
                if (null === $value) {
                    $messages[] = "geometry.{$key} is required for shape '{$requiredBy->value}'";
                    continue;
                }
                if ($value <= 0) {
                    $messages[] = "geometry.{$key} must be greater than 0, got {$value}";
                } elseif ($limit > 0 && $value > $limit) {
                    $messages[] = "geometry.{$key} ({$value}) must not exceed geometry.dimensions_m.{$limitName} ({$limit})";
                }
            } elseif (null !== $value) {
                $messages[] = "geometry.{$key} only applies to shape '{$requiredBy->value}', not '{$spec->shape->value}'";
            }
        }

        return [
            ...$messages,
            ...$this->validateShapeBlocks($spec),
            ...$this->validateTruss($spec),
            ...$this->validateMovingHead($spec),
            ...$this->validateScaffold($spec),
            ...$this->validateMast($spec),
            ...$this->validateTransport($spec),
        ];
    }

    /**
     * Each shape that is not a hexahedron needs its own block, and refuses everyone else's.
     *
     * One loop rather than the same six lines in four methods: the rule is identical for all of them, and it is
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
            'mast' => [Shape::Mast, $spec->mast],
        ];

        $messages = [];
        foreach ($blocks as $key => [$requiredBy, $value]) {
            if ($spec->shape === $requiredBy && null === $value) {
                $messages[] = "geometry.{$key} is required for shape '{$requiredBy->value}'";
            } elseif ($spec->shape !== $requiredBy && null !== $value) {
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
        if (null === $head) {
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

        if ($head->baseHeight + $head->headLength > $spec->dimensions->height + ValidationRules::FIT_TOLERANCE_M) {
            $messages[] = sprintf(
                'geometry.moving_head: base %s + head %s is taller than geometry.dimensions_m.height (%s)',
                $head->baseHeight,
                $head->headLength,
                $spec->dimensions->height,
            );
        }

        // The head hangs between the yoke arms, so it has to be narrower than the gap they leave.
        $gap = $spec->dimensions->width - 2 * $head->yokeArmThickness;
        if ($head->headDiameter > $gap + ValidationRules::FIT_TOLERANCE_M) {
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
        if (null === $scaffold) {
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
     * A wind-up stand's tubes have to nest inside each other and inside the column, and its stages have to overlap.
     *
     * **The overlap check is the one that earns its place.** A tube length and a stage count that leave a stage
     * standing out of the one below it on a few centimetres would draw a stand that falls apart at full extension.
     * The legs are held to the footprint the datasheet states, because the spread is what has to be kept clear.
     *
     * @return list<string>
     */
    private function validateMast(DeviceSpec $spec): array
    {
        $mast = $spec->mast;
        if (null === $mast) {
            return [];
        }

        $messages = [];
        $height = $spec->dimensions->height;

        foreach ([
            'transport_length_m' => $mast->transportLength,
            'hub_height_m' => $mast->hubHeight,
            'spigot_diameter_m' => $mast->spigotDiameter,
            'base_spread_m' => $mast->baseSpread,
            'leg_width_m' => $mast->legWidth,
            ...(null === $mast->adapter ? [] : [
                'adapter.length_m' => $mast->adapter->length,
                'adapter.bar_m' => $mast->adapter->bar,
                'adapter.height_m' => $mast->adapter->height,
                'adapter.clamp_spacing_m' => $mast->adapter->clampSpacing,
            ]),
        ] as $key => $value) {
            if ($value <= 0.0) {
                $messages[] = "geometry.mast.{$key} must be greater than 0, got {$value}";
            }
        }
        if ([] !== $messages) {
            return $messages;
        }

        if (Origin::BottomCenter !== $spec->origin) {
            $messages[] = "geometry.mast stands on the floor and needs origin 'bottom-center', not '{$spec->origin->value}'";
        }

        $sections = $mast->sections;
        if (count($sections) < 2) {
            $messages[] = 'geometry.mast.sections_m needs a sleeve and at least one stage, got '.count($sections);
        }
        // A counted loop rather than `foreach ($sections as $index => ...)`. Under PHP 8.3.33's tracing JIT the
        // foreach key came back as a pointer-sized garbage integer once this method ran hot in the suite, measured
        // on 2026-10-01: `sections_m[94669609268640]` and "Undefined array key -1" with the JIT on, a clean run with
        // `-d opcache.jit=disable`. Indexing the list directly does not take that path.
        $sections = array_values($sections);
        for ($index = 0, $count = count($sections); $index < $count; ++$index) {
            $diameter = $sections[$index];
            if ($diameter <= 0.0) {
                $messages[] = "geometry.mast.sections_m[{$index}] must be greater than 0, got {$diameter}";
            } elseif ($index > 0 && $diameter >= $sections[$index - 1]) {
                $messages[] = sprintf(
                    'geometry.mast.sections_m[%d] (%s) does not fit inside the tube below it (%s)',
                    $index,
                    $diameter,
                    $sections[$index - 1],
                );
            }
        }
        if ([] !== $messages) {
            return $messages;
        }

        $column = min($spec->dimensions->width, $spec->dimensions->depth);
        if ($sections[0] > $column + ValidationRules::FIT_TOLERANCE_M) {
            $messages[] = sprintf('geometry.mast.sections_m[0] (%s) is wider than the %s m column', $sections[0], $column);
        }
        if ($mast->spigotDiameter >= $sections[count($sections) - 1]) {
            $messages[] = sprintf(
                'geometry.mast.spigot_diameter_m (%s) does not fit the top stage (%s)',
                $mast->spigotDiameter,
                $sections[count($sections) - 1],
            );
        }

        if ($mast->collapsedHeightM() >= $height) {
            $messages[] = sprintf(
                'geometry.mast collapses to %.3f m, which is not below its %s m full extension',
                $mast->collapsedHeightM(),
                $height,
            );
        }
        if ($mast->hubHeight >= $mast->sleeveBottomM() + $mast->tubeLengthM()) {
            $messages[] = sprintf(
                'geometry.mast.hub_height_m (%s) is above the sleeve, which ends at %.3f m',
                $mast->hubHeight,
                $mast->sleeveBottomM() + $mast->tubeLengthM(),
            );
        }
        if ($mast->overlapM($height) < Mast::MIN_OVERLAP_M) {
            $messages[] = sprintf(
                'geometry.mast: at %s m each stage overlaps the one below by %.3f m, under the %.2f m a stage needs.'
                .' Add a section or lengthen the tubes',
                $height,
                $mast->overlapM($height),
                Mast::MIN_OVERLAP_M,
            );
        }

        if ($mast->legs < 3 || $mast->legs > 8) {
            $messages[] = "geometry.mast.legs must be 3 to 8, got {$mast->legs}";
        }
        if ($mast->baseSpread < max($spec->dimensions->width, $spec->dimensions->depth)) {
            $messages[] = sprintf('geometry.mast.base_spread_m (%s) is narrower than the column', $mast->baseSpread);
        }

        $adapter = $mast->adapter;
        if (null !== $adapter) {
            if ($adapter->length > $mast->baseSpread) {
                $messages[] = sprintf(
                    'geometry.mast.adapter.length_m (%s) reaches past the %s m base spread',
                    $adapter->length,
                    $mast->baseSpread,
                );
            }
            if ($adapter->clampSpacing >= $adapter->length) {
                $messages[] = sprintf(
                    'geometry.mast.adapter.clamp_spacing_m (%s) puts the clamps off the %s m bar',
                    $adapter->clampSpacing,
                    $adapter->length,
                );
            }
            if ($adapter->height <= 2 * $adapter->bar) {
                $messages[] = sprintf(
                    'geometry.mast.adapter.height_m (%s) leaves no spigot under a %s m bar and its clamps',
                    $adapter->height,
                    $adapter->bar,
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
        if (null === $truss) {
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
     * The packed box, which only a device that can be drawn folded may state.
     *
     * **A box that differs from the erected one needs a packed drawing**, because the pack renders the real model.
     * A mast folds its legs and collapses its stages, and a scaffold comes apart into a bundle of frames, and
     * `blender/lib` draws both. Any other shape would render erected inside a box that claims otherwise, so it is
     * refused until it has a drawing. `upright` alone needs nothing drawn and is allowed on every shape.
     *
     * **A mast's packed height is its transport length**, which the `mast` block already states. Two fields for one
     * figure drift, so they must agree.
     *
     * @return list<string>
     */
    private function validateTransport(DeviceSpec $spec): array
    {
        $box = $spec->transport?->dimensions;
        if (null === $box) {
            return [];
        }

        $messages = [];
        foreach ($box->toArray() as $axis => $value) {
            if ($value <= 0) {
                $messages[] = "transport.dimensions_m.{$axis} must be greater than 0, got {$value}";
            }
        }

        if ($box->toArray() !== $spec->dimensions->toArray() && !in_array($spec->shape, self::FOLDING_SHAPES, true)) {
            $messages[] = sprintf(
                "transport.dimensions_m differs from the erected box, which only a shape drawn folded may do (%s), not '%s'",
                implode(', ', array_map(static fn (Shape $shape): string => $shape->value, self::FOLDING_SHAPES)),
                $spec->shape->value,
            );
        }

        $length = $spec->mast?->transportLength;
        if (null !== $length && abs($box->height - $length) > 1e-9) {
            $messages[] = sprintf(
                'transport.dimensions_m.height (%s) must equal geometry.mast.transport_length_m (%s)',
                $box->height,
                $length,
            );
        }

        return $messages;
    }
}
