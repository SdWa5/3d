<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Spec\DeviceSpec;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds spec fixtures. The base array is a valid spec, so a test only states the one thing it
 * cares about — which keeps it obvious what each case is actually about.
 */
final class SpecFactory
{
    /**
     * @param array<string, mixed> $overrides merged over the valid base, one level deep
     * @return array<string, mixed>
     */
    public static function specArray(array $overrides = []): array
    {
        $base = [
            'id' => 'top-a',
            'name' => 'Top A',
            'category' => 'speaker',
            'owner' => 'sdwa5',
            'subtype' => 'top',
            'quantity' => 2,
            'build' => 'self-built',
            'clone_of' => [
                'manufacturer' => 'Acme',
                'model' => 'X1',
                'reference' => 'datasheet',
                'url' => null,
            ],
            'provenance' => 'datasheet',
            'geometry' => [
                'shape' => 'box',
                'dimensions_m' => ['width' => 0.8, 'height' => 0.6, 'depth' => 0.45],
                'origin' => 'bottom-center',
                'chamfer_m' => 0.01,
            ],
            'appearance' => [
                'color' => '#111111',
                'grille' => ['inset_m' => 0.012, 'color' => '#0a0a0a'],
            ],
            'physical' => [
                'weight_kg' => 34.0,
                'handles' => ['left', 'right'],
            ],
            'rigging' => ['flyable' => false, 'points' => []],
            'audio' => [
                'coverage_deg' => ['horizontal' => 90, 'vertical' => 60],
                'drivers' => [['size_in' => 15, 'type' => 'woofer', 'count' => 1]],
            ],
        ];

        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = array_replace($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function spec(array $overrides = [], ?string $sourcePath = null): DeviceSpec
    {
        $data = self::specArray($overrides);
        $path = $sourcePath ?? sprintf('/specs/speakers/%s.yaml', $data['id']);

        return DeviceSpec::fromArray($data, $path);
    }

    /**
     * Writes a spec as YAML into $dir, named after its id so the filename rule is satisfied.
     *
     * @param array<string, mixed> $overrides
     */
    public static function writeYaml(string $dir, array $overrides = []): string
    {
        $data = self::specArray($overrides);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $file = $dir.'/'.$data['id'].'.yaml';
        file_put_contents($file, Yaml::dump($data, 6));

        return $file;
    }

    /**
     * Throwaway directory under the system temp dir, removed by removeDir().
     */
    public static function tempDir(string $prefix = 'sdwa5-3d-'): string
    {
        $dir = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);

        return $dir;
    }

    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
