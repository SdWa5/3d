<?php

declare(strict_types=1);

namespace App\Render;

/**
 * How the scene is lit. Light positions are given relative to the scene's size, so a preset lights a
 * single cabinet and a whole rig the same way instead of needing to be re-tuned per scene.
 *
 * `Flat` exists for a different job than the others: even, shadowless light for looking at geometry —
 * checking a chamfer or a grille inset — rather than for producing a picture anybody wants to see.
 */
enum LightingPreset: string
{
    case Studio = 'studio';
    case Stage = 'stage';
    case Daylight = 'daylight';
    case Flat = 'flat';

    /**
     * Lights as {kind, at (multiples of the scene radius, from its centre), energy_per_area, color}.
     * Area-light energy is scaled by the scene size in RenderPlan — a big rig needs more light to
     * reach the same brightness.
     *
     * @return list<array{kind: string, at: array{float, float, float}, energy: float, size: float, color: array{float, float, float}}>
     */
    public function lights(): array
    {
        return match ($this) {
            // Neutral three-point: readable, honest, no drama.
            self::Studio => [
                ['kind' => 'AREA', 'at' => [-1.3, -1.7, 1.5], 'energy' => 620.0, 'size' => 1.4, 'color' => [1.0, 0.98, 0.95]],
                ['kind' => 'AREA', 'at' => [1.7, -1.1, 0.9], 'energy' => 190.0, 'size' => 1.4, 'color' => [0.95, 0.97, 1.0]],
                ['kind' => 'AREA', 'at' => [-0.4, 1.5, 1.1], 'energy' => 250.0, 'size' => 1.4, 'color' => [1.0, 1.0, 1.0]],
            ],
            // Warm key with coloured rims — closer to what an event actually looks like.
            self::Stage => [
                ['kind' => 'AREA', 'at' => [-1.1, -1.6, 1.6], 'energy' => 420.0, 'size' => 1.0, 'color' => [1.0, 0.86, 0.68]],
                ['kind' => 'AREA', 'at' => [1.8, 0.9, 1.2], 'energy' => 340.0, 'size' => 0.8, 'color' => [0.35, 0.55, 1.0]],
                ['kind' => 'AREA', 'at' => [-1.8, 1.0, 1.0], 'energy' => 300.0, 'size' => 0.8, 'color' => [1.0, 0.30, 0.45]],
            ],
            // Outdoors, which is where most SdWa5 events happen.
            self::Daylight => [
                ['kind' => 'SUN', 'at' => [-1.0, -1.2, 2.2], 'energy' => 3.2, 'size' => 0.0, 'color' => [1.0, 0.96, 0.9]],
            ],
            self::Flat => [
                ['kind' => 'AREA', 'at' => [-0.9, -1.4, 1.3], 'energy' => 380.0, 'size' => 2.5, 'color' => [1.0, 1.0, 1.0]],
                ['kind' => 'AREA', 'at' => [1.4, -1.0, 1.0], 'energy' => 300.0, 'size' => 2.5, 'color' => [1.0, 1.0, 1.0]],
                ['kind' => 'AREA', 'at' => [0.0, 1.6, 1.0], 'energy' => 300.0, 'size' => 2.5, 'color' => [1.0, 1.0, 1.0]],
                ['kind' => 'AREA', 'at' => [0.0, 0.0, 2.4], 'energy' => 260.0, 'size' => 2.5, 'color' => [1.0, 1.0, 1.0]],
            ],
        };
    }

    /**
     * Exposure in stops, applied by the view transform after rendering.
     *
     * **Studio was lit so brightly that every dark surface turned grey.** A ground of linear albedo 0.045 rendered
     * as #aeaeb1 and a #141414 cabinet as #646464, because AgX compresses an over-lit frame instead of clipping it.
     * Of renders at 0, -1 and -1.5 stops the owner chose the darker pair on 2026-10-01, and -1.25 lies between
     * them. The other presets were not measured and stay at 0.
     */
    public function exposure(): float
    {
        return match ($this) {
            self::Studio => -1.25,
            self::Stage, self::Daylight, self::Flat => 0.0,
        };
    }

    /**
     * World background colour, which is also the ambient fill.
     *
     * @return array{float, float, float}
     */
    public function background(): array
    {
        return match ($this) {
            self::Studio => [0.021, 0.025, 0.035],
            self::Stage => [0.010, 0.010, 0.016],
            self::Daylight => [0.240, 0.330, 0.480],
            self::Flat => [0.180, 0.180, 0.190],
        };
    }

    /**
     * Ground colour. Dark for indoor-ish presets, grass for daylight, mid grey for flat.
     *
     * @return array{float, float, float}
     */
    public function ground(): array
    {
        return match ($this) {
            self::Studio => [0.045, 0.045, 0.050],
            self::Stage => [0.020, 0.020, 0.024],
            self::Daylight => [0.075, 0.115, 0.045],
            self::Flat => [0.220, 0.220, 0.225],
        };
    }

    public function describe(): string
    {
        return match ($this) {
            self::Studio => 'neutral three-point — the default',
            self::Stage => 'warm key with coloured rims, event-like',
            self::Daylight => 'sun and sky, for outdoor setups',
            self::Flat => 'even and shadowless, for inspecting geometry',
        };
    }
}
