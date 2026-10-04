<?php

declare(strict_types=1);

namespace App\Tests\Render;

use App\Render\RenderPlacement;
use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Spec\Category;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The record a placed cabinet travels from `scene:build` to `scene:render` in (TOOL-11).
 */
final class RenderPlacementTest extends TestCase
{
    public function testItCarriesWhatThePlacedDeviceDerives(): void
    {
        $placed = $this->tilted(aimLines: true);
        $record = RenderPlacement::fromPlaced($placed);

        self::assertSame('top-1', $record->placementId);
        self::assertSame($placed->device->id, $record->deviceId);
        self::assertSame($placed->device->category, $record->category);
        self::assertSame($placed->device->subtype, $record->subtype);
        self::assertSame($placed->worldBox(), $record->worldBox);
        self::assertSame($placed->frontFaceCentre(), $record->frontFaceCentre);
        self::assertSame($placed->frontDirection(), $record->frontDirection);
        self::assertTrue($record->aimLines);
    }

    /**
     * **Through JSON and back to the bit**, because the camera is framed on these numbers and a render drawn from
     * the stored record has to be the picture a fresh solve would draw. A tilted, yawed cabinet gives every
     * component an irrational value, which is where a lossy encoding would show.
     */
    #[DataProvider('aimLineCases')]
    public function testItSurvivesJsonExactly(?bool $aimLines): void
    {
        $record = RenderPlacement::fromPlaced($this->tilted($aimLines));
        $json = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $back = RenderPlacement::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));

        self::assertEquals($record, $back);
        self::assertSame($record->toArray(), $back->toArray());
    }

    /**
     * @return iterable<string, array{bool|null}>
     */
    public static function aimLineCases(): iterable
    {
        yield 'left to the mode' => [null];
        yield 'asked for' => [true];
        yield 'refused' => [false];
    }

    public function testAListMayMixFreshSolvesAndStoredRecords(): void
    {
        $stored = RenderPlacement::fromPlaced($this->tilted());
        $list = RenderPlacement::listOf([$this->tilted(), $stored]);

        self::assertCount(2, $list);
        self::assertEquals($stored, $list[0]);
        self::assertSame($stored, $list[1], 'a record that already is one passes through untouched');
    }

    /**
     * @param array<string, mixed> $broken
     */
    #[DataProvider('brokenRecords')]
    public function testAMalformedRecordIsRefused(array $broken): void
    {
        $this->expectException(\UnexpectedValueException::class);

        RenderPlacement::fromArray($broken + RenderPlacement::fromPlaced($this->tilted())->toArray());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function brokenRecords(): iterable
    {
        yield 'no placement id' => [['placement_id' => null]];
        yield 'unknown category' => [['category' => 'spaceship']];
        yield 'short vector' => [['front_direction' => [0.0, -1.0]]];
        yield 'text in a vector' => [['front_face_centre' => [0.0, 'x', 1.0]]];
        yield 'no box' => [['world_box' => null]];
        yield 'aim lines as text' => [['aim_lines' => 'yes']];
    }

    private function tilted(?bool $aimLines = null): PlacedDevice
    {
        return new PlacedDevice(
            'top-1',
            SpecFactory::spec(['category' => Category::Speaker->value]),
            [1.25, -0.4, 0.62],
            new Orientation(-7.5, 0.0, 13.0),
            aimLines: $aimLines,
        );
    }
}
