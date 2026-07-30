# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.7.0] - 2026-07-30

### Added

- **`scene:render`** — renders an assembled scene to `build/renders/<id>-<camera>.png`. A preview now
  costs one command and no Blender knowledge; the 17-cabinet rig takes about 8 seconds on the
  container's CPU
- **Camera presets** (`-c`): `three-quarter` (default), `front`, `side`, `top`, `crowd`. Each is a
  *direction*, not a position — the distance is computed so the scene's own bounding sphere fits the
  narrower field of view, whatever the aspect ratio. The same preset therefore frames a single floor
  monitor and a fourteen-wide sub wall equally well, and no scene ever needs a camera placed by hand
- **Lighting presets** (`-l`): `studio` (default), `stage` (warm key, coloured rims), `daylight` (sun
  and sky — where most SdWa5 events are), `flat` (even and shadowless, for inspecting a chamfer or a
  grille inset). Light positions are multiples of the scene radius and area-light power scales with its
  square, so a big rig is not dark at the settings that light one cabinet
- `--samples`, `--resolution`, `--no-ground`, `--out`, and `--presets` to list what is available
- `src/Render/` (`CameraPreset`, `LightingPreset`, `RenderPlan`) and `blender/render_scene.py`. The
  framing maths lives in PHP so it is unit-testable — camera framing is exactly the kind of thing that
  drifts silently when nobody can assert on it
- `tests/Render/RenderPlanTest.php` — 12 tests covering bounds, framing distance scaling with scene
  size, per-preset placement, eye-height handling and light power scaling. Suite is now 127 tests
- `docs/scenes.md` gained a full Rendering section; `docs/blender.md` no longer says lighting has to be
  set up by hand

### Changed

- The `crowd` preset frames deliberately tighter than 1:1 (margin 0.92): standing in front of a 4 m sub
  wall it fills your view, and a shot that politely fits it all in undersells it

## [0.6.0] - 2026-07-30

### Added

- **`scene:build`** — a PA setup written as YAML in `scenes/` becomes `build/scenes/<id>.blend` with
  every cabinet placed at true scale. A setup that worked is now a commit rather than somebody's
  memory, and next year's variation is a diff
- `scenes/full-rig.yaml` — the whole PA in about twenty lines: a 14-cabinet sub wall as two stacked
  rows of seven, plus the three M2122 tops. 17 cabinets, 1394 kg, 2.486 m tall, 4.26 × 0.96 m footprint
- **`on:` stacking** — a placement sits on an earlier one and its height is worked out from the specs,
  so no z coordinate is ever written into a scene. When somebody finally measures the Flexys, every
  stack in every scene corrects itself instead of quietly becoming wrong
- **`repeat: { count, step }`** — a sub wall is two lines instead of fourteen entries. A repeated row
  can be stacked on another repeated row
- Scene report, printed before Blender runs: cabinet count, total weight, tallest stack, floor
  footprint from cabinet extents, and per-device and per-owner breakdowns. Plus three warnings that
  are cheap here and expensive on site — using **more cabinets than the inventory has**, depending on
  **borrowed gear** (any device whose `owner` is not `sdwa5` — what that field was added for), and
  positions resting on **un-measured** cabinets
- `--dry-run` reports without touching Blender, which is how CI checks scenes
- `src/Scene/` (`Placement`, `SceneSpec`, `SceneLoader`, `SceneCompiler`, `PlacedDevice`,
  `SceneReport`) and `blender/build_scene.py`. All the arithmetic lives in PHP so stacking and
  repetition are unit-testable without Blender
- `docs/scenes.md`; `ArrayReader::numberList()`; 22 new tests (suite now 116)

### Changed

- Each device's collection is appended **once** and instanced per placement, so a 14-cabinet wall
  costs one copy of the geometry — the shipped 17-cabinet scene is under 100 KB
- `scene:build` refuses to assemble a scene from stale or missing models, and from invalid specs
- CI now also runs `scene:build --dry-run`

## [0.5.0] - 2026-07-30

### Added

- **`mesh_override` now works.** A spec can point at a real mesh and it replaces the generated shell
  outright — grille and handle recesses included, since real CAD models those better than the builder
  can. `blender/lib/mesh_import.py` imports `.obj`, `.glb`, `.gltf`, `.stl`, `.ply` and `.blend`
- `mesh_override` accepts a bare path, or an expanded form with `units` (m/cm/mm), `rotate_deg` and
  `tolerance_m`. Real CAD arrives in whatever units and orientation its author used, so the spec has
  to say which rather than the builder guessing
- **The spec stays the authority:** after importing, scaling and rotating, the builder measures the
  mesh and fails the build if any axis is off by more than the tolerance, printing all three numbers.
  A silently mis-scaled cabinet still looks like a cabinet and would quietly poison every setup built
  from it
- `src/Spec/MeshOverride.php`; `SpecValidator` rejects a missing path, a format Blender cannot import
  (naming `.FCStd` explicitly, since that is the likely mistake here), unknown units and a negative
  tolerance
- `/meshes/` added to `.gitignore` — override meshes are third-party files and stay out of git

### Changed

- The Flexy's CAD mesh was tested against this: it imports correctly with `units: mm` and
  `rotate_deg: [90, 0, 90]`, and its depth and height match the spec exactly, but it is **18 mm
  narrower** than the cabinet — a second, independent confirmation that the mesh omits something.
  Its `mesh_override` therefore stays `null` until that is reconciled, and the finding is recorded in
  the spec and in `TODO.md`
- `docs/spec-format.md` gained a "Mesh overrides" section; `docs/conventions.md` no longer describes
  `mesh_override` as unimplemented

## [0.4.0] - 2026-07-30

### Changed

- **`provenance` is now tracked per field** — `provenance.dimensions` and `provenance.weight`, with a
  single value still accepted as shorthand for both. They diverge in practice: a hanging scale
  settles a cabinet's weight in a minute, while taping fourteen subs is an afternoon. With one field
  for both, a weighed-but-unmeasured cabinet still reported `plans` and the easy half of the work
  showed no progress at all
- `catalog` reports "Dimensions measured: n of m" and "Weights measured: n of m" as separate counts,
  and the Provenance column shows `plans/estimated` when the two differ
- `eighteensound-2way-15`, `achenbach-18` and `tecnare-m2122-clone` now declare
  `weight: estimated` against `dimensions: plans`/`datasheet`, which is what was true all along and
  previously only visible in prose
- Only an estimated **shape** gets the orange viewport tag; an estimated weight does not distort the
  model, so it is reported by `catalog` rather than drawn
- `SpecValidator` checks the clone/`clone_of` rule against each provenance field separately
- `flexy-folded-horn-hybrid`: width refined to 0.591 m
- Stale `tree/master` links updated to `tree/main` after the parent repo's branch rename

### Added

- `src/Spec/ProvenanceSet.php` with `weakest()`, `isFullyMeasured()` and a compact `label()`
- `ArrayReader::isSection()` — needed for fields that accept either a scalar shorthand or an
  expanded mapping
- `tests/Spec/ProvenanceSetTest.php`; suite is now 92 tests

## [0.3.0] - 2026-07-30

### Added

- `specs/speakers/tecnare-m2122-clone.yaml` — three M2122-shaped tops exist, not two: two genuine
  Tecnare cabinets plus one self-built. Kept as its own spec rather than raising the factory pair's
  quantity, because build, provenance and weight all differ and a setup should be able to tell which
  cabinet is which. `Hardware Overview.xlsx` counts only the factory pair
- Inventory totals are now 6 specs, 25 cabinets, 1834 kg, 8.96 m³

### Fixed

- `flexy-folded-horn-hybrid`: width corrected to **0.590 m** (owner-confirmed, and what the
  comparison spreadsheet said all along). The CAD mesh in Drive reads 0.573 m, so it omits ~17 mm of
  the real cabinet; height and depth still come from the mesh, which agrees with the spreadsheet's
  rounded figures while being more precise

### Changed

- The two remaining estimated weights are now **calculated rather than guessed**, with the working
  written into the specs: plywood surface area × density, plus internal panels, drivers and hardware.
  The whole Shared Drive (1166 files) was searched for weight data and has none, and Eighteen Sound
  publishes no finished-cabinet weight for a DIY kit — a hanging scale is the only remaining source
- `docs/sources.md`: per-device rows updated; the unsourced weights are now a table with their basis
- `docs/inventory.md`, `README.md`, `TODO.md`: totals, the Tecnare split, and the measuring backlog

## [0.2.0] - 2026-07-30

### Added

- The real inventory, read out of the SdWa5 Shared Drive (`Hardware/Hardware Overview.xlsx` and
  `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx`): 5 enclosures, 24 cabinets, 1766 kg, 8.5 m³
  - `specs/speakers/flexy-folded-horn-hybrid.yaml` — 14×, 0.573 × 0.763 × 0.964 m, 85 kg; dimensions
    measured off the design's own CAD mesh
  - `specs/speakers/skram.yaml` — 2×, 0.610 × 0.813 × 0.914 m, 90 kg; dimensions cross-checked
    against both the cut list in Drive and Josh Ricci's published 24″ × 32″ × 36″
  - `specs/speakers/tecnare-m2122.yaml` — 2×, tapered 0.500 m front / 0.345 m rear × 0.960 × 0.520 m,
    68 kg, 60° × 40°; from the Tecnare L2122LT datasheet. First spec to use `shape: trapezoid`
  - `specs/speakers/eighteensound-2way-15.yaml` — 2×, 0.420 × 0.800 × 0.335 m; dimensions read off
    the FRONT and SIDE VIEW drawings of Eighteen Sound's 15″ 2 Ways Kit application note
  - `specs/speakers/achenbach-18.yaml` — 4×, 0.600 × 0.600 × 0.700 m; derived from the panel geometry
    in the FreeCAD model in Drive
- `owner` spec field (default `sdwa5`) — some cabinets at an SdWa5 event are lent by a member, and a
  setup that quietly depends on borrowed boxes can fall apart. `catalog` gained an Owner column and
  reports units and weight per owner: sdwa5 18 units / 1506 kg, sepp 6 units / 260 kg
- `docs/sources.md` now records, per device, exactly which file each number came from

### Changed

- `SpecValidator`: `provenance: datasheet`/`plans` only requires `clone_of` for a **clone**. Factory
  gear cites its own datasheet, so demanding an "original" there forced a fiction — found while
  entering the Tecnare, which is a bought cabinet
- `SpecValidator`: rigging-point bounds are not checked when a dimension is impossible. An inverted
  bounding box made every point look out of bounds and buried the one error worth fixing
- `docs/inventory.md` rewritten: what is in Drive, what is in the specs, what is still missing, and
  the configured read-only `rclone` remote
- `README.md`, `TODO.md`: current state is a real inventory rather than photo estimates; the measuring
  backlog is now item 1

### Removed

- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — the photo-estimated placeholders,
  superseded by the five real specs

### Notes

- Nothing has been **measured** yet: every dimension describes a design or a datasheet, and the
  weights for `eighteensound-2way-15` and `achenbach-18` are outright estimates. `catalog` reports
  the un-measured count on every run
- SKHORN, GHORN and OTHORN appear in the comparison spreadsheet with full dimensions but were
  evaluated, not bought — deliberately absent from `specs/`

## [0.1.0] - 2026-07-30

### Added

- Initial repository: spec-driven parametric 3D models of SdWa5 speakers and stage equipment, for
  Blender event previews and PA setup planning
- `specs/` — one YAML file per device as the single source of truth for dimensions, weight, rigging
  points, and the provenance of every number. Most SdWa5 cabinets are DIY clones of commercial
  designs, so `build`/`clone_of`/`provenance` record which original a cabinet copies and whether its
  numbers come from a datasheet, build plans, a measurement or a guess
- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — worked examples estimated from
  `~/Documents/SdWa5/2024-07-17_pa-lautsprecher-foto.jpeg`, marked `provenance: estimated`
- `bin/console` — Symfony Console application with `specs:validate`, `models:build`,
  `library:build` and `catalog`
- `src/Spec/` — spec value objects, `SpecLoader`, and `SpecValidator` enforcing the shared
  conventions: positive dimensions, chamfer and grille inset within bounds, unique ids matching
  their filenames, valid subtype per category, `clone_of` present for clones, rigging points inside
  the cabinet, taper dimensions stated explicitly per shape
- `src/Build/` — `BuildPlan` (the PHP → Python hand-off), `BlenderRunner` (headless invocation with
  `--factory-startup` for reproducibility), `ModelBuilder` (up-to-date checks, output verification)
- `src/Catalog/CatalogRenderer` — equipment table with total weight, total volume and the count of
  devices that have never been measured
- `src/Process/` — `ProcessRunner`, `ProcOpenProcessRunner` and `BinaryChecker`, ported from the
  sibling `yt-dlp-tools` project
- `blender/build_model.py`, `blender/build_library.py` and `blender/lib/` — geometry, the shared
  material set, metadata and export. Box, trapezoid and wedge shells, chamfer, recessed grille
  behind a frame, handle recesses cut as applied booleans, rigging markers, and an orange tag on
  estimated cabinets
- `blender/build_library.py` also marks every device as a Blender collection asset with stable
  catalog UUIDs, so the library is draggable from the Asset Browser
- `tools/check-glb.py` — verifies an exported model against its own glTF `extras`: bounding box
  equals the declared dimensions, and the cabinet sits on the floor
- `tests/` — 83 PHPUnit tests mirroring `src/`, including validator rejection cases and the Blender
  invocation via a fake process runner; no Blender needed
- `.ddev/` — PHP 8.3 CLI project with Blender 4.3.2 installed into the web image, pinned to the
  version Debian 13 ships so container-written `.blend` files open on the host
- `.github/workflows/tests.yml` — PHPUnit and `specs:validate` on every push
- `docs/` — conventions, spec format, pipeline, Blender usage, measuring checklist, inventory and
  Drive access, sources and licensing, plus the generated `docs/catalog.md`
- `README.md`, `TODO.md`, `CHANGELOG.md`

### Notes

- Canonical model format is glTF 2.0 (`.glb`), chosen because it is Blender-native and the model
  format embedded in GDTF/MVR — the entertainment industry's scene exchange standard, which already
  covers truss and rigging and has audio speakers on its roadmap
- Nothing generated is committed: `build/` is gitignored and reproducible from the specs
