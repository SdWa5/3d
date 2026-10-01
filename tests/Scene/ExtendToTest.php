<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * `extend_to_m`, the height a telescoping truss tower is cranked to.
 */
final class ExtendToTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    public function testATowerCrankedDownIsThatTallEverywhere(): void
    {
        $result = $this->compile(['id' => 'tower', 'device' => 'truss-tower-4m', 'at' => [0, 0], 'extend_to_m' => 2.5]);

        self::assertSame([], $result['violations']);
        $tower = $result['placed'][0];
        self::assertSame(2.5, $tower->device->dimensions->height);
        self::assertEqualsWithDelta(2.5, $tower->topZ(), 1e-9);
        self::assertEqualsWithDelta(0.625, $tower->toArray()['scale_z'], 1e-9);
    }

    public function testAnUncrankedTowerCarriesNoScale(): void
    {
        $result = $this->compile(['id' => 'tower', 'device' => 'truss-tower-4m', 'at' => [0, 0]]);

        self::assertArrayNotHasKey('scale_z', $result['placed'][0]->toArray());
    }

    public function testATowerCannotBeCrankedAboveItsFullExtension(): void
    {
        $result = $this->compile(['id' => 'tower', 'device' => 'truss-tower-4m', 'at' => [0, 0], 'extend_to_m' => 4.5]);

        self::assertStringContainsString('at most 4.000 m', $result['violations'][0]->message);
        self::assertSame([], $result['placed']);
    }

    /** Every stage inside the sleeve leaves our stand at 2.225 m, so it cranks no lower. */
    public function testAMastCannotBeCrankedBelowItsCollapsedHeight(): void
    {
        $result = $this->compile(['id' => 'tower', 'device' => 'truss-tower-4m', 'at' => [0, 0], 'extend_to_m' => 1.0]);

        self::assertStringContainsString('below the 2.225 m truss-tower-4m cranks down to', $result['violations'][0]->message);
        self::assertSame([], $result['placed']);
    }

    public function testOnlyATowerTelescopes(): void
    {
        $result = $this->compile(['id' => 'sub', 'device' => 'wsx-18', 'at' => [0, 0], 'extend_to_m' => 0.5]);

        self::assertStringContainsString('only applies to a truss tower', $result['violations'][0]->message);
    }

    /**
     * @param array<string, mixed> $placement
     *
     * @return array{placed: list<\App\Scene\PlacedDevice>, violations: list<\App\Spec\Violation>}
     */
    private function compile(array $placement): array
    {
        return (new SceneCompiler($this->devices))->compile(
            SceneSpec::fromArray(['id' => 'extend', 'name' => 'Extend', 'placements' => [$placement]], 'test'),
        );
    }
}
