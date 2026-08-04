<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\Coverage;
use PHPUnit\Framework\TestCase;

/**
 * The one part of a coverage cone that can be checked without opening Blender.
 */
final class CoverageTest extends TestCase
{
    public function testTheSpreadAtADistanceIsPlainTrigonometry(): void
    {
        // The Tecnare's stated pattern: 60° across, 40° high.
        [$width, $height] = (new Coverage(60.0, 40.0))->spreadAt(10.0);

        self::assertEqualsWithDelta(11.547005, $width, 1e-6);
        self::assertEqualsWithDelta(7.279404, $height, 1e-6);
    }

    /**
     * A 90° pattern is the readable case: at any distance it is exactly twice as wide as it is far, because
     * the half-angle is 45°.
     */
    public function testANinetyDegreePatternIsAsWideAsTwiceItsDistance(): void
    {
        self::assertEqualsWithDelta(20.0, (new Coverage(90.0, 90.0))->spreadAt(10.0)[0], 1e-9);
        self::assertEqualsWithDelta(4.0, (new Coverage(90.0, 90.0))->spreadAt(2.0)[1], 1e-9);
    }

    public function testItGrowsInProportionToTheDistance(): void
    {
        $near = (new Coverage(60.0, 40.0))->spreadAt(5.0);
        $far = (new Coverage(60.0, 40.0))->spreadAt(20.0);

        self::assertEqualsWithDelta($near[0] * 4, $far[0], 1e-9);
        self::assertEqualsWithDelta($near[1] * 4, $far[1], 1e-9);
    }

    public function testAtTheBaffleThePatternHasNoWidthAtAll(): void
    {
        // Which is what makes the cone's apex an apex.
        self::assertSame([0.0, 0.0], (new Coverage(60.0, 40.0))->spreadAt(0.0));
    }
}
