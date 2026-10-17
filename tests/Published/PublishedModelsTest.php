<?php

declare(strict_types=1);

namespace App\Tests\Published;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The committed spec builds under `published/`, read straight from their .glb.
 *
 * Printed build plans link to these files by URL, so a missing file, a renamed one or a model that lost its
 * metadata would break a QR code on paper without anything else noticing.
 */
final class PublishedModelsTest extends TestCase
{
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
        self::assertNotEmpty(iterator_to_array(self::models()), 'published/ holds no model folder');
    }

    #[DataProvider('models')]
    public function testEachModelShipsItsGlbAndAPreview(string $id): void
    {
        foreach (["$id.glb", 'preview-closed.png'] as $file) {
            self::assertFileExists(self::root()."/$id/$file");
        }
    }

    #[DataProvider('models')]
    public function testEachModelHasASpec(string $id): void
    {
        self::assertNotEmpty(glob(\dirname(self::root())."/specs/*/*/$id.yaml"), "$id has no spec");
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
    public function testTheGlbCarriesNoImage(string $id): void
    {
        self::assertEmpty(self::gltf($id)['images'] ?? [], "$id carries an image, which may be a third-party photograph");
    }

    #[DataProvider('models')]
    public function testAMeshOverrideLiesInsideItsPackage(string $id): void
    {
        $spec = Yaml::parseFile(glob(\dirname(self::root())."/specs/*/*/$id.yaml")[0]);
        if (!isset($spec['mesh_override']['path'])) {
            self::markTestSkipped("$id is built without a mesh_override");
        }
        $path = $spec['mesh_override']['path'];
        self::assertStringStartsWith("published/$id/", $path, 'a published model must build from a committed mesh');
        self::assertFileExists(\dirname(self::root())."/$path");
    }

    public function testTheFlexyPlansTheQrCodesPointAtExist(): void
    {
        foreach (['build-plan-rev-d-en.pdf', 'bauplan-rev-d-de.pdf'] as $file) {
            self::assertStringStartsWith('%PDF-', (string) file_get_contents(self::root()."/flexy-folded-horn-hybrid/$file", length: 5));
        }
    }

    public function testTheFlexyPlansCreditMrFlexySmpsVideo(): void
    {
        foreach (['build-plan-rev-d-en.pdf', 'bauplan-rev-d-de.pdf'] as $file) {
            self::assertStringContainsString('tzwWSL8TyKI', (string) file_get_contents(self::root()."/flexy-folded-horn-hybrid/$file"), $file);
        }
    }

    public function testTheFlexyCadShipsWithThePlans(): void
    {
        foreach (['subwoofer-v28.obj', 'subwoofer-v28.x', 'subwoofer-v28-both-side-walls.obj', 'flexy-1to10.stl', 'horn-extension.FCStd', 'horn-extension.xlsx'] as $file) {
            self::assertFileExists(self::root()."/flexy-folded-horn-hybrid/cad/$file");
            self::assertStringContainsString("`$file`", (string) file_get_contents(self::root().'/README.md'), "the README's cad/ table lacks $file");
        }
    }

    public function testTheFlexyCutListShipsWithThePlans(): void
    {
        $lines = file(self::root().'/flexy-folded-horn-hybrid/flexy-cutlist.csv', \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertSame('Length,Width,Qty,Material,Label,Enabled', $lines[0]);
        self::assertGreaterThan(1, \count($lines));
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2).'/published';
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
}
