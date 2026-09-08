<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;

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
 * Every cabinet in the arc is the same {@see Outline} rotated about one shared centre of curvature by a
 * multiple of the splay. Two neighbours are therefore clear of each other exactly when the outline's
 * **angular width** about that centre is no more than the splay — no polygon intersection, no iteration,
 * and exact for whatever shape the outline turns out to be. A vertex at (x, y) sits `arm = radius −
 * sign·y` from the centre and `atan2(x, arm)` off the radial line, so the arc's radius is the smallest
 * one at which the widest and narrowest of those bearings are the splay apart.
 *
 * Measuring the width both ways round rather than assuming the outline is symmetric is what lets a
 * cabinet be rolled onto its side: at `roll_deg: 90` a Tecnare's plan outline is a 0.960 × 0.520
 * rectangle sitting entirely to one side of its own origin, and there is no half-wedge to fit it into.
 *
 * Which edges end up touching follows from the geometry rather than being chosen:
 *
 * * **concave** — the front edges, at every angle. The backs always gap, widely (0.31 m on a Tecnare at
 *   its flush angle). There is no tightest concave arc, so a concave arc must state its size.
 * * **convex** at the flush angle — the whole side face, front and back at once.
 * * **convex**, wider — the back edges, with the fronts opening out.
 * * **convex**, tighter than flush — the front edges, with a V opening behind. Still perfectly buildable,
 *   so it is allowed; it just is not what "back edges touching" means.
 * * **splay 0** — a straight row, the arc of infinite radius. What touches is the flanks, and the
 *   spacing is the outline's own width. See {@see isStraight}.
 */
final class Arc implements Group
{
    /** A splay of 180° puts two cabinets back to back; beyond it the arc would turn inside out. */
    public const MAX_SPLAY_DEG = 180.0;

    /** Below this an edge counts as running straight along the radial line, or two angles as equal. */
    private const EPSILON = 1e-9;

    public function __construct(
        public readonly ArcMode $mode,
        public readonly int $count,
        public readonly ?float $splayDeg = null,
        public readonly ?float $radiusM = null,
        public readonly ?float $gapM = null,
    ) {
    }

    /**
     * Structural reading only — whether the numbers make a buildable arc is `problems()`.
     */
    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['mode', 'count', 'splay_deg', 'radius_m', 'gap_m'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("arc: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        return new self(
            mode: $reader->requireEnum('mode', ArcMode::class),
            count: $reader->requireInt('count'),
            splayDeg: $reader->optionalFloat('splay_deg'),
            radiusM: $reader->optionalFloat('radius_m'),
            gapM: $reader->optionalFloat('gap_m'),
        );
    }

    /**
     * Centre of curvature to the front face — the radiating surface, and the radius `radius_m` states.
     *
     * The same physical thing in both modes: the arc the fronts sit on, bulging away from the centre in
     * convex and towards it in concave. Anything else would change meaning with `mode`.
     */
    public function frontRadiusM(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): float
    {
        return $this->centreRadiusM($device, $pitchDeg, $rollDeg)
            + $this->mode->sign() * $device->dimensions->depth / 2;
    }

    /**
     * Centre of curvature to the middle of each cabinet's footprint — the radius the seats are laid out
     * on. When the arc's size comes from a splay angle this is the tightest radius that keeps the
     * cabinets clear of each other, which is what makes the touching edges touch.
     *
     * `INF` for a straight row, which is not a guard but the answer: a row's centre of curvature really
     * is at infinity, and {@see seats} lays a row out from its spacing instead.
     */
    public function centreRadiusM(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): float
    {
        if ($this->count < 2) {
            return 0.0;
        }
        if (null !== $this->radiusM) {
            return $this->centreRadiusFromFront($device, $this->radiusM);
        }

        $splay = $this->splayDegFor($device, $pitchDeg, $rollDeg);
        if (0.0 === $splay) {
            return INF;
        }

        return $this->radiusForSplay($this->outline($device, $pitchDeg, $rollDeg), $splay);
    }

    private function centreRadiusFromFront(DeviceSpec $device, float $frontRadius): float
    {
        return $frontRadius - $this->mode->sign() * $device->dimensions->depth / 2;
    }

    /**
     * Tightest centre radius that keeps neighbours clear at this splay.
     *
     * Solved pairwise and in closed form. Requiring two vertices' bearings to be exactly the splay apart
     * is one quadratic in the radius, so the answer is the largest radius any pair demands — and because
     * only `sin` and `cos` of the splay appear, nothing blows up at a right angle. A splay of zero is the
     * one case with no finite answer, and {@see centreRadiusM} has already turned it into a row.
     */
    private function radiusForSplay(Outline $outline, float $splayDeg): float
    {
        $splay = deg2rad($splayDeg);
        $sin = sin($splay);
        $cos = cos($splay);
        $sign = $this->mode->sign();

        $radius = -INF;
        foreach ($outline->points as [$xi, $yi]) {
            foreach ($outline->points as [$xj, $yj]) {
                $b = -$cos * ($xi - $xj) - $sign * $sin * ($yi + $yj);
                $c = $sin * ($xi * $xj + $yi * $yj) + $sign * $cos * ($xi * $yj - $yi * $xj);

                $discriminant = $b ** 2 - 4 * $sin * $c;
                if ($discriminant < 0.0) {
                    continue;
                }

                $root = sqrt($discriminant);
                foreach ([(-$b + $root) / (2 * $sin), (-$b - $root) / (2 * $sin)] as $candidate) {
                    if ($this->spreadIs($xi, $yi, $xj, $yj, $candidate, $splay)) {
                        $radius = max($radius, $candidate);
                    }
                }
            }
        }

        return $radius;
    }

    /**
     * Whether two vertices really are `$splay` apart about a centre at this radius.
     *
     * A quadratic cannot tell "these two are the splay apart" from "they are the splay apart the other
     * way round", and a root of the wrong branch would quietly inflate the arc. Checking the angle it
     * came from is one `atan2` per root and removes the whole question.
     */
    private function spreadIs(float $xi, float $yi, float $xj, float $yj, float $radius, float $splay): bool
    {
        if (!is_finite($radius)) {
            return false;
        }

        $sign = $this->mode->sign();
        $armI = $radius - $sign * $yi;
        $armJ = $radius - $sign * $yj;
        if ($armI <= 0.0 || $armJ <= 0.0) {
            return false;
        }

        return abs(atan2($xi, $armI) - atan2($xj, $armJ) - $splay) < self::EPSILON;
    }

    /**
     * The cabinet's plan-view outline, tilted, rolled, and grown by the working gap.
     *
     * Putting `gap_m` into the outline rather than into a spacing afterwards is what makes it mean the
     * same thing at every angle: the seam simply opens by the gap measured across it.
     */
    private function outline(DeviceSpec $device, float $pitchDeg, float $rollDeg): Outline
    {
        return Outline::plan($device, $pitchDeg, $rollDeg)->dilatedX($this->gapM ?? 0.0);
    }

    /**
     * The splay this arc resolves to: stated outright, implied by a stated radius, or the cabinet's own
     * taper. Callers must have checked `problems()` first.
     */
    public function splayDegFor(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): float
    {
        if ($this->count < 2) {
            return 0.0;
        }
        if (null !== $this->splayDeg) {
            return $this->splayDeg;
        }
        if (null !== $this->radiusM) {
            return $this->splayForRadius(
                $this->outline($device, $pitchDeg, $rollDeg),
                $this->centreRadiusFromFront($device, $this->radiusM),
            );
        }

        return $this->flushSplayDeg($device, $pitchDeg, $rollDeg) ?? 0.0;
    }

    /**
     * The inverse: the tightest splay a stated centre radius allows, which is the outline's angular
     * width about that centre.
     */
    private function splayForRadius(Outline $outline, float $centreRadius): float
    {
        $sign = $this->mode->sign();

        $lowest = INF;
        $highest = -INF;
        foreach ($outline->points as [$x, $y]) {
            $arm = $centreRadius - $sign * $y;
            if ($arm <= 0.0) {
                return 0.0;
            }
            $bearing = atan2($x, $arm);
            $lowest = min($lowest, $bearing);
            $highest = max($highest, $bearing);
        }

        return rad2deg($highest - $lowest);
    }

    /**
     * Each outline vertex's half-width and its distance from the centre of curvature, or null when any
     * vertex is at or past the centre — at which point there is no arc to speak of.
     *
     * @return list<array{float, float}>|null
     */
    private function armsFrom(DeviceSpec $device, float $pitchDeg, float $rollDeg, float $centreRadius): ?array
    {
        $sign = $this->mode->sign();
        $arms = [];
        foreach ($this->outline($device, $pitchDeg, $rollDeg)->points as [$x, $y]) {
            $arm = $centreRadius - $sign * $y;
            if ($arm <= 0.0) {
                return null;
            }
            $arms[] = [$x, $arm];
        }

        return $arms;
    }

    /**
     * The angle at which a convex arc's side faces are fully in contact, front and back at once.
     *
     * Full-face contact means the shared face lies in a plane through the centre of curvature, so the
     * question is which of the outline's flanks, extended, passes through that centre — and the splay is
     * then twice that flank's angle off the radial line. Reading it off the outline rather than off
     * `width − back_width` is what makes it survive a roll: it gives the same 16.95° for an upright
     * Tecnare and for one turned over, and **0°** for one on its side, where the taper has rotated into
     * the vertical and the tightest convex arrangement is a straight row.
     *
     * Null when there is no such angle: concave arcs touch at the front whatever the angle, and an
     * outline whose two flanks point at different centres — which is any cabinet rolled off a quarter
     * turn — has no arrangement that closes both seams at once.
     */
    public function flushSplayDeg(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): ?float
    {
        if (ArcMode::Convex !== $this->mode) {
            return null;
        }

        $outline = $this->outline($device, $pitchDeg, $rollDeg);
        $right = self::radialFlanks($outline, 1.0);
        $left = self::radialFlanks($outline, -1.0);

        // Both flanks run along the radial line at every radius — a plain box, or any cabinet on its
        // side. They are already in full contact standing side by side, which is the straight row.
        if ([] === $right && [] === $left) {
            return 0.0;
        }

        $best = null;
        foreach ($right as [$splay, $centre]) {
            foreach ($left as [$otherSplay, $otherCentre]) {
                if (abs($splay - $otherSplay) > self::EPSILON || abs($centre - $otherCentre) > self::EPSILON) {
                    continue;
                }
                // The flank has to be the constraint that actually binds, not merely a flank that could
                // be made radial while some other corner already sticks out past it.
                if (abs($this->radiusForSplay($outline, $splay) - $this->mode->sign() * $centre) > self::EPSILON) {
                    continue;
                }
                $best = max($best ?? -INF, $splay);
            }
        }

        return $best;
    }

    /**
     * Each flank's splay angle and the point its line crosses the cabinet's own centre-line, for the
     * flanks that are not parallel to that line — a parallel flank meets it only at infinity, which is
     * the straight row rather than an arc.
     *
     * @return list<array{float, float}>
     */
    private static function radialFlanks(Outline $outline, float $sign): array
    {
        $flanks = [];
        foreach ($outline->facingEdges($sign) as [$p, $q]) {
            $dx = $q[0] - $p[0];
            $dy = $q[1] - $p[1];
            if (abs($dx) < self::EPSILON) {
                continue;
            }

            $flanks[] = [
                rad2deg(2 * atan2(abs($dx), abs($dy))),
                $p[1] + $dy * (0.0 - $p[0]) / $dx,
            ];
        }

        return $flanks;
    }

    /**
     * Whether this arc resolves to a straight row: `splay_deg: 0`, or a cabinet whose tightest convex
     * arrangement has no bend in it at all.
     *
     * Worth a name rather than an `is_infinite()` check at each use, because a row is a case and not a
     * limit — it is how a row of subs gets spaced and centred from the cabinets themselves instead of
     * from a step vector somebody worked out by hand.
     */
    public function isStraight(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): bool
    {
        return $this->count > 1
            && null === $this->radiusM
            && 0.0 === $this->splayDegFor($device, $pitchDeg, $rollDeg);
    }

    /**
     * Everything wrong with this arc for this cabinet, as messages without the placement prefix.
     *
     * `$pitchDeg` and `$rollDeg` are the attitude the cabinets will end up at, because the contact solve
     * depends on both. Rules that need an angle are skipped for a single-cabinet arc, which never uses
     * one.
     *
     * @return list<string>
     */
    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0, array $cellBox = []): array
    {
        $messages = [];

        if ($this->count < 1) {
            $messages[] = "arc.count must be at least 1, got {$this->count}";
        }
        if (null !== $this->splayDeg && null !== $this->radiusM) {
            $messages[] = 'use either `arc.splay_deg` or `arc.radius_m`, not both';
        }
        if (null !== $this->splayDeg && ($this->splayDeg < 0.0 || $this->splayDeg >= self::MAX_SPLAY_DEG)) {
            $messages[] = sprintf(
                'arc.splay_deg must be at least 0 and less than %s, got %s',
                self::MAX_SPLAY_DEG,
                $this->splayDeg,
            );
        }
        if (null !== $this->radiusM && $this->radiusM <= 0.0) {
            $messages[] = "arc.radius_m must be greater than 0, got {$this->radiusM}";
        }
        if (null !== $this->gapM && $this->gapM < 0.0) {
            $messages[] = "arc.gap_m must not be negative, got {$this->gapM}";
        }
        if (0.0 !== fmod(abs($rollDeg), 90.0)) {
            // A quarter turn keeps the plan outline's flanks square to each other, which is what makes the
            // contact solve *tight* rather than merely safe. Off a quarter turn the outline is lopsided,
            // the solve is only conservative, and an arc that quietly leaves 14 mm of air down every seam
            // is worse than one that refuses.
            $messages[] = sprintf(
                'an arc needs the cabinet on a quarter turn — roll_deg %s leaves its plan outline lopsided, '
                .'and the seam could only be solved loosely',
                $rollDeg,
            );
        }

        // Anything below needs a usable angle, and a broken one would only produce noise on top of the
        // real message. A single cabinet never resolves an angle at all.
        if ([] !== $messages || $this->count < 2) {
            return $messages;
        }

        if (null === $this->splayDeg && null === $this->radiusM) {
            $problem = $this->missingSizeProblem($device, $pitchDeg, $rollDeg);
            if (null !== $problem) {
                return [$problem];
            }
        }

        if (null !== $this->radiusM && null === $this->armsFrom(
            $device,
            $pitchDeg,
            $rollDeg,
            $this->centreRadiusM($device, $pitchDeg, $rollDeg),
        )) {
            $messages[] = sprintf(
                'arc.radius_m (%.2f) leaves the centre of curvature inside the cabinet — a %s arc of %s needs more than %.2f m',
                $this->radiusM,
                $this->mode->value,
                $device->id,
                $this->minimumRadiusM($device, $pitchDeg, $rollDeg),
            );

            return $messages;
        }

        $splay = $this->splayDegFor($device, $pitchDeg, $rollDeg);
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
     * Why this arc cannot work out its own size, or null when it can.
     */
    private function missingSizeProblem(DeviceSpec $device, float $pitchDeg, float $rollDeg): ?string
    {
        if (null !== $this->flushSplayDeg($device, $pitchDeg, $rollDeg)) {
            return null;
        }

        $hint = null === $device->coverage ? '' : sprintf(
            ' (try %.2f°, the cabinet\'s own horizontal coverage)',
            $device->coverage->horizontal,
        );

        if (ArcMode::Concave === $this->mode) {
            return 'a concave arc has no tightest angle — its front edges touch at any angle, '
                .'so it needs an explicit arc.splay_deg or arc.radius_m'.$hint;
        }

        return sprintf(
            '%s rolled %s puts its two flanks on different centres, so there is no angle that closes '
            .'both seams — a convex arc of it needs an explicit arc.splay_deg or arc.radius_m%s',
            $device->id,
            $rollDeg,
            $hint,
        );
    }

    /**
     * The smallest radius this cabinet can be arced at all, whatever the angle: any less and the centre
     * of curvature falls inside the cabinet.
     */
    public function minimumRadiusM(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): float
    {
        $sign = $this->mode->sign();
        $limit = 0.0;
        foreach ($this->outline($device, $pitchDeg, $rollDeg)->points as [, $y]) {
            $limit = max($limit, $sign * $y);
        }

        return $limit + $sign * $device->dimensions->depth / 2;
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
    public function seats(DeviceSpec $device, float $pitchDeg, float $rollDeg = 0.0): array
    {
        $anchor = intdiv($this->count - 1, 2);
        if ($this->count < 2) {
            return [new PlacementCopy([], [0.0, 0.0, 0.0], new Orientation(), true)];
        }

        if ($this->isStraight($device, $pitchDeg, $rollDeg)) {
            return $this->row($this->outline($device, $pitchDeg, $rollDeg)->widthX(), $anchor);
        }

        $sign = $this->mode->sign();
        $radius = $this->centreRadiusM($device, $pitchDeg, $rollDeg);
        $splay = deg2rad($this->splayDegFor($device, $pitchDeg, $rollDeg));

        $seats = [];
        for ($index = 0; $index < $this->count; ++$index) {
            $phi = ($index - ($this->count - 1) / 2) * $splay;
            $seats[] = new PlacementCopy(
                [$index + 1],
                [$radius * sin($phi), $sign * $radius * (1 - cos($phi)), 0.0],
                new Orientation(0.0, 0.0, $sign * rad2deg($phi)),
                $index === $anchor,
            );
        }

        return $seats;
    }

    /**
     * {@see Group}. An arc's spacing comes from the cabinet's own outline, so the cell box is not used —
     * nesting an arc inside something else is what the outer group measures, not the other way round.
     */
    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        return $this->seats($device, $pitchDeg, $rollDeg);
    }

    public function copyCount(): int
    {
        return $this->count;
    }

    public function kind(): string
    {
        return 'arc';
    }

    /** The fan's geometry *is* the yaw, which is why stating one as well is a contradiction. */
    /** An arc owns the yaw; the tilt is still each cabinet's own business. */
    public function decidesPitch(): bool
    {
        return false;
    }

    public function decidesYaw(): bool
    {
        return true;
    }

    /**
     * The degenerate arc: no turn, no bow, and neighbours spaced by exactly the room their outlines take
     * up side by side. Centred the same way a fan is, so a row is a drop-in for a single cabinet.
     *
     * @return list<PlacementCopy>
     */
    private function row(float $spacing, int $anchor): array
    {
        $seats = [];
        for ($index = 0; $index < $this->count; ++$index) {
            $seats[] = new PlacementCopy(
                [$index + 1],
                [($index - ($this->count - 1) / 2) * $spacing, 0.0, 0.0],
                new Orientation(),
                $index === $anchor,
            );
        }

        return $seats;
    }
}
