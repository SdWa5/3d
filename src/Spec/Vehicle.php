<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * What a transporter carries, as opposed to what it is — the block that makes a van packable.
 *
 * **A vehicle is the one device in the library that is measured twice.** Every other spec has a single set of
 * dimensions, the true outer bounding box, because the only question anybody asks of a cabinet is how much room it
 * takes up. A van is asked the opposite question: not what it occupies but what fits **inside** it. So
 * `geometry.dimensions_m` stays what it is everywhere else — the outside, 6.848 × 2.070 × 2.808 m for our Movano —
 * and the load bay is a second, smaller box declared here.
 *
 * **The bay reuses `width`/`height`/`depth` rather than the trade's own words**, and that is a deliberate cost. A
 * van catalogue says *Ladelänge*, and a reader coming from one will look for `length` and not find it. The bay lies
 * along the vehicle's own axes though, so its long dimension is the same axis as the vehicle's `depth`, and inventing
 * a second vocabulary for one category would mean every consumer of `Dimensions` needing to know which kind of box it
 * had been handed. The catalogue's *Ladelänge* is `depth` here.
 *
 * **Payload is derived and never stored**, which is the other half of the design. The Zulassungsbescheinigung states
 * a permitted gross mass in field F.2 and a mass in service in field G, both citable to a numbered field on a
 * document somebody can be shown. Their difference is neither, so storing it would put a number in the library that
 * points at no source, and would go quietly wrong the day one of the two halves is corrected. See {@see payloadKg}.
 *
 * @see \App\Spec\Category::Vehicle
 */
final class Vehicle
{
    /**
     * @param float $permittedGrossKg **Zulassungsbescheinigung field F.2**, the mass the vehicle may not exceed
     *     loaded. F.1 is the technically permitted mass and is often the same number; F.2 is the one that is legally
     *     binding in the country of registration, so F.2 is what a payload is worked out from.
     * @param Dimensions|null $loadBay the inside, or null when nobody has measured it yet. **Optional on purpose**:
     *     a registration document states every mass and no bay at all, so a van can be fully specified from its
     *     papers and still not be packable. A packer refuses such a vehicle by name, which is a better answer than a
     *     validator refusing the spec and leaving the masses unrecorded.
     * @param float|null $widthBetweenArchesM the narrow part, at floor level between the wheel boxes. **This is the
     *     dimension that actually decides whether something lies flat**, and it is 385 mm under the bay's own width
     *     on our Movano — 1.380 against 1.765 — so a packer reading only `loadBay->width` would promise floor space
     *     that does not exist.
     * @param float|null $doorApertureWidthM the rear opening, which is a third gate and usually the binding one for
     *     a tall object: a cabinet that fits the bay and not the doorway does not go in.
     * @param float|null $doorApertureHeightM as above, vertically.
     */
    public function __construct(
        public readonly float $permittedGrossKg,
        public readonly ?Dimensions $loadBay = null,
        public readonly ?float $widthBetweenArchesM = null,
        public readonly ?float $doorApertureWidthM = null,
        public readonly ?float $doorApertureHeightM = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        $bay = $reader->optionalSection('load_bay_m');

        return new self(
            permittedGrossKg: $reader->requireFloat('permitted_gross_kg'),
            loadBay: $bay !== null ? Dimensions::fromReader($bay) : null,
            widthBetweenArchesM: $bay?->optionalFloat('width_between_arches'),
            doorApertureWidthM: $bay?->optionalFloat('door_aperture_width'),
            doorApertureHeightM: $bay?->optionalFloat('door_aperture_height'),
        );
    }

    /**
     * What may be put in, in kilogrammes: `F.2 − G`.
     *
     * **The driver is already counted and a passenger is not.** "Mass in service" is defined by the EU to include a
     * 75 kg driver and a nearly full tank, so this figure is what is left for cargo with one person aboard. A second
     * person comes straight off it, which is worth knowing when the answer is 1024 kg and two people are going.
     *
     * Can come out negative or zero, and that is a spec error rather than a vehicle nobody may load — see
     * {@see \App\Spec\SpecValidator}, which refuses it there rather than letting a packer discover it.
     */
    public function payloadKg(float $massInServiceKg): float
    {
        return $this->permittedGrossKg - $massInServiceKg;
    }

    /**
     * The bay's volume in cubic metres, or null when it has not been measured.
     *
     * **A lower bound on what fits and never a fit test**, the same caveat the catalog's own volume total carries:
     * boxes do not tessellate, a wheel arch is not part of the box this multiplies out, and a horn is not a brick.
     * Two loads of identical volume pack differently.
     */
    public function loadBayVolumeM3(): ?float
    {
        return $this->loadBay?->volumeM3();
    }

    /**
     * @return array<string, float|array<string, float>>
     */
    public function toArray(): array
    {
        $out = ['permitted_gross_kg' => $this->permittedGrossKg];
        if ($this->loadBay !== null) {
            $bay = $this->loadBay->toArray();
            foreach ([
                'width_between_arches' => $this->widthBetweenArchesM,
                'door_aperture_width' => $this->doorApertureWidthM,
                'door_aperture_height' => $this->doorApertureHeightM,
            ] as $key => $value) {
                if ($value !== null) {
                    $bay[$key] = $value;
                }
            }
            $out['load_bay_m'] = $bay;
        }

        return $out;
    }
}
