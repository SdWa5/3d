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
ddev exec bin/console catalog             # table + weight/volume totals
ddev exec bin/console catalog --write     # also write docs/catalog.md
```

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
