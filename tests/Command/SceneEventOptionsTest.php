<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneEventOptions;
use App\Command\SceneStackCommand;
use App\Scene\StackOrientation;
use App\Spec\InvalidSpecException;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class SceneEventOptionsTest extends TestCase
{
    public function testBorrowedTopsDoNotChangeTheSubWallsOwner(): void
    {
        $devices = [
            'inn-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'innschleife']),
            'psl-top' => SpecFactory::spec(['owner' => 'psl']),
            'our-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sdwa5']),
            'sepp-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sepp']),
            'kicker-15' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'innschleife']),
        ];
        $input = new ArrayInput(['--event' => 'next-event'], (new SceneStackCommand())->getDefinition());
        $options = SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', $devices);

        self::assertSame('innschleife', $options->subOwner(['inn-sub', 'psl-top'], $devices));
        self::assertSame('sdwa5', $options->subOwner(['our-sub', 'psl-top'], $devices));
        self::assertNull($options->subOwner(['inn-sub', 'our-sub'], $devices));
        self::assertSame(['innschleife' => 1.6], $options->interfaces);
    }

    /**
     * Each cabinet follows its own system's stated orientation, the rest follow the sweep, and a standing cabinet
     * stays as measured under any mode.
     */
    public function testEachSystemRollsByItsOwnOrientation(): void
    {
        $devices = [
            'our-sub' => SpecFactory::spec(['id' => 'our-sub', 'subtype' => 'sub', 'owner' => 'sdwa5', 'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 1.2, 'depth' => 0.8]]]),
            'inn-sub' => SpecFactory::spec(['id' => 'inn-sub', 'subtype' => 'sub', 'owner' => 'innschleife', 'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 1.2, 'depth' => 0.8]]]),
            'inn-kick' => SpecFactory::spec(['id' => 'inn-kick', 'subtype' => 'sub', 'owner' => 'innschleife', 'geometry' => ['dimensions_m' => ['width' => 0.95, 'height' => 0.57, 'depth' => 0.6]]]),
            'gmss-sub' => SpecFactory::spec(['id' => 'gmss-sub', 'subtype' => 'sub', 'owner' => 'gmss', 'geometry' => ['dimensions_m' => ['width' => 0.6, 'height' => 1.2, 'depth' => 0.8]]]),
        ];
        $input = new ArrayInput([
            '--system-orientation' => ['sdwa5:upright', 'innschleife:turned'],
            '--stand' => ['inn-kick'],
        ], (new SceneStackCommand())->getDefinition());
        $options = SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', $devices);
        $ids = array_keys($devices);

        self::assertSame(['inn-sub', 'gmss-sub'], $options->rolls(StackOrientation::Turned, $devices, $ids, []));
        self::assertSame(['inn-sub'], $options->rolls(StackOrientation::Upright, $devices, $ids, []));
        self::assertSame(['inn-sub', 'gmss-sub'], $options->rolls(null, $devices, $ids, ['gmss-sub', 'our-sub']));
        self::assertFalse($options->fixesOrientation($devices, $ids));
        self::assertTrue($options->fixesOrientation($devices, ['our-sub', 'inn-kick']));
        self::assertSame(['innschleife:turned', 'sdwa5:upright'], $input->getOption('system-orientation'));
    }

    public function testAMalformedSystemOrientationIsRefused(): void
    {
        $input = new ArrayInput(['--system-orientation' => ['psl:sideways']], (new SceneStackCommand())->getDefinition());
        $this->expectException(InvalidSpecException::class);
        SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', ['sub' => SpecFactory::spec()]);
    }

    public function testAnUnknownSystemPreferenceIsRefused(): void
    {
        $input = new ArrayInput(['--system-target' => ['typo:1.75']], (new SceneStackCommand())->getDefinition());
        $this->expectException(InvalidSpecException::class);
        SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', ['sub' => SpecFactory::spec()]);
    }
}
