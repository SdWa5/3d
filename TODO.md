1. alignments of speakers and object groups
    1. **tetris stacking** — sort rows low frequency to high frequency and fill from the bottom up, instead of writing
       out a tier per placement by hand. Every `full-rig*` scene is this done manually: subs low, 18" above them, tops
       on top
        1. it is driven by a **constraint**, not by a tier list, and there are two ways to state one. Either is enough
           on its own; both together is a solve that can fail and has to say so:
            1. `max_width_m` — how wide the stage or the truss lets the rig be. The wall grows upward until the row no
               longer fits, which is how `full-rig`'s 12 Flexys became 2 × 6 at 3.646 m
            2. a **height at the sub/top interface** — stack subs until the tops' bottom edge (equivalently the sub
               stack's top face) clears a stated height, so the tops fire over the crowd rather than into it. Above head
               height is the point of it; the number is configurable and needs deciding,
               ~2.0 m is the candidate. Worked against what we own: 2 Flexy tiers reach 1.526 m and miss, 2 Flexy tiers
               plus an Achenbach row reach **2.126 m** and clear — which is exactly what
               `full-rig-three-tier` arrived at by hand, so the constraint reproduces a stack we already trust
            3. `min_width_m` and `max_height_m` as the other two bounds — a minimum width is how you ask for a wide
               short wall rather than a tall narrow one out of the same cabinets, and a maximum height is a ceiling or a
               rigging limit
        2. **there is no common module to lean on**, which is the real work. Our five cabinets have five different
           widths (0.4656 / 0.500 / 0.591 / 0.600 / 0.610 m) and five different heights (0.600 / 0.763 / 0.836 / 0.914 /
           0.960 m), no two of them multiples of anything. A fill that assumes a grid will look right on the Flexys
           alone and fall apart the moment an Achenbach or a SKRAM is in the same stack
        3. test it twice over the whole inventory, because the second case is the one that breaks a grid assumption:
            1. **all speakers except the two SKRAM** — 21 cabinets: 12 Flexy, 4 Achenbach, 3 Tecnare, 2 18sound. Two sub
               widths (0.591 / 0.600 m) and two top widths (0.500 / 0.4656 m)
            2. **all speakers including the two SKRAM** — all 23. The SKRAM is the widest cabinet we own at 0.610 m and
               the second tallest at 0.914 m, so it is what a tier boundary has to bend around
    2. **named layouts on top of the tetris stacking**, the way text has alignment. The stacking decides *what*
       goes in each tier; a layout decides *how it is distributed across the width*. Wanted, borrowing the text
       vocabulary:
        1. `center` — natural spacing, centred on `at`. What every `row` and `lattice` does today, and the only one that
           exists
        2. `block` (justify) — spread to fill a stated width with equal gaps, so a tier's outer edges land where you
           say. `scenes/full-rig-stereo.yaml` is this done by hand: `step_m: 0.8156` for the Achenbach row is "six 0.600
           m cabinets justified across 4.678 m"
        3. `stereo` — two columns pushed as far apart as the subs allow, with whatever is left over filling between them
           The reason this is worth generalising rather than leaving to arithmetic: **an aimed cabinet's outer edge
           cannot be computed from its width.** Aiming toes it in, a toed-in cabinet occupies more x than it is wide,
           and how far it toes in depends on where it ended up — so "align this tier's edge with that one's" is a fixed
           point, not a formula. `full-rig-stereo.yaml` needed three numbers bisected out by hand (0.8156, 2.1185,
           2.9709) and every one of them goes stale the moment a cabinet is measured or a focus moves. The same trap
           already bit `full-rig-all-tops.yaml`, where spacing two aimed cabinets on their widths overlapped them by 88
           mm, and `two-foci.yaml`, where it put the fills 0.41 m inside the sub wall. A layout that resolves against
           the rotated boxes would remove the whole class
2. detailed geometry for the remaining cabinets ([docs/sources.md](docs/sources.md#3d-geometry-per-device))
    1. tecnare top
        1. add the high frequency horns mounting braces vertically and horizontally each (this is probably not worth a
           general feature)
    2. find our custom flexy 3d model with the actual braces (smaller W-like metal braces) somewhere in gdrive or
       locally
    3. two ways top
        1. find or model acutal horn from online available data
        2. ports are not modelled. The 18sound's two Ø100 mm holes come from its CAD, but nothing sits behind them, and
           a generated cabinet has no way to declare a port at all
    4. handle recesses are currently a plain rectangular cut — a rounded dish would read better (implement general
       handle 3d model)
3. finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done
    1. write the splayed sub arc scene. `arc` covers the schema side now — a mirrored Flexy arc is
       `arc` plus `roll_deg: 180`, which the arc's roll rule deliberately allows — but no scene uses it, and a sub arc
       is the case where the reported footprint reads worst: a bounding box around a fan includes floor that nothing
       stands on
    2. more render polish: per-device colour (e.g. flexy bracings green), and a truss/stage backdrop so a preview looks
       like a venue rather than a void
    3. port the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) into scene files — they encode
       stack arrangements that already worked
4. more categories: truss, amp racks, stands. The schema and validator already accept them; the geometry builder needs
   shapes for truss segments and rack boxes
    1. amps and DSP are documented in Drive but not modelled — Gisen MM14K, Behringer Europower 4000, t.amp Proline 3000
5. audio routing table, for the coverage work ->
   https://docs.google.com/spreadsheets/d/1lLv8RN6I70Efh1ktJXTcqyx2qMsr7obSXus2ypfaWUs/edit?gid=1412726604#gid=1412726604
6. `inventory:import` — the first import was done by hand because the source is several spreadsheets and CAD files
   rather than one list, and every number needed a provenance decision. Worth building when the gear list next grows;
   see [docs/inventory.md](docs/inventory.md)
7. asset previews are blank because they cannot be rendered in background mode. Either generate them in the GUI once, or
   find a way to render thumbnails headless
8. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that only runs when
   `blender/` or `specs/` changed
9. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so
   this should mostly be packaging and metadata mapping
10. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a design or a
    datasheet, not our build; `catalog` lists what is still un-measured
    1. hanging-scale the two estimated weights first: `eighteensound-2way-15` (41 kg) and `achenbach-18`
       (50 kg). Neither exists in any source — the whole Shared Drive was searched, and Eighteen Sound publishes no
       finished weight for a DIY kit
    2. weigh and measure the self-built Tecnare against the two factory ones. All three share one spec at the factory's
       68 kg, and nothing has confirmed the copy matches
    3. the Flexy and SKRAM have no `audio.layout` — their drivers sit deep in a folded horn path. If a render ever looks
       into a mouth from close up, that changes
    4. confirm the SKRAM mesh's orientation — its dimensions verify, but which face carries the mouth has not been
       checked against the real cabinet. `rotate_deg: [90, 0, 0]` currently puts the open chambers upwards;
       `[90, 0, 180]` and `[-90, 0, 0]` are the other candidates
    5. **measure the Tecnare baffle.** Its whole `audio.layout` is estimated: three horn mouths, two throats, two centre
       heights and both flare laws. One tape measure across one mouth promotes it from `estimated` to
       `measured` and is the single highest-value measurement left in the repo
    6. **measure the Tecnare's three fly points.** `rigging.points` currently holds the only rigging positions in the
       repo and all three are derived from the nominal box rather than from the cabinet — two on the top face at x
       ±0.185, y −0.100, and a pull-back at the centre of the rear face 0.120 m up. They exist so `flyable: true`
       validates and so a flown scene has something to snap to; nothing has confirmed where the real track sits. The
       schema has no provenance field for rigging, so the estimate is stated in a comment in the spec — worth adding one
       if more flyable gear arrives
11. fly through renderings + combine with new project from existing audio routing table
