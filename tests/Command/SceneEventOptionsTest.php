<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneEventOptions;
use App\Command\SceneStackCommand;
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
        ];
        $input = new ArrayInput(['--event' => 'next-event'], (new SceneStackCommand())->getDefinition());
        $options = SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', $devices);

        self::assertSame('innschleife', $options->subOwner(['inn-sub', 'psl-top'], $devices));
        self::assertSame('sdwa5', $options->subOwner(['our-sub', 'psl-top'], $devices));
        self::assertNull($options->subOwner(['inn-sub', 'our-sub'], $devices));
        self::assertSame(['innschleife' => 1.6], $options->interfaces);
    }

    public function testAnUnknownSystemPreferenceIsRefused(): void
    {
        $input = new ArrayInput(['--system-target' => ['typo:1.75']], (new SceneStackCommand())->getDefinition());
        $this->expectException(InvalidSpecException::class);
        SceneEventOptions::resolve($input, dirname(__DIR__, 2).'/events', ['sub' => SpecFactory::spec()]);
    }
}
