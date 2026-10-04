<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;
use App\Spec\Violation;
use Symfony\Component\Yaml\Yaml;

/**
 * What happens to a candidate rig after the solver has dealt it and before it is written: it is compiled, it is
 * fingerprinted, and duplicates of it are dropped.
 *
 * **A generator that emits a scene the compiler rejects is worse than no generator**, because the failure surfaces
 * later and further from its cause. So every candidate goes through here before it reaches a file — the same
 * argument {@see \App\Command\SceneStackCommand} makes in its own docblock, moved to where the work is.
 *
 * The fingerprint is the reason the two live together. It is the geometry itself — every placed cabinet's position
 * and yaw, sorted — so two candidates that deal out to the same rig under different axis values are the same rig,
 * and only compiling them can tell. `stereo` on an odd tier of three resolves identically to `block`, and writing
 * that twice under two names would suggest a choice that does not exist.
 */
final class CandidateCheck
{
    /**
     * What the scene actually resolves to — how many cabinets, and a fingerprint of where they all end up —
     * or the first error it produces.
     *
     * The fingerprint is the **solved geometry**, not the file, and that distinction is the whole point of
     * it: two arrangements can differ in what they *say* and still be the same rig. Once every top shares one
     * row, that row is mixed, `align` has nothing left to distribute, and `center`/`block`/`stereo` all come
     * out identical — three files implying a choice that does not exist.
     *
     * @param array<string, DeviceSpec> $devices
     *
     * The spans are each top-level placement's world x extent, keyed by its id, with every cabinet a `stack:` placement
     * expands into counted under that placement. {@see \App\Command\SceneStackCommand} spaces neighbouring stacks on
     * them (GEO-11).
     *
     * @return array{cabinets: int, fingerprint: string, faults: list<Fault>, backY: float, spans: array<string, array{float, float}>}|string
     */
    public static function compileYaml(string $yaml, array $devices, ?RoomBounds $room = null): array|string
    {
        try {
            /** @var array<string, mixed> $data */
            $data = Yaml::parse($yaml);
            $scene = SceneSpec::fromArray($data, 'generated');
        } catch (\Throwable $e) {
            return 'the generated scene does not parse: '.$e->getMessage();
        }

        $result = (new SceneCompiler($devices))->compile($scene);
        $errors = Violation::errorsIn($result['violations']);
        if ([] !== $errors) {
            return $errors[0]->message;
        }

        $roomProblem = $room?->problem($result['placed']);
        if (null !== $roomProblem) {
            return $roomProblem;
        }

        // **THE TWO CHECKS THAT NAME A CABINET NO LONGER REFUSE — THEY REPORT.** A rig that floats a top or buries
        // two cabinets in each other is still not one somebody can build, but it *is* a rig, and CVR-5's whole
        // argument is that a picture of it beats a sentence about it. So the faults travel back with the geometry
        // and {@see Feasibility} decides which side of the axis the candidate lands on. Everything above this line
        // is still a refusal, because a scene that will not parse or that the compiler rejects has no geometry to
        // look at in the first place.
        //
        // The compiler cannot see either of these on its own: `on:` reads a top face, and nothing downstream
        // compares two finished placements.
        $faults = [
            ...PlacementChecks::floatingFaults($result['placed']),
            ...Interpenetration::faults($result['placed'], PlacementChecks::CONTACT_TOLERANCE_M),
        ];

        $marks = [];
        foreach ($result['placed'] as $entry) {
            $position = $entry->liftedPosition();
            $marks[] = sprintf(
                '%s@%.6F,%.6F,%.6F/%.4F',
                $entry->device->id,
                $position[0],
                $position[1],
                $position[2],
                $entry->yawDeg(),
            );
        }
        sort($marks);

        $backY = -INF;
        $spans = [];
        foreach ($result['placed'] as $entry) {
            $box = $entry->worldBox();
            $backY = max($backY, $box['max'][1]);
            // A stack's cabinets are named `<placement>/<row><slot>`, so the part before the slash is the placement.
            $owner = explode('/', $entry->placementId, 2)[0];
            $spans[$owner] = [
                min($spans[$owner][0] ?? INF, $box['min'][0]),
                max($spans[$owner][1] ?? -INF, $box['max'][0]),
            ];
        }

        return [
            'cabinets' => count($result['placed']),
            'fingerprint' => implode('|', $marks),
            'faults' => $faults,
            // The deepest back face, which a backdrop stands behind. See {@see StackBackdrop}.
            'backY' => [] === $result['placed'] ? 0.0 : $backY,
            'spans' => $spans,
        ];
    }

    /**
     * Drops arrangements that place their cabinets in exactly the same spots as an earlier one.
     *
     * `stereo` on an odd tier of three resolves identically to `block` — the leftover cabinet centres on
     * `at` and the outer two land on the envelope edges — and writing that rig twice under two names would
     * suggest a choice that does not exist.
     *
     * @param array<string, array{yaml: string, cabinets: int, fingerprint: string}> $candidates
     * @param array<string, string> $skipped
     *
     * @return array<string, array{yaml: string, cabinets: int, fingerprint: string}>
     */
    public static function deduplicate(array $candidates, array &$skipped): array
    {
        $kept = [];
        $seen = [];

        foreach ($candidates as $name => $candidate) {
            $fingerprint = $candidate['fingerprint'];
            $existing = array_search($fingerprint, $seen, true);
            if (false !== $existing) {
                $skipped[$name] = sprintf('the same rig as %s', $existing);
                continue;
            }

            $seen[$name] = $fingerprint;
            $kept[$name] = $candidate;
        }

        return $kept;
    }
}
