<?php

declare(strict_types=1);

namespace App\Command;

use App\Scene\GeneratedHeader;
use App\Scene\GeneratedSceneDiff;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What a regeneration changed in `scenes/generated/`, against a git revision.
 *
 * **The measurement every solver change ends with**, counted the same way each time by {@see GeneratedSceneDiff}.
 * It reads the files' headers and compiles nothing, so it takes seconds where a regeneration takes minutes, and it
 * can be run as often as the regeneration is.
 */
final class SceneDiffCommand extends BaseCommand
{
    private const GENERATED = 'scenes/generated';

    protected function configure(): void
    {
        $this
            ->setName('scene:diff')
            ->setDescription('Count what a regeneration changed in the generated scenes, against a git revision')
            ->addArgument('ref', InputArgument::OPTIONAL, 'The revision to compare the working tree with', 'HEAD')
            ->addOption('list', null, InputOption::VALUE_NONE, 'Name every rig that changed verdict, appeared or vanished')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show every folder, unchanged ones too, which makes the table a baseline');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $ref = (string) $input->getArgument('ref');

        $before = $this->atRevision($ref);
        if (null === $before) {
            $this->io->error("Cannot read {$ref}:".self::GENERATED.' from git');

            return self::FAILURE;
        }
        $folders = GeneratedSceneDiff::compare($before, $this->inWorkingTree(), (bool) $input->getOption('all'));

        if ([] === $folders) {
            $this->io->success("No generated scene differs from {$ref}");

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($folders as $folder => $f) {
            $rows[] = [
                $folder,
                sprintf('%d → %d', $f['possible'][0], $f['possible'][1]),
                sprintf('%d → %d', $f['impossible'][0], $f['impossible'][1]),
                count($f['to_possible']),
                count($f['to_impossible']),
                count($f['appeared']).' / '.count($f['vanished']),
                count($f['rows_changed']),
                sprintf('%.3f → %.3f', $f['miss_m'][0], $f['miss_m'][1]),
                sprintf('%.3f → %.3f', $f['worst_miss_m'][0], $f['worst_miss_m'][1]),
            ];
        }
        $this->io->table(
            ['folder', 'possible', 'impossible', '→ possible', '→ impossible', 'new / gone', 'rows changed', 'miss m', 'worst m'],
            $rows,
        );
        $this->io->text('Miss is how far each stack\'s subs stand from its target height, summed over every rig.');

        if ($input->getOption('list')) {
            foreach ($folders as $f) {
                foreach (['to_possible' => '→ possible', 'to_impossible' => '→ impossible', 'appeared' => 'new', 'vanished' => 'gone'] as $field => $label) {
                    foreach ($f[$field] as $key) {
                        $this->io->writeln(sprintf('  %-13s %s', $label, $key));
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    /** @return array<string, GeneratedHeader> */
    private function inWorkingTree(): array
    {
        $root = $this->projectDir().'/'.self::GENERATED;
        $headers = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $path = substr($file->getPathname(), strlen($root) + 1);
            $header = GeneratedHeader::parse($path, (string) file_get_contents($file->getPathname()));
            if (null !== $header) {
                $headers[$path] = $header;
            }
        }

        return $headers;
    }

    /**
     * Every generated scene at a revision, read through one `git cat-file --batch` rather than a process per file.
     *
     * @return array<string, GeneratedHeader>|null
     */
    private function atRevision(string $ref): ?array
    {
        $list = $this->git(['ls-tree', '-r', '-z', '--name-only', $ref, '--', self::GENERATED], '');
        if (null === $list) {
            return null;
        }
        $paths = array_values(array_filter(explode("\0", $list), static fn (string $p): bool => str_ends_with($p, '.yaml')));
        $batch = $this->git(['cat-file', '--batch'], implode('', array_map(static fn (string $p): string => "{$ref}:{$p}\n", $paths)));
        if (null === $batch) {
            return null;
        }

        $headers = [];
        $offset = 0;
        foreach ($paths as $path) {
            $end = strpos($batch, "\n", $offset);
            if (false === $end || 1 !== preg_match('/ blob (\d+)$/', substr($batch, $offset, $end - $offset), $size)) {
                return null;
            }
            $text = substr($batch, $end + 1, (int) $size[1]);
            $offset = $end + 1 + (int) $size[1] + 1;
            $relative = substr($path, strlen(self::GENERATED) + 1);
            $header = GeneratedHeader::parse($relative, $text);
            if (null !== $header) {
                $headers[$relative] = $header;
            }
        }

        return $headers;
    }

    /**
     * @param list<string> $arguments
     */
    private function git(array $arguments, string $stdin): ?string
    {
        // **The request goes in from a file, not a pipe.** `cat-file --batch` answers while it reads, so writing a few
        // hundred kilobytes of request into a pipe whose other end is blocked writing its answer would deadlock.
        $request = tempnam(sys_get_temp_dir(), 'sdwa5-scene-diff-');
        if (false === $request || false === file_put_contents($request, $stdin)) {
            return null;
        }

        try {
            $process = proc_open(
                ['git', '-C', $this->projectDir(), ...$arguments],
                [0 => ['file', $request, 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
            );
            if (!is_resource($process)) {
                return null;
            }
            $out = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            return 0 === proc_close($process) ? $out : null;
        } finally {
            @unlink($request);
        }
    }
}
