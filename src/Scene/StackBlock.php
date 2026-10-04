<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * One solved stack on its way to a scene file — the unit a rig-per-owner is made of.
 *
 * A generated scene used to be one stack, so the writer took a `Stack` and its tiers as loose arguments.
 * Grouping by owner makes it a *list* of stacks standing side by side, and the loose arguments stopped being
 * able to say which tiers belonged to which. So they are gathered here, together with the two things only the
 * generator knows: what the block should be called, and what it had to leave out.
 */
final class StackBlock
{
    /**
     * @param list<Tier> $tiers the solve
     * @param list<string> $from device ids in this stack, low frequency first
     * @param list<string> $warnings what the solver had to say about it
     * @param array<string, string> $omitted device id => why it is not in this stack
     * @param array{float, float}|null $spanM the compiled stack's left and right edge relative to its `at`, null until
     *                                        a compile has measured it. See {@see extentM}
     */
    public function __construct(
        public readonly string $placementId,
        public readonly string $label,
        public readonly Stack $stack,
        public readonly array $tiers,
        public readonly array $from,
        public readonly array $warnings,
        public readonly array $omitted = [],
        public readonly ?LayoutMode $align = null,
        public readonly ?array $spanM = null,
    ) {
    }

    /**
     * The same block with its compiled extent measured, so the writer spaces it on where its cabinets stand.
     *
     * A re-solve builds a new block without one, because new tiers stand somewhere else and the old measurement no
     * longer describes them.
     */
    public function withSpan(float $leftM, float $rightM): self
    {
        return new self(
            $this->placementId,
            $this->label,
            $this->stack,
            $this->tiers,
            $this->from,
            $this->warnings,
            $this->omitted,
            $this->align,
            [$leftM, $rightM],
        );
    }

    /**
     * How far this stack reaches to the left and to the right of its `at`, which is what a neighbour has to clear.
     *
     * **Measured where a compile has measured it, nominal until then** (GEO-11). The nominal figure is the widest tier
     * centred on `at`, and it is wrong in both directions once the stack is built. Aimed tops turn their corners out,
     * `clear_of` pushes a cluster past the subs and a row may stand off centre, so on 2026-10-04 1441 of 3669
     * multi-stack rigs stood up to 664 mm closer than `--clearance` and 39 up to 7.85 m further apart.
     *
     * @return array{float, float} left edge (negative) and right edge, relative to `at`
     */
    public function extentM(): array
    {
        if (null !== $this->spanM) {
            return $this->spanM;
        }

        $half = $this->widthM() / 2;

        return [-$half, $half];
    }

    /**
     * How many of each device this stack actually stands up, counted off the solve.
     *
     * The scene file needs this written into it, and that is not a nicety. A `stack:` block carries constraints
     * and a device list, and the compiler **re-solves it on every build** — against the spec's own `quantity`.
     * So a rig split across two stacks reported one split in its header comment and then built with *every*
     * stack holding the whole inventory: two 3.6 m walls 0.5 m apart, 561 mm inside each other. Writing the
     * share as `count:` is what makes the file mean what the header says.
     *
     * Counted from the tiers rather than from the share the generator dealt out, because the two can differ:
     * a device the solver could not carry is simply not in the tiers, and reproducing the solve means
     * reproducing that too.
     *
     * @return array<string, int> device id => cabinets in this stack
     */
    public function counts(): array
    {
        $counts = [];
        foreach ($this->tiers as $tier) {
            foreach ($tier->segments as [$device, $count]) {
                $counts[$device->id] = ($counts[$device->id] ?? 0) + $count;
            }
        }

        return $counts;
    }

    /**
     * The spec `quantity` of each device this stack places, so the writer can tell a share from the whole lot
     * and keep the shorthand where nothing was split.
     *
     * @return array<string, int>
     */
    public function owned(): array
    {
        $owned = [];
        foreach ($this->tiers as $tier) {
            foreach ($tier->segments as [$device, $count]) {
                $owned[$device->id] = $device->quantity;
            }
        }

        return $owned;
    }

    /**
     * How much floor this stack covers — its widest tier, which is what neighbouring stacks have to clear.
     */
    public function widthM(): float
    {
        $widest = 0.0;
        foreach ($this->tiers as $tier) {
            $widest = max($widest, $tier->widthM($this->stack->gapM));
        }

        return $widest;
    }

    /**
     * Whether anything stands on the sub wall — false for a **sub wing**, which is an ordinary thing to build.
     *
     * Asked because two messages are wrong without it. Both the file's own header and the sweep's console note report
     * a wall that falls short of its `interface_height_m`, and both used to explain it as tops firing below head
     * height. On a stack with no tops that sentence is simply false, and staying silent instead is no better: the
     * header prints the height against the interface either way, so an unexplained miss reads as a solver bug.
     */
    public function hasTops(): bool
    {
        // The shared row of a mirrored pair stands on this wall without being one of its tiers.
        if ($this->stack->sharedTops) {
            return true;
        }
        foreach ($this->tiers as $tier) {
            if (!$tier->isSub()) {
                return true;
            }
        }

        return false;
    }

    /**
     * How wide the **top** of this stack is, which is a different question from {@see widthM} and is asked by
     * exactly one caller.
     *
     * `widthM()` is the widest tier anywhere in the stack, because that is what a neighbouring stack has to clear.
     * What something standing *on* the stack has to fit is the topmost tier alone, and the two differ by a lot on any
     * rig that is not a column: a pyramid's base is its widest row and its top face is its narrowest. Used by
     * {@see SharedTops} to decide which sub wall has room for another top.
     *
     * Zero for a stack with no tiers, which is not a real stack and is worth answering rather than crashing on.
     */
    public function topFaceWidthM(): float
    {
        $top = [] === $this->tiers ? null : $this->tiers[count($this->tiers) - 1];

        return $top?->widthM($this->stack->gapM) ?? 0.0;
    }

    public function cabinets(): int
    {
        $count = 0;
        foreach ($this->tiers as $tier) {
            $count += $tier->count();
        }

        return $count;
    }

    /**
     * How high the sub stack's top face reaches — where the tops start, and the number every complaint about a rig
     * being "too high" is actually about.
     *
     * Here rather than worked out again at each call site, because three of them want it and they must agree: the
     * writer prints it in the header, {@see \App\Command\SceneStackCommand} breaks a tie between two arrangements on
     * it, and the block ordering decides which stack goes in the middle by it. Three copies of one sum is how a
     * header ends up disagreeing with the rig it describes.
     */
    public function subHeightM(): float
    {
        $height = 0.0;
        foreach ($this->tiers as $tier) {
            if ($tier->isSub()) {
                $height += $tier->heightM();
            }
        }

        return $height;
    }

    /** The whole pile, tops included — what a truss has to clear. */
    public function heightM(): float
    {
        $height = 0.0;
        foreach ($this->tiers as $tier) {
            $height += $tier->heightM();
        }

        return $height;
    }

    public function describe(): string
    {
        return sprintf(
            '%s, %d cabinets in %d %s',
            '' === $this->label ? 'the rig' : $this->label,
            $this->cabinets(),
            count($this->tiers),
            1 === count($this->tiers) ? 'row' : 'rows',
        );
    }
}
