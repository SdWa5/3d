<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\DeviceSpec;
use App\Spec\SpecValidator;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class SpecValidatorTest extends TestCase
{
    private SpecValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SpecValidator('/project');
    }

    public function testAValidSpecHasNoViolations(): void
    {
        self::assertSame([], $this->validate(SpecFactory::spec()));
    }

    public function testTwoStackedHornsMayShareOneMouth(): void
    {
        // The arrangement the Tecnare has: apart on z, lined up on x, and the wall between them removed
        // for less than either horn is deep.
        $spec = SpecFactory::spec(['audio' => ['layout' => self::layout([
            ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.12], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 4.0, 'depth_m' => 0.2],
            ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.12], 'mouth_m' => [0.4, 0.2],
                'throat_in' => 4.0, 'depth_m' => 0.2, 'join' => ['with' => 'lf-up', 'depth_m' => 0.018]],
        ])]]);

        self::assertSame([], $this->validate($spec));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejectionCases(): iterable
    {
        yield 'negative width' => [
            ['geometry' => ['dimensions_m' => ['width' => -1.0, 'height' => 0.6, 'depth' => 0.45]]],
            'geometry.dimensions_m.width must be greater than 0',
        ];
        yield 'zero height' => [
            ['geometry' => ['dimensions_m' => ['width' => 0.8, 'height' => 0.0, 'depth' => 0.45]]],
            'geometry.dimensions_m.height must be greater than 0',
        ];
        yield 'chamfer too large' => [
            ['geometry' => ['chamfer_m' => 0.4]],
            'must stay below half the smallest edge',
        ];
        yield 'negative chamfer' => [
            ['geometry' => ['chamfer_m' => -0.01]],
            'geometry.chamfer_m must not be negative',
        ];
        yield 'zero weight' => [
            ['physical' => ['weight_kg' => 0.0]],
            'physical.weight_kg must be greater than 0',
        ];
        yield 'quantity below one' => [
            ['quantity' => 0],
            'quantity must be at least 1',
        ];
        yield 'id with underscores' => [
            ['id' => 'top_a'],
            'must be lowercase words separated by single dashes',
        ];
        yield 'bad colour' => [
            ['appearance' => ['color' => 'black']],
            'must be a #rrggbb hex colour',
        ];
        yield 'bad grille colour' => [
            ['appearance' => ['grille' => ['inset_m' => 0.012, 'color' => '#xyz']]],
            'appearance.grille.color',
        ];
        yield 'grille inset deeper than half the cabinet' => [
            ['appearance' => ['grille' => ['inset_m' => 0.3, 'color' => '#0a0a0a']]],
            'must stay below half the depth',
        ];
        yield 'subtype not valid for category' => [
            ['subtype' => 'tower'],
            "subtype 'tower' is not valid for category 'speaker'",
        ];
        yield 'self-built without an original' => [
            ['build' => 'self-built', 'clone_of' => null],
            'build is \'self-built\' but clone_of is missing',
        ];
        yield 'own design that still names an original' => [
            ['build' => 'own-design', 'provenance' => 'measured'],
            "clone_of is set but build is 'own-design'",
        ];
        yield 'clone with datasheet provenance but no original named' => [
            ['build' => 'self-built', 'clone_of' => null, 'provenance' => 'datasheet'],
            "provenance.dimensions is 'datasheet' but no clone_of names where that came from",
        ];
        yield 'clone whose weight cites plans with no original named' => [
            [
                'build' => 'self-built',
                'clone_of' => null,
                'provenance' => ['dimensions' => 'measured', 'weight' => 'plans'],
            ],
            "provenance.weight is 'plans' but no clone_of names where that came from",
        ];
        yield 'owner with spaces' => [
            ['owner' => 'Wall Bass'],
            "owner 'Wall Bass' must be lowercase words separated by single dashes",
        ];
        yield 'unknown clone reference' => [
            ['clone_of' => ['manufacturer' => 'Acme', 'model' => 'X1', 'reference' => 'hearsay']],
            "clone_of.reference 'hearsay' is unknown",
        ];
        yield 'flyable without rigging points' => [
            ['rigging' => ['flyable' => true, 'points' => []]],
            'flyable is true but no rigging.points',
        ];
        yield 'rigging points but not flyable' => [
            ['rigging' => [
                'flyable' => false,
                'points' => [['id' => 'top-left', 'position_m' => [-0.3, -0.2, 0.6]]],
            ]],
            'rigging.points are defined but rigging.flyable is false',
        ];
        yield 'rigging point outside the cabinet' => [
            ['rigging' => [
                'flyable' => true,
                'points' => [['id' => 'top-left', 'position_m' => [-0.3, -0.2, 9.0]]],
            ]],
            "rigging point 'top-left' is outside the cabinet on z",
        ];
        yield 'duplicate rigging point id' => [
            ['rigging' => [
                'flyable' => true,
                'points' => [
                    ['id' => 'top-left', 'position_m' => [-0.3, -0.2, 0.6]],
                    ['id' => 'top-left', 'position_m' => [0.3, -0.2, 0.6]],
                ],
            ]],
            "duplicate rigging point id 'top-left'",
        ];
        yield 'rigging-point origin without any points' => [
            ['geometry' => ['origin' => 'rigging-point']],
            "origin is 'rigging-point' but no rigging.points",
        ];
        yield 'coverage out of range' => [
            ['audio' => ['coverage_deg' => ['horizontal' => 400, 'vertical' => 60]]],
            'audio.coverage_deg.horizontal must be between 0 and 360',
        ];
        yield 'driver with no size' => [
            ['audio' => ['drivers' => [['size_in' => 0, 'type' => 'woofer', 'count' => 1]]]],
            'audio.drivers[0].size_in must be greater than 0',
        ];
        yield 'trapezoid without a back width' => [
            ['geometry' => ['shape' => 'trapezoid']],
            "geometry.back_width_m is required for shape 'trapezoid'",
        ];
        yield 'back width on a plain box' => [
            ['geometry' => ['back_width_m' => 0.5]],
            "geometry.back_width_m only applies to shape 'trapezoid'",
        ];
        yield 'back width wider than the front' => [
            ['geometry' => ['shape' => 'trapezoid', 'back_width_m' => 1.2]],
            'must not exceed geometry.dimensions_m.width',
        ];
        yield 'wedge without a front height' => [
            ['geometry' => ['shape' => 'wedge']],
            "geometry.front_height_m is required for shape 'wedge'",
        ];
        // The truss block, which is the taper rule's shape applied to a shape that is not a hexahedron at all:
        // required for its own shape, refused on any other, and its tubes have to fit the box everything else
        // measures a truss by.
        yield 'truss without its tubes stated' => [
            ['geometry' => ['shape' => 'truss']],
            "geometry.truss is required for shape 'truss'",
        ];
        yield 'truss block on a plain box' => [
            ['geometry' => ['truss' => self::truss()]],
            "geometry.truss only applies to shape 'truss'",
        ];
        yield 'truss with an impossible chord count' => [
            ['geometry' => ['shape' => 'truss', 'truss' => self::truss(['chords' => 5])]],
            'geometry.truss.chords must be 2, 3 or 4',
        ];
        yield 'chord fatter than the cross-section it sits in' => [
            ['geometry' => ['shape' => 'truss', 'truss' => self::truss(['chord_diameter_m' => 0.9])]],
            'does not fit the',
        ];
        yield 'bracing thicker than the chords it braces' => [
            ['geometry' => ['shape' => 'truss', 'truss' => self::truss(['diagonal_diameter_m' => 0.06])]],
            'is thicker than the chords',
        ];
        // The other two open-frame shapes. Same rule as the truss block — required for its shape, refused on any
        // other — plus the one physical check each that stops geometry escaping the stated bounding box.
        yield 'moving head without its parts stated' => [
            ['geometry' => ['shape' => 'moving-head']],
            "geometry.moving_head is required for shape 'moving-head'",
        ];
        yield 'moving head block on a plain box' => [
            ['geometry' => ['moving_head' => self::movingHead()]],
            "geometry.moving_head only applies to shape 'moving-head'",
        ];
        yield 'a base with no room for a head above it' => [
            ['geometry' => ['shape' => 'moving-head', 'moving_head' => self::movingHead(['base_height_m' => 0.6])]],
            'leaves no room for a head',
        ];
        yield 'base plus head taller than the fixture' => [
            ['geometry' => ['shape' => 'moving-head', 'moving_head' => self::movingHead(['head_length_m' => 0.5])]],
            'is taller than geometry.dimensions_m.height',
        ];
        yield 'a head too wide for its yoke' => [
            ['geometry' => ['shape' => 'moving-head', 'moving_head' => self::movingHead(['head_diameter_m' => 0.9])]],
            'does not fit the',
        ];
        yield 'scaffold without its frame stated' => [
            ['geometry' => ['shape' => 'scaffold']],
            "geometry.scaffold is required for shape 'scaffold'",
        ];
        yield 'scaffold block on a plain box' => [
            ['geometry' => ['scaffold' => self::scaffold()]],
            "geometry.scaffold only applies to shape 'scaffold'",
        ];
        // The mistake this catches is reading a working height off a label: a tower sold as AH7 has its platform at
        // 5 m, and a spec that put 7 in the box would clear a truss it does not clear.
        yield 'a platform above its own frame' => [
            ['geometry' => ['shape' => 'scaffold', 'scaffold' => self::scaffold(['platform_height_m' => 0.9])]],
            'is above the frame',
        ];
        yield 'posts too fat to leave a span between them' => [
            ['geometry' => ['shape' => 'scaffold', 'scaffold' => self::scaffold(['post_diameter_m' => 0.3])]],
            'leaves no span between posts',
        ];
        yield 'bracing thicker than the posts it braces' => [
            ['geometry' => ['shape' => 'scaffold', 'scaffold' => self::scaffold(['brace_diameter_m' => 0.06])]],
            'is thicker than the posts',
        ];
        yield 'truss with no bay length' => [
            ['geometry' => ['shape' => 'truss', 'truss' => self::truss(['bay_length_m' => 0.0])]],
            'geometry.truss.bay_length_m must be greater than 0',
        ];
        yield 'mesh override Blender cannot read' => [
            ['mesh_override' => 'Achenbach 18.FCStd'],
            'Blender cannot read FreeCAD',
        ];
        yield 'baffle feature outside the baffle' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.38, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
            ])]],
            "'horn': reaches past the baffle",
        ];
        yield 'baffle feature deeper than the cabinet' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.9],
            ])]],
            'is deeper than the cabinet',
        ];
        yield 'horn without a throat' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'depth_m' => 0.1],
            ])]],
            'a horn needs throat_in',
        ];
        yield 'cone without a diameter' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'w', 'kind' => 'cone', 'at_m' => [0.0, 0.0], 'depth_m' => 0.1],
            ])]],
            'a cone needs diameter_in',
        ];
        yield 'unknown feature kind' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'thing', 'kind' => 'port', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.1, 0.1],
                    'depth_m' => 0.1],
            ])]],
            "unknown kind 'port'",
        ];
        yield 'nested feature naming one that comes later' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'plug', 'kind' => 'horn', 'inside' => 'horn', 'mouth_m' => [0.05, 0.05],
                    'throat_in' => 1.0, 'depth_m' => 0.03],
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
            ])]],
            '`inside: horn` must name an earlier feature',
        ];
        yield 'unknown mouth profile' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'profile' => 'conical'],
            ])]],
            "unknown profile 'conical'",
        ];
        yield 'unknown flare law' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'flare' => 'tractrix'],
            ])]],
            "unknown flare 'tractrix'",
        ];
        yield 'too few sides for a mouth' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'sides' => 2],
            ])]],
            'sides must be at least 3',
        ];
        yield 'unknown throat profile' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'throat_profile' => 'round'],
            ])]],
            "unknown throat_profile 'round'",
        ];
        yield 'throat profile on a driver cone' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'w', 'kind' => 'cone', 'at_m' => [0.0, 0.0], 'diameter_in' => 12,
                    'depth_m' => 0.1, 'throat_profile' => 'elliptical'],
            ])]],
            'throat_profile only applies to a horn',
        ];
        yield 'sides on an elliptical mouth' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'profile' => 'elliptical', 'sides' => 8],
            ])]],
            'sides has no meaning when neither the mouth nor the throat is a pyramid',
        ];
        yield 'sides on a driver cone' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'w', 'kind' => 'cone', 'at_m' => [0.0, 0.0], 'diameter_in' => 12,
                    'depth_m' => 0.1, 'sides' => 6],
            ])]],
            'sides only applies to a horn',
        ];
        yield 'join naming a feature that comes later' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.4, 0.2],
                    'throat_in' => 4.0, 'depth_m' => 0.2, 'join' => ['with' => 'lf-up', 'depth_m' => 0.04]],
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.1], 'mouth_m' => [0.4, 0.2],
                    'throat_in' => 4.0, 'depth_m' => 0.2],
            ])]],
            '`join.with: lf-up` must name an earlier feature',
        ];
        yield 'join naming itself' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.0], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'horn', 'depth_m' => 0.02]],
            ])]],
            'join.with names the feature itself',
        ];
        yield 'join with a driver cone' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'w', 'kind' => 'cone', 'at_m' => [0.0, 0.15], 'diameter_in' => 8, 'depth_m' => 0.1],
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'w', 'depth_m' => 0.02]],
            ])]],
            "join only works between horns, and 'w' is a cone",
        ];
        yield 'join on a driver cone' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
                ['id' => 'w', 'kind' => 'cone', 'at_m' => [0.0, -0.15], 'diameter_in' => 8, 'depth_m' => 0.1,
                    'join' => ['with' => 'horn', 'depth_m' => 0.02]],
            ])]],
            'join only applies to a horn',
        ];
        yield 'join to a horn nested inside another' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'horn', 'kind' => 'horn', 'at_m' => [0.0, 0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
                ['id' => 'plug', 'kind' => 'horn', 'inside' => 'horn', 'mouth_m' => [0.05, 0.05],
                    'throat_in' => 1.0, 'depth_m' => 0.03],
                ['id' => 'other', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'plug', 'depth_m' => 0.02]],
            ])]],
            "join needs both horns on the baffle, and 'plug' sits inside 'horn'",
        ];
        yield 'join with no depth to it' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'lf-up', 'depth_m' => 0.0]],
            ])]],
            'join.depth_m must be greater than 0',
        ];
        yield 'join reaching past a throat' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.08],
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'lf-up', 'depth_m' => 0.08]],
            ])]],
            'reaches the throat of the shallower horn',
        ];
        yield 'join between two overlapping mouths' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.05], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.05], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'lf-up', 'depth_m' => 0.02]],
            ])]],
            'there is nothing between them to remove',
        ];
        yield 'join between two mouths sitting diagonally' => [
            ['audio' => ['layout' => self::layout([
                ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [-0.25, 0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1],
                ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.25, -0.15], 'mouth_m' => [0.2, 0.2],
                    'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'lf-up', 'depth_m' => 0.02]],
            ])]],
            'lines up with \'lf-up\' on neither axis',
        ];
        yield 'join on a cabinet whose holes come from CAD' => [
            [
                'mesh_override' => 'meshes/sub.obj',
                'audio' => ['layout' => self::layout([
                    ['id' => 'lf-up', 'kind' => 'horn', 'at_m' => [0.0, 0.15], 'mouth_m' => [0.2, 0.2],
                        'throat_in' => 1.4, 'depth_m' => 0.1],
                    ['id' => 'lf-lo', 'kind' => 'horn', 'at_m' => [0.0, -0.15], 'mouth_m' => [0.2, 0.2],
                        'throat_in' => 1.4, 'depth_m' => 0.1, 'join' => ['with' => 'lf-up', 'depth_m' => 0.02]],
                ])],
            ],
            'join only applies to a generated cabinet',
        ];
        yield 'mesh override with unknown units' => [
            ['mesh_override' => ['path' => 'meshes/sub.obj', 'units' => 'inches']],
            "mesh_override.units 'inches' is unknown",
        ];
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return array<string, mixed>
     */
    private static function layout(array $features): array
    {
        return ['provenance' => 'estimated', 'inset_m' => 0.012, 'features' => $features];
    }

    /**
     * A valid moving-head block against the factory's 0.8 x 0.6 x 0.45 box, for cases that break one thing.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function movingHead(array $overrides = []): array
    {
        return [
            'base_height_m' => 0.200,
            'yoke_arm_thickness_m' => 0.050,
            'head_diameter_m' => 0.300,
            'head_length_m' => 0.300,
            ...$overrides,
        ];
    }

    /**
     * A valid scaffold block against the same box.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function scaffold(array $overrides = []): array
    {
        return [
            'post_diameter_m' => 0.050,
            'brace_diameter_m' => 0.025,
            'platform_height_m' => 0.600,
            'platform_thickness_m' => 0.050,
            ...$overrides,
        ];
    }

    /**
     * A valid truss block, for cases that break exactly one thing about it.
     *
     * The values are the 300 mm class standard the shipped segments use, so a case that overrides nothing is
     * genuinely valid against the factory's 0.8 x 0.6 x 0.45 box.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function truss(array $overrides = []): array
    {
        return [
            'chords' => 3,
            'chord_diameter_m' => 0.050,
            'diagonal_diameter_m' => 0.020,
            'bay_length_m' => 0.500,
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectionCases')]
    public function testRejects(array $overrides, string $expectedMessage): void
    {
        $messages = $this->validate(SpecFactory::spec($overrides));

        self::assertNotSame([], $messages, 'expected at least one violation');
        self::assertTrue(
            (bool) array_filter($messages, static fn (string $m): bool => str_contains($m, $expectedMessage)),
            sprintf("no violation contained %s\ngot: %s", var_export($expectedMessage, true), implode(' | ', $messages)),
        );
    }

    public function testAMissingOverrideMeshIsAWarningNotAnError(): void
    {
        // Override meshes are third-party CAD this repo does not commit, so a spec naming a file the
        // current checkout lacks must not make the whole library invalid for everyone else.
        $violations = $this->validator->validate([SpecFactory::spec(['mesh_override' => 'meshes/nope.glb'])]);

        self::assertCount(1, $violations);
        self::assertFalse($violations[0]->isError());
        self::assertSame([], Violation::errorsIn($violations));
        self::assertCount(1, Violation::warningsIn($violations));
        self::assertStringContainsString('is not in this checkout', $violations[0]->message);
    }

    public function testFactoryGearMayCiteItsOwnDatasheetWithoutNamingAClone(): void
    {
        // A bought cabinet's datasheet is its own — requiring a `clone_of` here would force a
        // fiction. Only a clone has to name the original its numbers were copied from.
        $spec = SpecFactory::spec([
            'build' => 'original',
            'clone_of' => null,
            'provenance' => 'datasheet',
        ]);

        self::assertSame([], $this->validate($spec));
    }

    public function testAnImpossibleDimensionDoesNotAlsoReportEveryRiggingPoint(): void
    {
        // The inverted bounding box would otherwise make every point look out of bounds, burying
        // the one error that actually needs fixing.
        $messages = $this->validate(SpecFactory::spec([
            'geometry' => ['dimensions_m' => ['width' => -0.8, 'height' => 0.6, 'depth' => 0.45]],
            'rigging' => [
                'flyable' => true,
                'points' => [['id' => 'top-left', 'position_m' => [-0.3, -0.2, 0.5]]],
            ],
        ]));

        self::assertCount(1, $messages, implode(' | ', $messages));
        self::assertStringContainsString('geometry.dimensions_m.width', $messages[0]);
    }

    public function testIdMustMatchTheFilename(): void
    {
        $spec = SpecFactory::spec([], '/project/specs/speakers/something-else.yaml');

        self::assertContains(
            "id 'top-a' does not match the filename 'something-else'",
            $this->validate($spec),
        );
    }

    public function testDuplicateIdsAreReportedOnBothFiles(): void
    {
        $specs = [
            SpecFactory::spec([], '/project/specs/speakers/top-a.yaml'),
            SpecFactory::spec([], '/project/specs/truss/top-a.yaml'),
        ];

        $violations = $this->validator->validate($specs);
        $messages = array_map(static fn ($violation): string => $violation->message, $violations);

        self::assertCount(2, $messages);
        self::assertStringContainsString("duplicate id 'top-a'", $messages[0]);
        self::assertStringContainsString('specs/truss/top-a.yaml', $messages[0]);
        self::assertStringContainsString('specs/speakers/top-a.yaml', $messages[1]);
    }

    public function testAnExistingMeshOverrideIsAccepted(): void
    {
        $dir = SpecFactory::tempDir();
        try {
            file_put_contents($dir.'/custom.glb', 'glb');
            $validator = new SpecValidator($dir);
            $spec = SpecFactory::spec(['mesh_override' => 'custom.glb'], $dir.'/top-a.yaml');

            self::assertSame([], array_map(
                static fn ($violation): string => $violation->message,
                $validator->validate([$spec]),
            ));
        } finally {
            SpecFactory::removeDir($dir);
        }
    }

    /**
     * @return list<string>
     */
    private function validate(DeviceSpec $spec): array
    {
        return array_map(
            static fn ($violation): string => $violation->message,
            $this->validator->validate([$spec]),
        );
    }
}
