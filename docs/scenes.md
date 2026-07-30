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
| `placements[].yaw_deg` | rotation about Z. 0 faces −Y, the convention every model uses |
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

The assembled `.blend` has no lighting or camera yet — add your own, or wait for the lighting template
in [`../TODO.md`](../TODO.md). Rigging markers and the estimated tag are render-invisible, so they will
not appear in an image.

Real reference layouts from past events live in Drive under
`Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/` as SVG — porting those into scene files is
TODO item 2.5.
