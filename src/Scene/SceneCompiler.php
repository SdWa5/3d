<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\Category;
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
    /** How often {@see flushFront} re-aims a copy at most. Two passes settle a 10 m focus to well under this. */
    private const FLUSH_PASSES = 4;

    /** How far a copy's front may stand off the stack's front plane and count as on it. */
    private const FLUSH_TOLERANCE_M = 1e-6;

    /** How often {@see expandStacksAimedAtTheRig} re-solves a pooled stack at most to follow a moving rig centre. */
    private const AIM_ROUNDS = 3;

    /** How far a stack's aim may stand from the rig's centre and count as aimed from it. */
    private const SAME_CENTRE_M = 1e-3;

    /**
     * @param array<string, DeviceSpec> $devicesById
     */
    public function __construct(
        private readonly array $devicesById,
        /**
         * True for the throwaway compiler {@see stackSurvives} builds, so it asks no candidate the same question and
         * the recursion is one level deep by construction rather than by a counter.
         */
        private readonly bool $probing = false,
    ) {
    }

    /**
     * Named focus points of the stacks, using the same front plane as their aimed cabinets.
     *
     * @return array<string, array{float, float, float}>
     */
    public function stackFocusPoints(SceneSpec $scene, string $name = 'far'): array
    {
        $points = [];
        foreach ($scene->placements as $placement) {
            if (null === $placement->stack) {
                continue;
            }
            $focus = $placement->focusByName[$name] ?? $scene->focusByName[$name] ?? null;
            if (null === $focus) {
                throw new \InvalidArgumentException("Stack '{$placement->id}' has no '{$name}' focus");
            }
            $expanded = $this->expandStacks(
                $scene,
                [$placement],
                static function (string $message): void { throw new \InvalidArgumentException($message); },
                static function (string $message): void {},
            );
            if ([] !== $expanded) {
                $points[$placement->id] = $focus->point($this->frontCentre($expanded));
            }
        }

        return $points;
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
        $warn = static function (string $message) use ($scene, &$violations): void {
            $violations[] = new Violation($scene->sourcePath, $message, Violation::WARNING);
        };

        // A `stack` is solved into ordinary placements first, once, so nothing after this point — not the
        // front-face walk, not the placing loop, not the report — has to know stacks exist.
        /** @var array<string, string> $stackOf expanded placement id => the stack placement it came from */
        $stackOf = [];
        $placements = $this->expandStacksAimedAtTheRig($scene, $add, $warn, $stackOf);

        // Where the rig stands, worked out before any orientation exists. Aiming needs the focus
        // point, the focus point needs the rig's front face, and the front face must not depend on
        // aiming — otherwise the two would chase each other.
        $frontCentre = $this->frontCentre($placements);
        $focusPoints = array_map(
            static fn (Focus $focus): array => $focus->point($frontCentre),
            $scene->focusByName,
        );

        $lowest = null;
        // The front plane of every stack, so a cabinet standing on one from outside stands flush on it as well.
        $stackFront = [];
        foreach ($placements as $placement) {
            $stack = $stackOf[$placement->id] ?? null;
            if (null !== $stack && null !== $placement->frontYM) {
                $stackFront[$stack] = $placement->frontYM;
            }
        }

        foreach ($placements as $placement) {
            if (isset($byId[$placement->id])) {
                $add("duplicate placement id '{$placement->id}'");
                continue;
            }
            if (null === $placement->frontYM && null !== $placement->on && isset($stackFront[$placement->on])) {
                $placement = $placement->flushedOn($stackFront[$placement->on]);
            }

            $model = $this->devicesById[$placement->deviceId] ?? null;
            if (null === $model) {
                $add("placement '{$placement->id}' references unknown device '{$placement->deviceId}'");
                continue;
            }
            $device = self::extended($placement, $model);
            if (is_string($device)) {
                $add("placement '{$placement->id}': {$device}");
                continue;
            }

            $base = $this->resolveBase($placement, $device, $byId, $add);
            if (null === $base) {
                continue;
            }

            if (null !== $placement->aimFocus && !isset($focusPoints[$placement->aimFocus])) {
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

            // **A PLACEMENT'S OWN FOCUS BEATS THE SCENE'S, AND IS MEASURED FROM THE PLACEMENT.** Everything above
            // resolved the scene's focus points once, from the *rig's* front centre, which is right for a cluster
            // and its near-fills and wrong for two systems standing side by side. A placement that states its own
            // is stating "ten metres in front of **me**". See {@see withOwnFocus}, which does the same thing one
            // level down for the cabinets a `stack:` expands into.
            $own = null === $placement->aimFocus ? null : ($placement->focusByName[$placement->aimFocus] ?? null);
            $target = $placement->aimAt ?? match (true) {
                null !== $own => $own->point($this->frontCentre([$placement])),
                null === $placement->aimFocus => null,
                default => $focusPoints[$placement->aimFocus],
            };
            // An arc's radius depends on how far the cabinets are tilted, and the tilt depends on where
            // they stand — so the arc is solved at the tilt of its anchor, which stands on `at` facing
            // straight ahead. Across a three-wide arc the individual tilts differ by 0.03°.
            $pitch = null === $target
                ? $placement->pitchDeg
                : Orientation::pitchTowards($base, $target, $device->dimensions->height, 0.0);

            $problems = $this->validate($placement, $device, $pitch);
            if ([] !== $problems) {
                foreach ($problems as $problem) {
                    $add("placement '{$placement->id}': {$problem}");
                }
                continue;
            }

            // A hang is one rigid body, so it is aimed once — at its anchor — and every element inherits
            // that attitude, differing only by the splay accumulated down to it. Aimed element by element
            // instead, each one turns towards the target on its own and the splay cancels out exactly:
            // four boxes all pointing at the same spot, which is not a J array.
            $hangAim = null !== $target && $placement->group->decidesPitch()
                ? Orientation::aimedAt($base, $target, $device->dimensions->height, $placement->rollDeg)
                : null;

            $copies = $this->copies($placement, $device, $pitch);
            // ...and because it is one rigid body, its chain swings with it. A hang solves its joints in the
            // elevation plane, which has no x in it, so its offsets come out along the world's y — while
            // every element is yawed towards the target. `flown-array` is aimed 11.1° off-axis, and the two
            // disagreeing put 3.5 mm of the bottom element inside the one above it.
            if ($placement->group->decidesPitch()) {
                $hangYaw = $hangAim->yawDeg ?? $placement->yawDeg;
                $copies = array_map(
                    static fn (PlacementCopy $copy): PlacementCopy => $copy->yawedBy($hangYaw),
                    $copies,
                );
            }
            // Spreading the tier across its envelope happens here, after the copies exist and before
            // anything is placed: the solve needs the arrangement the group made, and everything downstream
            // needs the spread one. Only x offsets move, so the stacking below is unaffected.
            if (null !== $placement->align) {
                $aligned = $this->aligned(
                    $placement,
                    $device,
                    $copies,
                    $base,
                    $target,
                    $hangAim,
                    $pitch,
                    $placedById,
                    static function (string $message) use ($warn, $placement): void {
                        $warn("placement '{$placement->id}': {$message}");
                    },
                );
                if (is_string($aligned)) {
                    $add("placement '{$placement->id}': {$aligned}");
                    continue;
                }
                $copies = $aligned;
            }

            // **AND THEN THE ROW IS SPACED AGAINST ITS OWN TOE-IN**, which is the one relationship nothing above asks
            // about: a run's copies against each other. See {@see clearedWithin}.
            $copies = $this->clearedWithin(
                $placement,
                $device,
                $copies,
                $base,
                $target,
                $hangAim,
                $pitch,
                static function (string $message) use ($warn, $placement): void {
                    $warn("placement '{$placement->id}': {$message}");
                },
            );

            // **AND MOVED BACK ONTO THE STACK'S FRONT**, which only an aimed cabinet needs, because aiming swings a
            // front corner forward. The spacing above was solved on copies flushed the same way, see {@see placedFor},
            // so this moves nothing the solve did not already measure.
            $copies = $this->flushFront($placement, $device, $copies, $base, $target, $hangAim, $pitch);

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
                if (null === $orientation) {
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
                    $copy->seated && null === $placement->fly,
                    $placement->aimLines,
                    $placement->fly?->label($placement->id),
                    // A packed device is a different model, not a shorter one, so it is never stretched.
                    $device->folded ? 1.0 : $device->dimensions->height / $model->dimensions->height,
                );

                $placed[] = $entry;
                $placedById[$placement->id][] = $entry;
                $lowest = min($lowest ?? INF, $entry->worldBox()['min'][2]);
                // `on` refers to the placement as a whole, so one copy has to stand for it: the last of
                // a repeated row, and the middle of an arc, which is the one sitting on `at`.
                if ($copy->isAnchor) {
                    $byId[$placement->id] = $entry;
                }
                // **A STACK CAN BE STOOD ON AS A WHOLE**, by its own id, and what carries the cabinet is its highest one.
                // That is what SYM-3's shared tops row needs, since nothing outside a stack knows the ids its tiers
                // solve to. A stack has one top height wherever its top tier is level, which the generator checks.
                $stack = $stackOf[$placement->id] ?? null;
                if (null !== $stack && (!isset($byId[$stack]) || $entry->topZ() > $byId[$stack]->topZ())) {
                    $byId[$stack] = $entry;
                }
            }

            // The check that makes `fly` police itself: a hang is the one thing that can legitimately be
            // told to sit above the floor and still end up through it, because its elements grow downwards
            // from the anchor rather than upwards from the ground.
            if (null !== $placement->fly && null !== $lowest && $lowest < -1e-9) {
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
     * Every copy moved front to back until the front edge of its foot stands on the placement's `frontYM`.
     *
     * **An aimed cabinet's front is not where `at` put it.** {@see Stack::expand} stands every cabinet flush at
     * the front, which holds for one firing straight ahead. A top yawed 25° towards the far focus swings its
     * outer front corner about 80 mm forward of the wall it stands on. So each copy is aimed, the front of its
     * foot measured, and the copy moved back by what pokes out. The foot rather than the whole cabinet, because a
     * top tilted down leans its upper front edge out over the wall the way a real one on a wedge does, see
     * {@see PlacedDevice::footFrontY}. Moving it changes its aim
     * a little, so this repeats until the front stays put, which at a 10 m focus takes two passes.
     *
     * Only y moves. {@see placedFor} flushes every candidate the same way before it measures it, so the spacing
     * that `align` and the row clearance solve for is the spacing of the flushed copies, and neither undoes the
     * other.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     *
     * @return list<PlacementCopy>
     */
    private function flushFront(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
    ): array {
        if (null === $placement->frontYM || null !== $placement->fly) {
            return $copies;
        }

        $lift = GroupStack::zLift($device, $copies, $pitchDeg, $placement->rollDeg);
        $flushed = [];
        foreach ($copies as $copy) {
            for ($pass = 0; $pass < self::FLUSH_PASSES; ++$pass) {
                $position = [
                    $base[0] + $copy->offset[0],
                    $base[1] + $copy->offset[1],
                    $base[2] + $copy->offset[2] + $lift,
                ];
                $orientation = $this->orientationFor($placement, $device, $copy, $position, $target, $hangAim);
                if (null === $orientation) {
                    // Refused by the placing loop, which says why.
                    break;
                }

                $front = (new PlacedDevice('', $device, $position, $orientation, $copy->seated))->footFrontY();
                $shift = $placement->frontYM - $front;
                if (abs($shift) < self::FLUSH_TOLERANCE_M) {
                    break;
                }
                $copy = $copy->movedInY($copy->offset[1] + $shift);
            }
            $flushed[] = $copy;
        }

        return $flushed;
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

        if (null !== $hangAim) {
            // Resolved once for the whole hang; only the splay differs between elements.
            $own = new Orientation($hangAim->pitchDeg + $tilt, $hangAim->rollDeg, $hangAim->yawDeg);
        } elseif (null === $target) {
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
                    $copy->rotation->yawDeg ?? 0.0,
                ) + $tilt,
                $placement->rollDeg,
                0.0,
            );
        }

        if (null === $copy->rotation) {
            return $own;
        }

        return $copy->rotation->after($own);
    }

    /**
     * Every `stack` expanded, with each pooled stack's seating check aimed where the finished scene will aim it.
     *
     * **GEO-11's scene-level half, as far as a second pass reaches.** A stack's seating check compiles each candidate
     * on its own, so it aims the tops row from that stack's own front centre. The finished scene aims a stack with
     * no `focus:` of its own from the **rig's** front centre, which in a two-stack rig lies off to one side. The tops
     * then turn further in the scene than in the probe, and 0.142.0 measured what that costs. Mirrored sub rows won
     * arrangements that stood alone and overlapped once aimed for real, on ten `sdwa5-sepp` and `gmss` rigs.
     *
     * So every stack is solved once, the rig's front centre is read off the result, and each pooled stack whose own
     * centre is somewhere else is solved again with its probe aimed at the rig's. A re-solve can change a stack's
     * width and so move the rig's centre, which is the circular part. It is followed for at most
     * {@see self::AIM_ROUNDS} rounds, and a rig that has not settled by then keeps the last answer.
     *
     * A stack whose re-solve finds nothing that seats keeps its first arrangement, the same as {@see RigAim} in
     * `scene:stack`, so a rig the aim cannot improve is judged as it was rather than dropped.
     *
     * A stack with a `focus:` of its own aims from its own front centre in the scene as well (see
     * {@see withOwnFocus}), and a single centred stack's own centre is the rig's, so neither is solved twice.
     *
     * @param callable(string):void $add
     * @param callable(string):void $warn
     * @param array<string, string> $stackOf filled with expanded placement id => the stack placement it came from
     *
     * @return list<Placement>
     */
    private function expandStacksAimedAtTheRig(SceneSpec $scene, callable $add, callable $warn, array &$stackOf): array
    {
        // Per placement, so a re-solve replaces one stack's tiers and its messages and leaves the rest alone.
        $expand = function (Placement $placement, ?float $rigCentreX) use ($scene): array {
            /** @var list<array{bool, string}> $messages whether it is a warning, and what it says */
            $messages = [];
            $own = [];
            $expanded = $this->expandStacks(
                $scene,
                [$placement],
                static function (string $message) use (&$messages): void { $messages[] = [false, $message]; },
                static function (string $message) use (&$messages): void { $messages[] = [true, $message]; },
                $own,
                $rigCentreX,
            );

            return ['expanded' => $expanded, 'messages' => $messages, 'stackOf' => $own, 'aimedFromX' => $rigCentreX];
        };

        $passes = [];
        foreach ($scene->placements as $placement) {
            $passes[] = $expand($placement, null);
        }

        for ($round = 0; !$this->probing && $round < self::AIM_ROUNDS; ++$round) {
            $rigCentreX = $this->frontCentre(array_merge(...array_column($passes, 'expanded')))[0];
            $moved = false;
            foreach ($scene->placements as $i => $placement) {
                $expanded = $passes[$i]['expanded'];
                if (null === $placement->stack || [] !== $placement->focusByName || [] === $expanded) {
                    continue;
                }
                $aimedFromX = $passes[$i]['aimedFromX'] ?? $this->frontCentre($expanded)[0];
                if (abs($aimedFromX - $rigCentreX) <= self::SAME_CENTRE_M) {
                    continue;
                }
                $reaimed = $expand($placement, $rigCentreX);
                if ([] === $reaimed['expanded']) {
                    // **Nothing seats when aimed from the rig, so the first answer stands.** The solver would hand
                    // back an arrangement it never checked, and on `innschleife-psl-sdwa5-sepp` that one broke its own
                    // `shape: v` and dropped the scene. The first pass's arrangement still compiles, and the
                    // interpenetration check on the finished rig decides the verdict as it always did.
                    $passes[$i]['aimedFromX'] = $rigCentreX;
                    continue;
                }
                $passes[$i] = $reaimed;
                $moved = true;
            }
            if (!$moved) {
                break;
            }
        }

        $placements = [];
        foreach ($passes as $pass) {
            array_push($placements, ...$pass['expanded']);
            foreach ($pass['messages'] as [$isWarning, $message]) {
                $isWarning ? $warn($message) : $add($message);
            }
            $stackOf += $pass['stackOf'];
        }

        return $placements;
    }

    /**
     * A scene's foci with every one that has no x of its own aimed at the given x instead.
     *
     * @param array<string, Focus> $focusByName
     *
     * @return array<string, Focus>
     */
    private static function aimedFrom(array $focusByName, ?float $x): array
    {
        if (null === $x) {
            return $focusByName;
        }

        return array_map(
            static fn (Focus $focus): Focus => null !== $focus->xM ? $focus : new Focus($focus->distanceM, $focus->heightM, $x),
            $focusByName,
        );
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
     * @param callable(string):void $warn
     * @param array<string, string> $stackOf filled with expanded placement id => the stack placement it came from
     * @param float|null $rigCentreX where the finished rig's front centre stands, for a stack's seating check to aim
     *                               from when the stack has no `focus:` of its own. Null aims from the stack's own.
     *
     * @return list<Placement>
     */
    private function expandStacks(
        SceneSpec $scene,
        array $placements,
        callable $add,
        callable $warn,
        array &$stackOf = [],
        ?float $rigCentreX = null,
    ): array {
        $expanded = [];

        foreach ($placements as $placement) {
            if (null === $placement->stack) {
                $expanded[] = $placement;
                continue;
            }

            $problems = $placement->stack->problems();
            if (null === $placement->at) {
                // Every tier is centred on the stack's own x, and a mixed row's segments are offset from
                // it — there is nothing to offset from without one, and the bottom tier has no `on` to
                // inherit from either.
                $problems[] = 'stack needs `at` for the x and y the rig is centred on';
            }
            $inventory = [];
            foreach ($placement->stack->from as $entry) {
                $device = $this->devicesById[$entry->device] ?? null;
                if (null === $device) {
                    $problems[] = "stack.from: unknown device '{$entry->device}'";
                    continue;
                }
                foreach ($entry->mixWith as $other) {
                    if (!isset($this->devicesById[$other])) {
                        $problems[] = "stack.from '{$entry->device}': mix_with names unknown device '{$other}'";
                    }
                }
                // A stated `count` over-books deliberately — eight Achenbachs against the six we own, to see
                // whether the rig would work if two more were borrowed. The report already says so:
                // `SceneReport::summarise()` returns `over_inventory` and `scene:build` warns on it.
                $inventory[] = [$device, $entry->count ?? $device->quantity];
            }

            if ([] === $problems) {
                // The placement's own alignment decides the ORDER of the tops row as well as its spacing:
                // stereo puts the long throws at the ends, everything else centres them. See StackTops::topRow.
                $solved = StackSolver::solve(
                    $inventory,
                    $placement->stack,
                    $placement->align?->mode,
                    $this->probing
                        ? null
                        : self::seatingCheck(
                            $this->devicesById,
                            $placement,
                            [] === $placement->focusByName
                                ? self::aimedFrom($scene->focusByName, $rigCentreX)
                                : $scene->focusByName,
                        ),
                );
                $problems = $solved['problems'];
                if ([] === $problems) {
                    // Buildable, but worth saying out loud: a stepped row, or a tier standing slightly
                    // proud of the one below it. Warnings, so the rig still builds.
                    foreach ($solved['warnings'] as $warning) {
                        $warn("placement '{$placement->id}': {$warning}");
                    }
                    $own = $placement->stack->expand($placement, $solved['tiers']);
                    foreach ($own as $tier) {
                        $stackOf[$tier->id] = $placement->id;
                    }
                    array_push($expanded, ...$this->withOwnFocus($placement, $own));
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
     * A stack's cabinets aimed at **its own** focus rather than at the rig's, where the placement states one.
     *
     * **A SCENE-WIDE FOCUS IS ONE POINT, AND THREE SOUND SYSTEMS STANDING SIDE BY SIDE DO NOT SHARE ONE.**
     * {@see Focus::point} measures out from the *rig's* front centre, which is right for a cluster and its
     * near-fills and wrong for `systems-apart`: the outer walls toe inward at a spot in front of the middle one,
     * so three systems cover one patch of floor instead of each covering the room in front of it. That is what a
     * render of the next event showed, and it is what `focus:` on a placement fixes.
     *
     * **Resolved to a point here rather than carried as a name**, because everything downstream reads
     * {@see Placement::$aimAt} already and a second focus lookup one level down would be a second answer to
     * "which focus does this cabinet mean". The point is computed from the expanded cabinets' own front face, so
     * "10 m out at ear height" means ten metres in front of **this** wall.
     *
     * A placement with no `focus:` of its own is returned untouched, which is every hand-written scene: a fill
     * beside a main cluster belongs to that cluster and aims where it aims.
     *
     * @param list<Placement> $expanded
     *
     * @return list<Placement>
     */
    private function withOwnFocus(Placement $placement, array $expanded): array
    {
        if ([] === $placement->focusByName) {
            return $expanded;
        }

        $frontCentre = $this->frontCentre($expanded);
        $points = array_map(
            static fn (Focus $focus): array => $focus->point($frontCentre),
            $placement->focusByName,
        );

        $aimed = [];
        foreach ($expanded as $copy) {
            $point = null === $copy->aimFocus ? null : ($points[$copy->aimFocus] ?? null);
            $aimed[] = null === $point ? $copy : new Placement(
                id: $copy->id,
                deviceId: $copy->deviceId,
                at: $copy->at,
                yawDeg: $copy->yawDeg,
                pitchDeg: $copy->pitchDeg,
                rollDeg: $copy->rollDeg,
                aimAt: $point,
                // **The name is dropped with the point put in its place**, because a placement carrying both is
                // refused — see the validation that says naming `aim` and `aim_at` together is two answers.
                aimFocus: null,
                on: $copy->on,
                fly: $copy->fly,
                group: $copy->group,
                aimLines: $copy->aimLines,
                align: $copy->align,
                stack: $copy->stack,
                focusByName: $copy->focusByName,
                extendToM: $copy->extendToM,
                frontYM: $copy->frontYM,
                packed: $copy->packed,
            );
        }

        return $aimed;
    }

    /**
     * Whether one candidate arrangement survives being **placed for real** — GEO-11's stack-local half.
     *
     * **The fill cannot answer this and must not learn to.** Two cabinets end up inside each other because of yaw,
     * taper and chamfer, none of which a row width knows about, and the repository has exactly one opinion about
     * where a cabinet's edge is. So the candidate is compiled rather than modelled: {@see Stack::expand} turns the
     * tiers into ordinary placements — gravity seated, stacks spaced, runs split — and a throwaway compiler places
     * them with the same `orientationFor()` and `worldBox()` the finished scene uses.
     *
     * **One level deep by construction.** The expanded placements carry no `stack` of their own, so the inner
     * `expandStacks()` finds nothing to solve, and `$probing` stops it asking the question again in any case.
     *
     * **What it deliberately does not see** is the rest of the scene. The inner compile works out its own front face
     * from this stack alone, so a tops row aimed at a focus is aimed from a centre the finished scene may move. That
     * is the genuinely circular half of GEO-11 and it is left open: aiming needs the front face, the front face needs
     * every placement, and every placement needs the solve. What is reconciled here is everything the stack decides
     * on its own, which is where all four of GEO-11's measured symptoms live.
     *
     * A candidate that will not compile at all is refused the same as one that overlaps or leaves a cabinet over air.
     * Either way the search should go on looking rather than hand this arrangement to whoever asked.
     *
     * @param list<Tier> $tiers
     */
    public static function stackSurvives(
        array $devicesById,
        Placement $placement,
        array $tiers,
        array $focusByName = [],
    ): bool {
        if (null === $placement->stack) {
            return true;
        }

        $probe = new SceneSpec(
            sourcePath: 'probe',
            id: 'probe',
            name: 'probe',
            placements: $placement->stack->expand($placement, $tiers),
            // Carried over because a tops row may name a focus of its own, and a probe that did not know it would
            // refuse every aimed arrangement as "unknown focus" rather than judging its geometry.
            focusByName: $focusByName,
            notes: null,
        );

        $result = (new self($devicesById, probing: true))->compile($probe);
        if ([] !== Violation::errorsIn($result['violations'])) {
            return false;
        }

        if (Interpenetration::worst($result['placed'])['separation'] < -PlacementChecks::CONTACT_TOLERANCE_M) {
            return false;
        }

        // **Every arrangement is also asked whether everything stands on something once placed.** The bearing rules
        // judge it on nominal widths, and the placement then aims and spaces the tops row, which can walk an end
        // cabinet off a row whose cabinets stand apart. Measured on `innschleife-psl-sdwa5-sepp`: the psl tops row
        // shifted 0.2 m and left a thebox-dsp-112 entirely over air. This used to be asked of gapped rows only, so
        // that packed rigs kept their pre-GEO-13 verdicts. The mirrored rows of 0.142.0 showed why that was not enough.
        // On `gmss-sdwa5-sepp`, the symmetric row `1S·1wall·1mid·1wall·1S` outranked a lopsided one that stood, and its
        // turbo-top then compiled over air. A packed rig that floats compiles to an impossible scene anyway, so asking
        // it here can only steer the search toward one that stands.
        return [] === PlacementChecks::floatingFaults($result['placed']);
    }

    /**
     * The predicate {@see StackSolver::solve} asks, for one placement of one scene.
     *
     * **Both stages that solve a stack go through this**, which is the point of it. `scene:stack` used to call
     * `StackSolver::solve()` with no predicate and then compile the result, so the two answered differently the
     * moment the predicate started refusing anything — the command wrote the arrangement its own solve liked and the
     * compiler rebuilt a different one from the same file. That is GEO-11's disagreement reappearing between two
     * stages rather than three, and one shared entry point is what stops it.
     *
     * @param array<string, DeviceSpec> $devicesById
     * @param array<string, Focus> $focusByName
     */
    public static function seatingCheck(array $devicesById, Placement $placement, array $focusByName = []): callable
    {
        return static fn (array $tiers): bool => self::stackSurvives($devicesById, $placement, $tiers, $focusByName);
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
     * @param callable(string):void $warn
     *
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
        callable $warn,
    ): array|string {
        $align = $placement->align;
        if (null === $align || !$align->mode->isSolved()) {
            return $copies;
        }

        // Two objectives, one solver. `outside` asks for a clearance beyond somebody else's outer faces; the rest
        // ask for a width to span. They differ in what the bisection chases and in what an impossible case looks
        // like, so they are kept apart here rather than pretended to be one number.
        if ($align->isClearance()) {
            return $this->clearedOutside($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg, $align, $placedById);
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

        // **A ROW WIDER THAN ITS ENVELOPE HAS NOTHING TO JUSTIFY, AND THAT IS NOT AN ERROR.** This used to compute
        // the span at parameter 0 and call it "the tightest the tier can ever be", which was wrong in both modes:
        // for `block` a factor below 1 pulls the copies into each other, and for `stereo` 0 is already the natural
        // spacing rather than every cabinet on one spot. So the floor is the arrangement's own spacing
        // ({@see Alignment::minParameter}), and a row that already exceeds the envelope there keeps that spacing and
        // says so. Refusing instead would throw the rig away over a row that stands up perfectly well unspread, and
        // compressing — what the old bound allowed — put 92 mm of one cabinet inside the next.
        $minimum = $align->minParameter();
        $natural = $spanAt($minimum);
        if ($natural > $width + StepSolver::TOLERANCE_M) {
            $warn(sprintf(
                "align.mode '%s' has a %.4f m envelope and these %d cabinets are %.4f m across at their own "
                .'spacing, so there is nothing to spread — the row keeps that spacing',
                $align->mode->value,
                $width,
                count($copies),
                $natural,
            ));

            return $align->apply($copies, $minimum);
        }

        $parameter = StepSolver::solve($spanAt, $width, $align->startParameter(), $minimum);
        if (null === $parameter) {
            return sprintf(
                'align cannot be solved: the tier never reaches its %.4f m envelope, however far it is spread',
                $width,
            );
        }

        return $align->apply($copies, $parameter);
    }

    /**
     * The copies pushed out until they clear the placement `outside` names by `inset_m`.
     *
     * The solve `full-rig-arc` had to do by hand. Its two fills have to sit beyond the arc's *outer* faces, and
     * an aimed cabinet's outer edge is not its half-width — a toed-in Tecnare's outermost point is its back
     * bottom corner — so the file carried `width_m: 2.60` as "about 20 mm clear" and a comment saying it could
     * not be derived. Now `inset_m: 0.020` states the 20 mm and this finds the 2.60.
     *
     * Refused rather than solved when the cabinets already clear the obstacle by more than asked: pulling them
     * *in* would need a bracket below the starting parameter, and every mode's parameter is bounded below by 0 —
     * every cabinet on `at`, the tightest arrangement there is. A fill already too far out is an over-wide `row`,
     * not a spacing to solve.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     *
     * @return list<PlacementCopy>|string
     */
    /**
     * The copies pushed apart until they clear **each other**, or exactly as they were when they already do.
     *
     * **The last gap in the spacing model.** Three mechanisms space a run against something else: `align` justifies it
     * into an envelope, `align.outside` holds it clear of a named neighbour, and {@see Stack::throwFirst} chains every
     * run of a tier outside the one inboard of it. None of them asks whether a run's own copies clear each other, and
     * they need not: the row is laid out at `gap_m` from *nominal* widths and then every cabinet in it is yawed towards
     * the focus. Where the cabinet is boxy the yaw widens it, and neighbours bite in — 17.6 mm on the by-type
     * three-stack tops row, 7.4 mm on its free-shape sibling, 4.6 and 3.7 mm on the stereo ones, twelve refused
     * candidates in all.
     *
     * **Clearance outranks the envelope, and the asymmetry is why.** A row solved onto its support's carryable width and
     * then pushed apart here ends a few millimetres wider than that width. The bearing rule has room for it and then
     * some, since its allowance is two thirds of a cabinet past each end, which on that row is 600 mm. Interpenetration
     * has no slack at all: a cabinet 17 mm inside its neighbour cannot be built. So the air is taken and the envelope is
     * reported as missed rather than the other way round.
     *
     * **The no-op is checked before anything moves**, and it is the safety property rather than an optimisation. Every
     * scene in the library is already clear, since `ShippedScenesTest` sweeps them all for interpenetration and passes,
     * so this may not move a single cabinet in any of them — which a regenerate and an empty `git diff` confirms.
     *
     * Guarded as narrowly as {@see Alignment::problems} guards itself: one plain row or lattice, nothing nested, at
     * least two across x. Scaling x is only "changing that level's step" when there is one level, and an arc owns its
     * spacing in its radius while a hang owns its in the splay.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     * @param callable(string):void $warn
     *
     * @return list<PlacementCopy>
     */
    private function clearedWithin(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        callable $warn,
    ): array {
        // Unaimed cabinets are as wide as their widths, so there is nothing for a yaw to have taken.
        $levels = $placement->group->groups;
        if (
            null === $target
            || count($copies) < 2
            || 1 !== count($levels)
            || !$levels[0] instanceof Lattice
            || $levels[0]->count[0] < 2
        ) {
            return $copies;
        }

        $gapM = $levels[0]->gapM[0];
        $clearanceAt = fn (float $parameter): float => Interpenetration::narrowestGap($this->placedFor(
            $placement,
            $device,
            self::scaledInX($copies, $parameter),
            $base,
            $target,
            $hangAim,
            $pitchDeg,
        ));

        if ($clearanceAt(1.0) >= $gapM - StepSolver::TOLERANCE_M) {
            return $copies;
        }

        // The floor is 1.0, so this only ever adds air: pulling an aimed row tighter than the spacing it was given is
        // the mistake {@see Alignment::minParameter} exists to prevent.
        $parameter = StepSolver::solve($clearanceAt, $gapM, 1.0, 1.0);
        if (null === $parameter) {
            $warn(sprintf(
                'the %d aimed cabinets never clear each other by %.0f mm however far they are spread, so the row keeps '
                .'its own spacing',
                count($copies),
                $gapM * 1000,
            ));

            return $copies;
        }

        $warn(sprintf(
            'the aimed cabinets toe into each other at their own spacing, so the row is spread %.1f%% wider to keep '
            .'%.0f mm between them',
            ($parameter - 1.0) * 100,
            $gapM * 1000,
        ));

        return self::scaledInX($copies, $parameter);
    }

    /**
     * The copies with every x offset scaled, which is `block`'s mechanism reused — see {@see Alignment::apply}.
     *
     * @param list<PlacementCopy> $copies
     *
     * @return list<PlacementCopy>
     */
    private static function scaledInX(array $copies, float $parameter): array
    {
        return array_map(
            static fn (PlacementCopy $copy): PlacementCopy => $copy->movedInX($copy->offset[0] * $parameter),
            $copies,
        );
    }

    private function clearedOutside(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        Alignment $align,
        array $placedById,
    ): array|string {
        // Two objectives, chosen by which key was written. `clear_of` measures the reference's shells and `outside`
        // measures the span it covers, and {@see Alignment::$clearOf} sets out why neither can stand in for the other.
        if (null !== $align->clearOf) {
            $keepOff = Envelope::cabinetsFor($align, $placedById);
            if (is_string($keepOff)) {
                return $keepOff;
            }

            $clearanceAt = fn (float $parameter): float => $this->clearanceOfShells(
                $placement,
                $device,
                $align->apply($copies, $parameter),
                $base,
                $target,
                $hangAim,
                $pitchDeg,
                $keepOff,
            );
        } else {
            $obstacle = Envelope::obstacleFor($align, $placedById);
            if (is_string($obstacle)) {
                return $obstacle;
            }

            $clearanceAt = fn (float $parameter): float => $this->clearanceOf(
                $placement,
                $device,
                $align->apply($copies, $parameter),
                $base,
                $target,
                $hangAim,
                $pitchDeg,
                $obstacle,
            );
        }

        // **UNLESS THE ALIGNMENT HUGS, WHEN IT IS A TARGET.** A copy standing further off than asked is pulled in until
        // the air is exactly `inset_m`, which moves it against the stated side, so the parameter runs negative and the
        // mirrored objective is what rises. One standing too close falls through to the push below like any other.
        if ($align->hug && $clearanceAt($align->startParameter()) > $align->insetM + StepSolver::TOLERANCE_M) {
            $pulled = StepSolver::solve(
                static fn (float $parameter): float => -$clearanceAt(-$parameter),
                -$align->insetM,
                $align->startParameter(),
            );

            if (null === $pulled) {
                return $copies;
            }

            // **The pull stops where the fill would leave what carries it.** Gravity seats a fill on a shoulder for
            // its bearing, and pulled the whole way to its neighbour it came off that shoulder: 98 of the 2172
            // generated scenes turned `-impossible` with a 2-way standing on nothing. So the pull goes as far as it
            // leaves the fill no more overhanging than gravity left it, bisected where the full pull would not.
            $supports = array_merge(...array_values($placedById));
            $overhangAt = fn (float $parameter): float => $this->overhangOf(
                $placement,
                $device,
                $align->apply($copies, $parameter),
                $base,
                $target,
                $hangAim,
                $pitchDeg,
                $supports,
            );
            $allowed = $overhangAt($align->startParameter()) + StepSolver::TOLERANCE_M;
            $reach = -$pulled;
            if ($overhangAt($reach) > $allowed) {
                $carried = $align->startParameter();
                for ($step = 0; $step < 40; ++$step) {
                    $middle = ($carried + $reach) / 2;
                    if ($overhangAt($middle) > $allowed) {
                        $reach = $middle;
                    } else {
                        $carried = $middle;
                    }
                }
                $reach = $carried;
            }

            return $align->apply($copies, $reach);
        }

        // **`inset_m` is a minimum, not a target.** Cabinets already further out than asked are left exactly where
        // they are rather than pulled back in, and that is the useful reading as well as the safe one: a fill that
        // {@see Gravity} re-seated onto a shoulder for its bearing is 517 mm clear, and dragging it back to 20 mm
        // would undo a repair that was made for a reason. Where the natural spacing does bite — which is every
        // contiguous tops row, once toe-in is applied — the solve pushes out until the air is really there.
        if ($clearanceAt($align->startParameter()) >= $align->insetM - StepSolver::TOLERANCE_M) {
            return $copies;
        }

        $parameter = StepSolver::solve($clearanceAt, $align->insetM, $align->startParameter());
        if (null === $parameter) {
            return sprintf(
                'align.%s: the cabinets never reach %.4f m clear of %s, however far they are pushed out',
                null !== $align->clearOf ? 'clear_of' : 'outside',
                $align->insetM,
                (string) ($align->clearOf ?? $align->outside),
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
        $placed = $this->placedFor($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg);

        return [] === $placed ? 0.0 : Envelope::extentOf($placed);
    }

    /**
     * How much air an `outside` alignment leaves between its cabinets and the placement they have to clear.
     *
     * Negative when the cabinets are still inside the obstacle, which is what the solver needs: the bracket has
     * to start somewhere below the target, and "they overlap by 300 mm" is a perfectly good place to start.
     *
     * The other half of {@see spanOf}'s job, against the same rotated boxes. Both numbers come out of
     * {@see Envelope}: the free span between my own outermost cabinets, less the obstacle's extent, halved —
     * because the clearance is per side and the arrangement is symmetric about `at`.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     * @param array{float, float} $obstacle the x span to get past
     */
    private function clearanceOf(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        array $obstacle,
    ): float {
        $placed = $this->placedFor($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg);
        if ([] === $placed) {
            return 0.0;
        }

        [$low, $high] = $obstacle;

        // The **tightest** gap any one of my cabinets leaves, so a pair straddling the obstacle is judged on
        // whichever side is worse and a lone cabinet on the only side it has. Negative while a cabinet still
        // overlaps, which is what gives the bisection somewhere below the target to start from.
        $worst = INF;
        foreach ($placed as $cabinet) {
            $box = $cabinet->worldBox();
            $worst = min($worst, $box['min'][0] >= $high || $box['max'][0] <= $low
                ? max($low - $box['max'][0], $box['min'][0] - $high)
                : -(min($box['max'][0], $high) - max($box['min'][0], $low)));
        }

        return $worst;
    }

    /**
     * How far, along x, the worst of the copies reaches past everything standing level under it.
     *
     * {@see PlacementChecks::coveredFraction} is the measure, so the hug bounds itself by the same coverage the
     * floating check later reports on.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     * @param list<PlacedDevice> $supports every cabinet placed so far
     */
    private function overhangOf(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        array $supports,
    ): float {
        $worst = 0.0;
        foreach ($this->placedFor($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg) as $cabinet) {
            $box = $cabinet->worldBox();
            $extent = $box['max'][0] - $box['min'][0];
            $worst = max($worst, $extent * (1.0 - PlacementChecks::coveredFraction($cabinet, $supports, 0)));
        }

        return $worst;
    }

    /**
     * How much air a `clear_of` alignment leaves between its cabinets and the ones it must not touch.
     *
     * {@see clearanceOf}'s sibling, and the difference is the whole reason both exist. That one asks how far past a
     * **span** the cabinets are, which is what `outside` means and what a hand-written envelope needs. This asks how
     * far from the obstacle's **shells** they are, which is what a fill beside the long throw needs: two tops aimed at
     * one focus take different yaws, and their axis-aligned spans overlap long before the cabinets do, because a
     * toed-in trapezoid's outermost point is a back bottom corner that swings behind its neighbour rather than into it.
     *
     * Measured against a span, a chain of fills over-pushes and it compounds down the chain: a 2-way yawed 29.4° has a
     * 0.8523 m span on a 0.5 m body, and the turbo top clearing it was driven 514 mm off the run that gave it its
     * height. See {@see Interpenetration::gapBetween}.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     * @param list<PlacedDevice> $obstacle the cabinets to keep off, not a span to get past
     */
    private function clearanceOfShells(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
        array $obstacle,
    ): float {
        $placed = $this->placedFor($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg);

        return [] === $placed ? 0.0 : Interpenetration::gapBetween($placed, $obstacle);
    }

    /**
     * Every copy of a candidate arrangement as a placed cabinet, aimed exactly as the finished scene would aim it.
     *
     * The shared half of both objectives, and the reason either can be trusted: the solve measures the same
     * `orientationFor()` + `worldBox()` the geometry is finally built from, so the spacing it lands on is the
     * spacing that actually clears.
     *
     * @param list<PlacementCopy> $copies
     * @param array{float, float, float} $base
     * @param array{float, float, float}|null $target
     *
     * @return list<PlacedDevice>
     */
    private function placedFor(
        Placement $placement,
        DeviceSpec $device,
        array $copies,
        array $base,
        ?array $target,
        ?Orientation $hangAim,
        float $pitchDeg,
    ): array {
        // Flushed first, so the solve measures each copy where the placing loop will finally put it.
        $copies = $this->flushFront($placement, $device, $copies, $base, $target, $hangAim, $pitchDeg);
        $lift = GroupStack::zLift($device, $copies, $pitchDeg, $placement->rollDeg);

        $placed = [];
        foreach ($copies as $copy) {
            $position = [
                $base[0] + $copy->offset[0],
                $base[1] + $copy->offset[1],
                $base[2] + $copy->offset[2] + $lift,
            ];

            $orientation = $this->orientationFor($placement, $device, $copy, $position, $target, $hangAim);
            if (null === $orientation) {
                continue;
            }

            $placed[] = new PlacedDevice(
                '',
                $device,
                $position,
                $orientation,
                $copy->seated && null === $placement->fly,
            );
        }

        return $placed;
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

        if ($placement->group->decidesYaw() && 0.0 !== $placement->yawDeg) {
            $messages[] = sprintf('the %s already sets yaw — remove yaw_deg', $placement->group->kind());
        }
        if (null !== $placement->fly && null !== $placement->on) {
            // Both decide the same number, and there is no reading of the pair that is not a contradiction.
            $messages[] = 'use either `fly` or `on`, not both — they both decide the height';
        }
        if (null !== $placement->fly?->point && null === $placement->fly->pointOn($device)) {
            $names = array_map(
                static fn (\App\Spec\RiggingPoint $point): string => $point->id,
                $device->riggingPoints,
            );
            $messages[] = sprintf(
                "fly.point '%s' is not a rigging point of %s (%s)",
                $placement->fly->point,
                $device->id,
                [] === $names ? 'it has none' : 'has: '.implode(', ', $names),
            );
        }
        if (null !== $placement->fly?->point && !$device->flyable) {
            $messages[] = sprintf('fly.point names a point on %s, which is not flyable', $device->id);
        }
        if (null !== $placement->aimAt && null !== $placement->aimFocus) {
            $messages[] = 'use either `aim` or `aim_at`, not both';
        }
        if (
            (null !== $placement->aimAt || null !== $placement->aimFocus)
            && (0.0 !== $placement->yawDeg || 0.0 !== $placement->pitchDeg)
        ) {
            // A group's yaw does not live in `yaw_deg`, so this stays the same rule it always was.
            $messages[] = 'aiming already sets yaw and pitch — remove yaw_deg/pitch_deg';
        }
        if (null !== $placement->align) {
            $messages = [...$messages, ...$placement->align->problems($placement->group, $placement->copyCount())];
        }

        if ([] !== $messages) {
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
     * The device a placement stands, cranked to its `extend_to_m` when it states one, or why it cannot be.
     *
     * **Only a truss tower telescopes**, so a speaker or a truss segment given a height is a typo or a wish and is
     * refused rather than stretched. A tower can be cranked down and never above its spec, whose height is its
     * full extension. A tower drawn as a mast also stops at the height it collapses to.
     *
     * **A packed placement is the device as it travels**, {@see DeviceSpec::packed()}, which only a pack writes. A
     * stand cannot be folded and cranked at once, so the two together are refused.
     */
    public static function extended(Placement $placement, DeviceSpec $device): DeviceSpec|string
    {
        if ($placement->packed) {
            return null === $placement->extendToM
                ? $device->packed()
                : sprintf('packed and extend_to_m are two states of %s, so state one', $device->id);
        }

        $height = $placement->extendToM;
        if (null === $height) {
            return $device;
        }
        if (Category::Truss !== $device->category || 'tower' !== $device->subtype) {
            return sprintf('extend_to_m only applies to a truss tower, and %s is %s/%s', $device->id, $device->category->value, $device->subtype);
        }
        if (!is_finite($height) || $height <= 0.0 || $height > $device->dimensions->height + 1e-9) {
            return sprintf(
                'extend_to_m %.3f is outside what %s reaches, which is above 0 and at most %.3f m',
                $height,
                $device->id,
                $device->dimensions->height,
            );
        }
        // A wind-up stand cranks down until every stage is inside its sleeve, and no further.
        $collapsed = $device->mast?->collapsedHeightM();
        if (null !== $collapsed && $height < $collapsed - 1e-9) {
            return sprintf('extend_to_m %.3f is below the %.3f m %s cranks down to', $height, $collapsed, $device->id);
        }

        return $device->withHeight($height);
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
     *
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
            if (null === $device) {
                continue;
            }
            // A refused extension is reported by the placing loop. Here it only must not move the front.
            $extended = self::extended($placement, $device);
            $device = is_string($extended) ? $device : $extended;

            $base = $placement->at;
            if (null === $base && null !== $placement->on) {
                $base = $ground[$placement->on] ?? null;
            }
            if (null === $base) {
                continue;
            }

            $aimed = null !== $placement->aimAt || null !== $placement->aimFocus;
            $pitch = $aimed ? 0.0 : $placement->pitchDeg;
            $yaw = $aimed ? 0.0 : $placement->yawDeg;

            // A placement the compiler is going to reject contributes nothing, and skipping it here is
            // what lets `copies()` assume the geometry resolves.
            if ([] !== $this->validate($placement, $device, $pitch)) {
                continue;
            }

            // An `align` is not solved here, and it does not have to be. Both solved modes are symmetric
            // about `at`, so spreading a tier never moves the rig's centre line; `across` and `inside`
            // reach no further than the placement they are measured from, which this walk has already
            // counted; and `min y` — the only thing the focus distance is measured from — does not depend
            // on an x spacing at all, because aimed placements are evaluated here at yaw 0. A stated
            // `width_m` is the one envelope with no such backstop, so it is contributed outright.
            if (null !== $placement->align?->widthM) {
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
                    null === $copy->rotation ? $attitude : ($copy->rotation->after($attitude) ?? $attitude),
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

        if (INF === $minX) {
            return [0.0, 0.0];
        }

        return [($minX + $maxX) / 2, $minY];
    }

    /**
     * Where the first (or only) copy of a placement sits.
     *
     * @param array<string, PlacedDevice> $byId
     * @param callable(string):void $add
     *
     * @return array{float, float, float}|null
     */
    private function resolveBase(Placement $placement, DeviceSpec $device, array $byId, callable $add): ?array
    {
        if (null !== $placement->fly) {
            if (null === $placement->at) {
                $add("placement '{$placement->id}': `fly` needs `at` for the x and y it hangs over");

                return null;
            }

            return $placement->fly->slot($placement->at, $placement->fly->pointOn($device));
        }

        if (null === $placement->on) {
            if (null === $placement->at) {
                $add("placement '{$placement->id}': needs either `at`, `on` or `fly`");

                return null;
            }

            return [$placement->at[0], $placement->at[1], 0.0];
        }

        $support = $byId[$placement->on] ?? null;
        if (null === $support) {
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
