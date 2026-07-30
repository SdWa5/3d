<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Spec\InvalidSpecException;
use App\Spec\Provenance;
use App\Spec\Shape;
use App\Spec\SpecLoader;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SpecLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = SpecFactory::tempDir();
    }

    protected function tearDown(): void
    {
        SpecFactory::removeDir($this->dir);
    }

    public function testLoadsAValidSpec(): void
    {
        $file = SpecFactory::writeYaml($this->dir.'/speakers');
        $spec = (new SpecLoader($this->dir))->load($file);

        self::assertSame('top-a', $spec->id);
        self::assertSame('speaker', $spec->category->value);
        self::assertSame(2, $spec->quantity);
        self::assertSame(0.8, $spec->dimensions->width);
        self::assertSame(34.0, $spec->weightKg);
        self::assertSame(Provenance::Datasheet, $spec->provenance->dimensions);
        self::assertSame(Provenance::Datasheet, $spec->provenance->weight);
        self::assertSame('Acme X1', $spec->originalLabel());
        self::assertSame(['left', 'right'], $spec->handles);
        self::assertCount(1, $spec->drivers);
        self::assertNotNull($spec->coverage);
        self::assertSame(90.0, $spec->coverage->horizontal);
    }

    public function testDefaultsShapeAndOriginWhenOmitted(): void
    {
        $file = SpecFactory::writeYaml($this->dir, [
            'geometry' => ['shape' => null, 'origin' => null, 'chamfer_m' => null],
        ]);
        $spec = (new SpecLoader($this->dir))->load($file);

        self::assertSame(Shape::Box, $spec->shape);
        self::assertSame('bottom-center', $spec->origin->value);
        self::assertSame(0.0, $spec->chamfer);
    }

    public function testFindsSpecsRecursivelyAndSorted(): void
    {
        SpecFactory::writeYaml($this->dir.'/speakers', ['id' => 'sub-a']);
        SpecFactory::writeYaml($this->dir.'/truss', ['id' => 'beam-a']);

        $files = (new SpecLoader($this->dir))->files();

        self::assertCount(2, $files);
        self::assertStringEndsWith('speakers/sub-a.yaml', $files[0]);
        self::assertStringEndsWith('truss/beam-a.yaml', $files[1]);
    }

    public function testMissingDirectoryYieldsNoFiles(): void
    {
        self::assertSame([], (new SpecLoader($this->dir.'/nope'))->files());
    }

    public function testThrowsOnMissingRequiredKey(): void
    {
        $data = SpecFactory::specArray();
        unset($data['physical']['weight_kg']);
        $file = $this->write('top-a.yaml', $data);

        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches('/physical\.weight_kg: expected a number/');
        (new SpecLoader($this->dir))->load($file);
    }

    public function testThrowsOnUnknownEnumValueAndListsTheAllowedOnes(): void
    {
        $file = $this->write('top-a.yaml', SpecFactory::specArray(['provenance' => 'guessed']));

        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches('/provenance: unknown value \'guessed\'.*measured/');
        (new SpecLoader($this->dir))->load($file);
    }

    public function testThrowsOnMalformedYaml(): void
    {
        $file = $this->dir.'/broken.yaml';
        file_put_contents($file, "id: [unclosed\nname: nope\n");

        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches('/invalid YAML/');
        (new SpecLoader($this->dir))->load($file);
    }

    public function testThrowsWhenTopLevelIsNotAMapping(): void
    {
        $file = $this->dir.'/list.yaml';
        file_put_contents($file, "- one\n- two\n");

        $this->expectException(InvalidSpecException::class);
        $this->expectExceptionMessageMatches('/mapping at the top level/');
        (new SpecLoader($this->dir))->load($file);
    }

    public function testLoadAllKeepsGoodSpecsAndReportsBrokenOnes(): void
    {
        SpecFactory::writeYaml($this->dir, ['id' => 'top-a']);
        file_put_contents($this->dir.'/broken.yaml', "id: broken\n");

        $result = (new SpecLoader($this->dir))->loadAll();

        self::assertCount(1, $result['specs']);
        self::assertSame('top-a', $result['specs'][0]->id);
        self::assertCount(1, $result['errors']);
        self::assertArrayHasKey($this->dir.'/broken.yaml', $result['errors']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $name, array $data): string
    {
        $file = $this->dir.'/'.$name;
        file_put_contents($file, Yaml::dump($data, 6));

        return $file;
    }
}
