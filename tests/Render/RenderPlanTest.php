<?php

declare(strict_types=1);

namespace App\Tests\Render;

use App\Render\CameraPreset;
use App\Render\CameraStand;
use App\Render\LightingPreset;
use App\Render\RenderPlan;
use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class RenderPlanTest extends TestCase
{
    public function testBoundsCoverCabinetExtentAndStackHeight(): void
    {
        $placed = [
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [2.0, 0.0, 1.0]),
        ];

        $bounds = RenderPlan::bounds($placed);

        // 1 m cubes at x = 0 and x = 2 → -0.5 .. 2.5; the upper one tops out at 2.0.
        self::assertSame([-0.5, -0.5, 0.0], $bounds['min']);
        self::assertSame([2.5, 0.5, 2.0], $bounds['max']);
    }

    public function testAYawedCabinetIsBoundedExactly(): void
    {
        // A 1.0 x 0.4 cabinet turned 45 deg reaches (1.0 + 0.4) / 2 * cos(45 deg) = 0.4950 in both
        // directions — the exact rotated box, not the old max(w, d) approximation.
        $device = SpecFactory::spec([
            'geometry' => ['dimensions_m' => ['width' => 1.0, 'height' => 1.0, 'depth' => 0.4]],
        ]);

        $straight = RenderPlan::bounds([new PlacedDevice('a', $device, [0.0, 0.0, 0.0], new Orientation())]);
        $turned = RenderPlan::bounds([new PlacedDevice('a', $device, [0.0, 0.0, 0.0], new Orientation(0.0, 0.0, 45.0))]);

        self::assertEqualsWithDelta(0.2, $straight['max'][1], 1e-9);
        self::assertEqualsWithDelta(0.4950, $turned['max'][1], 1e-4);
    }

    public function testCameraLooksAtTheSceneCentre(): void
    {
        $placed = [
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [4.0, 0.0, 0.0]),
        ];

        $plan = RenderPlan::forScene($placed);

        self::assertSame([2.0, 0.0, 0.5], $plan['camera']['target']);
    }

    public function testCameraRetreatsAsTheSceneGrows(): void
    {
        // The whole reason the framing is computed rather than hardcoded: one preset has to work for
        // a single monitor and for a 14-cabinet wall.
        $small = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])]);
        $large = RenderPlan::forScene([
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [10.0, 0.0, 0.0]),
        ]);

        self::assertGreaterThan(
            $this->distance($small),
            $this->distance($large),
            'a wider rig must be framed from further away',
        );
        self::assertGreaterThan($small['scene_bounds']['radius'], $large['scene_bounds']['radius']);
    }

    public function testAWideShallowSceneIsFramedTighterThanItsBoundingSphere(): void
    {
        // The case that forced box-projection framing: a sub wall's bounding sphere is far larger
        // than its silhouette, so fitting the sphere left the rig as a smudge in the frame's middle.
        $wide = [];
        for ($i = 0; $i < 8; ++$i) {
            $wide[] = $this->at($this->cube(), [$i * 1.1, 0.0, 0.0]);
        }

        $plan = RenderPlan::forScene($wide, CameraPreset::Front, resolution: [1600, 900]);
        $radius = $plan['scene_bounds']['radius'];

        // What a bounding-sphere fit would have demanded, for comparison: the sphere has to fit the
        // narrower field of view, which on a 16:9 frame is the vertical one.
        $lens = 42.0;
        $fovVertical = 2 * atan((36.0 * (900 / 1600)) / (2 * $lens));
        $sphereFit = ($radius / sin($fovVertical / 2)) * 1.15;

        self::assertLessThan(
            $sphereFit * 0.75,
            $this->distance($plan),
            'box-projection framing must comfortably beat a sphere fit on a wide, shallow scene',
        );
    }

    public function testATallSceneIsStillFullyFramed(): void
    {
        // The other direction: fitting width alone must not crop a stack.
        $tall = [
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [0.0, 0.0, 1.0]),
            $this->at($this->cube(), [0.0, 0.0, 2.0]),
            $this->at($this->cube(), [0.0, 0.0, 3.0]),
        ];

        $plan = RenderPlan::forScene($tall, CameraPreset::Front, resolution: [1600, 900]);
        $height = $plan['scene_bounds']['max'][2] - $plan['scene_bounds']['min'][2];

        // Vertical half-angle at 42 mm on a 16:9 frame is ~13.6°, so 4 m of stack needs ~8 m back.
        $lens = 42.0;
        $fovVertical = 2 * atan((36.0 * (900 / 1600)) / (2 * $lens));
        $needed = ($height / 2) / tan($fovVertical / 2);

        self::assertGreaterThanOrEqual($needed, $this->distance($plan));
    }

    public function testAimLinesAreOffByDefaultAndFramedWhenOn(): void
    {
        $top = SpecFactory::spec([
            'subtype' => 'top',
            'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 1.0, 'depth' => 0.5]],
        ]);
        // Tilted down, so the ray meets the floor a couple of metres out in front.
        $placed = [new PlacedDevice('t', $top, [0.0, 0.0, 0.0], new Orientation(20.0))];

        $off = RenderPlan::forScene($placed);
        $on = RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_TOPS);

        self::assertSame([], $off['aim_lines'], 'aim lines are opt-in');
        self::assertCount(1, $on['aim_lines']);
        self::assertTrue($on['aim_lines'][0]['hits_floor']);
        self::assertEqualsWithDelta(0.0, $on['aim_lines'][0]['end'][2], 1e-6, 'the ray stops at the floor');
        self::assertLessThan(
            $off['scene_bounds']['min'][1],
            $on['scene_bounds']['min'][1],
            'switching lines on has to widen the framing to include where they land',
        );
    }

    public function testANearlyLevelAimLineDoesNotClaimToHitTheFloor(): void
    {
        // 1 degree of down-tilt from 2 m up meets the floor 115 m out — far past the cap. The ray is
        // truncated, so it must not report a landing: otherwise the floor marker is drawn beneath a
        // line that stopped in mid-air, which is exactly what shipped in 0.13.0.
        $top = SpecFactory::spec([
            'subtype' => 'top',
            'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 1.0, 'depth' => 0.5]],
        ]);
        $placed = [new PlacedDevice('t', $top, [0.0, 0.0, 1.5], new Orientation(1.0))];

        $line = RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_TOPS)['aim_lines'][0];

        self::assertFalse($line['hits_floor']);
        self::assertGreaterThan(0.5, $line['end'][2], 'the truncated ray ends well above the floor');
    }

    public function testAimLinesForTopsOnlySkipSubs(): void
    {
        $sub = SpecFactory::spec(['id' => 'sub', 'subtype' => 'sub']);
        $top = SpecFactory::spec(['id' => 'top', 'subtype' => 'top']);
        $placed = [
            new PlacedDevice('s', $sub, [0.0, 0.0, 0.0], new Orientation()),
            new PlacedDevice('t', $top, [2.0, 0.0, 0.0], new Orientation()),
        ];

        self::assertCount(1, RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_TOPS)['aim_lines']);
        self::assertCount(2, RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_ALL)['aim_lines']);
    }

    public function testEachCameraPresetSitsOnTheSideItsNameImplies(): void
    {
        $placed = [$this->at($this->cube(), [0.0, 0.0, 0.0])];

        $front = RenderPlan::forScene($placed, CameraPreset::Front)['camera']['location'];
        $side = RenderPlan::forScene($placed, CameraPreset::Side)['camera']['location'];
        $top = RenderPlan::forScene($placed, CameraPreset::Top)['camera']['location'];

        // Cabinets face −Y, so a viewer is at negative Y.
        self::assertLessThan(0.0, $front[1]);
        self::assertEqualsWithDelta(0.0, $front[0], 1e-9, 'the front view is centred on X');
        self::assertGreaterThan(0.0, $side[0], 'the side view stands off to the right');
        self::assertGreaterThan($front[2], $top[2], 'the top view is higher than the front view');
    }

    public function testTheCrowdCameraStandsAtEyeHeightAndAimsLow(): void
    {
        $placed = [
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [0.0, 0.0, 1.0]),
        ];

        $plan = RenderPlan::forScene($placed, CameraPreset::Crowd);

        self::assertSame(1.65, $plan['camera']['location'][2]);
        self::assertLessThan(
            $plan['scene_bounds']['max'][2] / 2,
            $plan['camera']['target'][2],
            'aiming below the centre makes the rig tower over the viewer',
        );
    }

    public function testAreaLightPowerScalesWithSceneSizeButSunDoesNot(): void
    {
        $small = [$this->at($this->cube(), [0.0, 0.0, 0.0])];
        $large = [$this->at($this->cube(), [0.0, 0.0, 0.0]), $this->at($this->cube(), [8.0, 0.0, 0.0])];

        $studioSmall = RenderPlan::forScene($small, lighting: LightingPreset::Studio);
        $studioLarge = RenderPlan::forScene($large, lighting: LightingPreset::Studio);
        $sunSmall = RenderPlan::forScene($small, lighting: LightingPreset::Daylight);
        $sunLarge = RenderPlan::forScene($large, lighting: LightingPreset::Daylight);

        self::assertGreaterThan(
            $studioSmall['lighting']['lights'][0]['energy'],
            $studioLarge['lighting']['lights'][0]['energy'],
            'a bigger rig needs more light to reach the same brightness',
        );
        self::assertSame(
            $sunSmall['lighting']['lights'][0]['energy'],
            $sunLarge['lighting']['lights'][0]['energy'],
            'a sun is irradiance and does not fall off',
        );
    }

    public function testLightsStayAboveTheFloor(): void
    {
        $plan = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])], lighting: LightingPreset::Flat);

        foreach ($plan['lighting']['lights'] as $light) {
            self::assertGreaterThan(0.0, $light['location'][2], $light['name'].' is underground');
        }
    }

    public function testLightingPresetDrivesGroundAndBackground(): void
    {
        $studio = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])], lighting: LightingPreset::Studio);
        $daylight = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])], lighting: LightingPreset::Daylight);

        self::assertNotSame($studio['world']['color'], $daylight['world']['color']);
        self::assertNotSame($studio['ground']['color'], $daylight['ground']['color']);
        self::assertTrue($studio['ground']['enabled']);
    }

    public function testGroundCanBeTurnedOffAndRenderSettingsPassThrough(): void
    {
        $plan = RenderPlan::forScene(
            [$this->at($this->cube(), [0.0, 0.0, 0.0])],
            samples: 12,
            resolution: [640, 480],
            ground: false,
        );

        self::assertFalse($plan['ground']['enabled']);
        self::assertSame(12, $plan['render']['samples']);
        self::assertSame([640, 480], $plan['render']['resolution']);
        self::assertSame(LightingPreset::Studio->exposure(), $plan['render']['exposure']);
    }

    public function testAnEmptySceneStillProducesAUsablePlan(): void
    {
        $plan = RenderPlan::forScene([]);

        self::assertGreaterThan(0.0, $plan['scene_bounds']['radius']);
        self::assertNotSame($plan['camera']['location'], $plan['camera']['target']);
    }

    /**
     * **12 m in front of the front row at 2 m**, asked for on 2026-10-02 to compare two events from where an audience
     * stands. The distance runs from the rig's nearest face, so the cube's front at y −0.5 puts the camera at −12.5.
     */
    public function testAStatedStandPutsTheCameraThereAndAimsAtTheRig(): void
    {
        $plan = RenderPlan::forScene(
            [$this->at($this->cube(), [0.0, 0.0, 0.0])],
            CameraPreset::Front,
            stand: new CameraStand(12.0, 2.0),
        );

        self::assertEqualsWithDelta([0.0, -12.5, 2.0], $plan['camera']['location'], 1e-9);
        self::assertEqualsWithDelta([0.0, 0.0, 0.5], $plan['camera']['target'], 1e-9);
    }

    /** The same stand sees a wider rig from the same place, so it is the lens that gives, not the distance. */
    public function testAStatedStandZoomsRatherThanRetreats(): void
    {
        $stand = new CameraStand(12.0, 2.0);
        $narrow = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])], CameraPreset::Front, stand: $stand);
        $wide = RenderPlan::forScene([
            $this->at($this->cube(), [-3.0, 0.0, 0.0]),
            $this->at($this->cube(), [3.0, 0.0, 0.0]),
        ], CameraPreset::Front, stand: $stand);

        self::assertEqualsWithDelta($narrow['camera']['location'], $wide['camera']['location'], 1e-9);
        self::assertGreaterThan($wide['camera']['lens_mm'], $narrow['camera']['lens_mm']);
        // 7 m wide with its front corners 12 m away, so a half-angle of atan(3.5 / 12) and the 1.15 margin on it.
        self::assertEqualsWithDelta(36.0 / (2 * 3.5 / 12.0 * 1.15), $wide['camera']['lens_mm'], 0.5);
    }

    /** Without a distance the framing still fits the rig, and an eye height alone only lifts or lowers it. */
    public function testAnEyeHeightAloneKeepsTheFittedDistance(): void
    {
        $placed = [$this->at($this->cube(), [0.0, 0.0, 0.0])];
        $fitted = RenderPlan::forScene($placed, CameraPreset::Front);
        $raised = RenderPlan::forScene($placed, CameraPreset::Front, stand: new CameraStand(eyeHeightM: 3.0));

        self::assertSame(3.0, $raised['camera']['location'][2]);
        self::assertSame($fitted['camera']['location'][1], $raised['camera']['location'][1]);
        self::assertSame($fitted['camera']['lens_mm'], $raised['camera']['lens_mm']);
    }

    /** A stated stand gets its own file name, so it never overwrites the fitted picture of the same preset. */
    public function testAStandNamesItselfAndRefusesNothingLeftToStandOn(): void
    {
        self::assertSame('', (new CameraStand())->suffix());
        self::assertSame('-12m-2m-high', (new CameraStand(12.0, 2.0))->suffix());
        self::assertSame('-7.5m', (new CameraStand(7.5))->suffix());

        $this->expectException(\InvalidArgumentException::class);
        new CameraStand(0.0);
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function distance(array $plan): float
    {
        $location = $plan['camera']['location'];
        $target = $plan['camera']['target'];

        return sqrt(
            ($location[0] - $target[0]) ** 2
            + ($location[1] - $target[1]) ** 2
            + ($location[2] - $target[2]) ** 2,
        );
    }

    private function cube(): DeviceSpec
    {
        return SpecFactory::spec([
            'geometry' => ['dimensions_m' => ['width' => 1.0, 'height' => 1.0, 'depth' => 1.0]],
        ]);
    }

    /**
     * @param array{float, float, float} $position
     */
    private function at(DeviceSpec $device, array $position): PlacedDevice
    {
        return new PlacedDevice('p-'.$position[0].'-'.$position[2], $device, $position, new Orientation());
    }

    /**
     * **Off unless asked for**, like the aim lines. A render is the thing everybody looks at and most of them want
     * the picture rather than the annotation.
     */
    public function testThereAreNoLabelsUnlessTheyAreAskedFor(): void
    {
        $plan = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])]);

        self::assertSame([], $plan['labels']);
    }

    /**
     * **One label per device, not per unit, with the count in the text.** Seven Flexys labelled seven times is noise
     * rather than information — and on the packed convoy that is the difference between eighteen labels and thirty.
     */
    public function testUnitsOfOneDeviceShareALabelThatCountsThem(): void
    {
        $cube = $this->cube();
        $placed = [
            $this->at($cube, [0.0, 0.0, 0.0]),
            $this->at($cube, [1.2, 0.0, 0.0]),
            $this->at($cube, [2.4, 0.0, 0.0]),
        ];

        $plan = RenderPlan::forScene($placed, labels: true);
        $named = array_values(array_filter(
            $plan['labels'],
            static fn (array $l): bool => str_contains($l['text'], $cube->id),
        ));

        self::assertCount(1, $named, 'three of one device is one label');
        self::assertStringContainsString('3×', $named[0]['text']);
    }

    /**
     * **The same device in two vehicles is two labels**, because that is two facts. Grouping by device alone would
     * say "12× flexy" once and tell a loader nothing about which van to put them in.
     */
    public function testTheSameDeviceInTwoVehiclesIsLabelledTwice(): void
    {
        $cube = $this->cube();
        $placed = [
            $this->vehicle('van-a', [0.0, 0.0, 0.0]),
            $this->at($cube, [0.0, 0.0, 0.0]),
            $this->vehicle('van-b', [0.0, 10.0, 0.0]),
            $this->at($cube, [0.0, 10.0, 0.0]),
        ];

        $plan = RenderPlan::forScene($placed, labels: true);
        $named = array_filter(
            $plan['labels'],
            static fn (array $l): bool => str_contains($l['text'], $cube->id),
        );

        self::assertCount(2, $named, 'one label per device per vehicle');
    }

    /**
     * **A legend only claims what the picture can contain.** A rig has no cages and no wheel arches, so naming them
     * would be a legend a reader trusts about things that are not there — worse than no legend at all.
     */
    public function testTheLegendOnlyNamesWhatTheSceneCanShow(): void
    {
        $rig = RenderPlan::forScene([$this->at($this->cube(), [0.0, 0.0, 0.0])], labels: true);
        $convoy = RenderPlan::forScene([
            $this->vehicle('van-a', [0.0, 0.0, 0.0]),
            $this->at($this->cube(), [0.0, 0.0, 0.0]),
        ], labels: true);

        $text = static fn (array $plan): string => implode("\n", array_column($plan['labels'], 'text'));

        self::assertStringContainsString('LEGEND', $text($rig));
        self::assertStringNotContainsString('wheel arch', $text($rig), 'a rig has no arches');
        self::assertStringContainsString('wheel arch', $text($convoy));
        self::assertStringContainsString('load bay', $text($convoy));
    }

    /**
     * **The legend stands clear of the scene in x**, which the first version did not: it was offset by a tenth of the
     * radius, 0.9 m on the packed convoy against vans 2 m wide, so it landed on top of the Movano and read as text
     * painted across its side.
     */
    public function testTheLegendStandsClearOfTheScene(): void
    {
        $placed = [$this->vehicle('van-a', [0.0, 0.0, 0.0]), $this->at($this->cube(), [0.0, 0.0, 0.0])];
        $plan = RenderPlan::forScene($placed, labels: true);
        $widest = $plan['scene_bounds']['max'][0];

        $legend = array_values(array_filter(
            $plan['labels'],
            static fn (array $l): bool => str_contains($l['text'], 'LEGEND') || str_contains($l['text'], '='),
        ));

        self::assertNotSame([], $legend);
        foreach ($legend as $line) {
            self::assertGreaterThan($widest, $line['at'][0], 'the legend is inside the scene');
        }
    }

    /**
     * A vehicle, for the label tests. Two metres wide, so a legend offset that fails to clear it is caught.
     *
     * @param array{float, float, float} $position
     */
    private function vehicle(string $id, array $position): PlacedDevice
    {
        $spec = SpecFactory::spec([
            'id' => $id,
            'category' => 'vehicle',
            'subtype' => 'van',
            'build' => 'original',
            'clone_of' => null,
            'audio' => null,
            'geometry' => [
                'shape' => 'load-bay',
                'dimensions_m' => ['width' => 2.0, 'height' => 2.5, 'depth' => 5.0],
            ],
            'vehicle' => ['permitted_gross_kg' => 3500.0],
        ]);

        return new PlacedDevice($id, $spec, $position, new Orientation());
    }

    /**
     * A placement can ask for a line the mode would have skipped — a sub whose aim you want to see — and
     * refuse one the mode would have drawn. That is the whole point of `aim_lines` per group.
     */
    public function testAPlacementCanOverruleTheModeEitherWay(): void
    {
        $sub = SpecFactory::spec(['id' => 'sub', 'subtype' => 'sub']);
        $top = SpecFactory::spec(['id' => 'top', 'subtype' => 'top']);

        $placed = [
            new PlacedDevice('quiet-sub', $sub, [0.0, 0.0, 1.0], new Orientation(10.0)),
            new PlacedDevice('shown-sub', $sub, [2.0, 0.0, 1.0], new Orientation(10.0), true, true),
            new PlacedDevice('hidden-top', $top, [4.0, 0.0, 1.0], new Orientation(10.0), true, false),
            new PlacedDevice('normal-top', $top, [6.0, 0.0, 1.0], new Orientation(10.0)),
        ];

        $plan = RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_TOPS);
        $ids = array_column($plan['aim_lines'], 'placement_id');

        self::assertSame(['shown-sub', 'normal-top'], $ids);
    }

    /**
     * The one thing a placement cannot overrule. `--aim-lines=none` stays the way to get a clean render of
     * a scene that normally draws them.
     */
    public function testAskingForNoAimLinesAtAllOverrulesEveryPlacement(): void
    {
        $top = SpecFactory::spec(['id' => 'top', 'subtype' => 'top']);
        $placed = [new PlacedDevice('insistent', $top, [0.0, 0.0, 1.0], new Orientation(10.0), true, true)];

        $plan = RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_NONE);

        self::assertSame([], $plan['aim_lines']);
    }

    public function testAllStillDrawsEverythingWhateverTheSubtype(): void
    {
        $sub = SpecFactory::spec(['id' => 'sub', 'subtype' => 'sub']);
        $placed = [
            new PlacedDevice('a', $sub, [0.0, 0.0, 1.0], new Orientation(10.0)),
            new PlacedDevice('b', $sub, [2.0, 0.0, 1.0], new Orientation(10.0)),
        ];

        self::assertCount(2, RenderPlan::forScene($placed, aimLines: RenderPlan::AIM_ALL)['aim_lines']);
    }

    /**
     * The quality ladder, level by level. Each named level resolves to its own pair, and no level means the
     * default pair — which is what makes the flags a shorthand for numbers written down in one place.
     */
    public function testEachQualityLevelResolvesToItsOwnResolutionAndSamples(): void
    {
        self::assertSame(
            ['samples' => 16, 'resolution' => [960, 540]],
            RenderPlan::quality(RenderPlan::QUICK),
        );
        self::assertSame(
            ['samples' => 128, 'resolution' => [1920, 1080]],
            RenderPlan::quality(null),
        );
        self::assertSame(
            ['samples' => 384, 'resolution' => [3840, 2160]],
            RenderPlan::quality(RenderPlan::HIGH),
        );
    }

    /**
     * The ladder has to *be* a ladder in both axes at once, since a level that raised samples while lowering
     * pixels would not be a level at all.
     */
    public function testTheLevelsAscendInBothSamplesAndPixels(): void
    {
        $levels = array_map(
            static fn (?string $level): array => RenderPlan::quality($level),
            [RenderPlan::QUICK, null, RenderPlan::HIGH],
        );

        $samples = array_column($levels, 'samples');
        $pixels = array_map(static fn (array $l): int => $l['resolution'][0] * $l['resolution'][1], $levels);

        $sorted = $samples;
        sort($sorted);
        self::assertSame($sorted, $samples, 'samples should ascend');

        $sortedPixels = $pixels;
        sort($sortedPixels);
        self::assertSame($sortedPixels, $pixels, 'pixels should ascend');
    }

    public function testAnUnknownQualityLevelIsRefusedRatherThanSilentlyDefaulted(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RenderPlan::quality('medium');
    }

    /**
     * A level's numbers have to reach the plan Blender is handed, which is the whole point of the flags — the
     * 4K case is otherwise only checkable by rendering a frame that takes minutes.
     */
    public function testALevelsNumbersReachThePlan(): void
    {
        $sub = SpecFactory::spec(['id' => 'sub', 'subtype' => 'sub']);
        $placed = [new PlacedDevice('a', $sub, [0.0, 0.0, 1.0], new Orientation())];

        $high = RenderPlan::quality(RenderPlan::HIGH);
        $plan = RenderPlan::forScene($placed, samples: $high['samples'], resolution: $high['resolution']);

        self::assertSame([3840, 2160], $plan['render']['resolution']);
        self::assertSame(384, $plan['render']['samples']);
    }
}
