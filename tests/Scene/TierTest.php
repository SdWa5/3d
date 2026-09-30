<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\MirrorStyle;
use App\Scene\RolledBox;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * One solved row, the two ways of reflecting it, and a row the solver gapped out.
 *
 * `mirrored()` splits a row at its **own** middle, so the row comes out symmetric about itself. `flipped()`
 * reflects the whole row end to end, which is what one stack of a side-by-side pair needs. Getting those two
 * confused would produce a rig that measures right and is built wrong, so they are pinned apart here.
 */
final class TierTest extends TestCase
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

    /**
     * A flip reverses the order and hands every quarter turn the other way.
     *
     * Both halves are needed. Reversing alone would move the cabinets and leave them facing as they were;
     * negating alone would turn them without moving them. Only doing both gives a mirror image.
     */
    public function testAFlipReversesTheOrderAndHandsEveryQuarterTurnTheOtherWay(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $tier = new Tier([[$flexy, 1, 270.0], [$this->devices['skram'], 1, 270.0], [$flexy, 1, 90.0]]);

        $flipped = $tier->flipped();

        self::assertSame(
            '1× flexy-folded-horn-hybrid rolled 270° + 1× skram rolled 90° + 1× flexy-folded-horn-hybrid rolled 90°',
            $flipped->label(),
        );
        // The middle cabinet changes hands, which is the whole point for an odd-count row: the one that had to
        // pick a side picks the other one.
        self::assertSame(90.0, Tier::rollOf($flipped->segments[1]));
    }

    /** An upright segment has no handedness to reverse, only a position. */
    public function testAnUprightSegmentPassesThroughAFlipUnturned(): void
    {
        $tier = new Tier([
            [$this->devices['eighteensound-2way-15'], 1],
            [$this->devices['tecnare-m2122'], 3],
        ]);

        $flipped = $tier->flipped();

        self::assertSame('3× tecnare-m2122 + 1× eighteensound-2way-15', $flipped->label());
        self::assertSame(0.0, Tier::rollOf($flipped->segments[0]));
    }

    /** Flipping twice is the identity, and a symmetric row is its own mirror image. */
    public function testFlippingIsItsOwnInverseAndLeavesASymmetricRowAlone(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $symmetric = new Tier([[$flexy, 1, 270.0], [$flexy, 1, 90.0]]);
        self::assertSame($symmetric->label(), $symmetric->flipped()->label());

        $lopsided = new Tier([[$this->devices['eighteensound-2way-15'], 1], [$this->devices['tecnare-m2122'], 1]]);
        self::assertSame($lopsided->label(), $lopsided->flipped()->flipped()->label());
    }

    /** A flip changes nothing any check reads — same cabinets, same widths, same heights. */
    public function testAFlipChangesNothingTheChecksMeasure(): void
    {
        $tier = new Tier([
            [$this->devices['flexy-folded-horn-hybrid'], 2, 270.0],
            [$this->devices['skram'], 1, 90.0],
        ]);
        $flipped = $tier->flipped();

        self::assertSame($tier->count(), $flipped->count());
        self::assertEqualsWithDelta($tier->widthM(0.02), $flipped->widthM(0.02), 1e-12);
        self::assertEqualsWithDelta($tier->heightM(), $flipped->heightM(), 1e-12);
    }

    /**
     * And `mirrored()` is the other reflection: it splits at the row's own middle rather than reversing it.
     *
     * Pinned beside `flipped()` because the two are one character apart in a call site and a whole rig apart in
     * the result.
     */
    public function testMirroringSplitsAtTheRowsOwnMiddleRatherThanReversingIt(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];

        self::assertSame(
            '2× flexy-folded-horn-hybrid rolled 270° + 2× flexy-folded-horn-hybrid rolled 90°',
            (new Tier([[$flexy, 4, 90.0]]))->mirrored()->label(),
        );
    }

    /**
     * `alternate` flips which side the odd cabinet joins as the wall rises, so the stack balances even though no row
     * does.
     *
     * A row of five rolled cabinets cannot be halved, and there is no arrangement that avoids it — the middle one must
     * roll one way or the other. Cancelling it row by row is the answer that costs nothing geometric.
     */
    public function testAlternateFlipsTheOddCabinetsSideBetweenRows(): void
    {
        $row = Tier::of($this->devices['flexy-folded-horn-hybrid'], 5, 90.0);

        $even = $row->mirrored(MirrorStyle::Alternate, 0);
        $odd = $row->mirrored(MirrorStyle::Alternate, 1);

        // Same cabinets both ways, and every one of them still rolled.
        self::assertSame(5, $even->count());
        self::assertSame(5, $odd->count());
        self::assertStringNotContainsString('rolled 0', $even->label());

        // The extra goes to the right on an even row and to the left on the next one up.
        self::assertSame('2× flexy-folded-horn-hybrid rolled 270° + 3× flexy-folded-horn-hybrid rolled 90°', $even->label());
        self::assertSame('3× flexy-folded-horn-hybrid rolled 270° + 2× flexy-folded-horn-hybrid rolled 90°', $odd->label());
    }

    /**
     * `centred` leaves the middle cabinet standing, which makes the row a palindrome and 172 mm taller.
     *
     * Both halves of that matter. The symmetry is the point; the step is the price, and it is why the style is offered
     * rather than adopted — a rolled Flexy is 591 mm tall and an upright one 763, so anything standing on this row has
     * to bridge it and the bearing rules will usually refuse.
     */
    public function testCentredCentresTheOddCabinetAndStandsItProud(): void
    {
        $tier = Tier::of($this->devices['flexy-folded-horn-hybrid'], 5, 90.0)->mirrored(MirrorStyle::Centred, 0);

        self::assertSame(5, $tier->count());
        self::assertSame(
            '2× flexy-folded-horn-hybrid rolled 270° + 1× flexy-folded-horn-hybrid '
            .'+ 2× flexy-folded-horn-hybrid rolled 90°',
            $tier->label(),
        );

        // The row is as tall as its tallest cabinet, which is now the upright one: 763 against the rolled 591.
        self::assertEqualsWithDelta(0.763, $tier->heightM(), 1e-9);
        self::assertEqualsWithDelta(0.172, $tier->heightStepM(), 1e-9);
    }

    /**
     * `column` sends the odd cabinet to the same side on **every** row, where `alternate` swaps it.
     *
     * Asserted as the pair of rows rather than one, because the whole difference between the two styles is what the
     * *next* row does — a single row cannot tell them apart. `alternate` reads 2 + 3 then 3 + 2, so the wall balances
     * and the seam between the mirrored halves zig-zags; `column` reads 2 + 3 twice, so the stack is lopsided by one
     * cabinet and the seam runs straight up.
     *
     * No step either way, which is what separates this from {@see testCentredCentresTheOddCabinetAndStandsItProud}:
     * every cabinet stays rolled, so the row is one height and nothing above it has to bridge anything.
     */
    public function testColumnKeepsTheOddCabinetOnOneSideWhereAlternateSwapsIt(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];

        $columnRows = array_map(
            static fn (int $row): string => Tier::of($flexy, 5, 90.0)->mirrored(MirrorStyle::Column, $row)->label(),
            [0, 1],
        );
        $alternateRows = array_map(
            static fn (int $row): string => Tier::of($flexy, 5, 90.0)->mirrored(MirrorStyle::Alternate, $row)->label(),
            [0, 1],
        );

        // Both rows identical under `column`, and the second row mirrored under `alternate`.
        self::assertSame($columnRows[0], $columnRows[1], 'column does not depend on the row index');
        self::assertNotSame($alternateRows[0], $alternateRows[1], 'alternate does');
        self::assertSame($columnRows[0], $alternateRows[0], 'they agree on an even row');

        self::assertSame(
            '2× flexy-folded-horn-hybrid rolled 270° + 3× flexy-folded-horn-hybrid rolled 90°',
            $columnRows[0],
        );

        // Every cabinet still rolled, so the row is level — no step for anything above it to bridge. A delta rather
        // than an identity because the two roll directions go through the same trig from opposite ends and land
        // 2.2e-16 apart, which is float noise rather than a step.
        $tier = Tier::of($flexy, 5, 90.0)->mirrored(MirrorStyle::Column, 1);
        self::assertEqualsWithDelta(0.591, $tier->heightM(), 1e-9);
        self::assertEqualsWithDelta(0.0, $tier->heightStepM(), 1e-9);
    }

    /** A gapped row uses its own gap for its width and ignores the stack's. */
    public function testAGappedRowIsWiderByItsOwnGapAndIgnoresTheStacks(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $packed = Tier::of($flexy, 3);
        $gapped = $packed->withGap(0.25);

        self::assertEqualsWithDelta($packed->cabinetWidthM() + 2 * 0.02, $packed->widthM(0.02), 1e-9);
        self::assertEqualsWithDelta($packed->cabinetWidthM() + 2 * 0.25, $gapped->widthM(0.02), 1e-9);
        self::assertSame(0.25, $gapped->gapFor(0.02));
        self::assertSame(0.02, $packed->gapFor(0.02));
    }

    /** The seats of a gapped row sit at the gapped pitch, across a segment boundary too, and stay centred. */
    public function testAGappedRowSeatsItsSegmentsAtTheSpreadPitch(): void
    {
        $flexy = $this->devices['flexy-folded-horn-hybrid'];
        $skram = $this->devices['skram'];
        $tier = (new Tier([[$flexy, 1], [$skram, 2], [$flexy, 1]]))->withGap(0.1);

        $seats = $tier->seats(0.02);
        $flexyWidth = RolledBox::widthOf($flexy, 0.0);
        $skramWidth = RolledBox::widthOf($skram, 0.0);

        self::assertEqualsWithDelta(-$seats[2][2], $seats[0][2], 1e-9);
        self::assertEqualsWithDelta(0.0, $seats[1][2], 1e-9);
        self::assertEqualsWithDelta($flexyWidth / 2 + 0.1 + $skramWidth + 0.1 / 2, $seats[1][2] - $seats[0][2], 1e-9);
    }

    /** Mirror, flip and label all keep the gap, so nothing downstream falls back to the packed row. */
    public function testMirrorFlipAndLabelKeepTheGap(): void
    {
        $tier = (new Tier([[$this->devices['flexy-folded-horn-hybrid'], 2, 90.0]]))->withGap(0.125);

        self::assertSame(0.125, $tier->mirrored()->gapM);
        self::assertSame(0.125, $tier->flipped()->gapM);
        self::assertSame('2× flexy-folded-horn-hybrid rolled 90° at 125 mm gaps', $tier->label());
        self::assertSame('2× flexy-folded-horn-hybrid rolled 90°', (new Tier($tier->segments))->label());
    }
}
