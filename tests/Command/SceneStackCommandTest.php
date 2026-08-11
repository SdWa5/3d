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

    /**
     * Every cabinet that can share a stack. The SKRAMs are left out on purpose: only two exist, nothing else
     * shares their height so they cannot be mixed into a row, and a row of their own carries nothing — the
     * solver refuses them, which {@see testAnArrangementThatCannotBeSolvedIsSkippedWithAReason} pins.
     */
    private const STACKABLE = ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'];

    protected function tearDown(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * The alignments are tried, and the ones that resolve to the same rig are written once.
     *
     * Every top now shares one row, that row is mixed, and a mixed row is not distributed one segment at a
     * time — so `center`, `block` and `stereo` come out identical here and only the first is written. Three
     * files would imply a choice that does not exist.
     */
    public function testAlignmentsThatResolveToTheSameRigAreWrittenOnce(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--from' => self::STACKABLE, '--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(1, preg_match_all('/^id: /m', $tester->getDisplay()));
        self::assertStringContainsString('the same rig as stacked-center', $tester->getDisplay());
    }

    /** The fast path: one alignment named outright, exactly one scene. */
    public function testASingleAlignmentProducesExactlyOneScene(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--align' => ['block'], '--dry-run' => true,
        ]);

        self::assertSame(1, preg_match_all('/^id: /m', $tester->getDisplay()));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--id' => self::THROWAWAY_ID, '--dry-run' => true,
        ]);

        self::assertSame([], glob(dirname(__DIR__, 2).'/scenes/'.self::THROWAWAY_ID.'*.yaml') ?: []);
    }

    /**
     * Generated files live next to hand-written ones, so clobbering one has to be asked for. This is the
     * test that stops the command eating a scene somebody spent an afternoon commenting.
     */
    public function testAnExistingSceneIsRefusedWithoutForce(): void
    {
        $args = ['--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['block'], '--id' => self::THROWAWAY_ID];
        $first = $this->invoke($args);
        self::assertSame(0, $first->getStatusCode());

        $again = $this->invoke($args);
        self::assertSame(1, $again->getStatusCode());
        self::assertStringContainsString('--force', $again->getDisplay());

        $forced = $this->invoke($args + ['--force' => true]);
        self::assertSame(0, $forced->getStatusCode());
    }

    /**
     * A silent cap would read as "that is every possibility" when it is not, so going over the limit is a
     * refusal with the count in it.
     */
    public function testExceedingMaxScenesIsRefusedRatherThanTruncated(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--max-scenes' => '0', '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--max-scenes=0', $tester->getDisplay());
    }

    /**
     * A wide stage must not cost the rig its height. `max_width_m` is a maximum, not a target: on a 10 m
     * stage every device fits in one row, which leaves two sub tiers and puts a 2 m interface out of reach
     * forever unless the rows are allowed to narrow.
     */
    public function testAWideStageStillReachesTheInterface(): void
    {
        foreach ([['--max-width' => '10.0'], []] as $widthOption) {
            $tester = $this->invoke($widthOption + [
                '--from' => self::STACKABLE, '--interface-height' => '2.0',
                '--align' => ['center'], '--dry-run' => true,
            ]);

            self::assertSame(0, $tester->getStatusCode(), 'a wide or unbounded stage still solves');
            self::assertStringContainsString('clear the 2.0 m interface', $tester->getDisplay());
        }
    }

    /**
     * Every arrangement it rules out says why, and the SKRAMs are the honest case for it: two cabinets whose
     * height nothing else shares, so they cannot be mixed into a row, and whose own row is too narrow to
     * carry the tops. Asked for the whole inventory, the command refuses and names the fix.
     */
    public function testAnArrangementThatCannotBeSolvedIsSkippedWithAReason(): void
    {
        $tester = $this->invoke(['--max-width' => '3.70', '--interface-height' => '2.0', '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode(), 'nothing workable is a failure, not a silent success');
        self::assertStringContainsString('skipped', $tester->getDisplay());
        self::assertStringContainsString('stands on nothing', $tester->getDisplay());
        self::assertStringContainsString('take the odd cabinets out of the stack', $tester->getDisplay());
    }

    /**
     * The one that matters. A generator that emits a scene the compiler rejects is worse than no generator,
     * because the failure then surfaces later and further from its cause — so every candidate is compiled
     * before it is written, and this pins that the check is real by reading the cabinets back.
     */
    public function testEveryGeneratedSceneCompilesAndPlacesEveryCabinet(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--id' => self::THROWAWAY_ID,
        ]);

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
            // 12 Flexy + 4 Achenbach + 3 Tecnare + 2 2-ways. A generator that quietly dropped cabinets
            // would pass every other check in this file.
            self::assertCount(21, $result['placed'], basename($file).' lost cabinets');
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
