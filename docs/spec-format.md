# Spec format

One YAML file per device under [`specs/`](../specs), grouped by category. The file is the single
source of truth: geometry, the catalog and the metadata inside the exported model all come from it.

The filename must equal the `id`. Validate with `bin/console specs:validate`.

## Full example

```yaml
id: top-a                     # lowercase-dashes; must match the filename
name: "Top A"                 # human label, shown in the catalog and asset browser
category: speaker             # speaker | truss | rack | stand | other
subtype: top                  # see the category table below
quantity: 2                   # how many of these exist
owner: sdwa5                  # default sdwa5; lowercase-dashes. Borrowed gear names its owner
build: clone                  # clone | own-design | original

clone_of:                     # required for build: clone, forbidden otherwise
  manufacturer: Acme          # `unknown` until somebody writes it down
  model: X1
  reference: datasheet        # datasheet | plans | cad | none
  url: null                   # also recorded in docs/sources.md
                              # factory gear (build: original) omits this block entirely — its
                              # datasheet is its own, so there is no original to name

provenance: datasheet         # measured | plans | datasheet | estimated
deviations: |                 # optional: how the build differs from the original
  Custom grille art, different corner hardware.

geometry:
  shape: box                  # box | trapezoid | wedge
  dimensions_m:               # outer dimensions, always the true bounding box
    width: 0.80
    height: 0.58
    depth: 0.45
  back_width_m: null          # trapezoid only: width at the back
  front_height_m: null        # wedge only: height at the front
  origin: bottom-center       # bottom-center | rigging-point | geometric-center
  chamfer_m: 0.012            # edge bevel; below half the smallest edge

appearance:
  color: "#111111"            # #rrggbb, sRGB
  grille:
    inset_m: 0.014            # how deep the grille sits behind the front; omit for no grille
    color: "#0a0a0a"          # defaults to appearance.color

physical:
  weight_kg: 35.0             # required; a DIY build rarely weighs what the original does
  handles: [left, right]      # left | right | back | top — cut as recesses

rigging:
  flyable: false              # true requires at least one point
  points:                     # positions in the measuring frame (see docs/conventions.md)
    - id: top-front-left
      position_m: [-0.30, -0.15, 0.58]
      thread: M10

audio:                        # optional, but worth filling in from the original's datasheet
  coverage_deg: { horizontal: 90, vertical: 60 }
  drivers:
    - { size_in: 15, type: woofer, count: 1 }
    - { size_in: 1.4, type: horn, count: 1 }

mesh_override: null           # NOT YET APPLIED by the builder — see TODO.md item 10

notes: |
  Anything worth knowing. Where the numbers came from, what is still unconfirmed.
```

Anything marked optional can be left out entirely rather than written as `null`.

## Categories and subtypes

| `category` | allowed `subtype` |
|------------|-------------------|
| `speaker` | `top`, `sub`, `monitor`, `line-array-element` |
| `truss` | `straight`, `corner`, `base`, `tower` |
| `rack` | `amp`, `network`, `shipping` |
| `stand` | `speaker-pole`, `tripod`, `riser` |
| `other` | anything — the escape hatch for gear the taxonomy has not caught up with |

## Shapes

`box` needs nothing extra. The tapered shapes each need their taper stated outright — deriving it
from a ratio would invent a measurement, which is exactly what `provenance` exists to prevent:

| `shape` | extra field | meaning |
|---------|-------------|---------|
| `trapezoid` | `back_width_m` | narrows towards the back, e.g. an array-able top |
| `wedge` | `front_height_m` | lower at the front, e.g. a floor monitor |

The front face stays a full `width × height` (or `width × front_height_m`) rectangle in all three,
which is why the grille frame works the same way everywhere.

## What the validator checks

`specs:validate` runs without Blender, so CI runs it too. It rejects:

* dimensions that are zero or negative; a chamfer at or above half the smallest edge
* a grille inset at or beyond half the depth; malformed `#rrggbb` colours
* `weight_kg` of zero or less; `quantity` below 1
* an `id` or `owner` that is not lowercase-dashes; an `id` that does not match its filename or is
  used twice
* a `subtype` that does not belong to its `category`
* `build: clone` without `clone_of` — and `clone_of` on something that is not a clone
* a **clone** with `provenance: datasheet`/`plans` but no `clone_of` to look them up in (factory
  gear is exempt: its datasheet is its own)
* an unknown `clone_of.reference`
* `flyable` without points, points without `flyable`, `origin: rigging-point` without points
* duplicate rigging point ids, and points outside the cabinet
* a taper field missing for its shape, present on the wrong shape, or larger than the dimension it
  tapers from
* coverage angles outside 0–360; drivers with no size or a count below 1
* a `mesh_override` that does not exist
