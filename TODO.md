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
2. **`align` cannot spread a mixed row.** A tier that lands in several runs — and the tops row is always
   `18sound + M2122 + 18sound` — has no single envelope to justify into, so `center`, `block` and `stereo` resolve to
   the same rig and `scene:stack` still writes one all-speaker scene rather than three. The 2-ways *do* now go outboard
   on the shoulders of a flanked row, but as a gravity repair — it fires only when the contiguous row would leave a
   cabinet on less than half its width, not because an alignment asked for it
3. **stability is still only checked structurally.** One-wide sub tiers are refused now, which was the tower, but
   nothing weighs the rig: the half-a-cabinet support rule passes a 286 mm overhang on a 1.240 m base, and
   `weight_kg` is on every spec while no centre of mass is ever computed. A tipping angle — combined centre of mass
   against the base half-width — would put a number on it, and there is no citable limit to compare it against, so it
   wants reporting rather than refusing
4. **only one tier per pass is flanked from below, and only if it fits a single row.** A device needing several rows
   would have to say *which* of its rows gets the flanks, and a device that already names its row-mates with
   `mix_with` is left alone. One flanked tier is all this inventory can produce, so the general case is untested
5. **the whole inventory cannot be turned at once.** A rolled SKRAM is 610 mm tall and a rolled Flexy 591, so a bottom
   row mixing them has a 19 mm step and the tier above straddles it — gravity lifts each cabinet onto the taller
   neighbour it catches and the bearing check reports 17 %. Turned rigs work where the row below is level (Flexys +
   Achenbachs + tops come out at 94 % worst bearing). A shim, or a `bearing` rule that weighed the *drop* rather than
   only the overlap, would be the way in
6. **an odd mirrored row is lopsided by one cabinet** — `intdiv(n, 2)` go left and the rest right
7. **finish GMSS.** The four speakers, their models, `scenes/gmss-full-stack.yaml` and its renders are done —
   all `provenance: estimated` and all in the measuring backlog. What is left:
    1. **measure the four cabinets.** Nothing in them is sourced: the dimensions are reconstructions scaled off
       our own measured cabinets and off a Turbosound TMS-4, and the weights are calculated skins. A tape measure
       replaces the lot. See [docs/sources.md](docs/sources.md#gmss-is-estimated-end-to-end)
    2. **confirm the counts that were not stated.** 8 turbo subs and 3 turbo tops are stated; the 2 middle subs
       and 2 mid-bass cabinets are read off the photograph and are guesses
    3. resolve what "USB" means in "USB 2x 700rms mid bass", and the two drivers' size
    4. the tier heights in the middle of the stack, and whether the mid-bass cabinets really stand stacked. The
       plinths visible under everything in the photo are not modelled because nothing is known about them
    5. the 9 m truss and the two 5.2 m towers — `category: truss` with subtype `straight`/`tower` already exists,
       and both numbers are stated, so this needs less invention than the cabinets did
    6. the 4 Martin MAC Performance 2 moving heads, as `category: other`. Real datasheet dimensions are published
       for these, so they would be the only GMSS items with solid provenance
    7. reference photo: /home/stefanr/.config/JetBrains/PhpStorm2026.2/scratches/GMSS.jpeg
8. detailed geometry for the remaining cabinets ([docs/sources.md](docs/sources.md#3d-geometry-per-device))
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
9. daylight renders, insides of speakers come out a little bit too dark
10. finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done
    1. write the splayed sub arc scene. `arc` covers the schema side now — a mirrored Flexy arc is
       `arc` plus `roll_deg: 180`, which the arc's roll rule deliberately allows — but no scene uses it, and a sub arc
       is the case where the reported footprint reads worst: a bounding box around a fan includes floor that nothing
       stands on
    2. more render polish: per-device colour (e.g. flexy bracings green), and a truss/stage backdrop so a preview looks
       like a venue rather than a void
    3. port the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) into scene files — they encode
       stack arrangements that already worked
11. more categories: truss, amp racks, stands. The schema and validator already accept them; the geometry builder needs
    shapes for truss segments and rack boxes
    1. Truss: 2x 4 meter telescope feet; 5x 2m three point truss segments
    2. 2x 5 meter gerüst, 7 meter arbeitshöhe including 2 meters persons hand height (Das Krause Plattformgerüst AH7
       lässt schnell und einfach werkzeuglos montieren. Aufgrund des vertikal unabhängigen Rahmens kann es auf Treppen
       verwendet werden. Die Stabilität wird durch Diagonalträger gewährleistet. Die 7 m hohe, 1,50 m lange und 0,60 m
       breite Plattform ist witterungsbeständig. Bei der zweiten Aufstockung wird der Gerüstrahmen um 2 Meter angehoben,
       was mit dem Sockel und der ersten Aufstockung bis zu 7 Meter Arbeitshöhe bedeutet.)
    3. amps and DSP are documented in Drive but not modelled — Gisen MM14K, Behringer Europower 4000, t.amp Proline 3000
       (2 racks for amps 1 rack for power distribution)
    4. lighting: 2 600w rgb led strobes, 1 mini moving head, 1 mini laser
12. audio routing table, for the coverage work ->
    https://docs.google.com/spreadsheets/d/1lLv8RN6I70Efh1ktJXTcqyx2qMsr7obSXus2ypfaWUs/edit?gid=1412726604#gid=1412726604
13. `inventory:import` — the first import was done by hand because the source is several spreadsheets and CAD files
    rather than one list, and every number needed a provenance decision. Worth building when the gear list next grows;
    see [docs/inventory.md](docs/inventory.md)
14. asset previews are blank because they cannot be rendered in background mode. Either generate them in the GUI once,
    or find a way to render thumbnails headless
15. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that only runs when
    `blender/` or `specs/` changed
16. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so
    this should mostly be packaging and metadata mapping
17. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a design or a
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
18. fly through renderings + combine with new project from existing audio routing table
19. endfire setup add other sub and tops
20. **`scene:stack` with no `--from` now mixes two sound systems.** The default is "every speaker, subs before
    tops", and since the GMSS cabinets arrived that includes another system's gear: the tops row comes out
    `1× eighteensound + 2× tecnare + 3× gmss-turbo-top + 1× tecnare + 1× eighteensound` at 4.071 m and the solve
    refuses. It refuses with a clear reason rather than writing a wrong rig, so this is degraded and not broken,
    and `--per-owner` (which now yields three stacks) or an explicit `--from` both work. The fix is for the
    default to mean "one system's gear" — either an `--owner` narrowing option or owner-awareness in
    `everySpeaker()`. Owner is not quite the right discriminator, since the repository deliberately supports
    borrowing gear between owners, so this needs a decision before it needs code
21. **`audio.drivers` cannot record a count without a size.** `size_in` is required on every entry, so a cabinet
    known to have two drivers of unknown size — `gmss-mid-bass`, from "USB 2x 700rms" — has to omit the whole
    `audio` block and put the count in its notes. Making `size_in` optional would let the schema hold what is
    actually known instead of forcing a choice between inventing a size and recording nothing
