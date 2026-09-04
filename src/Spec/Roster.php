<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * What one system is bringing to one event, as counts that override the specs for a single run.
 *
 * **A spec says how many exist and a roster says how many turn up, and the two are different facts.** Innschleife
 * owns four small tops and brings either those four or the two big ones; a cabinet sits in the workshop with a blown
 * driver; a system lends half a rig out for the weekend. None of that is a correction to the spec — the spec is
 * right, and the catalog, the load plan and every weight total want exactly the number it states. So the difference
 * is written down beside the specs rather than inside them, and it is written down at all rather than typed into a
 * shell, because "what they are bringing on the 6th" is a fact somebody told us and facts belong in the repository.
 *
 * **A roster overrides counts and nothing else.** It does not select owners, it does not name a rig and it does not
 * decide a layout. `--owner` still says whose gear is in the inventory, and a device this file never mentions keeps
 * the quantity its spec states — which is what makes a roster composable: state Innschleife's counts and our own
 * gear is untouched, all of it, exactly as a bare sweep would build it.
 *
 * **Zero is how a cabinet stays at home.** It is a count like any other and every consumer already drops a device
 * with no units, so `thl4: 0` is the honest way to write "not this time" — honest because it names
 * the cabinet it is excluding, where simply leaving the line out would mean "bring whatever the spec says".
 */
final class Roster
{
    /**
     * @param array<string, int> $brings device id to the number of units brought, in file order
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $id,
        public readonly string $name,
        public readonly array $brings,
        public readonly ?string $notes = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data decoded YAML of one roster file
     *
     * @throws InvalidSpecException when a required field is missing or a count is not a whole number
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        $reader = new ArrayReader($data);
        $brings = $reader->requireSection('brings');

        $counts = [];
        foreach ($brings->keys() as $id) {
            $count = $brings->requireInt($id);
            if ($count < 0) {
                throw new InvalidSpecException("brings.{$id} is {$count} — a count cannot be negative");
            }
            $counts[$id] = $count;
        }

        if ($counts === []) {
            throw new InvalidSpecException('brings is empty — a roster that changes no count is not a roster');
        }

        return new self(
            sourcePath: $sourcePath,
            id: $reader->requireString('id'),
            name: $reader->requireString('name'),
            brings: $counts,
            notes: $reader->optionalString('notes'),
        );
    }
}
