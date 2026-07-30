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
            // Tapered, so it can form an arc on its own taper the way a real top does. No grille, to
            // keep the angles the same round numbers ArcTest uses.
            'top-taper' => SpecFactory::spec([
                'id' => 'top-taper',
                'geometry' => [
                    'shape' => 'trapezoid',
                    'dimensions_m' => ['width' => 0.5, 'height' => 0.9, 'depth' => 0.5],
                    'back_width_m' => 0.35,
                ],
                'appearance' => ['color' => '#111111', 'grille' => null],
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

        self::assertSame(180.0, $placed[0]->rollDeg());
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

    public function testAimingAtAPointResolvesYawAndTilt(): void
    {
        // The `top` fixture is 0.9 m high, so its mid-height — where aiming is measured from — is 0.45 m.
        // A target 10 m in front, 2 m to the right and below that height: turn right, tilt down.
        $down = $this->compile([
            ['id' => 'aimed', 'device' => 'top', 'at' => [0.0, 0.0], 'aim_at' => [2.0, -10.0, 0.0]],
        ]);

        self::assertGreaterThan(0.0, $down[0]->yawDeg(), 'target is to the right, so yaw is positive');
        self::assertEqualsWithDelta(rad2deg(atan2(2.0, 10.0)), $down[0]->yawDeg(), 1e-6);
        self::assertGreaterThan(0.0, $down[0]->pitchDeg(), 'target below mid-height, so nose-down');

        // And a target above it tilts the other way, which is what a floor-standing top under a
        // balcony would need.
        $up = $this->compile([
            ['id' => 'aimed', 'device' => 'top', 'at' => [0.0, 0.0], 'aim_at' => [0.0, -10.0, 3.0]],
        ]);

        self::assertLessThan(0.0, $up[0]->pitchDeg(), 'target above mid-height, so nose-up');
    }

    public function testAimingIsMeasuredFromMidHeightNotTheBase(): void
    {
        // A target at exactly the cabinet's mid-height needs no tilt at all. Aiming from the base
        // would produce a spurious upward tilt here.
        $placed = $this->compile([
            ['id' => 'level', 'device' => 'top', 'at' => [0.0, 0.0], 'aim_at' => [0.0, -10.0, 0.45]],
        ]);

        self::assertEqualsWithDelta(0.0, $placed[0]->pitchDeg(), 1e-9);
    }

    public function testARepeatedRowAimsEachCopySeparately(): void
    {
        $placed = $this->compile([
            [
                'id' => 'row', 'device' => 'top', 'at' => [-2.0, 0.0],
                'aim_at' => [0.0, -10.0, 0.45],
                'repeat' => ['count' => 3, 'step' => [2.0, 0.0, 0.0]],
            ],
        ]);

        // Left of the target turns right, centred turns not at all, right of it turns left.
        self::assertGreaterThan(0.0, $placed[0]->yawDeg());
        self::assertEqualsWithDelta(0.0, $placed[1]->yawDeg(), 1e-9);
        self::assertEqualsWithDelta(-$placed[0]->yawDeg(), $placed[2]->yawDeg(), 1e-9);
    }

    public function testPitchTiltsAndKeepsTheCabinetOnItsSlot(): void
    {
        $placed = $this->compile([
            ['id' => 'tilted', 'device' => 'top', 'at' => [0.0, 0.0], 'pitch_deg' => 10.0],
        ]);

        self::assertSame(10.0, $placed[0]->pitchDeg());
        self::assertGreaterThan(0.0, $placed[0]->zLift(), 'tilting drops a corner below the slot');
        self::assertEqualsWithDelta(0.0, $placed[0]->worldBox()['min'][2], 1e-9, 'lowest point back on the slot');
        self::assertGreaterThan(0.5, $placed[0]->extent()[1], 'a tilted cabinet reaches further front-to-back');
    }

    public function testAimingAndExplicitAnglesTogetherAreRejected(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene([
            ['id' => 'a', 'device' => 'top', 'at' => [0.0, 0.0], 'aim' => 'focus', 'yaw_deg' => 10.0],
        ]));

        $messages = array_map(static fn ($v): string => $v->message, $result['violations']);
        self::assertNotSame([], $messages);
        self::assertStringContainsString('aiming already sets yaw and pitch', $messages[0]);
    }

    public function testYawIsCarriedThrough(): void
    {
        $placed = $this->compile([
            ['id' => 'angled', 'device' => 'top', 'at' => [0.0, 0.0], 'yaw_deg' => 30.0],
        ]);

        self::assertSame(30.0, $placed[0]->yawDeg());
    }

    public function testSwappingASingleTopForAnArcLeavesTheMiddleCabinetWhereItWas(): void
    {
        // The property that makes an arc a drop-in replacement for one placement.
        $single = $this->compile([['id' => 'solo', 'device' => 'top-taper', 'at' => [1.0, 2.0]]]);
        $arc = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [1.0, 2.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);

        self::assertCount(3, $arc);
        self::assertSame($single[0]->position, $arc[1]->position);
        self::assertSame(0.0, $arc[1]->yawDeg());
    }

    public function testAnArcNumbersItsCabinetsTheWayARepeatDoes(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);

        self::assertSame(['tops-1', 'tops-2', 'tops-3'], array_map(static fn ($p) => $p->placementId, $placed));
    }

    public function testAnArcFansOutwardsInConvexAndInwardsInConcave(): void
    {
        $convex = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);
        $concave = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0],
                'arc' => ['mode' => 'concave', 'count' => 3, 'splay_deg' => 20.0]],
        ]);

        // Symmetric either way, and the outer cabinets turn opposite ways between the two modes.
        self::assertEqualsWithDelta(-$convex[2]->yawDeg(), $convex[0]->yawDeg(), 1e-9);
        self::assertLessThan(0.0, $convex[0]->yawDeg());
        self::assertGreaterThan(0.0, $concave[0]->yawDeg());
        // Convex ends sit behind the middle cabinet, concave ends in front of it.
        self::assertGreaterThan(0.0, $convex[0]->position[1]);
        self::assertLessThan(0.0, $concave[0]->position[1]);
    }

    public function testAnArcTakesYawFromTheGeometryAndPitchFromTheFocus(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'aim' => 'focus',
                'arc' => ['mode' => 'convex', 'count' => 3]],
        ], ['distance_m' => 10.0, 'height_m' => 1.8]);

        // Yaw is the arc's, untouched by the aim: aimed without an arc these three would all be within a
        // degree of straight ahead, so this is what proves the focus did not flatten the fan. The
        // expected angle comes from the fixture's own taper — 2·atan(half the taper / the depth it runs
        // over) — foreshortened by the tilt, not from anything the compiler worked out.
        $taper = (0.5 - 0.35) / 2;
        $splay = rad2deg(2 * atan($taper / (0.5 * cos(deg2rad($placed[1]->pitchDeg())))));

        self::assertEqualsWithDelta(-$splay, $placed[0]->yawDeg(), 1e-9);
        self::assertSame(0.0, $placed[1]->yawDeg());
        self::assertEqualsWithDelta($splay, $placed[2]->yawDeg(), 1e-9);
        self::assertGreaterThan(17.0, $splay);

        // Every cabinet is still tilted towards the focus, symmetrically. These stand on the floor and
        // the focus is at ear height above them, so the tilt is upward — the sign follows the geometry
        // rather than an assumption that a top always aims down.
        foreach ($placed as $entry) {
            self::assertLessThan(0.0, $entry->pitchDeg());
        }
        self::assertEqualsWithDelta($placed[2]->pitchDeg(), $placed[0]->pitchDeg(), 1e-9);
        // And the outer cabinets need *more* of it than the middle one, because the focus sits off their
        // own axis — the correction a plain aim would miss.
        self::assertGreaterThan(abs($placed[1]->pitchDeg()), abs($placed[0]->pitchDeg()));
    }

    public function testAnArcWithoutAnAimStandsLevel(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);

        foreach ($placed as $entry) {
            self::assertSame(0.0, $entry->pitchDeg());
        }
    }

    public function testAnArcStacksOnARowAndTakesItsHeightFromBelow(): void
    {
        $placed = $this->compile([
            ['id' => 'subs', 'device' => 'sub', 'at' => [0.0, 0.0], 'repeat' => ['count' => 3, 'step' => [0.6, 0, 0]]],
            ['id' => 'tops', 'device' => 'top-taper', 'on' => 'subs', 'at' => [0.6, 0.0],
                'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);

        foreach (array_slice($placed, 3) as $entry) {
            self::assertSame(0.6, $entry->position[2]);
        }
    }

    public function testOnAnArcInheritsItsMiddleCabinet(): void
    {
        // The middle cabinet is the one standing on `at`, so it is the only sensible anchor — an outer
        // one would drag whatever is stacked on it sideways and out of the fan.
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [1.0, 2.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
            ['id' => 'above', 'device' => 'top', 'on' => 'tops'],
        ]);

        self::assertSame([1.0, 2.0], [$placed[3]->position[0], $placed[3]->position[1]]);
        self::assertSame(0.9, $placed[3]->position[2]);
    }

    public function testTheFocusAccountsForAnArcsRotatedFootprint(): void
    {
        // A concave arc's outer cabinets stand well in front of its middle one. Measuring the rig's front
        // face on unrotated boxes would put the focus 6 cm further out than the scene asked for.
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'aim' => 'focus',
                'arc' => ['mode' => 'concave', 'count' => 3, 'splay_deg' => 20.0]],
        ], ['distance_m' => 10.0, 'height_m' => 1.8]);

        $frontmost = min(array_map(static fn ($p) => $p->worldBox()['min'][1], $placed));
        self::assertLessThan(-0.3, $frontmost, 'the outer cabinets reach well past the middle one');

        // Read the front face back out of the tilt the middle cabinet was given: the focus sits 10 m
        // ahead of it, and the drop to ear height is fixed, so the tilt says where the compiler thought
        // the rig's front face was.
        $drop = 1.8 - 0.9 / 2;
        $impliedFront = 10.0 - abs($drop / tan(deg2rad($placed[1]->pitchDeg())));

        // Measured on unrotated boxes it would have been the middle cabinet's own front face, −0.25.
        self::assertLessThan(-0.3, $impliedFront, 'the focus was measured from the arc, not from a box');
        self::assertEqualsWithDelta($frontmost, $impliedFront, 0.03, 'and from very nearly the real one');
    }

    public function testRollStillTurnsAnArcedCabinetOverWithoutChangingWhereItPoints(): void
    {
        $upright = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);
        $rolled = $this->compile([
            ['id' => 'tops', 'device' => 'top-taper', 'at' => [0.0, 0.0], 'roll_deg' => 180.0,
                'arc' => ['mode' => 'convex', 'count' => 3]],
        ]);

        self::assertEqualsWithDelta($upright[0]->position, $rolled[0]->position, 1e-12);
        self::assertEqualsWithDelta(
            $upright[0]->frontDirection(),
            $rolled[0]->frontDirection(),
            1e-12,
        );
        self::assertEqualsWithDelta(0.9, $rolled[0]->zLift(), 1e-12);
    }

    public function testASingleCabinetArcIsJustOneCabinet(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'sub', 'at' => [1.0, 2.0], 'arc' => ['mode' => 'concave', 'count' => 1]],
        ]);

        self::assertCount(1, $placed);
        self::assertSame('tops', $placed[0]->placementId);
        self::assertSame([1.0, 2.0, 0.0], $placed[0]->position);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, string}>
     */
    public static function rejectionCases(): iterable
    {
        yield 'arc and repeat together' => [
            [['id' => 'a', 'device' => 'top-taper', 'at' => [0, 0],
                'repeat' => ['count' => 2, 'step' => [1, 0, 0]], 'arc' => ['mode' => 'convex', 'count' => 3]]],
            'use either `repeat` or `arc`, not both',
        ];
        yield 'arc with an explicit yaw' => [
            [['id' => 'a', 'device' => 'top-taper', 'at' => [0, 0], 'yaw_deg' => 10.0,
                'arc' => ['mode' => 'convex', 'count' => 3]]],
            'the arc already sets yaw — remove yaw_deg',
        ];
        yield 'arc on a cabinet rolled onto its side' => [
            [['id' => 'a', 'device' => 'top-taper', 'at' => [0, 0], 'roll_deg' => 90.0,
                'arc' => ['mode' => 'convex', 'count' => 3]]],
            'puts its width on the vertical',
        ];
        yield 'arc of untapered boxes without an angle' => [
            [['id' => 'a', 'device' => 'sub', 'at' => [0, 0], 'arc' => ['mode' => 'convex', 'count' => 3]]],
            'so a convex arc needs an explicit arc.splay_deg or arc.radius_m',
        ];
        yield 'concave arc without an angle' => [
            [['id' => 'a', 'device' => 'top-taper', 'at' => [0, 0], 'arc' => ['mode' => 'concave', 'count' => 3]]],
            'a concave arc has no tightest angle',
        ];
        yield 'arc wrapping past a full circle' => [
            [['id' => 'a', 'device' => 'top-taper', 'at' => [0, 0],
                'arc' => ['mode' => 'convex', 'count' => 5, 'splay_deg' => 90.0]]],
            'wrap past a full circle',
        ];
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
     * @param array<string, mixed>|null $focus
     * @return list<\App\Scene\PlacedDevice>
     */
    private function compile(array $placements, ?array $focus = null): array
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($placements, $focus));

        self::assertSame([], array_map(static fn ($v): string => $v->message, $result['violations']));

        return $result['placed'];
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @param array<string, mixed>|null $focus
     */
    private function scene(array $placements, ?array $focus = null): SceneSpec
    {
        $data = ['id' => 'test', 'name' => 'Test scene', 'placements' => $placements];
        if ($focus !== null) {
            $data['focus'] = $focus;
        }

        return SceneSpec::fromArray($data, '/scenes/test.yaml');
    }
}
