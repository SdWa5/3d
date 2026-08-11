<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * The frame and platform of a scaffold tower, for {@see Shape::Scaffold}.
 *
 * A scaffold is open like a truss and for the same reason drawn from tubes rather than as a box: a 5 m solid block
 * beside a rig would hide it. What makes it a scaffold rather than a truss is the **platform** — the thing somebody
 * stands on — so that is the one solid part.
 *
 * **Guardrails and rungs are deliberately absent.** The posts, the bracing and the platform are what make the shape
 * read as a scaffold at a glance; a guardrail is another four tubes and another height nobody published. The same
 * judgement as a cabinet's ports: modelled when there is a source, left out when there is not.
 *
 * `platform_height_m` is where somebody stands, and it is NOT the working height. A tower sold as "AH7" —
 * *Arbeitshöhe* 7 m — has its platform at 5 m, because the convention adds two metres for a person's reach. That
 * two metres is a fact about people and has no place in a bounding box, so `dimensions_m.height` is the frame and
 * the working height lives in the notes.
 */
final class Scaffold
{
    public function __construct(
        public readonly float $postDiameter,
        public readonly float $braceDiameter,
        public readonly float $platformHeight,
        public readonly float $platformThickness,
    ) {
    }

    public static function fromReader(ArrayReader $reader): self
    {
        return new self(
            $reader->requireFloat('post_diameter_m'),
            $reader->requireFloat('brace_diameter_m'),
            $reader->requireFloat('platform_height_m'),
            $reader->requireFloat('platform_thickness_m'),
        );
    }

    /**
     * @return array{post_diameter_m: float, brace_diameter_m: float, platform_height_m: float, platform_thickness_m: float}
     */
    public function toArray(): array
    {
        return [
            'post_diameter_m' => $this->postDiameter,
            'brace_diameter_m' => $this->braceDiameter,
            'platform_height_m' => $this->platformHeight,
            'platform_thickness_m' => $this->platformThickness,
        ];
    }
}
