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
    ) {
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

    public function cabinets(): int
    {
        $count = 0;
        foreach ($this->tiers as $tier) {
            $count += $tier->count();
        }

        return $count;
    }

    public function describe(): string
    {
        return sprintf(
            '%s, %d cabinets in %d %s',
            $this->label === '' ? 'the rig' : $this->label,
            $this->cabinets(),
            count($this->tiers),
            count($this->tiers) === 1 ? 'row' : 'rows',
        );
    }
}
