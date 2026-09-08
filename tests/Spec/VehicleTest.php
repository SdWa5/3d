<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\Category;
use App\Spec\DeviceSpec;
use App\Spec\Shape;
use App\Spec\SpecLoader;
use App\Spec\SpecValidator;
use App\Spec\Vehicle;
use App\Spec\Violation;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The `vehicle` category: a device measured on the inside as well as the outside, and never placed in a scene.
 *
 * The rules worth pinning are the ones with consequences outside a render. A payload is a legal limit, and it is
 * **derived** from two document fields rather than stored, so the arithmetic and the refusal that guards it both
 * belong in a test.
 */
final class VehicleTest extends TestCase
{
    /**
     * **Payload is `F.2 − G` and the driver is already inside G**, which is the whole reason it is computed rather
     * than written down: both halves cite a numbered field on a Zulassungsbescheinigung and their difference cites
     * nothing, so a stored payload would be the one number in the file pointing at no source.
     */
    public function testThePayloadIsTheDifferenceBetweenTheTwoDocumentedMasses(): void
    {
        $vehicle = new Vehicle(permittedGrossKg: 3500.0);

        self::assertEqualsWithDelta(1024.0, $vehicle->payloadKg(2476.0), 1e-9);
    }

    /**
     * **A permitted gross mass at or below the mass in service is a transcribed digit, never a real vehicle**, and
     * it is an error rather than a warning because both directions of the slip are dangerous. Read one way it makes
     * a packer refuse every load; read the other, with the two fields swapped, it authorises an overloaded one.
     */
    public function testAVehicleThatMayLegallyCarryNothingIsRefused(): void
    {
        $messages = $this->validate([
            'physical' => ['weight_kg' => 3500.0],
            'vehicle' => ['permitted_gross_kg' => 3500.0],
        ]);

        self::assertNotSame([], $messages);
        self::assertStringContainsString('permitted_gross_kg', $messages[0]);
        self::assertStringContainsString('payload works out at 0 kg', $messages[0]);
    }

    /**
     * The bay is the inside, so it cannot be bigger than the outside. This is the same check a truss chord gets and
     * for the same reason: `geometry.dimensions_m` is what the rest of the repository measures the device by.
     *
     * It is not a hypothetical slip. The front-wheel-drive H3 bay is 2144 mm and the rear-wheel-drive one 2048 mm,
     * and the L4 body only comes rear-wheel drive — so copying the wrong catalogue row is exactly how a bay ends up
     * claiming height the vehicle does not have.
     */
    public function testALoadBayBiggerThanTheVehicleIsRefused(): void
    {
        $messages = $this->validate([
            // Overriding `geometry` replaces it whole, so the cage shape has to be restated or the validator's
            // other rule fires first and this test reads the wrong message.
            'geometry' => [
                'shape' => 'load-bay',
                'dimensions_m' => ['width' => 2.07, 'height' => 2.808, 'depth' => 6.848],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'vehicle' => [
                'permitted_gross_kg' => 3500.0,
                'load_bay_m' => ['width' => 1.765, 'height' => 3.5, 'depth' => 4.383],
            ],
        ]);

        self::assertNotSame([], $messages);
        self::assertStringContainsString('load_bay_m.height', $messages[0]);
        self::assertStringContainsString('bigger than the vehicle', $messages[0]);
    }

    /**
     * **The wheel arches narrow the floor and never widen it.** 1380 mm against a 1765 mm bay on our own Movano, so
     * the two differ by 385 mm and a packer reading the wrong one promises floor space that is not there.
     */
    public function testTheWidthBetweenTheArchesCannotExceedTheBay(): void
    {
        $messages = $this->validate([
            'vehicle' => [
                'permitted_gross_kg' => 3500.0,
                'load_bay_m' => ['width' => 1.765, 'height' => 2.048, 'depth' => 4.383, 'width_between_arches' => 1.9],
            ],
        ]);

        self::assertNotSame([], $messages);
        self::assertStringContainsString('width_between_arches', $messages[0]);
    }

    /**
     * **A bay is optional and its absence is not an error**, which is the distinction that lets a van be specified
     * from its papers before anybody has been inside it. No registration document states a load bay, so requiring
     * one would mean either inventing it or leaving the masses unrecorded — and the masses are the half with legal
     * consequences.
     */
    public function testAVehicleWithNoMeasuredBayIsStillAValidSpec(): void
    {
        self::assertSame([], $this->validate(['vehicle' => ['permitted_gross_kg' => 3500.0]]));
    }

    /**
     * The block and the category imply each other, in both directions. A `vehicle` with no block has no payload,
     * which is the only reason it is in the library; a cabinet with one is a copy-paste.
     */
    public function testTheBlockAndTheCategoryRequireEachOther(): void
    {
        $missing = $this->validate(['vehicle' => null]);
        self::assertNotSame([], $missing);
        self::assertStringContainsString('needs a `vehicle` block', $missing[0]);

        $misplaced = $this->validateSpeaker(['vehicle' => ['permitted_gross_kg' => 3500.0]]);
        self::assertNotSame([], $misplaced);
        self::assertStringContainsString("belongs to category 'vehicle'", $misplaced[0]);
    }

    /**
     * **A vehicle is drawn as a cage, not skipped and not solid**, and this test asserted the opposite for an
     * afternoon.
     *
     * The category arrived as the one thing never turned into geometry, on the argument that a 6.8 m solid van
     * would be the largest object in any picture that included it. Right about the *solid*, wrong about the
     * *model*: stated by the owner, the vans need wire-type models so a pack can be planned. So the shape is
     * `load-bay` — the vehicle's outline with its load bay caged inside it — and `Category::producesAModel()` is
     * gone, because an abstraction whose only case was wrong is worse than no abstraction.
     */
    public function testAVehicleIsDrawnAsACageOfItsLoadBay(): void
    {
        $movano = $this->realSpecs()['opel-movano-l4h3'] ?? null;
        self::assertNotNull($movano);
        self::assertSame(Shape::LoadBay, $movano->shape);
        self::assertFalse($movano->shape->isCabinet(), 'a van has no grille, handles or chamfer');
        self::assertNotNull($movano->vehicle?->loadBayPlan(), 'the cage needs a bay to draw');
    }

    /**
     * **A vehicle drawn as a solid is refused**, which is the thing the cage exists to avoid.
     *
     * Note what is *not* refused: a cage with no bay. Requiring both would have made the shape and the bay imply
     * each other and killed the reason the bay is optional — a van can be specified from its papers before anybody
     * has been inside it. A bayless vehicle draws its outline alone, which is the honest picture.
     */
    public function testAVehicleDrawnAsASolidIsRefused(): void
    {
        $messages = $this->validate([
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 2.07, 'height' => 2.808, 'depth' => 6.848],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'vehicle' => [
                'permitted_gross_kg' => 3500.0,
                'load_bay_m' => ['width' => 1.765, 'height' => 2.048, 'depth' => 4.383],
            ],
        ]);

        self::assertNotSame([], $messages);
        self::assertStringContainsString('drawn as a cage', $messages[0]);
    }

    /**
     * **Our own two transporters, read from `specs/` rather than from a fixture**, because the figures are the point
     * of the entry and a fixture would only prove the parser works. The Movano's are off its
     * Zulassungsbescheinigung; Sepp's are estimated and say so.
     */
    public function testTheTwoRealTransportersCarryTheFiguresTheirPapersState(): void
    {
        $specs = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $specs[$spec->id] = $spec;
        }

        $movano = $specs['opel-movano-l4h3'] ?? null;
        self::assertNotNull($movano, 'the Movano spec is missing');
        self::assertSame(Category::Vehicle, $movano->category);
        self::assertNotNull($movano->vehicle);

        // Fields G and F.2, and the 1024 kg that falls out of them.
        self::assertEqualsWithDelta(2476.0, $movano->weightKg, 1e-9);
        self::assertEqualsWithDelta(3500.0, $movano->vehicle->permittedGrossKg, 1e-9);
        self::assertEqualsWithDelta(1024.0, $movano->vehicle->payloadKg($movano->weightKg), 1e-9);

        // Field 18 is the length and the bay lies along it, so the vehicle's depth is the larger of the two.
        self::assertEqualsWithDelta(6.848, $movano->dimensions->depth, 1e-9);
        self::assertNotNull($movano->vehicle->loadBay);
        self::assertEqualsWithDelta(4.383, $movano->vehicle->loadBay->depth, 1e-9);

        // The rear-wheel-drive roof. 2.144 here would be the front-wheel-drive body, which the L4 is never sold as.
        self::assertEqualsWithDelta(2.048, $movano->vehicle->loadBay->height, 1e-9);

        // **Sepp's Fiat Ducato, weighed rather than read, and it is 365 kg heavier than its own papers.** The
        // Austrian Zulassungsschein gives Eigengewicht 2060 kg and field A10 a payload of 1365; the weighbridge
        // says 2500 kg with a full tank and a driver, which is already the mass-in-service definition the Movano's
        // field G uses. The difference is fuel plus a fit-out added after type approval, so no registration field
        // has ever seen it.
        //
        // **The permitted gross still comes off the paper**, because a legal ceiling is not something a scale can
        // tell you. That split — limit from the document, mass from the scale — is the whole point.
        $sepp = $specs['fiat-ducato-250-l3h2'] ?? null;
        self::assertNotNull($sepp, "Sepp's transporter spec is missing");
        self::assertSame('sepp', $sepp->owner);
        self::assertNotNull($sepp->vehicle);
        self::assertEqualsWithDelta(2500.0, $sepp->weightKg, 1e-9);
        self::assertEqualsWithDelta(3500.0, $sepp->vehicle->permittedGrossKg, 1e-9);
        self::assertEqualsWithDelta(1000.0, $sepp->vehicle->payloadKg($sepp->weightKg), 1e-9);
        self::assertTrue(
            $sepp->provenance->weight->isMeasured(),
            'the only weight in this library that has been on a scale must say so',
        );
        // The papers carry no dimensions at all, so the body is still a catalogue figure.
        self::assertFalse($sepp->provenance->dimensions->isMeasured());
    }

    /**
     * Every spec in `specs/`, keyed by id.
     *
     * @return array<string, DeviceSpec>
     */
    private function realSpecs(): array
    {
        $specs = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $specs[$spec->id] = $spec;
        }

        return $specs;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return list<string>
     */
    private function validate(array $overrides): array
    {
        return $this->messagesFor(SpecFactory::specArray(array_replace([
            'id' => 'zz-test-van',
            'name' => 'Test van',
            'category' => 'vehicle',
            'subtype' => 'van',
            'quantity' => 1,
            'build' => 'original',
            'clone_of' => null,
            'provenance' => 'estimated',
            'audio' => null,
            'geometry' => [
                // A vehicle is drawn as a cage, which the validator insists on, so the fixture has to be one too.
                'shape' => 'load-bay',
                'dimensions_m' => ['width' => 2.07, 'height' => 2.808, 'depth' => 6.848],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => 2476.0, 'handles' => []],
            'vehicle' => ['permitted_gross_kg' => 3500.0],
        ], $overrides)));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return list<string>
     */
    private function validateSpeaker(array $overrides): array
    {
        return $this->messagesFor(array_replace(SpecFactory::specArray(), $overrides));
    }

    /**
     * Only the messages this entry is about, so an unrelated rule firing on a fixture cannot make a case here look
     * like it passed or failed.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function messagesFor(array $data): array
    {
        $spec = DeviceSpec::fromArray($data, '/tmp/zz-test-van.yaml');
        $violations = (new SpecValidator(dirname(__DIR__, 2)))->validate([$spec]);

        return array_values(array_filter(
            array_map(static fn (Violation $v): string => $v->message, Violation::errorsIn($violations)),
            static fn (string $message): bool => str_contains($message, 'vehicle'),
        ));
    }
}
