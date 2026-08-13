1. not only enforce subwoofer ceiling strictly (optimum 2-3 meters) but mostly improve all of the already exisisting
   logic to reach that goal
    1. dont avoid generating the scenes or ignore the ceiling height or use less speakers if there is any other
       possiblilty to solve this
2. full-rig-truss-three-quarter.png: make new version: add the two skram, the gerüste are take the place of the truss
   stands (remove those). turn the gerüste 90 degrees for that (align front with front of sound system). fly the tecnare
   tops by hanging them wide apart on the truss . eighteensound two ways near field like always in combination with the
   tecnare
3. everything-three-quarter.png: remove unused truss stands, turn gerüste 90 degrees and align with their fronts the
   same as the sound systems front
4. detail-check-turned-three-quarter.png: add missing stuff

5. add option that aligns the scenes / stacks / rows at their front faces instead of centers

6. stereo and mono placing logic improvement:
    1. subs always get placed mono if possible, in a stereo scene (which is defined mainly by the topss placements) the
       subs get spread out as wide as possible or necessary so that its possible to set the tops as far apart as
       possible on top for the broadest possible stereo image
    2. in a mono scene the outermost tops should still get placed as wide as possible apart but all tops should be
       spaced as evenly as possible
    3. symmetry between the stacks inside the stacks and the rows should be optimized though

7. **the goal, and where it stands.** As many *sensible* speaker configurations as possible, generated automatically by
   a single command in its default settings, with priority on the configurations actually used in praxis as well as ones
   algorithmically derived. **Bare `scene:stack` now writes 10 scenes where it wrote none** — one rig per owner plus one
   from everything, by one, two and three stacks, in both shapes and all three alignments, every refusal named. It wrote
   28 before the sub height band bound; *sensible* is the word being enforced, and the trade is stated in item 9. What
   is left of this item:
    1. **"actually used in praxis" is not covered yet.** The ~13 real event setups in Drive (`…/setups/`, 2D SVG) are
       still not ported — see the scene-work item. Everything generated so far is algorithmic
    2. **`owner` is standing in for "system"** in the sweep, and it is not quite right: the repository deliberately
       supports borrowing gear between owners, so a rig can legitimately mix owners. It separates the two systems in
       practice, and inventing a `system:` field to serve a sweep would be inventing a property to serve a layout

8. **stack ordering is done for mono and unexercised for stereo.** The taller stacks now go to the middle in mono and
   the ends in stereo, ordered by their own solved sub height — `--per-owner` went from `3.34 | 3.20 | 1.80` to
   `2.44 | 3.61 | 1.80`. Two things are left. **No multi-stack stereo rig survives the checks**, so that half is
   implemented and untested: the tops-spread envelope refuses the narrow supports every generated scene has, and a rig
   wide enough to prove it does not exist yet. And **symmetry is improved rather than delivered** — ordering places the
   tall stacks but cannot make the flanks equal, which depends on the split giving each stack similar contents. 16 of 30
   multi-stack scenes are still not height-symmetric, and `--per-owner` never can be: three owners are three different
   systems

9. **the sub height band binds: a generated rig outside 2–3 m is not written.** Both bounds were preferences the solver
   warned about and built anyway, and measured, **only 16 of the 54 shipped scenes had every stack inside the band** —
   the rest included a 5.73 m wall and a 0.60 m one. The refusal names the height, the bound and the miss in
   millimetres; the two options are the control (`--interface-height=0 --max-sub-height=99` accepts anything); and it
   binds every invocation rather than only the sweep, because `build:all` replays each file's own narrowed command and
   would otherwise have rewritten every out-of-band file unchanged. The sweep also **moves a rig onto a stage that fits
   it** — a ladder of real widths, 2.00–6.00 m, walked in the direction the miss points — which recovered
   `stacked-all-2-center` (refused at 3.70 m, 2.033 and 2.833 m of subs at 4.40 m). The scene set is 17 files where it
   was 54. What is left:
    1. **coverage is now band-limited, and GMSS is where it hurts.** Of 132 candidates, 54 cannot fill a 2 m wall out of
       the cabinets they are given and 20 cannot get under 3 m on any stage in the ladder. Only `stacked-gmss-2-stereo`
       survives for that system and nothing at all for `sepp`. A rig too small to build a 2 m sub wall is a real rig
       played off a riser or on stands, and neither is modelled — that is the gap, not the band
    2. the `free` shape misses the ceiling far more often than the pyramid, which is inherent (it keeps the deepest
       cabinets on the floor and pays in height). Now that a miss is a refusal rather than a warning, the question is
       whether the sweep should stop offering `free` where the pyramid already solves, or keep it and accept the skips
    3. **a row may now sit off-centre on its support, but only where nothing stands beside it.** This is what recovered
       `stacked-gmss-1-center` at 2.84 m: the stage-width ladder cannot help a quantity-bound rig, and GMSS is one, so
       the lever is where the packed row sits rather than how wide the stage is. What is left is the multi-stack half.
       Stacks are spaced on their widest tier and their envelopes deliberately overlap in x, so a slide there reaches
       into the neighbour — measured unbounded, **180 mm of interpenetration across five `all-3` scenes**. Spacing
       neighbouring stacks from **resolved extents** rather than centred tier widths is the fix, and it is the same root
       as items 10 and 11 — do those first, then the bound can be the real gap to the neighbour instead of "no movement
       at all"

10. **`block` emits overlapping geometry on an aimed row — a defect, not the gap it was recorded as.** The clearance
    chain now covers every run of a tops row rather than the fills only, which fixed the 360 mm class where a split long
    throw was never spaced against itself, took the sweep from 25 written scenes to 28 and made `block` write 5 scenes
    where it wrote none. What is left is sharper than before, and re-measured against the current sweep: **all 8
    interpenetration refusals are `-block` variants and every one is same-tier** — `stacked-gmss-2-block` 360 mm,
    `stacked-sdwa5-1-block` 149 mm, `stacked-gmss-1-block` 92 mm, each with its `upright`/`free` sibling. Justifying an
    aimed row across its support is exactly the fixed point `StepSolver`
    exists for, and something in that path is not accounting for the toe-in. The generator refuses the output, so
    nothing broken ships, but this is a bug to find rather than a feature to add
11. **the tops row is placed against its support's extent, not its plateau.** 12 sweep candidates are refused for a top
    standing on nothing — `stacked-all-1-stereo`, `stacked-all-2-{block,center,stereo}`, `stacked-all-3-stereo` and
    their shape siblings — plus 6 more for a cabinet with nothing under it at all and 5 for one merely touching what
    carries it. Not a too-wide row — the case traced is a 1.456 m tops row on a 1.900 m support — but a *stepped*
    support: `wall-bass + skram +
    nuke` is 1.400, 0.914 and 0.590 m tall, so the outer tops drop to a lower face and overhang nothing.
    `StackSolver::swallows()` already encodes this rule for packed sub rows and has never guarded the tops row.
    Splitting the tops across two rows where the plateau cannot carry them would fix it, and would mean correcting
    `topRow()`'s "not split across tiers" comment — written when width was the only failure mode
12. **an aimed row spaced on flat widths still overlaps outside the default sweep.** Re-measured: the free-shape GMSS
    per-owner stack that overlapped by 22.5 mm **is fixed** and writes, so what is left is narrower and no longer only a
    `block` problem. `--stacks=3 --split=by-type` puts two runs of a by-type tops row **17.6 mm** inside each other on
    `center` and **135.6 mm** on `block`, and refuses `stereo` on the spread envelope — 3 cabinets 1.4065 m across into
    a 1.2400 m envelope. Same root as items 10 and 11: a row is spaced on nominal widths while its cabinets are aimed,
    so
    the toe-in is unaccounted for. The rigs are refused rather than written, so nothing broken ships
13. alignments of speakers and object groups
    1. **`align` on nested groups.** `align` takes a single `row`/`lattice` today and refuses anything nested, because
       scaling a nested arrangement's x would stretch the inner group's spacing along with the outer one's. Telling the
       two apart needs the level named — a key nothing shipped wants yet. Same for `arc`
       and `line_array`, which own their own spacing and are refusals rather than gaps
    2. **`stereo` splits into halves only.** Column size is `floor(n/2)`; asking for 2 + 2 out of six with two in the
       middle needs a `columns:` key. Nothing shipped wants it yet
    3. **only the top tier can be spread**, because spreading a load-bearing tier turns it into gaps and the tier above
       stands over air. So per-tier `align` decides *which* alignment the top tier uses, never *how many* tiers spread.
       A rig with siblings rather than a pure tower would change that
14. **the pyramid cap does not reach every row-building path.** `shape: pyramid` caps a row at the cabinet count of the
    row below, applied where rows are dealt (`rowSizeFor`) and packed (`packTo`) — but a lifted flank (`reserveLifts`),
    a stated `mix_with` (`statedMix`) and the mixed bottom row all build rows without asking. The bottom row needs no
    cap, being first. Re-measured against the current set, the other two are why **7 of the 9 pyramid-shaped stacks**
    still hold a sub row wider in cabinets than the one under it. `free` is excluded on purpose — it does not taper by
    design — and counting it in, 14 of all 17 stacks show a step
15. **only one tier per pass is flanked from below, and only if it fits a single row.** A device needing several rows
    would have to say *which* of its rows gets the flanks, and a device that already names its row-mates with
    `mix_with` is left alone. One flanked tier is all this inventory can produce, so the general case is untested
16. **the whole inventory cannot be turned at once**, and `build:all` now measures exactly where the line is rather than
    citing one example. It generates a turned sibling of every generated scene and, re-measured against the 10 the sweep
    now writes, **7 of the 10 solve**: `stacked-all-2-center` refuses turned, and two of the `stacked-sdwa5-2` variants
    do — one for a run inside another, one on the spread envelope. A rolled SKRAM is 610 mm tall against a rolled
    Flexy's 591, so a bottom row mixing them has a 19 mm step and the row above straddles it. A shim, or a `bearing`
    rule that weighed the *drop* rather than only the overlap, would be the way in

17. **an odd mirrored row is lopsided by one cabinet.** `Tier::mirrored()` sends `intdiv(n, 2)` left and the rest right,
    so a row of five reads 2 + 3 about the centre line. **THE WIDEST-REACHING OPEN ITEM: re-measured against the current
    set, 40 odd rows across all 11 mirrored stacks in 11 of the 17 scenes** — every mirrored stack in the library has at
    least one. The fix already exists for a different row — `StackSolver::stereoTopRow()` centres the odd cabinet rather
    than pushing it to one side, which is what makes a stereo tops row a palindrome. Applying the same rule here moves
    cabinets in every mirrored rig in the library, so it wants its own measured pass against the overlap and bearing
    sweep rather than being folded into another release
18. **finish GMSS.** The builder has given dimensions and weights for all **five** cabinets, so the photo-derived
    reconstruction is gone and with it most of this item: five specs, 14 cabinets, 994 kg, and
    `scenes/gmss-full-stack.yaml` now matches his own description of the arrangement. What is left:
    1. **measure the five cabinets.** They stay `provenance: estimated` deliberately — the figures are the builder's own
       statements, which is not a datasheet, not plans in hand, and not us taping it. Two he hedged himself ("maybe
       220kg" for the wall bass, "~120kg" for the mid bass). A tape measure and a hanging scale settle the lot
    2. **the mid bass's 120 kg disagrees with arithmetic.** An 18 mm skin over 3.24 m² is about 40 kg, plus horn and two
       drivers about 80 — 120 kg would make it the densest cabinet in either system at 333 kg/m³. Recorded as stated,
       with the disagreement noted
    3. resolve what "USB" stands for in "USB 2x 700rms mid bass", and the two drivers' size. The cabinet is identified
       now — the 1.200 × 0.500 m horn lying across the wall basses — but the acronym and the drivers are not
    4. **a `reported` provenance case.** "The builder told us" is a real and common source the enum cannot name, so it
       lands on `estimated` beside things nobody has any figure for at all. See [docs/sources.md](docs/sources.md)
    5. reference photo: /home/stefanr/.config/JetBrains/PhpStorm2026.2/scratches/GMSS.jpeg
19. detailed geometry for the remaining cabinets ([docs/sources.md](docs/sources.md#3d-geometry-per-device))
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
20. daylight renders, insides of speakers come out a little bit too dark
21. finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done
    1. write the splayed sub arc scene. `arc` covers the schema side now — a mirrored Flexy arc is
       `arc` plus `roll_deg: 180`, which the arc's roll rule deliberately allows — but no scene uses it, and a sub arc
       is the case where the reported footprint reads worst: a bounding box around a fan includes floor that nothing
       stands on
    2. more render polish: per-device colour (e.g. flexy bracings green), and a truss/stage backdrop so a preview looks
       like a venue rather than a void
    3. port the existing 2D setup drawings in Drive (`…/setups/`, ~13 events as SVG) into scene files — they encode
       stack arrangements that already worked
22. more categories: the remaining lighting. **Truss, moving heads, the Gerüst and the racks are done** —
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
    3. also truss tower feet are three — noted against the mast shape above, since that is the item the base belongs to.
       AMBIGUOUS AND NOT ACTED ON: it could mean each stand has a three-leg base where the spec's comment describes a
       square 1.499 × 1.499 m outrigger spread, or it could mean we own three stands rather than the two the original
       note said. The first reading fits where it sits; the second would change `truss-tower-4m`'s quantity. One answer
       settles it
23. audio routing table, for the coverage work ->
    https://docs.google.com/spreadsheets/d/1lLv8RN6I70Efh1ktJXTcqyx2qMsr7obSXus2ypfaWUs/edit?gid=1412726604#gid=1412726604
24. `inventory:import` — the first import was done by hand because the source is several spreadsheets and CAD files
    rather than one list, and every number needed a provenance decision. Worth building when the gear list next grows;
    see [docs/inventory.md](docs/inventory.md)
25. asset previews are blank because they cannot be rendered in background mode. Either generate them in the GUI once,
    or find a way to render thumbnails headless
26. run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job that only runs when
    `blender/` or `specs/` changed
27. GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so
    this should mostly be packaging and metadata mapping
28. measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec currently describes a design or a
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
29. fly through renderings + combine with new project from existing audio routing table
30. endfire setup add other sub and tops
31. **`scene:stack` with no `--from` mixes two sound systems, and still refuses.** The default is "every speaker, subs
    before tops", which since the GMSS cabinets arrived means both systems' gear in one stack. The refusal has moved
    rather than gone: it is now a SKRAM landing on 8 % of itself in a `skram + mid-bass + skram` row, and a floating
    Flexy in the free-shape variant — the 1.200 m mid bass is nearly twice the width of the next widest sub and there is
    no row it fits in a single mixed stack. It refuses with a clear reason rather than writing a wrong rig, so this is
    degraded and not broken, and `--per-owner` or an explicit `--from` both work. The fix is for the default to mean
    "one system's gear" — an `--owner` narrowing option, or owner-awareness in `everySpeaker()`. Owner is not quite the
    right discriminator, since the repository deliberately supports borrowing gear between owners, so this needs a
    decision before it needs code
32. **`audio.drivers` cannot record a count without a size.** `size_in` is required on every entry, so a cabinet known
    to have two drivers of unknown size — `gmss-mid-bass`, from "USB 2x 700rms" — has to omit the whole
    `audio` block and put the count in its notes. Making `size_in` optional would let the schema hold what is actually
    known instead of forcing a choice between inventing a size and recording nothing
33. **stability is weighed per row but never for the whole rig.** `Stability::tips()` does compute a centre of mass — it
    sums `count × weight_kg` across a row's runs and refuses a row whose combined mass falls outside what carries it —
    so the "nothing weighs the rig" version of this item is out of date. What is still missing is the *stack* as one
    body: 2 200 kg of cabinets on a 1.34 m base is never compared against anything, and a tipping angle for the whole
    pile has no citable limit to compare against anyway, so it wants reporting rather than refusing
34. **`provenance.dimensions` cannot say "outer box sourced, internals estimated".** It is one field for the whole
    geometry, and the shapes built from parts break that assumption: `gmss-mac-2000-performance-ii` has its 408 × 490 ×
    743 mm box and its 39.5 kg from Martin's datasheet, while the split between base, yoke and head comes off product
    photographs. The spec says `datasheet` — correct for the bounding box everything downstream reads — and the
    qualification survives only as prose in the file and in docs/sources.md, where nothing can check it. The same
    applies to `geruest-krause-ah7`, whose footprint and weight are published and whose tube diameters are not. A
    per-block provenance, or a `provenance.parts` beside `dimensions`, would let the spec say what is actually true
35. **two amplifier facts are unresolved, and both are cheap to settle by looking at the rack.** `rack-amp-12u`'s weight
    is derived from published figures for every amp in it, so these are the only loose ends: (a) the fourth amp is
    either a Behringer EP4000 or a t.amp Proline 3000 and the owner is not sure which — the Proline is 3U and 37 kg
    against the EP4000's 2U and 16.6, which moves each rack from 69 kg to 79; (b) "gisen md60" matches no Gisen product.
    Their M60-series DSP amplifiers fit the description at 1HE and under 13 kg, and M60Q-DSP and M60.12 are both
    plausible. The name on each front panel answers both
36. **the amplifiers have no specs of their own.** They are invisible inside a closed rack, so their published figures
    live in `rack-amp-12u.yaml`'s header and docs/sources.md rather than in five specs that would add five boxes nobody
    can see to `detail-check`. Worth revisiting only if an amp ever has to be placed on its own
37. **`rack-power-12u` is the weakest spec in the repository.** Its case follows the amp racks and its 15 kg of contents
    is a guess at breakers, socket panels and cable, with no component list to add up. Opening the rack and listing what
    is in it would make it as solid as the amp racks. Related: nothing in this schema can record electrical load, and
    the rig's amplifiers are rated in the tens of kilowatts — the breaker layout is what decides whether everything
    switches on at once
38. **our 4 m crank stands cannot clear a full three-stack rig**, and the pyramid does not rescue it. Every speaker in
    three stacks reaches 4.563 m as a pyramid and 5.628 m free — both above the 4 m the stands extend to, so a truss on
    `truss-tower-4m` sits below the tops of the rig it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead.
    The stands are fine for our own 3.125 m rig and not for a combined one; worth knowing before hiring a stage
39. end-fire-lattice-three-quarter.png: add vetical gaps between the subs and add the two skram and achenbach and
    tecnare and two ways tops
