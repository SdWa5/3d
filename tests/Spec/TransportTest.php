<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Load\LoadPlan;
use App\Spec\InvalidSpecException;
use App\Spec\Provenance;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The `transport` block: the box a device packs into and whether it may be laid down (SPEC-15).
 */
final class TransportTest extends TestCase
{
    private const PACKED = ['width' => 0.24, 'height' => 0.5, 'depth' => 0.3];

    public function testADeviceWithNoBlockPacksAsItStandsAndMayBeTurned(): void
    {
        $spec = SpecFactory::spec();

        self::assertSame($spec->dimensions, $spec->transportDimensions());
        self::assertSame($spec, $spec->packed(), 'nothing to fold, so no copy');
        self::assertFalse($spec->isUpright());
    }

    public function testThePackedCopyCarriesTheTransportBoxAndSaysItIsFolded(): void
    {
        $spec = SpecFactory::spec(['transport' => ['dimensions_m' => self::PACKED, 'provenance' => 'estimated']]);
        $packed = $spec->packed();

        self::assertSame(self::PACKED, $spec->transportDimensions()->toArray());
        self::assertSame(self::PACKED, $packed->dimensions->toArray());
        self::assertTrue($packed->folded);
        self::assertFalse($spec->folded, 'the spec on disk is the erected device');
        self::assertSame($packed, $packed->packed(), 'folding twice is folding once');
        self::assertSame(Provenance::Estimated, $spec->transport?->provenance);
    }

    public function testUprightNeedsNoBox(): void
    {
        $spec = SpecFactory::spec(['transport' => ['upright' => true]]);

        self::assertTrue($spec->isUpright());
        self::assertSame($spec, $spec->packed());
        self::assertNull($spec->transport?->provenance);
    }

    /** A packed size is never published as such here, so a box without its source is refused. */
    public function testAPackedBoxMustSayWhereItComesFrom(): void
    {
        $this->expectException(InvalidSpecException::class);

        SpecFactory::spec(['transport' => ['dimensions_m' => self::PACKED]]);
    }

    public function testAnUnknownKeyIsRefused(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("transport: unknown key 'may_lie'");

        SpecFactory::spec(['transport' => ['may_lie' => true]]);
    }

    /** The volume a van has to find is the packed one. */
    public function testALoadPlanCountsThePackedVolume(): void
    {
        $spec = SpecFactory::spec(['transport' => ['dimensions_m' => self::PACKED, 'provenance' => 'estimated']]);
        $plan = new LoadPlan(SpecFactory::spec(['id' => 'van']), [['spec' => $spec, 'count' => 2]], 1000.0, null);

        self::assertEqualsWithDelta(2 * 0.24 * 0.5 * 0.3, $plan->volumeM3(), 1e-12);
    }
}
