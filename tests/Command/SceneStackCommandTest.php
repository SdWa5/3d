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

    /**
     * Everything the collective itself owns, SKRAMs included — the whole of what one rig could be built from.
     *
     * This used to be expressed as passing no `--from` at all, which meant "every spec in the repository". That
     * stopped being the same thing when the GMSS cabinets arrived: those describe a **different sound system**
     * that this repository only documents, they are all `provenance: estimated`, and a rig solved out of two
     * systems' gear at once is not something anybody would build. So the gear is named now rather than implied.
     *
     * **The order matters and is not alphabetical.** `--from` is taken as given — "low frequency first" — where
     * the default sorts subs before tops and each by its driven corner. This list reproduces that sort: SKRAM
     * (15 Hz) then Flexy (38, up to 200) then Achenbach (driven from 38, up to 1500, so the high corner breaks
     * the tie), then the tops. Shuffle it and the solver deals a different rig.
     */
    private const OWN_GEAR = [
        'skram',
        'flexy-folded-horn-hybrid',
        'achenbach-18',
        'eighteensound-2way-15',
        'tecnare-m2122',
    ];

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
            self::assertStringContainsString('against a 2.0 m interface', $tester->getDisplay());
        }
    }

    /**
     * Asked for the whole inventory, the command places **all twenty-three** cabinets in one stack.
     *
     * The SKRAMs used to be left out, because a row of the two of them carries nothing and mixing them into a
     * Flexy row was banned after that mixed row left Flexys floating. Gravity removed the reason for the ban —
     * each cabinet lands on whatever is under it — so the arrangement the mixed row was built for works, and
     * the leaving-out machinery is left for cases that genuinely cannot stand up.
     *
     * 5.0 m rather than 3.70 m: six real Achenbachs are their own row's full 3.70 m stage width, so a SKRAM
     * row flanked by three Flexys either side (4.906 m) no longer fits under the 3.70 m bound and the command
     * falls back to leaving the SKRAMs out. Widen the stage and the flanked arrangement is reachable again.
     *
     * The gear is named via {@see OWN_GEAR} rather than left to the default, which is what "the whole
     * inventory" meant before a second sound system was documented in this repository.
     */
    public function testEverythingTheCollectiveOwnsGoesIntoOneStack(): void
    {
        $tester = $this->invoke([
            '--max-width' => '5.0', '--interface-height' => '2.0', '--from' => self::OWN_GEAR,
            '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringNotContainsString('LEFT OUT', $output, 'nothing needs leaving out any more');
        self::assertStringContainsString('2× skram', $output, 'the SKRAMs share the bottom row');
    }

    /**
     * `--per-owner` groups by {@see \App\Spec\DeviceSpec::$owner} and adds no new concept: for this
     * collective, who owns a cabinet *is* the split between the rigs. Each group becomes its own stack, and
     * the stacks stand side by side rather than merging into one pile.
     *
     * Three owners now, not two — GMSS is the third, and it is exactly the case this option is for: a second
     * sound system's gear must not end up in the same pile as the collective's own.
     */
    public function testPerOwnerWritesOneStackPerOwnerSideBySide(): void
    {
        $tester = $this->invoke([
            '--per-owner' => true, '--max-width' => '3.70',
            '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-sdwa5', $output);
        self::assertStringContainsString('- id: main-sepp', $output);
        self::assertStringContainsString('- id: main-gmss', $output);
        self::assertStringContainsString('3 stacks side by side', $output);
    }

    /** `--stacks=2` is how a stereo pair is asked for: each group split evenly into two. */
    public function testStacksSplitsAGroupIntoThatManyStacks(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-1', $output);
        self::assertStringContainsString('- id: main-2', $output);
        // Six Flexys each rather than twelve in one, because the split shares every device out.
        self::assertStringContainsString('2 stacks side by side', $output);
    }

    /**
     * A split rig writes each stack's **share** into the file, because the file is re-solved on every build.
     *
     * This was a silent and complete failure of multi-stack scenes. A `stack:` block carries constraints and a
     * device list, and the compiler solves it afresh against each spec's own `quantity` — so a rig reported in
     * its header as two stacks of eleven was *built* with both stacks holding all twenty-three cabinets: two
     * 3.6 m walls 0.5 m apart, 561 mm inside each other. Nothing said so, because the header comment described
     * the intended split and no check ever compiled the written file.
     */
    public function testASplitRigWritesEachStacksShareSoItRebuildsTheSame(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        // Six of the twelve Flexys per stack, stated, so the re-solve cannot reach for all twelve.
        $output = $tester->getDisplay();
        self::assertStringContainsString("- device: flexy-folded-horn-hybrid\n          count: 6", $output);
        self::assertSame(2, substr_count($output, 'count: 6'), 'one share per stack');
    }

    /** And a rig that is *not* split keeps the shorthand, so an ordinary file stays a list of ids. */
    public function testAnUnsplitRigKeepsTheShorthandDeviceList(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--align' => ['center'], '--dry-run' => true,
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('- flexy-folded-horn-hybrid', $output);
        self::assertStringNotContainsString('count:', $output);
    }

    /**
     * One stack of a side-by-side pair is the **mirror image** of the other, not a second copy of it.
     *
     * An unmirrored pair is the same rig built twice: both SKRAM mouths facing the same way, both tops rows in the
     * same left-to-right order, and the two fills therefore on the same side of their stacks instead of both
     * facing the middle. It measures identically to a mirrored pair, which is why nothing caught it.
     */
    public function testOneStackOfEachPairIsTheMirrorImageOfTheOther(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--stacks' => '2', '--align' => ['center'], '--dry-run' => true,
            '--roll-mirror' => ['skram', 'flexy-folded-horn-hybrid'],
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('mirror: true', $output);
        self::assertSame(1, substr_count($output, 'mirror: true'), 'only the earlier stack of the pair flips');

        // The 2-way ends up on the inner side of each stack: right of the M2122 in the left stack, left of it in
        // the right one.
        self::assertStringContainsString('1× tecnare-m2122 + 1× eighteensound-2way-15', $output);
        self::assertStringContainsString('1× eighteensound-2way-15 + 1× tecnare-m2122', $output);

        // And the SKRAM is handed the other way in the mirrored stack.
        self::assertStringContainsString('1× skram rolled 270°', $output);
        self::assertStringContainsString('1× skram rolled 90°', $output);
    }

    /** A single stack has no pair to mirror against, so it never claims one. */
    public function testASingleStackIsNeverMirrored(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE, '--align' => ['center'], '--dry-run' => true,
        ]);

        self::assertStringNotContainsString('mirror:', $tester->getDisplay());
    }

    public function testAStackCountBelowOneIsRejected(): void
    {
        $tester = $this->invoke(['--stacks' => '0', '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--stacks must be at least 1', $tester->getDisplay());
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
            // 12 Flexy + 6 Achenbach + 3 Tecnare + 2 2-ways. A generator that quietly dropped cabinets
            // would pass every other check in this file.
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
