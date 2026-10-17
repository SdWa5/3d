# Override meshes and front images

Two spec keys point at files in here, and **nothing in this directory is committed** except this README. Override
meshes are third-party CAD and front images are as often as not somebody else's photograph (see
[`../docs/sources.md`](../docs/sources.md#licensing)), so the repository references both by path
instead of carrying them.

| Key | What it is | What happens without the file |
|---|---|---|
| `mesh_override` | a real mesh replacing the generated shell | the generated block is built instead |
| `front_image` | a photograph of the cabinet's front, for a cabinet with no interior | the front stays plain |

A spec naming a file this checkout does not have is therefore **normal, not an error**:
`specs:validate` warns and `models:build` carries on. Fetch what you want and rebuild.

**Front images are additionally off unless asked for.** `models:build --front-images` applies them;
without the switch every cabinet is built plain even when its spec names a photograph. Flipping the
switch rebuilds the affected models on its own.

The size matters. PSL's front drawings are **1 px = 1 cm**, and the validator uses that to compare the
image against `geometry.dimensions_m` on both axes, so a photograph of the wrong cabinet is caught
rather than silently stretched. A file at another scale says so with `px_per_cm`.

## Fetching from the SdWa5 Shared Drive

Needs the read-only `SdWa5` rclone remote — see
[`../docs/inventory.md`](../docs/inventory.md#drive-access).

```bash
# Our own photographs of the Flexy wall and the full rig, which the Flexy and Tecnare specs cite
D="SdWa5:Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/MrFlexySMPs Folded Horn Hybrid"
rclone copy "$D/photo-flexy-wall.png" meshes/sdwa5/
rclone copy "$D/photo-full-rig.png" meshes/sdwa5/
```

The photographs are evidence for the specs and are not built into any model. After fetching a mesh or a front image,
rebuild the model it belongs to:

```bash
ddev exec bin/console models:build --id=<id> -f
ddev exec bin/console scene:build && ddev exec bin/console scene:render full-rig
```

## What is available, per device

| Device | Source in Drive | Usable? |
|--------|-----------------|---------|
| `flexy-folded-horn-hybrid` | **Committed**, in [`published/flexy-folded-horn-hybrid/cad/`](../published/flexy-folded-horn-hybrid/cad/), because SdWa5 owns the rights. Nothing to fetch | `flexy-1to10.stl` (1:10, watertight, 2824 faces) is the `mesh_override`: `units: cm`, `rotate_deg: [90, 0, 90]`, 3 mm narrower than spec. `subwoofer-v28.obj` has the horn but is a non-watertight shell with one side wall |
| `achenbach-18` | `…/Achenbach 18/Achenbach 18.FCStd` | Needs a FreeCAD export first — Blender cannot read `.FCStd` |
| `eighteensound-2way-15` | `…/18sound/Eighteensound 2 Way Point Source 15.FCStd` | Same: export from FreeCAD |
| `skram` | — | No 3D anywhere; only the cut list |
| `tecnare-m2122`, `tecnare-m2122-clone` | — | No 3D anywhere; datasheet line drawings only |

## Converting CAD — `ddev mesh-convert`

Blender reads none of `.FCStd`, `.step`, `.f3d` or `.SLDPRT`, so CAD has to be meshed first:

```bash
ddev mesh-convert meshes/skram.step                 # → meshes/skram.stl
ddev mesh-convert "src/Achenbach 18.FCStd" meshes/achenbach-18.stl
```

It prints the bounding box in millimetres, which is what you need to choose `units` and
`rotate_deg` for the spec. Then:

```bash
ddev exec bin/console models:build --id=<id> -f
```

The builder refuses the mesh if it disagrees with the spec's declared dimensions, so a wrong
`units` or `rotate_deg` fails loudly instead of producing a mis-scaled cabinet.

### Why it is a ddev *host* command

`bin/console` runs inside the web container, which has no docker — and FreeCAD lives in a container
because installing it into the ddev image would add well over a gigabyte for a step run rarely.
`ddev mesh-convert` therefore executes on the host, where docker is. Override the image with
`MESH_CONVERT_IMAGE` if you prefer another.

Two things learned the hard way, both handled by the script:

* FreeCAD resolves `HOME` through `getpwuid` and dies with *"Getting HOME path from system failed!"*
  for a uid with no passwd entry, so it runs as root and chowns the output back.
* `FreeCAD.open()` only understands `.FCStd`; STEP and IGES go through the `Import` module.
  [`../tools/freecad-export.py`](../tools/freecad-export.py) dispatches on the extension.

It also exports **solids only**. A FreeCAD document is full of datum planes and axes, and meshing
those inflates the bounding box — which then fails the dimension check for no real reason.

A large `.FCStd` can be slow: `Achenbach 18.FCStd` (3.8 MB of BREP) did not finish inside ten
minutes, while the 18sound file took about two. Give it time, or export from a FreeCAD GUI instead.
