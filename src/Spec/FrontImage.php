<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * A photograph of the cabinet's front, mapped onto the generated block's front face.
 *
 * The point is recognition rather than accuracy. Five Innschleife cabinets currently differ only in
 * their bounding box, and every borrowed cabinet is one nobody here has ever seen, so a render of
 * them is correct and unrecognisable at the same time. A front photograph fixes that without
 * pretending to model anything.
 *
 * **Only a cabinet with no interior gets one**, which is the condition the owner set and which the
 * geometry agrees with. A spec carrying an `audio.layout` has real openings cut into its baffle, so
 * a photograph over them would fight geometry that is already there and read as a texture bug. A
 * spec carrying a `mesh_override` has no known front plane at all — the CAD decides where its front
 * is — so there is nowhere well-defined to put the image. Both are rejected by
 * {@see SpecValidator} rather than silently skipped, because a spec that names an image and does not
 * get one is worse than a spec that is told no.
 *
 * **The image's own orientation is a property of the file, not of the cabinet.** A rolled cabinet's
 * front is still its front and the texture turns with the mesh, so nothing has to be done for that.
 * What does need saying is which way up the photograph itself was taken, because a photograph of a
 * cabinet lying on its side has to be turned to match a model built upright. Hence `rotate_deg`,
 * limited to the four right angles, since anything else would mean the photograph is not square to
 * the cabinet and cropping it is the fix rather than rotating it.
 *
 * **A cut-out takes its shape from the image.** With `cutout: true` the file's alpha channel cuts the whole panel,
 * front, back and edges alike, so a deco cut to its motif's silhouette shows the truss through its gaps. Only a PNG
 * carries an alpha channel among the formats read here. The cut edges along the silhouette have no wall of their
 * own, which reads at a grazing angle only.
 *
 * **The file is not committed**, for the same two reasons `mesh_override`'s meshes are not: it is
 * binary, and it is as often as not somebody else's photograph. `meshes/README.md` carries the
 * `rclone` line to fetch each one, and the licensing paragraph in `docs/sources.md` applies to a
 * photograph unchanged.
 */
final class FrontImage
{
    /** Formats Blender's image loader reads without a plugin. */
    public const IMPORTABLE = ['png', 'jpg', 'jpeg'];

    /**
     * The four right angles and nothing else. A photograph off by 7 degrees is a photograph that was
     * not taken square to the cabinet, and the fix for that is a better crop rather than a number
     * here that quietly hides it.
     */
    public const ROTATIONS = [0, 90, 180, 270];

    /**
     * PSL's front faces are drawn at 1 px = 1 cm, which is what makes the dimension check below
     * possible at all. Anything scanned or photographed at another scale says so.
     */
    public const DEFAULT_PX_PER_CM = 1.0;

    /**
     * How far the image's implied size may miss the cabinet's, as a fraction. Ten per cent is
     * generous on purpose: the check is there to catch a photograph of the *wrong cabinet*, not to
     * argue about a few pixels of border.
     */
    public const DEFAULT_TOLERANCE = 0.10;

    /** The one format here with an alpha channel, which is what a cut-out is cut by. */
    public const CUTOUT_EXTENSION = 'png';

    public function __construct(
        public readonly string $path,
        public readonly int $rotateDeg = 0,
        public readonly float $pxPerCm = self::DEFAULT_PX_PER_CM,
        public readonly float $tolerance = self::DEFAULT_TOLERANCE,
        public readonly bool $cutout = false,
    ) {
    }

    /**
     * Accepts a bare path as well as the expanded form, exactly as `mesh_override` does — a
     * photograph already the right way up at 1 px = 1 cm needs nothing else said about it.
     *
     * ```yaml
     * front_image: meshes/innschleife/tms4-front.png
     * front_image:
     *   path: meshes/psl/PSL_Subs_px.png
     *   rotate_deg: 90
     *   px_per_cm: 1.0
     * front_image:
     *   path: meshes/psl/deco-panel-front.png
     *   cutout: true
     * ```
     */
    public static function fromReader(ArrayReader $reader, string $key): ?self
    {
        if (!$reader->has($key)) {
            return null;
        }

        if (!$reader->isSection($key)) {
            return new self($reader->requireString($key));
        }

        $section = $reader->requireSection($key);

        return new self(
            $section->requireString('path'),
            $section->optionalInt('rotate_deg', 0) ?? 0,
            $section->optionalFloat('px_per_cm', self::DEFAULT_PX_PER_CM) ?? self::DEFAULT_PX_PER_CM,
            $section->optionalFloat('tolerance', self::DEFAULT_TOLERANCE) ?? self::DEFAULT_TOLERANCE,
            $section->optionalBool('cutout'),
        );
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function isImportable(): bool
    {
        return in_array($this->extension(), self::IMPORTABLE, true);
    }

    public function hasValidRotation(): bool
    {
        return in_array($this->rotateDeg, self::ROTATIONS, true);
    }

    /**
     * The cabinet size this image implies, in metres, as width and height.
     *
     * A quarter turn swaps the two, because after rotating the image its pixel width describes the
     * cabinet's height. Returns null when the file cannot be read as an image at all, which the
     * validator reports separately from a size that disagrees.
     *
     * @return array{float, float}|null
     */
    public function impliedSizeM(string $absolutePath): ?array
    {
        if ($this->pxPerCm <= 0.0) {
            return null;
        }

        $size = @getimagesize($absolutePath);
        if (false === $size) {
            return null;
        }

        [$pxW, $pxH] = $size;
        $wM = $pxW / $this->pxPerCm / 100.0;
        $hM = $pxH / $this->pxPerCm / 100.0;

        return 0 === $this->rotateDeg % 180 ? [$wM, $hM] : [$hM, $wM];
    }

    /**
     * Whether the image's implied size matches the cabinet's front within the tolerance.
     *
     * Deliberately compares both axes rather than the aspect ratio alone. An aspect check passes a
     * photograph of a cabinet twice the size, which is exactly the mix-up worth catching in a fleet
     * where several borrowed subs share a shape and differ only in how big they are.
     */
    public function matchesFront(string $absolutePath, float $widthM, float $heightM): bool
    {
        $implied = $this->impliedSizeM($absolutePath);
        if (null === $implied) {
            return false;
        }

        [$iw, $ih] = $implied;

        return abs($iw - $widthM) <= $widthM * $this->tolerance
            && abs($ih - $heightM) <= $heightM * $this->tolerance;
    }

    /**
     * @return array{path: string, rotate_deg: int, px_per_cm: float, tolerance: float, cutout: bool}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'rotate_deg' => $this->rotateDeg,
            'px_per_cm' => $this->pxPerCm,
            'tolerance' => $this->tolerance,
            'cutout' => $this->cutout,
        ];
    }
}
