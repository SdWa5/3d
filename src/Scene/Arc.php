<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;

/**
 * A group of identical cabinets seated on a circular arc — a point-source cluster.
 *
 * The tightest arc a cabinet can form is a property of the cabinet, not a number anyone should have to
 * work out: a **tapered** top's taper *is* its designed splay angle, because that is the one angle at
 * which two of them sit side by side with their side faces fully in contact. So `arc: {mode: convex,
 * count: 3}` needs no other input, and stating `splay_deg` or `radius_m` opens the arc up from there.
 *
 * How contact is solved
 * ---------------------
 * Everything here rests on one observation. A cabinet's plan-view outline is symmetric about the line
 * from the centre of curvature through it, and every cabinet in the arc is the same outline rotated
 * about that centre. Two neighbours are therefore clear of each other exactly when the outline fits
 * inside a wedge of half-angle `splay/2` — no polygon intersection, no iteration, and it is exact for
 * whatever shape the outline turns out to be. A vertex at local (x, y) sits `radius − sign·y` from the
 * centre, so it fits when `|x| <= (radius − sign·y)·tan(splay/2)`, and the arc's radius is simply the
 * largest radius any one vertex demands.
 *
 * That generality is the point, because the outline is not the nominal trapezoid:
 *
 * * `appearance.grille.inset_m` builds a **full-width** frame across the very front, so the taper only
 *   runs over `depth − inset`. Ignoring it, a Tecnare's flush arc comes out at 16.95° when the built
 *   meshes actually need 17.35°, and they overlap by 3.5 mm.
 * * Down-tilt swings the front-top edge forward into a full-width prow. Ignoring *that*, a concave
 *   cluster at 4.4° of tilt interpenetrates by 20.6 mm — tilt is not a cosmetic correction here.
 *
 * Both are just more vertices in the outline, which is why they cost nothing to handle.
 *
 * Which edges end up touching follows from the geometry rather than being chosen:
 *
 * * **concave** — the front edges, at every angle. The backs always gap, widely (0.31 m on a Tecnare at
 *   its flush angle). There is no tightest concave arc, so a concave arc must state its size.
 * * **convex** at the flush angle — the whole side face, front and back at once.
 * * **convex**, wider — the back edges, with the fronts opening out.
 * * **convex**, tighter than flush — the front edges, with a V opening behind. Still perfectly buildable,
 *   so it is allowed; it just is not what "back edges touching" means.
 */
final class Arc
{
    /** A splay of 180° puts two cabinets back to back; beyond it the arc would turn inside out. */
    public const MAX_SPLAY_DEG = 180.0;

    public function __construct(
        public readonly ArcMode $mode,
        public readonly int $count,
        public readonly ?float $splayDeg = null,
        public readonly ?float $radiusM = null,
    ) {
    }

    /**
     * Structural reading only — whether the numbers make a buildable arc is `problems()`.
     */
    public static function fromReader(ArrayReader $reader): self
    {
        $unknown = $reader->unknownKeys(['mode', 'count', 'splay_deg', 'radius_m']);
        if ($unknown !== []) {
            throw new \App\Spec\InvalidSpecException(sprintf(
                "arc: unknown key '%s' (allowed: mode, count, splay_deg, radius_m)",
                $unknown[0],
            ));
        }

        return new self(
            mode: $reader->requireEnum('mode', ArcMode::class),
            count: $reader->requireInt('count'),
            splayDeg: $reader->optionalFloat('splay_deg'),
            radiusM: $reader->optionalFloat('radius_m'),
        );
    }

    /**
     * Everything wrong with this arc for this cabinet, as messages without the placement prefix.
     *
     * `$pitchDeg` is the down-tilt the cabinets will end up with, because the contact solve depends on
     * it. Rules that need an angle are skipped for a single-cabinet arc, which never uses one.
     *
     * @return list<string>
     */
    public function problems(DeviceSpec $device, float $pitchDeg): array
    {
        $messages = [];

        if ($this->count < 1) {
            $messages[] = "arc.count must be at least 1, got {$this->count}";
        }
        if ($this->splayDeg !== null && $this->radiusM !== null) {
            $messages[] = 'use either `arc.splay_deg` or `arc.radius_m`, not both';
        }
        if ($this->splayDeg !== null && ($this->splayDeg <= 0.0 || $this->splayDeg >= self::MAX_SPLAY_DEG)) {
            $messages[] = sprintf(
                'arc.splay_deg must be between 0 and %s, got %s',
                self::MAX_SPLAY_DEG,
                $this->splayDeg,
            );
        }
        if ($this->radiusM !== null && $this->radiusM <= 0.0) {
            $messages[] = "arc.radius_m must be greater than 0, got {$this->radiusM}";
        }

        // Anything below needs a usable angle, and a broken one would only produce noise on top of the
        // real message. A single cabinet never resolves an angle at all.
        if ($messages !== [] || $this->count < 2) {
            return $messages;
        }

        if ($this->splayDeg === null && $this->radiusM === null) {
            $problem = $this->missingSizeProblem($device, $pitchDeg);
            if ($problem !== null) {
                return [$problem];
            }
        }

        if ($this->radiusM !== null && $this->armsFrom($device, $pitchDeg, $this->centreRadiusM($device, $pitchDeg)) === null) {
            $messages[] = sprintf(
                'arc.radius_m (%.2f) leaves the centre of curvature inside the cabinet — a %s arc of %s needs more than %.2f m',
                $this->radiusM,
                $this->mode->value,
                $device->id,
                $this->minimumRadiusM($device, $pitchDeg),
            );

            return $messages;
        }

        $splay = $this->splayDegFor($device, $pitchDeg);
        if ($this->count * $splay > 360.0 + 1e-9) {
            $messages[] = sprintf(
                '%d cabinets at %.2f° wrap past a full circle — arc.splay_deg must not exceed %.2f°',
                $this->count,
                $splay,
                360.0 / $this->count,
            );
        }

        return $messages;
    }

    /**
     * The splay this arc resolves to: stated outright, implied by a stated radius, or the cabinet's own
     * taper. Callers must have checked `problems()` first.
     */
    public function splayDegFor(DeviceSpec $device, float $pitchDeg): float
    {
        if ($this->count < 2) {
            return 0.0;
        }
        if ($this->splayDeg !== null) {
            return $this->splayDeg;
        }
        if ($this->radiusM !== null) {
            return $this->splayForRadius($device, $pitchDeg, $this->centreRadiusFromFront($device, $this->radiusM));
        }

        return $this->flushSplayDeg($device, $pitchDeg) ?? 0.0;
    }

    /**
     * Centre of curvature to the middle of each cabinet's footprint — the radius the seats are laid out
     * on. When the arc's size comes from a splay angle this is the tightest radius that keeps the
     * cabinets clear of each other, which is what makes the touching edges touch.
     */
    public function centreRadiusM(DeviceSpec $device, float $pitchDeg): float
    {
        if ($this->count < 2) {
            return 0.0;
        }
        if ($this->radiusM !== null) {
            return $this->centreRadiusFromFront($device, $this->radiusM);
        }

        return $this->radiusForSplay($device, $pitchDeg, $this->splayDegFor($device, $pitchDeg));
    }

    /**
     * Centre of curvature to the front face — the radiating surface, and the radius `radius_m` states.
     *
     * The same physical thing in both modes: the arc the fronts sit on, bulging away from the centre in
     * convex and towards it in concave. Anything else would change meaning with `mode`.
     */
    public function frontRadiusM(DeviceSpec $device, float $pitchDeg): float
    {
        return $this->centreRadiusM($device, $pitchDeg)
            + $this->mode->sign() * $device->dimensions->depth / 2;
    }

    /**
     * Where each cabinet sits, relative to the placement's `at`, and how far it is turned.
     *
     * `at` is the arc point at the middle of the fan: the middle cabinet for an odd count, and an empty
     * spot between the two middle ones for an even count. One formula either way, and it means a single
     * top can be swapped for a group without the middle one moving.
     *
     * @return list<PlacementCopy>
     */
    public function seats(DeviceSpec $device, float $pitchDeg): array
    {
        $anchor = intdiv($this->count - 1, 2);
        if ($this->count < 2) {
            return [new PlacementCopy(0, [0.0, 0.0, 0.0], 0.0, true)];
        }

        $sign = $this->mode->sign();
        $radius = $this->centreRadiusM($device, $pitchDeg);
        $splay = deg2rad($this->splayDegFor($device, $pitchDeg));

        $seats = [];
        for ($index = 0; $index < $this->count; ++$index) {
            $phi = ($index - ($this->count - 1) / 2) * $splay;
            $seats[] = new PlacementCopy(
                $index,
                [$radius * sin($phi), $sign * $radius * (1 - cos($phi)), 0.0],
                $sign * rad2deg($phi),
                $index === $anchor,
            );
        }

        return $seats;
    }

    /**
     * The angle at which a convex arc's side faces are fully in contact, front and back at once.
     *
     * Null when there is no such angle: concave arcs touch at the front whatever the angle, and an
     * untapered cabinet has nothing to derive one from.
     */
    public function flushSplayDeg(DeviceSpec $device, float $pitchDeg): ?float
    {
        if ($this->mode !== ArcMode::Convex) {
            return null;
        }

        $taper = ($device->dimensions->width - ($device->backWidth ?? $device->dimensions->width)) / 2;
        if ($taper <= 0.0) {
            return null;
        }

        // Both ends bind at once when the difference in half-width is exactly taken up by the depth the
        // taper runs over — which is the depth behind the grille frame, foreshortened by the tilt.
        $run = ($device->dimensions->depth - ($device->grilleInset ?? 0.0)) * cos(deg2rad($pitchDeg));
        if ($run <= 0.0) {
            return null;
        }

        return rad2deg(2 * atan($taper / $run));
    }

    /**
     * The smallest radius this cabinet can be arced at all, whatever the angle: any less and the centre
     * of curvature falls inside the cabinet.
     */
    public function minimumRadiusM(DeviceSpec $device, float $pitchDeg): float
    {
        $sign = $this->mode->sign();
        $limit = 0.0;
        foreach ($this->outline($device, $pitchDeg) as [, $y]) {
            $limit = max($limit, $sign * $y);
        }

        return $limit + $sign * $device->dimensions->depth / 2;
    }

    /**
     * Tightest centre radius that keeps neighbours clear at this splay: the largest radius any one
     * outline vertex demands to stay inside its half of the wedge.
     */
    private function radiusForSplay(DeviceSpec $device, float $pitchDeg, float $splayDeg): float
    {
        $tangent = tan(deg2rad($splayDeg) / 2);
        $sign = $this->mode->sign();

        $radius = -INF;
        foreach ($this->outline($device, $pitchDeg) as [$x, $y]) {
            $radius = max($radius, $x / $tangent + $sign * $y);
        }

        return $radius;
    }

    /**
     * The inverse: the tightest splay a stated centre radius allows.
     */
    private function splayForRadius(DeviceSpec $device, float $pitchDeg, float $centreRadius): float
    {
        $arms = $this->armsFrom($device, $pitchDeg, $centreRadius);
        if ($arms === null) {
            return 0.0;
        }

        $tangent = 0.0;
        foreach ($arms as [$x, $arm]) {
            $tangent = max($tangent, $x / $arm);
        }

        return rad2deg(2 * atan($tangent));
    }

    /**
     * Each outline vertex's half-width and its distance from the centre of curvature, or null when any
     * vertex is at or past the centre — at which point there is no arc to speak of.
     *
     * @return list<array{float, float}>|null
     */
    private function armsFrom(DeviceSpec $device, float $pitchDeg, float $centreRadius): ?array
    {
        $sign = $this->mode->sign();
        $arms = [];
        foreach ($this->outline($device, $pitchDeg) as [$x, $y]) {
            $arm = $centreRadius - $sign * $y;
            if ($arm <= 0.0) {
                return null;
            }
            $arms[] = [$x, $arm];
        }

        return $arms;
    }

    private function centreRadiusFromFront(DeviceSpec $device, float $frontRadius): float
    {
        return $frontRadius - $this->mode->sign() * $device->dimensions->depth / 2;
    }

    /**
     * The cabinet's plan-view outline as (half-width, depth) pairs, tilted by `$pitchDeg`.
     *
     * Three widths matter: the full-width grille frame at the very front, the front of the tapered shell
     * behind it, and the back. Each is taken at both the floor and the top of the cabinet, because
     * tilting moves the two in opposite directions and either can be the one that binds.
     *
     * @return list<array{float, float}>
     */
    private function outline(DeviceSpec $device, float $pitchDeg): array
    {
        $dimensions = $device->dimensions;
        $halfFront = $dimensions->width / 2;
        $halfBack = ($device->backWidth ?? $dimensions->width) / 2;
        $inset = $device->grilleInset ?? 0.0;
        $frontTop = $device->frontHeight ?? $dimensions->height;

        $corners = [
            [$halfFront, -$dimensions->depth / 2, $frontTop],
            [$halfFront, -$dimensions->depth / 2 + $inset, $frontTop],
            [$halfBack, $dimensions->depth / 2, $dimensions->height],
        ];

        $pitch = deg2rad($pitchDeg);
        $outline = [];
        foreach ($corners as [$x, $y, $top]) {
            foreach ([0.0, $top] as $z) {
                $outline[] = [$x, $y * cos($pitch) - $z * sin($pitch)];
            }
        }

        return $outline;
    }

    /**
     * Why this arc cannot work out its own size, or null when it can.
     */
    private function missingSizeProblem(DeviceSpec $device, float $pitchDeg): ?string
    {
        if ($this->flushSplayDeg($device, $pitchDeg) !== null) {
            return null;
        }

        $hint = $device->coverage === null ? '' : sprintf(
            ' (try %.2f°, the cabinet\'s own horizontal coverage)',
            $device->coverage->horizontal,
        );

        if ($this->mode === ArcMode::Concave) {
            return 'a concave arc has no tightest angle — its front edges touch at any angle, '
                .'so it needs an explicit arc.splay_deg or arc.radius_m'.$hint;
        }

        return sprintf(
            '%s has no taper to derive a splay from (%s), so a convex arc needs an explicit '
            .'arc.splay_deg or arc.radius_m%s',
            $device->id,
            $device->backWidth === null
                ? 'it is a plain box'
                : sprintf(
                    'back_width_m %s is not narrower than width %s',
                    $device->backWidth,
                    $device->dimensions->width,
                ),
            $hint,
        );
    }
}
