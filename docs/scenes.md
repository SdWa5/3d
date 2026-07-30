# Scenes

A PA setup written down instead of assembled by hand. One YAML file per setup in
[`scenes/`](../scenes); `scene:build` turns it into `build/scenes/<id>.blend`.

The reason to write a setup down rather than drag cabinets around in Blender: it becomes reviewable
and repeatable. A layout that worked at an event is a commit, next year's variation is a diff, and
"what if we used four fewer subs" is one edit and a rebuild.

## Example

```yaml
id: full-rig
name: "Full rig — 14 subs, 3 tops"

placements:
  - id: sub-row-bottom
    device: flexy-folded-horn-hybrid
    at: [-2.135, 0.0]
    repeat: { count: 7, step: [0.611, 0.0, 0.0] }

  - id: sub-row-top
    device: flexy-folded-horn-hybrid
    on: sub-row-bottom
    at: [-2.135, 0.0]
    repeat: { count: 7, step: [0.611, 0.0, 0.0] }

  - id: top-left
    device: tecnare-m2122
    on: sub-row-top
    at: [-2.135, 0.0]
```

## Fields

| Field | Meaning |
|-------|---------|
| `id` | scene id; must match the filename |
| `name` | human label |
| `placements[].id` | name for this placement, referenced by `on`. Defaults to `placement-<n>` |
| `placements[].device` | a device `id` from [`specs/`](../specs) |
| `placements[].at` | ground position `[x, y]` in metres |
| `placements[].on` | sit on top of an **earlier** placement; z is worked out from the specs |
| `placements[].yaw_deg` | rotation about Z — aiming. 0 faces −Y, the convention every model uses |
| `placements[].roll_deg` | rotation about the front-to-back axis — 180 turns a cabinet upside down while it keeps facing forward |
| `placements[].repeat` | `{ count, step: [x, y, z] }` — repeat along a vector |
| `notes` | anything worth knowing |

Coordinates follow [conventions.md](conventions.md): metres, X right, Y depth, Z up, cabinets face
−Y. A placement's position is the **bottom-center** of the cabinet, matching the default origin.

## Why `on` and `repeat` matter

**`on` means no height is ever written into a scene.** It reads the supporting cabinet's height from
its spec, so when somebody finally measures the Flexys and the height changes by 8 mm, every stack in
every scene corrects itself. Hard-coded z values would all quietly become wrong.

**`repeat` makes a sub wall two lines.** Fourteen cabinets as fourteen entries would be unreadable and
unmaintainable; as two rows of seven it is obvious what the setup *is*.

A repeated placement can be stacked on another repeated placement — the row above resolves against the
row below. Give the upper row its own `at` so it starts where you want; without one it inherits the
position of the last cabinet in the row below.

## Mirrored horn pairs

`roll_deg: 180` turns a cabinet over without turning it away, which is how horn-loaded subs get
stacked in mirrored pairs so two mouths meet and behave as one larger mouth:

```yaml
  - id: sub-row-bottom
    device: flexy-folded-horn-hybrid
    at: [-2.135, 0.0]
    roll_deg: 180            # turned over, so its mouths point up at the seam
    repeat: { count: 7, step: [0.611, 0.0, 0.0] }

  - id: sub-row-top
    device: flexy-folded-horn-hybrid
    on: sub-row-bottom
    at: [-2.135, 0.0]
    repeat: { count: 7, step: [0.611, 0.0, 0.0] }
```

`scenes/full-rig-mirrored.yaml` is exactly `full-rig.yaml` with that one line added — which is the
argument for keeping setups as files rather than as Blender scenes.

Which row to flip is not obvious and depends on where the mouth sits on the cabinet's face. A Flexy's
mouths are in the *lower* part of its front, so the **bottom** row is the one to turn over; flipping the
top row instead drives the mouths apart. Cheaper to discover in a render than on site.

Two things the compiler handles so this stays honest:

* A rolled cabinet is **lifted back onto its slot**. Geometry runs from z = 0 to the cabinet's height
  in its own frame, so turning it over would otherwise sink it through the floor.
* `on` and the reports use the **rolled** extent, so a cabinet on its side is treated as tall as it is
  wide and anything stacked on it still lands correctly.

## What it tells you before Blender opens

```
Cabinets:      17
Total weight:  1394.0 kg
Tallest stack: 2.486 m
Footprint:     4.26 × 0.96 m
  flexy-folded-horn-hybrid   14 × =  1190.0 kg
  tecnare-m2122               2 × =   136.0 kg
  owner sdwa5                17 cabinets, 1394.0 kg
```

Plus warnings that are cheap here and expensive on site:

* **more cabinets than we own** — easy to do by copying a stack; the report compares against each
  spec's `quantity`
* **borrowed gear** — any placement whose device has an `owner` other than `sdwa5`, so a setup cannot
  silently depend on someone else's cabinets
* **un-measured cabinets** — positions computed from design figures rather than measurements

`--dry-run` prints all of that without running Blender, which is also how CI checks scenes.

## Usage

```bash
ddev exec bin/console scene:build                    # every scene
ddev exec bin/console scene:build full-rig           # one, by id
ddev exec bin/console scene:build --dry-run          # report only, no Blender
```

Models have to exist first: the command refuses rather than assemble a scene from stale models, so run
`models:build` after changing a spec.

Each device's collection is appended **once** and instanced per placement, so a 14-cabinet wall costs
one copy of the geometry — the shipped 17-cabinet scene is under 100 KB.

## Rendering

```bash
ddev exec bin/console scene:render full-rig                       # three-quarter / studio, 1600x900
ddev exec bin/console scene:render full-rig -c crowd -l stage     # eye height, event lighting
ddev exec bin/console scene:render full-rig -c top -l daylight    # plan view on grass
ddev exec bin/console scene:render --presets                      # list every preset
```

Output goes to `build/renders/<scene>-<camera>.png`. On the container's CPU a 17-cabinet scene takes
about 8 seconds at the default 64 samples.

### Camera presets (`-c`, `--camera`)

| Preset | Looks from | Lens |
|--------|-----------|------|
| `three-quarter` *(default)* | front right, slightly above | 42 mm |
| `front` | straight on, as the audience sees it | 42 mm |
| `side` | stage right, showing cabinet depth | 42 mm |
| `top` | plan view, for checking the footprint | 32 mm |
| `crowd` | eye height (1.65 m), aimed slightly low | 50 mm |

**Nothing is hardcoded to a particular rig.** Each preset is a *direction*; the distance is computed
so the scene's own bounding sphere fits the narrower field of view, whatever the aspect ratio. The same
preset therefore frames a single floor monitor and a fourteen-wide sub wall equally well — which is the
whole reason the maths lives in PHP (`src/Render/RenderPlan.php`) where it can be tested, instead of in
the Blender script where camera framing quietly drifts.

`crowd` deliberately frames tighter than the others: standing in front of a 4 m sub wall it fills your
view, and a shot that politely fits it all in undersells it.

### Lighting presets (`-l`, `--lighting`)

| Preset | For |
|--------|-----|
| `studio` *(default)* | neutral three-point — readable and honest |
| `stage` | warm key with coloured rims, event-like |
| `daylight` | sun and sky, for outdoor setups — which is most of them |
| `flat` | even and shadowless, for inspecting geometry (a chamfer, a grille inset) |

Light positions are multiples of the scene radius from its centre, and area-light power scales with the
square of that radius — otherwise a big rig comes out dark at the settings that light one cabinet
nicely. A sun is left alone, since irradiance does not fall off.

### Other options

| Option | Default | Notes |
|--------|---------|-------|
| `--samples` | 64 | Cycles samples; 16 is enough to check a layout, 200+ for something to show people |
| `-r`, `--resolution` | `1600x900` | `WIDTHxHEIGHT` |
| `--no-ground` | off | leave out the ground plane |
| `-o`, `--out` | `build/renders/<scene>-<camera>.png` | single scene only |

Rigging markers and the orange "estimated" tag are render-invisible, so they never appear in an image.

Renders are gitignored along with the rest of `build/`. Cycles runs on the CPU because the container
has no GPU — EEVEE Next needs one.

Real reference layouts from past events live in Drive under
`Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/` as SVG — porting those into scene files is
TODO item 2.5.
