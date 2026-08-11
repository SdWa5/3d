1. add more both-systems scenes:
    1. all speakers in 1 stack 2 stacks and three stacks independent of owner or system
    2. add all the other equipment
2. alignments of speakers and object groups
    1. **`align` on nested groups.** `align` takes a single `row`/`lattice` today and refuses anything nested, because
       scaling a nested arrangement's x would stretch the inner group's spacing along with the outer one's. Telling the
       two apart needs the level named — a key nothing shipped wants yet. Same for `arc`
       and `line_array`, which own their own spacing and are refusals rather than gaps
    2. **`stereo` splits into halves only.** Column size is `floor(n/2)`; asking for 2 + 2 out of six with two in the
       middle needs a `columns:` key. Nothing shipped wants it yet
    3. **only the top tier can be spread**, because spreading a load-bearing tier turns it into gaps and the tier above
       stands over air. So per-tier `align` decides *which* alignment the top tier uses, never *how many* tiers spread.
       A rig with siblings rather than a pure tower would change that
3. **`align` cannot spread a mixed row.** A tier that lands in several runs — and the tops row is always
   `18sound + M2122 + 18sound` — has no single envelope to justify into, so `center`, `block` and `stereo` resolve to
   the same rig and `scene:stack` still writes one all-speaker scene rather than three. The 2-ways *do* now go outboard
   on the shoulders of a flanked row, but as a gravity repair — it fires only when the contiguous row would leave a
   cabinet on less than half its width, not because an alignment asked for it
4. **only one tier per pass is flanked from below, and only if it fits a single row.** A device needing several rows
   would have to say *which* of its rows gets the flanks, and a device that already names its row-mates with
   `mix_with` is left alone. One flanked tier is all this inventory can produce, so the general case is untested
5. **the whole inventory cannot be turned at once.** A rolled SKRAM is 610 mm tall and a rolled Flexy 591, so a bottom
   row mixing them has a 19 mm step and the tier above straddles it — gravity lifts each cabinet onto the taller
   neighbour it catches and the bearing check reports 17 %. Turned rigs work where the row below is level (Flexys +
   Achenbachs + tops come out at 94 % worst bearing). A shim, or a `bearing` rule that weighed the *drop* rather than
   only the overlap, would be the way in
6. **an odd mirrored row is lopsided by one cabinet** — `intdiv(n, 2)` go left and the rest right
7. **finish GMSS.** The four speakers, their models, `scenes/gmss-full-stack.yaml` and its renders are done — all
   `provenance: estimated` and all in the measuring backlog. What is left:
    1. **measure the four cabinets.** Nothing in them is sourced: the dimensions are reconstructions scaled off our own
       measured cabinets and off a Turbosound TMS-4, and the weights are calculated skins. A tape measure replaces the
       lot. See [docs/sources.md](docs/sources.md#gmss-is-estimated-end-to-end)
    2. **confirm the counts that were not stated.** 8 turbo subs and 3 turbo tops are stated; the 3 middle subs and 2
       mid-bass cabinets are read off the photograph and are guesses. Only 6 of the 8 turbo subs are in the photo, so
       `gmss-full-stack.yaml` builds six — where the other two go is unknown
    3. resolve what "USB" means in "USB 2x 700rms mid bass", and the two drivers' size
    4. **whether the third middle sub really lies on its side** in the middle bay's second row. What the photo shows
       there is a cross-braced horn mouth, wider than it is tall; a middle sub rolled a quarter turn fits that shape,
       carries the 1.420 m tops row at 92% bearing and lands the tops 50 mm off level with the columns — three things
       agreeing, but still a reading of a photograph rather than a fact. It could be a cabinet type GMSS never listed.
       The low boxes under the middle bay are not modelled either
    5. reference photo: /home/stefanr/.config/JetBrains/PhpStorm2026.2/scratches/GMSS.jpeg
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
11. more categories: the remaining lighting. **Truss, moving heads, the Gerüst and the racks are done** —
    `shape: truss`, `moving-head` and `scaffold` all build from the shared tube primitive in `blender/lib/tubes.py`, and
    `specs/` now has truss/, lighting/, stands/ and racks/ beside speakers/. A rack turned out to need no new geometry
    at all — a case IS a box — so the only geometry still missing is a telescoping mast
    1. lighting: 2 600w rgb led strobes, 1 mini moving head, 1 mini laser. No brands stated, so no dimensions — the
       weakest-sourced group left. `shape: moving-head` already exists for the mini head
    2. **a telescoping mast shape for the towers.** `truss-tower-4m` and `gmss-tower-5m` are `shape: box` — a 0.203 m
       column, which is the folded base size. A crank stand is a nested mast on folding outriggers, and neither the
       nesting nor the outriggers are drawn. The outriggers matter most: unfolded they spread to 1.499 × 1.499 m, which
       is what has to be kept clear at the feet on a real stage, where the scene shows a column a fifth of that. A
       `mast` shape taking a section count and the folded/unfolded base would fix both
    3. also truss tower feet are three — noted against the mast shape above, since that is the item the base belongs
       to. AMBIGUOUS AND NOT ACTED ON: it could mean each stand has a three-leg base where the spec's comment
       describes a square 1.499 × 1.499 m outrigger spread, or it could mean we own three stands rather than the two
       the original note said. The first reading fits where it sits; the second would change `truss-tower-4m`'s
       quantity. One answer settles it
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
20. **`scene:stack` with no `--from` now mixes two sound systems.** The default is "every speaker, subs before tops",
    and since the GMSS cabinets arrived that includes another system's gear: the tops row comes out
    `1× eighteensound + 2× tecnare + 3× gmss-turbo-top + 1× tecnare + 1× eighteensound` at 4.071 m and the solve
    refuses. It refuses with a clear reason rather than writing a wrong rig, so this is degraded and not broken, and
    `--per-owner` (which now yields three stacks) or an explicit `--from` both work. The fix is for the default to mean
    "one system's gear" — either an `--owner` narrowing option or owner-awareness in
    `everySpeaker()`. Owner is not quite the right discriminator, since the repository deliberately supports borrowing
    gear between owners, so this needs a decision before it needs code
21. **`audio.drivers` cannot record a count without a size.** `size_in` is required on every entry, so a cabinet known
    to have two drivers of unknown size — `gmss-mid-bass`, from "USB 2x 700rms" — has to omit the whole
    `audio` block and put the count in its notes. Making `size_in` optional would let the schema hold what is actually
    known instead of forcing a choice between inventing a size and recording nothing
22. **`scene:stack` spaces an aimed row on the cabinet's flat width and ignores the toe-in.** Every cabinet in an aimed
    row toes in on the focus from its OWN position, so a row does not share one yaw — and two boxes at different angles
    need about `depth x sin(yaw difference)` more room than two parallel ones. The solver gives them the stack's plain
    `gap_m`, so a stack far enough off the centre line writes a scene whose own cabinets overlap:
    `both-systems-per-owner-center.yaml`'s GMSS stack sits 2.65 m out and its three aimed tops came out **16.2 mm inside
    each other** at `gap_m: 0.02`. Caught by the overlap sweep in `ShippedScenesTest`, not by eye. That scene is
    generated with `--gap=0.05` as a workaround, which is honest but blunt — it widens every gap in every stack to fix a
    problem in one row of one of them. The fix is for the row spacing to account for the neighbour's yaw the way `align`
    already accounts for a toed-in cabinet being wider across x than it is wide. It is not GMSS-specific — any
    `--per-owner` or `--stacks=N` rig whose outer stacks sit far enough out can hit it, and the wider the rig the worse
    it gets
23. **a generated scene's comment table goes stale while the scene itself stays correct.** `scene:stack` writes a
    row-by-row summary into the file as comments — "1 4x gmss-turbo-sub 2.55 m wide" — but the `stack:` block below it
    is re-solved by the compiler on every build. So the picture always follows the current specs while the comments
    describe the solve as it stood when the file was written. Rescaling the GMSS cabinets in 0.55.0 left
    `both-systems-per-owner-center.yaml` claiming rows of 3.26 m that build as 2.55 m, and a five-row stack that builds
    as four. Nothing is wrong with the rig; the documentation beside it is wrong, which is worse than no documentation
    because it reads as authoritative. Options: regenerate the summary at build time, have
    `scene:build` warn when a generated scene's comments disagree with what it just solved, or drop the table and print
    it from `scene:build` instead. Regenerating the file by hand is the workaround today
24. **stability is still only checked structurally.** One-wide sub tiers are refused now, which was the tower, but
    nothing weighs the rig: the half-a-cabinet support rule passes a 286 mm overhang on a 1.240 m base, and
    `weight_kg` is on every spec while no centre of mass is ever computed. A tipping angle — combined centre of mass
    against the base half-width — would put a number on it, and there is no citable limit to compare it against, so it
    wants reporting rather than refusing
25. **`provenance.dimensions` cannot say "outer box sourced, internals estimated".** It is one field for the whole
    geometry, and the shapes built from parts break that assumption: `gmss-mac-2000-performance-ii` has its 408 × 490 ×
    743 mm box and its 39.5 kg from Martin's datasheet, while the split between base, yoke and head comes off product
    photographs. The spec says `datasheet` — correct for the bounding box everything downstream reads — and the
    qualification survives only as prose in the file and in docs/sources.md, where nothing can check it. The same
    applies to `geruest-krause-ah7`, whose footprint and weight are published and whose tube diameters are not. A
    per-block provenance, or a `provenance.parts` beside `dimensions`, would let the spec say what is actually true
25. **two amplifier facts are unresolved, and both are cheap to settle by looking at the rack.** `rack-amp-12u`'s
    weight is derived from published figures for every amp in it, so these are the only loose ends: (a) the fourth amp
    is either a Behringer EP4000 or a t.amp Proline 3000 and the owner is not sure which — the Proline is 3U and 37 kg
    against the EP4000's 2U and 16.6, which moves each rack from 69 kg to 79; (b) "gisen md60" matches no Gisen
    product. Their M60-series DSP amplifiers fit the description at 1HE and under 13 kg, and M60Q-DSP and M60.12 are
    both plausible. The name on each front panel answers both
26. **the amplifiers have no specs of their own.** They are invisible inside a closed rack, so their published figures
    live in `rack-amp-12u.yaml`'s header and docs/sources.md rather than in five specs that would add five boxes
    nobody can see to `detail-check`. Worth revisiting only if an amp ever has to be placed on its own
27. **`rack-power-12u` is the weakest spec in the repository.** Its case follows the amp racks and its 15 kg of
    contents is a guess at breakers, socket panels and cable, with no component list to add up. Opening the rack and
    listing what is in it would make it as solid as the amp racks. Related: nothing in this schema can record
    electrical load, and the rig's amplifiers are rated in the tens of kilowatts — the breaker layout is what decides
    whether everything switches on at once
