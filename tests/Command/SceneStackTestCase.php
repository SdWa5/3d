<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SceneStackCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What every `scene:stack` test needs, so the four axis suites do not each carry a copy of it.
 *
 * The bargain with the filesystem is the reason this is shared rather than duplicated. Everything runs
 * `--dry-run` except the tests that are *about* writing, and those write into the real `scenes/` directory
 * under a throwaway `--id` that {@see tearDown} removes. The command resolves its own paths from the project
 * root, so pointing it somewhere else would test a different code path than the one that ships — which means
 * every suite has to clean up after itself the same way, and a second copy of that rule is a second chance to
 * get it wrong.
 *
 * The suites: {@see SceneStackCommandTest} for the sweep itself and the refusals,
 * {@see SceneStackMirrorTest} for the mirror and orientation axes, {@see SceneStackSystemsTest} for the
 * separation, split and stack-count axes, {@see SceneStackFeasibilityTest} for the heights, bands and what
 * happens to a rig that does not stand up, and {@see SceneStackBringsTest} for the count overrides.
 */
abstract class SceneStackTestCase extends TestCase
{
    protected const THROWAWAY_ID = 'zz-test-stack';

    /**
     * Every cabinet that can share a stack. The SKRAMs are left out on purpose: only two exist, nothing else
     * shares their height so they cannot be mixed into a row, and a row of their own carries nothing — the
     * solver refuses them, which {@see SceneStackFeasibilityTest::testARigWithNoArrangementAtAllIsStillRefusedRatherThanDrawn}
     * pins.
     */
    protected const STACKABLE = ['flexy-folded-horn-hybrid', 'achenbach-18', 'tecnare-m2122', 'eighteensound-2way-15'];

    /**
     * Everything the collective itself owns, SKRAMs included — the whole of what one rig could be built from.
     *
     * This used to be expressed as passing no `--from` at all, which meant "every spec in the repository". That
     * stopped being the same thing when the GMSS cabinets arrived: those describe a **different sound system**
     * that this repository only documents, they are all `provenance: estimated`, and a rig solved out of two
     * systems' gear at once is not something anybody would build. So the gear is named now rather than implied.
     *
     * **The order matters and is not alphabetical.** `--from` is taken as given — "low frequency first" — where
     * the default sorts subs before tops and the subs by power per area in each pair's lowest octave. This list
     * reproduces that sort: SKRAM (15 Hz) then Flexy (38 Hz, 3991 W/m²) then Achenbach (35 Hz, 2778 W/m², which
     * loses the 35–70 Hz octave by 1.4 dB), then the tops. Shuffle it and the solver deals a different rig.
     */
    protected const OWN_GEAR = [
        'skram',
        'flexy-folded-horn-hybrid',
        'achenbach-18',
        'eighteensound-2way-15',
        'tecnare-m2122',
    ];

    protected function tearDown(): void
    {
        foreach (self::throwaway() as $file) {
            unlink($file);
        }
    }

    /**
     * Every throwaway file this test wrote, **at any depth** under `scenes/generated/`.
     *
     * A one-level `glob()` was enough while every generated scene sat in that one directory. It stopped being
     * enough when the inventory became a folder, and it would stop being enough again the moment another axis
     * does — and the failure is not a red test, it is throwaway files left behind in a tracked directory on every
     * run. Cleaned up by name rather than by directory, since the real generated set lives in the same tree.
     *
     * @return list<string>
     */
    protected static function throwaway(string $prefix = self::THROWAWAY_ID): array
    {
        $root = dirname(__DIR__, 2).'/scenes/generated';
        $found = [];
        foreach (self::generatedScenes() as $file) {
            if (str_starts_with(basename($file), $prefix)) {
                $found[] = $file;
            }
        }
        // Also the root of `scenes/` itself, where a run that names no inventory writes.
        foreach (glob(dirname($root).'/'.$prefix.'*.yaml') ?: [] as $file) {
            $found[] = $file;
        }
        sort($found);

        return $found;
    }

    /**
     * Every generated scene on disk, wherever it is filed.
     *
     * **A FLAT GLOB STOPPED SEEING ANY OF THEM AND SAID NOTHING.** The inventory moved out of the file name and
     * into a folder in 0.98.0, so `scenes/generated/*.yaml` matches nothing at all now — and a test that loops over
     * an empty list passes every assertion inside the loop. Two tests here were reduced to that, and one of them
     * only failed because it counts what it saw at the end.
     *
     * @return list<string>
     */
    protected static function generatedScenes(): array
    {
        $root = dirname(__DIR__, 2).'/scenes/generated';

        return [...glob($root.'/*.yaml') ?: [], ...glob($root.'/*/*.yaml') ?: []];
    }

    protected function invoke(array $options): CommandTester
    {
        $application = new Application();
        $application->add(new SceneStackCommand());

        $tester = new CommandTester($application->find('scene:stack'));
        $tester->execute($options);

        return $tester;
    }

    /**
     * Dry runs already made in this process, keyed on their options. Shared by every subclass, because the same
     * single-owner sweep is asked for by three of them.
     *
     * @var array<string, CommandTester>
     */
    private static array $dryRuns = [];

    /**
     * A `--dry-run` of these options, run once per process and handed to every test that asks for the same thing.
     *
     * **TOOL-22 measured four tests running the identical sweep**, two of them a combined 178 s on the same
     * `gmss` + `sepp` rigs. A dry run writes nothing and reads only the options and `specs/`, and neither changes
     * while the suite runs, so a second run can only repeat the first. Tests that write, or that assert on what a
     * run does to the disk, keep calling {@see invoke}.
     *
     * @param array<string, mixed> $options
     */
    protected function dryRun(array $options): CommandTester
    {
        $options['--dry-run'] = true;
        ksort($options);

        return self::$dryRuns[serialize($options)] ??= $this->invoke($options);
    }

    /**
     * The sub heights of each stack, left to right, off the header the writer prints.
     *
     * @return list<float>
     */
    protected function heights(string $display): array
    {
        preg_match_all('/^# Subs reach ([\d.]+) m/m', $display, $matches);

        return array_map(floatval(...), $matches[1]);
    }
}
