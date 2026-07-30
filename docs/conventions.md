# Conventions

The rules every model in this library follows. They exist so pieces built from different specs,
at different times, still stack, snap and scale together — without them a "library" is just a pile
of unrelated meshes.

## Units

Metres, everywhere. 1 Blender unit = 1 m, scene unit system metric, unit scale 1.0.

| Quantity | Unit | Spec field |
|----------|------|------------|
| Dimensions | m | `geometry.dimensions_m` |
| Chamfer, grille inset | m | `geometry.chamfer_m`, `appearance.grille.inset_m` |
| Rigging positions | m | `rigging.points[].position_m` |
| Weight | kg | `physical.weight_kg` |
| Driver size | inch | `audio.drivers[].size_in` |
| Coverage | degrees | `audio.coverage_deg` |

Driver sizes stay in inches because that is what they are called in the audio world — a 15" woofer
is not a 0.381 m woofer to anyone reading a spec sheet.

## Axes and orientation

* **+Z up**
* **Cabinet front faces −Y** — the direction the speaker points
* **+X is the cabinet's right**, seen from the front

This matches Blender's front view (numpad 1), which looks along +Y, so a front view shows the
speaker's front. glTF is Y-up, and the exporter converts on the way out; the scene itself stays
Z-up. Nothing in the specs ever needs to think about glTF's axes.

## Origin

`geometry.origin` decides where (0, 0, 0) sits in the finished model:

| Value | Origin at | Use for |
|-------|-----------|---------|
| `bottom-center` (default) | middle of the footprint, on the floor | anything ground-stacked |
| `rigging-point` | the first rigging point | flown cabinets |
| `geometric-center` | centre of the bounding box | odd cases; rarely what you want |

`bottom-center` is the default because it makes stacking work with no fiddling: drop a sub at
z = 0 and it stands on the floor, put a top at the sub's height and it sits on it.

### Positions are always measured from the footprint centre

Independently of `origin`, every position in a spec — rigging points above all — is given in the
**measuring frame**: middle of the footprint, on the floor, +Z up, front towards −Y. That is the
frame a tape measure gives you, and it means a position never has to be recomputed when the origin
changes. `origin` only moves the finished model; it never reinterprets the numbers.

## Naming

One name, used everywhere: spec filename == `id` == Blender collection == Blender body object ==
exported `.glb` basename. `specs/speakers/top-a.yaml` produces `build/glb/top-a.glb` containing a
collection `top-a`. `specs:validate` enforces the filename half of that, and duplicate ids are
rejected — two devices with one id would silently overwrite each other's model.

Ids are lowercase words separated by single dashes: `top-a`, `sub-18-horn`.

## Materials

Every model uses the same small material set, so the whole library reacts to light identically and
a colour scheme can be changed in one place:

| Material | Used for |
|----------|----------|
| `sdwa5-cabinet` | the shell and the grille frame; colour from `appearance.color` |
| `sdwa5-grille` | the grille panel; colour from `appearance.grille.color` |
| `sdwa5-handle` | handle recesses |
| `sdwa5-rigging` | rigging point markers |
| `sdwa5-estimated` | the orange tag on guessed cabinets |

Hex colours in specs are sRGB and are converted to linear on the way in — skipping that makes
every model noticeably too bright.

## Fidelity — "block level"

Models are accurate on the outside and empty on the inside:

* true outer dimensions, to the millimetre the spec claims
* chamfered edges (bevel modifier, kept live in the `.blend`, applied on export)
* recessed grille panel behind a four-bar frame, when `appearance.grille.inset_m` is set
* handle recesses cut into the sides listed in `physical.handles`
* small markers at the rigging points

No drivers, ports, bracing or wiring. Detail can be raised for a single device later — either by
extending the builder or by pointing `mesh_override` at a hand-made mesh — without changing any
dimension, because the spec stays the authority. (`mesh_override` is validated and reaches the build
plan, but the geometry builder does not act on it yet: [`../TODO.md`](../TODO.md) item 10.)

Two deliberate consequences:

* The **outer bounding box always equals `width × depth × height`**. The grille frame is what
  fills the inset, so a grille never makes a cabinet deeper than declared; handle recesses cut
  inward, so they never make one wider.
* **Markers do not render.** Rigging markers and the estimated tag are set to render-invisible:
  they exist to snap to and to nag, not to turn up in a preview image handed to the crew.

## Provenance

Most SdWa5 cabinets are DIY builds of commercial designs, so a number in a spec can come from four
different places and they are not equally trustworthy. `provenance` says which:

| Value | Means | Trust |
|-------|-------|-------|
| `measured` | somebody measured the actual cabinet | the real thing |
| `plans` | taken from the build plans it was made from | very close |
| `datasheet` | taken from the original's datasheet | good enough to design with |
| `estimated` | guessed, e.g. off a photo | placeholder |

`datasheet` and `plans` require `clone_of` to name the original — otherwise there is nothing to
look the numbers up in. `estimated` models are tagged in the viewport and listed by `catalog`.

Measuring a cabinet promotes its spec to `measured` and changes nothing else. Weight and outer
dimensions drift most between a DIY build and its original, so they are worth measuring first.

## Metadata travels with the model

Id, category, provenance, dimensions, weight, rigging points and coverage are attached to the body
object as custom properties and exported into the glTF `extras` block, as both a JSON string and a
handful of flat scalars. A `.glb` from this repo is therefore still self-describing after it leaves
it — whoever opens it can see what it is, what it weighs and whether anyone ever measured it.

## Why glTF 2.0

It is Blender-native, metric, and the model format embedded in **GDTF/MVR** — the entertainment
industry's scene exchange standard used by Vectorworks, grandMA3, Depence and Capture. GDTF/MVR
already covers truss and rigging and has audio speakers on its roadmap, so glTF keeps a path open
to real stage-design software. FBX, OBJ and SKP are all dead ends by comparison.
