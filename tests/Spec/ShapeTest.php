<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\Shape;
use PHPUnit\Framework\TestCase;

/**
 * Which shapes are loudspeaker cabinets, which is the question the whole build branches on.
 *
 * Worth its own test because getting it wrong is silent and expensive in one direction: a new shape that fell
 * through as a cabinet would be given a grille, handle recesses, a chamfer, drivers behind a baffle and a coverage
 * cone, and it would *build* — Blender would cut handles into a truss and nobody would see it until a render. The
 * enum's `match` has no default arm precisely so PHP refuses to compile a shape nobody has classified, and this
 * pins the classification itself.
 */
final class ShapeTest extends TestCase
{
    public function testExactlyTheHexahedraAreCabinets(): void
    {
        $cabinets = array_values(array_filter(
            Shape::cases(),
            static fn (Shape $shape): bool => $shape->isCabinet(),
        ));

        self::assertSame([Shape::Box, Shape::Trapezoid, Shape::Wedge], $cabinets);
    }

    /**
     * The open-frame shapes, named individually so adding one without deciding what it is fails here rather than
     * in a render three commands later.
     */
    public function testTheOpenFrameShapesAreNotCabinets(): void
    {
        foreach ([Shape::Truss, Shape::MovingHead, Shape::Scaffold] as $shape) {
            self::assertFalse($shape->isCabinet(), "{$shape->value} is not a cabinet");
        }
    }

    /**
     * Every shape is classified one way or the other. `isCabinet()`'s match has no default, so an unclassified case
     * throws `UnhandledMatchError` — this is what turns that into a named failure.
     */
    public function testEveryShapeIsClassified(): void
    {
        foreach (Shape::cases() as $shape) {
            $shape->isCabinet();
        }

        self::assertCount(6, Shape::cases(), 'a new shape needs a decision in isCabinet() and a builder');
    }

    /**
     * The string values are part of the plan the bpy side reads, and `build_model.py`'s dispatch table keys are
     * these exact strings. A rename here is a silent break there.
     */
    public function testTheValuesAreWhatTheBuilderDispatchesOn(): void
    {
        self::assertSame('truss', Shape::Truss->value);
        self::assertSame('moving-head', Shape::MovingHead->value);
        self::assertSame('scaffold', Shape::Scaffold->value);
    }
}
