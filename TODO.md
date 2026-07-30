1. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a
   design or a datasheet, not our build; `catalog` lists what is still un-measured
    1. hanging-scale the three estimated weights first: `eighteensound-2way-15` (30 kg),
       `achenbach-18` (50 kg) and `tecnare-m2122-clone` (68 kg, currently the factory figure).
       None of the three exists in any source — the whole Shared Drive was searched, and Eighteen
       Sound publishes no finished weight for a DIY kit
    2. verify `tecnare-m2122-clone` actually matches the factory cabinet's outer dimensions
    3. check the Flexy's own build against the design: the CAD mesh is 17 mm narrower than the
       confirmed 0.59 m width, so the mesh omits something
2. `scene:build` — declarative scene files in [scenes/](scenes)
    1. scene YAML (device id, position, rotation, stacked-on) → assembled `.blend`
    2. reproducible and diffable, unlike a hand-built scene; makes "try a different setup" a commit
       instead of a memory
    3. a lighting + camera template so a preview render needs no manual setup
    4. report total weight per flown point / per truss from the placed devices
    5. reuse the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) as reference
       layouts for the first scenes — they encode stack arrangements that already worked
3. more categories: truss, amp racks, stands. The schema and validator already accept them; the
   geometry builder needs shapes for truss segments and rack boxes
    1. amps and DSP are documented in Drive but not modelled — Gisen MM14K, Behringer Europower
       4000, t.amp Proline 3000
4. `mesh_override` is validated and reaches the build plan, but the geometry builder ignores it —
   wire it up so a hand-made or downloaded mesh can replace a generated block while the spec keeps
   owning the dimensions. Drive already has usable CAD for the Flexy (`subwoofer v28.obj`) and the
   Achenbach (`.FCStd`)
5. coverage cones from `audio.coverage_deg` — optional geometry, render-invisible like the other
   markers, so a setup can answer coverage questions and not just look right
6. `inventory:import` — the first import was done by hand because the source is several spreadsheets
   and CAD files rather than one list, and every number needed a provenance decision. Worth building
   when the gear list next grows; see [docs/inventory.md](docs/inventory.md)
7. handle recesses are currently a plain rectangular cut — a rounded dish would read better
8. asset previews are blank because they cannot be rendered in background mode. Either generate them
   in the GUI once, or find a way to render thumbnails headless
9. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that
   only runs when `blender/` or `specs/` changed
10. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is
    what GDTF embeds, so this should mostly be packaging and metadata mapping
