<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class SceneCompilerTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        // A 0.6 m tall sub and a 0.9 m tall top, so stacking heights are easy to read.
        $this->devices = [
            'sub' => SpecFactory::spec([
                'id' => 'sub',
                'subtype' => 'sub',
                'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 0.6, 'depth' => 1.0]],
                'physical' => ['weight_kg' => 80.0],
            ]),
            'top' => SpecFactory::spec([
                'id' => 'top',
                'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 0.9, 'depth' => 0.5]],
                'physical' => ['weight_kg' => 30.0],
            ]),
        ];
    }

    public function testPlacesAGroundDeviceAtZZero(): void
    {
        $placed = $this->compile([
            ['id' => 'a', 'device' => 'sub', 'at' => [1.0, 2.0]],
        ]);

        self::assertCount(1, $placed);
        self::assertSame([1.0, 2.0, 0.0], $placed[0]->position);
        self::assertSame('a', $placed[0]->placementId);
    }

    public function testStackingTakesTheHeightFromTheSpecBelow(): void
    {
        // The whole point of `on`: no height is ever written into a scene file.
        $placed = $this->compile([
            ['id' => 'bottom', 'device' => 'sub', 'at' => [0.0, 0.0]],
            ['id' => 'middle', 'device' => 'sub', 'on' => 'bottom'],
            ['id' => 'upper', 'device' => 'top', 'on' => 'middle'],
        ]);

        self::assertSame(0.0, $placed[0]->position[2]);
        self::assertSame(0.6, $placed[1]->position[2]);
        self::assertSame(1.2, $placed[2]->position[2]);
        self::assertSame(2.1, $placed[2]->topZ());
    }

    public function testStackingInheritsGroundPositionUnlessOverridden(): void
    {
        $placed = $this->compile([
            ['id' => 'bottom', 'device' => 'sub', 'at' => [3.0, 4.0]],
            ['id' => 'inherits', 'device' => 'top', 'on' => 'bottom'],
            ['id' => 'moved', 'device' => 'top', 'on' => 'bottom', 'at' => [9.0, 9.0]],
        ]);

        self::assertSame([3.0, 4.0, 0.6], $placed[1]->position);
        self::assertSame([9.0, 9.0, 0.6], $placed[2]->position);
    }

    public function testRepeatWalksAlongTheStepVector(): void
    {
        $placed = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0], 'repeat' => ['count' => 3, 'step' => [0.62, 0, 0]]],
        ]);

        self::assertCount(3, $placed);
        self::assertSame(['row-1', 'row-2', 'row-3'], array_map(fn ($p) => $p->placementId, $placed));
        self::assertEqualsWithDelta(0.0, $placed[0]->position[0], 1e-9);
        self::assertEqualsWithDelta(0.62, $placed[1]->position[0], 1e-9);
        self::assertEqualsWithDelta(1.24, $placed[2]->position[0], 1e-9);
    }

    public function testARepeatedRowCanBeStackedOnAnother(): void
    {
        $placed = $this->compile([
            ['id' => 'lower', 'device' => 'sub', 'at' => [0.0, 0.0], 'repeat' => ['count' => 2, 'step' => [0.62, 0, 0]]],
            ['id' => 'upper', 'device' => 'sub', 'on' => 'lower', 'at' => [0.0, 0.0], 'repeat' => ['count' => 2, 'step' => [0.62, 0, 0]]],
        ]);

        self::assertCount(4, $placed);
        foreach ([0, 1] as $index) {
            self::assertSame(0.0, $placed[$index]->position[2]);
        }
        foreach ([2, 3] as $index) {
            self::assertSame(0.6, $placed[$index]->position[2], 'the upper row sits on the lower one');
        }
    }

    public function testRollTurnsACabinetOverWithoutSinkingItThroughTheFloor(): void
    {
        // Geometry runs z = 0..height in a cabinet's own frame, so turning it over puts it below
        // zero unless the placement lifts it back up by its height.
        $placed = $this->compile([
            ['id' => 'flipped', 'device' => 'sub', 'at' => [0.0, 0.0], 'roll_deg' => 180],
        ]);

        self::assertSame(180.0, $placed[0]->rollDeg);
        self::assertSame(0.0, $placed[0]->position[2], 'the slot is still the floor');
        self::assertEqualsWithDelta(0.6, $placed[0]->zLift(), 1e-9, 'lifted by its own height');
        self::assertEqualsWithDelta(0.6, $placed[0]->toArray()['position_m'][2], 1e-9);
        self::assertEqualsWithDelta(0.6, $placed[0]->topZ(), 1e-9, 'an upside-down cabinet is no taller');
    }

    public function testStackingOnARolledCabinetStillLandsOnTopOfIt(): void
    {
        $placed = $this->compile([
            ['id' => 'flipped', 'device' => 'sub', 'at' => [0.0, 0.0], 'roll_deg' => 180],
            ['id' => 'above', 'device' => 'top', 'on' => 'flipped'],
        ]);

        self::assertEqualsWithDelta(0.6, $placed[1]->position[2], 1e-9);
    }

    public function testACabinetOnItsSideIsAsTallAsItIsWide(): void
    {
        // A 0.6 x 0.6 cube would hide this, so use the 0.5 wide x 0.9 high top.
        $placed = $this->compile([
            ['id' => 'sideways', 'device' => 'top', 'at' => [0.0, 0.0], 'roll_deg' => 90],
        ]);

        [$width, , $height] = $placed[0]->extent();
        self::assertEqualsWithDelta(0.9, $width, 1e-9, 'the 0.9 m height now runs left to right');
        self::assertEqualsWithDelta(0.5, $height, 1e-9, 'and the 0.5 m width is now the height');
        self::assertEqualsWithDelta(0.5, $placed[0]->topZ(), 1e-9);
    }

    public function testYawIsCarriedThrough(): void
    {
        $placed = $this->compile([
            ['id' => 'angled', 'device' => 'top', 'at' => [0.0, 0.0], 'yaw_deg' => 30.0],
        ]);

        self::assertSame(30.0, $placed[0]->yawDeg);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, string}>
     */
    public static function rejectionCases(): iterable
    {
        yield 'unknown device' => [
            [['id' => 'a', 'device' => 'nope', 'at' => [0, 0]]],
            "references unknown device 'nope'",
        ];
        yield 'neither at nor on' => [
            [['id' => 'a', 'device' => 'sub']],
            'needs either `at` or `on`',
        ];
        yield 'on a placement that does not exist' => [
            [['id' => 'a', 'device' => 'sub', 'on' => 'ghost']],
            'must name an earlier placement',
        ];
        yield 'on a placement defined later' => [
            [
                ['id' => 'a', 'device' => 'sub', 'on' => 'b'],
                ['id' => 'b', 'device' => 'sub', 'at' => [0, 0]],
            ],
            'must name an earlier placement',
        ];
        yield 'duplicate placement id' => [
            [
                ['id' => 'a', 'device' => 'sub', 'at' => [0, 0]],
                ['id' => 'a', 'device' => 'sub', 'at' => [1, 0]],
            ],
            "duplicate placement id 'a'",
        ];
        yield 'repeat without a step' => [
            [['id' => 'a', 'device' => 'sub', 'at' => [0, 0], 'repeat' => ['count' => 4]]],
            'repeat.count > 1 needs a repeat.step',
        ];
        yield 'repeat count below one' => [
            [['id' => 'a', 'device' => 'sub', 'at' => [0, 0], 'repeat' => ['count' => 0, 'step' => [1, 0, 0]]]],
            'repeat.count must be at least 1',
        ];
    }

    /**
     * @param list<array<string, mixed>> $placements
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectionCases')]
    public function testRejects(array $placements, string $expected): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($placements));
        $messages = array_map(static fn ($v): string => $v->message, $result['violations']);

        self::assertNotSame([], $messages);
        self::assertTrue(
            (bool)array_filter($messages, static fn (string $m): bool => str_contains($m, $expected)),
            sprintf("no violation contained %s\ngot: %s", var_export($expected, true), implode(' | ', $messages)),
        );
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @return list<\App\Scene\PlacedDevice>
     */
    private function compile(array $placements): array
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($placements));

        self::assertSame([], array_map(static fn ($v): string => $v->message, $result['violations']));

        return $result['placed'];
    }

    /**
     * @param list<array<string, mixed>> $placements
     */
    private function scene(array $placements): SceneSpec
    {
        return SceneSpec::fromArray(
            ['id' => 'test', 'name' => 'Test scene', 'placements' => $placements],
            '/scenes/test.yaml',
        );
    }
}
