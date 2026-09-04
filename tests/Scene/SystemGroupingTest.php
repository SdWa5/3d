<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\SystemGrouping;
use App\Scene\SystemSplit;
use PHPUnit\Framework\TestCase;

/**
 * Which owners are one sound system.
 *
 * **The rig that made this necessary was rendered before it existed**: `systems-apart` on the next event stood
 * four walls side by side — Sepp's, PSL's, ours and Innschleife's — because the separation partitioned on the
 * `owner` field and `sdwa5` and `sepp` are two owners. They are one system. Three walls is the right answer and
 * nothing in the repository could say so.
 */
final class SystemGroupingTest extends TestCase
{
    public function testTheDefaultMakesOurGearAndSeppsOneSystem(): void
    {
        $grouping = SystemGrouping::of([]);
        self::assertInstanceOf(SystemGrouping::class, $grouping);

        self::assertSame('ours', $grouping->systemOf('sdwa5'));
        self::assertSame('ours', $grouping->systemOf('sepp'));
        self::assertSame(1, $grouping->countIn(['sdwa5', 'sepp']));
    }

    /**
     * An owner no group names is its own system under its own name. A borrowed system is one system, and listing
     * it would add nothing.
     */
    public function testAnUngroupedOwnerIsItsOwnSystem(): void
    {
        $grouping = SystemGrouping::of([]);
        self::assertInstanceOf(SystemGrouping::class, $grouping);

        self::assertSame('psl', $grouping->systemOf('psl'));
        self::assertSame(3, $grouping->countIn(['sdwa5', 'sepp', 'psl', 'innschleife']));
    }

    /**
     * **One system has nothing to separate**, which is the second half of the fix: the separation axis used to be
     * offered to `sdwa5-sepp` because it counted two owners, and two of its three values then solved a rig that
     * stands our own gear apart from itself.
     */
    public function testASingleSystemIsOfferedPooledAlone(): void
    {
        $grouping = SystemGrouping::of([]);
        self::assertInstanceOf(SystemGrouping::class, $grouping);

        self::assertSame([SystemSplit::Pooled], SystemSplit::forOwnerCount($grouping->countIn(['sdwa5', 'sepp'])));
        self::assertCount(3, SystemSplit::forOwnerCount($grouping->countIn(['sdwa5', 'sepp', 'psl'])));
    }

    /**
     * Stating a group replaces the default rather than adding to it, so the option says the whole truth about the
     * run it is typed on.
     */
    public function testAStatedGroupReplacesTheDefault(): void
    {
        $grouping = SystemGrouping::of(['borrowed:gmss+psl']);
        self::assertInstanceOf(SystemGrouping::class, $grouping);

        self::assertSame('borrowed', $grouping->systemOf('gmss'));
        self::assertSame('borrowed', $grouping->systemOf('psl'));
        self::assertSame('sdwa5', $grouping->systemOf('sdwa5'), 'the default pair is not carried over');
        self::assertSame('sepp', $grouping->systemOf('sepp'));
    }

    public function testAnOwnerInTwoSystemsIsRefused(): void
    {
        $result = SystemGrouping::of(['a:sdwa5+sepp', 'b:sepp']);

        self::assertIsString($result);
        self::assertStringContainsString("'sepp' is in two systems", $result);
    }

    public function testAGroupThatIsNotNameColonOwnersIsRefused(): void
    {
        foreach (['ours', 'ours:', ':sdwa5', 'ours:sdwa5+'] as $bad) {
            self::assertIsString(SystemGrouping::of([$bad]), $bad);
        }
    }
}
