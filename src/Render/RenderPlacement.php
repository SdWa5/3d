<?php

declare(strict_types=1);

namespace App\Render;

use App\Scene\PlacedDevice;
use App\Spec\Category;

/**
 * One placed cabinet reduced to what a render reads of it, and nothing else.
 *
 * **This is the record `scene:build` hands to `scene:render`** (TOOL-11). The render used to solve every scene again
 * from the file, a moment after the build had solved it from identical inputs, and once more for each variant pass of
 * `build:all`. A {@see PlacedDevice} cannot make that trip, because it carries a whole {@see \App\Spec\DeviceSpec},
 * possibly a packed or cranked copy of the one in `specs/`, and its boxes are derived from that copy on every call.
 * So the derived values are taken here once and stored, which keeps the stored format at the render boundary. A
 * change to how the compiler arrives at a placement changes nothing in it unless it changes what a picture shows.
 *
 * Every field is one {@see RenderPlan} reads. A field it starts reading has to be added here as well, and
 * `RenderPlanFromCompiledSceneTest` fails until it is, because it compares a plan from fresh placements with a plan
 * from the stored ones.
 */
final class RenderPlacement
{
    /**
     * @param array{min: array{float, float, float}, max: array{float, float, float}} $worldBox
     * @param array{float, float, float} $frontFaceCentre
     * @param array{float, float, float} $frontDirection
     */
    public function __construct(
        public readonly string $placementId,
        public readonly string $deviceId,
        public readonly Category $category,
        public readonly string $subtype,
        public readonly array $worldBox,
        public readonly array $frontFaceCentre,
        public readonly array $frontDirection,
        /** Whether the placement asked for an aim line either way, or null to leave it to the mode. */
        public readonly ?bool $aimLines = null,
    ) {
    }

    public static function fromPlaced(PlacedDevice $placed): self
    {
        return new self(
            $placed->placementId,
            $placed->device->id,
            $placed->device->category,
            $placed->device->subtype,
            $placed->worldBox(),
            $placed->frontFaceCentre(),
            $placed->frontDirection(),
            $placed->aimLines,
        );
    }

    /**
     * Placed devices and render placements alike, as render placements, so every caller that holds a fresh solve
     * keeps passing it straight to {@see RenderPlan}.
     *
     * @param list<PlacedDevice|self> $placed
     *
     * @return list<self>
     */
    public static function listOf(array $placed): array
    {
        return array_map(
            static fn (PlacedDevice|self $entry): self => $entry instanceof self ? $entry : self::fromPlaced($entry),
            $placed,
        );
    }

    /**
     * @return array{placement_id: string, device: string, category: string, subtype: string, world_box: array{min: array{float, float, float}, max: array{float, float, float}}, front_face_centre: array{float, float, float}, front_direction: array{float, float, float}, aim_lines: bool|null}
     */
    public function toArray(): array
    {
        return [
            'placement_id' => $this->placementId,
            'device' => $this->deviceId,
            'category' => $this->category->value,
            'subtype' => $this->subtype,
            'world_box' => $this->worldBox,
            'front_face_centre' => $this->frontFaceCentre,
            'front_direction' => $this->frontDirection,
            'aim_lines' => $this->aimLines,
        ];
    }

    /**
     * The inverse of {@see toArray()}.
     *
     * Floats survive the trip exactly, because `json_encode` writes the shortest form that reads back as the same
     * double. A picture drawn from a stored record therefore frames the camera to the bit where a fresh solve would.
     *
     * @param array<mixed> $data
     *
     * @throws \UnexpectedValueException when a field is missing or has the wrong shape
     */
    public static function fromArray(array $data): self
    {
        $string = static function (string $key) use ($data): string {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw new \UnexpectedValueException("render placement has no string '{$key}'");
            }

            return $data[$key];
        };
        $box = $data['world_box'] ?? null;
        if (!is_array($box)) {
            throw new \UnexpectedValueException("render placement has no 'world_box'");
        }
        $category = Category::tryFrom($string('category'));
        if (null === $category) {
            throw new \UnexpectedValueException("render placement has an unknown category '{$string('category')}'");
        }
        $aimLines = $data['aim_lines'] ?? null;
        if (null !== $aimLines && !is_bool($aimLines)) {
            throw new \UnexpectedValueException("render placement has a non-boolean 'aim_lines'");
        }

        return new self(
            $string('placement_id'),
            $string('device'),
            $category,
            $string('subtype'),
            ['min' => self::vector($box['min'] ?? null, 'world_box.min'), 'max' => self::vector($box['max'] ?? null, 'world_box.max')],
            self::vector($data['front_face_centre'] ?? null, 'front_face_centre'),
            self::vector($data['front_direction'] ?? null, 'front_direction'),
            $aimLines,
        );
    }

    /**
     * @return array{float, float, float}
     */
    private static function vector(mixed $value, string $name): array
    {
        if (!is_array($value) || 3 !== count($value) || !array_is_list($value)) {
            throw new \UnexpectedValueException("render placement has no three-element '{$name}'");
        }
        foreach ($value as $component) {
            if (!is_int($component) && !is_float($component)) {
                throw new \UnexpectedValueException("render placement has a non-numeric '{$name}'");
            }
        }

        return [(float) $value[0], (float) $value[1], (float) $value[2]];
    }
}
