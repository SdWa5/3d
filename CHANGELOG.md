# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
