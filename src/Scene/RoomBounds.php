<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\InvalidSpecException;

/**
 * Room limits apply to the finished rig, including aiming, spacing and flown equipment.
 *
 * **Both limits are hard**, which the owner settled on 2026-10-03. The interface and target sub heights are goals that
 * give way to them, so a rig refused for its width is offered again with narrower stacks before it is given up on.
 * See {@see \App\Command\SceneStackCommand} for that search and {@see widthExcessIn} for how it recognises the refusal.
 */
final class RoomBounds
{
    /** The refusal for a rig wider than the room, the rig's width and then the room's. */
    private const WIDTH_PROBLEM = 'the whole rig is %f m wide and exceeds the %f m room width';

    public function __construct(
        public readonly ?float $widthM = null,
        public readonly ?float $heightM = null,
    ) {
        foreach (['room-width' => $widthM, 'room-height' => $heightM] as $name => $value) {
            if (null !== $value && (!is_finite($value) || $value <= 0.0)) {
                throw new InvalidSpecException($name.' must be a finite positive number');
            }
        }
    }

    /** @param list<PlacedDevice> $placed */
    public function problem(array $placed): ?string
    {
        if ([] === $placed) {
            return null;
        }
        $left = INF;
        $right = -INF;
        $top = -INF;
        foreach ($placed as $entry) {
            $box = $entry->worldBox();
            $left = min($left, $box['min'][0]);
            $right = max($right, $box['max'][0]);
            $top = max($top, $box['max'][2]);
        }
        if (null !== $this->widthM && $right - $left > $this->widthM + 1e-6) {
            return sprintf(str_replace('%f', '%.3f', self::WIDTH_PROBLEM), $right - $left, $this->widthM);
        }
        if (null !== $this->heightM && $top > $this->heightM + 1e-6) {
            return sprintf('the whole rig reaches %.3f m and exceeds the %.3f m room height', $top, $this->heightM);
        }

        return null;
    }

    /**
     * How far a rig is too wide, read back from a refusal {@see problem} wrote, or null for any other refusal.
     *
     * Read from the message because {@see CandidateCheck::compileYaml} hands every refusal back as one string, and this
     * class is the one place that writes this one. Rounded to the millimetre the message carries.
     */
    public static function widthExcessIn(string $refusal): ?float
    {
        $read = sscanf($refusal, self::WIDTH_PROBLEM);
        if (!is_array($read) || null === $read[0] || null === $read[1]) {
            return null;
        }

        return (float) $read[0] - (float) $read[1];
    }
}
