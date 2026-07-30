# SdWa5 3D

3D models of SdWa5's speakers and stage equipment, for rendering event previews in Blender and for
trying out different PA setups in 3D.

Models are **generated from specs**, not collected. One text file per device declares its real
dimensions, weight, rigging points and where those numbers came from; a Blender script builds the
model from it. That is what makes the library usable: every piece shares one scale, one orientation
and one origin convention, so cabinets from different specs actually stack and snap together.

## Requirements

- [ddev](https://ddev.readthedocs.io/) — Blender and PHP both live in the container
- Docker
- Blender **4.3.2** on your machine, to look at the results:
  `sudo apt install blender` on Debian 13 gives exactly that version

## Setup

```bash
ddev start          # first run builds Blender into the web image: ~1 GB, a few minutes
ddev composer install
```

## Usage

```bash
ddev exec bin/console specs:validate      # check every spec (no Blender needed)
ddev exec bin/console models:build        # build .glb + .blend for whatever changed
ddev exec bin/console library:build       # assemble the Blender asset library
ddev exec bin/console catalog             # equipment table with weight/volume totals
ddev exec bin/console list                # all commands
```

Then in Blender: **Preferences → File Paths → Asset Libraries → +**, point it at `build/library/`,
open an Asset Browser and drag devices into a scene. Every device is one collection asset at true
scale, already sitting on the floor. See [docs/blender.md](docs/blender.md).

## Commands

| Command | Does |
|---------|------|
| `specs:validate` | Validates every spec against the shared conventions. Runs without Blender, so CI runs it too |
| `models:build` | Spec → `build/glb/<id>.glb` + `build/blend/<id>.blend`. Skips up-to-date models; `--force` to rebuild, `--id=<id>` to narrow |
| `library:build` | Assembles `build/library/sdwa5-3d.blend` with every device as a draggable collection asset |
| `catalog` | Equipment table plus total weight, total volume and how many specs still need measuring. `--write` also writes `docs/catalog.md` |

## Adding a device

1. Copy an existing file in [`specs/speakers/`](specs/speakers) — the filename must equal the `id`
2. Fill in the real numbers, from the cloned original's datasheet or by measuring
   ([docs/measuring.md](docs/measuring.md)), and set `provenance` to say which
3. `ddev exec bin/console specs:validate && ddev exec bin/console models:build --id=<id>`
4. Record where the numbers came from in [docs/sources.md](docs/sources.md)

Field reference: [docs/spec-format.md](docs/spec-format.md).

## Repository layout

```
specs/          one YAML file per device — the source of truth
blender/        bpy build scripts, invoked headless by the PHP CLI
src/            PHP: spec loading, validation, catalog, build orchestration
tests/          PHPUnit, mirroring src/
tools/          check-glb.py — verifies an exported model matches its own metadata
scenes/         scene definitions (from v0.2.0)
build/          generated models, asset library, renders — gitignored
docs/
```

Nothing generated is committed. `build/` is reproducible from the specs with one command, which is
why the repository stays text-only and diffable.

## Current state

The whole PA is in the library — **6 specs, 25 cabinets, 1834 kg, 9.0 m³**:

| Device | Owner | Qty | W × H × D (m) | kg each |
|--------|-------|-----|---------------|---------|
| Flexy Folded Horn Hybrid | sdwa5 | 14 | 0.590 × 0.763 × 0.964 | 85 |
| SKRAM | sdwa5 | 2 | 0.610 × 0.813 × 0.914 | 90 |
| Tecnare M2122 | sdwa5 | 2 | 0.500 × 0.960 × 0.520 (tapered, 0.345 rear) | 68 |
| Tecnare M2122 (clone) | sdwa5 | 1 | same as above | 68 (placeholder) |
| Eighteensound 2-Way 15″ | sepp | 2 | 0.420 × 0.800 × 0.335 | 30 (est.) |
| Achenbach 18 | sepp | 4 | 0.600 × 0.600 × 0.700 | 50 (est.) |

The two factory Tecnare tops and the self-built third one are separate specs, because build,
provenance and weight all differ and a setup should be able to tell them apart.

Most are DIY builds of commercial designs, so their dimensions come from the designs themselves —
CAD meshes, cut lists, published build plans and one datasheet ([docs/sources.md](docs/sources.md)
records which, per device). Amps, DSP, racks and truss are documented in Drive but not modelled yet.

**Nothing has been measured yet.** Every number describes a design or a datasheet, not the cabinet
in the barn, and two weights are outright estimates. `catalog` reports the un-measured count on
every run, so the gap between "we have models" and "we have accurate models" stays visible — see
[docs/measuring.md](docs/measuring.md) and [`TODO.md`](TODO.md).

## Documentation

- [Conventions](docs/conventions.md) — units, axes, origin, naming, materials, fidelity, provenance
- [Spec format](docs/spec-format.md) — every field, and what the validator rejects
- [Pipeline](docs/pipeline.md) — how a spec becomes a model, and the failure modes
- [Blender](docs/blender.md) — version pinning, asset library, building setups
- [Measuring](docs/measuring.md) — checklist for turning a real cabinet into a spec
- [Inventory](docs/inventory.md) — the gear list, and Drive access via rclone
- [Sources](docs/sources.md) — where each device's numbers come from, and licensing

Org-level documentation lives in the parent repo's
[`docs/`](https://github.com/bestcodename/sdwa5/tree/master/docs).

## Development

```bash
ddev exec composer test                          # PHPUnit
ddev exec bin/console specs:validate             # same check CI runs
python3 tools/check-glb.py 'build/glb/*.glb'     # exported models match their own metadata
```

Tests do not need Blender: the spec, catalog and orchestration layers are unit-tested, and the
Blender invocation is tested through a fake process runner. PHP owns the specs, Python owns the
geometry — see [docs/pipeline.md](docs/pipeline.md#why-php-and-python).
