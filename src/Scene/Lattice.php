<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;

/**
 * A 1-, 2- or 3-D grid of whatever is nested inside it, spaced from that thing's own size.
 *
 * This is the group that stops a scene carrying arithmetic somebody did by hand. `full-rig.yaml` states
 * `at: [-2.135, 0.0]` and `step: [0.611, 0, 0]` for its sub row, and both are derived: seven 591 mm Flexys
 * with a 20 mm working gap make a 4.257 m wall, and centring that on `x = -0.302` puts its left edge at
 * −2.135. As a lattice it is `count: [7, 1, 1], gap_m: 0.02` on the wall's centre, and the numbers come
 * back out of the specs — so measuring a cabinet moves the row rather than invalidating the scene.
 *
 * What it replicates is not necessarily one cabinet. Nested inside another group it spaces itself on the
 * whole inner arrangement's extent, which is what makes `arc` in two tiers, or three 2×2 blocks in a row,
 * express themselves without anyone working out the block's width first. See {@see GroupStack}.
 *
 * Two asymmetries are deliberate, and both come from cabinets being things that stand on the floor:
 *
 * * **x and y are centred on `at`; z runs upward from it.** Centring x and y is what lets a single cabinet
 *   be swapped for a row of seven without moving, and one formula covers an odd count (a cell on `at`) and
 *   an even one (the gap between the middle two). z cannot be centred, because the base *is* the floor or
 *   the top of whatever the placement stands on, and centring a three-tier lattice would sink a tier
 *   through it.
 * * **The anchor is the middle cell in x and y but the *top* tier in z.** `on:` means "stand on top of
 *   that", and it reads the anchor's own top — so anchoring the bottom tier would bury the next placement
 *   inside the lattice.
 */
final class Lattice implements Group
{
    /** Cycled roll has to be a quarter turn; see {@see spanOf}. */
    private const CYCLE_STEP_DEG = 90.0;

    /**
     * @param array{int, int, int} $count cells along x, y and z
     * @param array{float, float, float} $gapM air between neighbours per axis
     * @param array{float, float, float} $stepM stated spacing per axis; 0 means "derive it"
     * @param list<float> $rollCycle roll applied to successive cells along $cycleAxis, repeating
     * @param float|null $rollMirror the quarter turn given to the cells past the middle, the ones before it
     *     getting its mirror image — see {@see mirroredOffsets}
     */
    public function __construct(
        public readonly array $count,
        public readonly array $gapM = [0.0, 0.0, 0.0],
        public readonly array $stepM = [0.0, 0.0, 0.0],
        public readonly array $rollCycle = [],
        public readonly ?Axis $cycleAxis = null,
        public readonly ?float $rollMirror = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        self::rejectUnknown(
            $reader,
            ['count', 'gap_m', 'step_m', 'roll_cycle', 'cycle_axis', 'roll_mirror'],
            'lattice',
        );

        return new self(
            count: self::readCount($reader),
            gapM: self::readGap($reader),
            stepM: self::readStep($reader),
            rollCycle: self::readCycle($reader),
            cycleAxis: $reader->has('cycle_axis') ? $reader->requireEnum('cycle_axis', Axis::class) : null,
            rollMirror: $reader->optionalFloat('roll_mirror'),
        );
    }

    /**
     * `row: { count: 7, axis: x }` — a lattice with one open axis, which is the case that dominates.
     *
     * Not a class of its own: it is the same geometry, and having the axis named outright is what makes
     * `roll_cycle` unambiguous without a second key.
     */
    public static function rowFromReader(ArrayReader $reader): self
    {
        self::rejectUnknown($reader, ['count', 'axis', 'gap_m', 'step_m', 'roll_cycle', 'roll_mirror'], 'row');

        $axis = $reader->has('axis') ? $reader->requireEnum('axis', Axis::class) : Axis::X;
        $count = [1, 1, 1];
        $count[$axis->index()] = $reader->requireInt('count');

        $gap = [0.0, 0.0, 0.0];
        $gap[$axis->index()] = $reader->optionalFloat('gap_m', 0.0) ?? 0.0;

        $step = [0.0, 0.0, 0.0];
        $step[$axis->index()] = $reader->optionalFloat('step_m', 0.0) ?? 0.0;

        /** @var array{int, int, int} $count */
        /** @var array{float, float, float} $gap */
        /** @var array{float, float, float} $step */
        return new self($count, $gap, $step, self::readCycle($reader), $axis, $reader->optionalFloat('roll_mirror'));
    }

    public function copies(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $step = $this->stepsFrom($cellBox);
        $axis = $this->resolvedCycleAxis();
        $numbered = array_values(array_filter([0, 1, 2], fn (int $a): bool => $this->count[$a] > 1));

        $anchor = [
            intdiv($this->count[0] - 1, 2),
            intdiv($this->count[1] - 1, 2),
            $this->count[2] - 1,
        ];

        $mirrored = $this->rollMirror === null || $axis === null
            ? null
            : $this->mirroredOffsets($cellBox, $axis);

        $copies = [];
        for ($ix = 0; $ix < $this->count[0]; ++$ix) {
            for ($iy = 0; $iy < $this->count[1]; ++$iy) {
                for ($iz = 0; $iz < $this->count[2]; ++$iz) {
                    $cell = [$ix, $iy, $iz];
                    $roll = $this->rollAt($cell, $axis);

                    $offset = [
                        ($ix - ($this->count[0] - 1) / 2) * $step[0],
                        ($iy - ($this->count[1] - 1) / 2) * $step[1],
                        $iz * $step[2],
                    ];
                    if ($mirrored !== null) {
                        $offset[$axis->index()] = $mirrored['offsets'][$cell[$axis->index()]];
                        $roll = $mirrored['rolls'][$cell[$axis->index()]];
                    }

                    $copies[] = new PlacementCopy(
                        array_map(static fn (int $a): int => $cell[$a] + 1, $numbered),
                        $offset,
                        $roll === 0.0 ? null : new Orientation(0.0, $roll, 0.0),
                        $cell === $anchor,
                    );
                }
            }
        }

        return $copies;
    }

    public function problems(DeviceSpec $device, float $pitchDeg, float $rollDeg, array $cellBox): array
    {
        $messages = [];
        $names = ['x', 'y', 'z'];

        foreach ([0, 1, 2] as $axis) {
            if ($this->count[$axis] < 1) {
                $messages[] = sprintf(
                    '%s.count must be at least 1 on every axis, got %s on %s',
                    $this->kind(),
                    $this->count[$axis],
                    $names[$axis],
                );
            }
            if ($this->gapM[$axis] < 0.0) {
                $messages[] = sprintf('%s.gap_m must not be negative, got %s on %s', $this->kind(), $this->gapM[$axis], $names[$axis]);
            }
            if ($this->stepM[$axis] < 0.0) {
                $messages[] = sprintf('%s.step_m must not be negative, got %s on %s', $this->kind(), $this->stepM[$axis], $names[$axis]);
            }
        }

        foreach ($this->rollCycle as $roll) {
            if (fmod(abs($roll), self::CYCLE_STEP_DEG) !== 0.0) {
                // The cell arrives as an axis-aligned box, and only a quarter turn can be applied to one
                // exactly. Anything else would need the cell's real hull and would silently over-space.
                $messages[] = sprintf(
                    '%s.roll_cycle must be quarter turns, got %s',
                    $this->kind(),
                    $roll,
                );
            }
        }

        if ($this->rollMirror !== null) {
            if (fmod(abs($this->rollMirror), self::CYCLE_STEP_DEG) !== 0.0
                || fmod(abs($this->rollMirror), 180.0) === 0.0) {
                // Only a quarter turn moves the body off to one side, which is what the mirror is made of;
                // 0 and 180 leave it centred and would mirror nothing.
                $messages[] = sprintf(
                    '%s.roll_mirror must be 90 or 270, got %s',
                    $this->kind(),
                    $this->rollMirror,
                );
            }
            if ($this->rollCycle !== []) {
                $messages[] = sprintf(
                    '%s cannot have both roll_cycle and roll_mirror — they both decide the same cells\' roll',
                    $this->kind(),
                );
            }
            if ($this->cycleAxis === null && count($this->openAxes()) > 1) {
                $messages[] = sprintf(
                    '%s.roll_mirror needs a cycle_axis when more than one axis has cells (%s)',
                    $this->kind(),
                    implode(' and ', array_map(static fn (int $a): string => $names[$a], $this->openAxes())),
                );
            }
            foreach ($this->openAxes() as $open) {
                if ($this->stepM[$open] !== 0.0) {
                    // The mirror derives its spacing from the bodies. A stated step would be applied to the
                    // origins, which is exactly the thing that opens the seam by a whole cabinet.
                    $messages[] = sprintf(
                        '%s.roll_mirror derives its own spacing, so step_m cannot be stated with it (%s)',
                        $this->kind(),
                        $names[$open],
                    );
                }
            }
        }

        if ($this->rollCycle !== [] && $this->cycleAxis === null && count($this->openAxes()) > 1) {
            // Same line `mode` draws on an arc: a cycle running down x instead of z on a seven-by-two wall
            // turns every column over instead of every tier, which is a fourteen-cabinet mistake that
            // renders perfectly plausibly.
            $messages[] = sprintf(
                '%s.roll_cycle needs a cycle_axis when more than one axis has cells (%s)',
                $this->kind(),
                implode(' and ', array_map(static fn (int $a): string => $names[$a], $this->openAxes())),
            );
        }

        return $messages;
    }

    public function copyCount(): int
    {
        return $this->count[0] * $this->count[1] * $this->count[2];
    }

    public function kind(): string
    {
        return 'lattice';
    }

    /** A lattice moves cells without turning them, so a placement's own `yaw_deg` turns the whole grid. */
    /** A lattice moves cells without tilting them. */
    public function decidesPitch(): bool
    {
        return false;
    }

    public function decidesYaw(): bool
    {
        return false;
    }

    /**
     * Spacing per axis: stated, or the cell's own extent plus the gap.
     *
     * A stated step of zero is the sentinel for "derive this axis", which is safe because a step of zero is
     * meaningless for a count above one — it would stack every cell in the same place.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return array{float, float, float}
     */
    private function stepsFrom(array $cellBox): array
    {
        $span = $this->spanOf($cellBox);

        $steps = [0.0, 0.0, 0.0];
        foreach ([0, 1, 2] as $axis) {
            $steps[$axis] = $this->stepM[$axis] !== 0.0
                ? $this->stepM[$axis]
                : $span[$axis] + $this->gapM[$axis];
        }

        /** @var array{float, float, float} $steps */
        return $steps;
    }

    /**
     * How much room one cell takes up, allowing for every attitude the roll cycle puts it in.
     *
     * Spacing stays **uniform**, on the widest of them, rather than solved per joint. Per-joint spacing
     * would make a cell's position depend on its neighbours while the anchor sits in the middle, so
     * changing the cycle would move whatever stacks on the placement; it would stop the grid being a grid
     * for the camera framing and for the swap-a-single-for-a-group property; and it would break the reason
     * a cycled row works at all, which is that a centred row is symmetric about its own axis and so lands
     * back where it was when the cycle turns it over. The cost is confined to mixing quarter turns with
     * half turns, which is not a real setup: `[0, 180]` and `[90, 270]` both leave the extent unchanged.
     *
     * A quarter turn about the cabinet's front-to-back axis swaps the box's width and height and leaves its
     * depth alone, which is why the cycle is restricted to quarter turns — an axis-aligned box can be
     * turned by one exactly.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return array{float, float, float}
     */
    private function spanOf(array $cellBox): array
    {
        $span = [
            $cellBox['max'][0] - $cellBox['min'][0],
            $cellBox['max'][1] - $cellBox['min'][1],
            $cellBox['max'][2] - $cellBox['min'][2],
        ];

        foreach ($this->rollCycle as $roll) {
            if (fmod(abs($roll), 180.0) !== 0.0) {
                $span = [max($span[0], $span[2]), $span[1], max($span[2], $span[0])];
            }
        }

        /** @var array{float, float, float} $span */
        return $span;
    }

    /**
     * The roll this cell gets from the cycle, or 0 when there is none.
     *
     * @param array{int, int, int} $cell
     */
    private function rollAt(array $cell, ?Axis $axis): float
    {
        if ($this->rollCycle === [] || $axis === null) {
            return 0.0;
        }

        return $this->rollCycle[$cell[$axis->index()] % count($this->rollCycle)];
    }

    /**
     * A **mirrored** row: the cells past the middle rolled one way, the ones before it the other.
     *
     * Bodies are laid out, not origins, and that is the whole of it. Rolling a quarter turn does not merely
     * swap a cabinet's width and height — geometry runs from its bottom-centre, so the body ends up entirely
     * to one side of the origin it was measured from: to the **right at 90**, to the **left at 270**. Step
     * origins uniformly across a mirrored row and the seam opens by a whole cabinet while nothing else moves,
     * because the two cells either side of it fall away from each other and need only the gap. Lay the bodies
     * at a uniform pitch instead and put each origin wherever its own body requires, and the seam falls out
     * with nothing stated.
     *
     * That is what {@see \App\Scene\Lattice::$rollCycle} cannot do, and why `full-rig-quarter-turned.yaml`
     * has to state `step_m: 0.02` by hand and warn that a derived gap "drives adjacent cabinets 591 mm into
     * each other". Here the spacing is derivable, so it is derived.
     *
     * **The envelope is unchanged**, which is the useful part: within a half two same-rolled bodies need
     * `W + gap` and at the seam they need `gap`, so the row still measures `n·W + (n−1)·gap` — the same as
     * the alternating pair pattern, and the same as any other row of `n` cells `W` wide. Only the handedness
     * differs.
     *
     * An **odd** count cannot be mirrored exactly. `intdiv(n, 2)` cells go on the left and the remaining
     * `n - intdiv(n, 2)` on the right, so the middle cabinet joins the right half; picking a side beats
     * refusing, and the row is then lopsided by one cabinet.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return array{offsets: list<float>, rolls: list<float>}
     */
    private function mirroredOffsets(array $cellBox, Axis $axis): array
    {
        $count = $this->count[$axis->index()];
        $right = self::normalisedRoll((float)$this->rollMirror);
        $left = self::normalisedRoll(360.0 - $right);
        $gap = $this->gapM[$axis->index()];

        // Both halves are quarter turns of the same box, so both bodies are the same width.
        $width = $cellBox['max'][2] - $cellBox['min'][2];
        $pitch = $width + $gap;
        $first = -($count * $width + max(0, $count - 1) * $gap) / 2;

        $offsets = [];
        $rolls = [];
        for ($cell = 0; $cell < $count; ++$cell) {
            $roll = $cell < intdiv($count, 2) ? $left : $right;
            $span = self::rolledSpan($cellBox, $roll);

            // The body's centre, then the origin that puts it there.
            $centre = $first + $cell * $pitch + $width / 2;
            $offsets[] = $centre - ($span[0] + $span[1]) / 2;
            $rolls[] = $roll;
        }

        return ['offsets' => $offsets, 'rolls' => $rolls];
    }

    /**
     * Where a cell's body lands along the mirror axis once rolled, relative to its own origin.
     *
     * Rolling about the front-to-back axis maps `(x, y, z) → (z, y, −x)` at 90 and `(−z, y, x)` at 270, so the
     * span along x comes out of the cell's *height*. For a cabinet with its origin at bottom-centre that is
     * `[0, h]` one way and `[−h, 0]` the other, which is the measured fact this whole layout rests on.
     *
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $cellBox
     * @return array{float, float}
     */
    private static function rolledSpan(array $cellBox, float $roll): array
    {
        if (fmod(abs($roll), 180.0) === 0.0) {
            return [$cellBox['min'][0], $cellBox['max'][0]];
        }

        return self::normalisedRoll($roll) === 90.0
            ? [$cellBox['min'][2], $cellBox['max'][2]]
            : [-$cellBox['max'][2], -$cellBox['min'][2]];
    }

    /** Any roll brought into 0..360, so 90 and −270 are the same turn. */
    private static function normalisedRoll(float $roll): float
    {
        return fmod(fmod($roll, 360.0) + 360.0, 360.0);
    }

    /**
     * Where the cycle runs when it was not named: the only axis that has more than one cell. With none or
     * several, `problems()` has already refused rather than guessed.
     */
    private function resolvedCycleAxis(): ?Axis
    {
        if ($this->cycleAxis !== null) {
            return $this->cycleAxis;
        }
        $open = $this->openAxes();

        return count($open) === 1 ? Axis::from(['x', 'y', 'z'][$open[0]]) : null;
    }

    /**
     * @return list<int>
     */
    private function openAxes(): array
    {
        return array_values(array_filter([0, 1, 2], fn (int $axis): bool => $this->count[$axis] > 1));
    }

    /**
     * @param list<string> $allowed
     */
    private static function rejectUnknown(ArrayReader $reader, array $allowed, string $kind): void
    {
        $unknown = $reader->unknownKeys($allowed);
        if ($unknown !== []) {
            throw new InvalidSpecException(sprintf(
                "%s: unknown key '%s' (allowed: %s)",
                $kind,
                $unknown[0],
                implode(', ', $allowed),
            ));
        }
    }

    /**
     * `count: [nx, ny, nz]`, with trailing axes defaulting to one — so `[7]` is a row and `[2, 1, 3]` is a
     * block. A list rather than a mapping, so `count`, `gap_m`, `step_m` and `at` all read in x, y, z order,
     * and the ones say "flat here" rather than being absent.
     *
     * @return array{int, int, int}
     */
    private static function readCount(ArrayReader $reader): array
    {
        $raw = $reader->numberList('count');
        if ($raw === [] || count($raw) > 3) {
            throw new InvalidSpecException('lattice.count: expected 1 to 3 numbers, as [x], [x, y] or [x, y, z]');
        }

        $count = [1, 1, 1];
        foreach ($raw as $axis => $value) {
            if ($value !== floor($value)) {
                throw new InvalidSpecException("lattice.count: expected whole numbers, got {$value}");
            }
            $count[$axis] = (int)$value;
        }

        /** @var array{int, int, int} $count */
        return $count;
    }

    /**
     * `gap_m: 0.02` is 20 mm on x and y only; `gap_m: [0.02, 0, 0.10]` names all three.
     *
     * The scalar deliberately leaves z alone, and that is the real decision here: a gap on x is air *beside*
     * a cabinet, which is normal, while a gap on z is air *under* one, which nobody wants by accident.
     * Somebody writing `gap_m: 0.02` for the side joints of a two-tier wall would otherwise float the upper
     * row. Air under a cabinet has to be asked for by name.
     *
     * @return array{float, float, float}
     */
    private static function readGap(ArrayReader $reader): array
    {
        if (!$reader->has('gap_m')) {
            return [0.0, 0.0, 0.0];
        }
        if (!$reader->isList('gap_m')) {
            $gap = $reader->requireFloat('gap_m');

            return [$gap, $gap, 0.0];
        }

        return $reader->requireVector3('gap_m');
    }

    /**
     * @return array{float, float, float}
     */
    private static function readStep(ArrayReader $reader): array
    {
        if (!$reader->has('step_m')) {
            return [0.0, 0.0, 0.0];
        }

        return $reader->requireVector3('step_m');
    }

    /**
     * @return list<float>
     */
    private static function readCycle(ArrayReader $reader): array
    {
        return $reader->has('roll_cycle') ? $reader->numberList('roll_cycle') : [];
    }
}
