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
  shape: box                  # box | trapezoid | wedge | truss | moving-head | scaffold | load-bay
  dimensions_m:               # outer dimensions, always the true bounding box
    width: 0.80
    height: 0.58
    depth: 0.45
  back_width_m: null          # trapezoid only: width at the back
  front_height_m: null        # wedge only: height at the front
  truss: null                 # truss only: the tubes — see Shapes below
  moving_head: null           # moving-head only: base, yoke and head
  scaffold: null              # scaffold only: posts, bracing and platform
  # load-bay takes its geometry from the `vehicle:` block below rather than from a section here
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
  passband_hz:                # what it covers and how it is driven; orders a `stack`. See below
    low_hz: 35
    high_hz: 1500
    driven_from_hz: 38        # optional: where it is high-passed in practice
    provenance: estimated     # required whenever a passband exists
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

### The passband, and the difference between reach and use

`audio.passband_hz` is what orders the tiers of a [`stack`](scenes.md#stack): lowest first, so the deepest
cabinets end up on the floor carrying everything.

| Field | Meaning |
|-------|---------|
| `low_hz` / `high_hz` | the band the cabinet **covers** |
| `driven_from_hz` | optional — where it is **high-passed in practice**, when that is deliberately not its low corner |
| `provenance` | `measured`, `plans`, `datasheet` or `estimated`. **Required**, for the same reason a baffle layout's is: a frequency is trivial to invent, impossible to check by looking at a render, and it silently decides the order every generated rig comes out in |

**Why two low corners rather than one.** They are two different facts, and collapsing them loses the more
useful one. Our Achenbach 18s reach **35 Hz** — lower than the Flexys' 38 — but they are run from **38** most
of the time, the same corner as the Flexys, deliberately, so that they sit *above* them in a stack rather
than under them. Recorded as a single number, either the cabinet's real capability or the operating choice
has to be thrown away: write 35 and four Achenbachs end up at the bottom of the wall carrying twelve Flexys;
write 38 and the spec now claims the cabinet cannot go below 38, which is untrue and would mislead anyone
reading it for any other purpose. So both are kept, and the solver sorts on `driven_from_hz` where it exists.

**Ties break on the high corner**, and that rule exists for exactly this pair: the Flexy and the Achenbach are
both driven from 38 Hz, and the one that stops sooner (Flexy at 200 Hz against the Achenbach's 1500) is the
more sub-like of the two, so it belongs lower. A spec with no passband at all sorts last within its band and
falls back to how much row the device can make.

The gear list as it stands — the tops carry no passband, because nothing needs one: subs always go below tops,
and the tops all share a single row ordered by width.

| Device | Covers | Driven from |
|--------|--------|-------------|
| `skram` | 15 – 120 Hz | — |
| `flexy-folded-horn-hybrid` | 38 – 200 Hz | — |
| `achenbach-18` | 35 – 1500 Hz | **38 Hz** |

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
| `join.with` | horns | an **earlier** horn this one shares its mouth with. Both must sit on the baffle, be apart on one axis and line up on the other — two horns side by side or stacked |
| `join.depth_m` | horns | how much of the wall between the two is missing, measured from the baffle inwards. Less than either horn's own `depth_m`, so some of the wall survives |

Both flare laws meet the declared `mouth_m` and `throat_in` exactly, so switching between them changes
the walls and never the sizes. The defaults are chosen so a layout written without these fields builds
the same geometry it always did.

A horn whose two ends differ is oversampled — the rings get enough vertices for the flat walls to bend
into the round end — so a morphing flare costs more polygons than one with a single cross-section. Only
horns that ask for it pay that.

The features are a flat list with `inside` references rather than a nested tree: easier to validate, and
it reads as a parts list.

### Two horns on one mouth

`join` is the other relation between features. Where `inside` puts one horn at another's throat, `join`
takes two horns lying next to each other and removes the wall between them from the baffle inwards, so
the front shows **one opening** and the two throats only part company deeper in — which is what a
cabinet with two drivers on one flare looks like:

```yaml
- { id: lf-up, kind: horn, at_m: [ 0.0,  0.103 ], mouth_m: [ 0.45, 0.28 ], throat_in: 10.0, depth_m: 0.20 }
- { id: lf-lo, kind: horn, at_m: [ 0.0, -0.189 ], mouth_m: [ 0.45, 0.28 ], throat_in: 10.0, depth_m: 0.20,
    join: { with: lf-up, depth_m: 0.018 } }
```

Moving the two mouths until they touch does not say this: they still read as two holes with a line
between them, and the wall carries on to the throats. The relation belongs to the pair, so it is stated
on the later of the two, the same way `inside` names the horn it sits in.

Down to `depth_m` the pair is cut as **one common section**, not as two cavities with the strip between
them knocked out. That distinction is the whole difference between a joined pair that reads right and one
that does not: every horn's cross-section narrows towards its throat, so two of them cut separately turn
inwards near the side walls and never meet there, leaving a shelf at each end of the wall. Cut as one
section, the walls run without a break from one horn's far edge to the other's, and the wall between the
two appears only where the join ends — with a blunt nose, because it is a board.

The section is measured off both horns at every depth, on their own flare laws and roundness, so it meets
the flares exactly where it hands back over to them. Its far end rolls off over a 15 mm radius rather than
stopping square, which leaves a fillet where the wall runs into the side walls instead of a sharp inside
corner — that junction is the part of a joined pair you actually look at.

Only a **generated** cabinet can have one. With a `mesh_override` the CAD already cut the holes and the
builder only fills in what sits behind them, so there is no baffle of ours to open up.

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
| `vehicle` | `van`, `trailer` — a transporter, see below |
| `other` | anything — the escape hatch for gear the taxonomy has not caught up with |

### vehicle

**The only category that is a container rather than something to be placed**, and the only one that is measured
twice. Every other spec has one set of dimensions, the true outer bounding box, because the only question anybody
asks of a cabinet is how much room it takes up. A van is asked the opposite question — not what it occupies but what
fits inside it — so `geometry.dimensions_m` stays the outside and a `vehicle:` block declares the inside.

```yaml
category: vehicle
subtype: van

geometry:
  # The outside, as everywhere else. Depth is the fore-aft axis, so a van's length is its depth.
  dimensions_m: { width: 2.070, height: 2.808, depth: 6.848 }

physical:
  # Zulassungsbescheinigung field G, the mass in service. Includes the 75 kg driver by EU definition.
  weight_kg: 2476.0

vehicle:
  # Field F.2, the legally binding permitted gross mass.
  permitted_gross_kg: 3500.0
  load_bay_m:
    width: 1.765
    height: 2.048
    depth: 4.383              # the trade's "Ladelänge"
    width_between_arches: 1.380
    # door_aperture_width / door_aperture_height are optional and usually the binding gate
```

| key | meaning |
|-----|---------|
| `permitted_gross_kg` | **required.** Zulassungsbescheinigung **field F.2**, what the vehicle may not exceed loaded |
| `load_bay_m` | **optional**, the inside. Absent means nobody has measured it, which is the normal state of a van specified from its papers — no registration document states a load bay. A packer refuses such a vehicle by name rather than the validator refusing the spec |
| `load_bay_m.width_between_arches` | the floor between the wheel boxes, and **the dimension that actually decides whether something lies flat**. 385 mm under the bay's own width on our Movano |
| `load_bay_m.door_aperture_width` / `_height` | the rear opening, a third gate. A cabinet that fits the bay and not the doorway does not go in |

**Payload is derived and never stored.** It is `F.2 − G`, worked out by `Vehicle::payloadKg()`. Both halves cite a
numbered field on a document somebody can be shown; their difference cites nothing, so storing it would put a number
in the library that points at no source and would go quietly wrong the day either half is corrected.

**The bay reuses `width`/`height`/`depth` rather than the trade's own words.** A van catalogue says *Ladelänge* and a
reader coming from one will look for `length`. The bay lies along the vehicle's own axes though, so its long
dimension is the same axis as the vehicle's `depth`, and a second vocabulary for one category would mean every
consumer of `Dimensions` having to know which kind of box it had been handed.

**A vehicle is never built into geometry** and never appears in a scene. `models:build` and `library:build` skip it
and say so, and the catalog counts it under `by_category` but leaves it out of every weight and volume total —
because those totals mean *what has to be carried*, and adding two vans took the library from 3493.7 kg to 8269.7.

## Shapes

`box` needs nothing extra. The tapered shapes each need their taper stated outright — deriving it
from a ratio would invent a measurement, which is exactly what `provenance` exists to prevent:

| `shape` | extra field | meaning |
|---------|-------------|---------|
| `trapezoid` | `back_width_m` | narrows towards the back, e.g. an array-able top |
| `wedge` | `front_height_m` | lower at the front, e.g. a floor monitor |
| `truss` | `truss` (a block) | chords and bracing instead of a shell — see below |
| `moving-head` | `moving_head` (a block) | base, yoke arms and head — see below |
| `scaffold` | `scaffold` (a block) | posts, bracing and a platform — see below |
| `load-bay` | the [`vehicle`](#vehicle) block's `load_bay_m` | a transporter's **outline with its load bay caged inside it**. A solid van would be the largest object in any picture that included it and would hide the rig it carries. With no bay measured it draws the outline alone, which is the honest picture of a van nobody has been inside |

The front face stays a full `width × height` (or `width × front_height_m`) rectangle in the first three,
which is why the grille frame works the same way everywhere.

### truss

The first three shapes are one hexahedron with different corners, and their outer dimensions *are* the
object. `truss` is the odd one out: **a truss is mostly air**, so drawing its bounding box would put a
solid wall where a 9 m span should be and hide the whole rig behind it. It is built from tubes instead.

```yaml
geometry:
  shape: truss
  dimensions_m: { width: 2.000, height: 0.258, depth: 0.290 }
  truss:
    chords: 3                 # 2 a ladder, 3 a triangle, 4 a box
    chord_diameter_m: 0.050   # the main tubes
    diagonal_diameter_m: 0.020  # the bracing, always the thinner tube
    bay_length_m: 0.500       # pitch of the zigzag; rounded to whole bays
```

`dimensions_m` is still the true bounding box, and that is deliberate: scene placement, the overlap sweep
in `ShippedScenesTest` and the catalog's shipping volume all read it, and none of them knows a truss from
a subwoofer. The chord centres are derived *inwards* from it by one radius, so the tubes touch its faces.

A segment **lies along X** — `width` is the length of the span and the other two are the cross-section.
Three chords are built apex up; a scene wanting it inverted uses `roll_deg: 180`. The builder skips
everything a truss has none of: grille, handle recesses, chamfer, drivers and the coverage cone.

`bay_length_m` is usually the only unsourced number in an otherwise sourced truss spec — manufacturers
publish tube sizes and weights but rarely the brace pitch. It changes the picture and nothing else.

### moving-head

A rack really is a box and loses nothing by being drawn as one. A **moving head drawn as a box is
unrecognisable**, and four of them hung on a truss would read as four flight cases — so the three parts are
stated and built.

```yaml
geometry:
  shape: moving-head
  dimensions_m: { width: 0.490, height: 0.743, depth: 0.408 }
  moving_head:
    base_height_m: 0.240          # the base, on the floor
    yoke_arm_thickness_m: 0.075   # the two arms, as tubes
    head_diameter_m: 0.300
    head_length_m: 0.430
```

**Pan and tilt are zero — the head points straight up.** That is the pose a datasheet quotes its height in and so
the only pose in which `dimensions_m` is true. Aim is a cue, not a dimension: a scene aims one with `yaw_deg` and
`pitch_deg`, and hangs one upside down under a bar with `roll_deg: 180`.

These four numbers are typically the **estimated part of an otherwise sourced spec**: manufacturers publish the
overall size and the weight but not how the height divides. `provenance.dimensions` is a single field and cannot
say "box sourced, internals estimated", so the spec has to say it in prose.

### scaffold

Open like a truss and drawn from tubes for the same reason. What makes it a scaffold is the **platform**, so that
is the one solid part.

```yaml
geometry:
  shape: scaffold
  dimensions_m: { width: 1.500, height: 5.000, depth: 0.650 }
  scaffold:
    post_diameter_m: 0.050
    brace_diameter_m: 0.025       # always the thinner tube
    platform_height_m: 5.000      # the deck's top face — where somebody stands
    platform_thickness_m: 0.050
```

**`platform_height_m` is not the working height.** A tower sold as "AH7" — *Arbeitshöhe* 7 m — has its platform at
5 m, because the convention adds two metres for a person's reach. That two metres is a fact about people and has no
place in a bounding box: `dimensions_m.height` is the frame, and the validator refuses a platform above it.
Guardrails and rungs are not modelled, so the box stops at the deck.

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
* in `vehicle`: the block missing on `category: vehicle`, or present on anything else; a
  `permitted_gross_kg` at or below `physical.weight_kg`, which means the vehicle may legally carry nothing and is
  always a transcribed digit; a `load_bay_m` axis that is zero, negative, or bigger than the same axis of the
  vehicle itself; a `width_between_arches` wider than the bay; a door aperture bigger than the bay behind it
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
* in `audio.passband_hz`: a `low_hz` of zero or less; a `high_hz` at or below `low_hz`; a `driven_from_hz`
  below `low_hz` (a cabinet cannot be driven lower than it reaches) or at or above `high_hz` (which leaves no
  band at all)
* an unknown `profile`, `throat_profile` or `flare`; `sides` below 3, on a horn that is elliptical at
  both ends, or on a driver cone; `throat_profile` on a driver cone
* a `join` on a driver cone, on a spec with a `mesh_override`, or naming itself, an unknown feature, one
  that comes later in the list, a driver cone, or a feature nested with `inside`; a `join.depth_m` of
  zero or one reaching the throat of the shallower of the two horns; a pair whose mouths already overlap,
  or that line up on neither axis and so have no shared mouth to open
* a `mesh_override` whose path does not exist, whose extension Blender cannot import
  (`.FCStd` being the common mistake), whose `units` are unknown, or whose tolerance is negative
