<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * What a regeneration changed across the generated scenes, per folder.
 *
 * **0.136.1, 0.142.0 and 0.144.0 each counted this by hand off the git diff**, which is how three releases came to
 * report three differently shaped numbers. This counts the same things every time. Those are the verdicts on each
 * side, the rigs that changed verdict, the rigs that appeared or vanished, the rigs whose dealt rows changed, and
 * the summed and worst distance of the subs from their target height.
 *
 * A rig is matched across the two sides by its path with the verdict cut off, because a changed verdict renames the
 * file.
 */
final class GeneratedSceneDiff
{
    /**
     * @param array<string, GeneratedHeader> $before by path
     * @param array<string, GeneratedHeader> $after by path
     *
     * @return array<string, array{
     *     possible: array{int, int},
     *     impossible: array{int, int},
     *     to_possible: list<string>,
     *     to_impossible: list<string>,
     *     appeared: list<string>,
     *     vanished: list<string>,
     *     rows_changed: list<string>,
     *     miss_m: array{float, float},
     *     worst_miss_m: array{float, float},
     * }> by folder, sorted, holding only folders where something differs unless every folder is asked for
     */
    public static function compare(array $before, array $after, bool $everyFolder = false): array
    {
        $old = self::byKey($before);
        $new = self::byKey($after);

        $folders = [];
        foreach ([...array_values($old), ...array_values($new)] as $header) {
            $folders[$header->folder] = self::empty();
        }
        foreach ([[$old, 0], [$new, 1]] as [$side, $index]) {
            foreach ($side as $header) {
                $f = $folders[$header->folder];
                ++$f[$header->possible ? 'possible' : 'impossible'][$index];
                $f['miss_m'][$index] += array_sum($header->misses);
                $f['worst_miss_m'][$index] = max($f['worst_miss_m'][$index], $header->worstMissM());
                $folders[$header->folder] = $f;
            }
        }

        foreach ($new as $key => $header) {
            $was = $old[$key] ?? null;
            if (null === $was) {
                $folders[$header->folder]['appeared'][] = $key;
                continue;
            }
            if ($was->possible !== $header->possible) {
                $folders[$header->folder][$header->possible ? 'to_possible' : 'to_impossible'][] = $key;
            }
            if ($was->rows !== $header->rows) {
                $folders[$header->folder]['rows_changed'][] = $key;
            }
        }
        foreach ($old as $key => $header) {
            if (!isset($new[$key])) {
                $folders[$header->folder]['vanished'][] = $key;
            }
        }

        if (!$everyFolder) {
            $folders = array_filter($folders, self::differs(...));
        }
        ksort($folders);

        return $folders;
    }

    /**
     * @param array{
     *     possible: array{int, int},
     *     impossible: array{int, int},
     *     to_possible: list<string>,
     *     to_impossible: list<string>,
     *     appeared: list<string>,
     *     vanished: list<string>,
     *     rows_changed: list<string>,
     *     miss_m: array{float, float},
     *     worst_miss_m: array{float, float},
     * } $f
     */
    private static function differs(array $f): bool
    {
        return $f['possible'][0] !== $f['possible'][1]
            || $f['impossible'][0] !== $f['impossible'][1]
            || [] !== $f['to_possible'] || [] !== $f['to_impossible']
            || [] !== $f['appeared'] || [] !== $f['vanished'] || [] !== $f['rows_changed']
            || abs($f['miss_m'][0] - $f['miss_m'][1]) > 1e-9;
    }

    /**
     * @param array<string, GeneratedHeader> $headers
     *
     * @return array<string, GeneratedHeader>
     */
    private static function byKey(array $headers): array
    {
        $byKey = [];
        foreach ($headers as $header) {
            $byKey[$header->key] = $header;
        }

        return $byKey;
    }

    /**
     * @return array{
     *     possible: array{int, int},
     *     impossible: array{int, int},
     *     to_possible: list<string>,
     *     to_impossible: list<string>,
     *     appeared: list<string>,
     *     vanished: list<string>,
     *     rows_changed: list<string>,
     *     miss_m: array{float, float},
     *     worst_miss_m: array{float, float},
     * }
     */
    private static function empty(): array
    {
        return [
            'possible' => [0, 0],
            'impossible' => [0, 0],
            'to_possible' => [],
            'to_impossible' => [],
            'appeared' => [],
            'vanished' => [],
            'rows_changed' => [],
            'miss_m' => [0.0, 0.0],
            'worst_miss_m' => [0.0, 0.0],
        ];
    }
}
