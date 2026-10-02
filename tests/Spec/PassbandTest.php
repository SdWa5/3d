<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\ArrayReader;
use App\Spec\FillOrder;
use App\Spec\InvalidSpecException;
use App\Spec\Passband;
use App\Spec\SpecLoader;
use App\Spec\SpecValidator;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

/**
 * `audio.passband_hz` — the band a cabinet covers.
 */
final class PassbandTest extends TestCase
{
    /**
     * The real gear list in fill order, every sub with a passband and a power figure: SKRAM deepest, then the ESX on
     * the most power per area, then the Flexy.
     *
     * The Achenbach is the pair that used to need `driven_from_hz`. It reaches 35 Hz, lower than the Flexy's 38, and
     * still sorts above the Flexys because it has 2778 W/m² against their 3991, so four of them do not end up
     * carrying twelve.
     */
    public function testTheRealSpecsAreDealtInLowOctaveOrder(): void
    {
        $subs = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            if ('sub' === $spec->subtype && null !== $spec->passband) {
                $subs[] = $spec;
            }
        }
        usort($subs, FillOrder::byFillOrder());

        self::assertSame([
            'skram',
            'concert-audio-esx',
            'flexy-folded-horn-hybrid',
            'concert-audio-esf',
            'achenbach-18',
            'thebox-tp218-1600',
            'thebox-tp118-800',
        ], array_map(static fn ($s): string => $s->id, $subs));
    }

    /** The driven corner is gone, so a spec still carrying one is refused rather than quietly ignored. */
    public function testTheDrivenCornerIsNoLongerAKey(): void
    {
        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessage("audio.passband_hz: unknown key 'driven_from_hz' (allowed: low_hz, high_hz, provenance)");

        Passband::fromReader(new ArrayReader(['low_hz' => 35, 'high_hz' => 1500, 'driven_from_hz' => 38, 'provenance' => 'estimated']));
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
        yield 'a high corner below the low one' => [
            ['low_hz' => 200, 'high_hz' => 38, 'provenance' => 'estimated'],
            'must be above low_hz',
        ];
        yield 'a low corner of zero' => [
            ['low_hz' => 0, 'high_hz' => 200, 'provenance' => 'estimated'],
            'low_hz must be greater than 0',
        ];
    }
}
