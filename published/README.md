# Published models

Models generated from a spec, committed so that a link printed on paper keeps working. Everything else the builder
writes goes to `build/` and is regenerated on demand. A model belongs here only when something outside this
repository points at it, for example a QR code on a build plan, and that link needs a stable URL.

Unlike [`standalone/`](../standalone/README.md), these are not modelled by hand. Each one is the plain output of
`models:build` for the spec of the same id, copied here together with whatever the outside link expects to find.

| Model | Spec | W × H × D (m) | Files |
|-------|------|---------------|-------|
| [Flexy Folded Horn Hybrid](flexy-folded-horn-hybrid/) | [`flexy-folded-horn-hybrid.yaml`](../specs/speakers/sdwa5/flexy-folded-horn-hybrid.yaml) | 0.591 × 0.763 × 0.964 | `.glb`, preview, build plan EN and DE |

## Flexy Folded Horn Hybrid

![The Flexy, closed](flexy-folded-horn-hybrid/preview-closed.png)

The build plan's cover carries two QR codes. One opens the `.glb` in
[3dviewer.net](https://3dviewer.net/#model=https://raw.githubusercontent.com/SdWa5/3d/main/published/flexy-folded-horn-hybrid/flexy-folded-horn-hybrid.glb),
the other opens this folder. So the file name and the folder name are fixed for as long as printed plans exist.

- `flexy-folded-horn-hybrid.glb` is the SdWa5 build of the cabinet, including its castors and fittings.
- `build-plan-rev-b-en.pdf` and `bauplan-rev-b-de.pdf` are the 25-sheet A3 build plan, revision B of 16.10.2026.
  Revision B adds the sheet numbers, part names, the paper size in every scale field and the notes on the optional
  parts. The same files are in the SdWa5 Shared Drive next to the original plan.

## Rebuilding

```bash
ddev exec bin/console models:build --id=flexy-folded-horn-hybrid -f
cp build/glb/flexy-folded-horn-hybrid.glb published/flexy-folded-horn-hybrid/
python3 tools/check-glb.py 'published/*/*.glb'
```

The spec uses `meshes/flexy-1to10.stl` as its `mesh_override`. That mesh is not committed, so a checkout without it
builds the generated block instead, and the result must not be copied here.

## Licence

The Flexy design belongs to MrFlexySMP. The CAD model and the build plan are by Oliver Ripper, and SdWa5 bought them
together with the full rights. They are published here under CC BY-SA 4.0 like the rest of this repository's data.
