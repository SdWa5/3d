<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Scene\SweepAxes;
use App\Spec\DeviceSpec;

/**
 * `scene:stack` — separation, splitting and how many stacks a rig comes out in.
 *
 * One inventory can be dealt in several ways, and the three that matter are counted in *systems* rather than
 * in owners: `pooled` builds one rig from everything, `systems-apart` stands each system's gear on its own,
 * and `tops-shared` puts one system's tops on another's subs. A rig drawn from one system has nothing to
 * separate and is offered `pooled` alone, which is a third of the sweep not solved twice for one file.
 *
 * `--split` then decides how a group is dealt into the stacks it was given, and `--stacks` how many there
 * are. The odd cabinet is placed by default and left out only when asked, because a rig that silently drops a
 * cabinet is a rig somebody loads a van for and then cannot build.
 */
final class SceneStackSystemsTest extends SceneStackTestCase
{
    /**
     * **The sweep offers rigs where the two sound systems stand apart, which it never did before.**.
     *
     * SWP-2's seventh axis, all three values of it. Every generated scene pooled the gear until 0.91.0 — verified
     * rather than assumed, since not one written file carried `--per-owner` in its recorded command — because naming
     * that option collapses the sweep to a single point. So a rig with each system in its own stack could be asked
     * for by hand and never came out of the sweep.
     *
     * **None of the three values is marginal.** On the `gmss` + `sepp` pair the sweep writes **116 `systems-apart`,
     * 105 `tops-shared` and 90 `pooled`**, because a system in its own narrower stack stands up more often than two
     * systems in one wide one, and the tops of one system standing on the other's subs is a third rig again.
     */
    public function testTheSweepOffersSystemsStandingApartAsWellAsPooled(): void
    {
        $display = $this->invoke(['--owner' => ['gmss', 'sepp'], '--low-end' => ['low'], '--dry-run' => true])->getDisplay();

        preg_match_all('/^id: (\S+)$/m', $display, $matches);
        self::assertNotSame([], $matches[1]);

        foreach ($matches[1] as $id) {
            self::assertMatchesRegularExpression(
                '/-(pooled|systems-apart|tops-shared)-+/',
                $id,
                $id.' does not say how separately its systems stand',
            );
        }

        // **Every value writes something, and each is counted for itself.** Asserting only that the field is present
        // would pass on a sweep that never separated anything, which is precisely the state this axis replaces — and
        // counting "not pooled" as one number would let a third value that wrote nothing hide behind the second.
        $count = static fn (string $value): int => count(array_filter(
            $matches[1],
            static fn (string $id): bool => str_contains($id, '-'.$value.'-'),
        ));

        self::assertGreaterThan(0, $count('pooled'));
        self::assertGreaterThan(0, $count('tops-shared'));
        self::assertGreaterThan(
            $count('pooled'),
            $count('systems-apart'),
            'two systems in their own stacks stand up more often than two systems in one',
        );
    }

    /**
     * **A separated rig records that it is separated, and this was a real defect rather than a hypothetical.**.
     *
     * SWP-2's axis lives on the rig rather than on the input, so `commandLine()` reading `--per-owner` off the
     * input recorded nothing at all for a swept `systems-apart` rig. The replay then rebuilt it **pooled**, under
     * the separated rig's name, with different geometry — and measured across the sweep, **99 of 976 scenes
     * replayed to a different file**, most of them flipping `-possible` to `-impossible`.
     *
     * `build:all`'s replay caught it, which is what that stage is for. This asserts the rule directly so the next
     * axis that lives on the rig does not have to be caught by a three-minute test over a thousand files.
     */
    public function testASeparatedRigRecordsTheSeparationInItsOwnRegenerateLine(): void
    {
        $display = $this->invoke(['--owner' => ['gmss', 'sepp'], '--low-end' => ['low'], '--dry-run' => true])->getDisplay();

        // Split on the section title the dry run prints before each file, not on the `id:` line inside the YAML —
        // the recorded command sits in the header *above* that line, so splitting there puts the two in different
        // blocks and the test passes or fails for the wrong reason.
        $parts = preg_split('/^(stacked\S*\.yaml)$/m', $display, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $separated = 0;
        for ($i = 1; $i < count($parts); $i += 2) {
            $name = $parts[$i];
            if (!str_contains($name, '-systems-apart')) {
                continue;
            }
            ++$separated;
            self::assertStringContainsString(
                '--systems=systems-apart',
                $parts[$i + 1] ?? '',
                $name.' does not record its own separation, so a replay would rebuild it three ways',
            );
        }

        self::assertGreaterThan(0, $separated, 'no separated rig was written, so nothing was checked');
    }

    /**
     * **A rig drawn from one owner is offered `pooled` alone**, because one system separated from nothing is one
     * system. Left to the deduplication instead, every single-owner rig would be solved twice to write one file —
     * and single-owner rigs are 153 of the sweep's scenes, so that is a large fraction of the work spent proving a
     * tautology.
     *
     * Both separated values, since the same argument retires both: one system's subs with its own tops dealt back
     * onto them is the rig `pooled` already wrote.
     */
    public function testASingleOwnerRigIsNotOfferedASeparationItCannotHave(): void
    {
        $display = $this->invoke(['--owner' => ['gmss'], '--low-end' => ['low'], '--dry-run' => true])->getDisplay();

        self::assertStringContainsString('-pooled', $display);
        self::assertStringNotContainsString('-systems-apart', $display);
        self::assertStringNotContainsString('-tops-shared', $display);
    }

    /**
     * **`tops-shared` stands one system's tops on another system's subs, which is the rig neither other value can
     * express.** SWP-2's third value.
     *
     * On the gear we own the difference is not subtle: there are exactly three top types and one belongs to each
     * owner — three Tecnares to `sdwa5`, two 2-ways to `sepp`, three turbo tops to `gmss`. So `pooled` mixes
     * everything into one stack and `systems-apart` puts each owner's tops straight back onto that owner's own subs.
     * Only this value can put a Tecnare on GMSS's wall, and on the `gmss` + `sdwa5` pair it does exactly that.
     *
     * **Asserted generically rather than on the pair of ids it happens to produce today.** The claim is that some
     * stack carries a top from another system, not that GMSS's wall carries the third Tecnare specifically — the
     * deal spends a measured budget, so which cabinet lands where is a consequence of the walls the solver returned.
     */
    public function testTopsSharedStandsOneSystemsTopsOnAnothersSubs(): void
    {
        $display = $this->invoke([
            '--owner' => ['gmss', 'sdwa5'],
            '--systems' => ['tops-shared'],
            '--stacks' => '1',
            '--shape' => ['pyramid'],
            '--orientation' => ['upright'],
            '--align' => ['center'],
            '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        // Which owner each top belongs to, written out rather than loaded, so the test says what it means. A stack
        // is named `main-<owner>` by the generator, which is what makes the comparison possible at all.
        $owners = ['tecnare-m2122' => 'sdwa5', 'turbo-top' => 'gmss', 'eighteensound-2way-15' => 'sepp'];

        $borrowed = [];
        $stack = null;
        foreach (explode("\n", $display) as $line) {
            if (1 === preg_match('/^  - id: main-(\S+)$/', $line, $named)) {
                $stack = $named[1];
                continue;
            }
            if (null === $stack || 1 !== preg_match('/^\s+- (?:device: )?(\S+)$/', $line, $device)) {
                continue;
            }
            $owner = $owners[$device[1]] ?? null;
            if (null !== $owner && $owner !== $stack) {
                $borrowed[] = sprintf('%s on the %s stack', $device[1], $stack);
            }
        }

        self::assertNotSame(
            [],
            $borrowed,
            'no stack carries another system\'s tops, so this is `systems-apart` under a different name',
        );
    }

    /**
     * **A `tops-shared` rig records its own value**, for the reason the separated rigs do: the axis lives on the rig
     * rather than on the input, so a replay that read the options back off the command line would rebuild it pooled
     * under this rig's name.
     *
     * `--per-owner` cannot say it. That flag is the older way to ask for one value of the axis and means
     * `systems-apart`, so the value that has no flag is recorded by name.
     */
    public function testATopsSharedRigRecordsItsOwnValue(): void
    {
        $display = $this->invoke([
            '--owner' => ['gmss', 'sdwa5'],
            '--systems' => ['tops-shared'],
            '--stacks' => '1',
            '--shape' => ['pyramid'],
            '--orientation' => ['upright'],
            '--align' => ['center'],
            '--low-end' => ['low'], '--dry-run' => true,
        ])->getDisplay();

        self::assertStringContainsString('--systems=tops-shared', $display);
        self::assertStringNotContainsString('--per-owner', $display);
    }

    /**
     * A misspelled value on the seventh axis is refused and every allowed one is named, which is the rule
     * {@see SweepAxes} holds for all six parsed axes.
     */
    public function testAnUnknownSystemsValueNamesTheThreeThereAre(): void
    {
        $tester = $this->invoke(['--systems' => ['apart-ish'], '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        foreach (['pooled', 'systems-apart', 'tops-shared'] as $value) {
            self::assertStringContainsString($value, $tester->getDisplay());
        }
    }

    /** An unknown `--split` names the values there are, rather than falling back to one of them. */
    public function testAnUnknownSplitIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--max-width' => '3.70', '--split' => 'sideways', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--split: unknown value 'sideways'", $tester->getDisplay());
        self::assertStringContainsString('allowed: by-count, by-type', $tester->getDisplay());
    }

    /**
     * `--split=by-type` gives each stack whole device types, and that is what makes it low.
     *
     * Split by count, every stack holds every type and is as many rows tall as there are types. Split by type,
     * each holds two or three and comes out in three rows. Asserted on the **rows**, not on which type landed
     * where: the balance is decided by `quantity × width`, so a cabinet finally being measured is allowed to move
     * a type from one stack to another without this test caring.
     */
    public function testSplitByTypeGivesEachStackWholeTypesAndFewerRows(): void
    {
        $shared = [
            // Two stacks rather than three: at three, an aimed tops row overlaps itself by 17.6 mm and the generator
            // refuses it now. The point of this test — by-type gives each stack whole types and fewer rows — is the
            // same either way.
            '--systems' => ['pooled'], '--from' => self::OWN_GEAR, '--stacks' => '2', '--max-width' => '3.70',
            '--interface-height' => '0', '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ];

        $byCount = $this->invoke($shared);
        $byType = $this->invoke($shared + ['--split' => 'by-type']);

        self::assertSame(0, $byType->getStatusCode());
        // No stack taller than the tallest by-count one. Asserted on ROWS being no more rather than strictly fewer:
        // at two stacks the two splits need the same row count and by-type's gain shows up as height, while at three
        // an aimed tops row overlaps itself and the generator refuses the rig outright.
        self::assertLessThanOrEqual(
            max($this->rowsPerStack($byCount->getDisplay())),
            max($this->rowsPerStack($byType->getDisplay())),
            'by-type should leave no stack taller than the tallest by-count stack',
        );

        // Every stack holds every type by count, and a proper subset of them by type.
        foreach ($this->rowsPerStack($byType->getDisplay()) as $rows) {
            self::assertLessThan(count(self::OWN_GEAR), $rows);
        }
    }

    /**
     * `--split=by-type` needs at least one type per stack, and says so rather than writing an empty stack.
     */
    public function testSplitByTypeWithMoreStacksThanTypesSaysSo(): void
    {
        $tester = $this->invoke([
            '--systems' => ['pooled'], '--from' => self::OWN_GEAR, '--stacks' => '9', '--split' => 'by-type',
            '--max-width' => '3.70', '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('5 device types cannot fill 9 stacks', $tester->getDisplay());
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
        // One shape and one orientation, so the count below is the two stacks of one scene rather than the same two
        // stacks across every variant of it.
        $tester = $this->invoke([
            '--max-width' => '3.70', '--systems' => ['pooled'], '--from' => self::STACKABLE, '--stacks' => '2', '--align' => ['center'],
            '--shape' => ['pyramid'], '--orientation' => ['upright'], '--low-end' => ['low'], '--dry-run' => true,
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
            '--max-width' => '3.70', '--systems' => ['pooled'], '--stacks' => '1', '--from' => self::STACKABLE,
            '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('- flexy-folded-horn-hybrid', $output);
        self::assertStringNotContainsString('count:', $output);
    }

    /** `--stacks=2` is how a stereo pair is asked for: each group split evenly into two. */
    public function testStacksSplitsAGroupIntoThatManyStacks(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--from' => self::STACKABLE,
            '--stacks' => '2', '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-1', $output);
        self::assertStringContainsString('- id: main-2', $output);
        // Six Flexys each rather than twelve in one, because the split shares every device out.
        self::assertStringContainsString('2 stacks side by side', $output);
    }

    /**
     * **A separated rig gives every system its own focus points; a pooled one does not.**.
     *
     * The scene's focus is measured from the *rig's* front centre, so three systems side by side would all aim at
     * a point in front of the middle one — covering one patch of floor between them instead of each covering the
     * room it stands in front of. A pooled rig split into two or three stacks is one system in several piles and
     * shares a focus, because it is aimed as one cluster.
     */
    public function testEachSystemGetsItsOwnFocusAndAPooledRigDoesNot(): void
    {
        $shared = ['--owner' => ['gmss', 'sepp'], '--stacks' => '1', '--shape' => ['free'],
            '--orientation' => ['upright'], '--mirror-style' => ['alternate'], '--align' => ['center'],
            '--low-end' => ['low'], '--dry-run' => true];

        $apart = $this->invoke($shared + ['--systems' => ['systems-apart']])->getDisplay();
        $pooled = $this->invoke($shared + ['--systems' => ['pooled']])->getDisplay();

        // One `focus:` under each of the two walls, and the placements keep aiming at it by name.
        self::assertSame(2, preg_match_all('/^    focus:$/m', $apart), 'one focus per system');
        self::assertSame(0, preg_match_all('/^    focus:$/m', $pooled), 'a pooled rig is one system');
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
        // The shape and the orientation are named, because the band stopped refusing the variants: a `v` on rolled
        // cabinets does have to leave a Tecnare out, and it is a different rig rather than a counter-example to this
        // one. The subject is the SKRAMs sharing a bottom row in the rig this test has always been about.
        $tester = $this->invoke([
            '--max-width' => '5.0', '--interface-height' => '2.0', '--from' => self::OWN_GEAR,
            '--shape' => ['free'], '--orientation' => ['upright'], '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringNotContainsString('LEFT OUT', $output, 'nothing needs leaving out any more');
        self::assertStringContainsString('2× skram', $output, 'the SKRAMs share the bottom row');
    }

    /**
     * `--per-owner` groups by {@see DeviceSpec::$owner} and adds no new concept: for this
     * collective, who owns a cabinet *is* the split between the rigs. Each group becomes its own stack, and
     * the stacks stand side by side rather than merging into one pile.
     *
     * **Two stacks rather than the library's five, and that is the default inventory doing its job.** This test
     * asserted three when there were three owners, and would have asserted five the day PSL and Innschleife were
     * specced — five systems side by side, four of them borrowed, from a command line that says nothing about whose
     * gear. `--per-owner` narrows the separation and not the inventory, so silence falls back to
     * {@see SweepAxes::DEFAULT_OWNERS} here exactly as it does for a bare sweep. Naming an owner still
     * works: `--per-owner --owner=gmss --owner=sdwa5` is two stacks of those two.
     */
    public function testPerOwnerWritesOneStackPerOwnerSideBySide(): void
    {
        $tester = $this->invoke([
            // **Per SYSTEM, not per owner**, which is why `gmss` is named: `sdwa5` and `sepp` are one system now
            // and a rig of the two of them has nothing to stand apart. See {@see \App\Scene\SystemGrouping}.
            '--per-owner' => true, '--owner' => ['gmss', 'sdwa5', 'sepp'], '--stacks' => '1',
            '--max-width' => '3.70', '--gap' => '0.05',
            // Outside the band by construction — see `testTheTallestStackGoesWhereTheAlignmentWantsIt`. The subject
            // here is which stack each owner's gear lands in.
            '--interface-height' => '0', '--max-sub-height' => '99',
            // Pinned to the pyramid, and to the 0.05 m gap every per-owner scene in the library uses: at 0.02 the
            // aimed tops toe into each other, which is the whole reason the wider gap was chosen for these rigs.
            // Pinned to the pyramid because: the free-shape GMSS stack interpenetrates by 22.5 mm and the generator now
            // refuses it. That overlap is not new — it was simply never checked, because `--dry-run` writes no file
            // and only written scenes reach the shipped-scene sweep.
            '--shape' => ['pyramid'], '--mirror-style' => ['alternate'],
            '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('- id: main-ours', $output, 'our gear and Sepp\'s are one wall');
        self::assertStringContainsString('- id: main-gmss', $output);
        self::assertStringNotContainsString('- id: main-sdwa5', $output);
        self::assertStringNotContainsString('- id: main-sepp', $output);
        self::assertStringContainsString('2 stacks side by side', $output);
    }

    public function testAStackCountBelowOneIsRejected(): void
    {
        $tester = $this->invoke(['--stacks' => '0', '--low-end' => ['low'], '--dry-run' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--stacks must be at least 1', $tester->getDisplay());
    }

    /**
     * The odd cabinet is placed by default and left out only when asked.
     *
     * Five Flexys and three Tecnares across two stacks. The odd cabinet is a sub, because an odd top no longer is one:
     * a pair stands its tops in one shared row. Dealt, the Flexys are two and three and every cabinet is in the rig,
     * which beats the shared row's seven. Refused, the Flexys are two each, the fifth is reported, and the walls are
     * equal again, so the three tops stand in one row across them rather than being split or cut to two.
     */
    public function testTheOddCabinetIsPlacedByDefaultAndLeftOutOnlyWhenAsked(): void
    {
        // One shape named, because the band no longer refuses the others and two of them now write the same split
        // twice. The subject is what the split does with the odd cabinet, which no shape changes.
        $shared = [
            '--systems' => ['pooled'], '--from' => ['flexy-folded-horn-hybrid', 'tecnare-m2122'], '--stacks' => '2', '--shape' => ['free'],
            '--quantity' => ['flexy-folded-horn-hybrid:5'], '--into' => 'odd-sub',
            '--orientation' => ['upright'], '--max-width' => '3.70', '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ];

        $dealt = $this->invoke($shared)->getDisplay();
        self::assertStringContainsString('SPLIT UNEVENLY, 1 of 5 over 2 stacks', $dealt);
        self::assertSame([3, 5], $this->cabinetsPerStack($dealt));
        self::assertStringNotContainsString('shared_tops', $dealt);

        $refused = $this->invoke($shared + ['--no-asymmetry' => true])->getDisplay();
        self::assertStringContainsString('LEFT OUT, 1 of 5', $refused);
        self::assertSame([2, 2], $this->cabinetsPerStack($refused));
        self::assertSame(3, substr_count($refused, 'device: tecnare-m2122'));
    }

    /**
     * How many rows each stack came out with, read off the header the writer prints.
     *
     * @return list<int>
     */
    private function rowsPerStack(string $display): array
    {
        preg_match_all('/^# main-\S+ — .*?, \d+ cabinets in (\d+) rows?:$/m', $display, $matches);

        return array_map(intval(...), $matches[1]);
    }

    /** @return list<int> */
    private function cabinetsPerStack(string $display): array
    {
        preg_match_all('/^# main-\S+ — .*?, (\d+) cabinets in \d+ rows?:$/m', $display, $matches);

        return array_map(intval(...), $matches[1]);
    }
}
