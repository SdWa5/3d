<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Which way an arc of cabinets bends.
 *
 * The whole difference between the two is one sign, applied in two places — which side of the arc the
 * centre of curvature sits on, and which way each cabinet turns — so it is worth having as an enum with
 * that sign on it rather than as a boolean called something like `$outward`.
 */
enum ArcMode: string
{
    /** Fronts fan outward, centre of curvature behind the cabinets, back edges touching. */
    case Convex = 'convex';

    /** Fronts fan inward, centre of curvature in front, front edges touching. */
    case Concave = 'concave';

    /**
     * +1 convex, −1 concave.
     *
     * A cabinet's own +y points backwards, so this is what turns "distance from the centre of
     * curvature" into a single expression: a vertex at local y sits `radius − sign * y` from the centre.
     * Convex puts the front further out, concave puts it nearer.
     */
    public function sign(): float
    {
        return $this === self::Convex ? 1.0 : -1.0;
    }
}
