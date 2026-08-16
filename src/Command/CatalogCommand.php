<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalog\CatalogRenderer;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints the equipment library as a table with totals, and can write `docs/catalog.md`.
 *
 * Useful well beyond 3D: total weight and volume are the numbers needed for transport and for
 * checking what a truss can carry, and the un-measured count is the measuring backlog.
 */
final class CatalogCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('catalog')
            ->setDescription('Show the equipment catalog with weight and volume totals')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Also write docs/catalog.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs, 'errors' => $errors] = $this->loadSpecs();
        if ($specs === []) {
            $this->io->warning('No specs found in '.$this->relative($this->specsDir()));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        $renderer = new CatalogRenderer();
        $this->io->table($renderer->headers(), $renderer->rows($specs));

        $summary = $renderer->summary($specs);
        $lines = [
            sprintf('Devices:      %d (%d units)', $summary['devices'], $summary['units']),
            sprintf('Total weight: %.1f kg', $summary['total_weight_kg']),
            sprintf('Total volume: %.3f m³', $summary['total_volume_m3']),
        ];
        foreach ($summary['by_category'] as $category => $count) {
            $lines[] = sprintf('%-13s %d units', ucfirst($category).':', $count);
        }
        foreach ($summary['fleet'] as $vehicle) {
            $lines[] = sprintf(
                'Vehicle %s (%s): %.1f kg payload%s',
                $vehicle['id'],
                $vehicle['owner'],
                $vehicle['payload_kg'],
                $vehicle['bay_m3'] === null ? ', bay not measured' : sprintf(', %.2f m³ bay', $vehicle['bay_m3']),
            );
        }
        foreach ($summary['by_owner'] as $owner => $totals) {
            $lines[] = sprintf('%-13s %d units, %.1f kg', 'owner '.$owner.':', $totals['units'], $totals['weight_kg']);
        }
        $this->io->text($lines);

        $this->io->newLine();
        $this->io->text([
            sprintf(
                'Dimensions measured: %d of %d',
                $summary['devices'] - count($summary['dimensions_unmeasured_ids']),
                $summary['devices'],
            ),
            sprintf(
                'Weights measured:    %d of %d',
                $summary['devices'] - count($summary['weight_unmeasured_ids']),
                $summary['devices'],
            ),
        ]);

        if ($summary['unmeasured'] > 0) {
            $this->io->warning(sprintf(
                "%d of %d device%s not fully measured.\nDimensions open: %s\nWeights open: %s",
                $summary['unmeasured'],
                $summary['devices'],
                $summary['devices'] === 1 ? '' : 's',
                $summary['dimensions_unmeasured_ids'] === [] ? 'none' : implode(', ', $summary['dimensions_unmeasured_ids']),
                $summary['weight_unmeasured_ids'] === [] ? 'none' : implode(', ', $summary['weight_unmeasured_ids']),
            ));
        }

        if ($input->getOption('write')) {
            $target = $this->projectDir().'/docs/catalog.md';
            if (@file_put_contents($target, $renderer->renderMarkdown($specs)) === false) {
                throw new RuntimeException("Cannot write {$target}");
            }
            $this->io->success('Wrote '.$this->relative($target));
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
