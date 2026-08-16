<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * One step of {@see StackSolver::fill}'s search: how wide a row may be, or how many cabinets it may hold.
 *
 * **TWO DIMENSIONS BECAUSE NEITHER ONE CONTAINS THE OTHER**, which was measured rather than assumed. GEO-12 replaced
 * a cabinet count with a width for a good reason: one integer applied to every device at once meant `perRow: 7` was
 * seven Flexys at 4.3 m *and* seven mid-bass at 8.5 m, and no setting of it expressed "as many of each as fit 4.40 m",
 * which is 7 Flexys and 3 mid-bass. Our cabinets run 0.45 m to 1.200 m, so a count stopped standing in for a width the
 * day `gmss-mid-bass` arrived.
 *
 * **But deleting the count cost 49 rigs**, and that is the same shape of loss CVR-8 caused by deleting the width
 * ladder: a mechanism built for one reason turning out to be load-bearing for a second nobody wrote down. A count says
 * "the same number of every type", a width says "the same metres of every type", and an arrangement like
 * `2× gmss-nuke + 1× gmss-mid-bass` is reachable from the first and from no value of the second. Every one of the 49
 * was refused on bearing rather than on the search running out, so they were arrangements the search could no longer
 * propose at all.
 *
 * So the ladder walks both, as a **union rather than a product**: every width with the seats unbounded, then every
 * count with the width unbounded. Additive keeps the search a few times longer where a product would square it, and
 * runtime is explicitly not a constraint on this project either way.
 *
 * **A count is turned into a width per device**, which is what makes one parameter enough for both. The width of
 * exactly `n` cabinets of a device admits exactly `n` of them, so capping the width there caps the count identically,
 * and every call site can go on asking the one question it already asks.
 */
final class RowBudget
{
    /**
     * @param float|null $widthM how wide a row may be, or null for no width bound at all
     * @param int $seats how many cabinets a row may hold, or `PHP_INT_MAX` for no count bound at all
     */
    public function __construct(
        public readonly ?float $widthM = null,
        public readonly int $seats = PHP_INT_MAX,
    ) {
    }

    /**
     * Neither bound, which is the step the search always tries first so that nothing is bounded by it.
     */
    public static function unbounded(): self
    {
        return new self();
    }

    /**
     * The tighter of two width bounds, where **null means no bound at all** on either side.
     *
     * Here rather than in {@see StackSolver} because this class is where the null convention is defined, and it has to
     * be the same everywhere: {@see StackSolver::perTier} reads null as unbounded and `INF` as a trap. Writing
     * `min($a, $b)` by hand at each site is what would put an `INF` in eventually.
     */
    public static function narrower(?float $a, ?float $b): ?float
    {
        if ($a === null) {
            return $b;
        }

        return $b === null ? $a : min($a, $b);
    }

    /**
     * The same budget with one more width bound folded into it, for a bound that applies to a single row.
     *
     * The pyramid's hint is the case this exists for. It is a width like the search's own, it varies per row rather
     * than per pass, and folding it in here keeps every call site asking the one question it already asks instead of
     * carrying a second bound alongside.
     */
    public function narrowedTo(?float $widthM): self
    {
        return new self(self::narrower($this->widthM, $widthM), $this->seats);
    }

    /**
     * This budget as a width for one device, or null when it bounds that device not at all.
     *
     * Null rather than `INF` for "no bound", which is what {@see StackSolver::perTier} understands. Handing it `INF`
     * casts to `(int)floor(INF)` in there, which is undefined in PHP and came out as a row of one — every tier a
     * pillar, from a stack with no stated width at all.
     */
    public function ceilingFor(DeviceSpec $device, Stack $stack, float $roll): ?float
    {
        if ($this->seats === PHP_INT_MAX) {
            return $this->widthM;
        }

        $bySeats = Tier::of($device, $this->seats, $roll)->widthM($stack->gapM);

        return $this->widthM === null ? $bySeats : min($this->widthM, $bySeats);
    }
}
