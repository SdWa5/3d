<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One piece of equipment, as declared in its spec file. This is the single source of truth for
 * the whole pipeline: geometry is generated from it, the catalog is rendered from it, and the
 * metadata it carries is written into the exported glTF so scenes can read it back.
 *
 * Values are taken at face value — validating them is SpecValidator's job.
 */
final class DeviceSpec
{
    /**
     * Whose gear it is. Some cabinets at an SdWa5 event are lent by a member, and a setup that
     * quietly depends on borrowed boxes is a setup that can fall apart — so ownership is recorded
     * rather than pooled.
     */
    public const DEFAULT_OWNER = 'sdwa5';

    /**
     * @param list<string> $handles
     * @param list<RiggingPoint> $riggingPoints
     * @param list<Driver> $drivers
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $id,
        public readonly string $name,
        public readonly Category $category,
        public readonly string $subtype,
        public readonly int $quantity,
        public readonly string $owner,
        public readonly BuildKind $build,
        public readonly ?CloneOf $cloneOf,
        public readonly ProvenanceSet $provenance,
        public readonly ?string $deviations,
        public readonly Shape $shape,
        public readonly Dimensions $dimensions,
        public readonly ?float $backWidth,
        public readonly ?float $frontHeight,
        public readonly Origin $origin,
        public readonly float $chamfer,
        public readonly string $color,
        public readonly ?float $grilleInset,
        public readonly ?string $grilleColor,
        public readonly float $weightKg,
        public readonly array $handles,
        public readonly bool $flyable,
        public readonly array $riggingPoints,
        public readonly ?Coverage $coverage,
        public readonly array $drivers,
        public readonly ?string $meshOverride,
        public readonly ?string $notes,
    ) {
    }

    /**
     * @param array<string, mixed> $data decoded YAML of one spec file
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        $reader = new ArrayReader($data);

        $geometry = $reader->requireSection('geometry');
        $appearance = $reader->optionalSection('appearance');
        $grille = $appearance?->optionalSection('grille');
        $physical = $reader->requireSection('physical');
        $rigging = $reader->optionalSection('rigging');
        $audio = $reader->optionalSection('audio');
        $coverage = $audio?->optionalSection('coverage_deg');

        $build = $reader->requireEnum('build', BuildKind::class);
        $cloneOfSection = $reader->optionalSection('clone_of');

        return new self(
            sourcePath: $sourcePath,
            id: $reader->requireString('id'),
            name: $reader->requireString('name'),
            category: $reader->requireEnum('category', Category::class),
            subtype: $reader->requireString('subtype'),
            quantity: $reader->optionalInt('quantity', 1) ?? 1,
            owner: $reader->optionalString('owner', self::DEFAULT_OWNER) ?? self::DEFAULT_OWNER,
            build: $build,
            cloneOf: $cloneOfSection !== null ? CloneOf::fromReader($cloneOfSection) : null,
            provenance: ProvenanceSet::fromReader($reader, 'provenance'),
            deviations: $reader->optionalString('deviations'),
            shape: $geometry->optionalEnum('shape', Shape::class, Shape::Box),
            dimensions: Dimensions::fromReader($geometry->requireSection('dimensions_m')),
            backWidth: $geometry->optionalFloat('back_width_m'),
            frontHeight: $geometry->optionalFloat('front_height_m'),
            origin: $geometry->optionalEnum('origin', Origin::class, Origin::BottomCenter),
            chamfer: $geometry->optionalFloat('chamfer_m', 0.0) ?? 0.0,
            color: $appearance?->optionalString('color', '#111111') ?? '#111111',
            grilleInset: $grille?->optionalFloat('inset_m'),
            grilleColor: $grille?->optionalString('color'),
            weightKg: $physical->requireFloat('weight_kg'),
            handles: $physical->stringList('handles'),
            flyable: $rigging?->optionalBool('flyable') ?? false,
            riggingPoints: array_map(
                static fn (ArrayReader $point): RiggingPoint => RiggingPoint::fromReader($point),
                $rigging?->sectionList('points') ?? [],
            ),
            coverage: $coverage !== null ? Coverage::fromReader($coverage) : null,
            drivers: array_map(
                static fn (ArrayReader $driver): Driver => Driver::fromReader($driver),
                $audio?->sectionList('drivers') ?? [],
            ),
            meshOverride: $reader->optionalString('mesh_override'),
            notes: $reader->optionalString('notes'),
        );
    }

    public function isClone(): bool
    {
        return $this->build === BuildKind::Clone;
    }

    /**
     * What the device is a copy of, for catalog and report output.
     */
    public function originalLabel(): string
    {
        return $this->cloneOf?->label() ?? '—';
    }

    /**
     * Total weight of all units of this device, which is the number that matters when loading
     * the van or checking a truss's capacity.
     */
    public function totalWeightKg(): float
    {
        return $this->weightKg * $this->quantity;
    }

    /**
     * The cabinet's extent in the measuring frame: middle of the footprint, +Z up, front towards
     * −Y, +X to the cabinet's right seen from the front. That is the frame a tape measure gives
     * you, so every position in a spec — rigging points above all — is expressed in it,
     * independently of which `origin` the finished model uses. The origin only moves the model.
     *
     * @return array{min: array{float, float, float}, max: array{float, float, float}}
     */
    public function measuringBox(): array
    {
        $halfWidth = $this->dimensions->width / 2;
        $halfDepth = $this->dimensions->depth / 2;

        return [
            'min' => [-$halfWidth, -$halfDepth, 0.0],
            'max' => [$halfWidth, $halfDepth, $this->dimensions->height],
        ];
    }

    /**
     * Metadata written into the glTF `extras` block, so a model stays self-describing once it
     * leaves this repo.
     *
     * @return array<string, mixed>
     */
    public function toMetadataArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'subtype' => $this->subtype,
            'quantity' => $this->quantity,
            'owner' => $this->owner,
            'build' => $this->build->value,
            'clone_of' => $this->cloneOf?->toArray(),
            'provenance' => $this->provenance->toArray(),
            'dimensions_m' => $this->dimensions->toArray(),
            // Consumers need the origin to interpret the geometry — tools/check-glb.py uses it to
            // decide whether a cabinet is supposed to sit on the floor.
            'origin' => $this->origin->value,
            'weight_kg' => $this->weightKg,
            'flyable' => $this->flyable,
            'rigging_points' => array_map(
                static fn (RiggingPoint $point): array => $point->toArray(),
                $this->riggingPoints,
            ),
            'coverage_deg' => $this->coverage?->toArray(),
            'drivers' => array_map(static fn (Driver $driver): array => $driver->toArray(), $this->drivers),
        ];
    }
}
