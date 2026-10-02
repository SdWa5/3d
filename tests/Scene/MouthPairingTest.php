<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\MouthMode;
use App\Scene\MouthPairing;
use App\Scene\Stack;
use App\Scene\Tier;
use App\Spec\ArrayReader;
use App\Spec\DeviceSpec;
use App\Spec\InvalidSpecException;
use App\Spec\MouthSide;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * GEO-16: horn subs turned so their mouths meet, with every cabinet left where the solve put it.
 */
final class MouthPairingTest extends TestCase
{
    /** A low mouth faces down upright, and the 270° turn sends it to +x, as the A3 render of 2026-10-02 showed. */
    public function testALowMouthFacesWhereTheRollSendsTheBottomOfTheBox(): void
    {
        self::assertSame([0, -1], MouthSide::Low->facing(0.0));
        self::assertSame([1, 0], MouthSide::Low->facing(270.0));
        self::assertSame([-1, 0], MouthSide::Low->facing(90.0));
        self::assertSame([0, 1], MouthSide::Low->facing(180.0));
        self::assertSame([-1, 0], MouthSide::High->facing(270.0));
    }

    /**
     * The scratch-skram-turned row, Flexy pair, SKRAM pair, Flexy pair. Mirrored, every Flexy mouth faces the centre
     * line, so the two Flexys of each pair face the same way. Paired, the inner one of each pair turns round, which is
     * the A3 render with columns 2 and 5 turned.
     */
    public function testATurnedFlexyPairBesideTheSkramsTurnsItsInnerCabinet(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $skram = self::horn('skram', null);
        $row = new Tier([[$flexy, 2, 270.0], [$skram, 1, 270.0], [$skram, 1, 90.0], [$flexy, 2, 90.0]]);

        self::assertSame(
            '1× flexy rolled 270° + 1× flexy rolled 90° + 1× skram rolled 270° + 1× skram rolled 90° + 1× flexy rolled 270° + 1× flexy rolled 90°',
            MouthPairing::pair([$row])[0]->label(),
        );
    }

    /** Six Flexys centred on the row pair from both ends, and the middle pair already met at the mirror seam. */
    public function testACentredRunPairsFromBothEnds(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $row = new Tier([[$flexy, 3, 270.0], [$flexy, 3, 90.0]]);

        self::assertSame(
            '1× flexy rolled 270° + 1× flexy rolled 90° + 1× flexy rolled 270° + 1× flexy rolled 90° + 1× flexy rolled 270° + 1× flexy rolled 90°',
            MouthPairing::pair([$row])[0]->label(),
        );
    }

    /** An odd run left of centre pairs from its outer end, so its spare is the cabinet nearest the centre line. */
    public function testAnOddRunKeepsItsSpareNearestTheCentre(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $skram = self::horn('skram', null);
        $row = new Tier([[$flexy, 3, 270.0], [$skram, 2, 0.0], [$flexy, 3, 90.0]]);

        self::assertSame(
            '1× flexy rolled 270° + 1× flexy rolled 90° + 1× flexy rolled 270° + 2× skram'
            .' + 1× flexy rolled 90° + 1× flexy rolled 270° + 1× flexy rolled 90°',
            MouthPairing::pair([$row])[0]->label(),
        );
    }

    /** The second stack of a stereo pair is the flipped first one, and it has to pair into the flipped pairing. */
    public function testAFlippedRowPairsIntoTheFlippedPairing(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $other = self::horn('other', MouthSide::High);
        $row = new Tier([[$flexy, 3, 270.0], [$other, 2, 270.0], [$flexy, 2, 90.0]]);

        self::assertSame(
            MouthPairing::pair([$row])[0]->flipped()->label(),
            MouthPairing::pair([$row->flipped()])[0]->label(),
        );
    }

    /** Two devices side by side never make a mouth together, whatever their sides. */
    public function testDifferentDevicesDoNotPair(): void
    {
        $row = new Tier([[self::horn('a', MouthSide::Low), 1, 270.0], [self::horn('b', MouthSide::Low), 1, 270.0]]);

        self::assertSame($row, MouthPairing::pair([$row])[0]);
    }

    /** A spec that states no mouth side is never turned, because nothing says turning it pairs anything. */
    public function testACabinetWithoutAMouthSideIsLeftAlone(): void
    {
        $plain = self::horn('plain', null);
        $tiers = [Tier::of($plain, 4), Tier::of($plain, 4), new Tier([[$plain, 2, 270.0], [$plain, 2, 90.0]])];

        self::assertSame($tiers, MouthPairing::pair($tiers));
    }

    /**
     * Upright rows of the same cabinets pair bottom-up. A low mouth in the lower row turns over to face up into the
     * row above, and the third row has no partner and stays.
     */
    public function testUprightRowsPairBottomUpAndTheOddTopRowStays(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $paired = MouthPairing::pair([Tier::of($flexy, 4), Tier::of($flexy, 4), Tier::of($flexy, 4)]);

        self::assertSame(['4× flexy rolled 180°', '4× flexy', '4× flexy'], array_map(static fn (Tier $t): string => $t->label(), $paired));
    }

    /** A high mouth already faces up in the lower row, so it is the upper row that turns over. */
    public function testAHighMouthTurnsTheUpperRowInstead(): void
    {
        $horn = self::horn('horn', MouthSide::High);
        $paired = MouthPairing::pair([Tier::of($horn, 2), Tier::of($horn, 2)]);

        self::assertSame(['2× horn', '2× horn rolled 180°'], array_map(static fn (Tier $t): string => $t->label(), $paired));
    }

    /** Rows that do not stand column for column, by count or by gap, are not pairs. */
    public function testRowsThatDoNotLineUpAreNotPaired(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);

        self::assertSame('4× flexy', MouthPairing::pair([Tier::of($flexy, 4), Tier::of($flexy, 3)])[0]->label());
        self::assertSame('4× flexy', MouthPairing::pair([Tier::of($flexy, 4), Tier::of($flexy, 4)->withGap(0.1)])[0]->label());
    }

    /**
     * Pairing changes rolls and nothing else. The segments gravity solves on stay as dealt, because a run there is one
     * roll and pairing written into them made gravity settle every cabinet on its own. See StackTest for the rig.
     */
    public function testPairingMovesNothing(): void
    {
        $flexy = self::horn('flexy', MouthSide::Low);
        $tiers = [new Tier([[$flexy, 3, 270.0], [$flexy, 3, 90.0]]), Tier::of($flexy, 4), Tier::of($flexy, 4)];

        foreach (MouthPairing::pair($tiers) as $index => $tier) {
            self::assertSame($tiers[$index]->segments, $tier->segments);
            self::assertEqualsWithDelta($tiers[$index]->widthM(0.02), $tier->widthM(0.02), 1e-9);
            self::assertEqualsWithDelta($tiers[$index]->heightM(), $tier->heightM(), 1e-9);
        }
    }

    /** The stack key, `paired` when unstated, and a misspelling refused rather than read as the default. */
    public function testTheStackKeyDefaultsToPairedAndRefusesAnUnknownValue(): void
    {
        $block = ['from' => ['flexy'], 'interface_height_m' => 1.6];

        self::assertSame(MouthMode::Paired, Stack::fromReader(new ArrayReader($block))->mouths);
        self::assertSame(MouthMode::Free, Stack::fromReader(new ArrayReader($block + ['mouths' => 'free']))->mouths);

        $this->expectException(InvalidSpecException::class);
        Stack::fromReader(new ArrayReader($block + ['mouths' => 'pair']));
    }

    /** The spec field, read from `audio.mouth_side`, and null when a spec says nothing. */
    public function testTheSpecFieldIsReadFromTheAudioSection(): void
    {
        self::assertSame(MouthSide::Low, self::horn('flexy', MouthSide::Low)->mouthSide);
        self::assertNull(self::horn('plain', null)->mouthSide);

        $this->expectException(InvalidSpecException::class);
        SpecFactory::spec(['audio' => ['mouth_side' => 'middle']]);
    }

    /** Both of our horn subs open in the lower half, the Flexy by its CAD and photo, the SKRAM by its CAD's front. */
    public function testOurHornSubsStateALowMouth(): void
    {
        $sides = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            if ($spec instanceof DeviceSpec && null !== $spec->mouthSide) {
                $sides[$spec->id] = $spec->mouthSide;
            }
        }
        ksort($sides);

        self::assertSame(['flexy-folded-horn-hybrid' => MouthSide::Low, 'skram' => MouthSide::Low], $sides);
    }

    /** A sub of the Flexy's box, with or without a mouth side. */
    private static function horn(string $id, ?MouthSide $side): DeviceSpec
    {
        $audio = null === $side ? [] : ['mouth_side' => $side->value];

        return SpecFactory::spec([
            'id' => $id,
            'subtype' => 'sub',
            'geometry' => ['dimensions_m' => ['width' => 0.591, 'height' => 0.763, 'depth' => 0.964]],
            'audio' => $audio,
        ]);
    }
}
