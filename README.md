# SdWa5 3D

3D models of SdWa5's speakers and stage equipment, for rendering event previews in Blender and for trying out different
PA setups in 3D.

Models are **generated from specs**, not collected. One text file per device declares its real dimensions, weight,
rigging points and where those numbers came from; a Blender script builds the model from it. That is what makes the
library usable: every piece shares one scale, one orientation and one origin convention, so cabinets from different
specs actually stack and snap together.

## Requirements

- [ddev](https://ddev.readthedocs.io/) — Blender and PHP both live in the container
- Docker
- Blender **4.3.2** on your machine, to look at the results:
  `sudo apt install blender` on Debian 13 gives exactly that version

## Setup

```bash
ddev start          # first run builds Blender into the web image: ~1 GB, a few minutes
ddev composer install
```

## Usage

```bash
ddev exec bin/console specs:validate      # check every spec (no Blender needed)
ddev exec bin/console models:build        # build .glb + .blend for whatever changed
ddev exec bin/console library:build       # assemble the Blender asset library
ddev exec bin/console scene:stack         # solve a rig from constraints, write it as scene files
ddev exec bin/console scene:stack         # generate every sensible rig — no flags needed
ddev exec bin/console scene:build         # assemble a setup from scenes/*.yaml
ddev exec bin/console scene:render        # render it to a PNG — no Blender knowledge needed
ddev exec bin/console scene:render full-rig-arc --aim-lines     # ...with laser lines showing the aim
ddev exec bin/console catalog             # equipment table with weight/volume totals
ddev exec bin/console build:all           # all of the above, in order
ddev exec bin/console list                # all commands
```

Then in Blender: **Preferences → File Paths → Asset Libraries → +**, point it at `build/library/`, open an Asset Browser
and drag devices into a scene. Every device is one collection asset at true scale, already sitting on the floor.
See [docs/blender.md](docs/blender.md).

Tops can be placed as a **group on an arc** — `arc: {mode: convex, count: 3}` fans them out at the tightest
angle the cabinets allow, which for a tapered top is its own taper ([docs/scenes.md](docs/scenes.md#arcs--a-group-on-one-placement)).

Subs go in a **lattice**, which works its own spacing out: `row: {count: 6, gap_m: 0.02}` is a wall spaced and
centred from the cabinet's width plus a working gap, and any group nests inside any other with `in`, so two
tiers of that row — or two tiers of a fan — is one more line
([docs/scenes.md](docs/scenes.md#lattices--a-grid-that-works-its-own-spacing-out)).

Or state the rig as **constraints** and let the tiers be worked out: `stack: {max_width_m: 3.70,
interface_height_m: 2.0, from: [...]}` deals the cabinets you own into rows bottom-up, stacking subs until
the tops clear head height. Against our gear that lands on a three-tier rig with an Achenbach row between
the subs and the tops. It will mix a row where it has to — two SKRAMs in the middle of the bottom row with Flexys
either side, because a row of only two SKRAMs is narrower than the tier that would stand on it
([docs/scenes.md](docs/scenes.md#stack)).

You do not have to write that file either: `ddev exec bin/console scene:stack --max-width=3.70` solves the rig
and writes one scene per alignment, with a reason for every arrangement it left out
([docs/scenes.md](docs/scenes.md#scenestack--writing-the-scene-for-you)).

A tier can be **spread across a width** rather than centred on a spacing you worked out:
`align: {mode: block, across: sub-wall}` justifies it until its outer edges land on the sub wall's. That is a
solve and not a sum — an aimed cabinet toes in, and a toed-in cabinet is wider across x than it is wide, so
the answer depends on where the cabinet ends up ([docs/scenes.md](docs/scenes.md#align)).

Or write the setup down instead of dragging it: `scenes/full-rig.yaml` is the whole PA — a 12-cabinet sub wall in two
stacked rows plus three tops — in about twenty lines. `scene:build` reports its weight, height and footprint before
Blender is involved, and `scene:render` turns it into a preview image in about 8 seconds without you placing a single
camera. See [docs/scenes.md](docs/scenes.md).

A **second system is documented rather than owned**: `specs/speakers/gmss-*.yaml` and
[`scenes/gmss-full-stack.yaml`](scenes/gmss-full-stack.yaml) describe GMSS (Gena Made Sound System) — six turbo subs
over a small sub at each column's foot, two 18″ middle subs with a third laid on its side across them, and three
turbo tops in one row, reconstructed from a site photo and a message. Not one dimension in them is sourced, so all four are `provenance: estimated`, `catalog` lists every one
in the measuring backlog, and `scene:build` warns that the positions rely on un-measured cabinets. That is the
point of the provenance field: a rig can be laid out now and corrected when somebody gets a tape measure to it.
See [docs/sources.md](docs/sources.md#gmss-is-estimated-end-to-end).

**Not everything is a loudspeaker.** [`specs/truss/`](specs/truss) holds three-point truss segments and the
telescopic stands that hold them up, and [`scenes/full-rig-truss.yaml`](scenes/full-rig-truss.yaml) puts a goalpost
over the full rig. A truss is the one device whose geometry is *not* its bounding box: it is mostly air, so drawing
the box would stand a solid wall where the span should be and hide the rig behind it. `shape: truss` builds chords
and bracing from the tube sizes instead ([docs/spec-format.md](docs/spec-format.md#truss)).

Beyond that: [`specs/lighting/`](specs/lighting) holds GMSS's four Martin MAC 2000 Performance II — the only gear of
theirs with a real datasheet — and [`specs/stands/`](specs/stands) the two Krause AH7 scaffold towers.
[`scenes/gmss-full-stack-truss.yaml`](scenes/gmss-full-stack-truss.yaml) hangs the fixtures under their 9 m truss and
reports the bar total, **195.2 kg**, which is the number a truss's capacity is checked against.

**Owner is not a constraint.** [`scenes/all-speakers-*.yaml`](scenes) deal both systems' 39 cabinets into one, two
and three stacks regardless of whose they are, and [`scenes/everything.yaml`](scenes/everything.yaml) is the only
place every device in the repository stands in one picture — 55 cabinets, 3237 kg, speakers under 10 m of truss with
the lights hung, both scaffold towers and all three racks
([docs/scenes.md](docs/scenes.md#all-speakers-owner-ignored)).

**And two of the specs are not gear at all.** [`specs/vehicles/`](specs/vehicles) holds the transporters the rig is
driven to the gig in, which is the one category that is never modelled and never placed in a scene. The Movano's
figures are off its registration document, so the number the whole load side turns on is documented rather than
guessed: **1024 kg of payload**, derived as permitted gross minus mass in service with the driver already counted.
Sepp's Ducato is the one device in the library whose weight has been on a **scale**, and it is 365 kg heavier than
its registration document — which makes the paper authoritative about what it may weigh and merely historical about
what it does. `bin/console catalog` reports both
as a fleet beside the library and keeps them out of the weight and volume totals, because a van is the container and
never the load.

**And the fleet is 214.5 kg short of carrying the library in one trip.**
`bin/console load:plan --exclude-owner=gmss` assigns every cabinet to a van and reports weight and space as two
verdicts, and both vans finish within three kilogrammes of their legal limit with four devices left behind.
**Sepp's payload has been three different numbers in a day** — estimated at 1200 kg, documented at 1365 in his
Zulassungsschein, and **weighed at 1000** on a scale with a full tank and a driver. The van is 365 kg heavier than
its own papers, because shelving fitted after type approval appears in no registration field
([docs/load.md](docs/load.md)).

## Commands

| Command          | Does                                                                                                                             |
|------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `specs:validate` | Validates every spec against the shared conventions. Runs without Blender, so CI runs it too                                     |
| `models:build`   | Spec → `build/glb/<id>.glb` + `build/blend/<id>.blend`. Skips up-to-date models; `--force` to rebuild, `--id=<id>` to narrow. A `vehicle` has no geometry and is reported as skipped rather than filtered out in silence |
| `library:build`  | Assembles `build/library/sdwa5-3d.blend` with every device as a draggable collection asset                                       |
| `scene:stack`    | Solves a rig from constraints and writes scene files — one per alignment. With no flags it sweeps every sensible rig. The sub/top transition aims at the 2–3 m band and **a rig that misses it is written with the miss on it** rather than skipped, and **an unstated `--max-width` bounds nothing at all**. By default it sweeps **every combination of owners**, which is where most of the output is — `sdwa5` + `sepp` writes more scenes than any single owner, since borrowed subs under somebody else's tops is the shape of a shared gig. `--owner=NAME` narrows that, `--per-owner` gives each owner its own stack, `--stacks=N` splits into a stereo pair, `--orientation=MODE` decides which cabinets lie on their sides (**tops never do**, and laying the subs down more than doubles what the sweep can build), `--roll-mirror=ID` names those cabinets outright instead, `--shape=MODE` picks the wall's silhouette (`pyramid` narrows as it rises, `v` widens, `free` asks only the bearing rule, and all three are width rules in metres rather than cabinet counts), `--dry-run` prints, `--force` overwrites. **A rig that does not stand up is written rather than refused** — named `-impossible`, with the offending cabinets caged in red in its render. The sweep writes **976 scenes**: 898 that stand up and 78 that do not, 543 pooling both sound systems and 433 standing them apart. **The sweep is solved across every core** — 25 minutes in one process against 1m58s in twenty-eight, byte-identical either way — and `--jobs=1` puts it back in one |
| `scene:build`    | Scene YAML → `build/scenes/<id>.blend`, with a weight/footprint report and warnings for borrowed or over-used gear. **A cabinet the geometry checks object to — standing on nothing, or inside another cabinet — is caged in red so the render shows *which* one** ([docs/pipeline.md](docs/pipeline.md#a-cabinet-the-checks-object-to-is-caged-in-red)). `--dry-run` skips Blender |
| `scene:render`   | Renders a scene to `build/renders/<id>-<camera>.png`. Camera and lighting presets, auto-framed from the scene's own size; `--aim-lines` draws where cabinets point; `--presets` lists them. Quality is Full HD at 128 samples, with `--quick-preview` (960×540/16) and `--high-quality` (4K/384) either side; an explicit `--samples`/`--resolution` wins over both. Redraws when an input moves **or** when the settings differ from the ones recorded in `build/renders/built-with.json` |
| `ddev mesh-convert` | Meshes a `.FCStd` or `.step` into `meshes/` so a spec can use it as a `mesh_override`. A ddev *host* command, since FreeCAD runs in its own container |
| `build:all`      | The whole pipeline in order: validate, models, library, scenes, renders. Every stage skips what is already current; `--force` rebuilds anyway. `--dry-run` lists the stages. It also **deletes generated scene files the sweep no longer writes** — a replay renames rather than replaces, so a renamed axis leaves the old file behind — and `--keep-stale` switches that off. The regenerate stage replays across every core, `--jobs=1` for one. Renders **one** picture per scene; `--every-variant` asks for all four lighting presets in both aim modes, a folder each, and `--lighting=X` or `--aim-lines=X` picks one out |
| `load:plan`      | Assigns the gear across the transporters and reports weight and space as **two** verdicts. A payload overrun exits non-zero, since it is a legal problem rather than an inconvenience; a bounding-box volume over the bay is stated as evidence, and under it is never a permission. `--exclude-owner=gmss` is the invocation this collective uses ([docs/load.md](docs/load.md)) |
| `catalog`        | Equipment table plus total weight, total volume and how many specs still need measuring. Transporters are reported separately as a fleet, with payload and bay volume each, and are kept out of the library's weight and volume totals. `--write` also writes `docs/catalog.md` |

## Adding a device

1. Copy an existing file in [`specs/speakers/`](specs/speakers) — the filename must equal the `id`
2. Fill in the real numbers, from the cloned original's datasheet or by measuring
   ([docs/measuring.md](docs/measuring.md)), and set `provenance` to say which
3. `ddev exec bin/console specs:validate && ddev exec bin/console models:build --id=<id>`
4. Record where the numbers came from in [docs/sources.md](docs/sources.md)

Field reference: [docs/spec-format.md](docs/spec-format.md).

## Repository layout

```
specs/          one YAML file per device — speakers/, truss/, lighting/, stands/, racks/, vehicles/
src/Load/       the pack: which unit rides in which van, and the two verdicts on it
blender/        bpy build scripts, invoked headless by the PHP CLI
src/            PHP: spec loading, validation, catalog, build orchestration
tests/          PHPUnit, mirroring src/
tools/          check-glb.py, freecad-export.py
scenes/         setups as YAML — one file per event layout
meshes/         override meshes — third-party CAD, gitignored
build/          generated models, asset library, renders — gitignored
docs/
```

Nothing generated is committed. `build/` is reproducible from the specs with one command, which is why the repository
stays text-only and diffable.

## Current state

The whole PA is in the library — **5 specs, 25 cabinets, 1786 kg, 8.7 m³**:

| Device                   | Owner | Qty | W × H × D (m)                               | kg each          | Model                    |
|--------------------------|-------|-----|---------------------------------------------|------------------|--------------------------|
| Flexy Folded Horn Hybrid | sdwa5 | 12  | 0.591 × 0.763 × 0.964                       | 85               | CAD — four horn mouths   |
| SKRAM                    | sdwa5 | 2   | 0.610 × 0.914 × 0.813                       | 90               | CAD — vent array         |
| Tecnare M2122            | sdwa5 | 3   | 0.500 × 0.960 × 0.520 (tapered, 0.345 rear) | 68               | generated + 3 horns, 2 cones (est.) |
| Eighteensound 2-Way 15″  | sepp  | 2   | 0.466 × 0.836 × 0.427                       | 41 (est.)        | CAD + horn and cone      |
| Achenbach 18             | sepp  | 6   | 0.600 × 0.600 × 0.700                       | 50 (est.)        | CAD + 18″ cone           |

**Four of five carry their own CAD** via `mesh_override`, so the models show the openings you actually see on a
cabinet rather than a black box. The meshes themselves are third-party files and are not committed —
[`meshes/README.md`](meshes/README.md) has the one-line `rclone` command for each, and `ddev mesh-convert` turns
FreeCAD or STEP into something Blender can read.

Behind those openings there are now **drivers and horns**, from each spec's `audio.layout`: cones with a surround and
a domed dust cap, and horn flares whose mouth shape, throat shape and flare law come from the spec
([docs/spec-format.md](docs/spec-format.md#baffle-layout)). The Tecnare has no CAD anywhere, so its whole baffle is
generated and its numbers are estimated — the flares are carved into the shell rather than sitting behind a CAD hole.
Its two 12″ horns are **connected**: the wall between them stops behind the baffle, so the front shows one opening and
the two throats only part company inside, which any pair of horns can now say with `join`.

All three Tecnare tops share one spec at quantity 3. Two are factory cabinets and the third is a self-built copy, but
the geometry is identical, so modelling it twice was wasted work; the distinction is recorded in the spec's notes.

Most are DIY builds of commercial designs, so their dimensions come from the designs themselves — CAD meshes, cut lists,
published build plans and one datasheet ([docs/sources.md](docs/sources.md)
records which, per device). Amps, DSP, racks and truss are documented in Drive but not modelled yet.

**Nothing has been measured yet** — dimensions 0 of 5, weights 0 of 5. Every number describes a design or a datasheet,
not the cabinet in the barn, and three weights are outright estimates. Dimensions and weights are tracked separately,
because a hanging scale settles a weight in a minute while taping fourteen subs is an afternoon; `catalog` reports both
counts on every run, so the gap between "we have models" and "we have accurate models" stays visible — see
[docs/measuring.md](docs/measuring.md) and [`TODO.md`](TODO.md).

## Documentation

- [Conventions](docs/conventions.md) — units, axes, origin, naming, materials, fidelity, provenance
- [Spec format](docs/spec-format.md) — every field, and what the validator rejects
- [Pipeline](docs/pipeline.md) — how a spec becomes a model, and the failure modes
- [Blender](docs/blender.md) — version pinning, asset library, building setups
- [Measuring](docs/measuring.md) — checklist for turning a real cabinet into a spec
- [Inventory](docs/inventory.md) — the gear list, and Drive access via rclone
- [Scenes](docs/scenes.md) — writing a PA setup as a file, stacking and repetition
- [Sources](docs/sources.md) — where each device's numbers come from, and licensing

Org-level documentation lives in the parent repo's
[`docs/`](https://github.com/bestcodename/sdwa5/tree/main/docs).

## Development

```bash
ddev exec composer test                          # PHPUnit
ddev exec bin/console specs:validate             # same check CI runs
ddev exec bin/console scene:build --dry-run      # scenes compile, no Blender needed
python3 tools/check-glb.py 'build/glb/*.glb'     # exported models match their own metadata
```

Tests do not need Blender: the spec, catalog and orchestration layers are unit-tested, and the Blender invocation is
tested through a fake process runner. PHP owns the specs, Python owns the geometry —
see [docs/pipeline.md](docs/pipeline.md#why-php-and-python).
