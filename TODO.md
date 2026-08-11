1. alignments of speakers and object groups
    1. **`align` on nested groups.** `align` takes a single `row`/`lattice` today and refuses anything nested, because
       scaling a nested arrangement's x would stretch the inner group's spacing along with the outer one's. Telling the
       two apart needs the level named — a key nothing shipped wants yet. Same for `arc`
       and `line_array`, which own their own spacing and are refusals rather than gaps
    2. **`stereo` splits into halves only.** Column size is `floor(n/2)`; asking for 2 + 2 out of six with two in the
       middle needs a `columns:` key. Nothing shipped wants it yet
    3. **only the top tier can be spread**, because spreading a load-bearing tier turns it into gaps and the tier above
       stands over air. So per-tier `align` decides *which* alignment the top tier uses, never *how many* tiers spread.
       A rig with siblings rather than a pure tower would change that
2. **the SKRAMs are in no rig scene.** 180 kg, appearing only in `detail-check.yaml`. They cannot be in a stack —
   nothing shares their 0.914 m height so they cannot be mixed into a row, and a row of the two of them carries
   nothing — so `scene:stack` leaves them out and says so. An **end-fire pair** would use exactly two and mirror
   `end-fire.yaml`; the spacing is a quarter wavelength at a target frequency, `343 / (4f)` — 1.715 m at 50 Hz,
   2.144 m at 40 Hz — and which frequency is a decision, not a derivation
3. **a stack is never checked for stability.** `--per-owner` gives `sdwa5` a rig 1.8 m wide and 4.9 m tall, because
   chasing the 2 m interface narrows the rows until the tiers are single columns. Geometrically valid, physically
   tippy. Related: the half-a-cabinet support rule passes a 286 mm overhang on a 1.240 m base, which is marginal —
   an aspect-ratio or centre-of-mass check would catch both
4. **the floating-cabinet test only covers `scenes/`.** A rig that exists only as a `scene:stack` invocation is
   checked for support by the solver but never by the separating-axis and support sweeps in `ShippedScenesTest`
5. detailed geometry for the remaining cabinets ([docs/sources.md](docs/sources.md#3d-geometry-per-device))
    1. tecnare top
        1. add the high frequency horns mounting braces vertically and horizontally each (this is probably not worth a
           general feature)
        2. make the sides of the connected horns one flat piece instead of three each side (probably currently ist more
           complex than necessary) no unnecessary parts or unnecessary details
    2. find our custom flexy 3d model with the actual braces (smaller W-like metal braces) somewhere in gdrive or
       locally
    3. two ways top
        1. find or model acutal horn from online available data or at least close gaps between horn and cabinet
        2. ports are not modelled. The 18sound's two Ø100 mm holes come from its CAD, but nothing sits behind them, and
           a generated cabinet has no way to declare a port at all
    4. handle recesses are currently a plain rectangular cut — a rounded dish would read better (implement general
       handle 3d model)
5. daylight renders, insides of speakers come out a little bit too dark
6. finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done
    1. write the splayed sub arc scene. `arc` covers the schema side now — a mirrored Flexy arc is
       `arc` plus `roll_deg: 180`, which the arc's roll rule deliberately allows — but no scene uses it, and a sub arc
       is the case where the reported footprint reads worst: a bounding box around a fan includes floor that nothing
       stands on
    2. more render polish: per-device colour (e.g. flexy bracings green), and a truss/stage backdrop so a preview looks
       like a venue rather than a void
    3. port the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) into scene files — they encode
       stack arrangements that already worked
7. more categories: truss, amp racks, stands. The schema and validator already accept them; the geometry builder needs
   shapes for truss segments and rack boxes
    1. amps and DSP are documented in Drive but not modelled — Gisen MM14K, Behringer Europower 4000, t.amp Proline 3000
8. audio routing table, for the coverage work ->
   https://docs.google.com/spreadsheets/d/1lLv8RN6I70Efh1ktJXTcqyx2qMsr7obSXus2ypfaWUs/edit?gid=1412726604#gid=1412726604
9. `inventory:import` — the first import was done by hand because the source is several spreadsheets and CAD files
   rather than one list, and every number needed a provenance decision. Worth building when the gear list next grows;
   see [docs/inventory.md](docs/inventory.md)
10. asset previews are blank because they cannot be rendered in background mode. Either generate them in the GUI once,
    or find a way to render thumbnails headless
11. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that only runs when
    `blender/` or `specs/` changed
12. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so
    this should mostly be packaging and metadata mapping
13. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a design or a
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
14. fly through renderings + combine with new project from existing audio routing table
15. make endfire setup two rows vertically too and add tops
