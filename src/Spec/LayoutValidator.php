<?php

declare(strict_types=1);

namespace App\Spec;

/** Semantic checks for baffle features, openings and their joins. */
final class LayoutValidator
{
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
    public function validate(DeviceSpec $spec): array
    {
        $layout = $spec->layout;
        if (null === $layout) {
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

            if ($feature->isHorn() && null === $feature->throatIn) {
                $messages[] = "{$label}: a horn needs throat_in";
            }
            if ($feature->isCone() && null === $feature->diameterIn) {
                $messages[] = "{$label}: a cone needs diameter_in";
            }
            foreach ($this->validateGrilleOrPlug($feature, $label, $dimensions->depth) as $message) {
                $messages[] = $message;
            }
            foreach ($this->validateGrilleShape($feature, $label) as $message) {
                $messages[] = $message;
            }

            foreach ($this->validateHornShape($feature, $label) as $message) {
                $messages[] = $message;
            }
            foreach ($this->validateCellOrFin($feature, $label, $dimensions->depth) as $message) {
                $messages[] = $message;
            }
            foreach ($this->validateFeatureColor($feature, $label) as $message) {
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
            if (null === $opening) {
                $messages[] = "{$label}: needs mouth_m, or diameter_in on a cone, a plug or a round grille";
            } else {
                foreach (['width' => $opening[0], 'height' => $opening[1]] as $axis => $value) {
                    if ($value <= 0) {
                        $messages[] = "{$label}: mouth {$axis} must be greater than 0, got {$value}";
                    }
                }

                $throat = $feature->throatM();
                if (null !== $throat && $throat >= min($opening[0], $opening[1])) {
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
            foreach ($this->validateFeatureJoin($feature, $layout, $seen, $label, null !== $spec->meshOverride, $opening) as $message) {
                $messages[] = $message;
            }

            $seen[$feature->id] = true;
        }

        return $messages;
    }

    /**
     * Where a feature sits: on the baffle at `at_m`, or nested in an earlier one via `inside`.
     *
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
            if (null !== $feature->sides) {
                $messages[] = "{$label}: sides only applies to a horn";
            }
            if (null !== $feature->throatProfile) {
                $messages[] = "{$label}: throat_profile only applies to a horn";
            }
            if (null !== $feature->throatBlendM) {
                $messages[] = "{$label}: throat_blend_m only applies to a horn";
            }

            return $messages;
        }

        if (null !== $feature->throatBlendM
            && ($feature->throatBlendM <= 0.0 || $feature->throatBlendM > $feature->depthM)) {
            $messages[] = sprintf(
                '%s: throat_blend_m must be above 0 and at most depth_m %.3f, got %.3f',
                $label,
                $feature->depthM,
                $feature->throatBlendM,
            );
        }

        foreach (['profile' => $feature->profile, 'throat_profile' => $feature->throatProfile] as $field => $value) {
            if (null !== $value && !in_array($value, BaffleFeature::PROFILES, true)) {
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
        if (null !== $feature->sides) {
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

    /**
     * The fields a cell or a fin may and may not carry, and what makes a fin a plate.
     *
     * Both are placed with `at_m` and have no throat or driver, so every horn and cone field on them is a
     * spec that means something the builder would not draw. A fin turned or set back past the cabinet's
     * back would leave the bounding box, and a "fin" with no thin edge is a block nobody meant.
     *
     * @return list<string>
     */
    private function validateCellOrFin(BaffleFeature $feature, string $label, float $cabinetDepth): array
    {
        $messages = [];

        $turns = $feature->isFin() || $feature->isCell();
        if (!$turns) {
            if (null !== $feature->angleDeg) {
                $messages[] = "{$label}: angle_deg only applies to a fin or a cell";
            }
            if (null !== $feature->turn) {
                $messages[] = "{$label}: turn only applies to a fin or a cell";
            }
        }
        if (null !== $feature->setbackM && !$feature->takesSetback()) {
            $messages[] = "{$label}: setback_m only applies to a fin, a grille on the baffle, or a horn inside another";
        }
        if (null !== $feature->setbackM && $feature->setbackM < 0 && !$feature->isFin()) {
            $messages[] = "{$label}: setback_m must not be negative, got {$feature->setbackM}";
        }
        if (!$feature->isFin()) {
            if ($feature->mitre) {
                $messages[] = "{$label}: mitre only applies to a fin";
            }
        }
        if ($feature->isFin() && $feature->mitre && !$feature->mitred()) {
            $messages[] = "{$label}: mitre needs a turned plate, so angle_deg between -90 and 90 and not 0";
        }
        if ($turns && null !== $feature->turn && !in_array($feature->turn, BaffleFeature::TURNS, true)) {
            $messages[] = sprintf("%s: turn '%s' must be one of %s", $label, $feature->turn, implode(', ', BaffleFeature::TURNS));
        }
        if (!$feature->isCell() && !$feature->isFin()) {
            return $messages;
        }

        $fields = ['throat_in' => $feature->throatIn, 'diameter_in' => $feature->diameterIn, 'driver_in' => $feature->driverIn, 'inside' => $feature->inside];
        foreach ($fields as $field => $value) {
            if (null !== $value) {
                $messages[] = "{$label}: {$field} has no meaning on a {$feature->kind}";
            }
        }
        if (null === $feature->mouth) {
            $messages[] = "{$label}: a {$feature->kind} needs mouth_m";
        }
        if ($feature->isCell()) {
            if (BaffleFeature::LINEAR !== $feature->flare) {
                if (!in_array($feature->flare, BaffleFeature::FLARES, true)) {
                    $messages[] = sprintf("%s: unknown flare '%s' (allowed: %s)", $label, $feature->flare, implode(', ', BaffleFeature::FLARES));
                } elseif (0.0 === ($feature->angleDeg ?? 0.0)) {
                    $messages[] = "{$label}: flare curves a tilted back wall, so the cell needs angle_deg";
                }
            }

            return [...$messages, ...$this->validateCellTilt($feature, $label, $cabinetDepth)];
        }
        if (null === $feature->mouth) {
            return $messages;
        }

        $thinnest = min($feature->mouth[0], $feature->mouth[1], $feature->depthM);
        if ($thinnest > BaffleFeature::FIN_MAX_THICKNESS_M) {
            $messages[] = sprintf(
                '%s: a fin is a plate, so one of its edges must be at most %s m, and the thinnest is %s m',
                $label,
                BaffleFeature::FIN_MAX_THICKNESS_M,
                $thinnest,
            );
        }
        if (null !== $feature->angleDeg && abs($feature->angleDeg) >= 90) {
            $messages[] = "{$label}: angle_deg must be between -90 and 90, got {$feature->angleDeg}";
        }
        if (null !== $feature->setbackM && $feature->setbackM < 0) {
            $messages[] = "{$label}: setback_m must not be negative, got {$feature->setbackM}";
        }

        $footprint = $feature->finFootprint();
        if (null !== $footprint && $footprint['reach'] > $cabinetDepth + 1e-9) {
            $messages[] = sprintf(
                '%s: turned and set back it reaches %s m behind the baffle, past the cabinet (%s)',
                $label,
                round($footprint['reach'], 4),
                $cabinetDepth,
            );
        }

        return $messages;
    }

    /**
     * What makes a grille a sheet and a plug a dome, and the fields neither of them can carry.
     *
     * A grille is either rectangular or round, never both, and thin enough to be mesh. A plug only exists in
     * front of a horn's throat, so it needs a host, and it is round by construction. Neither has a throat or
     * a driver of its own, so those fields would describe something the builder does not draw.
     *
     * @return list<string>
     */
    private function validateGrilleOrPlug(BaffleFeature $feature, string $label, float $cabinetDepth): array
    {
        if (!$feature->isGrille() && !$feature->isPlug()) {
            return [];
        }

        $messages = [];
        foreach (['throat_in' => $feature->throatIn, 'driver_in' => $feature->driverIn, 'join' => $feature->join] as $field => $value) {
            if (null !== $value) {
                $messages[] = "{$label}: {$field} has no meaning on a {$feature->kind}";
            }
        }

        if ($feature->isPlug()) {
            if (null === $feature->diameterIn) {
                $messages[] = "{$label}: a plug needs diameter_in";
            }
            if (null !== $feature->mouth) {
                $messages[] = "{$label}: a plug is round, so it takes diameter_in and no mouth_m";
            }
            if (null === $feature->inside) {
                $messages[] = "{$label}: a plug stands in front of a horn's throat, so it needs `inside`";
            }

            return $messages;
        }

        if ((null === $feature->mouth) === (null === $feature->diameterIn)) {
            $messages[] = "{$label}: a grille needs either mouth_m for a rectangle or diameter_in for a circle";
        }
        if ($feature->depthM > BaffleFeature::GRILLE_MAX_THICKNESS_M) {
            $messages[] = sprintf('%s: a grille is a sheet, so depth_m must be at most %s m, got %s', $label, BaffleFeature::GRILLE_MAX_THICKNESS_M, $feature->depthM);
        }
        if (($feature->setbackM ?? 0.0) + $feature->depthM > $cabinetDepth + 1e-9) {
            $messages[] = "{$label}: set back {$feature->setbackM} m it reaches past the back of the cabinet";
        }

        return $messages;
    }

    /**
     * A round grille's dome and rim, which a rectangular one cannot carry because the builder only presses and
     * rings a disc.
     *
     * @return list<string>
     */
    private function validateGrilleShape(BaffleFeature $feature, string $label): array
    {
        $messages = [];
        if (!$feature->isGrille() || null === $feature->diameterIn) {
            foreach (['dome_m' => $feature->domeM, 'rim_m' => $feature->rimM, 'rim_color' => $feature->rimColor] as $field => $value) {
                if (null !== $value) {
                    $messages[] = "{$label}: {$field} only applies to a round grille";
                }
            }

            return $messages;
        }

        if (null !== $feature->domeM && ($feature->domeM <= 0.0 || $feature->domeM > BaffleFeature::GRILLE_MAX_DOME_M)) {
            $messages[] = sprintf('%s: dome_m must be above 0 and at most %s m, got %s', $label, BaffleFeature::GRILLE_MAX_DOME_M, $feature->domeM);
        }
        $radius = $feature->diameterIn * 0.0254 / 2.0;
        if (null !== $feature->rimM && ($feature->rimM <= 0.0 || $feature->rimM >= $radius)) {
            $messages[] = sprintf('%s: rim_m must be above 0 and below the grille\'s radius of %.3f m, got %s', $label, $radius, $feature->rimM);
        }
        if (null !== $feature->rimColor) {
            if (null === $feature->rimM) {
                $messages[] = "{$label}: rim_color needs rim_m";
            }
            if (1 !== preg_match(ValidationRules::COLOR_PATTERN, $feature->rimColor)) {
                $messages[] = "{$label}: rim_color '{$feature->rimColor}' must be a #rrggbb hex colour";
            }
        }

        return $messages;
    }

    /**
     * A cell's tilted back wall has to stay between the baffle and the cabinet's back at both of its ends.
     *
     * @return list<string>
     */
    private function validateCellTilt(BaffleFeature $feature, string $label, float $cabinetDepth): array
    {
        if (null === $feature->angleDeg) {
            return [];
        }
        if (abs($feature->angleDeg) >= 90) {
            return ["{$label}: angle_deg must be between -90 and 90, got {$feature->angleDeg}"];
        }

        $depths = $feature->cellBackDepths();
        if (null === $depths) {
            return [];
        }

        $messages = [];
        if ($depths[0] < -1e-9) {
            $messages[] = sprintf('%s: tilted %s° its back wall comes out %s m in front of the baffle at its shallow end', $label, $feature->angleDeg, round(-$depths[0], 4));
        }
        if ($depths[1] > $cabinetDepth + 1e-9) {
            $messages[] = sprintf('%s: tilted %s° its back wall reaches %s m, past the cabinet (%s)', $label, $feature->angleDeg, round($depths[1], 4), $cabinetDepth);
        }

        return $messages;
    }

    /**
     * A feature's own colour: a cone's paper, a horn's driver, a cell's back wall, a fin's plate.
     *
     * A carved horn's walls are the cabinet's own material, so a colour on a horn can only mean the
     * driver at its throat. On a horn with no driver it would colour nothing.
     *
     * @return list<string>
     */
    private function validateFeatureColor(BaffleFeature $feature, string $label): array
    {
        if (null === $feature->color) {
            return [];
        }
        if (1 !== preg_match(ValidationRules::COLOR_PATTERN, $feature->color)) {
            return ["{$label}: color '{$feature->color}' must be a #rrggbb hex colour"];
        }
        if ($feature->isHorn() && null === $feature->driverIn) {
            return ["{$label}: color on a horn colours the driver at its throat, and this horn has no driver_in"];
        }

        return [];
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

        if (null !== $feature->inside) {
            // A horn set back into its host may say where in the host's mouth it stands. Anything else nested is
            // placed by its host alone.
            $offAxis = null !== $feature->at && $feature->isHorn() && null !== $feature->setbackM;
            if (null !== $feature->at && !$offAxis) {
                $messages[] = "{$label}: `inside` already places it — remove at_m, which only a horn set back with setback_m takes";
            }
            if (!isset($seen[$feature->inside])) {
                $messages[] = sprintf(
                    '%s: `inside: %s` must name an earlier feature',
                    $label,
                    $feature->inside,
                );

                return $messages;
            }

            $parent = $layout->feature($feature->inside);
            if (null !== $parent && $parent->isCell() && ($feature->isCone() || $feature->isGrille())) {
                if (BaffleFeature::LINEAR !== $parent->flare) {
                    return [...$messages, "{$label}: a {$feature->kind} needs a flat wall, and '{$parent->id}' bows"];
                }

                return $this->validateConeOnCellWall($feature, $parent, $label, $opening);
            }
            // A horn set back into a cell stands in its mouth the way it stands in a horn's, as the TMS-2's HF
            // horn does at the top of its port.
            $hornInCell = null !== $parent && $parent->isCell() && $offAxis;
            if (null !== $parent && !$parent->isHorn() && !$hornInCell) {
                $messages[] = "{$label}: `inside` only works within a horn, or for a cone or a grille on a cell's back wall, or for a horn set back into a cell, and '{$parent->id}' is a {$parent->kind}";
            }
            if ($feature->isGrille()) {
                $messages[] = "{$label}: a grille can only sit inside a cell, on its back wall";
            }
            // A nested feature must fit its parent's throat region, or it would poke through the flare.
            $parentOpening = $parent?->openingM();
            if (null !== $parentOpening && null !== $opening) {
                if ($opening[0] > $parentOpening[0] || $opening[1] > $parentOpening[1]) {
                    $messages[] = "{$label}: its mouth is larger than the horn it sits inside";
                }
            }
            if ($offAxis && null !== $parent?->at && null !== $parentOpening && null !== $opening) {
                foreach ([0 => 'x', 1 => 'z'] as $axis => $name) {
                    $reach = abs($feature->at[$axis] - $parent->at[$axis]) + $opening[$axis] / 2;
                    if ($reach > $parentOpening[$axis] / 2 + 1e-9) {
                        $messages[] = sprintf('%s: at_m puts its mouth %.3f m past the %s edge of the horn it sits inside', $label, $reach - $parentOpening[$axis] / 2, $name);
                    }
                }
            }
            if (null !== $parent && ($feature->setbackM ?? 0.0) + $feature->depthM > $parent->depthM + 1e-9) {
                $messages[] = null === $feature->setbackM
                    ? "{$label}: it is deeper than the horn it sits inside"
                    : "{$label}: set back {$feature->setbackM} m it reaches past the throat of the horn it sits inside";
            }

            return $messages;
        }

        if (null === $feature->at) {
            $messages[] = "{$label}: needs either at_m or inside";

            return $messages;
        }
        if (null === $opening) {
            return $messages;
        }

        // Staying within the baffle is what keeps the bounding box equal to the declared dimensions. A
        // turned fin is checked on the extent it actually reaches rather than on its front edge.
        $footprint = $feature->finFootprint();
        $limits = null === $footprint
            ? [
                ['x', $feature->at[0], $opening[0] / 2, $width / 2],
                ['z', $feature->at[1], $opening[1] / 2, $height / 2],
            ]
            : [
                ['x', ($footprint['x'][0] + $footprint['x'][1]) / 2, ($footprint['x'][1] - $footprint['x'][0]) / 2, $width / 2],
                ['z', ($footprint['z'][0] + $footprint['z'][1]) / 2, ($footprint['z'][1] - $footprint['z'][0]) / 2, $height / 2],
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
     * A cone or a grille mounted on a cell's back wall, which tilts with the wall when the cell's `angle_deg` does.
     *
     * It has to fit the wall, which on the tilted axis is longer than the cell's mouth by 1/cos(angle),
     * and the driver behind the wall must not reach out of the back of the cabinet. The cabinet check
     * is left to the depth rule every feature passes, because a cone is at most 0.2 m deep.
     *
     * @param array{float, float}|null $opening
     *
     * @return list<string>
     */
    private function validateConeOnCellWall(BaffleFeature $cone, BaffleFeature $cell, string $label, ?array $opening): array
    {
        $wall = $cell->mouth;
        if (null === $opening || null === $wall) {
            return [];
        }

        $stretch = 1 / cos(deg2rad($cell->angleDeg ?? 0.0));
        $yaw = BaffleFeature::YAW === $cell->effectiveTurn();
        $fits = [$wall[0] * ($yaw ? $stretch : 1.0), $wall[1] * ($yaw ? 1.0 : $stretch)];
        if ($opening[0] > $fits[0] + 1e-9 || $opening[1] > $fits[1] + 1e-9) {
            return [sprintf("%s: a %s m cone does not fit the %s x %s m back wall of '%s'", $label, round($opening[0], 4), round($fits[0], 4), round($fits[1], 4), $cell->id)];
        }

        return [];
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
     *
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
        if (null === $join) {
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
        if (null === $partner) {
            return $messages;
        }
        if (!$partner->isHorn()) {
            $messages[] = "{$label}: join only works between horns, and '{$partner->id}' is a {$partner->kind}";
        }
        foreach ([$feature, $partner] as $side) {
            if (null !== $side->inside) {
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
        if (null === $feature->at || null === $partner->at || null === $opening || null === $partnerOpening) {
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
}
