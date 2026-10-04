<?php

declare(strict_types=1);

namespace App\Load;

use App\Spec\Category;
use App\Spec\DeviceSpec;

/**
 * Assigns the gear across the fleet, heaviest unit first, with the payload as the refusal.
 *
 * **What this is and, more usefully, what it is not.** It is not a 3D packer. Placing irregular cabinets in a fixed
 * bay is NP-hard and the inputs do not exist for it: every cabinet in this library is a bounding box, several of
 * them badly — a Tecnare top is a trapezoid, a Flexy is mostly folded horn, and the truss towers report the mast's
 * footprint rather than their unfolded outriggers. What it is, is the *assignment*: which unit rides in which
 * vehicle, so that no vehicle is over its legal payload. Space is measured alongside and reported separately,
 * because a bounding-box sum is a lower bound and can never say a load fits. See {@see LoadPlan}.
 *
 * **The ordering is heaviest-first, and that is a measurement rather than a convention.** With GMSS's gear out of
 * the fleet — stated by the owner — the load is 2238.5 kg in 20.490 m³ against 2224 kg of payload and about 29.2 m³
 * of bay. Weight is 14.5 kg over and space has 30 % of headroom, so weight is what binds. Ordering by volume into
 * the emptiest bay, which is the reflex, optimises the constraint that does not.
 *
 * **On our own fleet the answer is that it does not fit, and no ordering changes that.** 2238.5 kg against 2224 kg
 * of combined payload is infeasible before any assignment is made, so the planner leaves the lightest 20.6 kg
 * behind — two F33 truss segments — and says so. That is the useful output: not "here is your load" but "here is
 * your load and here is what does not go in it". A heuristic that hid the remainder to look successful would be
 * worse than useless, because the remainder is the answer.
 *
 * **The bins are offered largest payload first**, which is best-fit-decreasing's usual companion rule and matters
 * here because the two vehicles are not the same size. Our Movano carries 1024 kg against Sepp's estimated 1200,
 * so a naive left-to-right fill would put the heavy half in whichever vehicle happened to be listed first.
 *
 * **A device is split across vehicles only when it has to be.** Four identical tops that all fit in one vehicle
 * stay together, because a load plan that scatters a matched set across two vans is technically valid and
 * practically annoying — you want the pair of tops that go on one stack in the same vehicle. The planner therefore
 * offers a whole device to a bin first and only deals it out unit by unit when no bin can take the lot.
 */
final class LoadPlanner
{
    /**
     * @param list<DeviceSpec> $specs every spec in the library, vehicles included — they are told apart here
     *
     * @return array{plans: list<LoadPlan>, leftovers: list<array{spec: DeviceSpec, count: int}>}
     */
    public function plan(array $specs): array
    {
        $vehicles = [];
        $cargo = [];
        foreach ($specs as $spec) {
            if (Category::Vehicle === $spec->category && null !== $spec->vehicle) {
                $vehicles[] = $spec;
                continue;
            }
            if (Category::Vehicle === $spec->category) {
                // A vehicle with no block cannot state a payload, so it is not a bin. `SpecValidator` refuses this
                // outright, and skipping it here keeps the planner honest if it is ever run on an unvalidated tree.
                continue;
            }
            $cargo[] = $spec;
        }

        if ([] === $vehicles) {
            return ['plans' => [], 'leftovers' => $this->wholeDevices($cargo)];
        }

        // Largest payload first, then by id so two vehicles of the same size come out in a stable order.
        usort($vehicles, static function (DeviceSpec $a, DeviceSpec $b): int {
            $payload = ($b->vehicle?->payloadKg($b->weightKg) ?? 0.0) <=> ($a->vehicle?->payloadKg($a->weightKg) ?? 0.0);

            return 0 !== $payload ? $payload : strcmp($a->id, $b->id);
        });

        // Heaviest *unit* first, not heaviest device: three 20 kg tops are not a heavier thing than one 90 kg SKRAM,
        // and a bin packer that thought so would place the wrong item first.
        usort($cargo, static function (DeviceSpec $a, DeviceSpec $b): int {
            $weight = $b->weightKg <=> $a->weightKg;

            return 0 !== $weight ? $weight : strcmp($a->id, $b->id);
        });

        $bins = [];
        foreach ($vehicles as $index => $vehicle) {
            $bins[$index] = ['vehicle' => $vehicle, 'items' => [], 'weight' => 0.0, 'volume' => 0.0];
        }

        // **PINNED DEVICES GO FIRST, ONTO THE BIN THEY NAME.** A pack is not free to put every device anywhere, and
        // scoring bins by strain gets this exactly backwards on the one case that matters: the trailer holding a
        // 465 kg generator is the most strained bin of the three, so the generator was sent to a *van* and the
        // trailer filled with speaker cabinets. Legal on every weight check and impossible to load, since two people
        // cannot lift it and no van has a ramp. A pin is a fact rather than a preference, so it is placed before
        // anything else can take the room.
        //
        // Partitioned rather than re-sorted. A comparator that only ranks pinnedness would leave the heaviest-first
        // order to `sort`'s stability, which PHP 8 does guarantee and which is a poor thing for the reader to have
        // to know.
        $pinned = [];
        $free = [];
        foreach ($cargo as $spec) {
            if (null !== $spec->carriedOn) {
                $pinned[] = $spec;
                continue;
            }
            $free[] = $spec;
        }
        $cargo = [...$pinned, ...$free];

        $leftovers = [];
        foreach ($cargo as $spec) {
            $remaining = $spec->quantity;
            $unitVolume = $spec->transportDimensions()->volumeM3();

            if (null !== $spec->carriedOn) {
                $target = null;
                foreach ($vehicles as $index => $vehicle) {
                    if ($vehicle->id === $spec->carriedOn) {
                        $target = $index;
                        break;
                    }
                }
                // A pin naming a vehicle that is not in this run — `--vehicle` narrowed it away, or the spec is
                // wrong, which `SpecValidator` refuses — leaves the device behind rather than quietly unpinning it.
                $room = null === $target
                    ? -INF
                    : ($vehicles[$target]->vehicle?->payloadKg($vehicles[$target]->weightKg) ?? 0.0)
                        - $bins[$target]['weight'];
                if (null !== $target && $room + 1e-9 >= $spec->weightKg * $remaining) {
                    $bins[$target]['items'][] = ['spec' => $spec, 'count' => $remaining];
                    $bins[$target]['weight'] += $spec->weightKg * $remaining;
                    $bins[$target]['volume'] += $unitVolume * $remaining;
                    continue;
                }
                $leftovers[] = ['spec' => $spec, 'count' => $remaining];
                continue;
            }

            // Whole first: the bin least strained by taking every one of them.
            $whole = $this->bestBinFor($bins, $vehicles, $spec->weightKg * $remaining, $unitVolume * $remaining);
            if (null !== $whole) {
                $bins[$whole]['items'][] = ['spec' => $spec, 'count' => $remaining];
                $bins[$whole]['weight'] += $spec->weightKg * $remaining;
                $bins[$whole]['volume'] += $unitVolume * $remaining;
                continue;
            }

            // Split only because nothing could take the lot, and then one unit at a time so each is scored afresh.
            while ($remaining > 0) {
                $bin = $this->bestBinFor($bins, $vehicles, $spec->weightKg, $unitVolume);
                if (null === $bin) {
                    break;
                }
                $bins[$bin]['items'][] = ['spec' => $spec, 'count' => 1];
                $bins[$bin]['weight'] += $spec->weightKg;
                $bins[$bin]['volume'] += $unitVolume;
                --$remaining;
            }

            if ($remaining > 0) {
                $leftovers[] = ['spec' => $spec, 'count' => $remaining];
            }
        }

        $plans = [];
        foreach ($bins as $bin) {
            /** @var DeviceSpec $vehicle */
            $vehicle = $bin['vehicle'];
            $plans[] = new LoadPlan(
                vehicle: $vehicle,
                items: self::merged($bin['items']),
                payloadKg: $vehicle->vehicle?->payloadKg($vehicle->weightKg) ?? 0.0,
                bayM3: $vehicle->vehicle?->loadBayVolumeM3(),
            );
        }

        return ['plans' => $plans, 'leftovers' => $leftovers];
    }

    /**
     * The bin this load is least likely to break, or null when none of them may legally take it.
     *
     * **Weight is a hard constraint and space is a soft one, and the two are scored together.** A bin is scored by
     * the **worse of its two fills** after taking the load, and the lowest score wins — the standard move for
     * vector bin packing. Scoring weight alone lets a plan come out legal and unloadable, because 30 % of headroom
     * *across a fleet* says nothing about either vehicle: the Flexys are 195 kg per cubic metre and the truss
     * towers are mostly air, so balancing one dimension can bury the other.
     *
     * **It does not rescue our own fleet, and the docblock said it did until the plan was actually run.** Sepp's van
     * comes out at 112 % of its bay against the Movano's 33 %, under both scorings, and the reason is structural
     * rather than a bad choice: twelve Flexys are 1020 kg of a 2224 kg fleet payload, so wherever they go that
     * vehicle is full by weight and everything else has to fit in the other one. The two-dimensional score earns its
     * place on fleets where a better split exists — {@see \App\Tests\Load\LoadPlannerTest} builds one — and on
     * this fleet it changes nothing, which is worth knowing before somebody credits it with an improvement.
     *
     * Space cannot refuse, only rank. A bounding-box sum is a lower bound on the room needed, so "over the bay" is
     * evidence and "under the bay" is not a permission — {@see LoadPlan::exceedsTheBay}. A bay nobody has measured
     * scores as weight alone rather than as infinite room.
     *
     * @param array<int, array{vehicle: DeviceSpec, items: list<array{spec: DeviceSpec, count: int}>, weight: float,
     *     volume: float}> $bins
     * @param list<DeviceSpec> $vehicles
     */
    private function bestBinFor(array $bins, array $vehicles, float $weight, float $volume): ?int
    {
        $best = null;
        $bestScore = INF;
        foreach ($bins as $index => $bin) {
            $vehicle = $vehicles[$index];
            $payload = $vehicle->vehicle?->payloadKg($vehicle->weightKg) ?? 0.0;
            if ($payload - $bin['weight'] + 1e-9 < $weight) {
                continue;
            }

            $weightFill = $payload > 0.0 ? ($bin['weight'] + $weight) / $payload : INF;
            $bay = $vehicle->vehicle?->loadBayVolumeM3();
            $volumeFill = null !== $bay && $bay > 0.0 ? ($bin['volume'] + $volume) / $bay : 0.0;
            $score = max($weightFill, $volumeFill);

            if ($score < $bestScore) {
                $best = $index;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Units of the same device dealt separately are reported as one line with a count.
     *
     * @param list<array{spec: DeviceSpec, count: int}> $items
     *
     * @return list<array{spec: DeviceSpec, count: int}>
     */
    private static function merged(array $items): array
    {
        $byId = [];
        foreach ($items as ['spec' => $spec, 'count' => $count]) {
            if (isset($byId[$spec->id])) {
                $byId[$spec->id]['count'] += $count;
                continue;
            }
            $byId[$spec->id] = ['spec' => $spec, 'count' => $count];
        }

        return array_values($byId);
    }

    /**
     * @param list<DeviceSpec> $specs
     *
     * @return list<array{spec: DeviceSpec, count: int}>
     */
    private function wholeDevices(array $specs): array
    {
        return array_map(
            static fn (DeviceSpec $spec): array => ['spec' => $spec, 'count' => $spec->quantity],
            $specs,
        );
    }
}
