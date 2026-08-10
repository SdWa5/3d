<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;
use App\Spec\Violation;

/**
 * Turns a scene's placements into absolute positions.
 *
 * All the arithmetic lives here rather than in the Blender script, so stacking heights, repetition
 * and the checks around them are unit-testable without Blender — and so a scene that does not add up
 * is caught before a single model is loaded.
 */
final class SceneCompiler
{
    /**
     * @param array<string, DeviceSpec> $devicesById
     */
    public function __construct(private readonly array $devicesById)
    {
    }

    /**
     * @return array{placed: list<PlacedDevice>, violations: list<Violation>}
     */
    public function compile(SceneSpec $scene): array
    {
        $placed = [];
        $violations = [];
        /** @var array<string, PlacedDevice> $byId last repeat of each placement, for `on` */
        $byId = [];
        // Every cabinet of each placement, for `align`. `$byId` cannot stand in for this: it holds the
        // anchor copy alone, so reading an envelope off it would size `across: sub-wall` from one Flexy's
        // 0.763 m instead of the wall's 4.678 m, silently and plausibly.
        /** @var array<string, list<PlacedDevice>> $placedById */
        $placedById = [];

        $add = static function (string $message) use ($scene, &$violations): void {
            $violations[] = new Violation($scene->sourcePath, $message);
        };

        // A `stack` is solved into ordinary placements first, once, so nothing after this point — not the
        // front-face walk, not the placing loop, not the report — has to know stacks exist.
        $placements = $this->expandStacks($scene->placements, $add);

        // Where the rig stands, worked out before any orientation exists. Aiming needs the focus
        // point, the focus point needs the rig's front face, and the front face must not depend on
        // aiming — otherwise the two would chase each other.
        $frontCentre = $this->frontCentre($placements);
        $focusPoints = array_map(
            static fn (Focus $focus): array => $focus->point($frontCentre),
            $scene->focusByName,
        );

        $lowest = null;
        foreach ($placements as $placement) {
            if (isset($byId[$placement->id])) {
                $add("duplicate placement id '{$placement->id}'");
                continue;
            }

            $device = $this->devicesById[$placement->deviceId] ?? null;
            if ($device === null) {
                $add("placement '{$placement->id}' references unknown device '{$placement->deviceId}'");
                continue;
            }

            $base = $this->resolveBase($placement, $device, $byId, $add);
            if ($base === null) {
                continue;
            }

            if ($placement->aimFocus !== null && !isset($focusPoints[$placement->aimFocus])) {
                // `aim: focuss` used to mean *not aimed*, silently, with the cabinet left firing straight
                // ahead and nothing to see in the output.
                $add(sprintf(
                    "placement '%s': unknown focus '%s' (defined: %s)",
                    $placement->id,
                    $placement->aimFocus,
                    implode(', ', array_keys($focusPoints)),
                ));
                continue;
            }

            $target = $placement->aimAt ?? ($placement->aimFocus === null ? null : $focusPoints[$placement->aimFocus]);
            // An arc's radius depends on how far the cabinets are tilted, and the tilt depends on where
            // they stand — so the arc is solved at the tilt of its anchor, which stands on `at` facing
            // straight ahead. Across a three-wide arc the individual tilts differ by 0.03°.
            $pitch = $target === null
                ? $placement->pitchDeg
                : Orientation::pitchTowards($base, $target, $device->dimensions->height, 0.0);

            $problems = $this->validate($placement, $device, $pitch);
            if ($problems !== []) {
                foreach ($problems as $problem) {
                    $add("placement '{$placement->id}': {$problem}");
                }
                continue;
            }

            // A hang is one rigid body, so it is aimed once — at its anchor — and every element inherits
            // that attitude, differing only by the splay accumulated down to it. Aimed element by element
            // instead, each one turns towards the target on its own and the splay cancels out exactly:
            // four boxes all pointing at the same spot, which is not a J array.
            $hangAim = $target !== null && $placement->group->decidesPitch()
                ? Orientation::aimedAt($base, $target, $device->dimensions->height, $placement->rollDeg)
                : null;

            $copies = $this->copies($placement, $device, $pitch);
            // ...and because it is one rigid body, its chain swings with it. A hang solves its joints in the
            // elevation plane, which has no x in it, so its offsets come out along the world's y — while
            // every element is yawed towards the target. `flown-array` is aimed 11.1° off-axis, and the two
            // disagreeing put 3.5 mm of the bottom element inside the one above it.
            if ($placement->group->decidesPitch()) {
                $hangYaw = $hangAim?->yawDeg ?? $placement->yawDeg;
                $copies = array_map(
                    static fn (PlacementCopy $copy): PlacementCopy => $copy->yawedBy($hangYaw),
                    $copies,
                );
            }
            // Spreading the tier across its envelope happens here, after the copies exist and before
            // anything is placed: the solve needs the arrangement the group made, and everything downstream
            // needs the spread one. Only x offsets move, so the stacking below is unaffected.
            if ($placement->align !== null) {
                $aligned = $this->aligned($placement, $device, $copies, $base, $target, $hangAim, $pitch, $placedById);
                if (is_string($aligned)) {
                    $add("placement '{$placement->id}': {$aligned}");
                    continue;
                }
                $copies = $aligned;
            }

            // A group can put part of itself below its own base — turning a multi-tier cell over maps its
            // offsets `z → −z` — so the whole arrangement is raised back onto the slot, exactly as one
            // rotated cabinet is. A hang is exempt: it belongs below its anchor.
            $lift = GroupStack::zLift($device, $copies, $pitch, $placement->rollDeg);

            foreach ($copies as $copy) {
                $id = $placement->id.$copy->idSuffix();

                $position = [
                    $base[0] + $copy->offset[0],
                    $base[1] + $copy->offset[1],
                    $base[2] + $copy->offset[2] + $lift,
                ];

                $orientation = $this->orientationFor($placement, $device, $copy, $position, $target, $hangAim);
                if ($orientation === null) {
                    $add(
                        "placement '{$placement->id}': the group turns cabinet {$id} onto its end, "
                        .'where its roll and its yaw become the same turn',
                    );
                    continue;
                }

                $entry = new PlacedDevice(
                    $id,
                    $device,
                    $position,
                    $orientation,
                    // A hang's slot is not the floor, whether it is one cabinet or a whole array: lifting a
                    // flown cabinet back onto a slot would move it away from the hardware holding it up.
                    $copy->seated && $placement->fly === null,
                    $placement->aimLines,
                    $placement->fly?->label($placement->id),
                );

                $placed[] = $entry;
                $placedById[$placement->id][] = $entry;
                $lowest = min($lowest ?? INF, $entry->worldBox()['min'][2]);
                // `on` refers to the placement as a whole, so one copy has to stand for it: the last of
                // a repeated row, and the middle of an arc, which is the one sitting on `at`.
                if ($copy->isAnchor) {
                    $byId[$placement->id] = $entry;
                }
            }

            // The check that makes `fly` police itself: a hang is the one thing that can legitimately be
            // told to sit above the floor and still end up through it, because its elements grow downwards
            // from the anchor rather than upwards from the ground.
            if ($placement->fly !== null && $lowest !== null && $lowest < -1e-9) {
                $add(sprintf(
                    "placement '%s': the hang reaches %.3f m below the floor — raise fly.height_m by at least that",
                    $placement->id,
                    -$lowest,
                ));
            }
            $lowest = null;
        }

        return ['placed' => $placed, 'violations' => $violations];
    }

    /**
     * How one cabinet ends up turned: the groups' contribution applied *outside* the cabinet's own
     * attitude, `G · P`.
     *
     * The placement's pitch and roll are the attitude the cabinet stands at inside its cell; a group's
     * rotation is how that cell is turned in the world. Composing them the other way round would apply an
     * arc seat's yaw inside an already-tilted frame, which is not what a fan of tilted tops is.
     *
     * Aim is resolved per copy, so a repeated row of tops each turns towards the target rather than all
     * sharing the first one's angle. Where a group has already decided the yaw, the aim contributes the
     * down-tilt only — and it is measured against the *group's* yaw while the roll it is given is the
     * placement's alone, because a roll living in the group is already in the matrix and counting it twice
     * would tilt every alternately-rolled cabinet the wrong way.
     *
     * Null when the composition cannot be split back into three angles, which is a cabinet stood on end.
     *
     * @param array{float, float, float} $position
     * @param array{float, float, float}|null $target
     */
    private function orientationFor(
        Placement $placement,
        DeviceSpec $device,
        PlacementCopy $copy,
        array $position,
        ?array $target,
        ?Orientation $hangAim = null,
    ): ?Orientation {
        // A group that tilts its cabinets — a hang, where the splay *is* the tilt — adds to whatever the
        // placement or its aim resolved to, rather than turning the cell.
        $tilt = $copy->pitchIncrementDeg;

        if ($hangAim !== null) {
            // Resolved once for the whole hang; only the splay differs between elements.
            $own = new Orientation($hangAim->pitchDeg + $tilt, $hangAim->rollDeg, $hangAim->yawDeg);
        } elseif ($target === null) {
            $own = new Orientation($placement->pitchDeg + $tilt, $placement->rollDeg, $placement->yawDeg);
        } elseif (!$placement->group->decidesYaw()) {
            // Nothing has claimed the yaw, so the aim gets both of them. A lattice's cycled roll comes
            // through `$copy->rotation` without claiming the yaw, which is why the question is whether the
            // group *decides* the yaw and not whether this copy happens to have one of zero.
            $aimed = Orientation::aimedAt($position, $target, $device->dimensions->height, $placement->rollDeg);
            $own = new Orientation($aimed->pitchDeg + $tilt, $aimed->rollDeg, $aimed->yawDeg);
        } else {
            $own = new Orientation(
                Orientation::pitchTowards(
                    $position,
                    $target,
                    $device->dimensions->height,
                    $copy->rotation?->yawDeg ?? 0.0,
                ) + $tilt,
                $placement->rollDeg,
                0.0,
            );
        }

        if ($copy->rotation === null) {
            return $own;
        }

        return $copy->rotation->after($own);
    }

    /**
     * A scene's placements with every `stack` replaced by the tiers it solves to.
     *
     * Done in one pass up front rather than lazily, because a stack's tiers are ordinary placements that
     * later entries can stand `on` — so they have to be in the list before anything walks it. A stack whose
     * constraints cannot be met contributes nothing and reports why; the rest of the scene still compiles,
     * which is the same courtesy every other failure here gets.
     *
     * @param list<Placement> $placements
     * @param callable(string):void $add
     * @return list<Placement>
     */
    private function expandStacks(array $placements, callable $add): array
    {
        $expanded = [];

        foreach ($placements as $placement) {
            if ($placement->stack === null) {
                $expanded[] = $placement;
                continue;
            }

            $problems = $placement->stack->problems();
            $inventory = [];
            foreach ($placement->stack->from as $deviceId) {
                $device = $this->devicesById[$deviceId] ?? null;
                if ($device === null) {
                    $problems[] = "stack.from: unknown device '{$deviceId}'";
                    continue;
                }
                $inventory[] = [$device, $device->quantity];
            }

            if ($problems === []) {
                $solved = StackSolver::solve($inventory, $placement->stack);
                $problems = $solved['problems'];
                if ($problems === []) {
                    array_push($expanded, ...$placement->stack->expand($placement, $solved['tiers']));
                    continue;
                }
            }

            foreach ($problems as $problem) {
                $add("placement '{$placement->id}': {$problem}");
            }
        }

        return $expanded;
    }

    /**
     * The copies spread across their envelope, or the one message saying why they cannot be.
     *
     * The solve is a fixed point rather than a formula — see {@see Alignment} — so it is bisected, and the
     * objective is measured by laying the cabinets out **for real**: same {@see orientationFor}, same
     * {@see PlacedDevice::worldBox} as the final geometry. Anything cheaper would be a second opinion about
     * where a cabinet's edge is, and the whole point of the feature is that there is only one.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     * @param array<string, list<PlacedDevice>> $placedById
     * @return list<PlacementCopy>|string
     */
    private function aligned(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        array $placedById,
    ): array|string {
        $align = $placement->align;
        if ($align === null || !$align->mode->isSolved()) {
            return $copies;
        }

        $width = Envelope::widthFor($align, $placedById);
        if (is_string($width)) {
            return $width;
        }

        $spanAt = fn (float $parameter): float => $this->spanOf(
            $placement,
            $device,
            $align->apply($copies, $parameter),
            $base,
            $target,
            $hangAim,
            $pitchDeg,
        );

        // The only genuinely unachievable case, and it is worth its own message: by monotonicity the
        // tightest the tier can ever be is every cabinet on `at`, so if that is already too wide, no
        // spacing exists and the scene has asked for something that does not fit.
        $tightest = $spanAt(0.0);
        if ($tightest > $width + StepSolver::TOLERANCE_M) {
            return sprintf(
                'align has a %.4f m envelope to fill and these %d cabinets are %.4f m across '
                .'even stacked on one spot — widen it or drop a cabinet',
                $width,
                count($copies),
                $tightest,
            );
        }

        $parameter = StepSolver::solve($spanAt, $width, $align->startParameter());
        if ($parameter === null) {
            return sprintf(
                'align cannot be solved: the tier never reaches its %.4f m envelope, however far it is spread',
                $width,
            );
        }

        return $align->apply($copies, $parameter);
    }

    /**
     * How much x an arrangement covers once every cabinet in it is placed and turned.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     */
    private function spanOf(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
    ): float {
        $lift = GroupStack::zLift($device, $copies, $pitchDeg, $placement->rollDeg);

        $min = INF;
        $max = -INF;
        foreach ($copies as $copy) {
            $position = [
                $base[0] + $copy->offset[0],
                $base[1] + $copy->offset[1],
                $base[2] + $copy->offset[2] + $lift,
            ];

            $orientation = $this->orientationFor($placement, $device, $copy, $position, $target, $hangAim);
            if ($orientation === null) {
                continue;
            }

            $box = (new PlacedDevice(
                '',
                $device,
                $position,
                $orientation,
                $copy->seated && $placement->fly === null,
            ))->worldBox();

            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return $min === INF ? 0.0 : $max - $min;
    }

    /**
     * Everything wrong with a placement, as messages without its `placement '<id>': ` prefix.
     *
     * Pure, so both the placing loop and the front-face walk can call it and neither has to guard
     * against geometry that does not resolve.
     *
     * @return list<string>
     */
    private function validate(Placement $placement, DeviceSpec $device, float $pitchDeg): array
    {
        $messages = [];

        if ($placement->group->decidesYaw() && $placement->yawDeg !== 0.0) {
            $messages[] = sprintf('the %s already sets yaw — remove yaw_deg', $placement->group->kind());
        }
        if ($placement->fly !== null && $placement->on !== null) {
            // Both decide the same number, and there is no reading of the pair that is not a contradiction.
            $messages[] = 'use either `fly` or `on`, not both — they both decide the height';
        }
        if ($placement->fly?->point !== null && $placement->fly->pointOn($device) === null) {
            $names = array_map(
                static fn (\App\Spec\RiggingPoint $point): string => $point->id,
                $device->riggingPoints,
            );
            $messages[] = sprintf(
                "fly.point '%s' is not a rigging point of %s (%s)",
                $placement->fly->point,
                $device->id,
                $names === [] ? 'it has none' : 'has: '.implode(', ', $names),
            );
        }
        if ($placement->fly?->point !== null && !$device->flyable) {
            $messages[] = sprintf('fly.point names a point on %s, which is not flyable', $device->id);
        }
        if ($placement->aimAt !== null && $placement->aimFocus !== null) {
            $messages[] = 'use either `aim` or `aim_at`, not both';
        }
        if (
            ($placement->aimAt !== null || $placement->aimFocus !== null)
            && ($placement->yawDeg !== 0.0 || $placement->pitchDeg !== 0.0)
        ) {
            // A group's yaw does not live in `yaw_deg`, so this stays the same rule it always was.
            $messages[] = 'aiming already sets yaw and pitch — remove yaw_deg/pitch_deg';
        }
        if ($placement->align !== null) {
            $messages = [...$messages, ...$placement->align->problems($placement->group, $placement->copyCount())];
        }

        if ($messages !== []) {
            return $messages;
        }

        return $placement->group->problems(
            $device,
            $pitchDeg,
            $placement->rollDeg,
            GroupStack::cabinetBox($device, $pitchDeg, $placement->rollDeg),
        );
    }

    /**
     * The cabinets a placement produces. The only place expansion happens.
     *
     * @return list<PlacementCopy>
     */
    private function copies(Placement $placement, DeviceSpec $device, float $pitchDeg): array
    {
        return $placement->group->copies(
            $device,
            $pitchDeg,
            $placement->rollDeg,
            GroupStack::cabinetBox($device, $pitchDeg, $placement->rollDeg),
        );
    }

    /**
     * The rig's x centre and the y of its front face.
     *
     * Applies every rotation that does **not** depend on the focus — an arc's yaw, an explicitly stated
     * yaw, roll, and pitch where the placement is not aimed. Anything that did depend on aiming would be
     * circular, because the focus point is derived from this and aiming is derived from the focus point.
     *
     * Getting this wrong is not cosmetic: a concave arc's outer cabinets stand well in front of its
     * middle one, and treating them as unrotated puts the front face 62 mm too far back, which lands the
     * focus that much further out than the scene asked for. The arc is measured here at zero tilt, so
     * there is a couple of centimetres of slack left in the other direction — it does not compound,
     * because the focus only feeds back into the tilt.
     *
     * @param list<Placement> $placements the scene's placements, with every `stack` already expanded
     * @return array{float, float}
     */
    private function frontCentre(array $placements): array
    {
        /** @var array<string, array{float, float}> $ground */
        $ground = [];
        $minX = $minY = INF;
        $maxX = -INF;

        foreach ($placements as $placement) {
            $device = $this->devicesById[$placement->deviceId] ?? null;
            if ($device === null) {
                continue;
            }

            $base = $placement->at;
            if ($base === null && $placement->on !== null) {
                $base = $ground[$placement->on] ?? null;
            }
            if ($base === null) {
                continue;
            }

            $aimed = $placement->aimAt !== null || $placement->aimFocus !== null;
            $pitch = $aimed ? 0.0 : $placement->pitchDeg;
            $yaw = $aimed ? 0.0 : $placement->yawDeg;

            // A placement the compiler is going to reject contributes nothing, and skipping it here is
            // what lets `copies()` assume the geometry resolves.
            if ($this->validate($placement, $device, $pitch) !== []) {
                continue;
            }

            // An `align` is not solved here, and it does not have to be. Both solved modes are symmetric
            // about `at`, so spreading a tier never moves the rig's centre line; `across` and `inside`
            // reach no further than the placement they are measured from, which this walk has already
            // counted; and `min y` — the only thing the focus distance is measured from — does not depend
            // on an x spacing at all, because aimed placements are evaluated here at yaw 0. A stated
            // `width_m` is the one envelope with no such backstop, so it is contributed outright.
            if ($placement->align?->widthM !== null) {
                $minX = min($minX, $base[0] - $placement->align->widthM / 2);
                $maxX = max($maxX, $base[0] + $placement->align->widthM / 2);
            }

            foreach ($this->copies($placement, $device, $pitch) as $copy) {
                $x = $base[0] + $copy->offset[0];
                $y = $base[1] + $copy->offset[1];

                $attitude = new Orientation($pitch + $copy->pitchIncrementDeg, $placement->rollDeg, $yaw);
                $box = (new PlacedDevice(
                    $placement->id,
                    $device,
                    [$x, $y, 0.0],
                    $copy->rotation === null ? $attitude : ($copy->rotation->after($attitude) ?? $attitude),
                    $copy->seated,
                ))->worldBox();

                $minX = min($minX, $box['min'][0]);
                $maxX = max($maxX, $box['max'][0]);
                $minY = min($minY, $box['min'][1]);

                if ($copy->isAnchor) {
                    $ground[$placement->id] = [$x, $y];
                }
            }
        }

        if ($minX === INF) {
            return [0.0, 0.0];
        }

        return [($minX + $maxX) / 2, $minY];
    }

    /**
     * Where the first (or only) copy of a placement sits.
     *
     * @param array<string, PlacedDevice> $byId
     * @param callable(string):void $add
     * @return array{float, float, float}|null
     */
    private function resolveBase(Placement $placement, DeviceSpec $device, array $byId, callable $add): ?array
    {
        if ($placement->fly !== null) {
            if ($placement->at === null) {
                $add("placement '{$placement->id}': `fly` needs `at` for the x and y it hangs over");

                return null;
            }

            return $placement->fly->slot($placement->at, $placement->fly->pointOn($device));
        }

        if ($placement->on === null) {
            if ($placement->at === null) {
                $add("placement '{$placement->id}': needs either `at`, `on` or `fly`");

                return null;
            }

            return [$placement->at[0], $placement->at[1], 0.0];
        }

        $support = $byId[$placement->on] ?? null;
        if ($support === null) {
            $add("placement '{$placement->id}': `on: {$placement->on}` must name an earlier placement");

            return null;
        }

        // Stacking is why heights never have to be written into a scene: the supported cabinet
        // starts exactly where the one below it ends, whatever the specs say today.
        $x = $placement->at[0] ?? $support->position[0];
        $y = $placement->at[1] ?? $support->position[1];

        return [$x, $y, $support->topZ()];
    }
}
