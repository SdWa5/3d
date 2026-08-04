<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class DeviceSpecTest extends TestCase
{
    public function testTotalWeightMultipliesByQuantity(): void
    {
        $spec = SpecFactory::spec(['quantity' => 4, 'physical' => ['weight_kg' => 12.5]]);

        self::assertSame(50.0, $spec->totalWeightKg());
    }

    public function testMeasuringBoxIsAlwaysTheFootprintFrame(): void
    {
        // Independent of `origin`: positions in a spec are what a tape measure gives you.
        foreach (['bottom-center', 'geometric-center'] as $origin) {
            $spec = SpecFactory::spec(['geometry' => ['origin' => $origin]]);

            self::assertSame(
                ['min' => [-0.4, -0.225, 0.0], 'max' => [0.4, 0.225, 0.6]],
                $spec->measuringBox(),
                "origin {$origin}",
            );
        }
    }

    public function testOriginalLabelFallsBackForNonClones(): void
    {
        $clone = SpecFactory::spec();
        $own = SpecFactory::spec(['build' => 'own-design', 'clone_of' => null, 'provenance' => 'measured']);

        self::assertSame('Acme X1', $clone->originalLabel());
        self::assertSame('—', $own->originalLabel());
    }

    public function testAnUnidentifiedOriginalIsLabelledOnce(): void
    {
        $spec = SpecFactory::spec([
            'clone_of' => ['manufacturer' => 'unknown', 'model' => 'unknown', 'reference' => 'none'],
            'provenance' => 'estimated',
        ]);

        self::assertFalse($spec->cloneOf?->isIdentified());
        self::assertSame('unknown', $spec->originalLabel());
    }

    public function testMetadataCarriesProvenanceAndRigging(): void
    {
        $spec = SpecFactory::spec(['rigging' => [
            'flyable' => true,
            'points' => [['id' => 'top-left', 'position_m' => [-0.3, -0.2, 0.6], 'thread' => 'M10']],
        ]]);

        $metadata = $spec->toMetadataArray();

        self::assertSame('top-a', $metadata['id']);
        self::assertSame(['dimensions' => 'datasheet', 'weight' => 'datasheet'], $metadata['provenance']);
        self::assertSame(['manufacturer' => 'Acme', 'model' => 'X1', 'reference' => 'datasheet', 'url' => null], $metadata['clone_of']);
        self::assertTrue($metadata['flyable']);
        self::assertSame('M10', $metadata['rigging_points'][0]['thread']);
        self::assertSame(['width' => 0.8, 'height' => 0.6, 'depth' => 0.45], $metadata['dimensions_m']);
    }

    public function testVolumeUsesOuterDimensions(): void
    {
        $spec = SpecFactory::spec();

        self::assertSame(0.8 * 0.6 * 0.45, $spec->dimensions->volumeM3());
    }

    /**
     * `tools/check-glb.py` needs the chamfer to tell a legitimately-eased corner from a modelling error:
     * easing a *tapered* cabinet's corners takes its widest point with them, so its bounding box is a
     * little under its declared width and the checker has to know by how much it may be.
     */
    public function testTheChamferTravelsInTheExportedMetadata(): void
    {
        $metadata = SpecFactory::spec(['geometry' => ['chamfer_m' => 0.01]])->toMetadataArray();

        self::assertSame(0.01, $metadata['chamfer_m']);
    }
}
