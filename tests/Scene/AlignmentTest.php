<?php

declare(strict_types=1);

namespace App\Tests\Scene;

use App\Scene\PlacedDevice;
use App\Scene\SceneCompiler;
use App\Scene\SceneSpec;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `align` — spreading a tier across a width instead of stating the step by hand.
 *
 * The fixtures are round numbers on purpose (0.6 m and 0.5 m cabinets, 4 m envelopes) so the expected
 * positions can be read off the file rather than taken on trust. The scenes that carry the real,
 * hand-bisected numbers are checked in {@see ShippedScenesTest}.
 */
final class AlignmentTest extends TestCase
{
    /** @var array<string, DeviceSpec> */
    private array $devices;

    protected function setUp(): void
    {
        $this->devices = [
            // 0.6 m wide, so four of them touching span 2.4 m and the arithmetic stays in one's head.
            'sub' => SpecFactory::spec([
                'id' => 'sub',
                'subtype' => 'sub',
                'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 0.6, 'depth' => 1.0]],
                'physical' => ['weight_kg' => 80.0],
            ]),
            'top' => SpecFactory::spec([
                'id' => 'top',
                'geometry' => ['dimensions_m' => ['width' => 0.5, 'height' => 0.9, 'depth' => 0.5]],
                'physical' => ['weight_kg' => 30.0],
            ]),
        ];
    }

    public function testBlockJustifiesAnUnaimedRowAcrossAStatedWidth(): void
    {
        $placed = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'block', 'width_m' => 4.0],
                'row' => ['count' => 4]],
        ]);

        // Four 0.6 m cabinets justified across 4.0 m: outer centres at ±1.7, inner at ±0.5666…
        self::assertEqualsWithDelta([-1.7, -17 / 30, 17 / 30, 1.7], $this->positions($placed), 1e-6);
        self::assertEqualsWithDelta(4.0, $this->extent($placed), 1e-6);
    }

    /** The no-op guarantee: writing `center` has to mean exactly what writing nothing means. */
    public function testCenterIsWhatEveryRowAlreadyDid(): void
    {
        $stated = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'center'],
                'row' => ['count' => 5, 'gap_m' => 0.02]],
        ]);
        $implied = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'row' => ['count' => 5, 'gap_m' => 0.02]],
        ]);

        self::assertSame($this->positions($implied), $this->positions($stated));
    }

    /**
     * `stereo` splits on the sign of a copy's natural offset, so an even count has no middle cabinet and
     * the hole between the two columns is the whole point of asking for it.
     */
    public function testStereoLeavesTheCentreEmptyOnAnEvenCount(): void
    {
        $placed = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'stereo', 'width_m' => 4.0],
                'row' => ['count' => 4]],
        ]);

        // Two columns of two, each still touching, pushed out by 0.8 m.
        self::assertEqualsWithDelta([-1.7, -1.1, 1.1, 1.7], $this->positions($placed), 1e-6);
        self::assertEqualsWithDelta(4.0, $this->extent($placed), 1e-6);
    }

    public function testStereoKeepsTheOddCabinetOutOnTheCentreLine(): void
    {
        $placed = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'stereo', 'width_m' => 4.0],
                'row' => ['count' => 5]],
        ]);

        self::assertEqualsWithDelta([-1.7, -1.1, 0.0, 1.1, 1.7], $this->positions($placed), 1e-6);
    }

    /**
     * The reason the whole feature exists. Aiming toes a cabinet in and a toed-in cabinet is wider across x
     * than it is wide, so the step that puts its outer edge on a stated width is **not** the step arithmetic
     * on half-widths produces — and the gap between the two is centimetres, not rounding.
     */
    public function testAnAimedRowsOuterEdgeIsSolvedAndNotComputedFromItsWidth(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'aim' => 'focus',
                'align' => ['mode' => 'block', 'width_m' => 4.0],
                'row' => ['count' => 3]],
        ], ['distance_m' => 2.0, 'height_m' => 1.4]);

        self::assertEqualsWithDelta(4.0, $this->extent($placed), 1e-6);

        // What half-widths would have said: (4.0 − 0.5) / 2 = 1.75 from the centre. The cabinets are toed
        // in hard by a 2 m focus, so the real answer is well inside that.
        $solved = $this->positions($placed)[2];
        self::assertLessThan(1.75, $solved);
        self::assertGreaterThan(1.0, $solved);
        // And every cabinet really is turned, which is what makes the naive answer wrong.
        self::assertNotEqualsWithDelta(0.0, $placed[2]->yawDeg(), 1.0);
    }

    /**
     * `across` is a placement's outer edges, `inside` is the gap between its outermost cabinets' facing
     * edges. Two different objects — confusing them is what would drop a fill on top of a top.
     */
    public function testAcrossIsTheExtentAndInsideIsTheGapBetweenTheOutermostCabinets(): void
    {
        $across = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'row' => ['count' => 3, 'step_m' => 1.5]],
            ['id' => 'fills', 'device' => 'sub', 'at' => [0.0, -2.0],
                'align' => ['mode' => 'block', 'across' => 'tops'],
                'row' => ['count' => 2]],
        ]);
        $inside = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'row' => ['count' => 3, 'step_m' => 1.5]],
            ['id' => 'fills', 'device' => 'sub', 'at' => [0.0, -2.0],
                'align' => ['mode' => 'block', 'inside' => 'tops'],
                'row' => ['count' => 2]],
        ]);

        // Three 0.5 m tops on a 1.5 m step: 3.5 m across, and 2.5 m of clear air between the outer two.
        self::assertEqualsWithDelta(3.5, $this->extent(array_slice($across, 3)), 1e-6);
        self::assertEqualsWithDelta(2.5, $this->extent(array_slice($inside, 3)), 1e-6);
    }

    /** The inset is taken off each side, which is how "20 mm inside the outer tops" is written. */
    public function testTheInsetIsTakenOffBothSidesOfTheEnvelope(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'row' => ['count' => 3, 'step_m' => 1.5]],
            ['id' => 'fills', 'device' => 'sub', 'at' => [0.0, -2.0],
                'align' => ['mode' => 'block', 'inside' => 'tops', 'inset_m' => 0.02],
                'row' => ['count' => 2]],
        ]);

        self::assertEqualsWithDelta(2.5 - 0.04, $this->extent(array_slice($placed, 3)), 1e-6);
    }

    /**
     * An envelope reads the whole placement and not just its anchor. `$byId` holds the anchor copy alone,
     * so reading `across` off it would size a twelve-cabinet wall from one cabinet — silently, and
     * plausibly, which is the worst way for it to be wrong.
     */
    public function testAnEnvelopeReadsEveryCabinetOfThePlacementAndNotJustItsAnchor(): void
    {
        $placed = $this->compile([
            ['id' => 'wall', 'device' => 'sub', 'at' => [0.0, 0.0], 'row' => ['count' => 6]],
            ['id' => 'row', 'device' => 'top', 'at' => [0.0, -2.0],
                'align' => ['mode' => 'block', 'across' => 'wall'],
                'row' => ['count' => 3]],
        ]);

        // Six 0.6 m cabinets touching is 3.6 m, not the 0.6 m of the anchor alone.
        self::assertEqualsWithDelta(3.6, $this->extent(array_slice($placed, 6)), 1e-6);
    }

    /**
     * @param array<string, mixed> $placement
     */
    #[DataProvider('rejections')]
    public function testRejects(array $placement, string $expected): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene([$placement]));

        $messages = array_map(static fn ($v): string => $v->message, $result['violations']);
        self::assertNotSame([], $messages, 'expected a violation');
        self::assertStringContainsString($expected, implode("\n", $messages));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejections(): iterable
    {
        $base = ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0]];

        yield 'block with no envelope' => [
            $base + ['align' => ['mode' => 'block'], 'row' => ['count' => 3]],
            "align.mode 'block' needs a width to fill",
        ];
        yield 'center given an envelope' => [
            $base + ['align' => ['mode' => 'center', 'width_m' => 4.0], 'row' => ['count' => 3]],
            "align.mode 'center' is the natural spacing",
        ];
        yield 'two envelopes at once' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0, 'across' => 'other'], 'row' => ['count' => 3]],
            'not two',
        ];
        yield 'a step the align would overrule' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0], 'row' => ['count' => 3, 'step_m' => 1.2]],
            'align solves the spacing — remove lattice.step_m',
        ];
        yield 'a single cabinet has nothing to space' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0], 'row' => ['count' => 1]],
            'align needs more than one cabinet across x',
        ];
        yield 'no group at all' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0]],
            'align needs a single row or lattice',
        ];
        yield 'an arc decides its own spacing' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0], 'arc' => ['mode' => 'convex', 'count' => 3]],
            'align needs a single row or lattice',
        ];
        yield 'a nested group cannot be scaled without scaling the nest' => [
            $base + [
                'align' => ['mode' => 'block', 'width_m' => 4.0],
                'row' => ['count' => 3],
                'in' => [['lattice' => ['count' => [1, 1, 2]]]],
            ],
            'align needs a single row or lattice',
        ];
        yield 'a forward reference' => [
            $base + ['align' => ['mode' => 'block', 'across' => 'later'], 'row' => ['count' => 3]],
            "align.across: 'later' must name an earlier placement",
        ];
        yield 'an envelope narrower than the cabinets' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 0.3], 'row' => ['count' => 3]],
            'even stacked on one spot',
        ];
        yield 'an inset that eats the envelope' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0, 'inset_m' => 3.0], 'row' => ['count' => 3]],
            'leaves nothing of the',
        ];
        yield 'a negative inset' => [
            $base + ['align' => ['mode' => 'block', 'width_m' => 4.0, 'inset_m' => -0.1], 'row' => ['count' => 3]],
            'align.inset_m must not be negative',
        ];
    }

    public function testReadingRejectsAnUnknownAlignKey(): void
    {
        $this->expectException(\App\Spec\InvalidSpecException::class);
        $this->expectExceptionMessage("align: unknown key 'witdh_m'");

        $this->scene([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'block', 'witdh_m' => 4.0],
                'row' => ['count' => 3]],
        ]);
    }

    /**
     * A placement used to accept any key at all, so `algn:` read as "not aligned" and rendered a perfectly
     * plausible, silently un-spread tier.
     */
    public function testReadingRejectsAMistypedPlacementKey(): void
    {
        $this->expectException(\App\Spec\InvalidSpecException::class);
        $this->expectExceptionMessage("unknown key 'algn'");

        $this->scene([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'algn' => ['mode' => 'block', 'width_m' => 4.0],
                'row' => ['count' => 3]],
        ]);
    }

    /**
     * @param list<PlacedDevice> $placed
     * @return list<float>
     */
    private function positions(array $placed): array
    {
        $x = array_map(static fn (PlacedDevice $device): float => $device->position[0], $placed);
        sort($x);

        return $x;
    }

    /**
     * @param list<PlacedDevice> $placed
     */
    private function extent(array $placed): float
    {
        $min = INF;
        $max = -INF;
        foreach ($placed as $device) {
            $box = $device->worldBox();
            $min = min($min, $box['min'][0]);
            $max = max($max, $box['max'][0]);
        }

        return $max - $min;
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @param array<string, mixed>|null $focus
     * @return list<PlacedDevice>
     */
    private function compile(array $placements, ?array $focus = null): array
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene($placements, $focus));

        self::assertSame([], array_map(static fn ($v): string => $v->message, $result['violations']));

        return $result['placed'];
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @param array<string, mixed>|null $focus
     */
    private function scene(array $placements, ?array $focus = null): SceneSpec
    {
        $data = ['id' => 'test', 'name' => 'Test scene', 'placements' => $placements];
        if ($focus !== null) {
            $data['focus'] = $focus;
        }

        return SceneSpec::fromArray($data, '/scenes/test.yaml');
    }
}
