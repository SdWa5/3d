<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * One thing on the front baffle: a driver cone, a horn flare, a horn nested inside another one as a
 * phase plug, an open cell, a fin, a grille, or a plug.
 *
 * A **cell** is a straight-walled rectangular recess, which is what the open mouths of a folded horn
 * and the slots between drivers look like from the front. A **fin** is a thin plate standing behind
 * the baffle plane — a divider, a brace, a splitter — and it is drawn as its own part rather than cut,
 * so it needs a cell or a horn around it to be seen at all.
 *
 * A **grille** is a thin see-through sheet, rectangular with `mouth_m` or round with `diameter_in`, standing
 * `setback_m` behind the baffle or, with `inside` a cell, just in front of that cell's back wall and tilted
 * with it. A round one may bulge forward by `dome_m` and carry a solid ring `rim_m` wide round its edge. A **plug** is a solid dome `diameter_in` across and `depth_m` tall, standing forward from the
 * throat of the horn it sits `inside`, which is what a phase plug in front of a cone driver is.
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

    public const CELL = 'cell';

    public const FIN = 'fin';

    public const GRILLE = 'grille';

    public const PLUG = 'plug';

    public const KINDS = [self::CONE, self::HORN, self::CELL, self::FIN, self::GRILLE, self::PLUG];

    /**
     * Every key a feature may carry. A feature is read strictly because each of these changes the
     * geometry, and a misspelt one would otherwise build a plausible cabinet that ignores it.
     */
    public const KEYS = [
        'id', 'kind', 'at_m', 'mouth_m', 'depth_m', 'throat_in', 'diameter_in', 'driver_in', 'inside', 'profile',
        'throat_profile', 'sides', 'flare', 'join', 'angle_deg', 'turn', 'mitre', 'setback_m', 'color', 'throat_blend_m',
        'dome_m', 'rim_m', 'rim_color',
    ];

    /**
     * How thin a fin's thinnest edge has to be for it to be a plate rather than a block. Any of its three
     * edges may be the thin one, so a facing panel only millimetres deep is a fin too.
     */
    public const FIN_MAX_THICKNESS_M = 0.05;

    /**
     * How thick a grille may be. A grille is a sheet of mesh or perforated metal, and anything thicker is a
     * panel, which a fin already draws.
     */
    public const GRILLE_MAX_THICKNESS_M = 0.01;

    /**
     * How far a round grille's middle may bulge forward of its edge. A pressed speaker grille is a shallow dome,
     * and anything deeper stops reading as a sheet over a cone.
     */
    public const GRILLE_MAX_DOME_M = 0.03;

    /**
     * What a fin turns about. A yaw turns it about a vertical line through its front edge, swinging its back
     * towards +x, and a pitch about a horizontal one, swinging it towards +z.
     */
    public const YAW = 'yaw';

    public const PITCH = 'pitch';

    public const TURNS = [self::YAW, self::PITCH];

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
     * @param array{float, float}|null $at centre in the baffle frame; nested horns with setback may also state it
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
        public readonly ?float $angleDeg = null,
        public readonly ?string $turn = null,
        public readonly bool $mitre = false,
        public readonly ?float $setbackM = null,
        public readonly ?string $color = null,
        public readonly ?float $throatBlendM = null,
        /** How far a round grille's middle bulges forward of its edge, or null for a flat sheet. */
        public readonly ?float $domeM = null,
        /** How wide the solid ring round a round grille's edge is, or null for no ring. */
        public readonly ?float $rimM = null,
        /** The ring's colour, or null for the grille's own. */
        public readonly ?string $rimColor = null,
    ) {
    }

    public static function fromReader(ArrayReader $reader, int $index): self
    {
        $unknown = $reader->unknownKeys(self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidSpecException(sprintf("feature %d: unknown key '%s' (allowed: %s)", $index, $unknown[0], implode(', ', self::KEYS)));
        }

        $at = $reader->has('at_m') ? $reader->numberList('at_m') : null;
        if (null !== $at && 2 !== count($at)) {
            throw new InvalidSpecException('at_m: expected [x, z] on the baffle');
        }

        $mouth = $reader->has('mouth_m') ? $reader->numberList('mouth_m') : null;
        if (null !== $mouth && 2 !== count($mouth)) {
            throw new InvalidSpecException('mouth_m: expected [width, height]');
        }

        return new self(
            id: $reader->optionalString('id') ?? 'feature-'.$index,
            kind: $reader->requireString('kind'),
            at: null === $at ? null : [$at[0], $at[1]],
            mouth: null === $mouth ? null : [$mouth[0], $mouth[1]],
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
            angleDeg: $reader->optionalFloat('angle_deg'),
            turn: $reader->optionalString('turn'),
            mitre: $reader->optionalBool('mitre'),
            setbackM: $reader->optionalFloat('setback_m'),
            color: $reader->optionalString('color'),
            throatBlendM: $reader->optionalFloat('throat_blend_m'),
            domeM: $reader->optionalFloat('dome_m'),
            rimM: $reader->optionalFloat('rim_m'),
            rimColor: $reader->optionalString('rim_color'),
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
        return self::PYRAMID === $this->profile || self::PYRAMID === $this->throatProfileOrMouth();
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
        return self::HORN === $this->kind;
    }

    public function isCone(): bool
    {
        return self::CONE === $this->kind;
    }

    public function isCell(): bool
    {
        return self::CELL === $this->kind;
    }

    public function isFin(): bool
    {
        return self::FIN === $this->kind;
    }

    public function isGrille(): bool
    {
        return self::GRILLE === $this->kind;
    }

    public function isPlug(): bool
    {
        return self::PLUG === $this->kind;
    }

    /**
     * Whether `setback_m` means something here. A fin and a grille stand that far behind the baffle, and a horn
     * nested in another one has its mouth that far behind its host's, instead of ending at the host's throat.
     */
    public function takesSetback(): bool
    {
        return $this->isFin() || ($this->isGrille() && null === $this->inside) || ($this->isHorn() && null !== $this->inside);
    }

    /**
     * What this fin or this cell's back wall turns about: the stated `turn`, or its longer front edge when none is stated. A plate that is
     * thin in z and turned in plan, such as one bar of a zigzag lying flat, has its long edge across and so
     * needs `turn: yaw` stated.
     */
    public function effectiveTurn(): string
    {
        if (null !== $this->turn) {
            return $this->turn;
        }

        return null !== $this->mouth && $this->mouth[1] < $this->mouth[0] ? self::PITCH : self::YAW;
    }

    /**
     * Whether this fin's edges are cut parallel to the depth axis. Only a turned plate has a mitre to cut, and
     * the validator rejects one on an unturned or a perpendicular fin, so this guards the arithmetic.
     */
    public function mitred(): bool
    {
        $angle = $this->angleDeg ?? 0.0;

        return $this->mitre && $this->isFin() && 0.0 !== $angle && abs($angle) < 90.0;
    }

    /**
     * How deep a cell's back wall lies at its two ends, shallow first.
     *
     * A cell's back wall is square to the baffle unless `angle_deg` tilts it. The wall then still passes
     * through `depth_m` at the cell's centre and lies deeper towards +x on a yaw and towards +z on a pitch
     * for a positive angle, which is how a driver wall sloping back into a cabinet is drawn. A cone placed
     * `inside` such a cell sits at the centre of that wall and faces along it.
     *
     * @return array{float, float}|null null for anything but a fully stated cell
     */
    public function cellBackDepths(): ?array
    {
        if (!$this->isCell() || null === $this->mouth) {
            return null;
        }

        $extent = self::YAW === $this->effectiveTurn() ? $this->mouth[0] : $this->mouth[1];
        $offset = $extent / 2 * abs(tan(deg2rad($this->angleDeg ?? 0.0)));

        return [$this->depthM - $offset, $this->depthM + $offset];
    }

    /**
     * Where a fin reaches once it is turned, in the baffle frame and behind the baffle plane.
     *
     * A fin turns about its front edge, by default its longer one: a vertical edge when the fin is at least as
     * tall as it is wide, a horizontal one otherwise, and `turn` overrides that. `angle_deg` turns the plate,
     * positive swinging its back towards +x on a yaw and towards +z on a pitch. A turned plate's front corner would poke out of
     * the baffle, so it is shifted back until its foremost corner touches the plane, and `setback_m` moves
     * it further back from there. With `mitre` the plate's front and back edges are cut parallel to the depth
     * axis instead of square to the plate, which makes it a parallelogram in section: the front edge stays on
     * `at_m`, the back edge lands `depth_m · sin(angle)` to the side of it, and two mirrored neighbours share
     * that cut face, so a zigzag of them has one point at every corner and no overlap. The Blender side does the same arithmetic, which is why it lives here
     * once: the validator checks the very extent the builder draws.
     *
     * @return array{x: array{float, float}, z: array{float, float}, reach: float}|null null when the fin
     *                                                                                  is not fully stated
     */
    public function finFootprint(): ?array
    {
        if (!$this->isFin() || null === $this->at || null === $this->mouth) {
            return null;
        }

        [$width, $height] = $this->mouth;
        $vertical = self::YAW === $this->effectiveTurn();
        $thickness = $vertical ? $width : $height;
        $length = $vertical ? $height : $width;
        $angle = deg2rad($this->angleDeg ?? 0.0);

        $lateral = [];
        $depths = [];
        foreach ([[-$thickness / 2, 0.0], [$thickness / 2, 0.0], [-$thickness / 2, $this->depthM], [$thickness / 2, $this->depthM]] as [$u, $y]) {
            if ($this->mitred()) {
                $lateral[] = $y * sin($angle);
                $depths[] = $y * cos($angle) - $u / sin($angle);
            } else {
                $lateral[] = $u * cos($angle) + $y * sin($angle);
                $depths[] = -$u * sin($angle) + $y * cos($angle);
            }
        }

        $across = [min($lateral), max($lateral)];
        $along = [-$length / 2, $length / 2];
        [$x, $z] = $vertical ? [$across, $along] : [$along, $across];

        return [
            'x' => [$this->at[0] + $x[0], $this->at[0] + $x[1]],
            'z' => [$this->at[1] + $z[0], $this->at[1] + $z[1]],
            'reach' => ($this->setbackM ?? 0.0) + max($depths) - min($depths),
        ];
    }

    /**
     * Throat diameter in metres. Inch sizes are how the audio world names throats — a "2 inch" driver
     * — so the spec keeps inches and the geometry gets metres.
     */
    public function throatM(): ?float
    {
        return null === $this->throatIn ? null : $this->throatIn * self::INCH_M;
    }

    /**
     * A cone's own diameter, or the diameter of the driver sitting at a horn's throat.
     */
    public function coneDiameterM(): ?float
    {
        $inches = $this->diameterIn ?? $this->driverIn;

        return null === $inches ? null : $inches * self::INCH_M;
    }

    /**
     * The opening's width and height, taking the diameter of a cone, a plug or a round grille as both when no
     * mouth is stated.
     *
     * @return array{float, float}|null
     */
    public function openingM(): ?array
    {
        if (null !== $this->mouth) {
            return $this->mouth;
        }
        if (($this->isCone() || $this->isPlug() || $this->isGrille()) && null !== $this->diameterIn) {
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
            'flare' => $this->isHorn() || $this->isCell() ? $this->flare : null,
            'join' => $this->isHorn() ? $this->join?->toArray() : null,
            // How far in front of the throat the mouth's shape starts turning into the throat's. Null blends
            // over the whole depth.
            'throat_blend_m' => $this->isHorn() ? $this->throatBlendM : null,
            // Only a fin turns and stands back, and only a cell tilts its back wall; everything else is square
            // to the baffle and starts at it.
            'angle_deg' => $this->isFin() || $this->isCell() ? $this->angleDeg ?? 0.0 : null,
            'turn' => $this->isFin() || $this->isCell() ? $this->effectiveTurn() : null,
            'mitre' => $this->isFin() ? $this->mitred() : null,
            // A nested horn without one ends at its host's throat, as every nested horn did before it existed.
            'setback_m' => match (true) {
                $this->isHorn() && null !== $this->inside => $this->setbackM,
                $this->takesSetback() => $this->setbackM ?? 0.0,
                default => null,
            },
            // A grille is round when it states a diameter rather than a mouth.
            'round' => $this->isGrille() ? null === $this->mouth : null,
            // What the feature shows of its own: a cone's paper, a horn's driver, a cell's back wall, a fin's
            // plate, a grille's mesh, a plug's body. Null takes the cabinet's colour, which is what every
            // feature did before this existed, and a grille's own default.
            'color' => $this->color,
            // A round grille's shape beyond a flat disc: the bulge of its middle and the ring round its edge.
            'dome_m' => $this->isGrille() ? $this->domeM : null,
            'rim_m' => $this->isGrille() ? $this->rimM : null,
            'rim_color' => $this->isGrille() ? $this->rimColor : null,
        ];
    }
}
