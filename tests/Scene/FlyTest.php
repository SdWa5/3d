<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Fly;
use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * Where a hung placement's slot ends up.
 *
 * The fixture is a 0.96 m tall cabinet with one rigging point on its top face, which is the case that makes
 * the subtraction worth having: a point 0.96 m up its own cabinet, hung at 6 m, leaves the cabinet's slot at
 * 5.04 m — not at 6.
 */
final class FlyTest extends TestCase
{
    public function testWithoutANamedPointTheHeightIsTheSlot(): void
    {
        // The honest reading of "no hardware named": the cabinet's own bottom-centre hangs there.
        $slot = (new Fly(6.0))->slot([-3.0, 1.0], null);

        self::assertSame([-3.0, 1.0, 6.0], $slot);
    }

    /**
     * The point of naming a point. A cabinet does not hang from its bottom-centre; it hangs from hardware
     * somewhere on its shell, and `rigging.points` already says where. Naming one lets the scene state the
     * thing that is true — *that* point is at 6 m — and the slot is worked out from it.
     */
    public function testANamedPointIsWhatSitsAtTheStatedHeight(): void
    {
        $device = $this->device();
        $fly = new Fly(6.0, 'top-left');

        $point = $fly->pointOn($device);
        self::assertNotNull($point);
        self::assertSame([-0.185, -0.100, 0.960], $point->position);

        // Every axis comes off, not just z: the point is 0.185 m left of centre, so hanging it over x = -3
        // puts the cabinet's own centre-line at -2.815.
        self::assertSame([-2.815, 0.1, 5.04], $fly->slot([-3.0, 0.0], $point));
    }

    public function testAPointIsFoundByIdAndAnUnknownOneIsNull(): void
    {
        $device = $this->device();

        self::assertNotNull((new Fly(6.0, 'top-left'))->pointOn($device));
        self::assertNull((new Fly(6.0, 'top-middle'))->pointOn($device));
        self::assertNull((new Fly(6.0))->pointOn($device), 'no name means no point');
    }

    /**
     * Two hangs off one bar have to add up in the report, so the label is separable from the placement.
     */
    public function testTheLabelDefaultsToThePlacementIdAndCanBeShared(): void
    {
        self::assertSame('hang-left', (new Fly(6.0))->label('hang-left'));
        self::assertSame('main-bar', (new Fly(6.0, id: 'main-bar'))->label('hang-left'));
    }

    public function testReadingTakesTheHeightAndTheOptionalNames(): void
    {
        $fly = Fly::fromReader(new ArrayReader([
            'height_m' => 6.0,
            'point' => 'top-left',
            'id' => 'main-bar',
        ]));

        self::assertSame(6.0, $fly->heightM);
        self::assertSame('top-left', $fly->point);
        self::assertSame('main-bar', $fly->id);
    }

    public function testTheHeightIsRequired(): void
    {
        $this->expectException(InvalidSpecException::class);

        Fly::fromReader(new ArrayReader(['point' => 'top-left']));
    }

    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("fly: unknown key 'height'");

        Fly::fromReader(new ArrayReader(['height_m' => 6.0, 'height' => 5.0]));
    }

    /**
     * A negative height is not refused here — under a stage is a real place to fly something from, and the
     * check that matters is whether the cabinets end up through the floor, which only the compiler can see.
     */
    public function testAHeightBelowZeroIsReadWithoutComplaint(): void
    {
        self::assertSame(-1.5, Fly::fromReader(new ArrayReader(['height_m' => -1.5]))->heightM);
    }

    private function device(): DeviceSpec
    {
        return SpecFactory::spec([
            'id' => 'fly-top',
            'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 0.96, 'depth' => 0.52]],
            'rigging' => [
                'flyable' => true,
                'points' => [
                    ['id' => 'top-left', 'position_m' => [-0.185, -0.100, 0.960]],
                    ['id' => 'top-right', 'position_m' => [0.185, -0.100, 0.960]],
                ],
            ],
        ]);
    }
}
