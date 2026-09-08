<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * How a tier is distributed across the width it is given — the alignment vocabulary text has, applied to
 * cabinets.
 *
 * A group decides *what* is in a tier and how many; this decides *where across the width* they end up. The
 * two are worth separating because they fail differently: a count that does not fit is arithmetic, while a
 * tier whose outer edges have to land on another tier's is a fixed point (see {@see Alignment}).
 */
enum LayoutMode: string
{
    /** Natural spacing, centred on `at`. What every `row` and `lattice` did before this existed. */
    case Center = 'center';

    /** Justified: spread with equal gaps until the outer edges land on the stated width. */
    case Block = 'block';

    /**
     * Two columns pushed apart until they reach the stated width, natural spacing kept within each.
     *
     * The centre is occupied only when leaving it empty would be asymmetric — an odd count has one cabinet
     * left over and it stays on `at`. An even count splits exactly in half and the middle stays open.
     */
    case Stereo = 'stereo';

    /**
     * Whether this mode has a spacing to solve at all.
     *
     * `center` is the identity, which is why it is the default and why stating an envelope alongside it is
     * a contradiction rather than something to ignore.
     */
    public function isSolved(): bool
    {
        return self::Center !== $this;
    }
}
