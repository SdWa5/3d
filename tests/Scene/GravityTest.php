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
     * **Every** support at the landing height carries the cabinet, not just the one it is named after.
     *
     * A defect in the bearing figure as first written: it took the overlap with the single highest support and
     * called that the bearing. A cabinet spanning two neighbours of equal height rests on both, and crediting it
     * with only the larger overlap read 35 % where it was really 93 % — which then refused arrangements that
     * were perfectly well carried. Level within a shim's worth counts; a support genuinely lower down does not.
     */
    public function testACabinetBridgingTwoLevelSupportsIsCarriedByBoth(): void
    {
        $rc = new \ReflectionMethod(Gravity::class, 'landsOn');

        $level = [
            ['id' => 'left', 'lo' => -1.0, 'hi' => -0.01, 'top' => 0.6],
            ['id' => 'right', 'lo' => 0.01, 'hi' => 1.0, 'top' => 0.6],
        ];
        // Spans the joint: 0.49 on the left, 0.49 on the right, 0.02 over the gap between them.
        $both = $rc->invoke(null, $level, -0.5, 0.5);
        self::assertEqualsWithDelta(0.98, $both['bearing'], 1e-9, 'both halves count');

        // Drop one of them well below and only the other carries it.
        $stepped = [$level[0], ['id' => 'right', 'lo' => 0.01, 'hi' => 1.0, 'top' => 0.4]];
        $one = $rc->invoke(null, $stepped, -0.5, 0.5);
        self::assertSame('left', $one['on']);
        self::assertEqualsWithDelta(0.49, $one['bearing'], 1e-9, 'the lower one is not touching it');
    }

    /**
     * The tilt a cabinet comes to rest at — **zero unless its own weight is off its support**.
     *
     * That gate is the whole rule, and leaving it out produced nonsense: a cabinet with a 20 mm sliver hanging
     * over a 19 mm step reads 43.5° by `atan(drop / overhang)` and does not move at all in reality, because its
     * weight is still over what holds it up. With the gate, the three cases this repository kept confusing come
     * apart on their own numbers.
     */
    public function testACabinetOnlyTiltsWhenItsOwnWeightIsOffItsSupport(): void
    {
        $rc = new \ReflectionMethod(Gravity::class, 'landsOn');

        // Centre over the support, a sliver hanging over a 19 mm step: sits flat.
        $flat = $rc->invoke(null, [
            ['id' => 'under', 'lo' => -0.30, 'hi' => 0.28, 'top' => 0.610],
            ['id' => 'lower', 'lo' => 0.30, 'hi' => 1.00, 'top' => 0.591],
        ], -0.30, 0.30);
        self::assertSame(0.0, $flat['settle'], 'its weight is over the support, so nothing tilts');

        // Half off the support with the rest over a surface 151 mm down: it rocks.
        $rocks = $rc->invoke(null, [
            ['id' => 'tall', 'lo' => -0.305, 'hi' => 0.305, 'top' => 0.914],
            ['id' => 'short', 'lo' => -0.916, 'hi' => -0.325, 'top' => 0.763],
        ], -0.601, -0.010);
        self::assertEqualsWithDelta(27.0, $rocks['settle'], 0.2, '151 mm down over a 296 mm overhang');

        // Off the support with nothing under the overhang at all: a cantilever, not a tilt.
        $cantilever = $rc->invoke(null, [
            ['id' => 'under', 'lo' => -0.916, 'hi' => -0.621, 'top' => 0.763],
        ], -1.212, -0.621);
        self::assertSame(0.0, $cantilever['settle'], 'over air, so the row decides rather than this cabinet');
    }

    /**
     * A mirrored tier lands as **two runs**, because its halves are turned opposite ways.
     *
     * Run identity is device, support *and roll*: without the roll the two halves of a mirrored row would merge
     * into one placement and be built as a single unturned lattice, which is the whole rig silently upright.
     */
    public function testAMirroredTierLandsAsTwoRunsTurnedOppositeWays(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $resolved = Gravity::resolve(
            [new Tier([[$flexy, 2, 270.0], [$flexy, 2, 90.0]])],
            0.02,
            'main',
        );

        self::assertCount(2, $resolved[0]);
        self::assertSame(270.0, $resolved[0][0]['roll']);
        self::assertSame(90.0, $resolved[0][1]['roll']);
        // Rolled bodies, so each run is 2 × 0.763 + 0.02 wide rather than 2 × 0.591 + 0.02.
        self::assertEqualsWithDelta(1.546, $resolved[0][0]['hi'] - $resolved[0][0]['lo'], 1e-9);
        self::assertEqualsWithDelta(0.02, $resolved[0][1]['lo'] - $resolved[0][0]['hi'], 1e-9, 'the seam');
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

    /**
     * **A run moved sideways after it was seated gets the height of what it is now over, not what it left.**
     *
     * The bug this closes was a whole family rather than one case: {@see Gravity::resolve} re-asks the question after
     * each of its own repairs, because they hand back *seats* and go through the fill again, but a caller holding
     * finished runs and shifting them had nothing to re-ask with. `Stack::spreadApart()` walks a stereo tops row out
     * across the whole support span, which routinely straddles supports at different heights, and the moved run kept
     * the height of the support it had left — a tecnare at 2.347 m and a turbo top at 4.668 m, both hanging in air
     * that no tier check could see, since the bearings had been computed before the move.
     *
     * Built on a deliberately stepped support so the two candidate heights are far apart and the assertion cannot pass
     * by rounding: the wall bass row stands 1.400 m high and the mid bass row beside it 0.500 m.
     */
    public function testARunMovedOffItsSupportIsReseatedOntoWhateverItIsNowOver(): void
    {
        $wall = $this->devices['gmss-wall-bass'];
        $mid = $this->devices['gmss-mid-bass'];

        // One stepped tier: two wall basses on the left, one much shorter mid bass to the right of them.
        $resolved = Gravity::resolve(
            [new Tier([[$wall, 2], [$mid, 1]]), Tier::of($this->devices['gmss-turbo-top'], 1)],
            0.02,
            'main',
        );

        $below = Gravity::topFacesOf($resolved[0]);
        $tall = max(array_column($below, 'top'));
        $short = min(array_column($below, 'top'));
        self::assertGreaterThan($short + 0.5, $tall, 'the fixture has to be genuinely stepped');

        $top = $resolved[1][0];
        self::assertEqualsWithDelta($tall, $top['top'], 1e-9, 'seated on the taller support to begin with');

        // Now shove it bodily over the short support and ask again.
        $shortFace = array_values(array_filter($below, static fn (array $f): bool => $f['top'] === $short))[0];
        $width = $top['hi'] - $top['lo'];
        $centre = ($shortFace['lo'] + $shortFace['hi']) / 2;
        $moved = Gravity::reseat(
            [['lo' => $centre - $width / 2, 'hi' => $centre + $width / 2] + $top],
            $below,
        );

        self::assertEqualsWithDelta($short, $moved[0]['top'], 1e-9, 'reseated onto the short support');
        self::assertSame($shortFace['id'], $moved[0]['on']);

        // And idempotent, so calling it on runs that did not move changes nothing.
        self::assertSame($moved, Gravity::reseat($moved, $below));
    }
}
