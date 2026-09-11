<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\Feasibility;
use App\Scene\Interpenetration;
use App\Scene\PlacedDevice;
use App\Scene\PlacementChecks;
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
     * The spec library and the scene loader, built once for the whole class instead of once per case.
     *
     * **THIS WAS 2661 FULL RE-READS OF `specs/` AND `scenes/`, AND IT BOUGHT NOTHING.** `compile()` runs once
     * per data-provider case, and it used to open a fresh {@see SpecLoader} and re-parse all 38 spec YAMLs
     * every single time, then open a fresh {@see SceneLoader} and call `find()` — which walks and sorts all
     * 2727 scene files — in order to locate a path the provider had just handed it. Measured per call:
     * `loadAll()` 6.3 ms, `find()` 3.7 ms, `load()` on a path you already have 0.5 ms. Across the class that
     * is about 25 s locally.
     *
     * **Static is safe here and only here.** Nothing in this class writes, so neither input can change under
     * it mid-run, and no test anywhere in the suite writes into `specs/`. It must **not** be hoisted into a
     * shared helper for {@see \App\Tests\Command\SceneStackTestCase},
     * {@see \App\Tests\Command\BuildAllCommandTest} or {@see SceneKeyTest}, all of
     * which write into `scenes/generated/` during a test and need a listing that reflects what they wrote.
     *
     * Lazy accessors rather than `setUpBeforeClass()`, because {@see sceneCases} is a data provider and PHPUnit
     * runs providers during discovery, before any class fixture has been set up.
     *
     * @var array<string, DeviceSpec>|null
     */
    private static ?array $devices = null;

    private static ?SceneLoader $loader = null;

    /** @var list<string>|null */
    private static ?array $files = null;

    /**
     * @return array<string, DeviceSpec>
     */
    private static function devices(): array
    {
        if (null === self::$devices) {
            $devices = [];
            foreach ((new SpecLoader(self::project().'/specs'))->loadAll()['specs'] as $spec) {
                /** @var DeviceSpec $spec */
                $devices[$spec->id] = $spec;
            }
            self::$devices = $devices;
        }

        return self::$devices;
    }

    private static function loader(): SceneLoader
    {
        return self::$loader ??= new SceneLoader(self::project().'/scenes');
    }

    /**
     * @return list<string>
     */
    private static function files(): array
    {
        return self::$files ??= self::loader()->files();
    }

    private static function project(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * How far two nominal cabinets may reach into each other before it counts.
     *
     * Not float noise — a millimetre is enormous next to that. It is the one approximation the compiler
     * makes on purpose: an arc's contact is solved at the tilt of its **anchor**, while each seat is then
     * aimed from where it actually stands, and across a three-wide arc those tilts differ by about 0.03°
     * ({@see SceneCompiler}). Flush faces at 0.03° to each other bite by a few tenths of a
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

    /**
     * Every scene this test holds to standing up, which is every scene **except the ones that say they do not**.
     *
     * **The exclusion is a property of the name and not a list somebody maintains**, which is the condition CVR-5
     * set for itself. The sweep's sixth axis is `possible` / `impossible`, and an impossible rig is in the
     * repository precisely because it fails one of the checks below — it is written so the failure can be looked at
     * in a render with the offending cabinets caged in red, rather than read about in a terminal line that scrolls
     * away. Holding it to the same promise as the rest would make this test's whole meaning "every scene stands up
     * except the ones I remembered to add to an array", which is not a promise at all.
     *
     * {@see Feasibility::isImpossibleId} is the single place that decides, so the writer and the test
     * cannot drift into disagreeing about which files are which.
     *
     * @return iterable<string, array{string}>
     */
    public static function sceneCases(): iterable
    {
        $project = self::project();
        foreach (self::files() as $file) {
            if (Feasibility::isImpossibleId(basename($file, '.yaml'))) {
                continue;
            }
            // **THE PATH, NOT THE ID, AND THAT IS NOT A COSMETIC CHOICE.** The inventory moved out of the generated
            // file name and into a folder, so ten inventories now hold a
            // `stacked-1-pooled--------free----turned--alternate-center-possible.yaml` each. Keyed on the id this
            // provider fed PHPUnit ten identical keys — which it refuses outright — and before it refused, every
            // one of those cases would have compiled whichever of the ten `SceneLoader::find()` happened to reach
            // first. A path is unique by construction.
            $relative = substr($file, strlen($project) + 1);
            yield $relative => [$relative];
        }
    }

    /**
     * **And the impossible ones really are impossible**, which is the other half of the exclusion above.
     *
     * Without this, the axis is a way to opt any rig out of every geometry check in the repository by naming it —
     * so the guard has to run the same checks and insist they *fail*. A scene that says it does not stand up and
     * then does is either a solver that improved or a rig that was mislabelled, and both are worth being told
     * about rather than being quietly carried.
     */
    public function testEveryImpossibleSceneReallyFailsACheck(): void
    {
        $loader = self::loader();
        $specs = self::devices();

        $checked = 0;
        foreach (self::files() as $file) {
            $id = basename($file, '.yaml');
            if (!Feasibility::isImpossibleId($id)) {
                continue;
            }

            ++$checked;
            $placed = (new SceneCompiler($specs))->compile($loader->load($file))['placed'];
            $faults = [
                ...PlacementChecks::floatingFaults($placed),
                ...Interpenetration::faults($placed, PlacementChecks::CONTACT_TOLERANCE_M),
            ];

            self::assertNotSame(
                [],
                $faults,
                $id.' is named impossible and passes every check — either the solver improved or the name is wrong',
            );
        }

        self::assertGreaterThan(0, $checked, 'the sweep writes impossible rigs, so some should be on disk');
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
    public function testEveryCabinetStandsOnSomethingAndInsideNothing(string $sceneId): void
    {
        // **ONE COMPILE, TWO CHECKS, AND THAT IS WORTH A LINE.** These were two tests over the same data provider,
        // so every scene in the repository was compiled twice — 25 minutes at 2688 scenes, for two questions that
        // read the same geometry. Merged, it is half that and nothing is checked less. What a merge usually costs
        // is a failure that does not name its own cause; it does not here, because each assertion carries its own
        // message and the scene's path.
        $placed = $this->compile($sceneId);

        $this->assertEveryCabinetIsCarried($placed, $sceneId);
        $this->assertNoTwoCabinetsAreInsideEachOther($placed, $sceneId);
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function assertEveryCabinetIsCarried(array $placed, string $where): void
    {
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            if ($box['min'][2] < self::CONTACT_TOLERANCE_M || null !== $entry->flyPoint) {
                continue;
            }

            // Not just *something* under it — **level** on it. Presence alone passes a cabinet balanced on a
            // 5.6 mm sliver of a taller neighbour, which is what a stepped row produces and what measuring tier
            // widths cannot see either.
            //
            // Measured as the tilt it would come to rest at, which is the rule the solver enforces and the only
            // one that separates the two cases: a cabinet half off its support with the rest over a surface
            // 151 mm down rocks 27°, while one left 19 mm proud over a 630 mm overhang settles 1.7° and is a
            // shim. A fraction of a footprint reads both the same. Re-derived here from the placed world boxes
            // rather than from the solver's tiers, which is what keeps this an independent check.
            // **Something under it, in both axes** — and only that. How far a cabinet would *tilt* on what it
            // finds is the solver's question, enforced by {@see \App\Scene\Stability::MAX_SETTLE_DEG} and
            // pinned in `GravityTest`; re-deriving it here needs "the tier immediately below", which world
            // boxes alone do not tell you. A first attempt searched everything downwards, found the floor
            // under a 20 mm overhang and reported a properly built rig as resting 89° out of level. A narrower
            // check that is right beats a broader one that is not.
            foreach (['x' => 0, 'y' => 1] as $name => $axis) {
                $bearing = $this->bearingOf($entry, $placed, $axis);

                self::assertGreaterThan(
                    0.0,
                    $bearing,
                    sprintf(
                        '%s in %s sits at %.3f m with nothing under its %s extent',
                        $entry->placementId,
                        $where,
                        $box['min'][2],
                        $name,
                    ),
                );
            }
        }
    }

    /**
     * How much of this cabinet's `$axis` extent has something under it, as a fraction — 0 for one in mid-air.
     *
     * A union rather than a sum, so a cabinet resting on two overlapping supports is not credited twice, and
     * measured against the **rotated** bounding box so an aimed top is judged on the box it actually occupies.
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
     * @param list<PlacedDevice> $placed
     */
    private function assertNoTwoCabinetsAreInsideEachOther(array $placed, string $where): void
    {
        self::assertNotSame([], $placed, "'{$where}' placed nothing");

        // The separating-axis geometry lives in `src/` now, because `scene:stack` refuses a candidate on this same
        // test before writing it — see {@see \App\Scene\Interpenetration}. Two copies would have drifted.
        ['separation' => $worst, 'pair' => $offenders] = Interpenetration::worst($placed);

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

        // A rig that is known good must read clear...
        self::assertGreaterThanOrEqual(0.0, Interpenetration::worst($placed)['separation']);

        // ...and the same cabinet placed twice must not. This is the check checking itself: a detector that always
        // returns "fine" would pass every scene in the library and mean nothing.
        $doubled = [...$placed, $placed[0]];
        $found = Interpenetration::worst($doubled);
        self::assertLessThan(-0.4, $found['separation'], 'a cabinet inside another must read negative');
        self::assertNotSame('', $found['pair'], 'and it must name the two');
    }

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
     *
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
        $devices = self::devices();

        // A case from {@see sceneCases} is a project-relative path and is resolved here rather than left to the
        // process's working directory; the hand-written cases below are still bare ids, which are unique.
        //
        // **The path form is loaded rather than searched for, which is the whole of the 3.2 ms per case.**
        // {@see SceneLoader::find} exists to resolve a bare id, and to do that it has to walk and sort all 2727
        // files and then report an ambiguity if the id names more than one. A path out of {@see sceneCases}
        // came from `files()` in the first place, so it exists by construction and there is nothing to resolve.
        // The bare-id branch still goes through `find()`, because that is exactly the case that needs it.
        if (str_contains($sceneId, '/')) {
            $scene = self::loader()->load(self::project().'/'.$sceneId);
        } else {
            $scene = self::loader()->find($sceneId)['scene'];
            self::assertNotNull($scene, "no scene '{$sceneId}'");
        }

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
