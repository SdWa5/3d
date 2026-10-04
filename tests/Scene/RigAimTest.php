<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Interpenetration;
use App\Scene\LayoutMode;
use App\Scene\PlacementChecks;
use App\Scene\RigAim;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Scene\Stack;
use App\Scene\StackBlock;
use App\Scene\StackEntry;
use App\Scene\StackShape;
use App\Scene\StackSolver;
use App\Scene\Tier;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * A pooled stack's seating check aims from the rig's centre, the way the finished scene aims it (GEO-11).
 */
final class RigAimTest extends TestCase
{
    /** The pair 0.142.0 lost on `sdwa5-sepp`, left stack mirrored, both aimed at the scene's one focus. */
    private const POOLED_PAIR = <<<'YAML'
        id: pooled-pair
        name: pooled pair
        focus:
          far: { distance_m: 10.0, height_m: 1.8 }
          near: { distance_m: 2.0, height_m: 1.8 }
        placements:
          - id: main-1
            at: [-1.764, 0.0]
            aim: far
            stack:
              interface_height_m: 2.0
              max_sub_height_m: 3.0
              gap_m: 0.02
              shape: pyramid
              mirror: true
              from:
                - { device: skram, count: 1 }
                - { device: flexy-folded-horn-hybrid, count: 6 }
                - { device: achenbach-18, count: 3 }
                - { device: tecnare-m2122, count: 1 }
                - { device: eighteensound-2way-15, count: 1, aim: near }
          - id: main-2
            at: [1.16, 0.0]
            aim: far
            stack:
              interface_height_m: 2.0
              max_sub_height_m: 3.0
              gap_m: 0.02
              shape: pyramid
              from:
                - { device: skram, count: 1 }
                - { device: flexy-folded-horn-hybrid, count: 6 }
                - { device: achenbach-18, count: 3 }
                - { device: tecnare-m2122, count: 2 }
                - { device: eighteensound-2way-15, count: 1, aim: near }
        YAML;

    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        $this->devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            $this->devices[$spec->id] = $spec;
        }
    }

    /**
     * Seated for its own centre, the right-hand stack of this pair won `A·S·A` on top, and aimed from the rig's
     * centre two of its tops then stood 38.9 mm inside each other.
     */
    public function testAPooledPairIsSeatedForTheCentreTheSceneAimsItFrom(): void
    {
        $result = $this->compile(self::POOLED_PAIR);

        self::assertGreaterThanOrEqual(
            -PlacementChecks::CONTACT_TOLERANCE_M,
            Interpenetration::worst($result['placed'])['separation'],
        );
        self::assertSame([], PlacementChecks::floatingFaults($result['placed']));
    }

    /** One stack's own centre is the rig's, so `scene:stack` leaves its solve alone. */
    public function testASingleBlockIsReturnedUntouched(): void
    {
        $block = $this->block('main', 0);

        self::assertSame([$block], RigAim::reaimed([$block], 0.0, 0.24, $this->devices));
    }

    /**
     * The command re-solves a block that stands off the rig's centre with the same check the compiler asks, so the
     * pair's right-hand stack comes out of `scene:stack` with the rows its build produces.
     */
    public function testTheCommandReSolvesAnOffCentreBlockForTheRigCentre(): void
    {
        $left = $this->block('main-1', 1, mirror: true);
        $right = $this->block('main-2', 2);

        [, $reaimed] = RigAim::reaimed([$left, $right], 0.0, 0.24, $this->devices);

        $stacked = SceneCompiler::stackSurvives(
            $this->devices,
            RigAim::probePlacement('main-2', $right->stack, null),
            $reaimed->tiers,
            RigAim::focusPointsAt(-$this->centreOf($left, $reaimed)),
        );
        self::assertTrue($stacked, 'the re-solved block does not stand when aimed from the rig centre');
        self::assertNotSame($this->rows($right), $this->rows($reaimed));
    }

    /**
     * **A block's probe is told the rig's front face, which is the deepest block's.** Every block stands on one `at`,
     * flush at its own deepest cabinet, so a block of Achenbachs alone stands 0.132 m behind one that holds a Flexy.
     * Its tops are aimed at a focus 10 m in front of the Flexy's front, and the probe has to measure from there too.
     */
    public function testTheProbeAimsFromTheDeepestBlocksFront(): void
    {
        $flexy = $this->block('main-1', 1);
        $achenbachs = new StackBlock(
            placementId: 'main-2',
            label: 'main-2',
            stack: $flexy->stack,
            tiers: [Tier::of($this->devices['achenbach-18'], 3)],
            from: ['achenbach-18'],
            warnings: [],
        );

        self::assertEqualsWithDelta(0.964 / 2, RigAim::frontSetbackM($flexy), 1e-9);
        self::assertEqualsWithDelta(0.700 / 2, RigAim::frontSetbackM($achenbachs), 1e-9);

        $far = RigAim::focusPointsAt(1.2, -RigAim::frontSetbackM($flexy))['far'];
        self::assertEqualsWithDelta([1.2, -0.482 - 10.0, 1.8], $far->point([0.0, -0.35]), 1e-9);
    }

    /**
     * @return array{placed: list<\App\Scene\PlacedDevice>, violations: list<\App\Spec\Violation>}
     */
    private function compile(string $yaml): array
    {
        $root = sys_get_temp_dir().'/sdwa5-rig-aim-'.bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root.'/scene.yaml', $yaml);

        try {
            return (new SceneCompiler($this->devices))->compile((new SceneLoader($root))->load($root.'/scene.yaml'));
        } finally {
            unlink($root.'/scene.yaml');
            rmdir($root);
        }
    }

    /** One block of {@see POOLED_PAIR}, solved alone at the origin the way `scene:stack` solves it first. */
    private function block(string $id, int $tecnares, bool $mirror = false): StackBlock
    {
        $counts = [
            'skram' => 1,
            'flexy-folded-horn-hybrid' => 6,
            'achenbach-18' => 3,
            'tecnare-m2122' => max(1, $tecnares),
            'eighteensound-2way-15' => 1,
        ];
        $stack = new Stack(
            from: [
                new StackEntry('skram'),
                new StackEntry('flexy-folded-horn-hybrid'),
                new StackEntry('achenbach-18'),
                new StackEntry('tecnare-m2122'),
                new StackEntry('eighteensound-2way-15', aim: 'near'),
            ],
            maxWidthM: null,
            interfaceHeightM: 2.0,
            gapM: 0.02,
            maxSubHeightM: 3.0,
            shape: StackShape::Pyramid,
            mirror: $mirror,
        );
        $solved = StackSolver::solve(
            array_map(fn (string $device): array => [$this->devices[$device], $counts[$device]], array_keys($counts)),
            $stack,
            LayoutMode::Center,
            SceneCompiler::seatingCheck(
                $this->devices,
                RigAim::probePlacement($id, $stack, LayoutMode::Center),
                RigAim::focusPointsAt(0.0),
            ),
        );
        self::assertSame([], $solved['problems']);

        return new StackBlock(
            placementId: $id,
            label: $id,
            stack: $stack,
            tiers: $solved['tiers'],
            from: array_keys($counts),
            warnings: $solved['warnings'],
        );
    }

    /** Where the rig's centre stands from the right-hand block's own centre, both laid out 0.24 m apart. */
    private function centreOf(StackBlock $left, StackBlock $right): float
    {
        return \App\Scene\StackSceneWriter::centres([$left, $right], 0.0, 0.24)[1];
    }

    /**
     * @return list<string>
     */
    private function rows(StackBlock $block): array
    {
        return array_map(static fn (Tier $tier): string => $tier->label(), $block->tiers);
    }
}
