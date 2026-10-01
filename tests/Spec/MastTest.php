<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\DeviceSpec;
use App\Spec\Mast;
use App\Spec\Shape;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * The wind-up stand's mast block, against our own Varytec specs, because the case is about their numbers: 4.0 m at
 * full extension, a 1.75 m transport length, a 0.12 m adapter and a hub at 0.95 m.
 */
final class MastTest extends TestCase
{
    private DeviceSpec $tower;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            if ('truss-tower-4m' === $spec->id) {
                /** @var DeviceSpec $spec */
                $this->tower = $spec;
            }
        }
    }

    public function testOurTowerIsAMast(): void
    {
        self::assertSame(Shape::Mast, $this->tower->shape);
        self::assertInstanceOf(Mast::class, $this->tower->mast);
        self::assertSame(3, $this->tower->mast->legs);
        self::assertSame(2, $this->tower->mast->movingStages());
    }

    /** The 1.75 m it folds to, less the 0.12 m adapter, is one tube, and the strut collar lifts it 0.475 m. */
    public function testTheLengthsFollowFromTheTransportLength(): void
    {
        $mast = $this->mast();

        self::assertEqualsWithDelta(0.475, $mast->sleeveBottomM(), 1e-9);
        self::assertEqualsWithDelta(1.63, $mast->tubeLengthM(), 1e-9);
        self::assertEqualsWithDelta(2.225, $mast->collapsedHeightM(), 1e-9);
    }

    /** Two stages share the 1.775 m between collapsed and full, and each keeps 0.7425 m inside the one below. */
    public function testEachStageOverlapsAtFullExtension(): void
    {
        $mast = $this->mast();

        self::assertEqualsWithDelta(0.8875, $mast->travelM(4.0), 1e-9);
        self::assertEqualsWithDelta(0.7425, $mast->overlapM(4.0), 1e-9);
        self::assertGreaterThan(Mast::MIN_OVERLAP_M, $mast->overlapM(4.0));
    }

    public function testCrankingKeepsTheMast(): void
    {
        self::assertSame($this->tower->mast, $this->tower->withHeight(3.742)->mast);
    }

    /** The plan carries the worked-out lengths, so the bpy side derives nothing. */
    public function testThePlanCarriesTheDerivedLengths(): void
    {
        $plan = $this->mast()->planArray($this->tower->dimensions);

        self::assertEqualsWithDelta(1.63, $plan['tube_length_m'], 1e-9);
        self::assertEqualsWithDelta(0.8875, $plan['travel_m'], 1e-9);
        self::assertEqualsWithDelta(0.12, $plan['head_m'], 1e-9);
        self::assertSame(2, $plan['moving_stages']);
        self::assertSame($this->mast()->toArray()['sections_m'], $plan['sections_m']);
    }

    private function mast(): Mast
    {
        $mast = $this->tower->mast;
        self::assertInstanceOf(Mast::class, $mast);

        return $mast;
    }
}
