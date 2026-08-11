# Pipeline

How a spec file becomes a model you can drag into a scene.

```
specs/speakers/top-a.yaml          hand-written, the source of truth
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
ddev exec bin/console build:all --aim-line-variants --lighting-variants
```

**Every stage skips what is already current**, so a rebuild after touching one scene costs that scene and its
renders rather than the whole library. Freshness is one rule shared by all of them (`App\Build\Staleness`): an
output is stale when it is missing, or older than any of its inputs.

| Stage | Output | Inputs it watches |
|-------|--------|-------------------|
| `models:build` | `build/glb/<id>.glb` + `build/blend/<id>.blend` | the spec, `blender/build_model.py`, all of `blender/lib`, any mesh override |
| `scene:build` | `build/scenes/<id>.blend` | the scene file, `blender/build_scene.py`, `blender/lib`, **and the model of every cabinet the scene places** |
| `scene:render` | the PNG | the scene's `.blend`, `blender/render_scene.py`, `blender/lib` |

Mtimes rather than hashes, and that choice is what makes the *chain* work with no bookkeeping: a spec is newer
than its model, so the model rebuilds; the model is then newer than the scene, so the scene reassembles; the
scene is then newer than the render, so the render redraws. Each stage only ever compares its own neighbours.

`--force` on `build:all`, `scene:build` or `scene:render` rebuilds anyway. On `build:all` it now reaches
**every** stage — it used to reach only `models:build`, so a forced run still reused stale scenes and renders.

`build:all` is the stage order written down. Every stage already refuses to run on stale input — the
library will not be stitched from models older than their specs, a scene will not place a device whose
model is out of date — but nothing knew the *order*, so getting from an edited spec to a new render meant
remembering five commands and which of them the edit had invalidated.

It delegates rather than reimplements, so each stage's own staleness rules, reporting and refusals are the
ones that apply, and a failing stage stops the run.

The variant options are what make it more than a shell alias. `--lighting-variants` renders every scene
under each of the four lighting presets and `--aim-line-variants` renders each with and without aim lines;
together that is eight passes into eight folders under `build/renders/`. With no variant asked for, output
goes exactly where `scene:render` always put it.

Add `-v` to any build command to see Blender's own output; without it only one line per model is
printed, because Blender is extremely chatty.

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
