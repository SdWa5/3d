# SdWa5 3D

3D models of SdWa5's speakers and stage equipment, for rendering event previews in Blender and for trying out different
PA setups in 3D.

Models are **generated from specs**, not collected. One text file per device declares its real dimensions, weight,
rigging points and where those numbers came from; a Blender script builds the model from it. That is what makes the
library usable: every piece shares one scale, one orientation and one origin convention, so cabinets from different
specs actually stack and snap together.

The solver also offers repeated mixed sub rows. Innschleife's photo roster produces four possible scenes with
WSX flanking two SBH in each of two rows, four kickers above them, and two TMS-2 around the estimated black middle
top. The roster uses a 1.6 m interface and a 1.75 m sub-height target.
See [the generation command](docs/scenes.md#repeating-a-flanked-row).

Next-event generations use [events/next-event.yaml](events/next-event.yaml). `scene:stack --event=next-event`
refuses a compiled rig wider than 13 m or higher than 4 m. Insets, gaps, aiming and flown equipment count towards
those limits. The event lowers the interface only for walls built from Innschleife's subs. It also states how each
system is set up, which is ours and Sepp's upright and PSL's and Innschleife's turned, with Innschleife's kickers
standing as measured. A next-event sweep therefore does not vary orientation, and its scenes are named `stated`.

PSL's 10 × 3.03 m deco panel hangs from the front of our five F33 segments on our two wind-up towers, behind the
rig. The event names that truss, and `scene:stack` adds it to every rig whose roster brings a `deco` device. Under
the 4 m ceiling the towers are cranked to 3.742 m, and each carries 45.975 kg of its 85 kg rating. The panel shows PSL's print
when its file is in `meshes/psl/` and the model is built with `--front-images`.
See [the backdrop](docs/scenes.md#a-deco-backdrop-behind-a-generated-rig).

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

**Four systems are documented rather than owned**, and they are documented very unevenly, which is the honest
state of a repository that describes other people's gear.

* **GMSS** — [`specs/speakers/gmss/`](specs/speakers/gmss) and
  [`scenes/gmss-full-stack.yaml`](scenes/gmss-full-stack.yaml). Fourteen cabinets whose every figure the builder
  stated himself, in centimetres, replacing a reconstruction from one site photograph that had the mid bass at half
  its real width. `provenance: estimated` throughout, because the enum has no case for "the builder said so".
* **PSL**, Pro Sound & Light — [`specs/speakers/psl/`](specs/speakers/psl). The best-sourced borrowed gear here:
  they are a rental company and publish the full technical data for all ten cabinets they hire out, so every
  dimension and weight is `datasheet`. What no rental catalogue states is **how many** there are, which is most of
  their entry in [docs/requests.md](docs/requests.md).
* **Innschleife** — [`specs/speakers/innschleife/`](specs/speakers/innschleife). The other extreme. They publish
  nothing, so the specs rest on two setup drawings that embed one photograph per cabinet **at 1 px = 1 cm** — a
  scale that makes the front width and height readable and says nothing about depth, weight or drivers — plus what
  Innschleife themselves said on 2026-09-02, which named four of the five cabinets and settled every count. The
  small tops are still `tms4`, because "die kleinen Tops" is a description rather than a name.

`catalog` lists every borrowed cabinet in the measuring backlog and `scene:build` warns that the positions rely on
un-measured gear. That is the point of the provenance field: a rig can be laid out now and corrected when somebody
gets a tape measure to it. See [docs/sources.md](docs/sources.md) for where each number came from and
[docs/requests.md](docs/requests.md) for the ones that have no source at all.

**Not everything is a loudspeaker.** [`specs/truss/`](specs/truss) holds three-point truss segments and the
telescopic stands that hold them up, and [`scenes/full-rig-truss.yaml`](scenes/full-rig-truss.yaml) puts a goalpost
over the full rig. A truss is the one device whose geometry is *not* its bounding box: it is mostly air, so drawing
the box would stand a solid wall where the span should be and hide the rig behind it. `shape: truss` builds chords
and bracing from the tube sizes instead ([docs/spec-format.md](docs/spec-format.md#truss)).

Beyond that: [`specs/lighting/`](specs/lighting) holds GMSS's four Martin MAC 2000 Performance II — the only gear of
theirs with a real datasheet — and [`specs/stands/`](specs/stands) the two Krause AH7 scaffold towers.
[`scenes/gmss-full-stack-truss.yaml`](scenes/gmss-full-stack-truss.yaml) hangs the fixtures under their 9 m truss and
reports the bar total, **195.2 kg**, which is the number a truss's capacity is checked against.

**Owner is not a constraint on a scene, only a default for the sweep.**
[`scenes/all-speakers-*.yaml`](scenes) deal cabinets into one, two and three stacks regardless of whose they are,
and [`scenes/everything.yaml`](scenes/everything.yaml) is the only place every device in the repository stands in
one picture — speakers under 10 m of truss with the lights hung, both scaffold towers and all three racks
([docs/scenes.md](docs/scenes.md#all-speakers-owner-ignored)). Those two hand-written sets were laid out when the
library was two systems and 39 cabinets; it is five systems and 101 units now, so they describe a subset by
accident rather than by design.

**And two of the specs are not gear at all.** [`specs/vehicles/`](specs/vehicles) holds the transporters the rig is
driven to the gig in, which is the one category that is never modelled and never placed in a scene. The Movano's
figures are off its registration document, so the number the whole load side turns on is documented rather than
guessed: **1024 kg of payload**, derived as permitted gross minus mass in service with the driver already counted.
Sepp's Ducato is the one device in the library whose weight has been on a **scale**, and it is 365 kg heavier than
its registration document — which makes the paper authoritative about what it may weigh and merely historical about
what it does. **His 465 kg generator travels on a trailer**, which is a third bin the planner now has and which turns out to
carry its own weight and little else. **Both vans are drawn as cages** rather than solids: the vehicle's outline, the load bay inside it and
the floor between the wheel arches, so a pack can be looked at instead of imagined
([docs/load.md](docs/load.md#seeing-it)). `bin/console catalog` reports both
as a fleet beside the library and keeps them out of the weight and volume totals, because a van is the container and
never the load.

**The fleet carries our two systems' gear in one trip and cannot also carry the generator.** Re-measured on
2026-09-02, both figures:
`bin/console load:plan --owner=sdwa5 --owner=sepp --exclude=generator-25kva` assigns all 37 units across the
two vans and the trailer with **340.5 kg spare**, while the same plan **including** the 465 kg generator is
**134.9 kg short** and leaves three devices behind. So the generator is the shortfall, not the gear. Both verdicts
come out as weight *and* space, and all three bins land close enough to their limits that the planner calls the
margin `UNDECIDED` — 6 kg, 3.7 kg and 0.7 kg of headroom is inside the error of the estimates it is computed from,
which is neither a pass nor a refusal.
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
| `scene:stack`    | Solves a rig from constraints and writes scene files — one per alignment. With no flags it sweeps every sensible rig. The sub/top transition aims at the 2–3 m band and **a rig that misses it is written with the miss on it** rather than skipped, and **an unstated `--max-width` bounds nothing at all**. With no flags it builds **one inventory: our gear and Sepp's, pooled as one rig**, which is what stands on a stage when we play. It used to sweep every combination of owners, and the pair was the finding that came out of that — a borrowed rig writes more scenes than any single owner, since somebody else's subs under our tops is the shape of a shared gig — so the finding is the default now rather than an enumeration of 31 subsets nobody would build. `--owner=NAME` names a different inventory and **each one gets its own folder** under `scenes/generated/`, so the system is stated once in a directory name instead of in every file name inside it, `--systems=VALUE` says how separately the systems stand (`pooled`, `systems-apart`, or `tops-shared` for each system's subs with every top dealt across those walls), `--group=NAME:owner+owner` says which owners are **one system** — `sdwa5` and `sepp` are, by default, so a separated rig gives them one wall rather than two — `--per-owner` is the older way to ask for `systems-apart`, `--order=NAME,NAME` states which system stands where, left to right, instead of leaving it to the tallest-in-the-middle rule — a system's own stacks stay adjacent, and a rig the order names nothing in keeps the height rule, `--roster=ID` builds from what a system is actually bringing to one event rather than everything it owns, `--folders=AXIS,AXIS` makes any axis a directory level instead of a field in the name (default: the inventory alone, which is the tree that already exists), `--stacks=N` narrows the stack count without collapsing the rest of the sweep, `--orientation=MODE` decides which cabinets lie on their sides (**tops never do**, and laying the subs down more than doubles what the sweep can build), `--roll-mirror=ID` names those cabinets outright instead, `--low-end=MODE` says where the lowest-reaching cabinets belong (`low` puts them on the floor, `central` pulls them onto the centre line even when that costs a row, and it is `central` that stacks two SKRAMs one above the other),  `--shape=MODE` picks the wall's silhouette (`pyramid` narrows as it rises, `v` widens, `free` asks only the bearing rule, and all three are width rules in metres rather than cabinet counts), `--dry-run` prints, `--force` overwrites. **A rig that does not stand up is written rather than refused** — named `-impossible`, with the offending cabinets caged in red in its render. **Every option narrows one axis rather than collapsing the sweep**, so "sweep everything, but only two stacks" is `--stacks=2`. A bare sweep writes **166 scenes** into `scenes/generated/sdwa5-sepp/`, and the eleven inventories that are committed come to 2219 across as many folders. **The sweep is solved across every core** — 25 minutes in one process against 1m58s in twenty-eight, byte-identical either way — and `--jobs=1` puts it back in one |
| `scene:build`    | Scene YAML → `build/scenes/<id>.blend`, with a weight/footprint report and warnings for borrowed or over-used gear. **A cabinet the geometry checks object to — standing on nothing, or inside another cabinet — is caged in red so the render shows *which* one** ([docs/pipeline.md](docs/pipeline.md#a-cabinet-the-checks-object-to-is-caged-in-red)). Takes a scene id, a path or a folder, and a folder builds every scene below it. `--dry-run` skips Blender |
| `scene:render`   | Renders a scene, or every scene in a folder, to `build/renders/<id>-<camera>.png`. Camera and lighting presets, auto-framed from the scene's own size; `--labels` names every device and adds a colour legend, `--aim-lines` draws where cabinets point; `--presets` lists them. Quality is Full HD at 128 samples, with `--quick-preview` (960×540/16) and `--high-quality` (4K/384) either side; an explicit `--samples`/`--resolution` wins over both. Redraws when an input moves **or** when the settings differ from the ones recorded in `build/renders/built-with.json` |
| `ddev mesh-convert` | Meshes a `.FCStd` or `.step` into `meshes/` so a spec can use it as a `mesh_override`. A ddev *host* command, since FreeCAD runs in its own container |
| `build:all`      | The whole pipeline in order: validate, models, library, scenes, renders. Every stage skips what is already current; `--force` rebuilds anyway. `--dry-run` lists the stages. It also **deletes generated scene files the sweep no longer writes** — a replay renames rather than replaces, so a renamed axis leaves the old file behind — and `--keep-stale` switches that off. The regenerate stage replays across every core, `--jobs=1` for one. Renders **one** picture per scene; `--every-variant` asks for all four lighting presets in both aim modes, a folder each, and `--lighting=X` or `--aim-lines=X` picks one out |
| `scene:pack`     | The load plan as a scene: the convoy in a row with each vehicle's load standing inside it. Positions come from **one stated rule** — heaviest first onto the bay floor in rows, then columns on top — not from an optimal pack, and the scene's own notes name what the rule could not place. `--write` writes it to `scenes/packs/` ([docs/load.md](docs/load.md#seeing-a-pack)) |
| `load:plan`      | Assigns the gear across the transporters and reports weight and space as **two** verdicts. `--owner=sdwa5 --owner=sepp --exclude=generator-25kva` is the invocation this collective uses: only our gear and Sepp's travel in these vans — GMSS, PSL and Innschleife arrive in their own — and Sepp's 465 kg generator goes on a trailer. A payload overrun exits non-zero, since it is a legal problem rather than an inconvenience; a bounding-box volume over the bay is stated as evidence, and under it is never a permission. ([docs/load.md](docs/load.md)) |
| `catalog`        | Equipment table plus total weight, total volume and how many specs still need measuring. Transporters are reported separately as a fleet, with payload and bay volume each, and are kept out of the library's weight and volume totals. `--write` also writes `docs/catalog.md` |

## Adding a device

1. Copy an existing file from the owner's folder under [`specs/speakers/`](specs/speakers), for example
   [`specs/speakers/sdwa5/`](specs/speakers/sdwa5) for our own gear. The filename must equal the `id`, and the folder
   must be the one named after the `owner` field — so **the id does not repeat the owner**, since the folder has
   already said it
2. Fill in the real numbers, from the cloned original's datasheet or by measuring
   ([docs/measuring.md](docs/measuring.md)), and set `provenance` to say which
3. `ddev exec bin/console specs:validate && ddev exec bin/console models:build --id=<id>`
4. Record where the numbers came from in [docs/sources.md](docs/sources.md)

Field reference: [docs/spec-format.md](docs/spec-format.md).

## Repository layout

```
specs/          one YAML file per device — <category>/<owner>/<id>.yaml
src/Load/       the pack: which unit rides in which van, and the two verdicts on it
src/Scene/      the solver and the scene compiler — five layered classes, see docs/pipeline.md
blender/        bpy build scripts, invoked headless by the PHP CLI
src/            PHP: spec loading, validation, catalog, build orchestration
tests/          PHPUnit, mirroring src/
tools/          check-glb.py, freecad-export.py
scenes/         setups as YAML — one file per event layout
scenes/generated/  the sweep's output, one folder per inventory — committed so it can be read on the web
rosters/        what a system brings to one event — counts that override the specs for one run
meshes/         override meshes — third-party CAD, gitignored
build/          generated models, asset library, renders — gitignored
docs/
```

No *binary* artefact is committed. `build/` and `meshes/` are reproducible or third-party, so the repository stays
text-only and diffable. The generated scenes under `scenes/generated/` **are** committed, on purpose, so that any of
them can be read on the web without a checkout and a solve — see [docs/scenes.md](docs/scenes.md).

## Current state

**Five systems, 38 devices, 101 units, 6804.0 kg, 44.0 m³.** `bin/console catalog` is the authority and
[docs/catalog.md](docs/catalog.md) is its written form. What we own ourselves is the first two rows:

| Device                   | Owner | Qty | W × H × D (m)                               | kg each          | Model                    |
|--------------------------|-------|-----|---------------------------------------------|------------------|--------------------------|
| Flexy Folded Horn Hybrid | sdwa5 | 12  | 0.591 × 0.763 × 0.964                       | 85               | CAD — four horn mouths   |
| SKRAM                    | sdwa5 | 2   | 0.610 × 0.914 × 0.813                       | 90               | CAD — vent array         |
| Tecnare M2122            | sdwa5 | 3   | 0.500 × 0.960 × 0.520 (tapered, 0.345 rear) | 68               | generated + 3 horns, 2 cones (est.) |
| Eighteensound 2-Way 15″  | sepp  | 2   | 0.466 × 0.836 × 0.427                       | 41 (est.)        | CAD + horn and cone      |
| Achenbach 18             | sepp  | 6   | 0.600 × 0.600 × 0.700                       | 50 (est.)        | CAD + 18″ cone           |

That is 25 cabinets and 1786 kg, and it is the inventory a bare `scene:stack` builds. The other 3239 kg belongs to
GMSS, PSL and Innschleife and arrives in their own vans — see the four borrowed systems above, and
[docs/inventory.md](docs/inventory.md) for the system-by-system state.

**Four of the five cabinets we own carry their own CAD** via `mesh_override`, so the models show the openings you actually see on a
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

**Nothing has been measured yet** — dimensions 0 of 36, weights 1 of 36, and the one is a van on a weighbridge.
Every number describes a design, a datasheet or a scaled drawing rather than the cabinet in the barn, and fifteen
figures are outright estimates: three weights of our own and Sepp's, and every Innschleife depth and weight. Dimensions and weights are tracked separately,
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
- [Signal chain](docs/signal-chain.md) — limiter thresholds, amplifier gain and DSP routing, per event

Org-level documentation lives in the association's [`docs`](https://github.com/SdWa5/docs) repository.

## Development

```bash
ddev exec composer test                          # PHPUnit
ddev exec composer static                        # PHPStan level 5 + Symfony coding standards
ddev exec composer cs-fix                        # apply the coding standards
pipx run 'ruff==0.16.6' check .                  # the Python side, blender/ and tools/
ddev exec bin/console specs:validate             # same check CI runs
ddev exec bin/console models:build --front-images  # cabinets wearing their front photograph
ddev exec bin/console scene:build --dry-run      # scenes compile, no Blender needed — not a CI step, see below
python3 tools/check-glb.py 'build/glb/*.glb'     # exported models match their own metadata
gitleaks dir . --redact --config .gitleaks.toml   # no secret in the working tree
gitleaks git . --redact --config .gitleaks.toml   # nor anywhere in the history
```

`scene:build --dry-run` with no argument is the one line above that CI does **not** run. It compiles all 2219
scenes, which was 26 minutes of a 135-minute job and is work `composer test` already does to a stricter standard in
`ShippedScenesTest`. It is still worth running by hand before a release, and `SDWA5_FULL_REPLAY=1 ddev exec composer
test` runs it as part of the suite.

`composer test` is **9 min 04 s** on twenty-eight cores. Most of it used to be one test: `ShippedScenesTest` checks
every shipped scene for a cabinet hanging in the air or sitting inside another, and it walked all 2489 of them down
one core for 10 minutes. It now runs one case per inventory and solves the scenes inside each across cores, through
the same `Parallel` the sweep uses. **The library is still checked whole and is never sampled** — that is the
promise the class exists for, and a test asserts that the chunks hold every scene on disk, because a case is now an
inventory rather than a scene and a dropped one would no longer even shorten the list of test names.

The suite runs with opcache's tracing JIT on, configured in [`.ddev/php/opcache-jit.ini`](.ddev/php/opcache-jit.ini)
and in `ini-values` on setup-php in the workflow. **Those two have to stay in step**, because `opcache.jit_buffer_size`
can only be set at startup and a local timing that does not match CI's is worse than no local timing. It is worth
about 1.5x on the commands and about 1.5x on the suite: 0.2085 s/scene interpreted against 0.1320 s/scene traced over
an evenly spread 40-scene sample, and 0.3304 s/case against 0.2154 s/case for the 434 cases of `generated/next-event`
inside PHPUnit.

**To time an interpreted run, use `-d opcache.jit_buffer_size=0`.** `-d opcache.jit=off` does not work, because
`phpunit.xml` sets the mode again at runtime and switches the JIT back on before a single test executes. That is what
made 0.113.0 report the suite as gaining nothing.

In CI, `composer test` runs as three parallel jobs. Each takes its `--filter` from
[`.github/phpunit-shards.json`](.github/phpunit-shards.json), and a step in the `static` job fails unless the shards
together list every test exactly once. A single shard runs locally the same way:

```bash
ddev exec composer test -- --filter "$(jq -r '.["other-commands"]' .github/phpunit-shards.json)"
```

The two `gitleaks` runs are the same ones [`.github/workflows/tests.yml`](.github/workflows/tests.yml) performs, and
the same check runs in the other two SdWa5 repositories. All three went public on 2026-09-30, and a public repository
publishes every past commit at once. This repository is clean in both tree and history, measured 2026-09-08, so the gate exists to stop
the next secret rather than to find a current one.

Tests do not need Blender: the spec, catalog and orchestration layers are unit-tested, and the Blender invocation is
tested through a fake process runner. PHP owns the specs, Python owns the geometry —
see [docs/pipeline.md](docs/pipeline.md#why-php-and-python).

## Licence

Two licences, because this repository is part tooling and part writing.

- **MIT** ([LICENSE](LICENSE)) for the code and configuration: `src/`, `tests/`, `bin/`, `blender/`, `.github/`, `.ddev/` and the build configuration
  (`composer.json`, `phpstan.neon`, `phpunit.xml`, `pyproject.toml`, `.php-cs-fixer.dist.php`).
- **CC BY-SA 4.0** ([LICENSE-docs](LICENSE-docs)) for the prose and data: `docs/`, `README.md`, `CHANGELOG.md`, `TODO.md`, `specs/`, `rosters/` and `scenes/`.

Attribute as "Musikverein Schmeiß die Wand an 5 (SdWa5)" with a link to the repository. Share-alike applies to the prose, so a
derivative of the documentation stays under the same licence. The code carries no such condition.

**Not ours to license**: any mesh referenced by a spec's `mesh_override`. Those files are third-party
CAD, they are deliberately not committed, and `docs/sources.md` records where each one came from and
under what terms.
