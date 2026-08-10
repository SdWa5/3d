<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One acoustic opening on the front baffle: a driver cone, or a horn flare, or a horn nested inside
 * another one as a phase plug.
 *
 * These are the parts you actually see when you look into a cabinet, and until now the models had
 * none of them — the CAD meshes cut the holes and left nothing behind them.
 *
 * Positions are in the **baffle frame**: origin at the centre of the front face, +X right, +Z up. That
 * is the frame a tape measure across a baffle gives you, and it does not move when the cabinet's
 * `origin` changes.
 */
final class BaffleFeature
{
    public const CONE = 'cone';

    public const HORN = 'horn';

    public const KINDS = [self::CONE, self::HORN];

    /**
     * Cross-section of a horn. A pyramid is `sides` flat walls — 4 for the usual rectangular flare, 8 for
     * an octagonal one; elliptical is a smooth outline using the declared width and height as its two
     * axes. The mouth and the throat can differ: a compression-driver horn is round where the driver
     * bolts on and straight-edged at the mouth, and the flare morphs between the two.
     */
    public const PYRAMID = 'pyramid';

    public const ELLIPTICAL = 'elliptical';

    public const PROFILES = [self::PYRAMID, self::ELLIPTICAL];

    /**
     * How the cross-section grows from throat to mouth. Linear is a straight-walled conical horn;
     * exponential grows the area exponentially with axial distance, which is what most real horns do.
     * Both meet the declared mouth and throat exactly, so the law changes the walls, never the sizes.
     */
    public const LINEAR = 'linear';

    public const EXPONENTIAL = 'exponential';

    public const FLARES = [self::LINEAR, self::EXPONENTIAL];

    public const DEFAULT_SIDES = 4;

    private const INCH_M = 0.0254;

    /**
     * @param array{float, float}|null $at centre on the baffle; null when nested via `inside`
     * @param array{float, float}|null $mouth width and height of the opening at the baffle
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly ?array $at,
        public readonly ?array $mouth,
        public readonly float $depthM,
        public readonly ?float $throatIn,
        public readonly ?float $diameterIn,
        public readonly ?float $driverIn,
        public readonly ?string $inside,
        public readonly string $profile = self::PYRAMID,
        public readonly ?string $throatProfile = null,
        public readonly ?int $sides = null,
        public readonly string $flare = self::LINEAR,
        public readonly ?BaffleJoin $join = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $at = $reader->has('at_m') ? $reader->numberList('at_m') : null;
        if ($at !== null && count($at) !== 2) {
            throw new InvalidSpecException('at_m: expected [x, z] on the baffle');
        }

        $mouth = $reader->has('mouth_m') ? $reader->numberList('mouth_m') : null;
        if ($mouth !== null && count($mouth) !== 2) {
            throw new InvalidSpecException('mouth_m: expected [width, height]');
        }

        return new self(
            id: $reader->optionalString('id') ?? 'feature-'.$index,
            kind: $reader->requireString('kind'),
            at: $at === null ? null : [$at[0], $at[1]],
            mouth: $mouth === null ? null : [$mouth[0], $mouth[1]],
            depthM: $reader->requireFloat('depth_m'),
            throatIn: $reader->optionalFloat('throat_in'),
            diameterIn: $reader->optionalFloat('diameter_in'),
            driverIn: $reader->optionalFloat('driver_in'),
            inside: $reader->optionalString('inside'),
            profile: $reader->optionalString('profile') ?? self::PYRAMID,
            throatProfile: $reader->optionalString('throat_profile'),
            sides: $reader->optionalInt('sides'),
            flare: $reader->optionalString('flare') ?? self::LINEAR,
            join: BaffleJoin::fromReader($reader->optionalSection('join')),
        );
    }

    /**
     * The throat's cross-section, which defaults to the mouth's — a horn with one shape throughout.
     */
    public function throatProfileOrMouth(): string
    {
        return $this->throatProfile ?? $this->profile;
    }

    public function isPyramid(): bool
    {
        return $this->profile === self::PYRAMID || $this->throatProfileOrMouth() === self::PYRAMID;
    }

    /**
     * Wall count for the geometry builder: null only when neither end has walls to count.
     */
    public function wallCount(): ?int
    {
        return $this->isPyramid() ? ($this->sides ?? self::DEFAULT_SIDES) : null;
    }

    public function isHorn(): bool
    {
        return $this->kind === self::HORN;
    }

    public function isCone(): bool
    {
        return $this->kind === self::CONE;
    }

    /**
     * Throat diameter in metres. Inch sizes are how the audio world names throats — a "2 inch" driver
     * — so the spec keeps inches and the geometry gets metres.
     */
    public function throatM(): ?float
    {
        return $this->throatIn === null ? null : $this->throatIn * self::INCH_M;
    }

    /**
     * A cone's own diameter, or the diameter of the driver sitting at a horn's throat.
     */
    public function coneDiameterM(): ?float
    {
        $inches = $this->diameterIn ?? $this->driverIn;

        return $inches === null ? null : $inches * self::INCH_M;
    }

    /**
     * The opening's width and height, taking a cone's diameter as both when no mouth is stated.
     *
     * @return array{float, float}|null
     */
    public function openingM(): ?array
    {
        if ($this->mouth !== null) {
            return $this->mouth;
        }
        if ($this->isCone() && $this->diameterIn !== null) {
            $diameter = $this->diameterIn * self::INCH_M;

            return [$diameter, $diameter];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'at_m' => $this->at,
            'mouth_m' => $this->openingM(),
            'depth_m' => $this->depthM,
            'throat_m' => $this->throatM(),
            'cone_diameter_m' => $this->coneDiameterM(),
            'inside' => $this->inside,
            // Only a horn has walls to shape; a cone is round by construction.
            'profile' => $this->isHorn() ? $this->profile : null,
            'throat_profile' => $this->isHorn() ? $this->throatProfileOrMouth() : null,
            'sides' => $this->isHorn() ? $this->wallCount() : null,
            'flare' => $this->isHorn() ? $this->flare : null,
            'join' => $this->isHorn() ? $this->join?->toArray() : null,
        ];
    }
}
