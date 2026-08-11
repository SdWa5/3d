<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\ArrayReader;
use App\Spec\InvalidSpecException;

/**
 * One device in a {@see Stack}'s `from` list, and what that tier should do differently.
 *
 * Written as a bare device id when there is nothing to say — which is most of the time — and as a mapping
 * when there is:
 *
 * ```yaml
 * from:
 *   - flexy-folded-horn-hybrid          # the shorthand
 *   - device: achenbach-18
 *     count: 8                          # eight against the six we own
 *     align: block                      # this tier's own alignment
 *     mix_with: skram                   # share a row with these
 *   - device: flexy-folded-horn-hybrid
 *     roll_mirror: 90                   # on their sides, mirrored about the rig centre line
 *   - device: eighteensound-2way-15
 *     aim: near                         # this tier turned towards a different focus
 * ```
 *
 * The keys exist because a stack had exactly one setting for the whole rig and that was too coarse:
 *
 * * **`count`** overrides the spec's `quantity`. A stack could only place what the inventory holds, so it
 *   could not express an over-claim by hand — eight Achenbachs against six owned, to see whether the rig
 *   would work if two more were borrowed. Over-booking is already reported downstream by
 *   {@see SceneReport::summarise}'s `over_inventory`, so nothing new has to warn about it.
 * * **`align`** is this tier's alignment rather than the whole stack's. The rules around it are unchanged:
 *   only a tier nothing stands on may be spread, and only as wide as its own support.
 * * **`mix_with`** names the devices to share a row with, which lifts mixing off the bottom row. It still has
 *   to pass the same two gates — matching heights, and only to remove an inverted step — because a row with a
 *   step through it has two top faces and whatever stands on it hangs in the air over the short half.
 * * **`roll_mirror`** lays this device's tiers on their sides, the half past the middle rolled the stated
 *   quarter turn and the half before it its mirror image. Folded horns are the reason: a Flexy on its side is
 *   763 × 591 rather than 591 × 763, so the wall comes out wider and lower out of the same cabinets. Named per
 *   device rather than inferred, because **no spec field says which cabinets are horn-loaded** and adding one
 *   to drive a rotation would be inventing a property to serve a layout.
 * * **`aim`** turns this tier towards a focus of its own rather than the placement's. A stack applies one aim to
 *   every top it carries, which is wrong for the row every rig actually builds: the long throw in the middle
 *   wants the far focus and the small boxes outboard of it are near-field fill. Since a mixed row expands into
 *   one placement per segment anyway, each segment can carry its own.
 */
final class StackEntry
{
    /**
     * @param int|null $count null means "however many the spec says we own"
     * @param list<string> $mixWith device ids to share this tier with
     * @param float|null $rollMirror the quarter turn given to this device's right-hand half, null for upright
     * @param string|null $aim the scene focus this tier is turned towards, null to follow the placement's own
     */
    public function __construct(
        public readonly string $device,
        public readonly ?int $count = null,
        public readonly ?LayoutMode $align = null,
        public readonly array $mixWith = [],
        public readonly ?float $rollMirror = null,
        public readonly ?string $aim = null,
    ) {
    }

    /**
     * One entry of `from`, in either form.
     *
     * @param string|ArrayReader $entry as {@see ArrayReader::entryList} returns them
     */
    public static function fromValue(string|ArrayReader $entry): self
    {
        if (is_string($entry)) {
            return new self($entry);
        }

        $allowed = ['device', 'count', 'align', 'mix_with', 'roll_mirror', 'aim'];
        $unknown = $entry->unknownKeys($allowed);
        if ($unknown !== []) {
            throw new InvalidSpecException(sprintf(
                "stack.from: unknown key '%s' (allowed: %s)",
                $unknown[0],
                implode(', ', $allowed),
            ));
        }

        return new self(
            device: $entry->requireString('device'),
            count: $entry->optionalInt('count'),
            align: $entry->has('align') ? $entry->requireEnum('align', LayoutMode::class) : null,
            mixWith: self::readMixWith($entry),
            rollMirror: $entry->optionalFloat('roll_mirror'),
            aim: $entry->optionalString('aim'),
        );
    }

    /**
     * Everything wrong with this entry on its own, without the `placement '<id>': ` prefix.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $messages = [];

        if ($this->count !== null && $this->count < 1) {
            $messages[] = sprintf("stack.from '%s': count must be at least 1, got %d", $this->device, $this->count);
        }
        foreach ($this->mixWith as $other) {
            if ($other === $this->device) {
                $messages[] = sprintf("stack.from '%s': mix_with names itself", $this->device);
            }
        }
        if ($this->rollMirror !== null && fmod(abs($this->rollMirror), 180.0) !== 90.0) {
            // Same line {@see Lattice} draws: only a quarter turn puts the body off to one side, which is
            // what a mirror is made of.
            $messages[] = sprintf(
                "stack.from '%s': roll_mirror must be 90 or 270, got %s",
                $this->device,
                $this->rollMirror,
            );
        }

        return $messages;
    }

    /** `mix_with: skram` and `mix_with: [skram, other]` both read the same way. */
    private static function readMixWith(ArrayReader $entry): array
    {
        if (!$entry->has('mix_with')) {
            return [];
        }

        return $entry->isList('mix_with') ? $entry->stringList('mix_with') : [$entry->requireString('mix_with')];
    }
}
