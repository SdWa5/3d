<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\CatalogRenderer;
use App\Tests\Support\SpecFactory;
use PHPUnit\Framework\TestCase;

final class CatalogRendererTest extends TestCase
{
    public function testSummaryTotalsAcrossAllUnits(): void
    {
        $specs = [
            SpecFactory::spec(['id' => 'top-a', 'quantity' => 2, 'physical' => ['weight_kg' => 35.0]]),
            SpecFactory::spec([
                'id' => 'sub-a',
                'subtype' => 'sub',
                'quantity' => 4,
                'physical' => ['weight_kg' => 55.0],
                'geometry' => ['dimensions_m' => ['width' => 1.0, 'height' => 1.0, 'depth' => 1.0]],
            ]),
        ];

        $summary = (new CatalogRenderer())->summary($specs);

        self::assertSame(2, $summary['devices']);
        self::assertSame(6, $summary['units']);
        self::assertSame(2 * 35.0 + 4 * 55.0, $summary['total_weight_kg']);
        self::assertSame(2 * (0.8 * 0.6 * 0.45) + 4 * 1.0, $summary['total_volume_m3']);
        self::assertSame(['speaker' => 6], $summary['by_category']);
    }

    public function testUnmeasuredCountsEverythingNotActuallyMeasured(): void
    {
        $specs = [
            SpecFactory::spec(['id' => 'top-a', 'provenance' => 'datasheet']),
            SpecFactory::spec(['id' => 'sub-a', 'subtype' => 'sub', 'provenance' => 'estimated']),
            SpecFactory::spec(['id' => 'mon-a', 'subtype' => 'monitor', 'provenance' => 'measured']),
        ];

        $renderer = new CatalogRenderer();
        $summary = $renderer->summary($specs);

        self::assertSame(2, $summary['unmeasured']);
        self::assertSame(['top-a', 'sub-a'], $summary['unmeasured_ids']);
        self::assertCount(2, $renderer->unmeasured($specs));
    }

    public function testRowsShowTheClonedOriginalAndProvenance(): void
    {
        $rows = (new CatalogRenderer())->rows([SpecFactory::spec(['quantity' => 3])]);

        self::assertCount(1, $rows);
        self::assertSame('top-a', $rows[0][0]);
        self::assertSame('speaker/top', $rows[0][2]);
        self::assertSame('3', $rows[0][3]);
        self::assertSame('0.8 × 0.6 × 0.45', $rows[0][4]);
        self::assertSame('34', $rows[0][5], 'trailing zeros are noise in a table');
        self::assertSame('102', $rows[0][6]);
        self::assertSame('Acme X1', $rows[0][7]);
        self::assertSame('datasheet', $rows[0][8]);
    }

    public function testMarkdownHasATableAndTotals(): void
    {
        $markdown = (new CatalogRenderer())->renderMarkdown([SpecFactory::spec()]);

        self::assertStringContainsString('# Equipment catalog', $markdown);
        self::assertStringContainsString('Do not edit', $markdown);
        self::assertStringContainsString('| top-a | Top A | speaker/top |', $markdown);
        self::assertStringContainsString('## Totals', $markdown);
        self::assertStringContainsString('- Devices: 1 (2 units)', $markdown);
        self::assertStringContainsString('- Total weight: 68 kg', $markdown);
        self::assertStringContainsString('- Not yet measured: 1 of 1 (top-a)', $markdown);
    }

    public function testMarkdownSurvivesAnEmptyLibrary(): void
    {
        $markdown = (new CatalogRenderer())->renderMarkdown([]);

        self::assertStringContainsString('No specs yet.', $markdown);
    }
}
