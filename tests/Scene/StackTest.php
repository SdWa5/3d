<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * A `stack` as the compiler sees it: solved into ordinary placements before anything else runs.
 *
 * The solve itself is {@see StackSolverTest}'s job. What is checked here is that the expansion produces
 * placements indistinguishable from hand-written ones — because that is the whole design. Nothing
 * downstream of the expansion knows a stack was involved, so anything it gets wrong shows up as ordinary
 * geometry rather than as a stack-shaped bug.
 */
final class StackTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices = [];

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    public function testAStackExpandsIntoOneNumberedPlacementPerTier(): void
    {
        $placed = $this->compile($this->rig());

        $tiers = [];
        foreach ($placed as $entry) {
            $tiers[preg_replace('/-\d+$/', '', $entry->placementId)] = true;
        }

        self::assertSame(['main/1', 'main/2', 'main/3', 'main/4'], array_keys($tiers));
        self::assertCount(19, $placed, '12 Flexy, 4 Achenbach, 3 Tecnare');
    }

    /**
     * Each tier stands `on` the one below, so no height is ever written — the same property a hand-written
     * rig gets from `on:`, and the reason a stack does not have to be re-measured when a spec changes.
     */
    public function testEachTierStandsOnTheOneBelow(): void
    {
        $placed = $this->compile($this->rig());

        $bottom = static fn (string $tier): float => min(array_map(
            static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
            array_filter($placed, static fn (PlacedDevice $e): bool => str_starts_with($e->placementId, $tier.'-')),
        ));

        self::assertEqualsWithDelta(0.0, $bottom('main/1'), 1e-9);
        self::assertEqualsWithDelta(0.763, $bottom('main/2'), 1e-9);
        self::assertEqualsWithDelta(1.526, $bottom('main/3'), 1e-9);
        // The sub/top interface the constraint was asking for: 1.526 + the Achenbach's 0.600.
        self::assertEqualsWithDelta(2.126, $bottom('main/4'), 1e-9);
    }

    /**
     * The aim goes on the **top** tiers only, which is what every hand-written rig does: the subs fire
     * straight ahead and the tops are turned into the room. Aiming the subs as well would toe a sub wall
     * in, which is a different rig and not one anybody asked for.
     */
    public function testOnlyTheTopTiersAreAimed(): void
    {
        $placed = $this->compile($this->rig());

        foreach ($placed as $entry) {
            $isTop = str_starts_with($entry->placementId, 'main/4-');
            if ($isTop && $entry->position[0] < -0.5) {
                self::assertNotEqualsWithDelta(0.0, $entry->yawDeg(), 1.0, 'an outer top is turned in');
            }
            if (!$isTop) {
                self::assertSame(0.0, $entry->yawDeg(), "a sub fires straight ahead: {$entry->placementId}");
                self::assertSame(0.0, $entry->pitchDeg(), "a sub is not tilted: {$entry->placementId}");
            }
        }
    }

    /**
     * The shipped scene against the one written out by hand. They agree on the Flexy rows and on the
     * interface height; they differ on the Achenbach row, and that difference is a correction —
     * `full-rig-three-tier` asks for six Achenbachs and only four exist.
     */
    public function testTheSolvedRigAgreesWithTheHandWrittenOneOnItsSubRows(): void
    {
        $solved = $this->compileShipped('full-rig-stacked');
        $byHand = $this->compileShipped('full-rig-three-tier');

        self::assertEqualsWithDelta(
            $this->extentOf($byHand, 'sub-row-bottom'),
            $this->extentOf($solved, 'main/1'),
            1e-9,
        );
        self::assertSame(4, count(array_filter(
            $solved,
            static fn (PlacedDevice $e): bool => $e->device->id === 'achenbach-18',
        )), 'four Achenbachs exist, so four are placed');
    }

    /**
     * A mixed row is several placements, because a `row` group makes copies of **one** device. Segments get
     * letters so they cannot be confused with the numeric `-1`, `-2` suffixes every group appends.
     */
    public function testAMixedTierExpandsIntoOnePlacementPerSegment(): void
    {
        $placed = $this->compile($this->allSpeakers());

        $ids = [];
        foreach ($placed as $entry) {
            $ids[preg_replace('/-\d+$/', '', $entry->placementId)] = true;
        }

        self::assertSame(
            ['main/1a', 'main/1b', 'main/1c', 'main/2', 'main/3', 'main/4', 'main/5', 'main/6'],
            array_keys($ids),
        );
    }

    /**
     * The segments of a mixed row have to sit side by side with the row's own working gap between them,
     * including across a segment boundary — a SKRAM beside a Flexy needs the same air as two Flexys do.
     */
    public function testTheSegmentsOfAMixedRowSitSideBySideWithOneGapBetweenThem(): void
    {
        $placed = $this->compile($this->allSpeakers());

        $left = $this->edgesOf($placed, 'main/1a');
        $middle = $this->edgesOf($placed, 'main/1b');
        $right = $this->edgesOf($placed, 'main/1c');

        self::assertEqualsWithDelta(0.02, $middle['min'] - $left['max'], 1e-9);
        self::assertEqualsWithDelta(0.02, $right['min'] - $middle['max'], 1e-9);
        // And the row as a whole is the 3.684 m that makes the rig a pyramid.
        self::assertEqualsWithDelta(3.684, $right['max'] - $left['min'], 1e-9);
    }

    /**
     * Whatever stands on a stepped row rests on its **tallest** segment, because that is the top face `on:`
     * reads. The SKRAMs are 0.914 m and the Flexys either side 0.763 m, so tier 2 starts at 0.914 m.
     */
    public function testTheTierAboveAMixedRowStandsOnItsTallestSegment(): void
    {
        $placed = $this->compile($this->allSpeakers());

        $bottom = static fn (string $tier): float => min(array_map(
            static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
            array_filter($placed, static fn (PlacedDevice $e): bool => str_starts_with($e->placementId, $tier.'-')),
        ));

        self::assertEqualsWithDelta(0.914, $bottom('main/2'), 1e-9);
    }

    /** The whole inventory, as a scene rather than only as a count assertion. */
    public function testTheWholeInventoryStacksAndClearsTheInterface(): void
    {
        $placed = $this->compile($this->allSpeakers());

        self::assertCount(23, $placed);
        // 12 Flexy + 2 SKRAM + 4 Achenbach = 3.040 m of subs under the tops.
        $tops = min(array_map(
            static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
            array_filter($placed, static fn (PlacedDevice $e): bool => str_starts_with($e->placementId, 'main/5-')),
        ));
        self::assertGreaterThanOrEqual(2.0, $tops);
        self::assertEqualsWithDelta(3.040, $tops, 1e-9);
    }

    /**
     * The two things the solver has to say about this rig, said as **warnings** so it still builds. Before
     * this, `scene:build` treated any violation as fatal and swallowed the whole report.
     */
    public function testTheStepAndTheOverhangAreWarningsRatherThanErrors(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($this->allSpeakers()));

        self::assertSame([], \App\Spec\Violation::errorsIn($result['violations']));
        $messages = implode("\n", array_map(static fn ($v): string => $v->message, $result['violations']));
        self::assertStringContainsString('stepped by 151 mm', $messages);
        self::assertStringContainsString('overhangs 18 mm each side', $messages);
    }

    public function testAnUnknownDeviceInFromIsReported(): void
    {
        $violations = $this->violations([
            ['id' => 'main', 'at' => [0.0, 0.0], 'stack' => [
                'max_width_m' => 3.7, 'from' => ['flexy-folded-horn-hybrid', 'nope'],
            ]],
        ]);

        self::assertStringContainsString("stack.from: unknown device 'nope'", implode("\n", $violations));
    }

    /** A stack that cannot be solved contributes nothing and says why; the rest of the scene still builds. */
    public function testAStackThatCannotBeSolvedReportsAndPlacesNothing(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene([
            ['id' => 'main', 'at' => [0.0, 0.0], 'stack' => [
                'max_width_m' => 3.7, 'interface_height_m' => 9.0,
                'from' => ['flexy-folded-horn-hybrid', 'tecnare-m2122'],
            ]],
            ['id' => 'elsewhere', 'device' => 'skram', 'at' => [8.0, 0.0]],
        ]));

        self::assertCount(1, $result['placed'], 'only the SKRAM, which has nothing to do with the stack');
        self::assertStringContainsString('interface_height_m', $result['violations'][0]->message);
    }

    public function testAStackRefusesADeviceOfItsOwn(): void
    {
        $this->expectException(\App\Spec\InvalidSpecException::class);
        $this->expectExceptionMessage('`device` is decided by the stack');

        $this->scene([
            ['id' => 'main', 'at' => [0.0, 0.0], 'device' => 'skram',
                'stack' => ['max_width_m' => 3.7, 'from' => ['flexy-folded-horn-hybrid']]],
        ]);
    }

    public function testAStackRefusesAGroupOfItsOwn(): void
    {
        $this->expectException(\App\Spec\InvalidSpecException::class);
        $this->expectExceptionMessage('`row` is decided by the stack');

        $this->scene([
            ['id' => 'main', 'at' => [0.0, 0.0], 'row' => ['count' => 3],
                'stack' => ['max_width_m' => 3.7, 'from' => ['flexy-folded-horn-hybrid']]],
        ]);
    }

    public function testReadingRejectsAnUnknownStackKey(): void
    {
        $this->expectException(\App\Spec\InvalidSpecException::class);
        $this->expectExceptionMessage("stack: unknown key 'max_with_m'");

        $this->scene([
            ['id' => 'main', 'at' => [0.0, 0.0],
                'stack' => ['max_with_m' => 3.7, 'from' => ['flexy-folded-horn-hybrid']]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rig(): array
    {
        return [
            ['id' => 'main', 'at' => [-0.302, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70,
                'interface_height_m' => 2.0,
                'gap_m' => 0.02,
                'from' => ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            ]],
        ];
    }

    /**
     * All 23 cabinets, which is the case that forces a mixed bottom row.
     *
     * @return list<array<string, mixed>>
     */
    private function allSpeakers(): array
    {
        return [
            ['id' => 'main', 'at' => [-0.302, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70,
                'interface_height_m' => 2.0,
                'gap_m' => 0.02,
                'from' => [
                    'flexy-folded-horn-hybrid', 'skram', 'achenbach-18',
                    'tecnare-m2122', 'eighteensound-2way-15',
                ],
            ]],
        ];
    }

    /**
     * A placement's outer edges in x.
     *
     * @param list<PlacedDevice> $placed
     * @return array{min: float, max: float}
     */
    private function edgesOf(array $placed, string $placementId): array
    {
        $min = INF;
        $max = -INF;
        foreach ($placed as $entry) {
            if (!str_starts_with($entry->placementId, $placementId.'-')) {
                continue;
            }
            $box = $entry->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function extentOf(array $placed, string $placementId): float
    {
        $min = INF;
        $max = -INF;
        foreach ($placed as $entry) {
            if (!str_starts_with($entry->placementId, $placementId.'-')) {
                continue;
            }
            $box = $entry->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return $max - $min;
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @return list<PlacedDevice>
     */
    private function compile(array $placements): array
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($placements));

        // Errors only. A stack may legitimately warn — a stepped mixed row, a tier standing slightly proud
        // of the one below — and those are things to know about a buildable rig, not test failures.
        self::assertSame([], array_map(
            static fn ($v): string => $v->message,
            \App\Spec\Violation::errorsIn($result['violations']),
        ));

        return $result['placed'];
    }

    /**
     * @return list<PlacedDevice>
     */
    private function compileShipped(string $sceneId): array
    {
        $scene = (new SceneLoader(dirname(__DIR__, 2).'/scenes'))->find($sceneId)['scene'];
        self::assertNotNull($scene);

        return (new SceneCompiler($this->devices))->compile($scene)['placed'];
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @return list<string>
     */
    private function violations(array $placements): array
    {
        return array_map(
            static fn ($v): string => $v->message,
            (new SceneCompiler($this->devices))->compile($this->scene($placements))['violations'],
        );
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
