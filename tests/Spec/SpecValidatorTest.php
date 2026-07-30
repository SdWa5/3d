<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\DeviceSpec;
use App\Spec\SpecValidator;
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
        yield 'clone without an original' => [
            ['build' => 'clone', 'clone_of' => null],
            'build is \'clone\' but clone_of is missing',
        ];
        yield 'own design that still names an original' => [
            ['build' => 'own-design', 'provenance' => 'measured'],
            "clone_of is set but build is 'own-design'",
        ];
        yield 'datasheet provenance without an original' => [
            ['build' => 'own-design', 'clone_of' => null, 'provenance' => 'datasheet'],
            'no clone_of names where those numbers came from',
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
        yield 'missing mesh override file' => [
            ['mesh_override' => 'meshes/nope.glb'],
            "mesh_override 'meshes/nope.glb' does not exist",
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
            (bool)array_filter($messages, static fn (string $m): bool => str_contains($m, $expectedMessage)),
            sprintf("no violation contained %s\ngot: %s", var_export($expectedMessage, true), implode(' | ', $messages)),
        );
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
