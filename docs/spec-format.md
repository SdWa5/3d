# Spec format

One YAML file per device under [`specs/`](../specs), at `<category>/<owner>/<id>.yaml`. The file is the single
source of truth: geometry, the catalog and the metadata inside the exported model all come from it.

**The path is filing, not data.** `SpecLoader` reads `specs/` recursively and every fact about a device comes from
the file's own fields, so the category comes from `category:` and the owner from `owner:` rather than from the two
directory levels. The levels exist because the tree is read by people: five systems' gear in one flat folder is a
pile. Nothing enforces that a spec sits in the directory its own fields name, so the two have to be kept in step by
hand, and a spec filed under the wrong owner is filed wrongly rather than broken.

The filename must equal the `id`. Validate with `bin/console specs:validate`.

## Full example

```yaml
id: top-a                     # lowercase-dashes; must match the filename, must not repeat the owner
name: "Top A"                 # human label, shown in the catalog and asset browser
category: speaker             # speaker | truss | rack | stand | vehicle | other
subtype: top                  # see the category table below
quantity: 2                   # how many of these exist — see events/ for how many turn up
owner: sdwa5                  # default sdwa5; lowercase-dashes. Borrowed gear names its owner
build: self-built             # self-built | own-design | original
carried_on: null              # optional: the id of the ONE transporter this may ride on. See below

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
  shape: box                  # box | trapezoid | wedge | truss | moving-head | scaffold | mast | load-bay
  dimensions_m:               # outer dimensions, always the true bounding box
    width: 0.80
    height: 0.58
    depth: 0.45
  back_width_m: null          # trapezoid only: width at the back
  front_height_m: null        # wedge only: height at the front
  truss: null                 # truss only: the tubes — see Shapes below
  moving_head: null           # moving-head only: base, yoke and head
  scaffold: null              # scaffold only: posts, bracing and platform
  mast: null                  # mast only: tubes, legs, winch and adapter of a wind-up stand
  # load-bay takes its geometry from the `vehicle:` block below rather than from a section here
  origin: bottom-center       # bottom-center | rigging-point | geometric-center
  chamfer_m: 0.012            # edge bevel; below half the smallest edge

appearance:
  color: "#111111"            # #rrggbb, sRGB
  front_color: null           # optional: the front face and every opening carved into it except a driver's
                              # bore, on a generated shell. PSL's black cabinets with white fronts
                              # use it, and the ESF without a layout takes it on the face alone
  grille:
    inset_m: 0.014            # how deep the grille sits behind the front; omit for no grille
    color: "#0a0a0a"          # defaults to appearance.color

physical:
  weight_kg: 35.0             # required; a DIY build rarely weighs what the original does
  max_load_kg: 85.0           # optional: what a stand or tower may carry. A truss backdrop is refused above it
  handles: [left, right]      # left | right | back | top — cut as recesses, side ones no deeper than
                              # the wall a baffle opening leaves beside them
  castors: null               # optional: { face: back | left | right, diameter_m, color, locking } — four
                              # wheels near the corners, drawn outside the declared box. See below

transport:                    # optional: how it travels, for the load side. See below
  dimensions_m: null          # the packed box in the device's own axes; mast and scaffold only
  provenance: null            # required with a box
  upright: false              # true: a pack never lays it on a side or an end

rigging:
  flyable: false              # true requires at least one point
  points:                     # positions in the measuring frame (see docs/conventions.md)
    - id: top-front-left
      position_m: [-0.30, -0.15, 0.58]
      thread: M10

audio:                        # optional, but worth filling in from the original's datasheet
  coverage_deg: { horizontal: 90, vertical: 60 }   # also draws the coverage cone; see below
  passband_hz:                # what it covers; orders a `stack`. See below
    low_hz: 35
    high_hz: 1500
    provenance: estimated     # required whenever a passband exists
  power_w:                    # optional: continuous power; decides which sub is the lowest type. See below
    rms: 1800
    provenance: datasheet     # required whenever a power figure exists
  mouth_side: low             # optional: the half of the front a horn mouth opens in. See below
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

### The passband

`audio.passband_hz` is what orders the tiers of a [`stack`](scenes.md#stack): lowest first, so the deepest
cabinets end up on the floor carrying everything.

| Field | Meaning |
|-------|---------|
| `low_hz` / `high_hz` | the band the cabinet **covers** |
| `provenance` | `measured`, `plans`, `datasheet` or `estimated`. **Required**, for the same reason a baffle layout's is: a frequency is trivial to invent, impossible to check by looking at a render, and it silently decides the order every generated rig comes out in |

**Where both cabinets also state [a continuous power](#the-continuous-power), that power decides the order**,
per square metre of front in the pair's lowest octave. Any other pair is ordered on `low_hz`, then on `high_hz`,
then on mass. That takes in every top and every sub without a power figure.

**There used to be a `driven_from_hz`** for where a cabinet is high-passed in practice. It existed so the
Achenbach, which reaches 35 Hz, would sort above the Flexy at 38. Power per area does that on its own, so the key
was removed in 0.137.0 and a spec that still carries it is refused.

The gear list as it stands. **Ours carry none on the tops**, because nothing needs one: subs always go below
tops, and the tops all share a single row ordered by width. PSL's cabinets carry one on every device, tops
included, because PSL publish theirs — a passband is recorded when there is a source for it rather than when
the solver happens to need it.

| Device | Covers |
|--------|--------|
| `skram` | 15 – 120 Hz |
| `concert-audio-esx` | 33 – 220 Hz |
| `thebox-tp218-1600` | 34 – 150 Hz |
| `thebox-tp118-800` | 35 – 150 Hz |
| `achenbach-18` | 35 – 1500 Hz |
| `concert-audio-esf` | 37 – 220 Hz |
| `flexy-folded-horn-hybrid` | 38 – 200 Hz |
| `thebox-pa302` | 40 – 20 000 Hz |
| `thebox-achat-112m` | 60 – 18 000 Hz |
| `thebox-achat-115m` | 60 – 17 000 Hz |
| `concert-audio-ef6` | 70 – 17 000 Hz |
| `hk-linear5-112x` | 79 – 18 000 Hz |

**Sorted by `low_hz`, which is not the order a stack deals the subs in.** Every sub in this table also states a
power figure, so the stack orders them SKRAM, ESX, Flexy, ESF, Achenbach, TP218, TP118, as the next section shows.
The ESX and ESF corners are PSL's figures with the system controller, which ships with the system. Without it
they reach 38 and 40 Hz.

**Nine of our own and every GMSS and Innschleife cabinet have no passband at all**, and that is a refusal
rather than a gap. GMSS's builder stated dimensions and weights and no frequencies; Innschleife's cabinets are
known from a photograph. A spec with no passband sorts last within its band and falls back to how much row the
device can make, so those cabinets are ordered by size — which is a worse answer than a frequency and a much
better one than an invented frequency.

### The continuous power

`audio.power_w` is the continuous electrical power a cabinet takes. Datasheets call it RMS, AES or continuous, and
those are the same claim under different test signals. Programme and peak ratings are not recorded, so a datasheet
that publishes only those gets a value derived from them, `provenance: estimated` and the derivation in a comment.
Concert Audio publish only programme ratings, and the owner chose on 2026-10-02 to read them as continuous rather
than halve them by the usual convention.

| Field | Meaning |
|-------|---------|
| `rms` | watts, greater than 0 |
| `provenance` | `measured`, `plans`, `datasheet` or `estimated`. **Required**, because a wattage is as easy to invent as a frequency and it decides which sub the low-end axis is about |

**What reads it is the fill order and the lowest type.** Per square metre of the cabinet's whole front, in the
lowest octave of each pair, it decides which sub a stack deals first and which one `low_end` puts on the floor or
the centre line. See
[where the low end goes](scenes.md#where-the-low-end-goes). It says nothing about sensitivity, which no spec records.

Each cabinet is taken as flat down to its `low_hz` and falling 24 dB per octave below it, and its level is averaged
as power over the octave above the deeper corner of the pair. The table is in the order a stack deals them, and
every cabinet beats every one below it. The lead is over the next row, in that pair's octave.

| Device | Continuous | Source | W/m² | Lead |
|--------|------------|--------|------|------|
| `skram` | 1800 W | datasheet, Eighteensound 21NLW9601 at 1800 W AES | 3228 | 9.8 dB |
| `concert-audio-esx` | 2800 W | estimated, 2800 W programme read as continuous | 4023 | 0.4 dB |
| `flexy-folded-horn-hybrid` | 1800 W | datasheet, Eighteensound 18NLW9601 and 18NLW9600 | 3991 | 1.2 dB |
| `concert-audio-esf` | 1400 W | estimated, 1400 W programme read as continuous | 3050 | 0.3 dB |
| `achenbach-18` | 1000 W | datasheet, B&C 18TBW100 | 2778 | 0.6 dB |
| `thebox-tp218-1600` | 1600 W | datasheet, 1600 W AES | 2424 | 1.4 dB |
| `thebox-tp118-800` | 600 W | datasheet, 600 W RMS | 1765 | — |
| `wall-bass` | 1600 W | estimated, GMSS's own message | — | — |

The wall bass states no passband, so it is ordered on mass like every other GMSS cabinet.

**The other subs carry none.** GMSS's iq-sub shares one 3000 W figure with the nukes and it cannot be split, the
mid-bass has no audio block, and Innschleife's cabinets are known from a photograph.

### The mouth side

`audio.mouth_side` says which half of an upright front a horn's mouth opens in, `low` or `high`, read with the
cabinet standing on its feet. The Flexy's is `low`, and so is the SKRAM's, whose CAD front is closed from 0.38 m up.
No other spec states one, because no other source shows it.

**What reads it is the mouth pairing.** Two horns turned so their mouths meet act as one larger mouth, and a solved
`stack` turns its cabinets that way wherever it can without moving any of them. A cabinet whose spec states no
mouth side is never turned. See [the mouth pairing](scenes.md#the-mouth-pairing).

Two values rather than a position on the baffle, because the pairing only ever asks which side. A third value is
refused when the spec loads.

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
| `kind` | every feature | `cone` (a driver), `horn` (a flare), `cell` (an open rectangular recess), `fin` (a thin plate behind the baffle), `grille` (a see-through sheet) or `plug` (a dome in front of a horn's throat). See [Cells and fins](#cells-and-fins) and [Grilles and plugs](#grilles-and-plugs) |
| `at_m` | unnested features, nested horns with `setback_m` | `[x, z]` centre in the baffle frame. A set-back nested horn may use it to stand off its host's centre |
| `depth_m` | every feature | how deep it reaches into the cabinet |
| `mouth_m` | horns, cells, fins, square grilles | `[width, height]` of the opening at the baffle, of a fin's front edge or of a grille |
| `throat_in` | horns | throat size in inches, as the audio world names it |
| `driver_in` | horns | puts a driver cone at the throat and bores the chamber through to it, which is what a horn-loaded driver looks like |
| `diameter_in` | cones, plugs, round grilles | the diameter of a plug or a round grille, or the cone's diameter — usually the baffle cut-out rather than the driver's nominal size, since the frame hides behind the panel |
| `inside` | nested features | nests this feature at the named horn's throat, facing forward, or puts a cone or a grille on a cell's back wall. This is how "the HF horn sits inside the LF horn as a phase plug" stays in the data instead of in two hand-matched sets of coordinates |
| `profile` | horns | the **mouth's** cross-section: `pyramid` (default) or `elliptical` |
| `throat_profile` | horns | the **throat's** cross-section, defaulting to the mouth's. `profile: pyramid` with `throat_profile: elliptical` is a horn with straight edges outside and a round throat, which is what a compression-driver horn is — the throat is a round bolt flange. The flare morphs between the two |
| `sides` | pyramid ends | wall count, default 4. `8` gives the familiar octagon |
| `flare` | horns and tilted cells | `linear` (default) — a straight-walled conical horn — or `exponential`, where a horn's area grows exponentially with depth. On a tilted cell, `exponential` bows its back wall forward while preserving its end depths |
| `join.with` | horns | an **earlier** horn this one shares its mouth with. Both must sit on the baffle, be apart on one axis and line up on the other — two horns side by side or stacked |
| `join.depth_m` | horns | how much of the wall between the two is missing, measured from the baffle inwards. Less than either horn's own `depth_m`, so some of the wall survives |
| `throat_blend_m` | horns | how far in front of the throat the mouth's shape starts turning into the throat's. Unstated, it blends over the whole depth. A short blend keeps a straight-edged horn's walls flat, which is how the Tecnare's joined LF pair reads as one piece |
| `angle_deg` | fins, cells | turns a fin's plate about its front edge, or tilts a cell's back wall, default 0 |
| `turn` | fins, cells | `yaw` (about a vertical line) or `pitch` (about a horizontal one). Unstated, it is the longer front edge |
| `mitre` | turned fins | cuts the plate's front and back edges parallel to the depth axis, so mirrored neighbours share one cut face |
| `setback_m` | fins, grilles, nested horns | how far behind the baffle plane a fin starts, default 0. A grille's is measured from the cabinet's front face. A nested horn's puts its mouth that far behind its host's, instead of ending it at the host's throat |
| `dome_m` | round grilles | how far the sheet's middle bulges forward of its edge, above 0 and at most 0.03 m. Unstated, the sheet is flat |
| `rim_m` | round grilles | the width of a solid ring round the sheet's edge, standing 8 mm proud of it. Above 0 and below the grille's radius |
| `rim_color` | round grilles with `rim_m` | `#rrggbb` for the ring. Unstated, it takes the grille's own colour |
| `color` | every feature | `#rrggbb` for what the feature shows of its own. That is a cone's paper, a cell's back wall, a fin's plate, or the driver at a horn's throat, which is why a horn needs `driver_in` to take one. Without it the feature takes `appearance.color` |

Both flare laws meet the declared `mouth_m` and `throat_in` exactly, so switching between them changes
the walls and never the sizes. The defaults are chosen so a layout written without these fields builds
the same geometry it always did.

A horn whose two ends differ is oversampled — the rings get enough vertices for the flat walls to bend
into the round end — so a morphing flare costs more polygons than one with a single cross-section. Only
horns that ask for it pay that.

The features are a flat list with `inside` references rather than a nested tree: easier to validate, and
it reads as a parts list.

### Cells and fins

A **cell** is a straight-walled rectangular recess, cut like a horn whose mouth and throat are the same
size. It is what the open mouths of a folded horn and the slots between drivers look like from the front.
It takes `at_m`, `mouth_m` and `depth_m`, and with a `color` it gets a thin back panel in that colour while
its side walls stay the cabinet's. That is how a black cabinet with blue panels inside is written.

A **fin** is a plate standing behind the baffle plane, such as a divider, a brace or a splitter. It is
drawn as a part of its own rather than cut, so it needs a cell, a horn or an imported mouth around it to
be seen at all. `at_m` is the centre of its front edge and `mouth_m` is that edge's `[width, height]`.
The longer of the two is the fin's axis, so a fin is vertical when it is at least as tall as it is wide.
`depth_m` is how far the plate runs back.

```yaml
- { id: slot,  kind: cell, at_m: [ 0.0, 0.0 ], mouth_m: [ 0.54, 0.27 ], depth_m: 0.35, color: "#1b3f8f" }
- { id: brace, kind: fin,  at_m: [ 0.0, 0.0 ], mouth_m: [ 0.02, 0.27 ], depth_m: 0.02, setback_m: 0.08,
    angle_deg: 30, color: "#37822f" }
```

`angle_deg` turns the plate about its front edge. A positive angle swings its back towards +x on a
vertical fin and towards +z on a horizontal one. A turned plate's front corner would poke out through the
baffle, so it is shifted back until its foremost corner touches the plane, and `setback_m` moves it
further back from there. The validator checks the extent the turned plate actually reaches, using the same
arithmetic as the builder (`BaffleFeature::finFootprint()`). Several fins make up a bent bar, and the
Flexy's VVV brace is six of them.

A cell's back wall tilts with `angle_deg`. It still passes through `depth_m` at the cell's centre and lies
deeper towards +x on a `yaw` and towards +z on a `pitch` for a positive angle. A cone placed `inside` such a
cell sits at the centre of that wall and faces along it, which is how the ESX's drivers on their 45° walls are
written. A tilted cell with `flare: exponential` bows forward between the same two end depths, while its other
walls stay flat. This forms the Innschleife tops' curved port floor. A cone or grille needs a flat wall, so it cannot
sit on this bowed cell wall. The cell needs `angle_deg` for an exponential wall.

A nested horn with `setback_m` may state `at_m` in the baffle frame. Its mouth can then sit off its host's centre,
as the HF horn does near the upper edge of the TMS-2's port. The validator checks that its mouth still fits inside
the host. Other nested features keep the host's centre and cannot state `at_m`.

With `mitre: true` a turned plate's front and back edges are cut parallel to the depth axis instead of square
to the plate. Two mirrored neighbours then share one cut face, and a zigzag of them has a single point at every
corner. The Flexy's VVV brace is built that way.

Cells and fins have no throat and no driver, so `throat_in`, `diameter_in`, `driver_in` and `inside` are
refused on them. On a cabinet with a `mesh_override` nothing is cut, so a cell draws nothing there, while
a fin is added as usual.

### Grilles and plugs

A **grille** is a see-through sheet of perforated metal or mesh. It is rectangular with `mouth_m` or round
with `diameter_in`, and `depth_m` is its thickness, at most 0.01 m. On the baffle it stands `setback_m` behind
the cabinet's front face, so it can sit in front of an inset baffle. With `inside` a cell it sits 12 mm in
front of that cell's back wall and tilts with it, which is how a grille over a driver on a sloping wall is
written. Its colour is its own `color`, then `appearance.grille.color`, then the body's.

```yaml
- { id: grille, kind: grille, at_m: [ 0.0, 0.0 ], mouth_m: [ 0.564, 0.564 ], depth_m: 0.0015, setback_m: 0.001 }
- { id: grille-up, kind: grille, inside: horn-up, diameter_in: 17, depth_m: 0.002, dome_m: 0.02, rim_m: 0.015, color: "#3a3a3a", rim_color: "#141414" }
```

A round grille may bulge forward by `dome_m` and carry a solid ring `rim_m` wide round its edge, which is what
a pressed speaker grille looks like. A black sheet over a black cone vanishes at scene distance, and the ESX's
dark grey dome inside a black rim reads as a grille from across the room.

The holes are punched by the material, as a 5 mm square grid with half the sheet open. Scenes render from
the .blend, which keeps them. A glTF export cannot carry the procedural alpha and shows the sheet solid.

A **plug** is a solid dome `diameter_in` across and `depth_m` tall, standing forward from the throat of the
horn it sits `inside`. It is the phase plug in front of a cone driver, as on the TMS-2's TurboMid.

```yaml
- { id: mid-plug, kind: plug, inside: mid, diameter_in: 7, depth_m: 0.08 }
```

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

## Castors

`physical.castors` puts four wheels on one face, one near each corner, and brakes the first `locking` of them,
the two nearer the floor. Each is a plate, a swivel, a fork and a wheel in `color`. They stand
`1.28 × diameter_m` off the face, which is a catalogue castor's height for its wheel.

```yaml
physical:
  castors: { face: back, diameter_m: 0.10, color: "#1f4fa8", locking: 2 }
```

**This is the one part of a model that lies outside its declared box.** A castor is what a cabinet rolls on,
and a datasheet states a cabinet "ohne Rollen" for the same reason. The metadata carries `protrusion_m`, and
`tools/check-glb.py` allows exactly that much on the face's axis. A scene still packs cabinets by their box,
so two cabinets back to back can show their wheels touching. Only the back and the sides can carry wheels,
because wheels under the bottom would change the height every stack is built from.

## Transport

**A device has an erected size and a transport size, and `dimensions_m` is the erected one.** A scene needs the Wind
Up stand at its working 4 m, and a van needs it at the 1.75 m it folds to. Until 0.148.0 one field carried both, so
the packed convoy showed a mast standing out of the trailer. The optional `transport:` block holds the second size.

```yaml
transport:
  dimensions_m: { width: 0.240, height: 1.750, depth: 0.300 }
  provenance: estimated
  upright: false
```

**The box is in the device's own axes**, so the folded stand is still 1.75 m along its own height. Laying it down is
the pack's decision and not a fact about the stand. Everything on the load side reads the transport box when there is
one, which means `load:plan`'s volume, `scene:pack` and the catalog's shipping volume. Scenes keep the erected box.

**Only a shape that can draw itself packed may state a box that differs from the erected one**, which today means
`mast` and `scaffold`. The model then carries a second collection, `<id>@packed`, beside the erected one. The mast folds
its legs up along the sleeve and the scaffold is drawn as its frames and decks bundled on edge. A scene placement shows
it with `packed: true`, which cannot be combined with `extend_to_m` because the two are different states of one stand.
A box stated on any other shape is refused rather than drawn wrong.

**On a mast the packed height must equal `mast.transport_length_m`**, so the two figures cannot drift apart.

**A packed box names its provenance**, the way the erected one does. Neither of ours was published as a package size.
The Wind Up's 1.75 m is the datasheet's and its cross-section is half of a case for two stands. The scaffold bundle is
worked out from Krause's parts list for the ClimTec AH 7. Both therefore say `estimated`, see
[sources.md](sources.md).

**`upright: true` is a statement by the owner**, and it needs no box. The two racks and the generator carry it, so a
pack turns them about the vertical only. Every other device may be turned onto a side or an end.

## Categories and subtypes

| `category` | allowed `subtype` |
|------------|-------------------|
| `speaker` | `top`, `sub`, `monitor`, `line-array-element` |
| `truss` | `straight`, `corner`, `base`, `tower` |
| `rack` | `amp`, `network`, `shipping` |
| `stand` | `speaker-pole`, `tripod`, `riser` |
| `vehicle` | `van`, `trailer` — a transporter, see below |
| `other` | anything — the escape hatch for gear the taxonomy has not caught up with |

### carried_on — the device that can only ride on one bin

**A pack is not free to put every device anywhere, and until this field existed it assumed otherwise.** Sepp's 465 kg
generator is the case: two people cannot lift it, it needs a ramp or a forklift, and it has no business inside a van.
`load:plan` scores bins by how strained they are, and a 550 kg trailer holding a 465 kg generator is by far the most
strained — so left to the score it put the generator in a **van** and filled the trailer with speaker cabinets. A
plan that passes every weight check and that nobody can load.

```yaml
carried_on: trailer-750kg
```

One transporter rather than a list, deliberately. Every case anybody has is "this rides on that", and a list would
invite a set of permissions nobody can state.

Refused if it names something that is not a transporter in this library, and refused on a transporter itself, since a
trailer is not cargo. That check is cross-spec and matters more than it sounds: the planner **leaves a pinned device
behind** when it cannot find its bin, so a typo would quietly turn "this rides on the trailer" into "this does not
travel" while the load plan looked complete.

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
| `mast` | `mast` (a block) | a wind-up stand: telescoping tubes on a tripod, a winch and a truss adapter — see below |
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

### mast

A wind-up stand, our Varytec Wind Up being the case. Round tubes slide inside each other on a tripod, a winch on the
outer sleeve cranks them, and an optional truss adapter sits on the top stage.

```yaml
geometry:
  shape: mast
  dimensions_m: { width: 0.203, height: 4.000, depth: 0.203 }
  origin: bottom-center           # required, the stand stands on the floor
  mast:
    sections_m: [0.060, 0.050, 0.040]   # tube diameters, the sleeve first, strictly decreasing
    transport_length_m: 1.750     # folded length, adapter included
    min_height_m: 2.050           # the lowest it cranks to, as published; the sleeve's foot follows from it
    hub_height_m: 0.850           # where the legs hinge
    spigot_diameter_m: 0.035
    legs: 3
    base_spread_m: 1.500
    leg_width_m: 0.030
    leg_yaw_deg: 90               # one leg straight back, +Y
    winch: true
    adapter: { length_m: 0.400, bar_m: 0.040, height_m: 0.120, clamp_spacing_m: 0.240 }
```

**The bounding box is the mast column at full extension, and the legs reach outside it.** Placement, the overlap
sweep and every scene check read only the box, so they do not see the legs. The legs stay within the
`base_spread_m` square in plan view, nothing goes above `height` or below the floor, and `tools/check-glb.py`
allows width and depth up to the spread for that reason. A backdrop keeps the legs clear of the rig by distance,
see `StackBackdrop::OUTRIGGER_CLEARANCE_M`.

**`height` is taken at the adapter's top face**, where the truss rests. Each tube is the transport length less the
adapter, and the PHP side works out the travel per stage, the overlap and the collapsed height into the build plan,
so the Blender side derives nothing.

**Cranking slides the stages.** An `extend_to_m` below full height moves stage k of N down by k/N of the loss, so
every joint keeps the same overlap and the base keeps its size. A tower still drawn as a box stretches as before.
**`min_height_m` is the published minimum, and the sleeve's foot is worked out from it.** Fully cranked down the stand
is its folded length standing on the sleeve's foot, so the foot sits `min_height_m − transport_length_m` off the
floor. Ours is the manual's 2.05 m, which puts the foot at 0.30 m. A scene or a backdrop that needs less is refused.
Before 0.150.0 the foot sat at half the hub height, and the minimum that followed was 2.225 m, which no document gave.

**The hub has to leave room for the legs to fold.** Folded, each leg swings up from the hub along the sleeve, so the
hub less the foot plus a leg must stay inside the folded length. `models:build` fails a packed drawing that leaves
its transport box. The folded stand is a different thing, stated in
[`transport:`](#transport) and drawn as `<id>@packed`.

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

Worked example: the Flexy's original CAD export measures 0.573 m across where the cabinet is 0.591 m,
so the builder rejects it. That is the mechanism doing its job. The `flexy-1to10.stl` export it uses now
measures 0.588 m and passes within the 5 mm tolerance.

### Painting and removing parts of an imported mesh

A CAD mesh is one material and shows whatever the CAD modelled. Two lists under `mesh_override` change
that without editing the file.

```yaml
mesh_override:
  path: meshes/flexy-1to10.stl
  paint:
    - { id: port-left, at_m: [ -0.142, -0.340 ], size_m: [ 0.104, 0.100 ], setback_m: 0.0563, depth_m: 0.019,
        color: "#37822f" }
  remove:
    - { id: cross-plank-front, x_m: [ -0.276, 0.276 ],
        section_m: [ [ -0.008, -0.162 ], [ 0.130, -0.162 ], [ 0.130, -0.1215 ], [ -0.008, -0.1215 ] ] }
```

`paint` recolours every face inside a box. `at_m` is the box's `[x, z]` centre measured from the centre of
the bounding box's front face, `size_m` is its `[width, height]`, and it reaches from `setback_m` behind
the bounding box's front plane to `depth_m` further back. The front plane is the bounding box's and not the
layout's `inset_m`. The mesh is cut along the box's six planes first, so the paint stops at the box and not
at whatever triangle the CAD happened to make. A region that paints no face at all fails the build.

`remove` cuts parts away. Each entry is a prism, with `section_m` its side view as a list of
`[setback, z]` corners, using the same frame as `paint`, and `x_m` the `[from, to]` range it is extruded
across. Setbacks may be negative, so a prism can start in the air in front of the cabinet. Each prism is
subtracted in an exact boolean of its own after the mesh is repaired, and closed pieces that end up lying
wholly inside the prisms are deleted afterwards. A removal that leaves the face count unchanged fails the
build.

A prism face that meets a remaining panel must lie **exactly** in that panel's plane. One that stops
short leaves a visible step, and one that reaches past grooves the panel. Read the planes off the mesh's
own vertices rather than off a measurement of the render. Removals run before painting, so a region
painted afterwards sees the finished shell.

## Front images

A photograph of the cabinet's front, mapped onto the generated block's front face. The point is
**recognition rather than accuracy**: five Innschleife cabinets currently differ only in their
bounding box, and every borrowed cabinet is one nobody here has seen, so a render of them is correct
and unrecognisable at the same time.

```yaml
front_image: meshes/innschleife/tms4-front.png   # shorthand: already upright, 1 px = 1 cm

front_image:                                     # or spelled out
  path: meshes/psl/PSL_Subs_px.png
  rotate_deg: 90                                 # 0 (default) | 90 | 180 | 270
  px_per_cm: 1.0                                 # the scale the drawing was made at
  tolerance: 0.10                                # how far the implied size may miss the cabinet
  cutout: false                                  # true cuts the whole panel along a PNG's alpha
```

**`cutout: true` gives the object the image's outline.** The PNG's alpha cuts the front, and the back and the edges
take the body colour cut along the same outline, on UVs projected straight back from the front. PSL's deco panel is
cut to its motif this way, so the truss behind it shows through the gaps. The cut edges have no wall of their own,
which shows at a grazing angle only. Only a PNG carries an alpha channel here, so a cut-out on a JPEG is refused.

**Applied whenever the file exists**, stated by the owner on 2026-10-01. A spec that names an image whose file is
not in `meshes/` builds plain, since the photographs are not committed. Each model records the image it was built
with in `build/glb/built-with.json`, so an image that appears or goes later rebuilds the model, which the mtimes
alone would miss. Up to 0.135.0 the images waited for a `--front-images` switch that `build:all` never passed, so a
full build lost the deco panel's print.

**Only a cabinet with no interior may have one**, and the validator refuses the other two cases
rather than skipping them quietly:

| The spec has | Why it is refused |
|---|---|
| an `audio.layout` | the baffle has real openings cut into it, and a photograph over them fights geometry that is already there |
| a `mesh_override` | the imported shell decides where its own front is, so there is no front plane this builder knows to put the image on |

**The size is checked against the cabinet.** PSL's fronts are drawn at 1 px = 1 cm, which is what
makes that possible: a 50 × 114 px image at 1 px/cm implies a 0.50 × 1.14 m front, and the validator
compares both axes against `geometry.dimensions_m` within the tolerance. Both axes rather than the
aspect ratio, because an aspect check passes a photograph of a cabinet twice the size — exactly the
mix-up worth catching in a fleet where several borrowed subs share a shape and differ only in how
big they are. A quarter turn swaps the axes before the comparison, so the rotation and the check
agree.

**`rotate_deg` is about the file, not the cabinet.** A rolled cabinet's front is still its front and
the texture turns with the mesh, so nothing has to be said for that. What does need saying is which
way up the photograph was taken, because a photograph of a cabinet lying down has to be turned to
match a model built upright. Only right angles are accepted: a photograph off a right angle was not
taken square to the cabinet, and the fix for that is a better crop rather than a number here that
quietly hides it.

Front images are **not committed**, for the same two reasons override meshes are not: they are binary,
and they are as often as not somebody else's photograph. `/meshes/` is gitignored, a spec naming a
file this checkout lacks is a warning rather than an error, and the front simply stays plain. Record
the origin and licence in [sources.md](sources.md).

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
* in `mast`: a non-positive value; an origin other than `bottom-center`; fewer than two sections, sections that
  do not strictly decrease, or a sleeve wider than the column; a spigot that does not fit the top stage; a
  `min_height_m` not below the full height or not above the transport length; a hub at or below the sleeve's foot,
  or above the sleeve's top; stages that overlap by less than
  0.20 m at full extension; legs outside 3 to 8; a spread narrower than the column; an adapter longer than the
  spread, with its clamps off its bar, or too low to leave a spigot
* in `transport`: an unknown key; a box without `provenance`; a box axis that is zero or negative; a box that
  differs from `dimensions_m` on a shape other than `mast` or `scaffold`; and on a mast, a box height other than
  `mast.transport_length_m`
* coverage angles outside 0–360; drivers with no size or a count below 1
* in `audio.layout`: a negative `inset_m`; a duplicate feature id; an unknown `kind`; a horn with no
  `throat_in` or a cone with no `diameter_in`; a `depth_m` that is zero or deeper than the cabinet; a
  missing or non-positive mouth; a throat that is not smaller than its mouth; `inside` combined with
  `at_m`, naming an unknown feature, naming one that comes later in the list, or naming something that
  is not a horn; a nested feature wider or deeper than the horn hosting it; a feature whose mouth
  reaches past the edge of the baffle; an unknown key on a feature
* on a cell or fin: `throat_in`, `diameter_in`, `driver_in` or `inside`. On anything but a fin or a cell:
  `angle_deg` or `turn`. A `turn` other than `yaw` or `pitch`, a `mitre` on anything but a turned fin, and a
  tilted cell whose back wall comes out of the front or reaches past the back. `setback_m` on anything but a
  fin, a grille on the baffle or a horn nested in another, a negative one, and a nested horn set back so far
  that it reaches past its host's throat. A `throat_blend_m` on anything but a horn, or one that is not above
  0 and at most the horn's depth. A fin none of whose edges is at most 0.05 m, or one that reaches past the
  baffle or behind the cabinet once turned and set back. On any feature: a `color` that is not `#rrggbb`,
  or a `color` on a horn without `driver_in`
* on a grille: both or neither of `mouth_m` and `diameter_in`, a thickness above 0.01 m, `inside` anything
  but a cell, a size that does not fit the cell's back wall, or a setback that reaches past the cabinet. On a
  plug: no `diameter_in`, a `mouth_m`, no `inside`, or a host that is not a horn. On either: `throat_in`,
  `driver_in` or `join`. `dome_m`, `rim_m` or `rim_color` on anything but a round grille, a dome not above 0
  and at most 0.03 m, a rim not above 0 and below the grille's radius, a `rim_color` without `rim_m`, or one
  that is not `#rrggbb`
* `appearance.front_color` that is not `#rrggbb`, on a spec with a `mesh_override`, or beside a `front_image`,
  which covers the same face
* in `physical.castors`: an unknown key; a face other than `back`, `left` or `right`; a diameter that is not
  above 0 and at most 0.2 m; a `locking` count outside 0 to 4; a colour that is not `#rrggbb`; and wheels too
  big for four of them to fit the face
* in `audio.passband_hz`: a `low_hz` of zero or less; a `high_hz` at or below `low_hz`
* in `audio.power_w`: an `rms` of zero or less
* an unknown `profile`, `throat_profile` or `flare`; `sides` below 3, on a horn that is elliptical at
  both ends, or on a driver cone; `throat_profile` on a driver cone
* a `join` on a driver cone, on a spec with a `mesh_override`, or naming itself, an unknown feature, one
  that comes later in the list, a driver cone, or a feature nested with `inside`; a `join.depth_m` of
  zero or one reaching the throat of the shallower of the two horns; a pair whose mouths already overlap,
  or that line up on neither axis and so have no shared mouth to open
* a `mesh_override` whose path does not exist, whose extension Blender cannot import
  (`.FCStd` being the common mistake), whose `units` are unknown, or whose tolerance is negative
* in `mesh_override.paint`: an unknown key; a `color` that is not `#rrggbb`; a size or depth that is zero
  or less; a negative `setback_m`; a box that reaches past the front face or past the cabinet's depth
* in `mesh_override.remove`: an unknown key; an `x_m` that does not run from lower to higher or that
  misses the cabinet; a section with fewer than three corners, one that encloses no area, or one that lies
  wholly outside the cabinet
* a `front_image` on a spec that has an `audio.layout` or a `mesh_override`; one whose extension is not
  `.png`, `.jpg` or `.jpeg`; a `cutout` on anything but a `.png`; a `rotate_deg` that is not 0, 90, 180 or 270; a `px_per_cm` of zero or
  less; a file that cannot be read as an image; and an image whose implied size misses the cabinet's
  front by more than its tolerance. A path that does not exist is a **warning**, not an error, exactly
  as for a mesh override

## Semantic validation

`SpecValidator::validate()` collects violations in the existing order. Asset, layout, physical, shape and vehicle
checks live in dedicated validators. `ValidationRules` shares identifier, colour and component-fit constants.
Parsing remains in the spec value objects, and the validator split changes no accepted fields or error messages.
