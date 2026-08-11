<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;
use App\Spec\Passband;
use App\Spec\Provenance;
use App\Spec\SpecLoader;
use App\Spec\SpecValidator;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * `audio.passband_hz` — the band a cabinet covers and the band it is driven over.
 *
 * The distinction between the two is the whole reason this exists, so most of these tests are about it.
 */
final class PassbandTest extends TestCase
{
    public function testTheDrivenCornerIsWhatAStackIsOrderedOn(): void
    {
        $band = new Passband(35.0, 1500.0, Provenance::Estimated, 38.0);

        self::assertSame(35.0, $band->lowHz, 'what the cabinet reaches');
        self::assertSame(38.0, $band->orderingLowHz(), 'where it is actually high-passed');
    }

    /** Without a driven corner there is nothing to distinguish, so the low corner orders it. */
    public function testWithoutADrivenCornerTheLowCornerOrdersIt(): void
    {
        self::assertSame(15.0, (new Passband(15.0, 120.0, Provenance::Estimated))->orderingLowHz());
    }

    /**
     * The real gear list, and the ordering the owner asked for: SKRAM deepest, then Flexy, then Achenbach.
     *
     * The Achenbach is the point. It reaches 35 Hz — lower than the Flexy's 38 — so on capability alone it
     * would sort *below* the Flexys and four of them would end up carrying twelve. Driven from 38 it ties with
     * the Flexy, and the high corner breaks the tie the right way round: 200 Hz stops sooner than 1500, so the
     * Flexy is the more sub-like of the two and stays on the floor.
     */
    public function testTheRealSpecsOrderSkramThenFlexyThenAchenbach(): void
    {
        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            $devices[$spec->id] = $spec;
        }

        $subs = ['skram', 'flexy-folded-horn-hybrid', 'achenbach-18'];
        foreach ($subs as $id) {
            self::assertNotNull($devices[$id]->passband, $id.' has no passband');
        }

        $ordering = array_map(static fn (string $id): float => $devices[$id]->passband->orderingLowHz(), $subs);
        self::assertSame([15.0, 38.0, 38.0], $ordering);

        // The tie, broken on the high corner.
        self::assertLessThan(
            $devices['achenbach-18']->passband->highHz,
            $devices['flexy-folded-horn-hybrid']->passband->highHz,
        );
    }

    public function testReadingRejectsAnUnknownKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("audio.passband_hz: unknown key 'lo_hz'");

        Passband::fromReader(new ArrayReader(['lo_hz' => 38, 'high_hz' => 200, 'provenance' => 'estimated']));
    }

    /**
     * Provenance is required for the same reason a baffle layout's is: a frequency is trivial to invent,
     * impossible to check in a render, and it silently decides the order every generated rig comes out in.
     */
    public function testProvenanceIsRequired(): void
    {
        $this->expectException(InvalidSpecException::class);

        Passband::fromReader(new ArrayReader(['low_hz' => 38, 'high_hz' => 200]));
    }

    /**
     * @param array<string, mixed> $band
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejections')]
    public function testRejects(array $band, string $expected): void
    {
        $spec = SpecFactory::spec(['audio' => ['passband_hz' => $band]]);

        $messages = array_map(
            static fn ($v): string => $v->message,
            (new SpecValidator('/project'))->validate([$spec]),
        );

        self::assertStringContainsString($expected, implode("\n", $messages));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejections(): iterable
    {
        $base = ['low_hz' => 38, 'high_hz' => 200, 'provenance' => 'estimated'];

        yield 'a high corner below the low one' => [
            ['low_hz' => 200, 'high_hz' => 38, 'provenance' => 'estimated'],
            'must be above low_hz',
        ];
        yield 'a low corner of zero' => [
            ['low_hz' => 0, 'high_hz' => 200, 'provenance' => 'estimated'],
            'low_hz must be greater than 0',
        ];
        yield 'driven below what the cabinet reaches' => [
            ['low_hz' => 38, 'high_hz' => 200, 'driven_from_hz' => 30, 'provenance' => 'estimated'],
            'cannot be driven lower than it reaches',
        ];
        yield 'driven above the whole band' => [
            $base + ['driven_from_hz' => 250],
            'leaves no band',
        ];
    }
}
