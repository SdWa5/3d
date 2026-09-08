# Pipeline

How a spec file becomes a model you can drag into a scene.

```
specs/speakers/sdwa5/top-a.yaml    hand-written, the source of truth
        │
        │  bin/console specs:validate      no Blender needed — CI runs this
        ▼
build/plans/top-a.json             flattened build plan (PHP → Python hand-off)
        │
        │  bin/console models:build        blender --background --python blender/build_model.py
        ▼
build/glb/top-a.glb                canonical interchange model, metadata in glTF extras
build/blend/top-a.blend            editable model, collection named `top-a`
        │
        │  bin/console library:build       blender --background --python blender/build_library.py
        ▼
build/library/sdwa5-3d.blend       asset library — every device as a draggable collection asset
build/library/blender_assets.cats.txt

scenes/full-rig.yaml               a setup written down: which devices, where, stacked on what
        │
        │  bin/console scene:build         blender --background --python blender/build_scene.py
        ▼
build/scenes/full-rig.blend        cabinets placed at true scale, geometry instanced once per device
        │
        │  bin/console scene:render        blender --background --python blender/render_scene.py
        ▼
build/renders/full-rig-<camera>.png  preview image; camera and lights framed from the scene's own size
build/renders/<variant>/…             one folder per lighting/aim-line combination, with `build:all`
```

Nothing under `build/` is committed. It is all reproducible from the specs, and regenerating it is
one command.

## Commands

All of them run inside ddev, which is where Blender lives:

```bash
ddev exec bin/console specs:validate      # check every spec
ddev exec bin/console models:build        # build what changed
ddev exec bin/console models:build -f     # rebuild everything
ddev exec bin/console models:build --id=top-a --id=sub-a
ddev exec bin/console library:build       # assemble the asset library
ddev exec bin/console scene:build         # assemble every scene
ddev exec bin/console scene:build full-rig --dry-run   # report a setup without Blender
ddev exec bin/console scene:render full-rig -c crowd -l stage   # preview image
ddev exec bin/console scene:render --presets           # list camera and lighting presets
ddev exec bin/console catalog             # table + weight/volume totals
ddev exec bin/console catalog --write     # also write docs/catalog.md

ddev exec bin/console build:all           # every stage above, in order
ddev exec bin/console build:all --dry-run # ...list what it would run, and run nothing
ddev exec bin/console build:all --lighting=studio       # ...that lighting instead of the default one
ddev exec bin/console build:all --every-variant         # ...all four lightings in both aim modes
ddev exec bin/console build:all --quick-preview         # ...at preview quality
ddev exec bin/console build:all --jobs=1  # ...regenerate the scenes in one process
```

**Every stage skips what is already current**, so a rebuild after touching one scene costs that scene and its
renders rather than the whole library. Freshness is one rule shared by all of them (`App\Build\Staleness`): an
output is stale when it is missing, or older than any of its inputs.

| Stage | Output | Inputs it watches |
|-------|--------|-------------------|
| `models:build` | `build/glb/<id>.glb` + `build/blend/<id>.blend` | the spec, `blender/build_model.py`, all of `blender/lib`, any mesh override |
| `scene:build` | `build/scenes/<id>.blend` | the scene file, `blender/build_scene.py`, `blender/lib`, **and the model of every cabinet the scene places** |
| `scene:render` | the PNG | the scene's `.blend`, `blender/render_scene.py`, `blender/lib`, **and the settings it was drawn with** |

Mtimes rather than hashes, and that choice is what makes the *chain* work with no bookkeeping: a spec is newer
than its model, so the model rebuilds; the model is then newer than the scene, so the scene reassembles; the
scene is then newer than the render, so the render redraws. Each stage only ever compares its own neighbours.

### What mtimes cannot see

An input moving is one question. **Whether an output was made with the settings now being asked for** is another,
and no mtime can answer it: nothing on disk moves when the default resolution is raised or `--lighting=stage` is
passed, so a 1600×900 studio render used to stay "current" against a request for a Full HD one. The inputs really
had not changed — only the instructions had. Since only the camera and the scene id appear in a PNG's filename,
that covered nearly every setting there is.

So `scene:render` records the settings each picture was made with and compares them on the next run: camera,
lighting, samples, resolution, ground plane, aim mode. Change any one and that render redraws; change none and it
does not.

**One file for the whole tree** — `build/renders/built-with.json` — keyed by each picture's path relative to it:

```json
{
  "detail-check-three-quarter.png":        { "lighting": "studio", "samples": 16,  "resolution": [960, 540] },
  "studio/full-rig-arc-three-quarter.png": { "lighting": "studio", "samples": 128, "resolution": [1920, 1080] }
}
```

One manifest rather than a stamp beside every picture, which would mean several hundred hidden files interleaved
with several hundred PNGs. Relative keys so the entries stay readable and stay true if the build tree moves.
Recording one render leaves every other entry alone, and the stages are sequential, so there is no writer to race.

A picture **absent from the manifest** counts as changed, which is what makes this self-healing — everything
rendered before the manifest existed redraws once, at whatever is now being asked for, and is recorded afterwards.
A manifest nobody can parse means everything in its tree redraws, rather than being trusted. Entries are written
only after Blender succeeds: one written ahead of a failed render would claim the old picture was made with the new
settings, which is the single way this could rebuild too little.

`--force` on `build:all`, `scene:build` or `scene:render` rebuilds anyway. On `build:all` it now reaches
**every** stage — it used to reach only `models:build`, so a forced run still reused stale scenes and renders.

`build:all` is the stage order written down. Every stage already refuses to run on stale input — the
library will not be stitched from models older than their specs, a scene will not place a device whose
model is out of date — but nothing knew the *order*, so getting from an edited spec to a new render meant
remembering five commands and which of them the edit had invalidated.

It delegates rather than reimplements, so each stage's own staleness rules, reporting and refusals are the
ones that apply, and a failing stage stops the run.

**The regenerate stage is idempotent, and "every candidate was refused" is not a failure.** It replays each generated
scene's own recorded command, and a rig the solver has stopped being able to build makes that command write nothing.
`scene:stack` says so with its own exit code — **2, `NOTHING_TO_WRITE`**, non-zero so a human sees it but distinct from
1 so a caller can tell it from a command that broke. The replay reports such a scene as `stale`, leaves it out of the
written set, and the stale deletion removes it. Before those were one exit code, a single abandoned rig aborted the
whole stage and took every other replay with it, so `build:all` could not be run twice in a row — from the stage whose
job is deleting exactly that file. `--keep-stale` leaves them in place.

One case is left, and it is cosmetic: a variant the sweep collapses as **the same rig as** a sibling replays perfectly
well on its own, because dedup is a decision across a whole sweep and a replay is one file with nothing to compare
itself against. Six such files existed at 0.84.0, every one a duplicate of a scene that is also on disk. `git status`
after a fresh `scene:stack --force` is still what finds those.

**The regenerate stage runs across every core**, one process per replay, because five hundred `scene:stack` runs share
nothing and a recorded command rewrites exactly the file it was read from. Each child's console output is captured and
printed back by the parent in file order, so the log reads as a serial run's did rather than as twenty-eight processes
interleaving mid-line. `--jobs=1` puts it back in one process, which is what a debugger needs. The one behaviour that
changes: a broken replay is reported after the whole stage has run rather than stopping it, and the failure named is
the one the serial order would have named first.

The variant sweep is a **flag** rather than the default, and that is a reversal of 0.70.0. Every scene under each of
the four lighting presets, each with and without aim lines, is eight passes into eight folders under `build/renders/`
— which at 2688 generated scenes is 21 504 pictures out of the slowest tool in the repository. Stated by the owner:
the lighting variants go if they are what holds `build:all` up. They were. `--every-variant` asks for the eight back,
and the useful-by-default argument still stands for everything cheap.

With nothing asked for there is one pass, writing exactly where `scene:render` always put it rather than into a
subfolder. Naming a `--lighting` or an `--aim-lines` picks that one out, and either of them alongside
`--every-variant` narrows the sweep to that row or column of it. An unknown `--aim-lines` value is refused before any
stage runs — a dry run runs nothing, so nothing downstream would catch the typo.

### A cabinet the checks object to is caged in red

**Every check in this repository used to answer with a sentence and then throw the geometry away**, which is the
wrong way round for the failures that are hard to picture. "A `turbo-top` would stand at 4.668 m with nothing
under it across x" took a debug dump, two probes and a corrected coordinate mapping to understand. A picture with
that one cabinet in a red cage says it at a glance.

So `scene:build` now runs the two geometry checks that name a cabinet — nothing under it, and two cabinets inside
each other — and writes what they found into the scene plan. `build_scene.py` draws a red emissive wireframe cage
around every cabinet named, and it renders:

```
sdwa5-3d: scene zz-fault-probe with 3 cabinet(s), 1 marked faulty → build/scenes/zz-fault-probe.blend
```

**A cage rather than a recolour**, because a placement is an empty instancing a linked collection and an empty takes
no material — recolouring would mean copying the collection per faulted cabinet. The cage is also the better
picture: it says "this one" without hiding the thing it is pointing at. It is real geometry from a Wireframe
modifier rather than `display_type = "WIRE"`, which is a viewport setting Cycles ignores.

**The marking is derived and never stored.** It is not a scene field and will not become one: a scene records the
constraints a rig has to satisfy and is re-solved on every build, so the checks fire again on the same arrangement
and name the same cabinets. A colour written into the schema would put a rendering concern in the file format and
freeze one build's opinion into a file whose whole contract is that it carries no answers.

**The prose refusal is now built from the marking**, rather than derived alongside it, so the sentence a terminal
prints and the cabinet a render cages can never be about different cabinets.

Today it is dormant on the shipped set, and deliberately so: `ShippedScenesTest` guarantees no scene in this
repository has either fault. What it serves now is a hand-written scene and a regression. Emitting the sweep's own
144 refusals as scenes to look at is the other half of CVR-5.

### Quality

One quality was fixed at 1600×900 and 64 samples. Three levels now, because the same command does two different
jobs — checking that a rig is arranged the way you meant, and producing something to look at:

| level | resolution | samples | per frame |
|---|---|---|---|
| `--quick-preview` | 960 × 540 | 16 | 0.09× |
| default | 1920 × 1080 | 128 | 2.9× |
| `--high-quality` | 3840 × 2160 | 384 | 17× |

Both on `scene:render` and on `build:all`, which forwards the level to every pass in its sweep. An explicit
`--samples` or `--resolution` wins over a level, so the levels are a shorthand rather than a constraint — that
matters for the one thing a level cannot say, like a 4K frame at 16 samples to check framing. Asking for both
levels at once is refused.

The two compound, which is why the variant sweep is no longer the default: eight variants at 2.9× a frame is about
**23×** one render of one scene, times 2688 scenes. `--every-variant --quick-preview` brings the whole of it back
under the cost of a single default pass.

**Raising the default does make existing renders stale**, as of the settings stamp above — so the PNGs still on
disk at the old 1600×900 redraw themselves on the next `build:all`, without a `--force` sweep. It costs the same
either way; the difference is that nobody has to know to ask.

Add `-v` to any build command to see Blender's own output; without it only one line per model is
printed, because Blender is extremely chatty.

## Static checks

Three tools read the code without running it, and `composer static` runs the two PHP ones together.

| tool | scope | command |
|---|---|---|
| PHPStan, level 5 | `src`, `tests` | `ddev exec composer stan` |
| PHP-CS-Fixer, Symfony ruleset | `src`, `tests`, `bin/console` | `ddev exec composer cs`, `cs-fix` to apply |
| Ruff | `blender/`, `tools/` | `pipx run 'ruff==0.16.6' check .` |

**Level 5 is a measured choice.** The counts when PHPStan first ran over this repository were 1 error at level 0,
18 at level 2, 51 at level 4, 64 at level 5, 231 at level 8 and 522 at max. Level 5 is where the errors stop being
missing annotations and start being real ones, so all 64 were fixed rather than baselined and `phpstan.neon`
carries no ignores. A new error therefore fails the build instead of joining a list nobody reads.

**What the first pass actually found**, since that is the argument for keeping it:

* `Stack.php` documented its run shape as `App\Scene\DeviceSpec`, a class that does not exist — the import was
  missing and the name resolved into the wrong namespace, so twelve array shapes described nothing. The same in
  `SceneStackCommand` for `Fault`.
* Three shapes had gone stale against the code: the rig tuple lost `split` when SWP-2 added it, the sweep task
  tuple lost the `LowEndBias` that `$task[5]` reads, and the built-scene shape lost the `faults` that
  `Feasibility::of()` reads. Every one of them was a docblock that no longer described its own data.
* `Stack::nearest()` ended in a fallback that could never run and would have thrown if it had, because it indexed
  `$references[0]` on the array it was guarding against being empty.
* `assertLessThan()` was called with four arguments in `StackSolverTest`. PHP drops a surplus argument to a
  userland function silently, so the warnings the author passed for a failing run were never in the message.
* `ShapeTest` called `isCabinet()` and threw the result away, and one array literal named `--low-end` twice.

**Ruff is pinned to an exact version**, because `pipx run ruff` takes whatever is newest and a release that adds a
rule would fail a push that changed no Python. `UP031` is ignored: it wants f-strings in place of percent
formatting in 52 `print()` calls, which buys nothing but the risk of a typo inside a console message.

**`bpy` cannot be type-checked from outside Blender**, so nothing here does. Ruff's lint rules are what is
checkable, and they found an unused `import bpy`, a dead `along = 1 - axis` in the flare solver and two
simplifications.

## Why PHP and Python

PHP owns the specs: loading, validation, the catalog, deciding what needs rebuilding. Python owns
the geometry, because bpy is Blender's only automation API and hand-rolling a glTF writer in PHP
would just duplicate what Blender already does well. The two meet at one JSON file per model, which
means the Python side never has to know about YAML, defaults or validation — it builds what it is
told — and the PHP side is unit-testable without Blender installed.

## Up-to-date checks

`models:build` skips a model when its `.glb` and `.blend` are both newer than everything that
shapes them: the spec file, all the scripts under `blender/`, and the `mesh_override` if there is
one. Comparing against the scripts matters — a change to the geometry builder has to rebuild the
whole library, not only the specs somebody edited. `--force` ignores all of it.

## Failure modes worth knowing

* **`Required binary not found: blender`** — you are running outside ddev, or the web container was
  built before Blender was added to it. `ddev debug rebuild` fixes the latter.
* **`Blender reported success but did not write ...`** — Blender can exit 0 after a Python error, so
  the builder checks that the output files actually appeared. Re-run with `-v` to see the traceback.
* **`Specs are invalid — refusing to build`** — deliberate. Building models from specs that break
  the conventions would produce a library whose pieces no longer fit together, which is the one
  thing this repo exists to prevent.
* **`These models are missing or out of date`** from `library:build` — run `models:build` first;
  a library that silently missed half the gear would be worse than an error.
