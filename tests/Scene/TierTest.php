<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * One solved row, and the two ways of reflecting it.
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
}
