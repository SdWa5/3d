<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A placement's own `focus:` block, which is what stops three sound systems aiming at one spot.
 *
 * **`Focus::point()` measures out from the *rig's* front centre**, which is right for a cluster and its near-fills
 * and wrong the moment two systems stand side by side: the outer walls toe inward at a point in front of the
 * middle one, so three systems cover one patch of floor instead of each covering the room in front of it.
 */
final class PlacementFocusTest extends TestCase
{
    /**
     * **Two tops standing well off the rig's centre line, aimed two ways.**.
     *
     * With the scene's focus they both swing back toward the middle of the rig — 23° and 26° here, because the
     * point they are aiming at is in front of somebody else's wall. With a focus of their own they face the room
     * in front of them, which is what "each system has its own focus points" means.
     */
    public function testAPlacementWithItsOwnFocusAimsStraightAheadRatherThanAtTheRigsCentre(): void
    {
        $shared = $this->yawsOf($this->scene(false));
        $own = $this->yawsOf($this->scene(true));

        // Both lean the same way and by a lot, because the rig's centre is four metres to their left.
        self::assertLessThan(-20.0, $shared[0]);
        self::assertLessThan(-20.0, $shared[1]);

        // Their own focus is straight in front of each of them.
        self::assertEqualsWithDelta(0.0, $own[0], 1e-9);
        self::assertEqualsWithDelta(0.0, $own[1], 1e-9);
    }

    /** @return list<float> the two tops' yaws, in placement order */
    private function yawsOf(SceneSpec $scene): array
    {
        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }

        $yaws = [];
        foreach ((new SceneCompiler($devices))->compile($scene)['placed'] as $placed) {
            if ('tecnare-m2122' === $placed->device->id) {
                $yaws[] = $placed->yawDeg();
            }
        }

        return $yaws;
    }

    /**
     * Two walls side by side: a wide one on the left and, well off the centre line, a narrow one carrying the two
     * tops this test reads. Written out rather than swept, so the geometry under the assertion is visible.
     */
    private function scene(bool $ownFocus): SceneSpec
    {
        $focus = $ownFocus ? "\n    focus:\n      far: { distance_m: 10.0, height_m: 1.8 }" : '';
        $yaml = <<<YAML
            id: focus-test
            name: "Two walls"

            focus:
              far: { distance_m: 10.0, height_m: 1.8 }

            placements:
              - id: left
                at: [-4.0, 0.0]
                device: flexy-folded-horn-hybrid
                row: { count: 6, gap_m: 0.02 }
              - id: right-a
                at: [4.0, 0.0]
                device: tecnare-m2122
                aim: far{$focus}
              - id: right-b
                at: [4.6, 0.0]
                device: tecnare-m2122
                aim: far{$focus}
            YAML;

        return SceneSpec::fromArray((array) Yaml::parse($yaml), '/scenes/focus-test.yaml');
    }
}
