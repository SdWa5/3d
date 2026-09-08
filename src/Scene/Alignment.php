<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;

/**
 * Where a tier's cabinets land across the width it is given.
 *
 * This exists because **an aimed cabinet's outer edge cannot be computed from its width.** Aiming toes a
 * cabinet in, a toed-in cabinet occupies more x than it is wide, and how far it toes in depends on where it
 * ended up — so "put this tier's edges on that one's" is a fixed point, not a formula. Every attempt to do
 * it as arithmetic has been wrong in a way that renders perfectly plausibly: `full-rig-all-tops` spaced two
 * aimed fills on their half-widths and drove them 88 mm into each other, and another put its fills
 * 0.41 m inside the sub wall. `full-rig-stereo` got it right only by carrying three numbers somebody
 * bisected by hand (0.8156, 2.1185, 2.9709), every one of which goes stale the moment a cabinet is measured
 * or a focus moves.
 *
 * It is worse than "the width is the wrong number", too. A Tecnare is a trapezoid, so once it is toed in
 * its outermost point is its *back* bottom corner rather than a front one, and at `full-rig-stereo`'s
 * 11.43° that corner sits 0.2206 m off centre against the 0.250 m half-width the arithmetic would use. The
 * naive answer is wrong in both directions depending on the cabinet.
 *
 * So the scene states the intent — "justified across the sub wall", "between the outer tops with 20 mm to
 * spare" — and the compiler solves for the spacing against the same exact rotated boxes it already uses for
 * contact, camera framing and the build report. See {@see StepSolver} for the solve and
 * {@see SceneCompiler::aligned} for where the envelope comes from.
 *
 * The transform is deliberately **one scalar** for both solved modes, which is what lets them share a
 * one-dimensional solver:
 *
 * * `block` scales every copy's x offset by `k`. A row's offsets are `(i − (n−1)/2) · step`, so scaling x
 *   *is* changing the step.
 * * `stereo` translates the copies left of `at` by `−d` and those right of it by `+d`, leaving each
 *   column's internal spacing alone. The sign of a copy's natural offset *is* the column split, so a count
 *   of four gives two columns of two and a count of five leaves the middle cabinet on `at`. That
 *   non-uniformity is why `stereo` could not have been expressed by writing a step back into the group.
 *
 * Both are symmetric about `at`, which matters more than it looks: it is why the rig's centre line does not
 * move when a tier is spread, and so why {@see SceneCompiler::frontCentre} can keep resolving the focus
 * before any alignment is solved without the two chasing each other.
 */
final class Alignment
{
    /** Below this a copy counts as sitting on the centre line rather than in either column. */
    private const CENTRE_EPSILON_M = 1e-9;

    public function __construct(
        public readonly LayoutMode $mode,
        /** The envelope stated outright — a stage or a truss, measured rather than referred to. */
        public readonly ?float $widthM = null,
        /** An earlier placement whose own x extent is the envelope. */
        public readonly ?string $across = null,
        /**
         * An earlier placement whose *inner* faces are the envelope — the free span between its outermost
         * cabinets, which is a different object from its extent and needs its own word. `full-rig-stereo`'s
         * tops span 4.678 m across and 3.628 m inside, and it is the second number a fill goes between.
         */
        public readonly ?string $inside = null,
        /**
         * An earlier placement to sit **outboard of** — the third thing a fill can be measured against, and the
         * one `align` could not express. `across` and `inside` are both *widths* my cabinets have to span;
         * `outside` is a *clearance* they have to keep, on the far side of somebody else's outer faces.
         *
         * `full-rig-arc` carried the gap as a comment for exactly as long: "the fills have to clear the arc's
         * outer faces, and `align` can measure a placement's extent (`across`) or the gap between its outermost
         * cabinets (`inside`) but not the room outboard of it. 2.60 puts them about 20 mm clear." With this,
         * `inset_m: 0.020` says the 20 mm and the solve finds the 2.60.
         */
        public readonly ?string $outside = null,
        /**
         * An earlier placement whose **cabinets** these must not come within `inset_m` of — a collision clearance
         * rather than an envelope.
         *
         * **The difference from {@see $outside} is not a refinement, it is a different question**, and conflating
         * them broke both. `outside` asks to be past somebody's outer faces, so the reference collapses to an x
         * span and a cabinet must clear the whole of it. That is right for a hand-written envelope and wrong for a
         * fill standing beside the long throw, because two tops aimed at one focus take different yaws and their
         * spans overlap long before the cabinets do: a toed-in trapezoid's outermost point is a back bottom corner
         * that swings *behind* its neighbour. So a span measure demands air that was never needed, and in a chain of
         * fills it compounds — a 2-way yawed 29.4° presents a 0.8523 m span on a 0.5 m body, and the turbo top
         * clearing it was pushed 514 mm off the run that had given it its height, leaving it hanging 228 mm over a
         * step.
         *
         * Measured on the shells by {@see Interpenetration::gapBetween}, which is a minimum over pairs — and that is
         * exactly why it cannot replace `outside`: a cabinet can satisfy it while sitting in a *gap* between two of
         * the reference's cabinets, nested inside the span it was told to stay out of.
         */
        public readonly ?string $clearOf = null,
        /** Taken off the envelope on **each** side — "20 mm inside the outer tops". Or, with `outside` or
         * `clear_of`, the clearance to keep from it. */
        public readonly float $insetM = 0.0,
        /**
         * Which way a cabinet **on the centre line** is pushed: `-1` left, `+1` right, `0` to work it out from
         * where the cabinet already sits.
         *
         * Only ever needed for a lone cabinet, and only under `outside`. Every other case derives its side from
         * the sign of the copy's own offset — that *is* the column split, which is why a row of four gives two
         * columns of two without anybody counting. A single copy sits at offset 0, so there is no sign to read
         * and the arrangement cannot say which way "outboard" is. A stack knows: its fill is the segment beside
         * the long throw, and which side of it is a fact about the tier.
         */
        public readonly float $side = 0.0,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $allowed = ['mode', 'width_m', 'across', 'inside', 'outside', 'clear_of', 'inset_m', 'side'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("align: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        return new self(
            mode: $reader->requireEnum('mode', LayoutMode::class),
            widthM: $reader->optionalFloat('width_m'),
            across: $reader->optionalString('across'),
            inside: $reader->optionalString('inside'),
            outside: $reader->optionalString('outside'),
            clearOf: $reader->optionalString('clear_of'),
            insetM: $reader->optionalFloat('inset_m', 0.0) ?? 0.0,
            side: match ($reader->optionalString('side')) {
                'left' => -1.0,
                'right' => 1.0,
                null => 0.0,
                default => throw new InvalidSpecException(sprintf("align.side: expected 'left' or 'right', got '%s'", (string) $reader->optionalString('side'))),
            },
        );
    }

    /**
     * The placement whose geometry states the envelope, or null when it is stated as a number.
     */
    public function reference(): ?string
    {
        return $this->across ?? $this->inside ?? $this->outside ?? $this->clearOf;
    }

    /**
     * Whether the solve is against a **clearance** rather than a width.
     *
     * The two objectives are genuinely different questions, which is why this is a flag and not another number:
     * `across`/`inside`/`width_m` all ask "how wide should my cabinets come out", and `outside` and `clear_of` ask
     * "how much air should there be between me and that". Only the second kind can be satisfied by a lone cabinet,
     * and only the first has a tightest case worth reporting.
     *
     * Both clearance keys count. They differ in *what* they measure against — a span for `outside`, the cabinets
     * themselves for `clear_of`, see {@see $clearOf} — and not in being a clearance, so every rule that turns on
     * "is this a clearance solve" applies to both.
     */
    public function isClearance(): bool
    {
        return null !== $this->outside || null !== $this->clearOf;
    }

    /**
     * This alignment with `$placementId` as its envelope, unless one was already stated.
     *
     * A {@see Stack} writes its own tiers, so an `align` on the stack means "every tier after the bottom
     * one lines up with the bottom one" — and the bottom one's id is not something the scene author can
     * know, because the stack invents it. Naming an envelope explicitly still wins.
     */
    public function orAcross(string $placementId): self
    {
        if ($this->hasEnvelope()) {
            return $this;
        }

        // Named arguments deliberately. Positionally, adding `clearOf` ahead of `insetM` silently slid the inset into
        // the new parameter and `side` into the inset, which every alignment test then failed on at once.
        return new self(
            mode: $this->mode,
            across: $placementId,
            insetM: $this->insetM,
            side: $this->side,
        );
    }

    /**
     * This alignment with a stated width as its envelope, unless one was already given.
     *
     * What a {@see Stack} falls back to when its bottom row is **mixed**: that row is several placements, so
     * there is no single id for `across` to name, and naming one segment would size the envelope from a pair
     * of Flexys instead of the whole 3.684 m row. The row's nominal width is exact here because a bottom row
     * is unaimed subs, whose rotated extent is their extent.
     */
    public function orWidth(float $widthM): self
    {
        if ($this->hasEnvelope()) {
            return $this;
        }

        return new self(
            mode: $this->mode,
            widthM: $widthM,
            insetM: $this->insetM,
            side: $this->side,
        );
    }

    private function hasEnvelope(): bool
    {
        return null !== $this->widthM || null !== $this->across || null !== $this->inside
            || null !== $this->outside || null !== $this->clearOf;
    }

    /**
     * Everything wrong with this `align` block for this placement, without the `placement '<id>': ` prefix
     * the compiler adds. Whether the reference names something that exists is not decided here — that needs
     * the earlier placements already resolved, so {@see SceneCompiler::aligned} asks it.
     *
     * @return list<string>
     */
    public function problems(GroupStack $group, int $copyCount): array
    {
        $messages = [];
        $sources = [$this->widthM, $this->across, $this->inside, $this->outside, $this->clearOf];
        $stated = count(array_filter($sources, static fn (mixed $v): bool => null !== $v));

        if ($stated > 1) {
            $messages[] = 'use one of align.width_m, align.across, align.inside, align.outside or align.clear_of, '
                .'not two — they all state what the tier is solved against';
        }
        if ($this->mode->isSolved() && 0 === $stated) {
            $messages[] = sprintf(
                "align.mode '%s' needs something to solve against — state width_m, across, inside, outside or "
                .'clear_of',
                $this->mode->value,
            );
        }
        if (!$this->mode->isSolved() && ($stated > 0 || 0.0 !== $this->insetM)) {
            // Silently ignoring them would make `center` look like it had been given a width and obeyed it.
            $messages[] = "align.mode 'center' is the natural spacing and has nothing to solve "
                .'— remove width_m/across/inside/outside/clear_of/inset_m, or ask for block';
        }
        if (null !== $this->widthM && $this->widthM <= 0.0) {
            $messages[] = sprintf('align.width_m must be positive, got %s', $this->widthM);
        }
        if ($this->insetM < 0.0) {
            $messages[] = sprintf('align.inset_m must not be negative, got %s', $this->insetM);
        }

        if (!$this->mode->isSolved() || [] !== $messages) {
            return $messages;
        }

        return array_merge($messages, $this->groupProblems($group, $copyCount));
    }

    /**
     * The copies moved to the parameter `$t`, whatever that parameter means for this mode.
     *
     * @param list<PlacementCopy> $copies
     *
     * @return list<PlacementCopy>
     */
    public function apply(array $copies, float $t): array
    {
        if (!$this->mode->isSolved()) {
            return $copies;
        }

        return array_map(
            fn (PlacementCopy $copy): PlacementCopy => $copy->movedInX(
                LayoutMode::Block === $this->mode
                    ? $copy->offset[0] * $t
                    : $copy->offset[0] + $this->columnOf($copy->offset[0]) * $t,
            ),
            $copies,
        );
    }

    /**
     * Where the doubling search starts looking for a bracket.
     *
     * `block`'s parameter is a factor, so 1 is the arrangement as the group made it; `stereo`'s is a
     * distance, so 0 is. Both are also the tightest either mode may legitimately be, which is what
     * {@see minParameter} is for.
     */
    public function startParameter(): float
    {
        return LayoutMode::Block === $this->mode ? 1.0 : 0.0;
    }

    /**
     * The tightest parameter this mode may be solved to — **the arrangement's own spacing, in both modes.**.
     *
     * This used to be 0 for both, on the reasoning that 0 is "every cabinet on `at`" and so the tightest anything
     * could be. That is true of `block`, whose parameter multiplies each copy's offset, and it is exactly the bug:
     * a factor below 1 pulls the copies *into each other*, below the working gap the group already left between
     * them. Measured on `stacked-gmss-1-block`, a tops row of three aimed turbo tops is 1.406 m across and the nuke
     * row carrying it is 1.200 m, so the solver found the factor that makes the span 1.200 — pitch 470 mm squeezed
     * to about 390 — and neighbouring cabinets ended up **92 mm inside each other**. Every interpenetration refusal
     * in the sweep was this, and every one was a `-block` variant.
     *
     * For `stereo` the old bound was harmless but described wrongly: its parameter is a distance *added* to each
     * column's offset, so 0 leaves the arrangement exactly as the group made it rather than stacking it on one spot.
     * Both modes therefore have the same floor, their own natural spacing, and neither can be asked to go below it.
     *
     * A row already wider than its envelope at this parameter has nothing to justify. That is not an error and
     * {@see SceneCompiler::aligned} says so with a warning, because the honest answer is the spacing the solver
     * already chose.
     */
    public function minParameter(): float
    {
        return $this->startParameter();
    }

    /**
     * Whether the group this is attached to can be spread at all.
     *
     * The rule is deliberately narrow: **one** `row` or `lattice`, nothing nested. Scaling the x offsets of
     * a *nested* arrangement would scale the inner one's spacing along with the outer one's — the 20 mm of
     * air inside `full-rig-stereo`'s rolled Flexy pairs would stretch with the wall — and telling the two
     * apart needs the level named, which is a key nothing shipped would use yet. With a single level,
     * scaling x is exactly changing that level's `step_m`, which is the whole claim this class rests on.
     *
     * @return list<string>
     */
    private function groupProblems(GroupStack $group, int $copyCount): array
    {
        $levels = $group->groups;

        if (1 !== count($levels) || !$levels[0] instanceof Lattice) {
            // An arc's spacing is its radius and a hang's is its splay — neither is a step to solve. A
            // `repeat` runs from `at` rather than about it, so neither the column split nor the symmetry
            // the focus resolution leans on would hold.
            return [sprintf(
                'align needs a single row or lattice and nothing nested in it, got %s',
                [] === $levels ? 'one cabinet' : $group->kind().(count($levels) > 1 ? ' inside another group' : ''),
            )];
        }

        $lattice = $levels[0];
        $messages = [];

        if ($lattice->count[0] < 2 && !$this->isClearance()) {
            // Under `outside` one cabinet is the ordinary case: there is no spacing to solve between cabinets,
            // only air to keep beyond something else, and a lone fill beside a long throw is exactly that.
            $messages[] = 'align needs more than one cabinet across x to space';
        }
        if ($lattice->count[0] < 2 && $this->isClearance() && 0.0 === $this->side) {
            $messages[] = sprintf(
                "align.%s on a single cabinet needs align.side: 'left' or 'right' — a cabinet on the centre line "
                .'has no sign to say which way out is',
                null !== $this->clearOf ? 'clear_of' : 'outside',
            );
        }
        if (0.0 !== $lattice->stepM[0]) {
            // Only `step_m`. `gap_m` stays legal: it is the natural spacing the solve starts from, it is
            // what `stereo` keeps within a column, and under `block` it simply cancels — a pure scale onto
            // a stated width does not depend on where it started.
            $messages[] = sprintf('align solves the spacing — remove %s.step_m', $lattice->kind());
        }

        return $messages;
    }

    /**
     * Which side of `at` a copy is on: −1 left, +1 right, 0 on the centre line.
     *
     * For `stereo` this *is* the column split, and it falls out of the natural offsets rather than being
     * counted: a row of four has two copies each side, a row of five has two each side and one on zero.
     */
    private function columnOf(float $x): float
    {
        if (abs($x) >= self::CENTRE_EPSILON_M) {
            return $x < 0.0 ? -1.0 : 1.0;
        }

        // On the centre line, so there is no sign to read — a stated side is the only thing that can say which
        // way out is, and without one the cabinet stays where it is.
        return $this->side;
    }
}
