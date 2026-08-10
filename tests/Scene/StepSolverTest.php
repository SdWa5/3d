<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\StepSolver;
use PHPUnit\Framework\TestCase;

/**
 * The bisection on its own, away from any geometry.
 *
 * The solver's contract is narrower than "find a root": it has to refuse rather than guess, because a
 * spacing that is quietly wrong renders perfectly plausibly and a scene is a commit. Every test here is
 * about one of the two refusals or about the answer being the same twice.
 */
final class StepSolverTest extends TestCase
{
    public function testTheSolvedParameterPutsTheSpanOnTheTarget(): void
    {
        // A straight row's span really is affine in the parameter: 0.6 m of cabinet plus 1.8 m of spread.
        $span = static fn (float $t): float => 0.6 + 1.8 * $t;

        $solved = StepSolver::solve($span, 4.0, 1.0);

        self::assertNotNull($solved);
        self::assertEqualsWithDelta(4.0, $span($solved), StepSolver::TOLERANCE_M);
    }

    /**
     * The non-linear case, which is what an aimed row is: spreading it turns each cabinet further, so the
     * span grows faster than the spacing does. Bisection does not care, and this pins that it does not.
     */
    public function testASpanThatGrowsFasterThanItsParameterIsStillSolved(): void
    {
        $span = static fn (float $t): float => 0.5 + 1.4 * $t + 0.35 * $t ** 2;

        $solved = StepSolver::solve($span, 6.0, 1.0);

        self::assertNotNull($solved);
        self::assertEqualsWithDelta(6.0, $span($solved), StepSolver::TOLERANCE_M);
    }

    /**
     * The one genuinely unachievable case. By monotonicity the tightest an arrangement can be is every
     * cabinet on `at`, so a target below that is not a hard solve — it is a scene asking for a tier
     * narrower than the cabinets in it.
     */
    public function testATargetNarrowerThanTheTightestArrangementIsRefused(): void
    {
        $span = static fn (float $t): float => 1.0 + 2.0 * $t;

        self::assertNull(StepSolver::solve($span, 0.5, 1.0));
    }

    /**
     * A degenerate arrangement — every copy at the same x, so spreading it changes nothing — would
     * otherwise double the bracket until it overflowed. It has to come back as "no".
     */
    public function testASpanThatNeverGrowsIsRefusedRatherThanSearchedForever(): void
    {
        $span = static fn (float $t): float => 1.0;

        self::assertNull(StepSolver::solve($span, 5.0, 1.0));
    }

    /**
     * A solved step lands in a committed build plan, so two runs of the same scene have to produce the same
     * bytes. A fixed bracket and a fixed halving rule give that; a relative tolerance or a secant step would
     * not, because their iterate sequence depends on where they started.
     */
    public function testTheAnswerIsIdenticalOnEveryRunSoACommittedPlanDoesNotDrift(): void
    {
        $span = static fn (float $t): float => 0.4656 + 1.1 * $t + 0.2 * $t ** 2;

        self::assertSame(
            StepSolver::solve($span, 3.6275, 1.0),
            StepSolver::solve($span, 3.6275, 1.0),
        );
    }

    /**
     * `stereo` measures a distance rather than a factor, so its natural starting point is zero — and a
     * doubling search seeded at zero would never move. The solver's floor exists for exactly that.
     */
    public function testAParameterStartingAtZeroStillFindsItsBracket(): void
    {
        $span = static fn (float $t): float => 1.2 + 2.0 * $t;

        $solved = StepSolver::solve($span, 4.0, 0.0);

        self::assertNotNull($solved);
        self::assertEqualsWithDelta(1.4, $solved, StepSolver::TOLERANCE_M);
    }
}
