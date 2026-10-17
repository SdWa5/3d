# Published models

The public package of a device, committed so that a link printed on paper keeps working. A folder here holds what
the outside link expects to find, which is the model, its build plan and cut list, and the CAD the model and plan
come from. Everything else the builder writes goes to `build/` and is regenerated on demand. A device belongs here
only when something outside this repository points at it, for example a QR code on a build plan, and that link needs
a stable URL.

Unlike [`standalone/`](../standalone/README.md), these models are not modelled by hand. Each `.glb` is the plain
output of `models:build` for the spec of the same id. Source CAD sits in a `cad/` subfolder, and only CAD whose
licence is recorded in [`docs/sources.md`](../docs/sources.md#licensing) is committed.

| Model | Spec | W × H × D (m) | Files |
|-------|------|---------------|-------|
| [Flexy Folded Horn Hybrid](flexy-folded-horn-hybrid/) | [`flexy-folded-horn-hybrid.yaml`](../specs/speakers/sdwa5/flexy-folded-horn-hybrid.yaml) | 0.591 × 0.763 × 0.964 | `.glb`, preview, build plan EN and DE, cut list CSV, CAD |

## Flexy Folded Horn Hybrid

![The Flexy, closed](flexy-folded-horn-hybrid/preview-closed.png)

The design is MrFlexySMP's, presented in the video
[Folded Horn Hybrid on YouTube](https://www.youtube.com/watch?v=tzwWSL8TyKI). Our cabinets are the single version
of it.

The build plan's cover carries two QR codes. One opens the `.glb` in
[3dviewer.net](https://3dviewer.net/#model=https://raw.githubusercontent.com/SdWa5/3d/main/published/flexy-folded-horn-hybrid/flexy-folded-horn-hybrid.glb),
the other opens this folder. So the file name and the folder name are fixed for as long as printed plans exist.

- `flexy-folded-horn-hybrid.glb` is the SdWa5 build of the cabinet, including its castors and fittings. Its metadata
  names MrFlexySMP's video as `clone_of.url`.
- `build-plan-rev-d-en.pdf` and `bauplan-rev-d-de.pdf` are the 26-sheet A3 build plan, revision D of 17.10.2026.
  - Revision B added the sheet numbers, part names, the paper size in every scale field and the notes on the
    optional parts.
  - Revision C states the material, 18 mm multiplex, on the cover and on every sheet. It also appends sheet 26 with
    the parts list, the cut list and the recommended stock sizes.
  - Revision D credits MrFlexySMP's video on the cover and in the PDF metadata.
- `flexy-cutlist.csv` is the cut list for import into [cutlistoptimizer.com](https://www.cutlistoptimizer.com/), in
  the columns `Length,Width,Qty,Material,Label,Enabled`. Blanks are rounded up to whole millimetres, and the optional
  parts are imported disabled. The lid is cut out of the top panel and has no row of its own.

### `cad/`

| File | What it is | By |
|------|------------|----|
| `subwoofer-v28.obj` | The design's full-scale CAD in millimetres, 24 bodies, 573 mm wide because it models one side wall. The parts list was measured on it. The same file is in [Oliver Ripper's repository](https://github.com/Oletarier/bassw-rfel-folded-horn) | Oliver Ripper |
| `subwoofer-v28.x` | The same model as a DirectX export | Oliver Ripper |
| `subwoofer-v28-both-side-walls.obj` | A FreeCAD re-export with the second side wall added, 588 mm wide. It is the only complete full-scale model | SdWa5, from Oliver Ripper's CAD |
| `flexy-1to10.stl` | The 1:10 print model in centimetres, watertight and 2824 faces. The spec's `mesh_override` | SdWa5, from Oliver Ripper's CAD |
| `horn-extension.FCStd` | A horn extension for the Flexy in FreeCAD, 2023 | SdWa5 |
| `horn-extension.xlsx` | Its calculation, with the horn segments and the cut sizes | SdWa5 |

### Other Flexy resources

| Resource | Link |
|----------|------|
| MrFlexySMP's design video | https://www.youtube.com/watch?v=tzwWSL8TyKI |
| A similar design, SB Audience dual 18″ horn | https://www.sbaudience.com/index.php/application/dual-18inch-horn-subwoofer/ |
| The spec (dimensions, weight, drivers, count) | [`specs/speakers/sdwa5/flexy-folded-horn-hybrid.yaml`](../specs/speakers/sdwa5/flexy-folded-horn-hybrid.yaml) |
| The plan generator and the parts data | [`tools/flexy-plan/`](../tools/flexy-plan/) |
| The original plan as bought | [`tools/flexy-plan/original.pdf`](../tools/flexy-plan/original.pdf) |
| Sources and rights | [`docs/sources.md`](../docs/sources.md) |
| Frequency response measured against the SKRAM and the Achenbach 18 | https://github.com/SdWa5/dsp/blob/main/docs/measurements.md |
| Drive folder with the design video copy, a front view and two photos of our rig (SdWa5 members) | https://drive.google.com/drive/folders/1XdZACErorGMSUiMl0TZeQFCY4FON4Mjr |
| Cabinet comparison and efficiency sheet (SdWa5 members) | https://docs.google.com/spreadsheets/d/15yo5IYkhrUXI_YFDnhXNwVoykogJKv4TkT4kepfMjbA |
| 1:10 print variants for the merch case (SdWa5 members) | Drive `Medien/Bildbearbeitung/Merch/CAD/3D Print Smoking paper rolls case - 3381901/files/` |

## Rebuilding

```bash
ddev exec bin/console models:build --id=flexy-folded-horn-hybrid -f
cp build/glb/flexy-folded-horn-hybrid.glb published/flexy-folded-horn-hybrid/
python3 tools/check-glb.py 'published/*/*.glb'
```

The spec's `mesh_override` is `cad/flexy-1to10.stl` in this folder, so every checkout builds the real cabinet rather
than the generated block.

The build plan is written by `tools/flexy-plan/` over the original plan `tools/flexy-plan/original.pdf`. The parts
data lives in `tools/flexy-plan/parts.json` alone, and the CSV and sheet 26 are both generated from it. The script
needs the Liberation Sans fonts and is byte-reproducible for a given `--date`.

```bash
python3 -m venv tools/flexy-plan/.venv
tools/flexy-plan/.venv/bin/pip install -r tools/flexy-plan/requirements.txt
cd tools/flexy-plan
.venv/bin/python build.py original.pdf ../../published/flexy-folded-horn-hybrid/build-plan-rev-d-en.pdf en
.venv/bin/python build.py original.pdf ../../published/flexy-folded-horn-hybrid/bauplan-rev-d-de.pdf de
python3 parts.py > ../../published/flexy-folded-horn-hybrid/flexy-cutlist.csv
```

## Licence

The Flexy design is MrFlexySMP's, from the video [Folded Horn Hybrid](https://www.youtube.com/watch?v=tzwWSL8TyKI).
The CAD model and the build plan are by Oliver Ripper, and SdWa5 bought them together with the full rights. The horn
extension and the derived meshes in `cad/` are SdWa5's own work. All of it is published here under CC BY-SA 4.0 like
the rest of this repository's data.
