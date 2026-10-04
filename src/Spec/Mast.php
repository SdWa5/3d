<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A telescoping wind-up stand on folding legs, for {@see Shape::Mast}.
 *
 * **The stand is open for the same reason a truss is**, and it is drawn from tubes rather than as its box. Two parts
 * make it a wind-up rather than a pole. The legs are the footprint to keep clear on a stage, and the stages slide, so
 * a stand cranked down keeps its base and loses height only in its mast.
 *
 * **`dimensions_m` is the mast column and nothing else.** Every mast tube lies inside it, and scene placement, the
 * overlap sweep and the catalog keep reading it as before. The legs, the winch and the truss adapter reach outside
 * it in plan view, but only within the `base_spread_m` square. Nothing reaches above `height` or below the floor.
 * `height` is the full extension, measured at the face the load sits on, so a truss flown at the stand's height
 * rests on the adapter.
 *
 * **The sleeve does not stand on the floor.** The legs hinge from a hub collar at `hub_height_m` and the struts
 * brace them from a lower collar where the outer sleeve ends. The sleeve and every stage are one tube length long,
 * because a stand folds to the length of its longest tube, so the published transport length gives the tube length
 * once the head above the top stage is taken off.
 *
 * **Where the sleeve ends follows from two published figures**, the minimum height and the transport length. Fully
 * cranked down the stand is its folded length standing on the sleeve's foot, so the foot is the difference. The first
 * version put it at half the hub height, which was estimated off a photograph, and so stated a minimum height that no
 * document gave.
 */
final class Mast
{
    /** Below this a stage wobbles in the one above it. Estimated, the owner's datasheet states no overlap. */
    public const MIN_OVERLAP_M = 0.20;

    /**
     * @param list<float> $sections round tube diameters, outer sleeve first
     */
    public function __construct(
        public readonly array $sections,
        public readonly float $transportLength,
        /** The lowest the stand cranks to, as published. */
        public readonly float $minHeight,
        public readonly float $hubHeight,
        public readonly float $spigotDiameter,
        public readonly int $legs,
        public readonly float $baseSpread,
        public readonly float $legWidth,
        public readonly float $legYawDeg,
        public readonly bool $winch,
        public readonly ?MastAdapter $adapter,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $adapter = $reader->optionalSection('adapter');

        return new self(
            $reader->numberList('sections_m'),
            $reader->requireFloat('transport_length_m'),
            $reader->requireFloat('min_height_m'),
            $reader->requireFloat('hub_height_m'),
            $reader->requireFloat('spigot_diameter_m'),
            $reader->requireInt('legs'),
            $reader->requireFloat('base_spread_m'),
            $reader->requireFloat('leg_width_m'),
            $reader->optionalFloat('leg_yaw_deg', 0.0) ?? 0.0,
            $reader->optionalBool('winch'),
            null !== $adapter ? MastAdapter::fromReader($adapter) : null,
        );
    }

    /** The stages that slide, which is every tube but the outer sleeve. */
    public function movingStages(): int
    {
        return max(0, count($this->sections) - 1);
    }

    /** Where the outer sleeve ends, on the strut collar: the minimum height less the folded length standing on it. */
    public function sleeveBottomM(): float
    {
        return $this->minHeight - $this->transportLength;
    }

    /** What stands above the top stage: the adapter, or nothing for a bare receiver. */
    public function headM(): float
    {
        return $this->adapter->height ?? 0.0;
    }

    /** One tube's length. The stand folds to it, with the head on top. */
    public function tubeLengthM(): float
    {
        return $this->transportLength - $this->headM();
    }

    /** The lowest the stand cranks to: every stage inside the sleeve, the head on top. */
    public function collapsedHeightM(): float
    {
        return $this->minHeight;
    }

    /** How far each stage stands out of the one below it at the given height. */
    public function travelM(float $height): float
    {
        $stages = $this->movingStages();

        return 0 === $stages ? 0.0 : ($height - $this->collapsedHeightM()) / $stages;
    }

    /** How much of each stage is still inside the one below it at the given height. */
    public function overlapM(float $height): float
    {
        return $this->tubeLengthM() - $this->travelM($height);
    }

    /**
     * @return array{sections_m: list<float>, transport_length_m: float, min_height_m: float, hub_height_m: float, spigot_diameter_m: float, legs: int, base_spread_m: float, leg_width_m: float, leg_yaw_deg: float, winch: bool, adapter: array{length_m: float, bar_m: float, height_m: float, clamp_spacing_m: float}|null}
     */
    public function toArray(): array
    {
        return [
            'sections_m' => $this->sections,
            'transport_length_m' => $this->transportLength,
            'min_height_m' => $this->minHeight,
            'hub_height_m' => $this->hubHeight,
            'spigot_diameter_m' => $this->spigotDiameter,
            'legs' => $this->legs,
            'base_spread_m' => $this->baseSpread,
            'leg_width_m' => $this->legWidth,
            'leg_yaw_deg' => $this->legYawDeg,
            'winch' => $this->winch,
            'adapter' => $this->adapter?->toArray(),
        ];
    }

    /**
     * The block as the bpy side builds it, with every length it needs already worked out at full extension, so the
     * Python does no unit arithmetic of its own.
     *
     * @return array<string, mixed>
     */
    public function planArray(Dimensions $dimensions): array
    {
        return [
            ...$this->toArray(),
            'sleeve_bottom_m' => $this->sleeveBottomM(),
            'tube_length_m' => $this->tubeLengthM(),
            'travel_m' => $this->travelM($dimensions->height),
            'head_m' => $this->headM(),
            'collapsed_height_m' => $this->collapsedHeightM(),
            'moving_stages' => $this->movingStages(),
        ];
    }
}
