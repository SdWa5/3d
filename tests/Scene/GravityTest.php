<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Gravity;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Where a solved stack's cabinets actually come to rest, and **how much of each one is over its support**.
 *
 * The bearing figure is the interesting part, and it exists because two checks that both look like they cover
 * this do not. Comparing tier widths only ever sees a *row* hanging off a row, and the shipped-scene sweep only
 * asks whether there is anything underneath at all. A cabinet resting on 1.2 % of itself satisfies both.
 */
final class GravityTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    /** A cabinet squarely on its support is fully carried, and a row on level ground merges into one run. */
    public function testALevelRowIsOneFullyCarriedRun(): void
    {
        $resolved = Gravity::resolve(
            [Tier::of($this->devices['flexy-folded-horn-hybrid'], 6), Tier::of($this->devices['achenbach-18'], 4)],
            0.02,
            'main',
        );

        self::assertCount(1, $resolved[0], 'six Flexys on the floor are one placement');
        self::assertCount(1, $resolved[1]);
        self::assertSame('main/1', $resolved[0][0]['id'], 'no letter when a tier lands in one place');
        // 2.460 m of Achenbach well inside the 3.646 m under it, so not even an edge sits proud.
        self::assertEqualsWithDelta(1.0, $resolved[1][0]['bearing'], 1e-9);
    }

    /**
     * A cabinet that overlaps a taller neighbour by a hair is lifted onto that hair.
     *
     * This is the failure the bearing figure exists to name, in the exact geometry it was found in: a mixed
     * Achenbach row is 163 mm taller at its Flexy shoulders, and the contiguous top row's outboard 2-way clips
     * the shoulder by 5.6 mm. Falling is right to lift it — that is what falling does — so the arrangement has
     * to be rejected somewhere, and it can only be rejected by something that measures the overlap.
     */
    public function testACabinetClippingATallerNeighbourLandsOnAlmostNothing(): void
    {
        $below = [
            ['id' => 'shoulder', 'lo' => -1.841, 'hi' => -1.250, 'top' => 2.289],
            ['id' => 'middle', 'lo' => -1.230, 'hi' => 1.230, 'top' => 2.126],
        ];

        $rc = new \ReflectionMethod(Gravity::class, 'landsOn');
        $landing = $rc->invoke(null, $below, -1.2556, -0.7900);

        self::assertSame('shoulder', $landing['on'], 'the highest thing under it, not the widest');
        self::assertEqualsWithDelta(0.012, $landing['bearing'], 5e-4, '5.6 mm of a 465.6 mm cabinet');
    }

    /**
     * So the top tier is re-seated **fills outboard**, and the whole rig comes out carried.
     *
     * The end segments go over the end supports and the rest is centred between them, which is the layout that
     * makes flanking a load-bearing row usable: the Tecnares land on the Achenbachs, the two 2-ways on the
     * Flexy shoulders they would otherwise have caught.
     */
    public function testTheTopTierIsReSeatedOutboardWhenTheOrdinaryRowWouldHang(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $tiers = [
            new Tier([[$flexy, 2], [$this->devices['skram'], 2], [$flexy, 2]]),
            Tier::of($flexy, 6),
            new Tier([[$flexy, 1], [$this->devices['achenbach-18'], 4], [$flexy, 1]]),
            new Tier([
                [$this->devices['eighteensound-2way-15'], 1],
                [$this->devices['tecnare-m2122'], 3],
                [$this->devices['eighteensound-2way-15'], 1],
            ]),
        ];

        $resolved = Gravity::resolve($tiers, 0.02, 'main');

        $worst = 1.0;
        foreach ($resolved as $runs) {
            foreach ($runs as $run) {
                $worst = min($worst, $run['bearing']);
            }
        }
        self::assertGreaterThan(Gravity::MIN_BEARING, $worst, 'every cabinet in the rig is carried');

        $tops = $resolved[3];
        self::assertCount(3, $tops);
        self::assertSame('eighteensound-2way-15', $tops[0]['device']->id);
        // Centred on the left Flexy shoulder, which spans -1.841..-1.250 — not butted against the Tecnares.
        self::assertEqualsWithDelta(-1.5455, ($tops[0]['lo'] + $tops[0]['hi']) / 2, 1e-9);
        self::assertEqualsWithDelta(1.0, $tops[0]['bearing'], 1e-9);
        self::assertEqualsWithDelta(0.0, ($tops[1]['lo'] + $tops[1]['hi']) / 2, 1e-9, 'the Tecnares stay centred');
    }

    /**
     * And a tier that is already carried is left exactly where it was.
     *
     * Re-seating is a repair, not a preference. Firing it on a rig that stands up would quietly restyle every
     * scene that already works, which is the sort of change that is only noticed months later in a render.
     */
    public function testATierThatIsAlreadyCarriedIsNotReSeated(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $tops = new Tier([
            [$this->devices['eighteensound-2way-15'], 1],
            [$this->devices['tecnare-m2122'], 3],
            [$this->devices['eighteensound-2way-15'], 1],
        ]);

        $resolved = Gravity::resolve(
            [new Tier([[$flexy, 2], [$this->devices['skram'], 2], [$flexy, 2]]), Tier::of($flexy, 6), $tops],
            0.02,
            'main',
        );

        $seats = $tops->seats(0.02);
        foreach ($resolved[2] as $slot => $run) {
            self::assertEqualsWithDelta($seats[$slot][2], ($run['lo'] + $run['hi']) / 2, 1e-9, 'seat '.$slot);
        }
    }
}
