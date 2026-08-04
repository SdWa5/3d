<?php

declare(strict_types=1);

namespace App\Tests\Render;

use App\Render\CameraPreset;
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
    }

    public function testAnEmptySceneStillProducesAUsablePlan(): void
    {
        $plan = RenderPlan::forScene([]);

        self::assertGreaterThan(0.0, $plan['scene_bounds']['radius']);
        self::assertNotSame($plan['camera']['location'], $plan['camera']['target']);
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
}
