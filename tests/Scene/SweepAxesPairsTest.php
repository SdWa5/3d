<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\MirrorStyle;
use App\Scene\StackOrientation;
use App\Scene\SweepAxes;
use PHPUnit\Framework\TestCase;

/**
 * {@see SweepAxes::pairs} with an event's per-system orientations resolved in.
 */
final class SweepAxesPairsTest extends TestCase
{
    /** A mode that lays down the same cabinets as an earlier one is that rig under another name, and is dropped. */
    public function testAModeRepeatingAnEarlierModesCabinetsIsDropped(): void
    {
        // The fixed system rolls `inn-sub` under every mode, and only `turned` adds `our-sub`.
        $resolve = static fn (?StackOrientation $o): array => StackOrientation::Turned === $o ? ['inn-sub', 'our-sub'] : ['inn-sub'];

        $pairs = SweepAxes::pairs(StackOrientation::cases(), [MirrorStyle::Alternate], [], [], ['inn-sub', 'our-sub'], $resolve);

        self::assertSame(
            [[StackOrientation::Upright, MirrorStyle::Alternate], [StackOrientation::Turned, MirrorStyle::Alternate]],
            $pairs,
        );
    }

    /** A fully fixed rig sweeps one null orientation, and it still gets every style when something is rolled. */
    public function testAFullyFixedRigIsOneStatedCandidatePerStyle(): void
    {
        $pairs = SweepAxes::pairs([null], [], [], [], ['inn-sub'], static fn (?StackOrientation $o): array => ['inn-sub']);

        self::assertSame(
            array_map(static fn (MirrorStyle $style): array => [null, $style], MirrorStyle::cases()),
            $pairs,
        );
    }
}
