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
        public readonly ?Truss $truss,
        public readonly ?MovingHead $movingHead,
        public readonly ?Scaffold $scaffold,
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
        /** The band it covers and the band it is driven over; orders a `stack`. Optional. */
        public readonly ?Passband $passband,
        public readonly array $drivers,
        public readonly ?BaffleLayout $layout,
        public readonly ?MeshOverride $meshOverride,
        /** What this transporter can carry. Present exactly when the category is `vehicle`. */
        public readonly ?Vehicle $vehicle,
        /**
         * The id of the one transporter this device may ride on, or null when any of them will do.
         *
         * **A pack is not free to put every device anywhere, and until this field existed it assumed otherwise.**
         * Sepp's 465 kg generator is the case: two people cannot lift it, it needs a ramp or a forklift, and it has
         * no business inside a van at all. The planner scores bins by how strained they are, so it sent the
         * generator to the *van* and filled the trailer with speaker cabinets — a plan that is legal on every
         * weight check and impossible to load.
         *
         * Deliberately one bin rather than a list. Every case anybody has is "this rides on that", and a list would
         * invite a set of permissions nobody can state.
         */
        public readonly ?string $carriedOn,
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
            truss: ($trussSection = $geometry->optionalSection('truss')) !== null
                ? Truss::fromReader($trussSection)
                : null,
            movingHead: ($headSection = $geometry->optionalSection('moving_head')) !== null
                ? MovingHead::fromReader($headSection)
                : null,
            scaffold: ($scaffoldSection = $geometry->optionalSection('scaffold')) !== null
                ? Scaffold::fromReader($scaffoldSection)
                : null,
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
            passband: Passband::fromReader($audio?->optionalSection('passband_hz')),
            drivers: array_map(
                static fn (ArrayReader $driver): Driver => Driver::fromReader($driver),
                $audio?->sectionList('drivers') ?? [],
            ),
            layout: BaffleLayout::fromReader($audio?->optionalSection('layout')),
            meshOverride: MeshOverride::fromReader($reader, 'mesh_override'),
            vehicle: ($vehicleSection = $reader->optionalSection('vehicle')) !== null
                ? Vehicle::fromReader($vehicleSection)
                : null,
            carriedOn: $reader->optionalString('carried_on'),
            notes: $reader->optionalString('notes'),
        );
    }

    /**
     * Whether this device copies somebody else's design, and so has an original to cite.
     *
     * The build kind is spelled `self-built`; the thing it produces is still a clone, which is why the
     * block naming the original is `clone_of` and this reads the way it does.
     */
    public function isClone(): bool
    {
        return $this->build === BuildKind::SelfBuilt;
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
     * The cabinet's eight corners in the measuring frame.
     *
     * The taper is real geometry, not decoration: treating a trapezoid as a full-width box overstates a
     * concave arc's width by 9%, and a footprint computed from these is meant to be the exact rotated
     * one. A wedge's front corners are likewise only as tall as its front.
     *
     * @return list<array{float, float, float}>
     */
    public function shellCorners(): array
    {
        $halfDepth = $this->dimensions->depth / 2;
        $frontHeight = $this->frontHeight ?? $this->dimensions->height;
        $halfBack = ($this->backWidth ?? $this->dimensions->width) / 2;

        $planes = [
            [$this->dimensions->width / 2, -$halfDepth, $frontHeight],
            [$halfBack, $halfDepth, $this->dimensions->height],
        ];

        return self::cornersOf($planes);
    }

    /**
     * The corners that decide whether two cabinets touch: the shell, plus the grille frame standing
     * proud of it.
     *
     * `appearance.grille.inset_m` builds a **full-width** slab across the very front, so the taper only
     * runs over `depth − inset`. Ignoring it, a Tecnare's flush arc comes out at 16.95° when the built
     * meshes actually need 17.35°, and they overlap by 3.5 mm. It is a wider set than
     * {@see shellCorners} rather than a different one, which is why contact and footprint can share a
     * definition of the cabinet and disagree only about the frame.
     *
     * @return list<array{float, float, float}>
     */
    public function contactCorners(): array
    {
        $halfDepth = $this->dimensions->depth / 2;
        $frontHeight = $this->frontHeight ?? $this->dimensions->height;
        $halfBack = ($this->backWidth ?? $this->dimensions->width) / 2;
        $halfFront = $this->dimensions->width / 2;

        $planes = [
            [$halfFront, -$halfDepth, $frontHeight],
            [$halfFront, -$halfDepth + ($this->grilleInset ?? 0.0), $frontHeight],
            [$halfBack, $halfDepth, $this->dimensions->height],
        ];

        return self::cornersOf($planes);
    }

    /**
     * @param list<array{float, float, float}> $planes half-width, y and top height of each cross-section
     * @return list<array{float, float, float}>
     */
    private static function cornersOf(array $planes): array
    {
        $corners = [];
        foreach ($planes as [$halfWidth, $y, $top]) {
            foreach ([-$halfWidth, $halfWidth] as $x) {
                foreach ([0.0, $top] as $z) {
                    $corners[] = [$x, $y, $z];
                }
            }
        }

        return $corners;
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
            // Also for tools/check-glb.py: easing a *tapered* cabinet's corners takes its widest point
            // with them, so its bounding box is legitimately a little under its declared width and the
            // checker needs to know by how much it may be.
            'chamfer_m' => $this->chamfer,
            'weight_kg' => $this->weightKg,
            'flyable' => $this->flyable,
            'rigging_points' => array_map(
                static fn (RiggingPoint $point): array => $point->toArray(),
                $this->riggingPoints,
            ),
            'coverage_deg' => $this->coverage?->toArray(),
            'drivers' => array_map(static fn (Driver $driver): array => $driver->toArray(), $this->drivers),
            'baffle_layout' => $this->layout?->toArray(),
            // Only the basename: a local absolute path has no business travelling inside a .glb.
            // The tolerance travels so tools/check-glb.py can apply the same one the builder did.
            'mesh_override' => $this->meshOverride === null ? null : [
                'file' => basename($this->meshOverride->path),
                'units' => $this->meshOverride->units,
                'tolerance_m' => $this->meshOverride->toleranceM,
            ],
        ];
    }
}
