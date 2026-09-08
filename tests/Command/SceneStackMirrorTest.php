<?php

declare(strict_types=1);

namespace App\Tests\Command;

/**
 * `scene:stack` — the mirror-style and orientation axes.
 *
 * Both axes exist because a cabinet that is not symmetrical measures differently once it is turned, and every
 * width rule in the solver is a width *after* the turn. So these are the tests that would still pass if the
 * geometry were wrong and the labels right, which is why each one asserts a measurement rather than a name.
 *
 * The mirror axis is only swept where something is actually rolled, and a single stack is never mirrored —
 * two facts that keep the sweep from writing the same rig under two names.
 */
final class SceneStackMirrorTest extends SceneStackTestCase
{
    /**
     * The mirror-style axis is only swept where the orientation actually rolls something.
     *
     * {@see \App\Scene\Tier::mirrored} acts only on segments lying on a quarter turn, so with nothing rolled `centred`
     * comes out byte-identical to `alternate`. Sweeping the two axes independently made a third of every candidate a
     * duplicate by construction: 66 `centred` candidates, 0 written, 18 of them recognised as duplicates afterwards and
     * the other 48 refused on the same height and support grounds as their twin. Paired, the vacuous combinations cannot
     * be expressed at all.
     *
     * Asserted on the *ids offered*, not on the files written, because the point is the candidate that is never built
     * rather than the scene that was never any different. An id carries no orientation suffix only when it is `upright`,
     * so "every style suffix sits beside an orientation suffix" is the whole invariant.
     */
    public function testTheMirrorStyleAxisIsSweptOnlyWhereSomethingIsRolled(): void
    {
        // One alignment and one shape, which neither the orientation nor the mirror style depends on. The bare sweep
        // asserts the same thing eight times over, and `testTheBareCommandWritesScenesAcrossOwnersAndStackCounts` is
        // where that whole run is paid for once.
        $display = $this->invoke([
            '--low-end' => ['low'], '--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid'],
        ])->getDisplay();

        preg_match_all('/^\s*(?:skipped|id:)\s*(\S+)/m', $display, $matches);
        self::assertNotSame([], $matches[1]);

        // **Every axis in an id is a fixed-width field padded with dashes**, so `turned` reads as `turned--` and
        // `centred` as `centred---`. Collapsing runs of dashes first is what keeps this test about the pairing of two
        // axes rather than about how wide their columns happen to be — matching `-turned-centred-` literally pinned
        // the padding by accident and broke here the moment it arrived.
        $ids = array_map(static fn (string $id): string => (string) preg_replace('/-{2,}/', '-', $id), $matches[1]);
        $collapsed = (string) preg_replace('/-{2,}/', '-', $display);

        $vacuous = array_values(array_filter(
            $ids,
            static fn (string $id): bool => (str_contains($id, '-centred') || str_contains($id, '-column'))
                && !str_contains($id, '-turned-') && !str_contains($id, '-mixed-'),
        ));

        self::assertSame([], $vacuous, 'a mirror style was offered with nothing rolled to apply it to');

        // And the pairing is not merely absent — the rolled orientations do get all three styles.
        self::assertStringContainsString('-turned-centred-', $collapsed);
        self::assertStringContainsString('-turned-column-', $collapsed);
    }

    /**
     * An explicit `--mirror-style=centred` is honoured even with nothing rolled, because the caller asked for it by
     * name. Only the *default* narrows — a stated option is never second-guessed.
     */
    public function testAnExplicitCentredStyleIsHonouredWithNothingRolled(): void
    {
        $display = $this->invoke([
            '--low-end' => ['low'], '--dry-run' => true, '--orientation' => ['upright'], '--mirror-style' => ['centred'],
            '--align' => ['center'], '--shape' => ['pyramid'],
        ])->getDisplay();

        self::assertStringContainsString('-centred-', $display);
    }

    /**
     * **Laying the subs down is the largest single lever the sweep has**, and the tops stay standing whatever it does.
     *
     * Pinned as a property of the ids offered rather than as a scene count, which would be a second copy of whatever the
     * inventory currently happens to build. The two claims are the ones the axis exists for: a `-turned-` candidate is
     * offered at all, and no orientation ever puts a top on its side.
     */
    public function testTheSweepOffersTurnedRigsAndNeverRollsATop(): void
    {
        $display = $this->invoke(['--low-end' => ['low'], '--dry-run' => true, '--align' => ['center'], '--shape' => ['pyramid']])->getDisplay();

        self::assertStringContainsString('-turned-', $display);
        self::assertStringContainsString('-mixed-', $display);

        // Every rolled segment the writer names, against the tops there are. A top appears in these scenes constantly;
        // what may never appear is a top with a roll on it.
        // Unanchored, because a mixed row names several segments on one comment line and every one of them counts.
        preg_match_all('/(\S+) rolled \d+°/', $display, $rolled);
        foreach (array_unique($rolled[1]) as $id) {
            self::assertContains($id, [
                'flexy-folded-horn-hybrid', 'skram', 'iq-sub', 'nuke', 'wall-bass', 'mid-bass',
                'achenbach-18',
            ], $id.' is a top and was rolled');
        }
        self::assertNotSame([], $rolled[1], 'nothing was rolled at all, so the assertion above proves nothing');
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
            '--max-width' => '3.70', '--stacks' => '2', '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
            // **The collective's own gear, named.** This used to pass no `--from` at all, which means every speaker in
            // the repository — two sound systems in one stack, and a rig the sub height band refuses. Naming the gear
            // that the two `--roll-mirror` cabinets actually belong to makes it a rig that ships: 2.392 m of subs on a
            // 2.0 m interface, mirrored, which is what this test is about.
            '--systems' => ['pooled'], '--from' => self::OWN_GEAR,
            '--shape' => ['free'], '--mirror-style' => ['alternate'],
            '--roll-mirror' => ['skram', 'flexy-folded-horn-hybrid'],
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('mirror: true', $output);
        self::assertSame(1, substr_count($output, 'mirror: true'), 'only the earlier stack of the pair flips');

        // A ROLLED ROW READS REVERSED in the mirrored stack — 2 turned one way and 1 the other becomes 1 and 2.
        // This used to be pinned on the tops row instead, as "the 2-way ends up on the inner side of each stack",
        // and that was pinning the *fill order* rather than the mirroring: which device lands in the tops row moved
        // the moment the fill order changed to put the heavy GMSS subs on the floor, and the mirroring it was meant
        // to be testing had not changed at all. A rolled row is the better evidence because its handedness is the
        // thing `mirror` actually reverses.
        // Read on the mixed bottom row, which is the same row in both stacks and differs only in handedness: the
        // rolled SKRAM between two rolled Flexys faces one way in stack 1 and the other in its mirror image.
        self::assertStringContainsString(
            '1× flexy-folded-horn-hybrid rolled 270° + 1× skram rolled 270° + 1× flexy-folded-horn-hybrid rolled 90°',
            $output,
        );
        self::assertStringContainsString(
            '1× flexy-folded-horn-hybrid rolled 270° + 1× skram rolled 90° + 1× flexy-folded-horn-hybrid rolled 90°',
            $output,
        );

        // And the SKRAM is handed the other way in the mirrored stack.
        self::assertStringContainsString('1× skram rolled 270°', $output);
        self::assertStringContainsString('1× skram rolled 90°', $output);
    }

    /**
     * A single stack has no pair to mirror against, so it never claims one — at any orientation.
     *
     * Asserted on `mirror: true` rather than on `mirror:`, which is the key this test means and not merely a shorter
     * way of writing it. A turned rig states `roll_mirror: 90.0` on every rolled cabinet, and that ends in the same
     * seven characters — so the loose form passed only for as long as nothing was ever rolled, and would have failed on
     * a correct stack the moment one was.
     */
    public function testASingleStackIsNeverMirrored(): void
    {
        $tester = $this->invoke([
            '--max-width' => '3.70', '--systems' => ['pooled'], '--stacks' => '1', '--from' => self::STACKABLE, '--align' => ['center'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertStringNotContainsString('mirror: true', $tester->getDisplay());
    }

    /** An unknown `--orientation` names the values there are rather than falling back to one of them. */
    public function testAnUnknownOrientationIsRefusedAndNamesTheAllowedValues(): void
    {
        $tester = $this->invoke([
            '--from' => self::OWN_GEAR, '--orientation' => ['sideways'], '--low-end' => ['low'], '--dry-run' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("--orientation: unknown value 'sideways'", $tester->getDisplay());

        // Whitespace collapsed, because the console wraps the block and puts the last value on the next line.
        $wrapped = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('allowed: upright, turned, mixed', $wrapped);
    }

    /**
     * A recorded command line names the **mode**, not the cabinets it resolved to.
     *
     * That is what keeps a replay correct across a spec change: `--orientation=turned` means "every sub", so a sub
     * measured tomorrow joins the rig the file describes, where a frozen list would rebuild yesterday's. The stated form
     * is only recorded where it is what the caller actually said.
     *
     * Read off the shipped files rather than from a run, the same way the stage-width test is: the recorded line is what
     * `build:all` replays, so a file that failed to carry the mode is the actual defect.
     *
     * **Asserted across the whole set rather than on two named files.** Naming this test's evidence by file name broke it
     * three times for reasons that had nothing to do with orientation, once per change to the id shape. The rule it is
     * really about holds for every generated scene, so every generated scene is where it is checked.
     */
    public function testARecordedLineNamesTheOrientationRatherThanTheCabinets(): void
    {
        $turned = 0;
        $upright = 0;
        foreach (self::generatedScenes() as $file) {
            $yaml = (string) file_get_contents($file);
            $name = basename($file);

            if (str_contains($yaml, '--orientation=turned')) {
                ++$turned;
                // The mode is recorded, and the cabinets it resolved to live in the stack itself, which is where a
                // re-solve reads them from.
                self::assertStringNotContainsString('--roll-mirror=', $yaml, $name);
                self::assertStringContainsString('roll_mirror: 90.0', $yaml, $name);
            }

            if (str_contains($yaml, '--orientation=upright')) {
                ++$upright;
                self::assertStringNotContainsString('roll_mirror', $yaml, $name);
            }
        }

        // Both modes are actually represented, so a sweep that stopped writing one of them fails here rather than
        // passing an assertion loop that never ran.
        self::assertGreaterThan(0, $turned, 'no shipped scene records --orientation=turned');
        self::assertGreaterThan(0, $upright, 'no shipped scene records --orientation=upright');
    }
}
