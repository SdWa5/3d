<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
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
            array_filter($placed, static fn (PlacedDevice $e): bool => self::belongsTo($e, $tier)),
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
     * A mixed row is several placements, because a `row` group makes copies of **one** device. Segments get
     * letters so they cannot be confused with the numeric `-1`, `-2` suffixes every group appends.
     *
     * The mixed row here is the **tops**: every top shares one row, widest in the middle, so it comes out as
     * a 2-way, three M2122s and a 2-way — three placements at one height.
     */
    public function testAMixedTierExpandsIntoOnePlacementPerSegment(): void
    {
        $placed = $this->compile($this->allSpeakers());

        $ids = [];
        foreach ($placed as $entry) {
            $ids[preg_replace('/-\d+$/', '', $entry->placementId)] = true;
        }

        self::assertSame(['main/1', 'main/2', 'main/3', 'main/4a', 'main/4b', 'main/4c'], array_keys($ids));
    }

    /**
     * The segments of a mixed row sit side by side, spaced on their nominal widths plus one working gap —
     * including across a segment boundary, because a 2-way beside an M2122 needs the same air as two M2122s.
     *
     * Checked on the **slot positions**, not the rotated boxes, and that distinction is the point: these are
     * aimed cabinets, so toe-in eats into the gap and the boxes measure 7.9 mm apart where the layout put
     * 20 mm. The layout is what this test is about; whether the turned boxes still clear each other is
     * {@see ShippedScenesTest}'s job, and it does check it.
     */
    public function testTheSegmentsOfAMixedRowSitSideBySideWithOneGapBetweenThem(): void
    {
        $placed = $this->compile($this->allSpeakers());

        $centre = function (string $segment) use ($placed): float {
            $xs = array_map(
                static fn (PlacedDevice $e): float => $e->position[0],
                array_values(array_filter($placed, static fn (PlacedDevice $e): bool => self::belongsTo($e, $segment))),
            );

            return array_sum($xs) / count($xs);
        };

        // Row is 2.5112 m centred on -0.302: a 2-way at each end, three M2122s in the middle.
        self::assertEqualsWithDelta(-1.3248, $centre('main/4a'), 1e-9);
        self::assertEqualsWithDelta(-0.302, $centre('main/4b'), 1e-9);
        self::assertEqualsWithDelta(0.7208, $centre('main/4c'), 1e-9);
    }

    /**
     * Every segment of the tops row stands at the same height, on the Achenbach row — not on each other.
     * A 2-way perched on a tilted M2122 was a fill hovering over the middle of the rig.
     */
    public function testEverySegmentOfTheTopsRowStandsAtTheSameHeight(): void
    {
        $placed = $this->compile($this->allSpeakers());

        foreach (['main/4a', 'main/4b', 'main/4c'] as $segment) {
            $bottom = min(array_map(
                static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
                array_filter($placed, static fn (PlacedDevice $e): bool => self::belongsTo($e, $segment)),
            ));
            self::assertEqualsWithDelta(2.126, $bottom, 1e-9, $segment.' is not on the Achenbach row');
        }
    }

    /** Everything that can share a stack, as a scene rather than only as a count assertion. */
    public function testEveryStackableCabinetStacksAndClearsTheInterface(): void
    {
        $placed = $this->compile($this->allSpeakers());

        self::assertCount(21, $placed);
        // 12 Flexy + 4 Achenbach = 2.126 m of subs under the tops.
        $tops = min(array_map(
            static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
            array_filter($placed, static fn (PlacedDevice $e): bool => str_starts_with($e->placementId, 'main/4')),
        ));
        self::assertGreaterThanOrEqual(2.0, $tops);
        self::assertEqualsWithDelta(2.126, $tops, 1e-9);
    }

    /**
     * The one thing the solver has to say about this rig, said as a **warning** so it still builds: the tops
     * row is 2.511 m on a 2.460 m Achenbach row, so it stands 26 mm proud at each end. Before this,
     * `scene:build` treated any violation as fatal and swallowed the whole report.
     */
    public function testASmallOverhangIsAWarningRatherThanAnError(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($this->allSpeakers()));

        self::assertSame([], \App\Spec\Violation::errorsIn($result['violations']));
        $messages = implode("\n", array_map(static fn ($v): string => $v->message, $result['violations']));
        self::assertStringContainsString('overhangs 26 mm each side', $messages);
    }

    /**
     * A tier's own `align` overrides the stack's for that tier.
     *
     * What per-tier alignment can and cannot do is worth stating: it says *which* alignment a tier uses, at
     * the point that tier is declared. It does not let a load-bearing tier be spread — that rule is unchanged,
     * because spreading a tier turns it into gaps and the tier above then stands over air. In a plain tower
     * only the top tier carries nothing, so that is the one this reaches.
     */
    public function testATiersOwnAlignmentOverridesTheStacks(): void
    {
        $placements = [
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    'flexy-folded-horn-hybrid',
                    'achenbach-18',
                    ['device' => 'tecnare-m2122', 'align' => 'block'],
                ],
            ]],
        ];

        $placed = $this->compile($placements);

        // The Tecnare row is spread onto the Achenbach row carrying it: 2.460 m, not the 1.514 m it occupies
        // unaligned. The delta is the solver's own tolerance — `align` bisects a fixed point to 1e-6 m,
        // because these cabinets are aimed and their outer edge moves as they toe in.
        $tops = $this->edgesOf($placed, 'main/4');
        self::assertEqualsWithDelta(2.460, $tops['max'] - $tops['min'], 1e-5);
    }

    /** Without a per-tier alignment the tier keeps its natural spacing. */
    public function testWithoutAnAlignmentATierKeepsItsNaturalSpacing(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122'],
            ]],
        ]);

        // 1.514 m, not the 1.540 m of three 0.500 m cabinets plus two gaps: they are aimed, and a toed-in
        // trapezoid's outermost point is its *back* bottom corner, which sits inside its half-width.
        $tops = $this->edgesOf($placed, 'main/4');
        self::assertEqualsWithDelta(1.5137, $tops['max'] - $tops['min'], 1e-4);
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

    /**
     * A stack whose bounds cannot be met contributes nothing and says why; the rest of the scene still builds.
     *
     * A stage narrower than a single cabinet is the honest case now. "Cannot be solved" no longer means
     * "cannot reach the interface height" (a low rig is a warning) nor "cannot mix heights" (gravity handles a
     * stepped row).
     */
    public function testAStackWhoseBoundsCannotBeMetReportsAndPlacesNothing(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene([
            ['id' => 'main', 'at' => [0.0, 0.0], 'stack' => [
                'max_width_m' => 0.4, 'interface_height_m' => 0.0,
                'from' => ['flexy-folded-horn-hybrid'],
            ]],
            ['id' => 'elsewhere', 'device' => 'skram', 'at' => [8.0, 0.0]],
        ]));

        self::assertCount(1, $result['placed'], 'only the SKRAM, which has nothing to do with the stack');
        self::assertStringContainsString(
            'stack.max_width_m',
            implode("\n", array_map(static fn ($v): string => $v->message, $result['violations'])),
        );
    }

    /**
     * The tops of a flanked rig sit **outboard on the shoulders**, not butted together across the step.
     *
     * The whole rig, end to end, in the arrangement the flanking rule produces: a mixed Achenbach row with a
     * Flexy either side, 163 mm taller at those shoulders than in the middle. Laid out as one contiguous row the
     * outboard 2-way clips a shoulder by 5.6 mm and gravity lifts it onto 1.2 % of itself — nothing about the
     * tier widths says so, and there really is something underneath, so both of the other checks pass it.
     * Seated on the shoulder instead it is squarely carried, raised, and out where a fill belongs.
     */
    public function testTheTopsOfAFlankedRigSitOutboardOnTheShoulders(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    'skram', 'flexy-folded-horn-hybrid', 'achenbach-18',
                    'tecnare-m2122', 'eighteensound-2way-15',
                ],
            ]],
        ]);

        self::assertCount(23, $placed, 'every cabinet we own, in one stack');

        $fills = array_values(array_filter(
            $placed,
            static fn (PlacedDevice $e): bool => $e->device->id === 'eighteensound-2way-15',
        ));
        self::assertCount(2, $fills);

        foreach ($fills as $fill) {
            $box = $fill->worldBox();
            $centre = ($box['min'][0] + $box['max'][0]) / 2;
            // On the shoulder, whose Flexy spans 1.250..1.841 — a contiguous row would have put it at 1.023.
            // Within a centimetre rather than exactly, because the fill is aimed: yawing it moves the centre of
            // its bounding box a few millimetres off the seat it was given.
            self::assertEqualsWithDelta(1.5455, abs($centre), 0.01);
            // And standing on the Flexy, so 763 mm above the 1.526 m tier rather than 600 mm.
            self::assertEqualsWithDelta(2.289, $box['min'][2], 1e-9);
        }
    }

    /**
     * **Gravity.** Each cabinet above a stepped row lands on whatever is under *it*, not on the height of the
     * tallest cabinet in the row below.
     *
     * This is the test for the bug that took three attempts to get right. A Flexy bottom row with two SKRAMs in
     * the middle is 151 mm taller in the middle; resting the whole row above at 0.914 m left four of its six
     * Flexys hanging in the air over the Flexys either side. Banning mixed heights removed the symptom and the
     * feature with it. Landing each cabinet individually keeps both: an uneven top, and everything carried.
     */
    public function testEachCabinetLandsOnWhateverIsUnderIt(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    'flexy-folded-horn-hybrid', 'skram', 'achenbach-18',
                    'tecnare-m2122', 'eighteensound-2way-15',
                ],
            ]],
        ]);

        self::assertCount(23, $placed, 'every cabinet we own, in one stack');

        // The second tier splits into three: over the left Flexys, over the SKRAMs, over the right Flexys.
        $bottomOf = function (string $run) use ($placed): float {
            $zs = array_map(
                static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
                array_values(array_filter($placed, static fn (PlacedDevice $e): bool => self::belongsTo($e, $run))),
            );
            self::assertNotSame([], $zs, $run.' was not placed');

            return min($zs);
        };

        self::assertEqualsWithDelta(0.763, $bottomOf('main/2a'), 1e-9, 'over a Flexy');
        self::assertEqualsWithDelta(0.914, $bottomOf('main/2b'), 1e-9, 'over a SKRAM, 151 mm higher');
        self::assertEqualsWithDelta(0.763, $bottomOf('main/2c'), 1e-9, 'over a Flexy again');
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
     * Every cabinet that can share a stack — 21 of them. The tops row is the mixed one.
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
                // No SKRAMs: only two exist and no other cabinet shares their height, so they cannot be
                // mixed into a row and a row of their own carries nothing. They belong beside a rig.
                'from' => [
                    'flexy-folded-horn-hybrid', 'achenbach-18',
                    'tecnare-m2122', 'eighteensound-2way-15',
                ],
            ]],
        ];
    }

    /**
     * Whether a cabinet belongs to a placement.
     *
     * A placement that makes **one** cabinet gets no numeric suffix at all — `idSuffix()` is empty when the
     * group has no axis with more than one cell — so a segment of one is `main/4a`, not `main/4a-1`. Matching
     * on the dash alone silently matches nothing.
     */
    private static function belongsTo(PlacedDevice $entry, string $placementId): bool
    {
        return $entry->placementId === $placementId
            || str_starts_with($entry->placementId, $placementId.'-');
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
            if (!self::belongsTo($entry, $placementId)) {
                continue;
            }
            $box = $entry->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return ['min' => $min, 'max' => $max];
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
