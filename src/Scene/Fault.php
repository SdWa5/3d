<?php

declare(strict_types=1);

namespace App\Scene;

/**
 * One cabinet the geometry checks object to, named rather than described.
 *
 * **Every check in this repository answers with a sentence and then throws the geometry away**, which is the wrong
 * way round for the failures that are hard to picture — and that is most of them. "A `gmss-turbo-top` would stand at
 * 4.668 m with nothing under it across x" took a debug dump, two probes and a corrected coordinate mapping to
 * understand. A picture with that one cabinet in a red cage says it in a second. The checks already know which
 * cabinet they object to; until now the answer was formatted into prose and the identity was lost with it.
 *
 * **A fault is derived, never stored.** It is not a scene field and it never will be: a scene records the
 * constraints a rig has to satisfy and is re-solved on every build, so the checks fire again on the same
 * arrangement and name the same cabinets. Writing a colour into the file would put a rendering concern in the
 * schema and — worse — would freeze one build's opinion into a file whose whole contract is that it does not
 * carry answers. This way the marking cannot drift from the geometry, because it *is* the geometry's answer.
 */
final class Fault
{
    /** A cabinet standing on nothing across one horizontal axis. */
    public const FLOATING = 'floating';

    /** Two cabinets occupying the same space. */
    public const INTERPENETRATION = 'interpenetration';

    /**
     * @param list<string> $placementIds every cabinet this fault is about — one for a float, two for an overlap
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $placementIds,
        public readonly string $message,
    ) {
    }

    /**
     * @return array{kind: string, placements: list<string>, message: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'placements' => $this->placementIds, 'message' => $this->message];
    }

    /**
     * Every placement id named by any of these faults, each once.
     *
     * @param list<Fault> $faults
     * @return list<string>
     */
    public static function placementsIn(array $faults): array
    {
        $ids = [];
        foreach ($faults as $fault) {
            foreach ($fault->placementIds as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }
}
