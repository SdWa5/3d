<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\MirrorStyle;
use App\Scene\RowBudget;
use App\Scene\Stack;
use App\Scene\StackEntry;
use App\Scene\StackMix;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Repeated flanked rows, the arrangement on Innschleife's photo of their stack.
 *
 * Run against the real Innschleife specs, because the case is about their heights: SBH lies at 0.550 m, WSX at
 * 0.570 m and the kicker stands at 0.570 m, and that 20 mm is what decides whether two types count as one row.
 */
final class StackMixFlankedRowsTest extends TestCase
{
    /** What the roster brings, in the order the sweep deals it: `wsx-18` first as the heaviest. */
    private const PHOTO = ['wsx-18' => 4, 'sbh-18' => 4, 'kicker-15' => 4, 'top-70x93' => 1, 'tms2' => 2];

    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $this->devices[$spec->id] = $spec;
        }
    }

    /** Both 0.570 m types may flank the 0.550 m SBH, since 20 mm is inside the tolerance. */
    public function testEveryTypeOfTheSameHeightIsOfferedAsAFlank(): void
    {
        $inventory = $this->inventory(self::PHOTO);

        $flanks = StackMix::levelFlanks($inventory, $this->stack());

        self::assertSame(['wsx-18', 'kicker-15'], array_map(static fn (int $i): string => $inventory[$i][0]->id, $flanks));
    }

    /** The black JBL is 0.600 m high, 50 mm over the SBH, and a row of the two would not be level. */
    public function testATypeOutsideTheToleranceIsNotOffered(): void
    {
        $inventory = $this->inventory(['sbh-18' => 4, 'sub-60x60' => 4]);

        self::assertSame([], StackMix::levelFlanks($inventory, $this->stack(['sbh-18', 'sub-60x60'])));
    }

    /** The photo's base: two rows of [WSX | SBH SBH | WSX], and nothing of either type left over. */
    public function testTheCentreTypeIsSplitOverRowsThatAreFlankedAlike(): void
    {
        $inventory = $this->inventory(self::PHOTO);

        $built = StackMix::flankedRows($inventory, $this->stack(), RowBudget::unbounded(), 0);

        self::assertNotNull($built);
        [$tiers, $remaining] = $built;
        self::assertSame(
            [['wsx-18', 'sbh-18', 'wsx-18'], ['wsx-18', 'sbh-18', 'wsx-18']],
            array_map(static fn (Tier $t): array => array_map(static fn (array $s): string => $s[0]->id, $t->segments), $tiers),
        );
        self::assertSame([1, 2, 1], array_map(static fn (array $s): int => $s[1], $tiers[0]->segments));
        self::assertSame(0, $remaining[0][1]);
        self::assertSame(0, $remaining[1][1]);
        self::assertSame(4, $remaining[2][1]);
    }

    /** One SBH cannot be split over two rows, so there is no repeated row to build. */
    public function testACentreTypeThatCannotBeSplitEvenlyBuildsNothing(): void
    {
        $inventory = $this->inventory(['wsx-18' => 4, 'sbh-18' => 3]);

        self::assertNull(StackMix::flankedRows($inventory, $this->stack(['wsx-18', 'sbh-18']), RowBudget::unbounded(), 0));
    }

    /** A row that does not fit the stage is no candidate. */
    public function testARowWiderThanTheStageBuildsNothing(): void
    {
        $inventory = $this->inventory(self::PHOTO);

        self::assertNull(StackMix::flankedRows($inventory, $this->stack(maxWidthM: 4.0), RowBudget::unbounded(), 0));
    }

    /**
     * **The whole solve picks the photo's rig** once the transition is aimed at 1.75 m, which is how the
     * `innschleife-next-event` folder is generated. At 1.70 m it ties with the unmixed rows at 1.69 m and loses.
     */
    public function testTheSolverBuildsTheRigOnThePhoto(): void
    {
        $result = StackSolver::solve($this->inventory(self::PHOTO), $this->stack());

        self::assertSame([], $result['problems']);
        self::assertSame(
            ['1× wsx-18 + 2× sbh-18 + 1× wsx-18', '1× wsx-18 + 2× sbh-18 + 1× wsx-18', '4× kicker-15', '1× tms2 + 1× top-70x93 + 1× tms2'],
            array_map(fn (Tier $t): string => $this->summary($t), $result['tiers']),
        );
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array{DeviceSpec, int}>
     */
    private function inventory(array $counts): array
    {
        return array_map(fn (string $id): array => [$this->devices[$id], $counts[$id]], array_keys($counts));
    }

    /**
     * The stack the folder is generated with: `mixed` rolls WSX and SBH and leaves the kicker standing.
     *
     * @param list<string>|null $ids
     */
    private function stack(?array $ids = null, ?float $maxWidthM = null): Stack
    {
        return new Stack(
            from: array_map(
                static fn (string $id): StackEntry => new StackEntry(
                    $id,
                    rollMirror: in_array($id, ['wsx-18', 'sbh-18', 'sub-60x60'], true) ? 90.0 : null,
                ),
                $ids ?? array_keys(self::PHOTO),
            ),
            maxWidthM: $maxWidthM,
            interfaceHeightM: 1.6,
            gapM: 0.02,
            maxSubHeightM: 3.0,
            shape: StackShape::Pyramid,
            targetSubHeightM: 1.75,
            mirrorStyle: MirrorStyle::Alternate,
            slideSlackM: INF,
        );
    }

    /** A row as counts per type in order, with neighbouring segments of one type merged, so rolls do not matter. */
    private function summary(Tier $tier): string
    {
        $runs = [];
        foreach ($tier->segments as $segment) {
            $last = array_key_last($runs);
            if (null !== $last && $runs[$last][0] === $segment[0]->id) {
                $runs[$last][1] += $segment[1];
            } else {
                $runs[] = [$segment[0]->id, $segment[1]];
            }
        }

        return implode(' + ', array_map(static fn (array $run): string => $run[1].'× '.$run[0], $runs));
    }
}
