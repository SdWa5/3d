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
build: self-built             # self-built | own-design | original

clone_of:                     # required for build: self-built, forbidden otherwise
  manufacturer: Acme          # `unknown` until somebody writes it down
  model: X1
  reference: datasheet        # datasheet | plans | cad | none
  url: null                   # also recorded in docs/sources.md
                              # factory gear (build: original) omits this block entirely — its
                              # datasheet is its own, so there is no original to name

provenance: datasheet         # shorthand: applies to dimensions and weight alike
                              # or per field, when they differ:
                              #   provenance:
                              #     dimensions: plans
                              #     weight: measured
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
  coverage_deg: { horizontal: 90, vertical: 60 }   # also draws the coverage cone; see below
  drivers:                    # the complement, for the catalog and the model's metadata
    - { size_in: 15, type: woofer, count: 1 }
    - { size_in: 1.4, type: horn, count: 1 }

  layout:                     # optional: the openings on the front baffle, see below
    provenance: plans         # required whenever a layout exists
    inset_m: 0.056            # how far the baffle sits behind the outer front face
    features:
      - { id: horn, kind: horn, at_m: [0.0, 0.26], mouth_m: [0.36, 0.29], throat_in: 1.4,
          depth_m: 0.18, profile: pyramid, sides: 4, throat_profile: elliptical,
          flare: exponential }
      - { id: woofer, kind: cone, at_m: [0.0, -0.10], diameter_in: 13.9, depth_m: 0.10 }

mesh_override: null           # a real mesh replacing the generated block; see below

notes: |
  Anything worth knowing. Where the numbers came from, what is still unconfirmed.
```

Anything marked optional can be left out entirely rather than written as `null`.

## Baffle layout

### The coverage cone

Stating `audio.coverage_deg` also builds a **coverage cone**: a wireframe cone in front of the baffle,
reaching 10 m — the same distance a scene's default focus sits at, so "does the pattern cover the
dancefloor" reads directly against where scenes already aim. A 60° × 40° cabinet spreads 11.55 m across and
7.28 m high by the time it gets there.

Like the rigging markers it is **render-invisible** and stays out of the `.glb`, so it never changes a
model's bounding box or turns up in a preview. It is drawn as a wireframe rather than a solid, because a
solid ten-metre cone would swallow the cabinet it belongs to.

Its apex sits at the middle of the baffle, which is a simplification worth knowing: a real pattern comes
from the drivers, spread across the baffle and crossing over at different distances. The cone answers
"roughly where does this cabinet throw", not "what does the summed response do".

`audio.layout` is what turns a cabinet from a box with holes in it into one you can see into. Everything
it describes is recessed **behind** the baffle, so it never changes a model's outer bounding box.

Positions are in the **baffle frame**: origin at the centre of the front face, `at_m: [x, z]`, +X right
and +Z up — the frame a tape measure across a baffle actually gives you, and one that does not move when
the cabinet's `origin` does.

| Field | Applies to | Meaning |
|-------|-----------|---------|
| `provenance` | the layout | `measured`, `plans`, `datasheet` or `estimated`. Required — the numbers in a layout are the easiest in the whole spec to invent |
| `inset_m` | the layout | how far the baffle sits behind the outer front face. A CAD cabinet often recesses it well back |
| `id` | every feature | referenced by `inside`, and used to name the geometry |
| `kind` | every feature | `cone` (a driver) or `horn` (a flare) |
| `at_m` | unnested features | `[x, z]` centre on the baffle |
| `depth_m` | every feature | how deep it reaches into the cabinet |
| `mouth_m` | horns | `[width, height]` of the opening at the baffle |
| `throat_in` | horns | throat size in inches, as the audio world names it |
| `driver_in` | horns | puts a driver cone at the throat and bores the chamber through to it, which is what a horn-loaded driver looks like |
| `diameter_in` | cones | the cone's diameter — usually the baffle cut-out rather than the driver's nominal size, since the frame hides behind the panel |
| `inside` | nested features | nests this feature at the named horn's throat, facing forward. This is how "the HF horn sits inside the LF horn as a phase plug" stays in the data instead of in two hand-matched sets of coordinates |
| `profile` | horns | the **mouth's** cross-section: `pyramid` (default) or `elliptical` |
| `throat_profile` | horns | the **throat's** cross-section, defaulting to the mouth's. `profile: pyramid` with `throat_profile: elliptical` is a horn with straight edges outside and a round throat, which is what a compression-driver horn is — the throat is a round bolt flange. The flare morphs between the two |
| `sides` | pyramid ends | wall count, default 4. `8` gives the familiar octagon |
| `flare` | horns | `linear` (default) — a straight-walled conical horn — or `exponential`, where the area grows exponentially with depth, as most real horns do |

Both flare laws meet the declared `mouth_m` and `throat_in` exactly, so switching between them changes
the walls and never the sizes. The defaults are chosen so a layout written without these fields builds
the same geometry it always did.

A horn whose two ends differ is oversampled — the rings get enough vertices for the flat walls to bend
into the round end — so a morphing flare costs more polygons than one with a single cross-section. Only
horns that ask for it pay that.

The features are a flat list with `inside` references rather than a nested tree: easier to validate, and
it reads as a parts list.

For a **generated** cabinet the openings are cut into the shell, so a horn's flare is carved out of the
cabinet and its own material forms the walls — which is what a wooden horn is. For one with a
`mesh_override` the CAD already has its holes, so nothing is cut and only the parts behind them are
added.

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

## Mesh overrides

A spec can point at a real mesh instead of letting the builder generate a block. The mesh then
replaces the shell entirely, including the grille and handle recesses — real CAD already models
those better than the builder could.

```yaml
mesh_override: meshes/sub.glb          # shorthand: already in metres, already oriented our way

mesh_override:                         # or spelled out
  path: meshes/sub.obj
  units: mm                            # m (default) | cm | mm
  rotate_deg: [90, 0, 90]              # applied X, then Y, then Z, to reach our axes
  tolerance_m: 0.005                   # how far the mesh may differ from the declared size
```

Importable: `.obj`, `.glb`, `.gltf`, `.stl`, `.ply`, `.blend`. **Not `.FCStd`** — Blender cannot read
FreeCAD, so export from FreeCAD first.

**The spec stays the authority.** After importing, scaling and rotating, the builder measures the
mesh and **fails the build** if any axis differs from `geometry.dimensions_m` by more than the
tolerance, printing all three numbers. A silently mis-scaled cabinet still looks like a cabinet and
would quietly poison every setup built from it — so a mismatch has to be reconciled, not ignored.

Override meshes are third-party files and are **not committed**: `/meshes/` is gitignored. Keep them
there (or anywhere) and record their origin and licence in [sources.md](sources.md).

Worked example: the Flexy's CAD mesh imports correctly with `units: mm` and
`rotate_deg: [90, 0, 90]`, and its depth and height match the spec exactly — but it is 18 mm
narrower, so the builder rejects it. That is the mechanism doing its job.

## What the validator checks

`specs:validate` runs without Blender, so CI runs it too. It rejects:

* dimensions that are zero or negative; a chamfer at or above half the smallest edge
* a grille inset at or beyond half the depth; malformed `#rrggbb` colours
* `weight_kg` of zero or less; `quantity` below 1
* an `id` or `owner` that is not lowercase-dashes; an `id` that does not match its filename or is
  used twice
* a `subtype` that does not belong to its `category`
* `build: clone` without `clone_of` — and `clone_of` on something that is not a clone
* a **clone** whose `provenance.dimensions` or `provenance.weight` is `datasheet`/`plans` but which
  names no `clone_of` to look that up in (factory gear is exempt: its datasheet is its own)
* an unknown `clone_of.reference`
* `flyable` without points, points without `flyable`, `origin: rigging-point` without points
* duplicate rigging point ids, and points outside the cabinet
* a taper field missing for its shape, present on the wrong shape, or larger than the dimension it
  tapers from
* coverage angles outside 0–360; drivers with no size or a count below 1
* in `audio.layout`: a negative `inset_m`; a duplicate feature id; an unknown `kind`; a horn with no
  `throat_in` or a cone with no `diameter_in`; a `depth_m` that is zero or deeper than the cabinet; a
  missing or non-positive mouth; a throat that is not smaller than its mouth; `inside` combined with
  `at_m`, naming an unknown feature, naming one that comes later in the list, or naming something that
  is not a horn; a nested feature wider or deeper than the horn hosting it; a feature whose mouth
  reaches past the edge of the baffle
* an unknown `profile`, `throat_profile` or `flare`; `sides` below 3, on a horn that is elliptical at
  both ends, or on a driver cone; `throat_profile` on a driver cone
* a `mesh_override` whose path does not exist, whose extension Blender cannot import
  (`.FCStd` being the common mistake), whose `units` are unknown, or whose tolerance is negative
