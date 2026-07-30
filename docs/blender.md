# Blender

## Version

**4.3.2**, pinned on both sides:

* in the ddev web container, installed from the official tarball by
  [`.ddev/web-build/Dockerfile`](../.ddev/web-build/Dockerfile)
* on your machine, for actually looking at things: `sudo apt install blender` on Debian 13 gives
  exactly 4.3.2

The pin is not pedantry — `.blend` files are version-sensitive, and a container writing a newer
format than the host can open would make the asset library unopenable. `.glb` output is
version-independent, so only `.blend` files care.

To bump: change both `ARG`s in the Dockerfile, `ddev debug rebuild`, and install the same version on
the host. If Blender then fails to start in the container, run
`ldd /opt/blender/blender | grep 'not found'` inside it — a new release occasionally needs one more
system library.

## Using the asset library

1. `ddev exec bin/console models:build && ddev exec bin/console library:build`
2. In Blender: **Preferences → File Paths → Asset Libraries → +**, point it at
   `build/library/` in this repo, name it `SdWa5`
3. Open an **Asset Browser** editor, pick the `SdWa5` library, and drag devices into your scene

Every device is a **collection asset**, so one drag brings the whole cabinet — shell, grille,
markers. Assets are filed under catalogs by category (`SdWa5/Speaker`, …) and tagged with their
category, subtype and provenance, so `provenance:estimated` can be filtered out when you want to
see only trustworthy gear.

Thumbnails are blank at first: previews cannot be rendered in background mode. Generate them once
in the GUI — select the assets in the Asset Browser and use **Asset → Generate Preview** — or just
ignore them, the names are right there.

## Building a setup

The conventions are what make this quick:

* Everything is at true scale in metres, so real-world distances just work.
* Origins are at the bottom centre of the footprint, so a cabinet dropped at z = 0 stands on the
  floor and one placed at a sub's height sits on it. Snapping (**Shift+Tab**, vertex or face) does
  the rest.
* Front faces −Y, so a cabinet rotated to aim somewhere aims where you think it does.

`build/library/sdwa5-3d.blend` also has every device instanced in a row, which makes it a decent
visual overview of the gear.

## Rendering a preview

Nothing in this repo sets up lighting or cameras yet — that is what the scene work in
[`../TODO.md`](../TODO.md) is for. For now, build a scene from the asset library, add your own
lighting and render normally. Two things are already taken care of:

* Rigging markers and the orange "estimated" tag are **render-invisible**, so they will not show up
  in the image.
* Materials come from one shared set, so cabinets from different specs match each other under the
  same light.

## What the build scripts do

| Script | Job |
|--------|-----|
| [`blender/build_model.py`](../blender/build_model.py) | one device: geometry, materials, metadata, export |
| [`blender/build_library.py`](../blender/build_library.py) | appends every device's collection, marks it as an asset, writes the catalog file |
| [`blender/lib/geometry.py`](../blender/lib/geometry.py) | the hexahedron shell, grille, handle recesses, markers, chamfer |
| [`blender/lib/materials.py`](../blender/lib/materials.py) | the shared material set, sRGB → linear |
| [`blender/lib/export.py`](../blender/lib/export.py) | scene reset, unit setup, custom properties, glTF/blend output |

They are always invoked through the PHP CLI (see [pipeline.md](pipeline.md)) and read a JSON build
plan — never a YAML spec directly. Running them by hand is possible but only useful for debugging:

```bash
ddev exec blender --background --factory-startup \
  --python blender/build_model.py -- --plan build/plans/top-a.json
```

`--factory-startup` is deliberate: without it a build would inherit whatever add-ons and unit
settings the person running it happens to have, and models would stop being reproducible.
