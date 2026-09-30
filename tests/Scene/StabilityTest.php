<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Stability;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * A packed row is one body and a gapped row is several, so the same cabinets over the same supports can stand in
 * one and tip in the other.
 */
final class StabilityTest extends TestCase
{
    private DeviceSpec $flexy;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            if ($spec instanceof DeviceSpec && 'flexy-folded-horn-hybrid' === $spec->id) {
                $this->flexy = $spec;
            }
        }
    }

    /** A cabinet whose centre hangs over air stands in a packed row, held by its neighbour, and tips on its own. */
    public function testAGappedCabinetOverAirTipsWhereThePackedRowStands(): void
    {
        $runs = [$this->cabinet(-1.0, 0.0), $this->cabinet(0.0, 1.0)];
        // Only the inner third of each cabinet is carried: the row's combined centre is over the support, each
        // cabinet's own centre is not.
        $below = [['lo' => -0.34, 'hi' => 0.34]];

        self::assertFalse(Stability::tips($runs, $below));
        self::assertTrue(Stability::tips($runs, $below, apart: true));
    }

    /** A gapped cabinet bridging two supports stands even with its centre over the gap between them. */
    public function testAGappedCabinetBridgingTwoSupportsStands(): void
    {
        $below = [['lo' => -1.0, 'hi' => -0.2], ['lo' => 0.2, 'hi' => 1.0]];

        self::assertFalse(Stability::tips([$this->cabinet(-0.5, 0.5)], $below, apart: true));
    }

    /** A gapped cabinet whose centre sits over the one support it touches stands. */
    public function testAGappedCabinetCentredOverItsSupportStands(): void
    {
        self::assertFalse(Stability::tips([$this->cabinet(0.0, 0.6)], [['lo' => 0.1, 'hi' => 0.5]], apart: true));
    }

    /**
     * @return array{device: DeviceSpec, count: int, lo: float, hi: float}
     */
    private function cabinet(float $lo, float $hi): array
    {
        return ['device' => $this->flexy, 'count' => 1, 'lo' => $lo, 'hi' => $hi];
    }
}
