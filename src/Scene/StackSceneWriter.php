<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Turns solved {@see Stack}s into a scene file somebody can read, edit and argue with.
 *
 * Hand-rolled rather than `Yaml::dump()`, and the reason is the comments. Every scene in this repository
 * explains itself — which cabinet went where and *why that number* — and a generated one that arrived as a
 * bare mapping would be the only magic file in the folder. So the header carries the solved tier table, the
 * widths, the interface height it reached and anything the solver warned about, which is what makes the
 * output reviewable instead of trusted.
 *
 * The body is emitted as `stack:` blocks rather than as expanded tiers, deliberately: the whole point of the
 * feature is that the rig follows the specs, so a generated scene has to re-solve when a cabinet is finally
 * measured rather than freeze today's answer into a list of rows.
 *
 * **More than one block** is how a rig per owner comes out — `sdwa5` and `sepp` each get their own stack,
 * standing side by side with air between them rather than merged into one pile.
 */
final class StackSceneWriter
{
    /**
     * The stacks laid out so the tall ones end up where the alignment wants them.
     *
     * **The same rule as the tops row, one level up.** {@see StackSolver::topRow} centres the long throw for mono and
     * {@see StackSolver::stereoTopRow} pushes it to the ends for stereo; a rig of several stacks is the same question
     * asked of whole stacks. Until now they came out in *solve* order — owner alphabetical, or the order the split
     * dealt them — and nothing ever looked at their heights, so `both-systems-per-owner` read `3.34 | 3.20 | 1.80`
     * with the tallest hard left. Seven of thirty multi-stack scenes were wrong that way.
     *
     * * **mono** — tallest in the middle, the rest alternating outward. `3.34 | 3.20 | 1.80` becomes
     *   `3.20 | 3.34 | 1.80`: the biggest pile carries the room from the centre and the small ones widen the coverage.
     * * **stereo** — tallest at the outer ends, working inward. The mirror, because the point of a stereo rig is the
     *   width of its image and the main clusters belong as far apart as the stage allows.
     *
     * **This improves symmetry and does not deliver it**, which is worth being plain about. Ordering can place the
     * tall stacks; it cannot make the two flanks *equal*, because that depends on the split giving each stack similar
     * contents. `--per-owner` puts three different systems side by side and no ordering makes those the same height.
     *
     * @param list<StackBlock> $blocks
     * @param list<string> $order system labels, left to right. Empty — or naming none of these blocks, which
     *     is what a system order does to a `pooled` rig — leaves the height rule below in charge
     * @return list<StackBlock>
     */
    public static function byHeight(array $blocks, LayoutMode $mode, array $order = []): array
    {
        $rank = array_flip(array_values($order));

        // **THE ORDER NAMES A SYSTEM, AND A LABEL CARRIES ITS STACK NUMBER TOO.** `--stacks=2` labels the blocks
        // `ours-1`, `ours-2`, `psl-1` and so on, so matching the label whole meant `--order=ours,psl,innschleife`
        // named nothing in any multi-stack rig and 533 of them silently kept the height rule — which mirrored each
        // system's pair about the centre and put `ours` in the *middle*, the opposite of a stated order that begins
        // with it. Ranking on the system part instead keeps a system's stacks adjacent, because equal ranks hold
        // their relative order under PHP's stable sort. Stated by the owner: strictly left to right, grouped.
        $rankOf = static function (string $label) use ($rank): ?int {
            return $rank[$label] ?? $rank[preg_replace('/-\d+$/', '', $label)] ?? null;
        };

        // **AN ORDER THAT NAMES NONE OF THESE BLOCKS IS NOT AN INSTRUCTION ABOUT THEM.** `--order` states where
        // *systems* go, and a `pooled` rig has no systems — its stacks are labelled `1`, `2`, `3`, so a system
        // order names nothing in it. Returning early on a non-empty order regardless put every such rig in *solve*
        // order and skipped the height rule entirely, which is a silent geometry change in the one mode the option
        // cannot be about: sweeping `next-event` with `--order=ours,psl,innschleife` moved 8 `pooled` ids, five of
        // them across the possible/impossible line. So the stated path is taken only when it has something to say.
        $named = false;
        foreach ($blocks as $block) {
            if ($rankOf($block->label) !== null) {
                $named = true;

                break;
            }
        }

        if ($named) {
            // **A STATED ORDER BEATS THE HEURISTIC, WHICH IS WHY IT IS CHECKED FIRST AND RETURNS.** Everything
            // below is a rule about where a *taller* stack reads best, and it is a good rule precisely because
            // nobody had said where the stacks go. Somebody saying so is a different kind of fact: the systems
            // stand in the order they were asked for, and a rig that came out `psl | innschleife | ours` because
            // Innschleife's wall grew 380 mm is not the rig anybody drew on a stage plan.
            //
            // Stable, and a block whose label the order does not name keeps its place at the end rather than
            // being dropped — naming two of three systems is a partial instruction, not a filter.
            $positions = [];
            foreach ($blocks as $index => $block) {
                $positions[$index] = $rankOf($block->label) ?? count($rank) + $index;
            }
            uksort($blocks, static fn (int $a, int $b): int => $positions[$a] <=> $positions[$b]);

            return array_values($blocks);
        }

        if (count($blocks) < 3 && $mode !== LayoutMode::Stereo) {
            // Two stacks have no middle to be in, and no mono ordering can tell them apart.
            return $blocks;
        }

        usort(
            $blocks,
            static fn (StackBlock $a, StackBlock $b): int => $b->subHeightM() <=> $a->subHeightM(),
        );

        // Tallest first in `$blocks`. Deal them alternately to build the shape the mode asks for: for mono the
        // tallest takes the middle and each next one goes to the shorter side, which comes out as a list read from
        // the centre outward and then flattened; for stereo the same deal read from the ends inward.
        $left = [];
        $right = [];
        foreach ($blocks as $position => $block) {
            $position % 2 === 0 ? $left[] = $block : $right[] = $block;
        }

        return $mode === LayoutMode::Stereo
            // Tallest at the ends: the tall half outward on the left, the rest inward, mirrored on the right.
            ? [...$left, ...array_reverse($right)]
            // Tallest central: shorter ones outboard on the left, tallest in the middle, the rest to the right.
            : [...array_reverse($right), ...$left];
    }

    /**
     * @param list<StackBlock> $blocks one solved stack each, left to right
     * @param array{float, float} $at where the whole arrangement is centred
     * @param array<string, int> $stated devices whose count came from a roster or `--quantity` rather than from
     *     their spec, which must be written into the file whatever the share works out to. See {@see yaml}
     * @param bool $perSystemFocus whether each block is its own sound system and therefore aims at its own focus
     *     rather than at the rig's — true for the separated values of {@see SystemSplit}, false for `pooled`
     */
    public static function yaml(
        string $id,
        string $name,
        array $blocks,
        array $at,
        float $clearanceM,
        string $command = '',
        array $stated = [],
        bool $perSystemFocus = false,
    ): string {
        $lines = self::header($blocks, $clearanceM, $command);

        $lines[] = sprintf('id: %s', $id);
        $lines[] = sprintf('name: %s', self::quote($name));
        $lines[] = '';
        $lines[] = 'focus:';
        foreach (self::focusPoints() as $name => $focus) {
            $lines[] = sprintf('  %s:', $name);
            $lines[] = sprintf('    distance_m: %s', self::number($focus->distanceM));
            $lines[] = sprintf('    height_m: %s', self::number($focus->heightM));
        }
        $lines[] = '';
        $lines[] = 'placements:';

        $centres = self::centres($blocks, $at[0], $clearanceM);
        foreach ($blocks as $index => $block) {
            if (count($blocks) > 1) {
                $lines[] = sprintf('  # %s', $block->describe());
            }
            $lines[] = sprintf('  - id: %s', $block->placementId);
            $lines[] = sprintf('    at: [%s, %s]', self::number($centres[$index]), self::number($at[1]));
            $lines[] = sprintf('    aim: %s                 # the TOP tiers only; subs fire straight ahead', self::AIM);

            if ($perSystemFocus) {
                // **ONE FOCUS PER SYSTEM, BECAUSE THREE SYSTEMS SIDE BY SIDE DO NOT SHARE ONE.** The scene's own
                // focus is measured from the *rig's* front centre, so without this the outer walls toe inward at a
                // point in front of the middle one — three systems covering one patch of floor rather than each
                // covering the room in front of it. Written out with the same two points the scene carries, so the
                // numbers mean what they always meant and only what they are measured from changes.
                //
                // Only where the stacks **are** systems. A pooled rig split into two or three is one system in
                // several piles, and those do share a focus: they are aimed as one cluster and the near-fills of
                // one belong to the other.
                $lines[] = '    focus:';
                foreach (self::focusPoints() as $name => $focus) {
                    $lines[] = sprintf('      %s:', $name);
                    $lines[] = sprintf('        distance_m: %s', self::number($focus->distanceM));
                    $lines[] = sprintf('        height_m: %s', self::number($focus->heightM));
                }
            }

            if ($block->align !== null) {
                $lines[] = '    align:';
                $lines[] = sprintf('      mode: %s', $block->align->value);
            }

            $lines[] = '    stack:';
            foreach (self::constraints($block->stack) as $key => $value) {
                $lines[] = sprintf('      %s: %s', $key, self::number($value));
            }
            if ($block->stack->mirrorStyle !== MirrorStyle::Alternate) {
                // Same reason `shape` is written: the `stack:` block is re-solved on every build, so a style left out
                // comes back as the default and the variant rebuilds as its sibling.
                $lines[] = sprintf('      mirror_style: %s', $block->stack->mirrorStyle->value);
            }
            if ($block->stack->lowEnd !== LowEndBias::Low) {
                // Written only when it is not the default, like every other key here — and written it must be,
                // because the `stack:` block is re-solved on every build and a bias left out comes back as `low`.
                // The two values are two rigs: `central` puts the SKRAMs one above the other on the centre line
                // where `low` puts both of them on the floor.
                $lines[] = sprintf('      low_end: %s', $block->stack->lowEnd->value);
            }
            if ($block->stack->shape !== StackShape::Free) {
                // Written only when it is not the default, like every other key here — but written it must be. The
                // `stack:` block is re-solved on every build, and a shape left out of the file comes back as `free`:
                // the pyramid variant would rebuild as its own sibling, which is exactly what made the two
                // indistinguishable until this line existed.
                $lines[] = sprintf('      shape: %s', $block->stack->shape->value);
            }
            if ($block->stack->mirror) {
                // Only when true, the same "say something only when there is something to say" the entry keys
                // follow — and it has to be said at all because the tiers are re-solved on every build.
                $lines[] = '      mirror: true             # the mirror image of stack '.$block->label;
            }
            $lines[] = '      from:';
            $counts = $block->counts();
            $owned = $block->owned();
            foreach ($block->from as $deviceId) {
                // Everything the solve decided has to be written, because a `stack:` block is **re-solved on
                // every build**. A roll left out comes back upright; a share left out comes back as the whole
                // inventory, which is how two stacks 0.5 m apart ended up 561 mm inside each other.
                $roll = $block->stack->entryFor($deviceId)?->rollMirror;
                $count = $counts[$deviceId] ?? 0;

                if ($count < 1) {
                    // Not in the solve at all — listing it would invite the re-solve to place it after all.
                    continue;
                }

                // The mapping form only when there is something to say, so an ordinary rig keeps the shorthand.
                //
                // **A COUNT THAT CAME FROM A ROSTER IS ALWAYS SOMETHING TO SAY, AND LEAVING IT OUT WROTE FILES
                // THAT REBUILT INTO A DIFFERENT RIG.** `owned()` reports the quantity of the specs this run was
                // given, and a roster hands the command specs it has already rewritten — so twelve of twelve ESX
                // looked like the whole inventory and the shorthand was used. Loading that file back reads the
                // spec on disk, which says six, and the compiler dealt out a rig with three fewer rows under a
                // top row laid out for twelve. Two of the sixteen PSL scenes came out with a floating cabinet
                // that way, under a `-possible` name, because the writer had checked a different rig from the one
                // it wrote. The file has to be self-contained: whatever the spec says tomorrow, this scene is the
                // rig somebody actually asked for.
                $share = $count !== ($owned[$deviceId] ?? $count) || isset($stated[$deviceId]) ? $count : null;
                // `mix_with` belongs in the same list as the roll and the share: it is part of what the solve
                // decided, and a mix left out comes back as separate tiers — which is a taller stack, quietly.
                $mixWith = $block->stack->entryFor($deviceId)?->mixWith ?? [];
                if ($roll === null && $share === null && $mixWith === [] && $block->stack->entryFor($deviceId)?->aim === null) {
                    $lines[] = sprintf('        - %s', $deviceId);
                    continue;
                }

                $lines[] = sprintf('        - device: %s', $deviceId);
                if ($share !== null) {
                    $lines[] = sprintf('          count: %d', $share);
                }
                if ($roll !== null) {
                    $lines[] = sprintf('          roll_mirror: %s', self::number($roll));
                }
                if ($mixWith !== []) {
                    $lines[] = sprintf('          mix_with: [ %s ]', implode(', ', $mixWith));
                }
                $aim = $block->stack->entryFor($deviceId)?->aim;
                if ($aim !== null) {
                    $lines[] = sprintf('          aim: %s', $aim);
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Where each block's centre line sits: laid left to right on their solved widths, with the whole
     * arrangement centred on `$centreX`.
     *
     * Widths come from each block's **widest tier**, which is the one thing a neighbour has to clear. Using
     * the bottom row instead would be wrong for any rig whose widest row is not its lowest — an over-booked
     * Achenbach row is 3.700 m against a 3.646 m sub wall.
     *
     * @param list<StackBlock> $blocks
     * @return list<float>
     */
    /**
     * The name a generated scene's top tiers aim at.
     */
    public const AIM = 'far';

    /**
     * The two focus points every generated scene carries.
     *
     * **Shared rather than written twice**, because the seating check GEO-11 added has to judge a candidate under the
     * aim the file will actually state. A probe that did not know about `far` refuses every arrangement outright as
     * "unknown focus", and one that knew the name but not the distance would aim the tops somewhere else and measure
     * a different rig. Two copies of these numbers is how that drifts, so there is one.
     *
     * @return array<string, Focus>
     */
    public static function focusPoints(): array
    {
        return [
            self::AIM => new Focus(distanceM: 10.0, heightM: 1.8),
            'near' => new Focus(distanceM: 2.0, heightM: 1.8),
        ];
    }

    public static function centres(array $blocks, float $centreX, float $clearanceM): array
    {
        $widths = array_map(static fn (StackBlock $block): float => $block->widthM(), $blocks);
        $total = array_sum($widths) + max(0, count($blocks) - 1) * $clearanceM;

        $centres = [];
        $x = $centreX - $total / 2;
        foreach ($widths as $width) {
            $centres[] = $x + $width / 2;
            $x += $width + $clearanceM;
        }

        return $centres;
    }

    /**
     * The explaining comment block — the whole reason this is not `Yaml::dump()`.
     *
     * @param list<StackBlock> $blocks
     * @return list<string>
     */
    private static function header(array $blocks, float $clearanceM, string $command = ''): array
    {
        $lines = [
            '# Generated by `bin/console scene:stack` — edit it freely, it is an ordinary scene file.',
            '#',
            '# Nothing here says which cabinet goes in which row. The constraints below do, and the compiler',
            '# re-solves them every build, so the rig follows the specs when a cabinet is finally measured.',
            '#',
        ];

        // THE COMMAND THAT MADE IT, written out in full and runnable. A generated file that cannot say how it was
        // generated has to be reverse-engineered from its own contents before it can be regenerated — which is
        // exactly what happened when the GMSS cabinets were re-measured and eleven scenes needed rebuilding. The
        // options are reconstructed from what the command actually used rather than copied off the command line,
        // so a default that changes is reflected here instead of being silently inherited.
        if ($command !== '') {
            $lines[] = '# Regenerate it with:';
            $lines[] = '#';
            foreach (self::wrap('#   '.$command, 116) as $line) {
                $lines[] = $line;
            }
            $lines[] = '#';
        }

        if (count($blocks) > 1) {
            $lines[] = sprintf(
                '# %d stacks side by side, with %s m of air between them.',
                count($blocks),
                self::number($clearanceM),
            );
            $lines[] = '#';
        }

        foreach ($blocks as $block) {
            $lines[] = count($blocks) > 1
                ? sprintf('# %s — %s:', $block->placementId, $block->describe())
                : '# What it deals out today:';
            $lines[] = '#';

            foreach ($block->tiers as $index => $tier) {
                $lines[] = sprintf(
                    '#   %d  %-70s %8s m wide',
                    $index + 1,
                    $tier->label(),
                    self::number(round($tier->widthM($block->stack->gapM), 4)),
                );
            }

            $lines[] = '#';
            $lines[] = sprintf(
                '# Subs reach %s m against a %s m interface. The stack is %s m of cabinet.',
                self::number(round($block->subHeightM(), 3)),
                self::number($block->stack->interfaceHeightM),
                self::number(round($block->heightM(), 3)),
            );

            if ($block->align !== null) {
                $lines[] = sprintf(
                    '# `align: %s` spreads the top tier onto the edges of the row carrying it.',
                    $block->align->value,
                );
            }
            foreach ($block->omitted as $deviceId => $why) {
                // The note carries its own verb: a device can be left out of the rig entirely, or kept whole in
                // one stack because it could not be split. Prefixing everything with "LEFT OUT" read as a
                // contradiction on the second.
                foreach (self::wrap(sprintf('#   * %s: %s', $deviceId, $why), 118) as $wrapped) {
                    $lines[] = $wrapped;
                }
            }
            foreach ($block->warnings as $warning) {
                foreach (self::wrap('#   * '.$warning, 118) as $wrapped) {
                    $lines[] = $wrapped;
                }
            }
            $lines[] = '#';
        }

        return $lines;
    }

    /**
     * @return array<string, float>
     */
    private static function constraints(Stack $stack): array
    {
        // `max_sub_height_m` sits with the interface height rather than with the widths, because the two are the
        // floor and the ceiling on one number and reading them apart from each other invites writing a
        // contradiction. Every constraint the command was given has to be written: the scene is re-solved on every
        // build, so a bound left out of the file is a bound the rig quietly stops answering to.
        $constraints = ['interface_height_m' => $stack->interfaceHeightM];
        if ($stack->maxSubHeightM !== null) {
            $constraints['max_sub_height_m'] = $stack->maxSubHeightM;
        }
        // **Only when it is not the default**, unlike the two bounds above. A target is a preference and its default is
        // the one every rig is solved against, so writing it into all 149 files would state a number that says nothing
        // and would have to be rewritten in every one of them the day the default moves. Stated, it means somebody
        // asked for a different aim and the re-solve has to keep it.
        if (abs($stack->targetSubHeightM - Stack::DEFAULT_TARGET_SUB_HEIGHT_M) > 1e-9) {
            $constraints['target_sub_height_m'] = $stack->targetSubHeightM;
        }
        $constraints['gap_m'] = $stack->gapM;
        // **The one solve input this writer used to leave out, and it changed the rig.** A solo stack is solved with
        // `INF` here and a stack in a rig with null, and the file could say neither — so every solo scene rebuilt with
        // sliding forbidden and answered a different question from the one its own header comment reported. It is
        // written whenever it is stated rather than only when it is not the default, because both values are
        // meaningful and null is not a missing number but the "may not move" answer.
        if ($stack->slideSlackM !== null) {
            $constraints['slide_slack_m'] = $stack->slideSlackM;
        }

        foreach (['max_width_m' => $stack->maxWidthM, 'min_width_m' => $stack->minWidthM, 'max_height_m' => $stack->maxHeightM] as $key => $value) {
            if ($value !== null) {
                $constraints = [$key => $value] + $constraints;
            }
        }

        return $constraints;
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, int $width): array
    {
        $wrapped = explode("\n", wordwrap($text, $width, "\n"));

        return array_map(
            static fn (int $index, string $line): string => $index === 0 ? $line : '#     '.ltrim($line, '# '),
            array_keys($wrapped),
            $wrapped,
        );
    }

    /** Numbers that read like the ones in a hand-written scene: `0.02`, `3.7`, `2.0` — never `2.0000000001`. */
    private static function number(float $value): string
    {
        // `sprintf('%.4F', INF)` is the string `INF`, which YAML reads as an ordinary word rather than as a number, so
        // the file would fail to load on the one key that needs it. `.inf` is the YAML spelling and Symfony parses it
        // back to a float INF. See {@see Stack::$slideSlackM}, the only value here that is ever unbounded.
        if (is_infinite($value)) {
            return $value > 0 ? '.inf' : '-.inf';
        }

        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0.0' : (str_contains($formatted, '.') ? $formatted : $formatted.'.0');
    }

    private static function quote(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }
}
