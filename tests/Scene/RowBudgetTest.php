<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\RowBudget;
use App\Scene\Stack;
use App\Scene\StackEntry;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The one search step {@see \App\Scene\StackSolver::fill} walks, and the conversion that makes it one parameter.
 */
final class RowBudgetTest extends TestCase
{
    /**
     * **Null means no bound at all, on either side and in the answer.**.
     *
     * Worth its own test because the convention is load-bearing and easy to get wrong in the other direction:
     * {@see \App\Scene\StackSolver::perTier} reads null as unbounded and `INF` as a trap, since `(int)floor(INF)` is
     * undefined in PHP and came out as a row of one — every tier a pillar, from a stack with no stated width at all.
     */
    public function testNullIsNoBoundRatherThanAZeroWidthOne(): void
    {
        self::assertNull(RowBudget::narrower(null, null));
        self::assertSame(2.5, RowBudget::narrower(null, 2.5));
        self::assertSame(2.5, RowBudget::narrower(2.5, null));
        self::assertSame(2.5, RowBudget::narrower(2.5, 4.0));

        self::assertNull(RowBudget::unbounded()->ceilingFor($this->device(), $this->stack(), 0.0));
    }

    /**
     * **A seat count becomes the width that admits exactly that many of this device**, which is what lets one
     * parameter carry both halves of the search.
     *
     * `n` cabinets and `n − 1` gaps, so four 0.8 m cabinets at a 0.02 m gap are `4 × 0.8 + 3 × 0.02`. Asserted on the
     * arithmetic rather than by round-tripping through `perTier()`, because the point is that the two agree and a test
     * that used one to check the other would agree with itself.
     */
    public function testASeatCountIsTheWidthOfExactlyThatManyCabinets(): void
    {
        $budget = new RowBudget(null, 4);

        self::assertEqualsWithDelta(
            4 * 0.8 + 3 * 0.02,
            (float) $budget->ceilingFor($this->device(), $this->stack(), 0.0),
            1e-9,
        );
    }

    /**
     * Stating both bounds takes the tighter, which is how the pyramid's per-row hint folds into a search step.
     */
    public function testStatingBothTakesTheTighterOfThem(): void
    {
        $device = $this->device();
        $stack = $this->stack();

        // Four seats are 3.26 m of cabinet, so a 2.0 m width is the binding half.
        self::assertEqualsWithDelta(2.0, (float) (new RowBudget(2.0, 4))->ceilingFor($device, $stack, 0.0), 1e-9);

        // And the other way round, where the seats bind first.
        self::assertEqualsWithDelta(
            4 * 0.8 + 3 * 0.02,
            (float) (new RowBudget(9.0, 4))->ceilingFor($device, $stack, 0.0),
            1e-9,
        );

        self::assertEqualsWithDelta(1.5, (float) RowBudget::unbounded()->narrowedTo(1.5)->widthM, 1e-9);
        self::assertSame(4, (new RowBudget(9.0, 4))->narrowedTo(1.5)->seats, 'narrowing a width leaves the seats alone');
    }

    private function device(): \App\Spec\DeviceSpec
    {
        return SpecFactory::spec(['geometry' => [
            'shape' => 'box',
            'dimensions_m' => ['width' => 0.8, 'height' => 0.6, 'depth' => 0.45],
            'origin' => 'bottom-center',
            'chamfer_m' => 0.01,
        ]]);
    }

    private function stack(): Stack
    {
        return new Stack(
            from: [new StackEntry('top-a')],
            maxWidthM: null,
            interfaceHeightM: 0.0,
            gapM: 0.02,
        );
    }
}
