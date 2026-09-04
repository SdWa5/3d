<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\StackOrientation;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Which cabinets each orientation lays on their sides, measured against the real specs.
 *
 * Pinned on the actual inventory rather than on invented devices, because the whole point of the modes is that they are
 * *derived* from what the specs record — `subtype` and `dimensions_m` — and a rule tested against fixtures would keep
 * passing after the fact it depends on had changed.
 */
final class StackOrientationTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices = [];

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    /** Nothing rolls, whatever it is and whatever it measures. */
    public function testUprightRollsNothing(): void
    {
        self::assertSame([], StackOrientation::Upright->rolls($this->devices, array_keys($this->devices)));
        self::assertFalse(StackOrientation::Upright->rollsAnything($this->devices, array_keys($this->devices)));
    }

    /**
     * `turned` rolls every sub and **no top at any setting**, which is the one rule here that is not geometry.
     *
     * A top's horn throws its pattern in one orientation and rolling the cabinet rolls the pattern with it, so a rolled
     * top is a rig that measures right and is acoustically wrong. Low frequency is near-omnidirectional, which is why
     * the same objection does not reach a sub.
     */
    public function testTurnedRollsEverySubAndNoTop(): void
    {
        $ids = ['flexy-folded-horn-hybrid', 'skram', 'mid-bass', 'achenbach-18', 'tecnare-m2122', 'turbo-top'];

        self::assertSame(
            ['flexy-folded-horn-hybrid', 'skram', 'mid-bass', 'achenbach-18'],
            StackOrientation::Turned->rolls($this->devices, $ids),
        );
    }

    /**
     * `mixed` rolls a sub only where rolling makes it wider and shorter, which is the whole reason to roll one.
     *
     * Two cabinets are left standing and for two different reasons. `mid-bass` is 1.200 × 0.500 — the one sub
     * already wider than it is tall — so rolling it would make the wall *taller* and the row narrower, the opposite of
     * what the mode is for. `achenbach-18` is 0.600 × 0.600, where rolling is geometrically nothing at all.
     */
    public function testMixedLeavesTheCabinetsThatGainNothingStanding(): void
    {
        $ids = ['flexy-folded-horn-hybrid', 'mid-bass', 'achenbach-18', 'wall-bass'];

        self::assertSame(
            ['flexy-folded-horn-hybrid', 'wall-bass'],
            StackOrientation::Mixed->rolls($this->devices, $ids),
        );
    }

    /**
     * A mode that rolls nothing in *this* inventory is the same rig as `upright`, and says so.
     *
     * This is what the sweep asks before offering a candidate, so that a `-mixed-` scene always has something turned in
     * it. `sepp`'s subs are all Achenbachs, so `mixed` has nothing to turn there while `turned` still does.
     */
    public function testAModeWithNothingToRollReportsSo(): void
    {
        $sepp = ['achenbach-18', 'eighteensound-2way-15'];

        self::assertTrue(StackOrientation::Turned->rollsAnything($this->devices, $sepp));
        self::assertFalse(StackOrientation::Mixed->rollsAnything($this->devices, $sepp));

        // And a rig of nothing but tops has nothing for either of them.
        $tops = ['tecnare-m2122', 'turbo-top'];
        self::assertFalse(StackOrientation::Turned->rollsAnything($this->devices, $tops));
        self::assertFalse(StackOrientation::Mixed->rollsAnything($this->devices, $tops));
    }

    /** An id the specs do not know is ignored rather than fatal — the command validates `--from` for itself. */
    public function testAnUnknownIdIsSkipped(): void
    {
        self::assertSame([], StackOrientation::Turned->rolls($this->devices, ['no-such-device']));
    }
}
