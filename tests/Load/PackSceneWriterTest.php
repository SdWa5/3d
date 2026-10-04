<?php

declare(strict_types=1);

namespace App\Tests\Load;

use App\Load\LoadPlan;
use App\Load\PackLayout;
use App\Load\PackSceneWriter;
use App\Scene\Interpenetration;
use App\Scene\PlacementChecks;
use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The pack written as a scene, which is only worth anything if the compiler puts every unit where the layout meant it.
 *
 * **A turned unit is the case that can go wrong.** A turn of 90° about the bottom-centre origin moves the box off the
 * origin, so the writer has to put the origin elsewhere for the box to land in the layout's place. These tests take
 * real specs from the library, because a truss, a folding stand and an upright rack are what turns.
 */
final class PackSceneWriterTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private static array $devices = [];

    public static function setUpBeforeClass(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            self::$devices[$spec->id] = $spec;
        }
    }

    public function testTurnedAndPackedUnitsAreWrittenWithTheirAngles(): void
    {
        $yaml = (new PackSceneWriter())->yaml('pack-test', [self::plan()], []);

        self::assertMatchesRegularExpression('/device: truss-f33-2m\n    at: \[ [^\]]+ \]\n(    on: \S+\n)?    yaw_deg: 90/', $yaml);
        self::assertStringContainsString('packed: true', $yaml);
        self::assertStringNotContainsString('rack-amp-12u-1', self::turnedIds($yaml), 'an upright rack was laid down');
    }

    /**
     * **Every unit compiles into the box the layout gave it**, turned ones included, and nothing interpenetrates. The
     * open bed is the case with a roll in it, since it lays the folded stand down.
     */
    #[DataProvider('vehicles')]
    public function testTheCompiledSceneMatchesTheLayout(string $vehicle): void
    {
        $plan = 'trailer-750kg' === $vehicle ? self::trailerPlan() : self::plan();
        $yaml = (new PackSceneWriter())->yaml('pack-test', [$plan], []);
        /** @var array<string, mixed> $data */
        $data = Yaml::parse($yaml);
        $result = (new SceneCompiler(self::$devices))->compile(SceneSpec::fromArray($data, 'test'));

        self::assertSame([], array_map(static fn (Violation $v): string => $v->message, Violation::errorsIn($result['violations'])));
        self::assertGreaterThanOrEqual(-PlacementChecks::CONTACT_TOLERANCE_M, Interpenetration::worst($result['placed'])['separation']);

        $compiled = [];
        foreach ($result['placed'] as $placed) {
            $compiled[$placed->placementId] = $placed->worldBox();
        }

        // The writer puts the first vehicle's centre at half its length along y.
        $shift = $plan->vehicle->dimensions->depth / 2.0;
        $placed = (new PackLayout())->forPlan($plan)['placed'];
        if ('trailer-750kg' === $vehicle) {
            self::assertNotSame([], array_filter($placed, static fn (array $e): bool => 0.0 !== $e['turn']->rollDeg || 0.0 !== $e['turn']->pitchDeg), 'nothing was laid down, so the origin offset went untested');
        }
        foreach ($placed as $entry) {
            $box = $compiled[$entry['id']] ?? null;
            self::assertNotNull($box, $entry['id'].' is missing from the compiled scene');
            for ($axis = 0; $axis < 3; ++$axis) {
                $offset = 1 === $axis ? $shift : 0.0;
                self::assertEqualsWithDelta($entry['box']['min'][$axis] + $offset, $box['min'][$axis], 1e-3, $entry['id'].' min '.$axis);
                self::assertEqualsWithDelta($entry['box']['max'][$axis] + $offset, $box['max'][$axis], 1e-3, $entry['id'].' max '.$axis);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function vehicles(): iterable
    {
        yield 'a van' => ['opel-movano-l4h3'];
        yield 'an open bed' => ['trailer-750kg'];
    }

    private static function trailerPlan(): LoadPlan
    {
        $items = [['spec' => self::$devices['truss-tower-4m'], 'count' => 2], ['spec' => self::$devices['achenbach-18'], 'count' => 1]];

        return new LoadPlan(self::$devices['trailer-750kg'], $items, 550.0, null);
    }

    private static function plan(): LoadPlan
    {
        $items = [];
        foreach (['achenbach-18' => 4, 'rack-amp-12u' => 2, 'truss-f33-2m' => 3, 'truss-tower-4m' => 2, 'geruest-krause-ah7' => 1] as $id => $count) {
            $items[] = ['spec' => self::$devices[$id], 'count' => $count];
        }

        return new LoadPlan(self::$devices['opel-movano-l4h3'], $items, 1024.0, 15.8);
    }

    /** The ids of the placements written with a pitch or a roll. */
    private static function turnedIds(string $yaml): string
    {
        preg_match_all('/- id: (\S+)\n(?:    (?!- id).*\n)*?    (?:pitch|roll)_deg/', $yaml, $matches);

        return implode(' ', $matches[1]);
    }
}
