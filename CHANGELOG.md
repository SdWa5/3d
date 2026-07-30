# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
