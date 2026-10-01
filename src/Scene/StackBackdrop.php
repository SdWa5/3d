<?php

declare(strict_types=1);

namespace App\Scene;

use App\Spec\Category;
use App\Spec\DeviceSpec;

/**
 * A truss standing behind a generated rig on two towers, with a deco panel hung from its front.
 *
 * **PSL's 10 x 2.5 m panel at the next event is the case**, stated on 2026-10-01 as hung "from the front of the three
 * point truss, truss standing behind the systems". The truss and the towers are ours and are named by the event as
 * `TRUSS:SEGMENTS:TOWER`, see {@see parse}. The panel comes from the roster as a device of subtype `deco`.
 *
 * **EVERY NUMBER IS DERIVED, NOT CHOSEN.**
 *
 *   * The truss is `SEGMENTS` straight sections bolted flush, centred on the rig.
 *   * Its top is at the room's ceiling or at the towers' full extension, whichever is lower. Under the next event's
 *     4 m ceiling our F33 is 0.258 m deep, so it rests at 3.742 m on towers cranked down to that.
 *   * One tower stands under each end, inset by half its own width so the truss end rests on it.
 *   * The panel hangs flush against the truss's front face with its top at the truss's top.
 *   * The towers stand {@see OUTRIGGER_CLEARANCE_M} behind the deepest back face of the rig, because their unmodelled
 *     outriggers spread to 1.6 m and the outer stacks of a wide rig stand right in front of them.
 *
 * Symmetric about the rig's own centre, so it moves neither the front centre the focus is measured from nor the
 * rig's x extent in a way that changes which side is wider.
 */
final class StackBackdrop
{
    /**
     * How far behind the rig's deepest back face the towers' centre lines stand.
     *
     * Half the 1.6 m base spread a Varytec Wind Up reaches, see `truss-tower-4m.yaml`. The outriggers are not drawn,
     * so nothing would report a foot under a cabinet, and the distance is what keeps that from being true.
     */
    public const OUTRIGGER_CLEARANCE_M = 0.8;

    /** The `fly.id` the truss and the panel share, so the scene report adds them up as one bar. */
    public const FLY_ID = 'backdrop';

    public function __construct(
        public readonly DeviceSpec $truss,
        public readonly int $segments,
        public readonly DeviceSpec $tower,
    ) {
    }

    /**
     * `TRUSS:SEGMENTS:TOWER`, as an event's `backdrop:` block resolves and as a recorded command replays it.
     *
     * @param array<string, DeviceSpec> $devices
     */
    public static function parse(string $value, array $devices): self|string
    {
        $parts = explode(':', $value);
        if (3 !== count($parts) || 1 !== preg_match('/^[1-9]\d*$/', $parts[1])) {
            return sprintf('--backdrop expects TRUSS:SEGMENTS:TOWER, got %s', $value);
        }
        [$trussId, $segments, $towerId] = $parts;
        $truss = $devices[$trussId] ?? null;
        if (null === $truss || Category::Truss !== $truss->category || 'straight' !== $truss->subtype) {
            return sprintf('--backdrop: %s is not a straight truss segment', $trussId);
        }
        $tower = $devices[$towerId] ?? null;
        if (null === $tower || Category::Truss !== $tower->category || 'tower' !== $tower->subtype) {
            return sprintf('--backdrop: %s is not a truss tower', $towerId);
        }

        return new self($truss, (int) $segments, $tower);
    }

    /** As {@see parse} reads it. */
    public function stated(): string
    {
        return sprintf('%s:%d:%s', $this->truss->id, $this->segments, $this->tower->id);
    }

    /** Whether a device is something a backdrop hangs, rather than a cabinet the solver stacks. */
    public static function isDeco(DeviceSpec $device): bool
    {
        return Category::Other === $device->category && 'deco' === $device->subtype;
    }

    public function spanM(): float
    {
        return $this->segments * $this->truss->dimensions->width;
    }

    /** Where the truss's bottom chords rest, which is also the height the towers are cranked to. */
    public function flyHeightM(?float $ceilingM): float
    {
        $full = $this->tower->dimensions->height;

        return null === $ceilingM ? $full : min($full, $ceilingM - $this->truss->dimensions->height);
    }

    /** The share of truss and panel each of the two towers carries. */
    public function towerLoadKg(DeviceSpec $deco): float
    {
        return ($this->segments * $this->truss->weightKg + $deco->weightKg) / 2;
    }

    /**
     * Why this backdrop cannot carry `$deco` under `$ceilingM`, or null when it can.
     *
     * Each is a refusal rather than a fault to draw, because none of them is an arrangement somebody could look at
     * and fix by moving a cabinet. They are facts about the gear, and the fix is different gear.
     */
    public function problem(DeviceSpec $deco, ?float $ceilingM): ?string
    {
        if ($this->segments > $this->truss->quantity) {
            return sprintf('the backdrop needs %d× %s, and %d exist', $this->segments, $this->truss->id, $this->truss->quantity);
        }
        if ($this->tower->quantity < 2) {
            return sprintf('the backdrop needs two %s, and %d exist', $this->tower->id, $this->tower->quantity);
        }
        if ($deco->dimensions->width > $this->spanM() + StackMetrics::EPSILON_M) {
            return sprintf(
                '%s is %.3f m wide and the backdrop truss spans %.3f m',
                $deco->id,
                $deco->dimensions->width,
                $this->spanM(),
            );
        }
        $fly = $this->flyHeightM($ceilingM);
        if ($fly <= 0.0) {
            return sprintf('a %.3f m ceiling leaves no room for %s', $ceilingM, $this->truss->id);
        }
        $bottom = $fly + $this->truss->dimensions->height - $deco->dimensions->height;
        if ($bottom < -StackMetrics::EPSILON_M) {
            return sprintf(
                '%s hung from a truss topping out at %.3f m would reach %.3f m below the floor',
                $deco->id,
                $fly + $this->truss->dimensions->height,
                -$bottom,
            );
        }
        $load = $this->towerLoadKg($deco);
        if (null === $this->tower->maxLoadKg) {
            return sprintf('%s states no max_load_kg, so nothing says it can carry the backdrop', $this->tower->id);
        }
        if ($load > $this->tower->maxLoadKg + 1e-9) {
            return sprintf(
                'each %s would carry %.1f kg of truss and %s, over its %.1f kg rating',
                $this->tower->id,
                $load,
                $deco->id,
                $this->tower->maxLoadKg,
            );
        }

        return null;
    }

    /**
     * The four placements as scene YAML, behind a rig centred on `$centreX` whose deepest back face is at `$backY`.
     *
     * Call {@see problem} first. This writes whatever it is given.
     *
     * @return list<string>
     */
    public function yaml(DeviceSpec $deco, float $centreX, float $backY, ?float $ceilingM): array
    {
        $fly = $this->flyHeightM($ceilingM);
        $trussH = $this->truss->dimensions->height;
        $towerY = $backY + self::OUTRIGGER_CLEARANCE_M;
        $towerX = $this->spanM() / 2 - $this->tower->dimensions->width / 2;
        // Flush on the truss's front face, which is the side the audience is on.
        $panelY = $towerY - $this->truss->dimensions->depth / 2 - $deco->dimensions->depth / 2;
        $panelZ = $fly + $trussH - $deco->dimensions->height;
        $extend = $fly < $this->tower->dimensions->height - 1e-9;

        $lines = [
            sprintf(
                '  # Backdrop: %s hung from %d× %s on two %s, %s m behind the rig\'s deepest back face.',
                $deco->id,
                $this->segments,
                $this->truss->id,
                $this->tower->id,
                self::number(self::OUTRIGGER_CLEARANCE_M),
            ),
            sprintf(
                '  # The truss tops out at %s m. Each tower carries %s kg.',
                self::number($fly + $trussH),
                self::number($this->towerLoadKg($deco)),
            ),
        ];
        foreach (['left' => -1, 'right' => 1] as $side => $sign) {
            $lines[] = sprintf('  - id: backdrop-tower-%s', $side);
            $lines[] = sprintf('    device: %s', $this->tower->id);
            $lines[] = sprintf('    at: [%s, %s]', self::number($centreX + $sign * $towerX), self::number($towerY));
            if ($extend) {
                $lines[] = sprintf('    extend_to_m: %s', self::number($fly));
            }
        }
        $lines[] = '  - id: backdrop-truss';
        $lines[] = sprintf('    device: %s', $this->truss->id);
        $lines[] = sprintf('    at: [%s, %s]', self::number($centreX), self::number($towerY));
        $lines[] = '    fly:';
        $lines[] = sprintf('      height_m: %s', self::number($fly));
        $lines[] = sprintf('      id: %s', self::FLY_ID);
        $lines[] = '    row:';
        $lines[] = sprintf('      count: %d', $this->segments);
        $lines[] = '      gap_m: 0.0';
        $lines[] = '  - id: backdrop-deco';
        $lines[] = sprintf('    device: %s', $deco->id);
        $lines[] = sprintf('    at: [%s, %s]', self::number($centreX), self::number($panelY));
        $lines[] = '    fly:';
        $lines[] = sprintf('      height_m: %s', self::number($panelZ));
        $lines[] = sprintf('      id: %s', self::FLY_ID);
        $lines[] = '';

        return $lines;
    }

    /** Millimetres, with the trailing zeros a person would not write. */
    private static function number(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }
}
