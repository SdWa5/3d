<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SharedTops;
use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The second pass of SWP-2 — which sub wall each pooled top ends up on.
 *
 * **What is pinned here is the rule's fairness rather than its optimality**, the same line
 * {@see \App\Tests\Load\PackLayoutTest} holds for the pack. The deal cannot prove a top fits, because pass two
 * re-solves each stack from a different inventory and the wall this measured may not be the wall it gets. What it can
 * promise is that the biggest boxes get the best walls, that no wall is starved while another piles up, that every
 * cabinet is dealt somewhere, and that two runs deal a tie the same way — which is the property the sweep's file
 * names depend on.
 */
final class SharedTopsTest extends TestCase
{
    /**
     * **The long throw goes to the widest wall and the fill to the narrow one**, which is the whole rule in one
     * case: a wide wall and a narrow one, two big tops and two small ones, and the big pair should not be split
     * across the narrow wall while the small pair sits on the wide one.
     */
    public function testTheWidestTopsTakeTheWidestWall(): void
    {
        $devices = [
            'long-throw' => self::top('long-throw', 0.60),
            'fill' => self::top('fill', 0.30),
        ];

        $dealt = SharedTops::deal(
            [self::wall('wide', 2.00), self::wall('narrow', 0.70)],
            ['long-throw' => 2, 'fill' => 2],
            $devices,
        );

        // **Both long throws on the wide wall and none on the narrow one**, which is the claim. The wide wall also
        // takes one of the two fills, and that is the rule rather than a leak: after two 0.60 m tops it still has
        // 0.80 m of unused face against the narrow wall's 0.70, so it is genuinely the emptier of the two. Asserting
        // `['long-throw' => 2]` outright fails on exactly that, which is the assertion being wrong and not the deal.
        self::assertSame(2, $dealt['wide']['long-throw']);
        self::assertArrayNotHasKey('long-throw', $dealt['narrow'], 'a long throw was squeezed onto the narrow wall');
        self::assertSame(['fill' => 1], $dealt['narrow']);
    }

    /**
     * **Cabinet by cabinet, so the tops spread instead of piling up.** Three identical tops over three walls of the
     * same width come out one each. Dealt per device type instead, all three would stand on one wall and the other
     * two would have nothing firing over the crowd.
     */
    public function testOneTypeIsSpreadAcrossEqualWalls(): void
    {
        $dealt = SharedTops::deal(
            [self::wall('a', 1.50), self::wall('b', 1.50), self::wall('c', 1.50)],
            ['top' => 3],
            ['top' => self::top('top', 0.50)],
        );

        self::assertSame(['a' => ['top' => 1], 'b' => ['top' => 1], 'c' => ['top' => 1]], $dealt);
    }

    /**
     * **A tie goes to the wall that comes first in the rig, and that has to be stable.** The sweep writes one file
     * per candidate and names it after the rig, so two runs that resolved a tie differently would produce two files
     * for one rig — and the deduplication would then report a duplicate that depends on nothing but iteration order.
     */
    public function testATieGoesToTheEarlierWall(): void
    {
        $walls = [self::wall('first', 1.00), self::wall('second', 1.00)];
        $devices = ['top' => self::top('top', 0.50)];

        $dealt = SharedTops::deal($walls, ['top' => 1], $devices);

        self::assertSame(['first' => ['top' => 1]], $dealt);
        self::assertSame($dealt, SharedTops::deal($walls, ['top' => 1], $devices), 'the deal is not deterministic');
    }

    /**
     * **Every top is dealt somewhere, even when no wall has room for it.** The budget goes negative rather than the
     * cabinet being held back, because a cabinet in no rig at all is the worse failure and the solver is the
     * backstop: it reports what it cannot carry as `LEFT OUT` instead of the deal silently swallowing it.
     */
    public function testATopIsDealtEvenWhereNoWallHasRoomLeft(): void
    {
        $dealt = SharedTops::deal(
            [self::wall('tiny', 0.20)],
            ['top' => 2],
            ['top' => self::top('top', 0.50)],
        );

        self::assertSame(['tiny' => ['top' => 2]], $dealt, 'a top with nowhere to go must still be offered a wall');
    }

    /**
     * Nothing to deal, or nowhere to deal it, is an empty answer rather than an error. Both are reachable: a rig of
     * subs alone has no pool, and `groups()` refuses an inventory with no subs before this is ever called.
     */
    public function testAnEmptyPoolOrNoWallsDealsNothing(): void
    {
        self::assertSame([], SharedTops::deal([self::wall('a', 1.0)], [], []));
        self::assertSame([], SharedTops::deal([], ['top' => 2], ['top' => self::top('top', 0.5)]));
    }

    /**
     * A wall whose top face is the given width — one row of one cabinet that wide, since
     * {@see StackBlock::topFaceWidthM} reads the topmost tier and nothing else here cares what is under it.
     */
    private static function wall(string $label, float $topFaceM): StackBlock
    {
        return new StackBlock(
            placementId: 'main-'.$label,
            label: $label,
            stack: new Stack(from: [], interfaceHeightM: 2.0, gapM: 0.0),
            tiers: [Tier::of(self::top('wall-'.$label, $topFaceM), 1)],
            from: [],
            warnings: [],
        );
    }

    private static function top(string $id, float $widthM): DeviceSpec
    {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => $id,
            'name' => $id,
            'subtype' => 'top',
            'quantity' => 4,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => $widthM, 'height' => 0.5, 'depth' => 0.4],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.0,
            ],
        ]), '/tmp/'.$id.'.yaml');
    }
}
