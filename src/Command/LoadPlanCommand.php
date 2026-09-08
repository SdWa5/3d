<?php

declare(strict_types=1);

namespace App\Command;

use App\Load\LoadPlanner;
use App\Load\LoadReport;
use App\Spec\Category;
use App\Spec\DeviceSpec;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Which gear rides in which vehicle, and whether the fleet may legally carry it.
 *
 * **The one command in this repository whose output is a legal question rather than a picture.** Everything else
 * here can be wrong and produce a bad render; a payload overrun is a fine, a liability question after an accident
 * and a refused insurance claim. So an overrun exits non-zero and prints the kilogrammes, and it is never a warning
 * to be scrolled past.
 *
 * **Who travels is stated on the command line, not decided here.** `--exclude-owner=gmss` is the invocation this
 * collective actually uses, because GMSS's gear does not go in these two vans — stated by the owner — and that is a
 * fact about an arrangement between people rather than about the library. Baking it in would make a policy look
 * like a property of the cabinets.
 */
final class LoadPlanCommand extends BaseCommand
{
    /** Something could not be carried, or a vehicle came out over its payload. */
    public const OVERLOADED = 2;

    protected function configure(): void
    {
        $this
            ->setName('load:plan')
            ->setDescription('Assign the gear across the transporters and report weight and space separately')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Carry only this owner\'s gear; repeatable')
            ->addOption('exclude-owner', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Leave this owner\'s gear behind; repeatable')
            ->addOption('vehicle', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Use only these vehicles; repeatable')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Leave this device behind by id; repeatable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        ['specs' => $specs] = $this->loadSpecs();
        if ([] === $specs) {
            $this->io->warning('No specs found in '.$this->relative($this->specsDir()));

            return self::FAILURE;
        }

        /** @var list<string> $only */
        $only = $input->getOption('owner');
        /** @var list<string> $without */
        $without = $input->getOption('exclude-owner');
        /** @var list<string> $vehicles */
        $vehicles = $input->getOption('vehicle');
        // **One device at a time, by id, and it is not the same question `--exclude-owner` answers.** Sepp's 465 kg
        // generator travels on a trailer rather than in a van — stated by the owner — so it is neither a whole
        // owner's gear nor part of a van's load. Until the trailer is a spec of its own and becomes a third bin
        // (LOAD-5), naming the device is the only way to tell the truth about what the vans carry.
        /** @var list<string> $excluded */
        $excluded = $input->getOption('exclude');

        $unknown = $this->unknownNames($specs, $only, $without, $vehicles, $excluded);
        if (null !== $unknown) {
            $this->io->error($unknown);

            return self::FAILURE;
        }

        $selected = array_values(array_filter($specs, static function (DeviceSpec $spec) use ($only, $without, $vehicles, $excluded): bool {
            if (Category::Vehicle === $spec->category) {
                return [] === $vehicles || in_array($spec->id, $vehicles, true);
            }
            if (in_array($spec->id, $excluded, true)) {
                return false;
            }
            if ([] !== $only && !in_array($spec->owner, $only, true)) {
                return false;
            }

            return !in_array($spec->owner, $without, true);
        }));

        ['plans' => $plans, 'leftovers' => $leftovers] = (new LoadPlanner())->plan($selected);

        if ([] === $plans) {
            $this->io->error('No transporter to load — every vehicle spec was filtered out or none states a payload');

            return self::FAILURE;
        }

        foreach ((new LoadReport())->lines($plans, $leftovers) as $line) {
            $this->io->writeln($line);
        }

        $overloaded = array_filter($plans, static fn ($plan): bool => $plan->isOverloaded());
        if ([] !== $leftovers || [] !== $overloaded) {
            $this->io->error(sprintf(
                'The fleet cannot legally carry this load: %d device%s left behind',
                count($leftovers),
                1 === count($leftovers) ? '' : 's',
            ));

            return self::OVERLOADED;
        }

        $this->io->success('Every unit is assigned and no vehicle is over its payload');

        return self::SUCCESS;
    }

    /**
     * A named owner or vehicle that does not exist is a typo, and a typo that silently carries nothing would read
     * as a load plan for an empty van.
     *
     * @param list<DeviceSpec> $specs
     * @param list<string> $only
     * @param list<string> $without
     * @param list<string> $vehicles
     * @param list<string> $excluded
     */
    private function unknownNames(array $specs, array $only, array $without, array $vehicles, array $excluded): ?string
    {
        $owners = [];
        $vehicleIds = [];
        $cargoIds = [];
        foreach ($specs as $spec) {
            if (Category::Vehicle === $spec->category) {
                $vehicleIds[] = $spec->id;
                continue;
            }
            $owners[$spec->owner] = true;
            $cargoIds[] = $spec->id;
        }

        foreach ($excluded as $id) {
            if (!in_array($id, $cargoIds, true)) {
                return sprintf("unknown device '%s' — nothing to exclude", $id);
            }
        }

        foreach ([...$only, ...$without] as $owner) {
            if (!isset($owners[$owner])) {
                return sprintf("unknown owner '%s'. Available: %s", $owner, implode(', ', array_keys($owners)));
            }
        }
        foreach ($vehicles as $id) {
            if (!in_array($id, $vehicleIds, true)) {
                return sprintf("unknown vehicle '%s'. Available: %s", $id, implode(', ', $vehicleIds));
            }
        }

        return null;
    }
}
