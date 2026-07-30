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

The two specs in the repository are **worked examples estimated off a photo**, not an inventory.
Most SdWa5 cabinets are DIY builds of commercial designs, so the fastest way to a real library is to
identify which original each one clones and take the numbers from its datasheet — the gear list for
that lives in the SdWa5 Shared Drive and is not imported yet. See
[docs/inventory.md](docs/inventory.md) and [`TODO.md`](TODO.md).

`catalog` always reports how many devices have never been measured, so the gap between "we have
models" and "we have accurate models" stays visible.

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
