<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Two horns that share one mouth: the wall between them stops `depth_m` behind the baffle, so the
 * baffle shows a single continuous opening and the two flares only separate deeper in.
 *
 * That is what a horn-loaded cabinet with two drivers on one flare looks like from the front, and it
 * cannot be said with the fields that place a feature: `at_m` and `mouth_m` describe one opening each,
 * and two of them that touch still read as two holes with a line between them. The relation is between
 * the pair, so it lives on the later of the two — the same way `inside` names the horn it sits in.
 *
 * `depth_m` is the length of the divider that is *missing*, measured from the baffle inwards, not the
 * length of the divider that remains. Zero would be a join that removes nothing.
 */
final class BaffleJoin
{
    public function __construct(
        public readonly string $with,
        public readonly float $depthM,
    ) {
    }

    public static function fromReader(?ArrayReader $reader): ?self
    {
        if (null === $reader) {
            return null;
        }

        // Both fields change the geometry and neither has a default worth falling back to, so a typo
        // here is a join that quietly builds something else. Same reason the scene readers are strict.
        $allowed = ['with', 'depth_m'];
        $unknown = $reader->unknownKeys($allowed);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("join: unknown key '%s' (allowed: %s)", $unknown[0], implode(', ', $allowed)));
        }

        return new self(
            $reader->requireString('with'),
            $reader->requireFloat('depth_m'),
        );
    }

    /**
     * @return array{with: string, depth_m: float}
     */
    public function toArray(): array
    {
        return [
            'with' => $this->with,
            'depth_m' => $this->depthM,
        ];
    }
}
