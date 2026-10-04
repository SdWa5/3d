<?php

declare(strict_types=1);

namespace App\Scene;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * What one generated scene file says about itself: its verdict, the rows it deals and how far its subs miss.
 *
 * **Read off the file rather than recompiled**, because the question {@see GeneratedSceneDiff} answers is what a
 * regeneration changed, and the files are what was regenerated. The header is written by {@see StackSceneWriter},
 * so the parsing here follows its lines. The verdict comes from the file name, the rows from the numbered lines
 * under each block, and the sub heights from the "Subs reach" line under each block.
 */
final class GeneratedHeader
{
    private const VERDICT = '/-(possible|impossible)\.yaml$/';

    private const ROW = '/^#\s+\d+\s{2}(.+?)\s+[\d.]+ m wide$/';

    private const SUBS = '/^# Subs reach ([\d.]+) m against a ([\d.]+) m interface\./';

    /**
     * @param string $key the path with its verdict cut off, which names the same rig on both sides of a rename
     * @param list<string> $rows every dealt row, in file order, with the width left out
     * @param list<float> $misses per stack, how far its subs stand from its target height, up or down
     */
    public function __construct(
        public readonly string $key,
        public readonly string $folder,
        public readonly bool $possible,
        public readonly array $rows,
        public readonly array $misses,
    ) {
    }

    /**
     * Null for a file that is not a generated rig, which is one without a verdict in its name.
     *
     * @param string $path relative to `scenes/generated/`, so the folder is everything before the last slash
     */
    public static function parse(string $path, string $text): ?self
    {
        if (1 !== preg_match(self::VERDICT, $path, $verdict)) {
            return null;
        }

        $rows = [];
        $subs = [];
        foreach (explode("\n", $text) as $line) {
            if (!str_starts_with($line, '#')) {
                break;
            }
            if (1 === preg_match(self::ROW, $line, $row)) {
                $rows[] = $row[1];
            } elseif (1 === preg_match(self::SUBS, $line, $reach)) {
                $subs[] = (float) $reach[1];
            }
        }

        $slash = strrpos($path, '/');

        return new self(
            key: (string) preg_replace(self::VERDICT, '', $path),
            folder: false === $slash ? '.' : substr($path, 0, $slash),
            possible: 'possible' === $verdict[1],
            rows: $rows,
            misses: self::misses($subs, self::targets($text)),
        );
    }

    /** The worst stack's miss, or 0 for a file whose header names no sub height. */
    public function worstMissM(): float
    {
        return [] === $this->misses ? 0.0 : max($this->misses);
    }

    /**
     * Each stack's target sub height, in the order the placements stand in the file.
     *
     * @return list<float>|null null when the body cannot be read, so nothing is matched up against the header
     */
    private static function targets(string $text): ?array
    {
        try {
            $data = Yaml::parse($text);
        } catch (ParseException) {
            return null;
        }
        if (!is_array($data) || !is_array($data['placements'] ?? null)) {
            return null;
        }

        $targets = [];
        foreach ($data['placements'] as $placement) {
            if (is_array($placement) && is_array($placement['stack'] ?? null)) {
                $target = $placement['stack']['target_sub_height_m'] ?? Stack::DEFAULT_TARGET_SUB_HEIGHT_M;
                $targets[] = is_numeric($target) ? (float) $target : Stack::DEFAULT_TARGET_SUB_HEIGHT_M;
            }
        }

        return $targets;
    }

    /**
     * @param list<float> $subs
     * @param list<float>|null $targets
     *
     * @return list<float>
     */
    private static function misses(array $subs, ?array $targets): array
    {
        // A header and a body that disagree on how many stacks there are is a file somebody edited, and pairing
        // them up anyway would report a miss against the wrong stack's target.
        if (null === $targets || count($subs) !== count($targets)) {
            return [];
        }

        return array_map(static fn (float $sub, float $target): float => abs($sub - $target), $subs, $targets);
    }
}
