1. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a design or a
   datasheet, not our build; `catalog` lists what is still un-measured
    1. hanging-scale the three estimated weights first: `eighteensound-2way-15` (30 kg),
       `achenbach-18` (50 kg) and `tecnare-m2122-clone` (68 kg, currently the factory figure). None of the three exists
       in any source — the whole Shared Drive was searched, and Eighteen Sound publishes no finished weight for a DIY
       kit
    2. verify `tecnare-m2122-clone` actually matches the factory cabinet's outer dimensions
2. finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done
    1. more render polish: per-device colour so the sprayed grille art shows up, and a truss/stage
       backdrop so a preview looks like a venue rather than a void
    2. report weight per flown point / per truss once anything is actually flown
    3. port the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) into scene
       files — they encode stack arrangements that already worked
    4. angled stacks: `yaw_deg` covers aiming, but a splayed sub arc would want per-placement pitch
3. more categories: truss, amp racks, stands. The schema and validator already accept them; the geometry builder needs
   shapes for truss segments and rack boxes
    1. amps and DSP are documented in Drive but not modelled — Gisen MM14K, Behringer Europower 4000, t.amp Proline 3000
4. reconcile the Flexy CAD mesh so `mesh_override` can be switched on for it — the mesh is 18 mm
   narrower than the cabinet, confirmed twice. The Achenbach CAD is `.FCStd`, which Blender cannot
   read; export it to `.obj` or `.glb` from FreeCAD first
5. coverage cones from `audio.coverage_deg` — optional geometry, render-invisible like the other markers, so a setup can
   answer coverage questions and not just look right
6. `inventory:import` — the first import was done by hand because the source is several spreadsheets and CAD files
   rather than one list, and every number needed a provenance decision. Worth building when the gear list next grows;
   see [docs/inventory.md](docs/inventory.md)
7. handle recesses are currently a plain rectangular cut — a rounded dish would read better
8. asset previews are blank because they cannot be rendered in background mode. Either generate them in the GUI once, or
   find a way to render thumbnails headless
9. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that only runs when
   `blender/` or `specs/` changed
10. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so
    this should mostly be packaging and metadata mapping
