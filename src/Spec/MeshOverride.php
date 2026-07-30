<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A hand-made or downloaded mesh standing in for the generated block.
 *
 * Real CAD arrives in whatever units and orientation its author used, so the source has to say
 * which — guessing would silently produce a model at the wrong scale, and a wrongly scaled cabinet
 * is worse than a plain box. After importing, the builder checks the mesh against the spec's
 * declared dimensions and refuses to build if they disagree: the spec stays the authority.
 */
final class MeshOverride
{
    /** Formats Blender can import. `.FCStd` is deliberately absent — Blender cannot read FreeCAD. */
    public const IMPORTABLE = ['obj', 'glb', 'gltf', 'stl', 'ply', 'blend'];

    public const UNITS = ['m' => 1.0, 'cm' => 0.01, 'mm' => 0.001];

    public const DEFAULT_TOLERANCE_M = 0.005;

    /**
     * @param array{float, float, float} $rotateDeg applied X, then Y, then Z, before measuring
     */
    public function __construct(
        public readonly string $path,
        public readonly string $units = 'm',
        public readonly array $rotateDeg = [0.0, 0.0, 0.0],
        public readonly float $toleranceM = self::DEFAULT_TOLERANCE_M,
    ) {
    }

    /**
     * Accepts a bare path as well as the expanded form — a mesh already in metres and in our
     * orientation needs nothing else said about it.
     *
     * ```yaml
     * mesh_override: meshes/sub.glb
     * mesh_override:
     *   path: meshes/sub.obj
     *   units: mm
     *   rotate_deg: [90, 0, 90]
     * ```
     */
    public static function fromReader(ArrayReader $reader, string $key): ?self
    {
        if (!$reader->has($key)) {
            return null;
        }

        if (!$reader->isSection($key)) {
            return new self($reader->requireString($key));
        }

        $section = $reader->requireSection($key);

        return new self(
            $section->requireString('path'),
            $section->optionalString('units', 'm') ?? 'm',
            $section->has('rotate_deg') ? $section->requireVector3('rotate_deg') : [0.0, 0.0, 0.0],
            $section->optionalFloat('tolerance_m', self::DEFAULT_TOLERANCE_M) ?? self::DEFAULT_TOLERANCE_M,
        );
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function isImportable(): bool
    {
        return in_array($this->extension(), self::IMPORTABLE, true);
    }

    public function unitScale(): ?float
    {
        return self::UNITS[$this->units] ?? null;
    }

    /**
     * @return array{path: string, units: string, unit_scale: float, rotate_deg: array{float, float, float}, tolerance_m: float}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'units' => $this->units,
            'unit_scale' => $this->unitScale() ?? 1.0,
            'rotate_deg' => $this->rotateDeg,
            'tolerance_m' => $this->toleranceM,
        ];
    }
}
