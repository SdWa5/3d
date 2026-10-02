<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;
use App\Spec\Power;
use App\Spec\Provenance;
use App\Spec\SpecValidator;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * `audio.power_w` — the continuous power a cabinet takes, which the low-end axis weighs per square metre of front.
 */
final class PowerTest extends TestCase
{
    public function testASpecReadsItsContinuousPower(): void
    {
        $spec = SpecFactory::spec(['audio' => ['power_w' => ['rms' => 1800, 'provenance' => 'datasheet']]]);

        self::assertNotNull($spec->power);
        self::assertSame(1800.0, $spec->power->rmsW);
        self::assertSame(Provenance::Datasheet, $spec->power->provenance);
        self::assertSame(1800.0, $spec->withQuantity(5)->power?->rmsW, 'a copy for one event keeps it');
    }

    public function testASpecWithoutOneHasNone(): void
    {
        self::assertNull(SpecFactory::spec()->power);
    }

    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("audio.power_w: unknown key 'peak'");

        Power::fromReader(new ArrayReader(['rms' => 1800, 'peak' => 7200, 'provenance' => 'datasheet']));
    }

    /** A wattage is trivial to invent and silently decides which cabinet the low-end axis is about. */
    public function testProvenanceIsRequired(): void
    {
        $this->expectException(InvalidSpecException::class);

        Power::fromReader(new ArrayReader(['rms' => 1800]));
    }

    public function testTheValidatorRejectsAPowerOfZero(): void
    {
        $spec = SpecFactory::spec(['audio' => ['power_w' => ['rms' => 0, 'provenance' => 'estimated']]]);

        $messages = array_map(
            static fn ($v): string => $v->message,
            (new SpecValidator('/project'))->validate([$spec]),
        );

        self::assertStringContainsString('audio.power_w.rms must be greater than 0', implode("\n", $messages));
    }
}
