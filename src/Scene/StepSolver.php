<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Finds the one number that puts a tier's outer edges where the scene asked for them.
 *
 * The problem is a fixed point rather than a formula, and that is the whole reason this file exists.
 * Spreading an *aimed* row moves each cabinet outward; a cabinet further from the focus toes in further;
 * a further-toed-in cabinet's rotated box is wider than the cabinet is; so the outer edge moves by more
 * than the spacing did, by an amount that depends on where the cabinet ended up. `full-rig-stereo`'s
 * 2.1185 and 2.9709 are that fixed point, found by hand with a bisection somebody ran in their head.
 *
 * So this bisects it properly, and the objective is monotone with a margin worth writing down. Let `W(s)`
 * be the arrangement's x extent at parameter `s`, `n` the count, `R` the cabinet's plan half-diagonal at
 * its pitch, and `D` the distance to the target. The outer cabinet sits at `((n−1)/2)·s`, so its yaw moves
 * by at most `(n−1)|Δs| / (2D)`; `W` is a max over corners and so not differentiable, but it is Lipschitz
 * in the yaw with constant `R` per side, which gives
 *
 * ```
 * W(s₂) − W(s₁)  ≥  (n−1)(1 − R/D)(s₂ − s₁)
 * ```
 *
 * — strictly increasing whenever the target is further away than the cabinet is large. A Tecnare's `R` is
 * 0.391 m against a 10 m focus and an 18sound's is 0.401 m against a 2 m one, so the margin is two orders
 * of magnitude and nothing we own comes near the bound.
 *
 * The bracket is still **verified rather than assumed**. An aim close enough to turn a cabinet past
 * broadside would break the bound, and a solve that quietly returned the wrong root would be worse than one
 * that said it could not do it. Null is that admission, and {@see SceneCompiler::aligned} turns it into a
 * violation naming the width it could not reach.
 *
 * The span is measured by placing the copies for real — same orientation code, same
 * {@see PlacedDevice::worldBox} — so the solve and the geometry it is solving for cannot disagree. That
 * costs one full placement pass per iteration, which is nothing against a Blender run and is the only way
 * the answer stays right when a cabinet is measured.
 */
final class StepSolver
{
    /**
     * A micrometre. Cabinets are measured to the millimetre at best, so this is three orders finer than
     * anything the answer is ever compared against — the repo's usual tolerance for derived trig geometry.
     */
    public const TOLERANCE_M = 1e-6;

    /** Bisection halves the interval each time, so this is far more than convergence needs. */
    private const MAX_ITERATIONS = 200;

    /** Doubling from the natural spacing; 40 of them is a rig wider than the planet. */
    private const MAX_DOUBLINGS = 40;

    /**
     * The parameter at which the arrangement spans exactly `$targetM`, or null when there is none.
     *
     * `$spanAt` is the arrangement's x extent at a given parameter — a factor for `block`, a distance for
     * `stereo`. Both are zero at their tightest, which is why the lower end of the bracket is known
     * outright and only the upper end has to be searched for.
     *
     * Null means one of two things, and the caller can tell them apart by asking `$spanAt(0.0)` itself:
     * the envelope is narrower than the cabinets at their tightest, or the span never reaches it however
     * far the tier is spread — which is what a degenerate arrangement (every copy at the same x) looks
     * like from here.
     *
     * @param callable(float): float $spanAt
     */
    public static function solve(callable $spanAt, float $targetM, float $startT): ?float
    {
        $low = 0.0;
        if ($spanAt($low) > $targetM + self::TOLERANCE_M) {
            return null;
        }

        $high = max($startT, self::TOLERANCE_M);
        for ($doubling = 0; $spanAt($high) < $targetM; ++$doubling) {
            if ($doubling >= self::MAX_DOUBLINGS) {
                return null;
            }
            $high *= 2.0;
        }

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; ++$iteration) {
            $middle = ($low + $high) / 2.0;
            $span = $spanAt($middle);

            if (abs($span - $targetM) <= self::TOLERANCE_M) {
                return $middle;
            }
            if ($span < $targetM) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        // Bisection cannot fail to converge on a continuous objective, so arriving here means the span is
        // discontinuous in the parameter — say a copy that jumps columns. Refuse rather than return the
        // last guess.
        return null;
    }
}
