1. import the real gear list — [docs/inventory.md](docs/inventory.md)
    1. get read-only Drive access (service account + rclone remote, steps are in the doc)
    2. find the gear list in the SdWa5 Shared Drive
    3. `inventory:import <file>` — one spec per row, filling `clone_of`, `quantity` and whatever
       dimensions the list already carries; leave every field without a source absent rather than
       guessed
    4. identify which original each cabinet clones, then fill dimensions/weight/drivers/coverage
       from the originals' datasheets and record them in [docs/sources.md](docs/sources.md) — this
       is what gets the whole library from `estimated` to `datasheet` in one pass
2. `scene:build` — declarative scene files in [scenes/](scenes)
    1. scene YAML (device id, position, rotation, stacked-on) → assembled `.blend`
    2. reproducible and diffable, unlike a hand-built scene; makes "try a different setup" a commit
       instead of a memory
    3. a lighting + camera template so a preview render needs no manual setup
    4. report total weight per flown point / per truss from the placed devices
3. measure the cabinets — [docs/measuring.md](docs/measuring.md); `catalog` lists what is still
   un-measured
4. coverage cones from `audio.coverage_deg` — optional geometry, render-invisible like the other
   markers, so a setup can answer coverage questions and not just look right
5. more categories: truss, amp racks, stands. The schema and validator already accept them; the
   geometry builder needs shapes for truss segments
6. handle recesses are currently a plain rectangular cut — a rounded dish would read better
7. asset previews are blank because they cannot be rendered in background mode. Either generate them
   in the GUI once, or find a way to render thumbnails headless
8. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that
   only runs when `blender/` or `specs/` changed
9. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is
   what GDTF embeds, so this should mostly be packaging and metadata mapping
10. `mesh_override` is validated and passed through the build plan but the geometry builder still
    ignores it — wire it up so a hand-made or downloaded mesh can replace a generated one while the
    spec keeps owning the dimensions
