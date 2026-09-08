<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\DeviceSpec;

/**
 * Which cabinets of a rig lie on their sides — the sweep's largest single lever on how much it can build.
 *
 * A rolled sub is wider and shorter than a standing one, and both halves of that pay: a wider row fills the stage in
 * fewer cabinets, and a shorter row keeps the sub/top transition inside the 2–3 m band the sweep insists on. Measured
 * on the 66-candidate sweep, varying nothing but which cabinets were rolled: **upright writes 11 scenes and every sub
 * rolled writes 24**, and the rolled runs include the first three-stack rigs this project has ever generated.
 *
 * **TOPS ARE NEVER ROLLED, AT ANY SETTING.** Settled by the owner of the gear rather than derived here, and the reason
 * is acoustic rather than geometric: a top's horn is built to throw a pattern in one orientation and rolling the cabinet
 * rolls the pattern with it. The measurement agrees — rolling everything writes 18 against the 24 of rolling only the
 * subs — but the numbers are not why. Low frequency is near-omnidirectional, which is why the same objection does not
 * apply to a sub.
 *
 * The three modes:
 *
 * * **{@see Upright}** — nothing rolled. What every generated scene was before this axis existed.
 * * **{@see Turned}** — every sub rolled, whatever it measures. The literal reading of "all cabinets on their sides",
 *   tops excepted.
 * * **{@see Mixed}** — rolled only where rolling makes the cabinet **wider and shorter**, which is the whole reason to
 *   roll one. That leaves `mid-bass` standing, at 1.200 × 0.500 the one sub already wider than it is tall, where
 *   rolling would make the wall *taller* and the row narrower; and it leaves the 0.600 × 0.600 `achenbach-18` standing,
 *   where rolling is geometrically nothing at all.
 *
 * **`Mixed` is derived from the specs and states no new fact about the gear.** `subtype` and `dimensions_m` are both
 * recorded already, so the rule reads what is there. That matters because this repository refuses to invent physical
 * properties to serve a layout — {@see StackEntry}'s own note says as much about which cabinets are horn-loaded — and a
 * rule of the form "roll the ones the owner says may be rolled" would need a spec field nobody has measured.
 *
 * A mode that resolves to no rolled cabinet at all is the same rig as {@see Upright}, and the sweep drops it before
 * solving rather than letting `deduplicate()` find it afterwards, so the scene names stay honest: a `-turned-` file
 * always has something turned in it.
 */
enum StackOrientation: string
{
    /** Every cabinet stands as it was measured. */
    case Upright = 'upright';

    /** Every sub on its side; tops stand. */
    case Turned = 'turned';

    /** On its side only where that makes the cabinet wider and shorter; tops stand. */
    case Mixed = 'mixed';

    /**
     * The device ids this mode lays on their sides, out of the ones the rig is built from.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function rolls(array $devices, array $ids): array
    {
        if (self::Upright === $this) {
            return [];
        }

        return array_values(array_filter($ids, function (string $id) use ($devices): bool {
            $device = $devices[$id] ?? null;
            if (null === $device || 'sub' !== $device->subtype) {
                return false;
            }

            // Nominal width against nominal height, not {@see RolledBox}: the question is whether the cabinet as
            // measured is taller than it is wide, and a rolled box is the answer to that rather than part of the
            // question. Strict, so a cube — which rolls to exactly itself — counts as nothing gained.
            return self::Mixed !== $this || $device->dimensions->height > $device->dimensions->width;
        }));
    }

    /**
     * Whether this mode rolls anything at all in this rig, which is what decides if it is a distinct candidate.
     *
     * @param array<string, DeviceSpec> $devices
     * @param list<string> $ids
     */
    public function rollsAnything(array $devices, array $ids): bool
    {
        return [] !== $this->rolls($devices, $ids);
    }
}
