<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * Whether a candidate rig stands up, which is the sixth axis of the sweep and the odd one out among them.
 *
 * **It is the only axis that is an outcome rather than an input.** The other five — the rig, the shape, the
 * orientation, the mirror style and the alignment — are things a caller asks for, and the id records them so a
 * reader can see what was asked. This one is the solver's answer, and a candidate is one or the other rather than a
 * variant of the same rig, so it doubles the sweep instead of multiplying it. There is deliberately no
 * `--feasibility` option: asking for an impossible rig is not a request anybody can make.
 *
 * **Written into every id all the same.** The alternative — say nothing when a rig is possible — is exactly the
 * convention this repository abandoned in 0.79.0, when `pyramid`, `upright` and `alternate` were omitted at one
 * value each and the price was a directory nobody could read: a name with a gap in it says that a value was left
 * out, never which one. Stated by the owner: treat it like the other axis.
 *
 * **A rig can change sides, and that is handled rather than special-cased.** Measure a cabinet and a rig that used
 * to interpenetrate may stand; the replay then writes a differently named file and the old one is deleted as stale
 * by the same machinery that handles a renamed axis. Nothing else has to know.
 */
enum Feasibility: string
{
    /** Every geometry check is satisfied. This is what `scenes/generated/` held exclusively until 0.89.0. */
    case Possible = 'possible';

    /**
     * At least one cabinet stands on nothing or inside another one. Emitted anyway, with the offenders caged in
     * red by {@see \App\Scene\Fault} so the failure is a picture rather than a sentence that scrolls away.
     */
    case Impossible = 'impossible';

    /**
     * Whether a rig with these faults is one somebody could build.
     *
     * @param list<Fault> $faults
     */
    public static function of(array $faults): self
    {
        return $faults === [] ? self::Possible : self::Impossible;
    }

    /**
     * Whether a scene with this id is one the shipped-scene sweep should hold to standing up.
     *
     * **The exclusion is a property of the name rather than a list somebody maintains**, which is what CVR-5 asked
     * for. `ShippedScenesTest`'s promise is that a scene in this repository stands up, and an impossible rig is in
     * the repository precisely because it does not — so it has to be excluded by construction or the test's whole
     * meaning changes.
     */
    public static function isImpossibleId(string $id): bool
    {
        // **Matched as a name suffix OR as a whole path segment**, so that the answer survives the axis becoming a
        // folder. Today feasibility is a field in the name and `-impossible` is the whole of it; the day somebody
        // runs `--folders=feasibility` the value moves into the path, and a check that only knew the suffix would
        // quietly return false for every impossible rig — which would turn `ShippedScenesTest` into an assertion
        // that hundreds of deliberately unbuildable rigs stand up.
        $path = str_replace('\\', '/', $id);

        return str_contains($path, '-'.self::Impossible->value)
            || str_contains('/'.$path.'/', '/'.self::Impossible->value.'/');
    }
}
