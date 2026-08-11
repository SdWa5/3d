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
     * @param list<StackBlock> $blocks one solved stack each, left to right
     * @param array{float, float} $at where the whole arrangement is centred
     */
    public static function yaml(string $id, string $name, array $blocks, array $at, float $clearanceM): string
    {
        $lines = self::header($blocks, $clearanceM);

        $lines[] = sprintf('id: %s', $id);
        $lines[] = sprintf('name: %s', self::quote($name));
        $lines[] = '';
        $lines[] = 'focus:';
        $lines[] = '  far:';
        $lines[] = '    distance_m: 10.0';
        $lines[] = '    height_m: 1.8';
        $lines[] = '  near:';
        $lines[] = '    distance_m: 2.0';
        $lines[] = '    height_m: 1.8';
        $lines[] = '';
        $lines[] = 'placements:';

        $centres = self::centres($blocks, $at[0], $clearanceM);
        foreach ($blocks as $index => $block) {
            if (count($blocks) > 1) {
                $lines[] = sprintf('  # %s', $block->describe());
            }
            $lines[] = sprintf('  - id: %s', $block->placementId);
            $lines[] = sprintf('    at: [%s, %s]', self::number($centres[$index]), self::number($at[1]));
            $lines[] = '    aim: far                 # the TOP tiers only; subs fire straight ahead';

            if ($block->align !== null) {
                $lines[] = '    align:';
                $lines[] = sprintf('      mode: %s', $block->align->value);
            }

            $lines[] = '    stack:';
            foreach (self::constraints($block->stack) as $key => $value) {
                $lines[] = sprintf('      %s: %s', $key, self::number($value));
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
                $share = $count !== ($owned[$deviceId] ?? $count) ? $count : null;
                if ($roll === null && $share === null && $block->stack->entryFor($deviceId)?->aim === null) {
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
    private static function header(array $blocks, float $clearanceM): array
    {
        $lines = [
            '# Generated by `bin/console scene:stack` — edit it freely, it is an ordinary scene file.',
            '#',
            '# Nothing here says which cabinet goes in which row. The constraints below do, and the compiler',
            '# re-solves them every build, so the rig follows the specs when a cabinet is finally measured.',
            '#',
        ];

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

            $height = 0.0;
            $subHeight = 0.0;
            foreach ($block->tiers as $index => $tier) {
                $height += $tier->heightM();
                if ($tier->isSub()) {
                    $subHeight += $tier->heightM();
                }
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
                self::number(round($subHeight, 3)),
                self::number($block->stack->interfaceHeightM),
                self::number(round($height, 3)),
            );

            if ($block->align !== null) {
                $lines[] = sprintf(
                    '# `align: %s` spreads the top tier onto the edges of the row carrying it.',
                    $block->align->value,
                );
            }
            foreach ($block->omitted as $deviceId => $why) {
                foreach (self::wrap(sprintf('#   * LEFT OUT %s — %s', $deviceId, $why), 118) as $wrapped) {
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
        $constraints = ['interface_height_m' => $stack->interfaceHeightM, 'gap_m' => $stack->gapM];
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
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0.0' : (str_contains($formatted, '.') ? $formatted : $formatted.'.0');
    }

    private static function quote(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }
}
