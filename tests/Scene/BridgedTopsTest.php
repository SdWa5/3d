<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\BridgedTops;
use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * SYM-3, one tops row standing on both walls of a pair at an equal pitch.
 *
 * Every case is a pair of one-cabinet walls, because what is pinned here is the row's geometry against two top faces
 * and not the wall solve. Each asserts measured positions rather than a yes or no, so a row that comes back in the
 * wrong place fails as loudly as one that does not come back.
 */
final class BridgedTopsTest extends TestCase
{
    /**
     * **An odd row puts its middle top over the gap, and the walls close in until it is carried.** Three 0.5 m tops
     * on two 1.0 m walls stated 0.5 m apart. The middle top bears a third of its width only once the gap is at most
     * 0.333 m, so the walls close in by 5 mm steps to 0.330 m and the row spreads to their outer edges at one pitch.
     */
    public function testAnOddRowClosesTheWallsInUntilItsMiddleTopIsCarried(): void
    {
        $bridge = BridgedTops::solve(self::wall('a', 1.0), self::wall('b', 1.0), Tier::of(self::top(0.5), 3), 0.5);

        self::assertNotNull($bridge);
        self::assertEqualsWithDelta(0.33, $bridge->clearanceM, 1e-9);
        // Walls at ±0.665, outer edges at ±1.165, so the end tops stand flush at ±0.915.
        self::assertEqualsWithDelta([-0.915, 0.0, 0.915], array_column($bridge->seats, 'x'), 1e-9);
        self::assertEqualsWithDelta(0.915, $bridge->pitchM, 1e-9);
        self::assertSame([0, 0, 1], array_column($bridge->seats, 'wall'));
        self::assertSame(
            'top-0.5-0.5-0.4 + top-0.5-0.5-0.4 + top-0.5-0.5-0.4, at an equal pitch of 0.915 m',
            $bridge->describe(),
        );
    }

    /**
     * **An even row stands on the walls and leaves the stated clearance alone**, because nothing hangs over the gap
     * and the walls have no reason to move. Two tops spread to the outer edges, one on each wall.
     */
    public function testAnEvenRowSpreadsToTheOuterEdgesAtTheStatedClearance(): void
    {
        $bridge = BridgedTops::solve(self::wall('a', 1.0), self::wall('b', 1.0), Tier::of(self::top(0.5), 2), 0.5);

        self::assertNotNull($bridge);
        self::assertEqualsWithDelta(0.5, $bridge->clearanceM, 1e-9);
        self::assertEqualsWithDelta([-1.0, 1.0], array_column($bridge->seats, 'x'), 1e-9);
        self::assertSame([0, 1], array_column($bridge->seats, 'wall'));
    }

    /**
     * **A row too long for the pair moves the walls apart by what its tightest pitch lacks, and no further.** Four
     * 0.5 m tops at a 0.02 m gap need 2.06 m. Two 0.9 m walls 0.1 m apart offer 1.9 m, so they move 0.16 m apart
     * and the row stands at its floor pitch of 0.52 m, the widest adjacent pair plus the gap.
     */
    public function testALongRowMovesTheWallsApartByWhatItLacks(): void
    {
        $bridge = BridgedTops::solve(self::wall('a', 0.9), self::wall('b', 0.9), Tier::of(self::top(0.5), 4), 0.1);

        self::assertNotNull($bridge);
        self::assertEqualsWithDelta(0.26, $bridge->clearanceM, 1e-9);
        self::assertEqualsWithDelta(0.52, $bridge->pitchM, 1e-9);
        self::assertEqualsWithDelta([-0.78, -0.26, 0.26, 0.78], array_column($bridge->seats, 'x'), 1e-9);
    }

    /**
     * **Two walls of different heights carry no shared row**, which is why the command asks only of a pair out of
     * one pool. The top over the lower wall would land 0.3 m below the others, and the row is refused rather than
     * written with a top hanging in the air.
     */
    public function testWallsOfDifferentHeightsCarryNoRow(): void
    {
        self::assertNull(BridgedTops::solve(
            self::wall('a', 1.0, 0.6),
            self::wall('b', 1.0, 0.9),
            Tier::of(self::top(0.5), 2),
            0.5,
        ));
    }

    /**
     * **A gap no top can bridge refuses the row.** One 0.3 m top over two walls whose working gap is 0.25 m bears
     * 0.05 m, a sixth of its width, wherever the walls stand, so there is nothing to close in to.
     */
    public function testAGapTheTopCannotBridgeRefusesTheRow(): void
    {
        $bridge = BridgedTops::solve(
            self::wall('a', 1.0, 0.6, 0.25),
            self::wall('b', 1.0, 0.6, 0.25),
            Tier::of(self::top(0.3), 1),
            0.5,
        );

        self::assertNull($bridge);
    }

    /**
     * **A top stands flush with the front of the deepest wall cabinet**, the same plane a stack stands its own tops
     * on, and aims where the command says its type aims.
     */
    public function testATopStandsFlushWithTheWallFrontAndAimsAsItsTypeDoes(): void
    {
        $bridge = BridgedTops::solve(
            self::wall('a', 1.0),
            self::wall('b', 1.0),
            Tier::of(self::top(0.5), 2),
            0.5,
            ['top-0.5-0.5-0.4'],
        );

        self::assertNotNull($bridge);
        // Walls 0.8 m deep and tops 0.4 m deep, so each top stands 0.2 m behind the wall's centre line.
        self::assertEqualsWithDelta([-0.2, -0.2], array_column($bridge->seats, 'y'), 1e-9);
        self::assertSame(['near', 'near'], array_column($bridge->seats, 'aim'));
    }

    private static function wall(string $label, float $widthM, float $heightM = 0.6, float $gapM = 0.02): StackBlock
    {
        return new StackBlock(
            placementId: 'main-'.$label,
            label: $label,
            stack: new Stack(from: [], gapM: $gapM, sharedTops: true),
            tiers: [Tier::of(self::box('wall-'.$label, 'sub', $widthM, $heightM, 0.8), 1)],
            from: [],
            warnings: [],
        );
    }

    private static function top(float $widthM): DeviceSpec
    {
        return self::box('top', 'top', $widthM, 0.5, 0.4);
    }

    /**
     * A box named after its size as well as its role, because rolled extents are cached by device id and two sizes
     * under one id would read each other's.
     */
    private static function box(string $role, string $subtype, float $widthM, float $heightM, float $depthM): DeviceSpec
    {
        $id = sprintf('%s-%s-%s-%s', $role, $widthM, $heightM, $depthM);

        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'subtype' => $subtype,
            'quantity' => 4,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => $widthM, 'height' => $heightM, 'depth' => $depthM],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.0,
            ],
        ]), '/tmp/'.$id.'.yaml');
    }
}
