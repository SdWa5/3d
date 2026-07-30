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

        self::assertSame(1, $plan['plan_version']);
        self::assertSame('top-a', $plan['id']);
        self::assertSame('box', $plan['geometry']['shape']);
        self::assertSame(['width' => 0.8, 'height' => 0.6, 'depth' => 0.45], $plan['geometry']['dimensions_m']);
        self::assertNull($plan['geometry']['back_width_m']);
        self::assertNull($plan['geometry']['front_height_m']);
        self::assertSame('bottom-center', $plan['geometry']['origin']);
        self::assertSame(0.012, $plan['appearance']['grille']['inset_m']);
        self::assertSame(['left', 'right'], $plan['physical']['handles']);
        self::assertSame('/build/glb/top-a.glb', $plan['outputs']['glb']);
        self::assertSame('/build/blend/top-a.blend', $plan['outputs']['blend']);
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
}
