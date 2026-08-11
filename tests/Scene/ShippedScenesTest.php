<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use PHPUnit\Framework\TestCase;

/**
 * Every shipped scene, checked for the one mistake a render hides: cabinets inside each other.
 *
 * Runs against the repository's real specs and scenes — the same thing CI already does with
 * `scene:build --dry-run`, which proves a scene *compiles* and says nothing about whether the result is
 * physically buildable. This is the missing half, and it exists because it found a real one: the near-fills
 * of one since-deleted scene sat **0.41 m inside the sub wall** for two releases. Nothing complained, because from
 * the three-quarter camera the fill is in front of the wall and looks fine.
 *
 * The separation is measured with a separating-axis test on each cabinet's own eight corners, and that
 * choice is the whole reason this test is worth having rather than being noise. The obvious cheap version —
 * comparing axis-aligned bounding boxes — reports four of the shipped scenes as broken when they are not:
 * a yawed cabinet's bounding box is much larger than the cabinet, so an arc's neighbouring seats and a line
 * array's neighbouring elements *always* look like they overlap. A test that cries wolf on half the library
 * would be switched off within a week.
 */
final class ShippedScenesTest extends TestCase
{
    /**
     * How far two nominal cabinets may reach into each other before it counts.
     *
     * Not float noise — a millimetre is enormous next to that. It is the one approximation the compiler
     * makes on purpose: an arc's contact is solved at the tilt of its **anchor**, while each seat is then
     * aimed from where it actually stands, and across a three-wide arc those tilts differ by about 0.03°
     * ({@see \App\Scene\SceneCompiler}). Flush faces at 0.03° to each other bite by a few tenths of a
     * millimetre. Against a 10 mm chamfer easing exactly the corners in contact, the built meshes have
     * clearance there.
     *
     * Deliberately two orders of magnitude below anything that has ever gone wrong here: the mistakes this
     * catches were 21.7 mm and 0.41 m.
     */
    private const TOLERANCE_M = 1e-3;

    /**
     * How close a top face has to be to a bottom face to count as carrying it — and how much plan overlap
     * counts as being under something. A millimetre either way; the mistakes this catches are 151 mm gaps.
     */
    private const CONTACT_TOLERANCE_M = 2e-3;

    /**
     * The hexahedron {@see DeviceSpec::shellCorners} builds, as face and edge index lists.
     *
     * Its vertex order is fixed by that method — front plane then back, −x then +x, bottom then top — so the
     * topology can be written down once instead of running a hull algorithm in a test.
     */
    private const FACES = [
        [0, 1, 3, 2], // front
        [4, 5, 7, 6], // back
        [0, 1, 5, 4], // left
        [2, 3, 7, 6], // right
        [0, 2, 6, 4], // bottom
        [1, 3, 7, 5], // top
    ];

    private const EDGES = [
        [0, 1], [2, 3], [4, 5], [6, 7],  // vertical
        [0, 2], [1, 3], [4, 6], [5, 7],  // across
        [0, 4], [1, 5], [2, 6], [3, 7],  // front to back
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function sceneCases(): iterable
    {
        $loader = new SceneLoader(dirname(__DIR__, 2).'/scenes');
        foreach ($loader->files() as $file) {
            $id = basename($file, '.yaml');
            yield $id => [$id];
        }
    }

    /**
     * How far a solved step may sit from the number it replaced.
     *
     * The shipped scenes used to carry these steps as literals somebody bisected by hand and wrote down to
     * four decimal places. The exact fixed points are 2.118461, 2.970946 and 1.886983, so the whole
     * disagreement is the rounding in the old files — under 5e-5 m. A tolerance of 1e-4 admits exactly that
     * and nothing else: it is two orders below the 20 mm clearance these scenes are built around, and three
     * below the 88 mm mistake that spacing cabinets on their widths produced.
     */
    private const HAND_BISECTED_TOLERANCE_M = 1e-4;

    /**
     * The steps `align` now solves, against the numbers the scenes carried before it existed.
     *
     * @return iterable<string, array{string, string, float}>
     */
    public static function handBisectedSteps(): iterable
    {
        yield 'the achenbach row justified across the sub wall' => ['full-rig-stereo', 'achenbach-row', 0.8156];
        yield 'the tops justified across the sub wall, aimed' => ['full-rig-stereo', 'tops', 2.1185];
        yield 'the near-fills inside the outer tops' => ['full-rig-stereo', 'near-fills', 2.9709];
        yield 'the near-fills of the upright rig, inside its tops' => ['full-rig-all-tops', 'near-fills', 1.887];
    }

    /**
     * The point of `align`, checked against the only evidence there is: the numbers that were right before.
     *
     * Three of these four are fixed points rather than arithmetic — the cabinets are aimed, so spreading
     * them toes them in and moves the edge that was being aligned. Somebody bisected each one by hand and
     * wrote it into the scene, where it would have gone stale the moment a cabinet was measured. If this
     * test fails, either the solver drifted or a spec changed and the old number is the stale one.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('handBisectedSteps')]
    public function testTheSolvedStepReproducesTheNumberThatWasBisectedByHand(
        string $sceneId,
        string $placementId,
        float $expected,
    ): void {
        $step = $this->stepOf($this->compile($sceneId), $placementId);

        self::assertEqualsWithDelta($expected, $step, self::HAND_BISECTED_TOLERANCE_M);
    }

    /**
     * The clearance itself, which is what the scene actually asked for — and it is asserted much tighter
     * than the step, because both sides of it come out of the solved geometry and carry no rounding.
     *
     * This is measured on the boxes the cabinets *occupy*: at `full-rig-stereo`'s foci the outer top is
     * toed in 11.4° and the fill beside it 30.9°, and a step worked out from their widths instead would
     * have driven them into each other.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('insetScenes')]
    public function testTheNearFillsStandExactlyTheStatedTwentyMillimetresInsideTheOuterTops(string $sceneId): void
    {
        $placed = $this->compile($sceneId);

        $tops = $this->edgesOf($placed, 'tops');
        $fills = $this->edgesOf($placed, 'near-fills');

        self::assertEqualsWithDelta(0.020, $fills['min'] - $tops['innerLeft'], 1e-6);
        self::assertEqualsWithDelta(0.020, $tops['innerRight'] - $fills['max'], 1e-6);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function insetScenes(): iterable
    {
        yield 'full-rig-stereo' => ['full-rig-stereo'];
        yield 'full-rig-all-tops' => ['full-rig-all-tops'];
        yield 'full-rig-quarter-turned' => ['full-rig-quarter-turned'];
    }

    /**
     * Every cabinet above the floor has something under it.
     *
     * The sibling of the overlap check, and it exists for the same reason: it caught a real one. The sub wall
     * of `full-rig-arc` had two SKRAMs in the middle of its bottom row, and because a SKRAM is 0.914 m tall
     * against a Flexy's 0.763, the row above rested on the SKRAMs and **four of its six Flexys hung 151 mm in
     * the air**. It rendered perfectly plausibly from a three-quarter camera — the gap is behind the front
     * faces — and `scene:build` was happy, because `on:` only reads a top face and never asks whether
     * anything is actually there.
     *
     * A cabinet counts as supported when something's top face is at its bottom face and the two overlap in
     * plan. Flown cabinets are exempt: hanging in the air is the entire point of them.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sceneCases')]
    public function testEveryCabinetAboveTheFloorHasSomethingUnderIt(string $sceneId): void
    {
        $this->assertEveryCabinetIsCarried($this->compile($sceneId), $sceneId);
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function assertEveryCabinetIsCarried(array $placed, string $where): void
    {
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            if ($box['min'][2] < self::CONTACT_TOLERANCE_M || $entry->flyPoint !== null) {
                continue;
            }

            // Not just *something* under it — enough of it. Presence alone passes a cabinet balanced on a
            // 5.6 mm sliver of a taller neighbour, which is exactly what a stepped row produces and what
            // measuring tier widths cannot see either. Half its own extent, the same line
            // {@see \App\Scene\Gravity::MIN_BEARING} draws inside the solver.
            //
            // **Per axis, not as an area.** Multiplying the two fractions was wrong and had been since this
            // check was written: a Flexy is 964 mm deep and a SKRAM 813, so a Flexy standing squarely on a
            // SKRAM covers 84 % of its own depth however perfectly it is centred, and the product read 43 % for
            // a cabinet that is properly stacked. Cabinets of different depths sit on each other in every rig
            // there is. What matters is that neither axis is more than half off.
            foreach (['x' => 0, 'y' => 1] as $name => $axis) {
                $bearing = $this->bearingOf($entry, $placed, $axis);

                self::assertGreaterThan(
                    0.5,
                    $bearing,
                    sprintf(
                        '%s in %s sits at %.3f m on %.1f%% of its own %s extent',
                        $entry->placementId,
                        $where,
                        $box['min'][2],
                        $bearing * 100,
                        $name,
                    ),
                );
            }
        }
    }

    /**
     * How much of this cabinet's `$axis` extent has something under it, as a fraction — 0 for one in mid-air.
     *
     * Everything whose top face meets this cabinet's bottom face counts, and the covered areas are summed: a
     * cabinet bridging two neighbours is carried by both, and a row is normally spread across several supports.
     *
     * Deliberately measured against the **rotated** bounding box, so an aimed top is judged on the box it
     * actually occupies. That understates a yawed cabinet — the box grows while the cabinet does not — which
     * makes the check conservative in the one direction that matters.
     *
     * @param list<PlacedDevice> $placed
     */
    private function bearingOf(PlacedDevice $entry, array $placed, int $axis): float
    {
        $box = $entry->worldBox();
        $extent = $box['max'][$axis] - $box['min'][$axis];
        if ($extent <= 0.0) {
            return 0.0;
        }

        // The union of what is under it, projected onto this axis — a union rather than a sum, so a cabinet
        // resting on two overlapping supports is not credited twice.
        $spans = [];
        foreach ($placed as $other) {
            if ($other === $entry) {
                continue;
            }
            $under = $other->worldBox();

            if (abs($under['max'][2] - $box['min'][2]) > self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $overlap = [];
            foreach ([0, 1] as $plan) {
                $overlap[$plan] = min($box['max'][$plan], $under['max'][$plan])
                    - max($box['min'][$plan], $under['min'][$plan]);
            }
            if ($overlap[0] <= self::CONTACT_TOLERANCE_M || $overlap[1] <= self::CONTACT_TOLERANCE_M) {
                continue;
            }

            $spans[] = [
                max($box['min'][$axis], $under['min'][$axis]),
                min($box['max'][$axis], $under['max'][$axis]),
            ];
        }

        usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $covered = 0.0;
        $reach = -INF;
        foreach ($spans as [$lo, $hi]) {
            $lo = max($lo, $reach);
            if ($hi > $lo) {
                $covered += $hi - $lo;
                $reach = $hi;
            }
        }

        return $covered / $extent;
    }

    /**
     * Every rig `scene:stack` can produce, through the same two sweeps.
     *
     * The gap this closes: `sceneCases()` enumerates `scenes/*.yaml`, so a rig that exists only as a command
     * invocation was checked for support by the solver and never by the separating-axis or bearing sweeps —
     * which is exactly where the risky geometry is. Turned subs, mirrored halves, several stacks side by side
     * and an over-booked Achenbach row all live here and in no scene file.
     *
     * The **command** generates it and the **writer** produces the YAML, so the round trip is on the path too:
     * a roll left out of the written file would come back upright, and only re-reading the file catches that.
     *
     * @return iterable<string, array{list<string>, array<string, mixed>}>
     */
    public static function generatedRigCases(): iterable
    {
        $all = ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'];
        $noSkram = ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'];

        yield 'everything, unbounded' => [$all, []];
        yield 'everything on a 3.70 m stage' => [$all, ['--max-width' => '3.70']];
        yield 'chasing a 12 m interface' => [$all, ['--interface-height' => '12.0']];
        yield 'one stack per owner' => [$all, ['--per-owner' => true]];
        yield 'two stacks' => [$all, ['--stacks' => '2', '--max-width' => '3.70']];
        yield 'three stacks' => [$all, ['--stacks' => '3', '--max-width' => '3.70']];
        yield 'two stacks, subs on their sides' => [
            $all,
            ['--stacks' => '2', '--max-width' => '3.70', '--roll-mirror' => ['skram', 'flexy-folded-horn-hybrid']],
        ];
        yield 'three stacks, subs on their sides' => [
            $all,
            ['--stacks' => '3', '--max-width' => '3.70', '--roll-mirror' => ['skram', 'flexy-folded-horn-hybrid']],
        ];
        yield 'subs on their sides' => [
            $noSkram,
            ['--max-width' => '3.70', '--roll-mirror' => ['flexy-folded-horn-hybrid']],
        ];
    }

    /**
     * @param list<string> $from
     * @param array<string, mixed> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('generatedRigCases')]
    public function testEveryGeneratedRigIsBuildableGeometry(array $from, array $options): void
    {
        $placed = $this->compileGenerated($from, $options);
        if ($placed === null) {
            return;
        }

        $label = 'scene:stack '.json_encode($options, JSON_THROW_ON_ERROR);
        $this->assertNoTwoCabinetsAreInsideEachOther($placed, $label);
        $this->assertEveryCabinetIsCarried($placed, $label);
    }

    /**
     * Runs the real command, writes what it printed into a temp directory and loads it back.
     *
     * Null when the command refused the arrangement — which is a legitimate answer for some of these, and the
     * reason it is checked rather than skipped: a refusal has to **say why**. A silent skip would let a rig
     * that stopped being solvable pass as "nothing to check".
     *
     * @param list<string> $from
     * @param array<string, mixed> $options
     * @return list<PlacedDevice>|null
     */
    private function compileGenerated(array $from, array $options): ?array
    {
        $command = new \App\Command\SceneStackCommand();
        $application = new \Symfony\Component\Console\Application();
        $application->add($command);

        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->execute([
            '--from' => $from,
            '--align' => ['center'],
            '--dry-run' => true,
        ] + $options);
        $display = $tester->getDisplay();

        if ($tester->getStatusCode() !== 0) {
            self::assertMatchesRegularExpression(
                '/cannot|refus|no workable|LEFT OUT|already/i',
                $display,
                'a refused arrangement has to say why',
            );

            return null;
        }

        $at = strpos($display, '# Generated by');
        self::assertNotFalse($at, "the command printed no scene:\n".$display);

        $dir = \App\Tests\Support\SpecFactory::tempDir('sdwa5-3d-generated-');
        try {
            $path = $dir.'/generated.yaml';
            file_put_contents($path, substr($display, $at));

            $devices = [];
            foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
                /** @var DeviceSpec $spec */
                $devices[$spec->id] = $spec;
            }

            $scene = (new SceneLoader($dir))->find($path)['scene'];
            self::assertNotNull($scene, 'the written scene did not load back');

            $result = (new SceneCompiler($devices))->compile($scene);
            self::assertSame(
                [],
                array_map(static fn ($v): string => $v->message, \App\Spec\Violation::errorsIn($result['violations'])),
                'a written scene has to compile cleanly',
            );

            return $result['placed'];
        } finally {
            \App\Tests\Support\SpecFactory::removeDir($dir);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sceneCases')]
    public function testNoTwoCabinetsAreInsideEachOther(string $sceneId): void
    {
        $this->assertNoTwoCabinetsAreInsideEachOther($this->compile($sceneId), $sceneId);
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function assertNoTwoCabinetsAreInsideEachOther(array $placed, string $where): void
    {
        self::assertNotSame([], $placed, "'{$where}' placed nothing");

        $hulls = array_map(fn (PlacedDevice $entry): array => $this->corners($entry), $placed);

        $worst = 0.0;
        $offenders = '';
        foreach ($placed as $i => $a) {
            foreach ($placed as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                // Boxes that do not even share a bounding box cannot intersect, and skipping them keeps
                // this from being the slowest test in the suite.
                if (!$this->boxesTouch($a, $b)) {
                    continue;
                }

                $separation = $this->separation($hulls[$i], $hulls[$j]);
                if ($separation < $worst) {
                    $worst = $separation;
                    $offenders = "{$a->placementId} and {$b->placementId}";
                }
            }
        }

        self::assertGreaterThan(
            -self::TOLERANCE_M,
            $worst,
            sprintf('%s are %.4f m inside each other in %s', $offenders, -$worst, $where),
        );
    }

    /**
     * A sanity check on the checker: two cabinets deliberately driven into each other have to be caught,
     * or the test above passing would mean nothing.
     */
    public function testTheCheckActuallyDetectsAnIntersection(): void
    {
        $placed = $this->compile('full-rig-all-tops');
        $hulls = array_map(fn (PlacedDevice $entry): array => $this->corners($entry), $placed);

        // Same cabinet twice, the second shifted a centimetre: unmistakably intersecting.
        $shifted = array_map(static fn (array $c): array => [$c[0] + 0.01, $c[1], $c[2]], $hulls[0]);

        self::assertLessThan(-0.4, $this->separation($hulls[0], $shifted), 'an overlap must read negative');
        self::assertGreaterThan(0.0, $this->separation($hulls[0], $hulls[3]), 'and clear air must read positive');
    }

    /**
     * Separation of two convex hexahedra: positive is clear air, 0 is touching, negative is how far they
     * interpenetrate. Exact, because for convex solids some axis among the two sets of face normals and the
     * cross products of their edge directions is separating whenever they are disjoint.
     *
     * @param list<array{float, float, float}> $a
     * @param list<array{float, float, float}> $b
     */
    private function separation(array $a, array $b): float
    {
        $widest = -INF;
        foreach ($this->axes($a, $b) as $axis) {
            $length = sqrt($axis[0] ** 2 + $axis[1] ** 2 + $axis[2] ** 2);
            if ($length < 1e-9) {
                // Parallel edges or a degenerate face give no axis to test.
                continue;
            }

            $unit = [$axis[0] / $length, $axis[1] / $length, $axis[2] / $length];
            [$aMin, $aMax] = $this->project($a, $unit);
            [$bMin, $bMax] = $this->project($b, $unit);

            $gap = max($bMin - $aMax, $aMin - $bMax);
            if ($gap > $widest) {
                $widest = $gap;
            }
            if ($widest > 0.0) {
                // One separating axis is proof enough; the rest cannot make them intersect.
                break;
            }
        }

        return $widest;
    }

    /**
     * @param list<array{float, float, float}> $a
     * @param list<array{float, float, float}> $b
     * @return list<array{float, float, float}>
     */
    private function axes(array $a, array $b): array
    {
        $normals = [];
        $edges = [];
        foreach ([$a, $b] as $hull) {
            foreach (self::FACES as $face) {
                $normals[] = $this->cross(
                    $this->minus($hull[$face[1]], $hull[$face[0]]),
                    $this->minus($hull[$face[2]], $hull[$face[0]]),
                );
            }
            $own = [];
            foreach (self::EDGES as [$from, $to]) {
                $own[] = $this->minus($hull[$to], $hull[$from]);
            }
            $edges[] = $own;
        }

        // Face normals first: for boxes standing on a floor one of them almost always separates, so the
        // 144 edge pairs below are rarely reached.
        $axes = $normals;
        foreach ($edges[0] as $one) {
            foreach ($edges[1] as $other) {
                $axes[] = $this->cross($one, $other);
            }
        }

        return $axes;
    }

    /**
     * @param list<array{float, float, float}> $hull
     * @param array{float, float, float} $axis
     * @return array{float, float}
     */
    private function project(array $hull, array $axis): array
    {
        $min = INF;
        $max = -INF;
        foreach ($hull as $point) {
            $along = $point[0] * $axis[0] + $point[1] * $axis[1] + $point[2] * $axis[2];
            $min = min($min, $along);
            $max = max($max, $along);
        }

        return [$min, $max];
    }

    /**
     * The cabinet's eight corners where they actually stand: the spec's own shape, turned the way the
     * compiler turned it, at the position it lifted it to.
     *
     * @return list<array{float, float, float}>
     */
    private function corners(PlacedDevice $entry): array
    {
        $origin = $entry->liftedPosition();

        $corners = [];
        foreach ($entry->device->shellCorners() as $corner) {
            $rotated = $entry->orientation->apply($corner);
            $corners[] = [
                $origin[0] + $rotated[0],
                $origin[1] + $rotated[1],
                $origin[2] + $rotated[2],
            ];
        }

        return $corners;
    }

    private function boxesTouch(PlacedDevice $a, PlacedDevice $b): bool
    {
        $boxA = $a->worldBox();
        $boxB = $b->worldBox();
        for ($axis = 0; $axis < 3; ++$axis) {
            if ($boxA['min'][$axis] - $boxB['max'][$axis] > 0.0 || $boxB['min'][$axis] - $boxA['max'][$axis] > 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
     * @return array{float, float, float}
     */
    private function minus(array $a, array $b): array
    {
        return [$a[0] - $b[0], $a[1] - $b[1], $a[2] - $b[2]];
    }

    /**
     * @param array{float, float, float} $a
     * @param array{float, float, float} $b
     * @return array{float, float, float}
     */
    private function cross(array $a, array $b): array
    {
        return [
            $a[1] * $b[2] - $a[2] * $b[1],
            $a[2] * $b[0] - $a[0] * $b[2],
            $a[0] * $b[1] - $a[1] * $b[0],
        ];
    }

    /**
     * @return list<PlacedDevice>
     */
    /**
     * The uniform spacing of a placement's cabinets along x, which is what `step_m` used to state outright.
     *
     * @param list<PlacedDevice> $placed
     */
    private function stepOf(array $placed, string $placementId): float
    {
        $x = [];
        foreach ($placed as $entry) {
            if (str_starts_with($entry->placementId, $placementId.'-')) {
                $x[] = $entry->position[0];
            }
        }
        sort($x);

        self::assertGreaterThan(1, count($x), "placement '{$placementId}' has no spacing to read");

        return ($x[count($x) - 1] - $x[0]) / (count($x) - 1);
    }

    /**
     * A placement's outer edges, and the facing edges of its outermost cabinets.
     *
     * @param list<PlacedDevice> $placed
     * @return array{min: float, max: float, innerLeft: float, innerRight: float}
     */
    private function edgesOf(array $placed, string $placementId): array
    {
        $min = INF;
        $max = -INF;
        $innerLeft = -INF;
        $innerRight = INF;

        foreach ($placed as $entry) {
            if (!str_starts_with($entry->placementId, $placementId.'-')) {
                continue;
            }
            $box = $entry->worldBox();
            if ($box['min'][0] < $min) {
                $min = $box['min'][0];
                $innerLeft = $box['max'][0];
            }
            if ($box['max'][0] > $max) {
                $max = $box['max'][0];
                $innerRight = $box['min'][0];
            }
        }

        return ['min' => $min, 'max' => $max, 'innerLeft' => $innerLeft, 'innerRight' => $innerRight];
    }

    private function compile(string $sceneId): array
    {
        $project = dirname(__DIR__, 2);

        $devices = [];
        foreach ((new SpecLoader($project.'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }

        $scene = (new SceneLoader($project.'/scenes'))->find($sceneId)['scene'];
        self::assertNotNull($scene, "no scene '{$sceneId}'");

        $result = (new SceneCompiler($devices))->compile($scene);

        // Errors only: a shipped scene may carry warnings — `full-rig-all-speakers` reports a stepped mixed
        // row and an 18 mm overhang — and those describe a rig that builds rather than one that does not.
        self::assertSame(
            [],
            array_map(static fn ($v): string => $v->message, \App\Spec\Violation::errorsIn($result['violations'])),
            "scene '{$sceneId}' does not compile cleanly",
        );

        return $result['placed'];
    }
}
