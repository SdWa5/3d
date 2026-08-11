<?php

declare(strict_types=1);

namespace App\Tests\Build;

use App\Build\BuildPlan;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class BuildPlanTest extends TestCase
{
    public function testCarriesGeometryAppearanceAndOutputs(): void
    {
        $plan = BuildPlan::forSpec(SpecFactory::spec(), '/build/glb/top-a.glb', '/build/blend/top-a.blend');

        self::assertSame(2, $plan['plan_version']);
        self::assertSame('top-a', $plan['id']);
        self::assertSame('box', $plan['geometry']['shape']);
        self::assertSame(['width' => 0.8, 'height' => 0.6, 'depth' => 0.45], $plan['geometry']['dimensions_m']);
        self::assertNull($plan['geometry']['back_width_m']);
        self::assertNull($plan['geometry']['front_height_m']);
        self::assertNull($plan['geometry']['truss'], 'a cabinet has no tubes');
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
