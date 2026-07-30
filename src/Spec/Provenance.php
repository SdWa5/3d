<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Where a spec's numbers come from. This is what keeps the library honest: datasheet and
 * plans values are good enough to design setups with immediately, estimated ones are visibly
 * marked in the model and flagged in the catalog, and measuring a box promotes it to
 * `Measured` without any other change.
 */
enum Provenance: string
{
    case Datasheet = 'datasheet';
    case Plans = 'plans';
    case Measured = 'measured';
    case Estimated = 'estimated';

    /**
     * Whether the numbers describe the actual built cabinet rather than the design it was
     * copied from. Everything else is a candidate for the measuring backlog.
     */
    public function isMeasured(): bool
    {
        return $this === self::Measured;
    }
}
