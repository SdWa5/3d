<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\Provenance;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class ProvenanceSetTest extends TestCase
{
    public function testScalarShorthandAppliesToBothFields(): void
    {
        $spec = SpecFactory::spec(['provenance' => 'plans']);

        self::assertSame(Provenance::Plans, $spec->provenance->dimensions);
        self::assertSame(Provenance::Plans, $spec->provenance->weight);
        self::assertSame('plans', $spec->provenance->label());
    }

    public function testExpandedFormKeepsTheFieldsApart(): void
    {
        // The case this whole split exists for: a hanging scale settles the weight long before
        // anybody tapes fourteen subs.
        $spec = SpecFactory::spec([
            'provenance' => ['dimensions' => 'plans', 'weight' => 'measured'],
        ]);

        self::assertSame(Provenance::Plans, $spec->provenance->dimensions);
        self::assertSame(Provenance::Measured, $spec->provenance->weight);
        self::assertSame('plans/measured', $spec->provenance->label());
        self::assertFalse($spec->provenance->isFullyMeasured());
    }

    public function testFullyMeasuredNeedsBoth(): void
    {
        $half = SpecFactory::spec(['provenance' => ['dimensions' => 'measured', 'weight' => 'estimated']]);
        $both = SpecFactory::spec(['provenance' => 'measured']);

        self::assertFalse($half->provenance->isFullyMeasured());
        self::assertTrue($both->provenance->isFullyMeasured());
    }

    public function testWeakestIsWhatASingleColumnShouldShow(): void
    {
        $spec = SpecFactory::spec(['provenance' => ['dimensions' => 'measured', 'weight' => 'estimated']]);

        self::assertSame(Provenance::Estimated, $spec->provenance->weakest());
    }

    public function testMetadataCarriesBothFields(): void
    {
        $spec = SpecFactory::spec(['provenance' => ['dimensions' => 'plans', 'weight' => 'estimated']]);

        self::assertSame(
            ['dimensions' => 'plans', 'weight' => 'estimated'],
            $spec->toMetadataArray()['provenance'],
        );
    }
}
