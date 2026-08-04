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
 * in `two-foci.yaml` sat **0.41 m inside the sub wall** for two releases. Nothing complained, because from
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

    #[\PHPUnit\Framework\Attributes\DataProvider('sceneCases')]
    public function testNoTwoCabinetsAreInsideEachOther(string $sceneId): void
    {
        $placed = $this->compile($sceneId);
        self::assertNotSame([], $placed, "scene '{$sceneId}' placed nothing");

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
            sprintf('%s are %.4f m inside each other in %s', $offenders, -$worst, $sceneId),
        );
    }

    /**
     * A sanity check on the checker: two cabinets deliberately driven into each other have to be caught,
     * or the test above passing would mean nothing.
     */
    public function testTheCheckActuallyDetectsAnIntersection(): void
    {
        $placed = $this->compile('full-rig');
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

        self::assertSame(
            [],
            array_map(static fn ($v): string => $v->message, $result['violations']),
            "scene '{$sceneId}' does not compile cleanly",
        );

        return $result['placed'];
    }
}
