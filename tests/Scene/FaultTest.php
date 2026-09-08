<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Fault;
use App\Scene\Interpenetration;
use App\Scene\Orientation;
use App\Scene\PlacedDevice;
use App\Scene\PlacementChecks;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The checks answering with identities instead of with prose.
 *
 * **The point of CVR-5 is that a sentence is the wrong answer for a geometry failure.** "A `turbo-top` would
 * stand at 4.668 m with nothing under it across x" took a debug dump, two probes and a corrected coordinate mapping
 * to understand; a picture with that cabinet in a red cage says it at a glance. The checks always knew which cabinet
 * they objected to and formatted the identity away. What is tested here is that they no longer do, and — the part
 * that matters more — that the sentence and the marking can never disagree, because the sentence is now built from
 * the marking rather than derived alongside it.
 */
final class FaultTest extends TestCase
{
    /**
     * **Every offender, not the first.** A refusal only needs one reason to be a refusal, so the old check stopped
     * at the first floating cabinet and that was right for a refusal. It is wrong for a picture: a render with one
     * of four floating cabinets caged would be more misleading than no render at all, because it reads as a
     * complete diagnosis.
     */
    public function testEveryFloatingCabinetIsNamedRatherThanJustTheFirst(): void
    {
        $faults = PlacementChecks::floatingFaults([
            self::at('ground', [0.0, 0.0, 0.0]),
            self::at('mid-air-one', [4.0, 0.0, 2.0]),
            self::at('mid-air-two', [8.0, 0.0, 3.0]),
        ]);

        self::assertCount(2, $faults);
        self::assertSame(['mid-air-one'], $faults[0]->placementIds);
        self::assertSame(['mid-air-two'], $faults[1]->placementIds);
        self::assertSame(Fault::FLOATING, $faults[0]->kind);
    }

    /**
     * A cabinet floating across both axes is **one** thing wrong. Marking it twice would make the fault count read
     * as more failures than there are, and cage it twice in the same place.
     */
    public function testACabinetFloatingBothWaysIsOneFaultRatherThanTwo(): void
    {
        $faults = PlacementChecks::floatingFaults([self::at('mid-air', [0.0, 0.0, 2.0])]);

        self::assertCount(1, $faults);
    }

    /**
     * **The sentence is built from the marking**, so the refusal a terminal prints and the cabinet a render cages
     * are always the same cabinet. They used to be two derivations of one idea, which is how they could drift.
     */
    public function testTheProseRefusalIsTheFirstFaultsOwnMessage(): void
    {
        $placed = [self::at('ground', [0.0, 0.0, 0.0]), self::at('mid-air', [4.0, 0.0, 2.0])];

        $faults = PlacementChecks::floatingFaults($placed);
        self::assertSame(PlacementChecks::floating($placed), $faults[0]->message);
        self::assertStringContainsString('with nothing under it', $faults[0]->message);
    }

    /**
     * Nothing wrong means no faults and no sentence, which is the case every shipped scene is in.
     */
    public function testACleanSceneHasNoFaults(): void
    {
        $placed = [self::at('a', [0.0, 0.0, 0.0]), self::at('b', [4.0, 0.0, 0.0])];

        self::assertSame([], PlacementChecks::floatingFaults($placed));
        self::assertNull(PlacementChecks::floating($placed));
        self::assertSame([], Interpenetration::faults($placed, 0.001));
    }

    /**
     * **Both cabinets of an overlap are named**, because a cage around one of two interpenetrating boxes says the
     * wrong thing: it points at a culprit where the fault is a relationship.
     */
    public function testAnOverlapNamesBothCabinets(): void
    {
        $faults = Interpenetration::faults([
            self::at('buried-a', [0.0, 0.0, 0.0]),
            self::at('buried-b', [0.2, 0.0, 0.0]),
        ], 0.001);

        self::assertCount(1, $faults);
        self::assertSame(['buried-a', 'buried-b'], $faults[0]->placementIds);
        self::assertSame(Fault::INTERPENETRATION, $faults[0]->kind);
        self::assertStringContainsString('inside each other', $faults[0]->message);
    }

    /**
     * Three cabinets in the same place are three overlapping pairs, and all three cabinets get marked. The old
     * check reported the single deepest pair, which is the right answer for "is this rig buildable" and leaves a
     * third of this picture unexplained.
     */
    public function testThreeCabinetsInOnePlaceMarkAllThree(): void
    {
        $faults = Interpenetration::faults([
            self::at('a', [0.0, 0.0, 0.0]),
            self::at('b', [0.15, 0.0, 0.0]),
            self::at('c', [0.30, 0.0, 0.0]),
        ], 0.001);

        self::assertCount(3, $faults, 'three cabinets in one place is three overlapping pairs');
        self::assertSame(['a', 'b', 'c'], Fault::placementsIn($faults));
    }

    /**
     * `worst()` still answers what it always did, and now does it by formatting the deepest of these. The two
     * cannot disagree because there is only one sweep behind them.
     */
    public function testTheWorstPairIsStillTheDeepestOne(): void
    {
        $placed = [
            self::at('shallow-a', [0.0, 0.0, 0.0]),
            self::at('shallow-b', [0.55, 0.0, 0.0]),
            self::at('deep-a', [4.0, 0.0, 0.0]),
            self::at('deep-b', [4.05, 0.0, 0.0]),
        ];

        ['pair' => $pair, 'separation' => $separation] = Interpenetration::worst($placed);

        self::assertSame('deep-a and deep-b', $pair);
        self::assertLessThan(0.0, $separation);
        self::assertStringContainsString('deep-a', Interpenetration::faults($placed, 0.001)[0]->message);
    }

    /**
     * A tolerance is what tells a shared face from an overlap. Cabinets standing shoulder to shoulder touch by
     * construction and must not be caged for it.
     */
    public function testCabinetsThatMerelyTouchAreNotAFault(): void
    {
        // Two 0.610 m SKRAMs exactly one width apart: the faces meet and nothing intersects.
        $faults = Interpenetration::faults([
            self::at('left', [0.0, 0.0, 0.0]),
            self::at('right', [0.610, 0.0, 0.0]),
        ], 0.001);

        self::assertSame([], $faults);
    }

    /**
     * **A load bay contains rather than collides**, which is what makes a pack scene worth sweeping at all.
     *
     * The whole load of a van stands inside the van's box on purpose — the vehicle is drawn as a cage precisely so
     * the cabinets can be seen in it — and without this exemption the sweep called the first unit of
     * `packed-convoy` **1.09 m inside the Movano** and `scene:build` would have caged the entire load in red.
     */
    public function testACabinetInsideALoadBayIsNotAFault(): void
    {
        $placed = [self::bay('movano', [0.0, 0.0, 0.0]), self::at('cabinet', [0.0, 0.0, 0.0])];

        self::assertSame([], Interpenetration::faults($placed, 0.001));
        self::assertSame(0.0, Interpenetration::worst($placed)['separation']);
    }

    /**
     * And the check is not weakened by the exemption: two cabinets in one place **inside** a bay are still a fault,
     * and only the two of them are named. A pack that quietly put two units in one spot would otherwise look
     * exactly like a good one.
     */
    public function testTwoCabinetsInsideOneBayAreStillAFault(): void
    {
        $faults = Interpenetration::faults([
            self::bay('movano', [0.0, 0.0, 0.0]),
            self::at('buried-a', [0.0, 0.0, 0.0]),
            self::at('buried-b', [0.2, 0.0, 0.0]),
        ], 0.001);

        self::assertCount(1, $faults);
        self::assertSame(['buried-a', 'buried-b'], $faults[0]->placementIds);
    }

    /**
     * Every marked placement, each once, however many faults name it. A cabinet in two overlaps gets one cage.
     */
    public function testAPlacementNamedTwiceIsMarkedOnce(): void
    {
        $faults = [
            new Fault(Fault::INTERPENETRATION, ['a', 'b'], 'one'),
            new Fault(Fault::INTERPENETRATION, ['b', 'c'], 'two'),
        ];

        self::assertSame(['a', 'b', 'c'], Fault::placementsIn($faults));
    }

    /**
     * A SKRAM at a world position, which is all these checks read.
     *
     * @param array{float, float, float} $position
     */
    private static function at(string $id, array $position): PlacedDevice
    {
        static $device = null;
        $device ??= DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => 'skram',
            'name' => 'SKRAM',
            'quantity' => 8,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 0.610, 'height' => 0.914, 'depth' => 0.762],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.0,
            ],
            'physical' => ['weight_kg' => 90.0, 'handles' => []],
        ]), '/tmp/skram.yaml');

        return new PlacedDevice($id, $device, $position, new Orientation(0.0, 0.0, 0.0));
    }

    /**
     * A transporter big enough to hold the SKRAM above, and the only shape this repository calls hollow.
     *
     * @param array{float, float, float} $position
     */
    private static function bay(string $id, array $position): PlacedDevice
    {
        static $device = null;
        $device ??= DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => 'van',
            'name' => 'Van',
            'category' => 'vehicle',
            'subtype' => 'van',
            'quantity' => 1,
            'geometry' => [
                'shape' => 'load-bay',
                'dimensions_m' => ['width' => 2.070, 'height' => 2.808, 'depth' => 6.848],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.0,
            ],
            'physical' => ['weight_kg' => 2276.0, 'handles' => []],
        ]), '/tmp/van.yaml');

        return new PlacedDevice($id, $device, $position, new Orientation(0.0, 0.0, 0.0));
    }
}
