<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\StackEntry;
use App\Scene\StackSceneWriter;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Where the writer stands each stack of a rig, across and in depth (GEO-11, ALN-1).
 */
final class StackSceneWriterTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        $this->devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            $this->devices[$spec->id] = $spec;
        }
    }

    /** Unmeasured, a block covers its widest tier centred on its `at`, and the gap is between those widths. */
    public function testAnUnmeasuredBlockIsSpacedOnItsWidestTierCentred(): void
    {
        $flexys = $this->block('main-1', 'flexy-folded-horn-hybrid', 2);
        $achenbachs = $this->block('main-2', 'achenbach-18', 3);
        $width = $flexys->widthM();

        self::assertEqualsWithDelta([-$width / 2, $width / 2], $flexys->extentM(), 1e-9);

        $centres = StackSceneWriter::centres([$flexys, $achenbachs], 0.0, 0.5);
        $total = $width + 0.5 + $achenbachs->widthM();
        self::assertEqualsWithDelta(-$total / 2 + $width / 2, $centres[0], 1e-9);
        self::assertEqualsWithDelta($total / 2 - $achenbachs->widthM() / 2, $centres[1], 1e-9);
    }

    /**
     * **Measured, the clearance is the gap between where the cabinets stand.** A block whose tops reach 0.4 m further
     * out on its right than its widest tier does gets its neighbour moved by exactly that, and the rig stays centred
     * on its middle.
     */
    public function testAMeasuredBlockIsSpacedOnItsOwnEdges(): void
    {
        $left = $this->block('main-1', 'flexy-folded-horn-hybrid', 2);
        $right = $this->block('main-2', 'achenbach-18', 3);
        $half = $left->widthM() / 2;
        $measured = $left->withSpan(-$half, $half + 0.4);

        [$a, $b] = StackSceneWriter::centres([$measured, $right], 0.0, 0.5);
        [$nominalA, $nominalB] = StackSceneWriter::centres([$left, $right], 0.0, 0.5);

        $leftEdgeOfRight = $b + $right->extentM()[0];
        self::assertEqualsWithDelta(0.5, $leftEdgeOfRight - ($a + $half + 0.4), 1e-9);
        self::assertEqualsWithDelta(0.4, $b - $a - ($nominalB - $nominalA), 1e-9);
        self::assertEqualsWithDelta(0.0, ($a - $half + $b + $right->extentM()[1]) / 2, 1e-9);
    }

    /** A re-solve builds a new block, and that block carries no measurement of the rows it no longer has. */
    public function testAMeasurementBelongsToTheBlockItWasTakenOf(): void
    {
        $block = $this->block('main-1', 'flexy-folded-horn-hybrid', 2)->withSpan(-1.0, 2.0);

        self::assertSame([-1.0, 2.0], $block->extentM());
        self::assertNull($this->block('main-1', 'flexy-folded-horn-hybrid', 2)->spanM);
    }

    /**
     * **ALN-1: every stack's front stands on the deepest stack's front.** A Flexy is 0.964 m deep and an Achenbach
     * 0.700 m, so the Achenbach block stands 0.132 m further forward than the Flexy block, and the Flexy keeps `at`.
     */
    public function testEveryBlocksFrontStandsOnTheDeepestBlocksFront(): void
    {
        $flexys = $this->block('main-1', 'flexy-folded-horn-hybrid', 2);
        $achenbachs = $this->block('main-2', 'achenbach-18', 3);

        [$deep, $shallow] = StackSceneWriter::depths([$flexys, $achenbachs], 1.0);

        self::assertEqualsWithDelta(1.0, $deep, 1e-9);
        self::assertEqualsWithDelta(1.0 - 0.132, $shallow, 1e-9);
        self::assertEqualsWithDelta($deep - 0.964 / 2, $shallow - 0.700 / 2, 1e-9);
    }

    /** The y written into the file is the one {@see StackSceneWriter::depths} gives. */
    public function testTheWrittenSceneStandsEachBlockAtItsDepth(): void
    {
        $yaml = StackSceneWriter::yaml(
            id: 'aligned',
            name: 'aligned',
            blocks: [
                $this->block('main-1', 'flexy-folded-horn-hybrid', 2),
                $this->block('main-2', 'achenbach-18', 3),
            ],
            at: [0.0, 0.0],
            clearanceM: 0.5,
        );

        self::assertMatchesRegularExpression('/- id: main-1\n    at: \[-?[\d.]+, 0\.0\]/', $yaml);
        self::assertMatchesRegularExpression('/- id: main-2\n    at: \[-?[\d.]+, -0\.132\]/', $yaml);
    }

    private function block(string $id, string $device, int $count): StackBlock
    {
        return new StackBlock(
            placementId: $id,
            label: $id,
            stack: new Stack(from: [new StackEntry($device)], gapM: 0.02),
            tiers: [Tier::of($this->devices[$device], $count)],
            from: [$device],
            warnings: [],
        );
    }
}
