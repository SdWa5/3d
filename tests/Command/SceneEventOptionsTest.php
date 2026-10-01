<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneEventOptions;
use App\Command\SceneStackCommand;
use App\Scene\LowEndBias;
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

        self::assertSame(1.6, $options->interfaceFor(['inn-sub', 'psl-top'], $devices));
        self::assertSame(1.75, $options->targetFor(['inn-sub', 'psl-top'], $devices));
        self::assertNull($options->interfaceFor(['our-sub', 'psl-top'], $devices));
        self::assertNull($options->interfaceFor(['inn-sub', 'our-sub'], $devices));
        self::assertSame(['innschleife' => 1.6], $options->interfaces);
    }

    /**
     * Ours is a wall of two owners' subs. When both state the same interface the wall takes it, and when they differ
     * or one is silent it keeps the default.
     */
    public function testAPooledWallFollowsItsSubOwnersWhenTheyAgree(): void
    {
        $devices = [
            'our-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sdwa5']),
            'sepp-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sepp']),
            'psl-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'psl']),
            'psl-top' => SpecFactory::spec(['owner' => 'psl']),
            'kicker-15' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'innschleife']),
        ];
        $definition = (new SceneStackCommand())->getDefinition();
        $agreed = SceneEventOptions::resolve(new ArrayInput(['--event' => 'next-event-light-achenbach'], $definition), dirname(__DIR__, 2).'/events', $devices);

        self::assertSame(1.6, $agreed->interfaceFor(['our-sub', 'sepp-sub', 'psl-top'], $devices));
        self::assertSame(1.75, $agreed->targetFor(['our-sub', 'sepp-sub'], $devices));
        self::assertNull($agreed->interfaceFor(['our-sub', 'psl-sub'], $devices));

        $differ = SceneEventOptions::resolve(new ArrayInput([
            '--event' => 'next-event-light-achenbach',
            '--system-interface' => ['sepp:1.7'],
        ], $definition), dirname(__DIR__, 2).'/events', $devices);

        self::assertNull($differ->interfaceFor(['our-sub', 'sepp-sub'], $devices));
        self::assertSame(1.75, $differ->targetFor(['our-sub', 'sepp-sub'], $devices));
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

    /**
     * **A stack follows the low end its subs' systems agree on**, so ours follows sdwa5 and Sepp together, a borrowed
     * top changes nothing, and a pooled wall of two systems that disagree keeps the swept value.
     */
    public function testAStackFollowsTheLowEndItsSubsSystemsAgreeOn(): void
    {
        $devices = [
            'our-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sdwa5']),
            'sepp-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sepp']),
            'inn-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'innschleife']),
            'psl-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'psl']),
            'psl-top' => SpecFactory::spec(['owner' => 'psl']),
        ];
        $input = new ArrayInput(['--system-low-end' => ['sepp:central', 'sdwa5:central', 'innschleife:low']], (new SceneStackCommand())->getDefinition());
        $options = SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', $devices);

        self::assertSame(LowEndBias::Central, $options->lowEndFor(['our-sub', 'sepp-sub', 'psl-top'], $devices));
        self::assertSame(LowEndBias::Low, $options->lowEndFor(['inn-sub'], $devices));
        self::assertNull($options->lowEndFor(['our-sub', 'inn-sub'], $devices));
        self::assertNull($options->lowEndFor(['psl-sub'], $devices));
        self::assertNull($options->lowEndFor(['psl-top'], $devices));
        self::assertSame(['innschleife:low', 'sdwa5:central', 'sepp:central'], $input->getOption('system-low-end'));
    }

    /**
     * The sweep's low-end axis is left with nothing to vary only when every system in the rig states one value, because
     * any of them could end up in one pooled wall.
     */
    public function testOnlyARigWhoseSystemsAllAgreeFixesTheLowEnd(): void
    {
        $devices = [
            'our-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sdwa5']),
            'sepp-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'sepp']),
            'inn-sub' => SpecFactory::spec(['subtype' => 'sub', 'owner' => 'innschleife']),
            'psl-top' => SpecFactory::spec(['owner' => 'psl']),
        ];
        $input = new ArrayInput(['--system-low-end' => ['sdwa5:central', 'sepp:central', 'innschleife:low']], (new SceneStackCommand())->getDefinition());
        $options = SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', $devices);

        self::assertTrue($options->fixesLowEnd($devices, ['our-sub', 'sepp-sub']));
        self::assertTrue($options->fixesLowEnd($devices, ['inn-sub']));
        self::assertFalse($options->fixesLowEnd($devices, ['our-sub', 'inn-sub']));
        self::assertFalse($options->fixesLowEnd($devices, ['inn-sub', 'psl-top']));
    }

    public function testAMalformedSystemLowEndIsRefused(): void
    {
        $input = new ArrayInput(['--system-low-end' => ['psl:middle']], (new SceneStackCommand())->getDefinition());
        $this->expectException(InvalidSpecException::class);
        SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', ['sub' => SpecFactory::spec()]);
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
