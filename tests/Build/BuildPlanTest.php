<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\BuildPlan;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class BuildPlanTest extends TestCase
{
    public function testAFrontImageReachesThePlanOnlyWithAResolvedPath(): void
    {
        // Three cases the bpy side must not have to tell apart, all of them "the front stays plain":
        // the spec names no image, the file is not in this checkout, and the feature is off for this
        // run. The caller collapses all three into a null path, so the plan carries one null.
        $spec = SpecFactory::spec(['front_image' => 'meshes/tms4-front.png']);

        $off = BuildPlan::forSpec($spec, '/build/glb/top-a.glb', '/build/blend/top-a.blend');
        self::assertNull($off['front_image'], 'no resolved path means no image in the plan');

        $on = BuildPlan::forSpec(
            $spec,
            '/build/glb/top-a.glb',
            '/build/blend/top-a.blend',
            null,
            '/project/meshes/tms4-front.png',
        );
        self::assertSame('/project/meshes/tms4-front.png', $on['front_image']['path'], 'absolute, so bpy needs no project root');
        self::assertSame(0, $on['front_image']['rotate_deg']);
    }

    public function testASpecWithNoFrontImageCarriesNone(): void
    {
        $plan = BuildPlan::forSpec(SpecFactory::spec(), '/build/glb/top-a.glb', '/build/blend/top-a.blend');

        self::assertNull($plan['front_image']);
    }

    public function testTheImageRotationTravelsToTheBuilder(): void
    {
        // The one thing about the file the bpy side cannot work out for itself. A photograph taken of
        // the cabinet lying down is turned in UV space, and only the plan knows by how much.
        $spec = SpecFactory::spec(['front_image' => ['path' => 'meshes/lying.png', 'rotate_deg' => 90]]);

        $plan = BuildPlan::forSpec($spec, '/g.glb', '/b.blend', null, '/project/meshes/lying.png');

        self::assertSame(90, $plan['front_image']['rotate_deg']);
    }

    public function testCarriesGeometryAppearanceAndOutputs(): void
    {
        $plan = BuildPlan::forSpec(SpecFactory::spec(), '/build/glb/top-a.glb', '/build/blend/top-a.blend');

        self::assertSame(5, $plan['plan_version']);
        self::assertSame('top-a', $plan['id']);
        self::assertSame('box', $plan['geometry']['shape']);
        self::assertSame(['width' => 0.8, 'height' => 0.6, 'depth' => 0.45], $plan['geometry']['dimensions_m']);
        self::assertNull($plan['geometry']['back_width_m']);
        self::assertNull($plan['geometry']['front_height_m']);
        self::assertNull($plan['geometry']['truss'], 'a cabinet has no tubes');
        self::assertNull($plan['geometry']['moving_head']);
        self::assertNull($plan['geometry']['scaffold']);
        self::assertNull($plan['geometry']['load_bay'], 'a cabinet has no inside to cage');
        self::assertSame('bottom-center', $plan['geometry']['origin']);
        self::assertSame(0.012, $plan['appearance']['grille']['inset_m']);
        self::assertSame(['left', 'right'], $plan['physical']['handles']);
        self::assertSame('/build/glb/top-a.glb', $plan['outputs']['glb']);
        self::assertSame('/build/blend/top-a.blend', $plan['outputs']['blend']);
    }

    /**
     * The tubes reach the bpy side, which is the whole reason `plan_version` went to 2: a plan carrying a truss
     * block is one an older builder would silently draw as a box.
     */
    public function testATrussCarriesItsTubesIntoThePlan(): void
    {
        $plan = BuildPlan::forSpec(
            SpecFactory::spec(['geometry' => [
                'shape' => 'truss',
                'truss' => [
                    'chords' => 3,
                    'chord_diameter_m' => 0.050,
                    'diagonal_diameter_m' => 0.020,
                    'bay_length_m' => 0.500,
                ],
            ]]),
            '/glb',
            '/blend',
        );

        self::assertSame('truss', $plan['geometry']['shape']);
        self::assertSame([
            'chords' => 3,
            'chord_diameter_m' => 0.050,
            'diagonal_diameter_m' => 0.020,
            'bay_length_m' => 0.500,
        ], $plan['geometry']['truss']);
    }

    /**
     * The other two open-frame blocks reach the plan the same way the truss one does. One test rather than two
     * because the mechanism is identical and it is the mechanism that could break.
     */
    public function testTheOtherOpenFrameBlocksCarryIntoThePlan(): void
    {
        $head = BuildPlan::forSpec(
            SpecFactory::spec(['geometry' => [
                'shape' => 'moving-head',
                'dimensions_m' => ['width' => 0.49, 'height' => 0.743, 'depth' => 0.408],
                'moving_head' => [
                    'base_height_m' => 0.240,
                    'yoke_arm_thickness_m' => 0.075,
                    'head_diameter_m' => 0.300,
                    'head_length_m' => 0.430,
                ],
            ]]),
            '/glb',
            '/blend',
        );

        self::assertSame('moving-head', $head['geometry']['shape']);
        self::assertSame(0.240, $head['geometry']['moving_head']['base_height_m']);
        self::assertNull($head['geometry']['truss'], 'one shape, one block');

        $tower = BuildPlan::forSpec(
            SpecFactory::spec(['geometry' => [
                'shape' => 'scaffold',
                'dimensions_m' => ['width' => 1.5, 'height' => 5.0, 'depth' => 0.65],
                'scaffold' => [
                    'post_diameter_m' => 0.050,
                    'brace_diameter_m' => 0.025,
                    'platform_height_m' => 5.000,
                    'platform_thickness_m' => 0.050,
                ],
            ]]),
            '/glb',
            '/blend',
        );

        self::assertSame('scaffold', $tower['geometry']['shape']);
        self::assertSame(5.000, $tower['geometry']['scaffold']['platform_height_m']);
    }

    public function testGrilleFallsBackToTheCabinetColour(): void
    {
        $plan = BuildPlan::forSpec(
            SpecFactory::spec(['appearance' => ['color' => '#223344', 'grille' => ['inset_m' => 0.01]]]),
            '/glb',
            '/blend',
        );

        self::assertSame('#223344', $plan['appearance']['grille']['color']);
    }

    public function testOnlyEstimatedSpecsAreMarked(): void
    {
        $estimated = BuildPlan::forSpec(
            SpecFactory::spec(['provenance' => 'estimated']),
            '/glb',
            '/blend',
        );
        $measured = BuildPlan::forSpec(
            SpecFactory::spec(['provenance' => 'measured']),
            '/glb',
            '/blend',
        );

        self::assertTrue($estimated['appearance']['mark_estimated']);
        self::assertFalse($measured['appearance']['mark_estimated']);
    }

    public function testTaperDimensionsArePassedThrough(): void
    {
        $plan = BuildPlan::forSpec(
            SpecFactory::spec(['geometry' => ['shape' => 'trapezoid', 'back_width_m' => 0.5]]),
            '/glb',
            '/blend',
        );

        self::assertSame('trapezoid', $plan['geometry']['shape']);
        self::assertSame(0.5, $plan['geometry']['back_width_m']);
    }

    public function testPlanIsJsonEncodable(): void
    {
        // The plan crosses into Python as JSON, so anything unencodable is a build failure.
        $plan = BuildPlan::forSpec(SpecFactory::spec(), '/glb', '/blend');

        self::assertJson(json_encode($plan, JSON_THROW_ON_ERROR));
    }

    /**
     * The angles were already crossing into Python and nothing read them. A cone also needs a length, and
     * that decision is made here rather than in the geometry builder — the default focus distance, which is
     * the one number in the library that already means "out where aiming matters".
     */
    public function testCoverageAnglesAndTheThrowTheyReachReachThePlan(): void
    {
        $plan = BuildPlan::forSpec(
            SpecFactory::spec(['audio' => ['coverage_deg' => ['horizontal' => 60, 'vertical' => 40]]]),
            '/glb',
            '/blend',
        );

        self::assertSame(['horizontal' => 60.0, 'vertical' => 40.0], $plan['audio']['coverage_deg']);
        self::assertSame(10.0, $plan['audio']['coverage_throw_m']);
        // 60° x 40° at 10 m: 11.55 m across and 7.28 m high.
        self::assertEqualsWithDelta(11.547005, $plan['audio']['coverage_spread_m'][0], 1e-6);
        self::assertEqualsWithDelta(7.279404, $plan['audio']['coverage_spread_m'][1], 1e-6);
    }

    /**
     * Four of the five shipped specs state no coverage, so "no cone" has to be the quiet default — the same
     * way a missing `baffle_layout` simply builds nothing.
     */
    public function testASpecWithoutCoverageCarriesNoneAndNoThrow(): void
    {
        $plan = BuildPlan::forSpec(SpecFactory::spec(['audio' => ['coverage_deg' => null]]), '/glb', '/blend');

        self::assertNull($plan['audio']['coverage_deg']);
        self::assertNull($plan['audio']['coverage_throw_m']);
        self::assertNull($plan['audio']['coverage_spread_m']);
    }

    /**
     * A join is a relation between two features, and bpy resolves it by id the way it resolves `inside`.
     * So the plan has to carry it verbatim: a builder that had to re-derive which pair belongs together
     * would be deciding geometry the spec already decided.
     */
    public function testAJoinedPairReachesThePlan(): void
    {
        $plan = BuildPlan::forSpec(SpecFactory::spec(['audio' => ['layout' => [
            'provenance' => 'estimated',
            'features' => [
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.2],
                    'throat_in' => 10.0, 'depth_m' => 0.2],
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.4, 0.2],
                    'throat_in' => 10.0, 'depth_m' => 0.2,
                    'join' => ['with' => 'lf-up', 'depth_m' => 0.045]],
            ],
        ]]]), '/glb', '/blend');

        $features = $plan['baffle_layout']['features'];

        self::assertNull($features[0]['join']);
        self::assertSame(['with' => 'lf-up', 'depth_m' => 0.045], $features[1]['join']);
    }
}
