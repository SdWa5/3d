<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Interpenetration;
use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Scene\Stack;
use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

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
        self::assertCount(21, $placed, '12 Flexy, 6 Achenbach, 3 Tecnare');
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
     * A stack stands flush at the front, on the front of its deepest cabinet, which the owner stated on 2026-10-01.
     * Centred on one y, the 0.52 m Tecnares stood 222 mm behind the front of the 0.964 m Flexys under them. The
     * outer Tecnares are aimed, so this also holds them to it after the yaw has swung a front corner forward. It is
     * the foot that stands flush, so a top tilted down leans its upper edge out over the wall.
     */
    public function testEveryCabinetStandsFlushWithTheFrontOfTheDeepest(): void
    {
        $placed = $this->compile($this->rig());

        foreach ($placed as $entry) {
            self::assertEqualsWithDelta(-0.964 / 2, $entry->footFrontY(), 1e-5, $entry->placementId);
        }
        $aimed = array_filter($placed, static fn (PlacedDevice $e): bool => abs($e->yawDeg()) > 1.0);
        self::assertNotSame([], $aimed, 'the rig has an aimed top to hold to the front');
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

        // `4b` before `4a`: the top tier emits its **long throw first**, because the fills either side of it are
        // solved `align.outside` it and `outside` can only name a placement that already exists. Which segment is
        // which is unchanged — only the order they are written in.
        self::assertSame(['main/1', 'main/2', 'main/3', 'main/4b', 'main/4a', 'main/4c'], array_keys($ids));
    }

    /**
     * The segments of a mixed row sit side by side, spaced on their nominal widths plus one working gap —
     * including across a segment boundary, because a 2-way beside an M2122 needs the same air as two M2122s.
     *
     * Checked on the **slot positions**, not the rotated boxes. The fills are no longer one nominal gap from the
     * long throw, and that is the change rather than a regression: toe-in used to eat the stated 20 mm down to
     * 7.9 mm of real air, so a fill is now solved against the M2122 beside it and the 20 mm is the air that is
     * actually there. 7.9 mm still cleared here; the same mechanism bit 1.7 mm in a three-stack rig's right stack,
     * which is what made it worth solving rather than tolerating.
     *
     * **The fill sits 12 mm closer than it did under `align.outside`, and the 20 mm is still there** — asserted
     * below on the shells rather than inferred, which is the whole point. `outside` collapses its reference to an x
     * span, so it put 20 mm between the *extreme x points* of two rotated boxes; the cabinets nest in y, so the real
     * separation was more than asked for and the fill was pushed further out than the gap required.
     * {@see Alignment::$clearOf} measures the shells and lands exactly on the stated working gap.
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

        // Three M2122s centred on -0.302, with a 2-way solved 20 mm clear of each end of them. The flanking centres
        // moved out when the M2122s themselves stopped sitting closer than their own working gap: they are aimed, and
        // their front corners were 20 mm apart in nominal widths but not in fact. The middle segment does not move,
        // because spreading a row scales its offsets about its own centre.
        self::assertEqualsWithDelta(-0.302, $centre('main/4b'), 1e-9);
        self::assertEqualsWithDelta(-1.342456, $centre('main/4a'), 1e-6);
        self::assertEqualsWithDelta(0.738456, $centre('main/4c'), 1e-6);

        // The number the positions above exist to deliver, measured the way the solve measures it. Symmetric to the
        // micrometre, which is itself worth pinning: a one-sided answer would mean the two fills were solved against
        // different things.
        $segment = static fn (string $id): array => array_values(array_filter(
            $placed,
            static fn (PlacedDevice $e): bool => self::belongsTo($e, $id),
        ));
        self::assertEqualsWithDelta(0.020, Interpenetration::gapBetween($segment('main/4a'), $segment('main/4b')), 1e-5);
        self::assertEqualsWithDelta(0.020, Interpenetration::gapBetween($segment('main/4c'), $segment('main/4b')), 1e-5);
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

        self::assertCount(23, $placed);
        // 12 Flexy + 6 Achenbach = 2.126 m of subs under the tops.
        $tops = min(array_map(
            static fn (PlacedDevice $e): float => $e->worldBox()['min'][2],
            array_filter($placed, static fn (PlacedDevice $e): bool => str_starts_with($e->placementId, 'main/4')),
        ));
        self::assertGreaterThanOrEqual(2.0, $tops);
        self::assertEqualsWithDelta(2.126, $tops, 1e-9);
    }

    /**
     * The one thing the solver has to say about this rig, said as a **warning** so it still builds: the
     * Achenbach row is 3.700 m on a 3.646 m Flexy row, so it stands 27 mm proud at each end. Before this,
     * `scene:build` treated any violation as fatal and swallowed the whole report.
     */
    public function testASmallOverhangIsAWarningRatherThanAnError(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($this->allSpeakers()));

        self::assertSame([], \App\Spec\Violation::errorsIn($result['violations']));
        $messages = implode("\n", array_map(static fn ($v): string => $v->message, $result['violations']));
        self::assertStringContainsString('overhangs 27 mm each side', $messages);
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

        // The Tecnare row is spread onto the Achenbach row carrying it: 3.700 m, not the 1.514 m it occupies
        // unaligned. The delta is the solver's own tolerance — `align` bisects a fixed point to 1e-6 m,
        // because these cabinets are aimed and their outer edge moves as they toe in.
        $tops = $this->edgesOf($placed, 'main/4');
        self::assertEqualsWithDelta(3.700, $tops['max'] - $tops['min'], 1e-5);
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

        // 1.5391 m, and 1.5427 while the tops stood centred on the Flexys rather than flush with their front. Two effects, pulling opposite ways, and the row used to record only the first of them. A toed-in
        // trapezoid's outermost point is its *back* bottom corner and sits inside its half-width, which is why the row
        // is not simply the 1.540 m of three 0.500 m cabinets plus two gaps. But the same yaw swings each cabinet's
        // *front* corners towards its neighbour, so at nominal spacing the air between them fell under the 20 mm the
        // row was given, and 1.5137 m was a row whose cabinets were closer together than asked. The spacing solve now
        // restores the gap, which cost 29 mm of width. Standing 222 mm further back the tops toe in a little less
        // and the row lands just under the unrotated 1.540.
        $tops = $this->edgesOf($placed, 'main/4');
        self::assertEqualsWithDelta(1.5391, $tops['max'] - $tops['min'], 1e-4);
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
     * **A stereo tops row is spread by its own groups, not by the runs gravity seated it in.**.
     *
     * PSL's five EF 6 over twelve turned ESX are the palindrome `2× | 1× | 2×`, and gravity seats them over the three
     * ESX columns as runs of 2, 2 and 1. Spread by those runs, the odd top landed 0.31 m right of the centre line with
     * a pair beside it, measured on the next event's combined rig. Spread by its groups the row is a pair at each edge
     * and the odd one on the centre line, mirrored to the millimetre.
     */
    public function testAStereoTopsRowIsSpreadByItsGroupsRatherThanByItsRuns(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'align' => ['mode' => 'stereo'], 'stack' => [
                'max_width_m' => 3.58, 'interface_height_m' => 2.0, 'max_sub_height_m' => 3.0, 'gap_m' => 0.02,
                'shape' => 'pyramid',
                'from' => [
                    ['device' => 'concert-audio-esx', 'count' => 12, 'roll_mirror' => 90.0],
                    ['device' => 'concert-audio-ef6', 'count' => 5],
                ],
            ]],
        ]);

        $tops = array_map(
            static fn (PlacedDevice $device): float => $device->position[0],
            array_values(array_filter($placed, static fn (PlacedDevice $device): bool => 'concert-audio-ef6' === $device->device->id)),
        );
        sort($tops);

        self::assertCount(5, $tops);
        self::assertEqualsWithDelta(0.0, $tops[2], 0.001, 'the odd top on the centre line');
        self::assertEqualsWithDelta(0.0, $tops[0] + $tops[4], 0.001, 'the outer tops mirrored');
        self::assertEqualsWithDelta(0.0, $tops[1] + $tops[3], 0.001, 'the inner tops mirrored');
        self::assertLessThan(0.65, $tops[1] - $tops[0], 'the left pair keeps its own spacing');
    }

    /**
     * **A stereo tops row packs its near-field tops outward, against the long throws at its ends.**.
     *
     * The owner stated on 2026-10-01 that the two-ways stand as far out as they can, touching the Tecnare at their end
     * of the row. Before, each was only kept clear of the middle Tecnare and stood wherever the row dealt it, 0.323 m
     * off the outer one. So each two-way stands one working gap off the Tecnare outboard of it, measured on the shells,
     * and further than that off the one inboard.
     */
    public function testAStereoTopsRowPacksItsFillsAgainstTheOuterLongThrows(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'align' => ['mode' => 'stereo'], 'stack' => [
                'max_width_m' => 4.30, 'interface_height_m' => 1.6, 'gap_m' => 0.02,
                'from' => [
                    ['device' => 'flexy-folded-horn-hybrid', 'count' => 6],
                    ['device' => 'tecnare-m2122', 'count' => 3],
                    ['device' => 'eighteensound-2way-15', 'count' => 2],
                ],
            ]],
        ]);

        $of = static fn (string $id): array => array_values(array_filter($placed, static fn (PlacedDevice $e): bool => $id === $e->device->id));
        $throws = $of('tecnare-m2122');
        $fills = $of('eighteensound-2way-15');
        self::assertCount(3, $throws);
        self::assertCount(2, $fills);
        usort($throws, static fn (PlacedDevice $a, PlacedDevice $b): int => $a->position[0] <=> $b->position[0]);

        foreach ($fills as $fill) {
            $left = $fill->position[0] < 0.0;
            $outer = $left ? $throws[0] : $throws[2];
            self::assertEqualsWithDelta(0.02, Interpenetration::gapBetween([$fill], [$outer]), 1e-4, $fill->placementId.' against the outer Tecnare');
            self::assertGreaterThan(0.02 + 1e-3, Interpenetration::gapBetween([$fill], [$throws[1]]), $fill->placementId.' off the middle one');
        }
    }

    public function testAnAsymmetricStereoRowKeepsItsFillsClearAcrossASupportStep(): void
    {
        $placements = Yaml::parse(
            <<<'YAML'
                - id: main-gmss
                  at:
                  - 2.993
                  - 0.0
                  aim: far
                  focus:
                    far:
                      distance_m: 10.0
                      height_m: 1.8
                    near:
                      distance_m: 2.0
                      height_m: 1.8
                  align:
                    mode: stereo
                  stack:
                    interface_height_m: 2.0
                    max_sub_height_m: 3.0
                    gap_m: 0.02
                    slide_slack_m: .inf
                    shape: pyramid
                    from:
                    - wall-bass
                    - mid-bass
                    - nuke
                    - iq-sub
                    - tecnare-m2122
                    - device: eighteensound-2way-15
                      count: 1
                      aim: near
                    - device: turbo-top
                      count: 1
                      aim: near
                YAML
        );
        $placed = $this->compile($placements);

        self::assertSame([], Interpenetration::faults($placed, 0.001));
        self::assertSame([], \App\Scene\PlacementChecks::floatingFaults($placed));
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
     * A stack whose subs lie on their sides places them by their **bodies**, not their origins.
     *
     * The trap the whole roll path turns on: a rolled cabinet is not centred on its own origin — at 90 the body
     * is entirely to the right of it, at 270 entirely to the left. A run whose `at` were taken as the middle of
     * its body span would sit half a cabinet off, and mirrored halves would slide into each other from both
     * sides. Checked here in world coordinates, which is the only place the mistake would show.
     */
    public function testAStackOfRolledSubsPlacesThemByTheirBodies(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    ['device' => 'flexy-folded-horn-hybrid', 'roll_mirror' => 90],
                    'achenbach-18',
                    'tecnare-m2122',
                ],
            ]],
        ]);

        $subs = array_values(array_filter(
            $placed,
            static fn (PlacedDevice $e): bool => 'flexy-folded-horn-hybrid' === $e->device->id,
        ));
        self::assertCount(12, $subs);

        // The bottom tier, left to right: four rolled bodies tiling from −1.556 with 20 mm between them.
        $bottom = array_values(array_filter($subs, static fn (PlacedDevice $e): bool => $e->worldBox()['min'][2] < 1e-9));
        usort($bottom, static fn (PlacedDevice $a, PlacedDevice $b): int => $a->worldBox()['min'][0] <=> $b->worldBox()['min'][0]);
        self::assertCount(4, $bottom);

        $edges = array_map(static fn (PlacedDevice $e): array => [
            round($e->worldBox()['min'][0], 4),
            round($e->worldBox()['max'][0], 4),
        ], $bottom);
        self::assertSame([[-1.556, -0.793], [-0.773, -0.01], [0.01, 0.773], [0.793, 1.556]], $edges);

        // Rolled, so each one is 763 mm across and 591 mm tall rather than the other way round.
        foreach ($bottom as $sub) {
            $box = $sub->worldBox();
            self::assertEqualsWithDelta(0.763, $box['max'][0] - $box['min'][0], 1e-9);
            self::assertEqualsWithDelta(0.591, $box['max'][2] - $box['min'][2], 1e-9);
        }

        // And the halves are turned opposite ways.
        self::assertEqualsWithDelta(270.0, fmod($bottom[0]->rollDeg() + 360.0, 360.0), 1e-9);
        self::assertEqualsWithDelta(90.0, fmod($bottom[3]->rollDeg() + 360.0, 360.0), 1e-9);
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
        // Achenbach pinned to the four this scenario's numbers were computed against — six real ones are
        // their own row's full 3.70 m stage width and can no longer be flanked to close a step.
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    'skram', 'flexy-folded-horn-hybrid', ['device' => 'achenbach-18', 'count' => 4],
                    'tecnare-m2122', 'eighteensound-2way-15',
                ],
            ]],
        ]);

        self::assertCount(23, $placed, 'every cabinet we own, in one stack');

        $fills = array_values(array_filter(
            $placed,
            static fn (PlacedDevice $e): bool => 'eighteensound-2way-15' === $e->device->id,
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
     * **A TOPS ROW THAT LANDS IN TWO RUNS STAYS ONE ROW**, and the outer run is not dealt to both ends of it.
     *
     * The psl stack is the shape that exposed this: six tiers of two mirrored ESXs and a row of five EF6s over
     * them. The tops row is 3.020 m on a 2.380 m support, so gravity splits it into a run of three carried by the
     * left ESX and a run of two, and {@see Stack::throwFirst} then solves the pair `clear_of` the three with the
     * side it is on. That solve used to read each copy's own offset as its column, so the pair was dealt
     * ±2.7735 m about its own centre rather than translated 0.0553 m to the right. One EF6 ended at +3.6435 m,
     * about a whole row width past its place, and 0.2020 m inside a cabinet of the stack standing next to it.
     *
     * Pinned as the four pitches rather than as five positions, because the pitches are what "one row" means: each
     * is the 0.588 m cabinet plus the 20 mm gap, widened a couple of per cent by the aim spread that
     * {@see SceneCompiler::clearedWithin} solves for, and not one of them is anywhere near a row width.
     *
     * **One stack on its own, so the numbers are a few millimetres tighter than the generated scene's.** A rig's
     * aim centre is worked out across every placement in it, so the three-stack scene aims this row from a centre
     * this fixture does not have and comes out at 0.6346 m in the middle. The shape is the assertion either way.
     */
    public function testATopsRowThatLandsInTwoRunsStaysOneRow(): void
    {
        $placed = $this->compile([
            ['id' => 'main', 'at' => [-0.042, 0.0], 'aim' => 'far', 'focus' => [
                'far' => ['distance_m' => 10.0, 'height_m' => 1.8],
                'near' => ['distance_m' => 2.0, 'height_m' => 1.8],
            ], 'stack' => [
                'interface_height_m' => 2.0, 'max_sub_height_m' => 3.0, 'gap_m' => 0.02,
                'slide_slack_m' => \INF, 'mirror_style' => 'centred',
                'from' => [
                    ['device' => 'concert-audio-esx', 'count' => 12, 'roll_mirror' => 90.0],
                    ['device' => 'concert-audio-ef6', 'count' => 5],
                ],
            ]],
        ]);

        $tops = array_map(
            static fn (PlacedDevice $e): float => $e->position[0],
            array_values(array_filter(
                $placed,
                static fn (PlacedDevice $e): bool => 'concert-audio-ef6' === $e->device->id,
            )),
        );
        sort($tops);
        self::assertCount(5, $tops);

        $pitches = [];
        for ($i = 1; $i < count($tops); ++$i) {
            $pitches[] = $tops[$i] - $tops[$i - 1];
        }

        // Four pitches, none of them a row width. The seam between the two runs is the solved 20 mm clearance
        // rather than the aim spread, so it is a hair tighter than the two inside the first run.
        self::assertEqualsWithDelta([0.6210, 0.6210, 0.6180, 0.6134], $pitches, 1e-4);

        // The row is 3.0 m of cabinet and the stack stands 2.38 m wide, so a split run showed up as a pitch of
        // roughly a row width. Stated as a bound as well as as numbers, because that is the property rather than
        // the arithmetic: nothing in this row may sit a row away from its neighbour.
        foreach ($pitches as $pitch) {
            self::assertLessThan(0.70, $pitch, 'a tops row cabinet sits further than one pitch from its neighbour');
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
        // Achenbach pinned to four — see testTheTopsOfAFlankedRigSitOutboardOnTheShoulders just above.
        $placed = $this->compile([
            ['id' => 'main', 'at' => [0.0, 0.0], 'aim' => 'focus', 'stack' => [
                'max_width_m' => 3.70, 'interface_height_m' => 2.0, 'gap_m' => 0.02,
                'from' => [
                    'flexy-folded-horn-hybrid', 'skram', ['device' => 'achenbach-18', 'count' => 4],
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

    /**
     * **`slide_slack_m` is read, and unstated it is `null` rather than a missing number.**.
     *
     * The distinction is the whole point of the key. Null means "a row may not be moved sideways to get it carried",
     * which is the right answer for a stack with a neighbour to slide into, and `.inf` means "bounded only by the
     * stage", which is what `scene:stack` solves a solo rig with. Until this key existed the file could state
     * neither, so every solo rig rebuilt under the stricter rule and came out as a different arrangement — see
     * {@see \App\Tests\Command\SceneStackCommandTest::testAWrittenSceneRebuildsToTheSubWallItsOwnHeaderReports}.
     *
     * `.inf` is asserted through the YAML rather than by handing the reader a PHP `INF`, because the spelling is
     * half of what could go wrong: `sprintf('%.4F', INF)` writes `INF`, which YAML reads as an ordinary word and the
     * reader then refuses as "expected a number".
     */
    public function testTheSlideSlackIsReadAndIsNullWhenNothingSaysOtherwise(): void
    {
        $parsed = Yaml::parse("from: [flexy-folded-horn-hybrid]\nslide_slack_m: .inf\n");
        self::assertInfinite(Stack::fromReader(new ArrayReader($parsed))->slideSlackM);

        $bounded = Yaml::parse("from: [flexy-folded-horn-hybrid]\nslide_slack_m: 0.15\n");
        self::assertSame(0.15, Stack::fromReader(new ArrayReader($bounded))->slideSlackM);

        $silent = Yaml::parse("from: [flexy-folded-horn-hybrid]\n");
        self::assertNull(Stack::fromReader(new ArrayReader($silent))->slideSlackM);
    }

    /**
     * **Every solve input a `Stack` carries has a key the scene can state, and the writer writes it.**.
     *
     * This is the invariant rather than the field. A generated scene holds constraints and is re-solved on every
     * build, so an input the file cannot express is an input the rebuild silently substitutes a default for — and
     * three separate releases have shipped that bug in three different places: the seating check, the aim the probe
     * judged tops at, and `slide_slack_m`, which turned a four-row rig into a 23.5 m line. Each was found by looking
     * at a render. Asserted structurally so the fourth one is found by this test instead.
     *
     * The key name is derived from the constructor parameter rather than listed here, because a list is a fourth
     * place to forget something. Every parameter maps by camelCase to snake_case, with a trailing `M` becoming the
     * `_m` suffix the schema uses everywhere.
     */
    public function testEveryStackInputCanBeStatedInASceneAndIsWrittenIntoOne(): void
    {
        $allowed = (new \ReflectionClass(Stack::class))->getMethod('fromReader');
        $source = (string) file_get_contents((string) $allowed->getFileName());

        foreach ((new \ReflectionClass(Stack::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $key = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $parameter->getName()));

            self::assertStringContainsString(
                "'".$key."'",
                $source,
                sprintf('Stack::$%s is a solve input no scene can state, so a rebuild will not reproduce it', $parameter->getName()),
            );
        }

        // And the other direction: a stack that states everything is written out stating everything. A key the reader
        // accepts but the writer never emits is the same defect seen from the generator's side.
        $stated = new Stack(
            from: [new \App\Scene\StackEntry('flexy-folded-horn-hybrid')],
            maxWidthM: 3.7,
            minWidthM: 2.1,
            maxHeightM: 4.2,
            interfaceHeightM: 1.9,
            gapM: 0.03,
            mirror: true,
            maxSubHeightM: 2.9,
            shape: \App\Scene\StackShape::Pyramid,
            mirrorStyle: \App\Scene\MirrorStyle::Column,
            slideSlackM: INF,
            targetSubHeightM: 2.4,
        );
        $written = \App\Scene\StackSceneWriter::yaml(
            'zz-round-trip',
            'round trip',
            [new \App\Scene\StackBlock('main', 'a', $stated, [], ['flexy-folded-horn-hybrid'], [])],
            [0.0, 0.0],
            0.5,
        );

        foreach ([
            'max_width_m', 'min_width_m', 'max_height_m', 'interface_height_m', 'gap_m', 'mirror',
            'max_sub_height_m', 'target_sub_height_m', 'shape', 'mirror_style', 'slide_slack_m',
        ] as $key) {
            self::assertStringContainsString($key.':', $written, $key.' is accepted by the reader and never written');
        }

        // It has to load again, which is what catches `INF` being written as a word YAML reads as a string. `from` is
        // supplied here rather than read back: the writer counts it off the tiers and this block has none, since what
        // is under test is the constraint set rather than the deal.
        $reread = Stack::fromReader(new ArrayReader(
            ['from' => ['flexy-folded-horn-hybrid']] + Yaml::parse($written)['placements'][0]['stack'],
        ));
        self::assertInfinite($reread->slideSlackM);
        self::assertSame(3.7, $reread->maxWidthM);
        self::assertSame(2.4, $reread->targetSubHeightM);
        self::assertSame(\App\Scene\StackShape::Pyramid, $reread->shape);
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
     *
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
     *
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
     *
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
