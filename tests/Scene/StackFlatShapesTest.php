<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\StackChecks;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/** Width objectives on unequal inventories, without an optional height ceiling. */
final class StackFlatShapesTest extends TestCase
{
    public function testATowerContinuesIntoTheNextTypeToCompleteItsLastRow(): void
    {
        $result = StackSolver::solve(
            [[$this->sub('wide', 1.0), 5], [$this->sub('narrow', 0.5), 2]],
            $this->stack(StackShape::Tower),
        );

        self::assertSame([], $result['problems']);
        self::assertCount(3, $result['tiers']);
        foreach ($result['tiers'] as $tier) {
            self::assertEqualsWithDelta(2.0, $tier->widthM(0.0), 1e-9);
        }
        self::assertSame(7, array_sum(array_map(static fn (Tier $tier): int => $tier->count(), $result['tiers'])));
        self::assertCount(3, $result['tiers'][2]->segments, 'the last wide cabinet has narrow cabinets on both sides');
    }

    public function testAMixedWallKeepsItsLowerHalfFlushAndTapersAboveIt(): void
    {
        $result = StackSolver::solve(
            [[$this->sub('wide', 1.0), 5], [$this->sub('narrow', 0.5), 1]],
            $this->stack(StackShape::Mixed),
        );

        self::assertSame([], $result['problems']);
        self::assertCount(3, $result['tiers']);
        $widths = array_map(static fn (Tier $tier): float => $tier->widthM(0.0), $result['tiers']);
        self::assertEqualsWithDelta($widths[0], $widths[1], 1e-9);
        self::assertLessThan($widths[1], $widths[2]);
        self::assertSame(6, array_sum(array_map(static fn (Tier $tier): int => $tier->count(), $result['tiers'])));
    }

    public function testAnUnreachableTowerTargetIsReportedWithoutLosingCabinets(): void
    {
        $result = StackSolver::solve(
            [[$this->sub('wide', 1.0), 4], [$this->sub('narrow', 0.5), 1]],
            $this->stack(StackShape::Tower),
        );

        self::assertNotEmpty($result['problems']);
        self::assertSame(5, array_sum(array_map(static fn (Tier $tier): int => $tier->count(), $result['tiers'])));
    }

    public function testAWidthCeilingDoesNotMakeAnIncompleteTowerRowFlush(): void
    {
        $device = $this->sub('wide', 1.0);
        $rows = [Tier::of($device, 3), Tier::of($device, 2)];
        self::assertStringContainsString('flush base', implode('\n', StackChecks::supportChecks($rows, $this->stack(StackShape::Tower))['problems']));
        self::assertSame([], StackChecks::supportChecks($rows, $this->stack(StackShape::Pyramid))['problems']);
    }

    public function testAMixedWallStillRejectsAWideningUpperRow(): void
    {
        $device = $this->sub('wide', 1.0);
        $rows = [Tier::of($device, 3), Tier::of($device, 3), Tier::of($device, 2), Tier::of($device, 3)];
        self::assertStringContainsString('shape: mixed', implode('\n', StackChecks::supportChecks($rows, $this->stack(StackShape::Mixed))['problems']));
    }

    public function testAnAllTopInventoryNeedsNoFlushSubBase(): void
    {
        foreach ([StackShape::Tower, StackShape::Mixed] as $shape) {
            $result = StackSolver::solve([[SpecFactory::spec(), 2]], $this->stack($shape));
            self::assertSame([], $result['problems']);
            self::assertCount(1, $result['tiers']);
            self::assertSame(2, $result['tiers'][0]->count());
        }
    }

    private function sub(string $id, float $width): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => $id,
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => $width, 'height' => 0.6, 'depth' => 0.6]],
        ]);
    }

    private function stack(StackShape $shape): Stack
    {
        return new Stack(from: [], maxWidthM: 2.0, interfaceHeightM: 0.0, gapM: 0.0, shape: $shape, targetSubHeightM: 1.8);
    }
}
