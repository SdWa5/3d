# Sources

Where each device's numbers come from. Every spec's `provenance` and `clone_of.reference` point at a
row here, so a number can always be traced back to something — or visibly to nothing.

Keep this table in step with the specs. It is the difference between "0.58 m high" and "0.58 m high
*because the original's datasheet says so*".

## Devices

| Device | Clones | Reference | Source | Notes |
|--------|--------|-----------|--------|-------|
| `top-a` | unknown | none | `~/Documents/SdWa5/2024-07-17_pa-lautsprecher-foto.jpeg` | Estimated off the photo. Original not identified yet — see [inventory.md](inventory.md) |
| `sub-a` | unknown | none | `~/Documents/SdWa5/2024-07-17_pa-lautsprecher-foto.jpeg` | Estimated off the photo. Original not identified yet |

## What counts as a source

| `reference` | Means |
|-------------|-------|
| `datasheet` | the original manufacturer's published spec sheet — link it |
| `plans` | the build plans the cabinet was made from |
| `cad` | the original's published CAD/3D model |
| `none` | nothing yet; the numbers are measured or guessed |

## Licensing

Only relevant for `mesh_override`, and only ever for models we did **not** generate:

* Everything the builder produces is our own geometry, generated from our own measurements or from
  published dimensions. Dimensions are facts about an object, not a creative work.
* A **downloaded** original mesh (manufacturer CAD, a marketplace model) is somebody else's work.
  Check its licence before using it as a `mesh_override`, and record the licence in the table above.
  Manufacturer CAD is commonly free to use for system design but not to redistribute.
* Nothing binary is committed to this repository at all — `build/` is ignored — so a
  `mesh_override` mesh must be stored outside it, and the spec references it by path. That keeps a
  third-party licence question from ever becoming a question about this repo's history.

## Original datasheets

Empty until the originals are identified. One row per original, so several clones can share it:

| Original | Datasheet | Retrieved |
|----------|-----------|-----------|
| — | — | — |
