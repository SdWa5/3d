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

    /**
     * **A row wider than its envelope keeps its own spacing and warns.** It is not compressed and not refused.
     *
     * This is the assertion that pins the bug `block` shipped with. Its parameter is a factor on each copy's x
     * offset, and the solver was allowed to search below 1, so an envelope narrower than the row was met by pulling
     * the cabinets *into each other* — measured on the real rig, a tops row of three aimed turbo tops at 1.406 m
     * asked to fit the 1.200 m nuke row beneath it, and neighbours ended up 92 mm inside each other. Every
     * interpenetration refusal in the sweep was this.
     *
     * Three 0.6 m cabinets at a 0.02 m gap are 1.84 m across, and 0.3 m is far narrower, so there is nothing to
     * spread. Natural spacing is the answer, and the round numbers say so: centres at −0.62, 0 and +0.62.
     */
    public function testARowWiderThanItsEnvelopeKeepsItsOwnSpacing(): void
    {
        $result = (new SceneCompiler($this->devices))->compile($this->scene([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'block', 'width_m' => 0.3],
                'row' => ['count' => 3, 'gap_m' => 0.02]],
        ]));

        self::assertEqualsWithDelta([-0.62, 0.0, 0.62], $this->positions($result['placed']), 1e-6);
        self::assertEqualsWithDelta(1.84, $this->extent($result['placed']), 1e-6);

        $errors = array_filter(
            $result['violations'],
            static fn ($v): bool => $v->severity !== \App\Spec\Violation::WARNING,
        );
        self::assertSame([], array_map(static fn ($v): string => $v->message, $errors), 'a warning, not an error');
        self::assertStringContainsString(
            'there is nothing to spread',
            implode("\n", array_map(static fn ($v): string => $v->message, $result['violations'])),
        );
    }

    /**
     * **An aimed row keeps the working gap it was given, between the cabinets and not just on paper.**
     *
     * The last relationship nothing used to space. `align` justifies a row into an envelope, `align.outside` holds it
     * clear of a neighbour and the tier chain spaces each run outside the one inboard of it — every one of them about a
     * run and something *else*. A row is laid out at `gap_m` from nominal widths and then every cabinet is yawed towards
     * the focus, which swings its front corners towards its neighbour, so the air asked for is not the air there is. On
     * the real rigs that was 17.6 mm of one cabinet inside the next and twelve refused candidates.
     *
     * Asserted on the gap rather than on positions, because the gap is the promise and the positions are how it is kept.
     * A 2 m focus is chosen to toe the cabinets in hard enough that nominal spacing is definitely not enough.
     */
    public function testAnAimedRowKeepsItsWorkingGapBetweenNeighbours(): void
    {
        // Compiled directly rather than through the helper, because keeping the gap is *reported*: the row was given
        // 20 mm and could not have it at the spacing it was laid out with, which is worth a line in the build output.
        $result = (new SceneCompiler($this->devices))->compile($this->scene([
            ['id' => 'row', 'device' => 'top', 'at' => [0.0, 0.0], 'aim' => 'focus',
                'row' => ['count' => 4, 'gap_m' => 0.02]],
        ], ['distance_m' => 2.0, 'height_m' => 1.4]));

        $errors = array_filter(
            $result['violations'],
            static fn ($v): bool => $v->severity !== \App\Spec\Violation::WARNING,
        );
        self::assertSame([], array_map(static fn ($v): string => $v->message, $errors));
        self::assertGreaterThanOrEqual(0.02 - 1e-6, \App\Scene\Interpenetration::narrowestGap($result['placed']));
        self::assertStringContainsString(
            'toe into each other',
            implode("\n", array_map(static fn ($v): string => $v->message, $result['violations'])),
        );
    }

    /**
     * And the no-op that keeps it safe: an **unaimed** row is not touched, because nothing turned its cabinets and
     * their boxes are their widths. Every scene in the library depends on this, so it is pinned rather than assumed.
     */
    public function testAnUnaimedRowIsLeftExactlyWhereItWas(): void
    {
        $placed = $this->compile([
            ['id' => 'row', 'device' => 'sub', 'at' => [0.0, 0.0], 'row' => ['count' => 4, 'gap_m' => 0.02]],
        ]);

        // Four 0.6 m cabinets at a 0.02 m gap: centres 0.62 apart, and not a micrometre more.
        self::assertEqualsWithDelta([-0.93, -0.31, 0.31, 0.93], $this->positions($placed), 1e-9);
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
     * `outside` measures the room **past** a placement's outer faces, which is the third thing a fill can be
     * solved against and the one `align` could not express.
     *
     * `across` and `inside` are both widths my cabinets must *span*; this is a clearance they must *keep*, on the
     * far side of somebody else's edges. `full-rig-arc` carried the gap as a comment — "the fills have to clear
     * the arc's outer faces … 2.60 puts them about 20 mm clear" — and it turned out 2.60 left 37.8 mm.
     */
    public function testOutsideSolvesForTheRoomPastAPlacementsOuterFaces(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'row' => ['count' => 3, 'step_m' => 1.5]],
            ['id' => 'fills', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'stereo', 'outside' => 'tops', 'inset_m' => 0.02],
                'row' => ['count' => 2]],
        ]);

        // Three 0.5 m tops on a 1.5 m step span 3.5 m. Two 0.6 m subs 20 mm clear of that, one either side, put
        // their inner faces at ±1.77 and so span 3.5 + 2×0.02 + 2×0.6 = 4.74 m outer to outer.
        self::assertEqualsWithDelta(4.74, $this->extent(array_slice($placed, 3)), 1e-6);
    }

    /**
     * `inset_m` is a **minimum**, so cabinets already further out are left exactly where they are.
     *
     * Not merely permissive — it is the reading that keeps other decisions intact. A fill that gravity re-seated
     * onto a shoulder because its bearing demanded it sits 517 mm clear, and pulling it back to 20 mm would undo
     * that repair. Where the natural spacing does bite, the solve still pushes out until the air is really there.
     */
    public function testOutsideLeavesCabinetsThatAlreadyClearByMoreThanAsked(): void
    {
        $placed = $this->compile([
            ['id' => 'tops', 'device' => 'top', 'at' => [0.0, 0.0], 'row' => ['count' => 1]],
            ['id' => 'fills', 'device' => 'sub', 'at' => [0.0, 0.0],
                'align' => ['mode' => 'stereo', 'outside' => 'tops', 'inset_m' => 0.02],
                'row' => ['count' => 2, 'gap_m' => 6.0]],
        ]);

        // Two 0.6 m subs 6 m apart: 7.2 m across, untouched.
        self::assertEqualsWithDelta(7.2, $this->extent(array_slice($placed, 1)), 1e-6);
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
            "align.mode 'block' needs something to solve against",
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
        yield 'a forward reference on a clearance solve' => [
            $base + ['align' => ['mode' => 'block', 'outside' => 'later', 'inset_m' => 0.02], 'row' => ['count' => 3]],
            "align.outside: 'later' must name an earlier placement",
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
