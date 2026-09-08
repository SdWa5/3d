<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Which owners are one sound system.
 *
 * **`owner` is what the specs record and it is not quite the right discriminator, which this class exists to
 * admit.** `sdwa5` and `sepp` are two owners and one system: they travel together, they are what stands on a stage
 * when this collective plays, and {@see SweepAxes::DEFAULT_OWNERS} already names them as the pair a bare sweep
 * builds from. Until this existed, {@see SystemSplit::SystemsApart} read the owner field and dealt them two
 * separate walls — so the rig a render showed was four systems where the event has three.
 *
 * **Stated at invocation time rather than in the specs, which is the answer CVR-3 was holding out for.** That
 * section asked what the right discriminator is and refused to invent a `system:` field to serve a layout, and the
 * refusal was right: who owns a cabinet is a fact about the cabinet, where a grouping is a fact about one gig.
 * Lending gear across systems is normal here and the specs must keep saying who owns what.
 *
 * **The default lives beside `DEFAULT_OWNERS` for the same reason it is a constant rather than a file**: it is one
 * sentence about this collective, it is already half-stated ten lines above, and a second place to write it down is
 * a second place to forget. `--group` replaces it for a run.
 */
final class SystemGrouping
{
    /**
     * The systems this collective actually has, as system name to the owners in it.
     *
     * Every owner not named here is its own system under its own name, which is why GMSS, PSL and Innschleife are
     * absent rather than listed: a borrowed system is one system and saying so adds nothing.
     */
    public const DEFAULT = ['ours' => ['sdwa5', 'sepp']];

    /**
     * @param array<string, list<string>> $systems system name to the owners in it
     */
    private function __construct(private readonly array $systems)
    {
    }

    /**
     * The default grouping, or what `--group` stated.
     *
     * **`NAME:owner+owner`, repeatable, and stating any group replaces the default rather than adding to it.**
     * Adding would make `--group=gmss-psl:gmss+psl` mean "and our pair is still one system", which is a second
     * fact nobody typed. Replacing means the option says the whole truth about this run, which is what every other
     * narrowing option here does.
     *
     * @param list<string> $stated
     *
     * @return self|string the grouping, or the reason it cannot be read
     */
    public static function of(array $stated): self|string
    {
        if ([] === $stated) {
            return new self(self::DEFAULT);
        }

        $systems = [];
        $seen = [];
        foreach ($stated as $group) {
            $parts = explode(':', $group);
            if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
                return sprintf('--group=%s expects NAME:owner+owner', $group);
            }
            $owners = explode('+', $parts[1]);
            foreach ($owners as $owner) {
                if ('' === $owner) {
                    return sprintf('--group=%s expects NAME:owner+owner', $group);
                }
                if (isset($seen[$owner])) {
                    // Two systems claiming one owner is not a grouping, and picking by argument order would make
                    // the rig depend on typing order. The same refusal two rosters get when they disagree.
                    return sprintf("--group: '%s' is in two systems, %s and %s", $owner, $seen[$owner], $parts[0]);
                }
                $seen[$owner] = $parts[0];
            }
            $systems[$parts[0]] = $owners;
        }

        return new self($systems);
    }

    /**
     * The system an owner belongs to — itself, unless a group names it.
     *
     * The one question everything else asks. {@see StackDeal::groups} partitions on it instead of on the owner
     * field, and {@see SweepAxes::rigsToTry} counts distinct answers to decide whether a rig has anything to
     * separate.
     */
    public function systemOf(string $owner): string
    {
        foreach ($this->systems as $system => $owners) {
            if (in_array($owner, $owners, true)) {
                return $system;
            }
        }

        return $owner;
    }

    /**
     * How many distinct systems these owners make up.
     *
     * **This is what decides whether the separation axis means anything**, and reading it off the owner count was
     * the defect: our gear and Sepp's is two owners, so all three separations were offered and solved, and two of
     * them produced a rig standing our own system apart from itself.
     *
     * @param list<string> $owners
     */
    public function countIn(array $owners): int
    {
        $systems = [];
        foreach ($owners as $owner) {
            $systems[$this->systemOf($owner)] = true;
        }

        return count($systems);
    }
}
