<?php

declare(strict_types=1);

namespace App\Tests\Load;

use App\Load\LoadPlan;
use App\Load\LoadReport;
use App\Spec\DeviceSpec;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * The two verdicts as sentences, on plans built to order.
 *
 * **Built to order on purpose.** These rules are about what the report says when something is wrong, and as of
 * Sepp's Zulassungsschein arriving the real fleet is not wrong about anything: it carries its load in one trip with
 * both bays comfortable. Testing an overflow against the real specs would mean waiting for the fleet to get worse,
 * or contorting an invocation until it misbehaves — and a rule that can only be demonstrated by accident is a rule
 * nobody can rely on.
 */
final class LoadReportTest extends TestCase
{
    /**
     * **A bay already exceeded by bounding boxes alone is said out loud, in capitals.**.
     *
     * It is the one space answer that is safe to give. A bounding-box sum is a lower bound on the room needed, so
     * over the bay is real evidence the load will not go in, where under it is never a permission. A reader
     * skimming must not be able to mistake it for a rounding note.
     */
    public function testABayAlreadyExceededIsSaidOutLoud(): void
    {
        $lines = implode("\n", (new LoadReport())->lines([
            new LoadPlan(self::van(), [['spec' => self::cargo(), 'count' => 4]], payloadKg: 5000.0, bayM3: 1.0),
        ], []));

        self::assertStringContainsString('WHICH ALREADY EXCEEDS IT', $lines);
    }

    /**
     * A bay with room to spare gets a percentage and **no verdict**, because the sum cannot say a load fits.
     */
    public function testABayWithRoomGetsAPercentageAndNoVerdict(): void
    {
        $lines = implode("\n", (new LoadReport())->lines([
            new LoadPlan(self::van(), [['spec' => self::cargo(), 'count' => 1]], payloadKg: 5000.0, bayM3: 20.0),
        ], []));

        self::assertStringContainsString('m³ bay', $lines);
        self::assertStringNotContainsString('EXCEEDS', $lines);
    }

    /**
     * An unmeasured bay gets no space answer at all. Reporting it as "does not exceed" would read as
     * checked-and-fine, where the truth is that nobody has been inside the van with a tape measure.
     */
    public function testAnUnmeasuredBaySaysSoRatherThanPassing(): void
    {
        $lines = implode("\n", (new LoadReport())->lines([
            new LoadPlan(self::van(), [['spec' => self::cargo(), 'count' => 1]], payloadKg: 5000.0, bayM3: null),
        ], []));

        self::assertStringContainsString('bay not measured', $lines);
    }

    /**
     * **A margin inside the error of its own inputs is undecided rather than a pass**, and the report says which.
     * Half a kilogramme of headroom off two estimated masses is not a decision, and printing it as one would be
     * the most confident sentence in the file resting on the least evidence.
     */
    public function testAMarginInsideTheErrorBarIsUndecided(): void
    {
        $lines = implode("\n", (new LoadReport())->lines([
            // 100 kg carried against a 101 kg limit: 1 kg of margin is under 1 % and well inside the error.
            new LoadPlan(self::van(), [['spec' => self::cargo(), 'count' => 1]], payloadKg: 101.0, bayM3: 20.0),
        ], []));

        self::assertStringContainsString('UNDECIDED', $lines);
        self::assertStringContainsString('neither a pass nor a refusal', $lines);
    }

    /**
     * What was left behind is named and weighed, never summarised away.
     */
    public function testLeftoversAreNamedAndWeighed(): void
    {
        $lines = implode("\n", (new LoadReport())->lines(
            [new LoadPlan(self::van(), [], payloadKg: 1.0, bayM3: 20.0)],
            [['spec' => self::cargo(), 'count' => 3]],
        ));

        self::assertStringContainsString('NOT CARRIED', $lines);
        self::assertStringContainsString('short by 300.0 kg', $lines);
    }

    private static function van(): DeviceSpec
    {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => 'test-van',
            'name' => 'Test van',
            'category' => 'vehicle',
            'subtype' => 'van',
            'quantity' => 1,
            'build' => 'original',
            'clone_of' => null,
            'provenance' => 'estimated',
            'audio' => null,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 2.2, 'height' => 2.9, 'depth' => 6.9],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => 2000.0, 'handles' => []],
            'vehicle' => ['permitted_gross_kg' => 3500.0],
        ]), '/tmp/test-van.yaml');
    }

    private static function cargo(): DeviceSpec
    {
        return DeviceSpec::fromArray(SpecFactory::specArray([
            'id' => 'crate',
            'name' => 'Crate',
            'quantity' => 4,
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 1.0, 'height' => 1.0, 'depth' => 1.0],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.02,
            ],
            'physical' => ['weight_kg' => 100.0, 'handles' => []],
        ]), '/tmp/crate.yaml');
    }
}
