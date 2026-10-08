<?php

declare(strict_types=1);

namespace App\Tests\Standalone;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The committed models under `standalone/`, read straight from their .glb.
 *
 * They are the one binary output this repository keeps, built by hand from `blender/standalone/`, so nothing else
 * would notice a rebuild that drifted from its declared size, a model that lost its metadata, or a drawing that was
 * only ours to trace from ending up inside a published file. Mirrors `tools/check-glb.py`, without needing Python.
 */
final class StandaloneModelsTest extends TestCase
{
    private const TOLERANCE_M = 1e-4;

    /**
     * @return iterable<string, array{string}>
     */
    public static function models(): iterable
    {
        foreach (glob(self::root().'/*', \GLOB_ONLYDIR) ?: [] as $dir) {
            yield basename($dir) => [basename($dir)];
        }
    }

    public function testThereAreModelsToCheck(): void
    {
        self::assertNotEmpty(iterator_to_array(self::models()), 'standalone/ holds no model folder');
    }

    #[DataProvider('models')]
    public function testEachModelShipsItsBlendGlbAndAPreview(string $id): void
    {
        foreach (["$id.blend", "$id.glb", 'preview-closed.png'] as $file) {
            self::assertFileExists(self::root()."/$id/$file");
        }
    }

    #[DataProvider('models')]
    public function testTheReadmeListsTheModel(string $id): void
    {
        self::assertStringContainsString("($id/", (string) file_get_contents(self::root().'/README.md'));
    }

    #[DataProvider('models')]
    public function testTheGlbNamesItselfAfterItsFolder(string $id): void
    {
        self::assertSame($id, self::metadata(self::gltf($id))['id']);
    }

    #[DataProvider('models')]
    public function testTheGlbIsItsDeclaredSizeAndStandsOnTheFloor(string $id): void
    {
        $gltf = self::gltf($id);
        $dims = self::metadata($gltf)['dimensions_m'];
        [$low, $high] = self::boundingBox($gltf);

        // glTF is Y-up: x is the width, y the height, z the depth.
        foreach (['width' => 0, 'height' => 1, 'depth' => 2] as $label => $axis) {
            self::assertEqualsWithDelta($dims[$label], $high[$axis] - $low[$axis], self::TOLERANCE_M, "$id $label");
        }
        self::assertEqualsWithDelta(0.0, $low[1], self::TOLERANCE_M, "$id does not stand on the floor");
        self::assertEqualsWithDelta(0.0, $low[0] + $high[0], self::TOLERANCE_M, "$id is not centred across");
        self::assertEqualsWithDelta(0.0, $low[2] + $high[2], self::TOLERANCE_M, "$id is not centred front to back");
    }

    #[DataProvider('models')]
    public function testNoNodeIsRotatedOrScaled(string $id): void
    {
        foreach (self::gltf($id)['nodes'] as $node) {
            self::assertArrayNotHasKey('rotation', $node, "$id: {$node['name']} is rotated");
            self::assertArrayNotHasKey('scale', $node, "$id: {$node['name']} is scaled");
        }
    }

    #[DataProvider('models')]
    public function testTheGlbCarriesNoImage(string $id): void
    {
        self::assertEmpty(self::gltf($id)['images'] ?? [], "$id carries an image, which may be a third-party drawing");
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2).'/standalone';
    }

    /**
     * @return array<string, mixed>
     */
    private static function gltf(string $id): array
    {
        $bytes = (string) file_get_contents(self::root()."/$id/$id.glb");
        $header = unpack('Vmagic/Vversion/Vlength/VchunkLength/VchunkType', $bytes);
        self::assertIsArray($header);
        self::assertSame(0x46546C67, $header['magic'], "$id.glb is not a GLB file");
        self::assertSame(2, $header['version']);
        self::assertSame(0x4E4F534A, $header['chunkType'], 'the first chunk is not JSON');

        return json_decode(substr($bytes, 20, $header['chunkLength']), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $gltf
     *
     * @return array<string, mixed>
     */
    private static function metadata(array $gltf): array
    {
        foreach ($gltf['nodes'] as $node) {
            if (isset($node['extras']['sdwa5_metadata'])) {
                return json_decode($node['extras']['sdwa5_metadata'], true, flags: \JSON_THROW_ON_ERROR);
            }
        }
        self::fail('no sdwa5_metadata in any node');
    }

    /**
     * Union of every POSITION accessor, offset by its node's translation, as `tools/check-glb.py` computes it.
     *
     * @param array<string, mixed> $gltf
     *
     * @return array{list<float>, list<float>}
     */
    private static function boundingBox(array $gltf): array
    {
        $low = [\INF, \INF, \INF];
        $high = [-\INF, -\INF, -\INF];
        foreach ($gltf['nodes'] as $node) {
            if (!isset($node['mesh'])) {
                continue;
            }
            $offset = $node['translation'] ?? [0.0, 0.0, 0.0];
            foreach ($gltf['meshes'][$node['mesh']]['primitives'] as $primitive) {
                $accessor = $gltf['accessors'][$primitive['attributes']['POSITION']];
                for ($axis = 0; $axis < 3; ++$axis) {
                    $low[$axis] = min($low[$axis], $accessor['min'][$axis] + $offset[$axis]);
                    $high[$axis] = max($high[$axis], $accessor['max'][$axis] + $offset[$axis]);
                }
            }
        }
        self::assertNotSame(\INF, $low[0], 'no mesh geometry');

        return [$low, $high];
    }
}
