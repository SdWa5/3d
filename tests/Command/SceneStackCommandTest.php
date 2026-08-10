<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;
use App\Scene\SceneCompiler;
use App\Scene\SceneLoader;
use App\Spec\DeviceSpec;
use App\Spec\SpecLoader;
use App\Spec\Violation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `scene:stack` — solving a rig from constraints and writing it out.
 *
 * Everything here runs `--dry-run` except the tests that are *about* writing, and those write into the real
 * `scenes/` directory under a throwaway `--id` and are cleaned up in `tearDown()`. That is the same
 * bargain `SpecValidatorTest` strikes with `SpecFactory::tempDir()`: the command resolves its own paths from
 * the project root, so pointing it somewhere else would mean testing a different code path than the one that
 * ships.
 */
final class SceneStackCommandTest extends TestCase
{
    private const THROWAWAY_ID = 'zz-test-stack';

    protected function tearDown(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * The default call is three scenes — one per alignment — and not the cross product of every tier's
     * options, which would be hundreds.
     */
    public function testTheDefaultCallProducesOneScenePerAlignment(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--dry-run' => true]);

        $output = $tester->getDisplay();
        foreach (['stacked-center.yaml', 'stacked-block.yaml', 'stacked-stereo.yaml'] as $expected) {
            self::assertStringContainsString($expected, $output);
        }
        self::assertSame(0, $tester->getStatusCode());
    }

    /** The fast path: one alignment, one sub placement, exactly one scene. */
    public function testASingleAlignmentProducesExactlyOneScene(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--align' => ['block'], '--subs' => 'mixed', '--dry-run' => true]);

        self::assertSame(1, preg_match_all('/^id: /m', $tester->getDisplay()));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->invoke(['--max-width' => '3.70', '--id' => self::THROWAWAY_ID, '--dry-run' => true]);

        self::assertSame([], glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: []);
    }

    /**
     * Generated files live next to hand-written ones, so clobbering one has to be asked for. This is the
     * test that stops the command eating a scene somebody spent an afternoon commenting.
     */
    public function testAnExistingSceneIsRefusedWithoutForce(): void
    {
        $first = $this->invoke(['--max-width' => '3.70', '--align' => ['block'], '--id' => self::THROWAWAY_ID]);
        self::assertSame(0, $first->getStatusCode());

        $again = $this->invoke(['--max-width' => '3.70', '--align' => ['block'], '--id' => self::THROWAWAY_ID]);
        self::assertSame(1, $again->getStatusCode());
        self::assertStringContainsString('--force', $again->getDisplay());

        $forced = $this->invoke(['--max-width' => '3.70', '--align' => ['block'], '--id' => self::THROWAWAY_ID, '--force' => true]);
        self::assertSame(0, $forced->getStatusCode());
    }

    /**
     * A silent cap would read as "that is every possibility" when it is not, so going over the limit is a
     * refusal with the count in it.
     */
    public function testExceedingMaxScenesIsRefusedRatherThanTruncated(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--subs' => 'both', '--max-scenes' => '2', '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--max-scenes=2', $tester->getDisplay());
    }

    /**
     * A wide stage must not cost the rig its height.
     *
     * This is the bug that made `--max-width=10` — and `--max-width` left out entirely — unsolvable: the
     * mixed bottom row grew until the *width bound* stopped it, swallowing all twelve Flexys into one
     * 8.572 m row, leaving two sub tiers at 1.514 m and no way to clear a 2 m interface however the tops were
     * arranged. The flanking width is now spent only as far as the interface allows.
     */
    public function testAWideStageStillReachesTheInterface(): void
    {
        foreach ([['--max-width' => '10.0'], []] as $widthOption) {
            $tester = $this->invoke($widthOption + ['--interface-height' => '2.0', '--align' => ['center'], '--dry-run' => true]);

            self::assertSame(0, $tester->getStatusCode(), 'a wide or unbounded stage still solves');
            self::assertStringContainsString('clear the 2.0 m interface', $tester->getDisplay());
        }
    }

    /**
     * Every arrangement it rules out says why — and a height nothing we own can reach is the honest case for
     * that, rather than one the solver could have found its own way around.
     */
    public function testAnArrangementThatCannotBeSolvedIsSkippedWithAReason(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--interface-height' => '9.0', '--dry-run' => true]);

        self::assertStringContainsString('skipped', $tester->getDisplay());
        self::assertStringContainsString('interface_height_m', $tester->getDisplay());
        self::assertSame(1, $tester->getStatusCode(), 'nothing workable is a failure, not a silent success');
    }

    /**
     * The one that matters. A generator that emits a scene the compiler rejects is worse than no generator,
     * because the failure then surfaces later and further from its cause — so every candidate is compiled
     * before it is written, and this pins that the check is real by reading the cabinet counts back.
     */
    public function testEveryGeneratedSceneCompilesAndPlacesEveryCabinet(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--subs' => 'both', '--id' => self::THROWAWAY_ID]);

        self::assertSame(0, $tester->getStatusCode());
        $written = glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: [];
        self::assertNotSame([], $written);

        $devices = [];
        foreach ((new SpecLoader(dirname(__DIR__, 2).'/specs'))->loadAll()['specs'] as $spec) {
            /** @var DeviceSpec $spec */
            $devices[$spec->id] = $spec;
        }
        $loader = new SceneLoader(dirname(__DIR__, 2).'/scenes');

        foreach ($written as $file) {
            $result = (new SceneCompiler($devices))->compile($loader->load($file));

            self::assertSame(
                [],
                array_map(static fn ($v): string => $v->message, Violation::errorsIn($result['violations'])),
                basename($file).' does not compile',
            );
            // `mixed` puts all 23 in the stack; `beside` stands the two SKRAMs next to it. Either way the
            // whole inventory ends up somewhere — a generator that quietly dropped cabinets would pass
            // every other check in this file.
            self::assertCount(23, $result['placed'], basename($file).' lost cabinets');
        }
    }

    public function testAnUnknownAlignmentIsRejected(): void
    {
        $tester = $this->invoke(['--align' => ['blok'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("unknown value 'blok'", $tester->getDisplay());
    }

    public function testAnUnknownDeviceIsRejected(): void
    {
        $tester = $this->invoke(['--from' => ['nope'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("Unknown device 'nope'", $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invoke(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new SceneStackCommand());

        $tester = new CommandTester($application->find('scene:stack'));
        $tester->execute($options);

        return $tester;
    }
}
