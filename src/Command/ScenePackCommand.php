<?php

declare(strict_types=1);

namespace App\Command;

use App\Load\LoadPlanner;
use App\Load\PackSceneWriter;
use App\Spec\Category;
use App\Spec\DeviceSpec;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The load plan as a scene: the convoy in a row with each vehicle's load standing inside it.
 *
 * **`load:plan` says what goes where and this shows it.** The two are separate commands because they answer separate
 * questions and one of them is legal: a payload verdict has to be readable without Blender anywhere near it, and CI
 * has no Blender at all. This adds the geometry on top and nothing else — the assignment, the verdicts and the
 * remainder are all the planner's, unchanged.
 *
 * **The positions come from one stated rule rather than from an optimal pack**, which
 * {@see \App\Load\PackLayout} spells out along with what it ignores. Written into `scenes/packs/` so it goes through
 * the same pipeline as every other scene, which means `ShippedScenesTest` sweeps the result for cabinets inside each
 * other — if the layout rule produces an overlap, the repository's own checks say so rather than the picture merely
 * looking wrong.
 */
final class ScenePackCommand extends BaseCommand
{
    /** Where packs live. Their own directory, because a pack is not a rig and nothing should confuse the two. */
    public const DIRECTORY = 'packs';

    protected function configure(): void
    {
        $this
            ->setName('scene:pack')
            ->setDescription('Write the load plan out as a scene — the convoy with its load inside it')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Scene id', 'packed-convoy')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Carry only this owner\'s gear; repeatable')
            ->addOption('exclude-owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Leave this owner\'s gear behind; repeatable')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Write the scene file instead of printing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        /** @var list<string> $only */
        $only = $input->getOption('owner');
        /** @var list<string> $without */
        $without = $input->getOption('exclude-owner');

        $selected = array_values(array_filter($specs, static function (DeviceSpec $spec) use ($only, $without): bool {
            if (Category::Vehicle === $spec->category) {
                return true;
            }
            if ([] !== $only && !in_array($spec->owner, $only, true)) {
                return false;
            }

            return !in_array($spec->owner, $without, true);
        }));

        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan($selected);
        if ([] === $plans) {
            $this->io->error('No transporter to pack — the library has no vehicle that states a payload');

            return self::FAILURE;
        }

        $id = (string) $input->getOption('id');
        $yaml = (new PackSceneWriter())->yaml($id, $plans, $leftovers);

        if (!$input->getOption('write')) {
            $this->io->writeln($yaml);
            $this->io->text('Pass <comment>--write</comment> to write it.');

            return self::SUCCESS;
        }

        $directory = $this->scenesDir().'/'.self::DIRECTORY;
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create '.$this->relative($directory));
        }

        $path = $directory.'/'.$id.'.yaml';
        if (false === @file_put_contents($path, $yaml)) {
            throw new \RuntimeException('Cannot write '.$this->relative($path));
        }

        $this->io->success('Wrote '.$this->relative($path));
        $this->io->text('Now: <comment>bin/console scene:build '.$id.'</comment>');

        return self::SUCCESS;
    }
}
