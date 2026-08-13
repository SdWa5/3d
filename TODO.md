# TODO

## The goal

As many *sensible* speaker configurations as possible, generated automatically by a single command in its default
settings, with priority on the configurations actually used in praxis as well as ones algorithmically derived.

Not only enforce the subwoofer ceiling strictly (optimum 2–3 m) but improve the existing logic to reach it: **do not
avoid generating a scene, ignore the ceiling, or use fewer speakers if there is any other possibility to solve it.**

Where that stands: bare `scene:stack` writes **11 scenes of 132 candidates**, every stack's sub/top transition inside
2–3 m, every refusal named. The 121 refusals are the work — grouped below by what actually causes them.

## How to read this

* **One table per group.** Groups are by shared root cause, so finishing one closes several rows. Rows are sorted by
  priority inside the group.
* **IDs are stable and never renumbered.** Plain numbers broke every cross-reference twice in one session; `GEO-2`
  keeps meaning `GEO-2` even when rows are added, reordered or deleted.
* **Prio** — `P1` blocks the goal above · `P2` a real defect or a wanted feature · `P3` when it's next touched.
* **Effort** — estimate for one focused pass *including* tests and docs, to 5 minutes. `phys` means physical work
  (tape measure, hanging scale, opening a rack), which no estimate here can shorten.
* **Buys** — the measured payoff, in refused sweep candidates or affected scenes. `—` means it buys nothing
  measurable and is wanted for its own sake.
* **Needs** — the IDs that must land first, or `decision` when a question has to be answered before any code.
* **State** — `open` · `partial` (some of it shipped) · `decision` (blocked on a call, not on work) · `known` (a fact
  worth keeping, not a task).
* Resolved rows are **deleted**, not ticked. Measured figures are re-measured when touched, never carried forward on
  trust — several were wrong for two releases.
* **GEO, SYM and ALN together are the highest priority** — geometry, symmetry and alignment are the same placement
  problem seen from three sides. While working inside any of the three: fix every bug hit immediately, and implement
  every related TODO it turns up immediately, rather than filing it for later.

## GEO · placement geometry

**One root cause across this group:** a row is positioned and spaced as if its cabinets were unrotated and centred, and
neighbouring stacks are spaced on nominal tier widths rather than on where the cabinets actually ended up. GEO-1 is
done, which cleared all 8 interpenetration refusals and all 6 spread-envelope refusals. What is left needs **GEO-2
first**: sliding a sub row moves what the tops row stands on, so enabling it before the tops row understands its
support's plateau makes things worse, measured as 6 "nothing under it" refusals becoming 12.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-2 | Tops row stands on its support's **plateau**, not its extent — split it across two rows when the plateau cannot carry it | P1 | 4h | 12 tops-on-nothing + 6 nothing-under-at-all + 5 bearing refusals | — | open |
| GEO-4 | Switch multi-stack row sliding on — the bound is written and measured, `clearance / 2 - gap` | P1 | 15m | the `LEFT OUT` mid-bass in `stacked-all-2-center`; `--per-owner` writing at all | GEO-2 | partial |
| GEO-3 | An aimed row is still spaced on flat widths outside the default sweep — **re-measure**, GEO-1 may have closed it | P2 | 45m | was 17.6 mm / 135.6 mm overlaps + 1 envelope refusal under `--split=by-type` | — | open |
| GEO-5 | The **pyramid cap** does not reach `reserveLifts`, `statedMix` or the mixed bottom row | P2 | 1h 30m | 7 of 9 pyramid stacks still step outward (the V shape) | — | partial |
| GEO-6 | The whole inventory cannot be **turned** at once — a rolled SKRAM is 19 mm taller than a rolled Flexy and the row above straddles the step | P2 | 2h | 3 of 10 turned siblings | — | partial |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |

#### GEO-2 — the tops row and the plateau

Where: `StackSolver::topRow()`, `stereoTopRow()`; the rule already exists in `StackSolver::swallows()`.

Not a too-wide row — the case traced is a 1.456 m tops row on a 1.900 m support — but a *stepped* support:
`wall-bass + skram + nuke` is 1.400, 0.914 and 0.590 m tall, so the outer tops drop to a lower face and overhang
nothing. `swallows()` encodes this for packed sub rows and has never guarded the tops row. Splitting the tops across two
rows where the plateau cannot carry them fixes it, and means correcting `topRow()`'s "not split across tiers" comment —
written when width was the only failure mode. Expect this one to grow: it changes what `topRow()` produces for every
scene, so `StackSolverTest` is the gate and any fill that moves wants reading rather than renumbering.

#### GEO-4 — resolved extents, and the row that may not move

Where: `Gravity::slidSeats()` (bounded by `Stack::$slideWithinM`), `Stack::spreadApart()`, `SceneCompiler`.

A row does not have to be centred on what carries it, and 0.70.0 acts on that where nothing stands beside the stack —
which is what recovered `stacked-gmss-1-center` at 2.84 m. The multi-stack half is blocked: stacks are spaced on their
widest tier and their envelopes deliberately overlap in x, so a slide there reaches into the neighbour — measured
unbounded, **180 mm of interpenetration across five `all-3` scenes**. Spacing from resolved extents turns the bound into
the real gap to the neighbour instead of "no movement at all". **This is the concrete instance of the goal's "do not use
fewer speakers":** `stacked-all-2-center` ships with `gmss-mid-bass: LEFT OUT, it cannot be carried in this stack`, for
exactly the bearing failure a slide fixes.

#### GEO-8 — the rig as one body

Where: `Stability::tips()`.

The "nothing weighs the rig" version of this item is out of date: `tips()` does compute a centre of mass — it sums
`count × weight_kg` across a row's runs and refuses a row whose combined mass falls outside what carries it. What is
missing is the **stack** as one body: 2 200 kg of cabinets on a 1.34 m base is never compared against anything. A
tipping angle for the whole pile has no citable limit to compare against either, so this wants reporting rather than
refusing.

## SYM · symmetry, stereo and mono

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SYM-1 | An **odd mirrored row is lopsided** by one cabinet — `intdiv(n, 2)` left, the rest right, so five reads 2 + 3 | P1 | 1h 30m | 40 odd rows across **all 11** mirrored stacks, in 11 of 19 scenes | — | open |
| SYM-3 | Stereo/mono placement breadth: subs mono where possible and spread only as far as the tops need; tops as wide and as evenly spaced as possible; symmetry wins ties | P2 | 3h | broadest stereo image; the mono spread | decision, ALN-4 | decision |
| SYM-2 | Stack ordering cannot make the flanks *equal*, only place the tall ones | P3 | 1h | 3 of 13 multi-stack scenes are height-asymmetric | GEO-4 | partial |

#### SYM-1 — centre the odd cabinet

Where: `Tier::mirrored()`; the fix already exists in `StackSolver::stereoTopRow()`, which centres the odd cabinet and is
what makes a stereo tops row a palindrome. Applying the same rule here moves cabinets in **every** mirrored rig in the
library, so it wants its own measured pass against the interpenetration and bearing sweep rather than being folded into
another release.

#### SYM-3 — the contradiction to settle first

The ask is "in a stereo scene the subs get spread out as wide as possible or necessary so that the tops can be set as
far apart as possible", and "in a mono scene the outermost tops as wide apart as possible but all tops spaced as evenly
as possible", with symmetry between and inside stacks optimised.

Two things block it, both needing a call rather than code:

1. **Spreading subs is currently forbidden by design.** ALN-4 states the rule — only the top tier may be spread,
   because spreading a load-bearing tier turns it into gaps and the tier above stands over air. Spreading sub *clusters*
   with the tier above following the gaps is a different mechanism from `align`, and a real change to the model.
2. **"Widest outermost" and "evenly spaced" compete** whenever the row does not exactly fill its envelope, which with
   mixed cabinet widths is most of the time. Which yields, and by how much?

#### SYM-2 — what ordering can and cannot do

The taller stacks go to the middle in mono and the ends in stereo, ordered by solved sub height. Both halves are
exercised now — 5 multi-stack stereo scenes ship. Symmetry is *improved* rather than delivered: equal flanks depend on
the split giving each stack similar contents, so `stacked-gmss-2-stereo` reads 2.74 | 2.07 and `stacked-all-2-center`
2.033 | 2.833. `--per-owner` can never be symmetric — three owners are three different systems — and currently writes
nothing at all: 6 variants refused by GEO-2's family and the rest by the sub height band.

## ALN · alignment features

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| ALN-1 | Align scenes / stacks / rows at their **front faces** instead of their centres | P2 | 1h 30m | — | — | open |
| ALN-4 | Only the top tier can be spread — per-tier `align` picks *which* alignment the top tier uses, never how many tiers spread | P2 | — | — | decision |
| ALN-2 | `align` on **nested** groups — scaling x would stretch the inner group's spacing with the outer one's; needs the level named. Same for `arc` and `line_array`, which own their spacing | P3 | 2h | — | — | known |
| ALN-3 | `stereo` splits into halves only — `floor(n/2)`; 2 + 2 out of six with two in the middle needs a `columns:` key | P3 | 1h | — | — | known |

## CVR · coverage of the height band

**121 of 132 candidates are refused, and the two height bounds account for 72 of them.** Not because the band is wrong
but because the model has no way to raise tops other than stacking subs under them.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| CVR-1 | **A top may stand on something that is not a cabinet** — riser, stand or fly point. A rig too small for a 2 m sub wall is a real rig, not an impossible one | P2 | 6h | 54 refusals — every `sepp` rig, `gmss-3`, `sdwa5-3` | — | open |
| CVR-3 | The default `--from` means both sound systems in one stack; make it mean one system — an `--owner` narrowing, or owner-awareness in `everySpeaker()` | P3 | 1h | the one-stack mixed rigs, once GEO-2 lands | decision, GEO-2 | decision |
| CVR-4 | Port the ~13 real event setups from Drive (`…/setups/`, 2D SVG) into scene files | P3 | 4h | "actually used in praxis", which nothing covers today | — | open |
| CVR-2 | Decide whether the sweep keeps offering `free` where the pyramid already solves — it misses the ceiling far more often, inherently | P3 | 15m | fewer named refusals, or more scenes — pinned at floor, scale has no P4 | decision | decision |

#### CVR-1 — tops that do not stand on the sub wall

18 of the 132 candidates cannot get under 3 m on any stage in the ladder; **54 cannot fill a 2 m wall out of the
cabinets they are given** — `sepp`'s eight cabinets cannot, however they are stacked. In reality you solve that with a
riser or a pair of stands, and neither is modelled: support in the solver is always another cabinet. This is the gap,
not the band. Related: SCN-1 wants the Tecnare tops flown from truss, which is the same missing concept from the other
end, and INFO-1 is what the stands can actually reach.

#### CVR-3 — `owner` is standing in for "system"

`owner` is not quite the right discriminator: the repository deliberately supports borrowing gear between owners, so a
rig can legitimately mix them. It separates the two systems in practice, and inventing a `system:` field to serve a
sweep would be inventing a property to serve a layout. **Decide the discriminator before writing code.** Note the
refusal this item used to cite has moved: the one-stack mixed rig now fails on the tops row (`2× turbo-top + 1× 2-way +
3× tecnare`) and one top floating at 4.196 m — that is GEO-2, not a `--from` problem.

## SCN · scenes and renders

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SCN-1 | `full-rig-truss-three-quarter.png`: add the two SKRAMs; Gerüste replace the truss stands (remove those), turned 90° with fronts aligned to the system front; fly the Tecnare tops wide apart on the truss; 18Sound 2-ways near field beside them | P2 | 1h 15m | — | CVR-1 (flying) | open |
| SCN-2 | `everything-three-quarter.png`: remove unused truss stands, turn Gerüste 90°, align their fronts with the system front | P2 | 30m | — | — | open |
| SCN-8 | `end-fire-lattice-three-quarter.png`: vertical gaps between the subs; add the two SKRAMs, Achenbach, Tecnare and 2-way tops | P2 | 45m | — | — | open |
| SCN-3 | `detail-check-turned-three-quarter.png`: add missing stuff | P3 | 20m | — | — | open |
| SCN-7 | End-fire setup: add the other sub and the tops | P3 | 30m | — | — | open |
| SCN-4 | Daylight renders: the insides of speakers come out a little too dark | P3 | 30m | — | — | open |
| SCN-5 | Finish the scene work — [docs/scenes.md](docs/scenes.md); `scene:build` itself is done | P3 | 2h | — | — | partial |
| SCN-6 | Fly-through renders, combined with a new project from the audio routing table | P3 | 3h | — | — | open |

#### SCN-5 — what is left of the scene work

1. Write the **splayed sub arc** scene. `arc` covers the schema side — a mirrored Flexy arc is `arc` plus
   `roll_deg: 180`, which the arc's roll rule deliberately allows — but no scene uses it, and a sub arc is where the
   reported footprint reads worst: a bounding box around a fan includes floor nothing stands on.
2. Render polish: per-device colour (e.g. Flexy bracings green), and a truss/stage backdrop so a preview looks like a
   venue rather than a void.
3. Porting the 2D setup drawings is CVR-4 — it is coverage, not polish.

#### SCN-6 — the routing table

<https://docs.google.com/spreadsheets/d/1lLv8RN6I70Efh1ktJXTcqyx2qMsr7obSXus2ypfaWUs/edit?gid=1412726604#gid=1412726604>

## SPEC · specs, provenance and measurement

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SPEC-1 | Finish GMSS — five specs exist from the builder's own figures; measuring them is what is left | P2 | phys | provenance for 14 cabinets, 994 kg | — | partial |
| SPEC-5 | Measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec describes a design or a datasheet, not our build | P2 | phys | — | — | partial |
| SPEC-6 | `audio.drivers` cannot record a count without a size — `size_in` is required, so "2× unknown" has to omit the whole `audio` block | P2 | 45m | `gmss-mid-bass` keeps what is known | — | open |
| SPEC-8 | Two amplifier facts, both settled by reading the front panels: the fourth amp (EP4000 2U/16.6 kg vs Proline 3000 3U/37 kg — 69 kg vs 79 per rack), and "gisen md60", which matches no product | P2 | phys | rack weights | — | open |
| SPEC-2 | Detailed geometry for the remaining cabinets ([docs/sources.md](docs/sources.md#3d-geometry-per-device)) | P3 | 3h | — | — | partial |
| SPEC-7 | `provenance.dimensions` cannot say "outer box sourced, internals estimated" — one field for the whole geometry, which the part-built shapes break | P3 | 1h 15m | — | — | open |
| SPEC-3 | The remaining lighting, plus a **telescoping mast** shape for the towers | P3 | 2h 30m | — | — | partial |
| SPEC-10 | `rack-power-12u` is the weakest spec in the repo — 15 kg of contents is a guess with no component list | P3 | phys | — | — | open |
| SPEC-9 | The amplifiers have no specs of their own — invisible inside a closed rack, so their figures live in the rack's header | P3 | — | — | — | known |

#### SPEC-1 — finishing GMSS

Reference photo: `/home/stefanr/.config/JetBrains/PhpStorm2026.2/scratches/GMSS.jpeg`

1. **Measure the five cabinets.** They stay `provenance: estimated` deliberately — the builder's own statements are not
   a datasheet, not plans in hand, and not us taping it. Two he hedged himself ("maybe 220kg" wall bass, "~120kg" mid
   bass). A tape measure and a hanging scale settle the lot.
2. **The mid bass's 120 kg disagrees with arithmetic.** An 18 mm skin over 3.24 m² is about 40 kg, plus horn and two
   drivers about 80 — 120 kg would make it the densest cabinet in either system at 333 kg/m³. Recorded as stated, with
   the disagreement noted.
3. Resolve what **"USB"** stands for in "USB 2x 700rms mid bass", and the two drivers' size. The cabinet is identified —
   the 1.200 × 0.500 m horn lying across the wall basses — but the acronym and the drivers are not.
4. **A `reported` provenance case.** "The builder told us" is a real and common source the enum cannot name, so it lands
   on `estimated` beside things nobody has any figure for at all. See [docs/sources.md](docs/sources.md).

#### SPEC-5 — the measurement queue, highest value first

1. **The Tecnare baffle.** Its whole `audio.layout` is estimated: three horn mouths, two throats, two centre heights and
   both flare laws. One tape measure across one mouth promotes it to `measured` — the single highest-value measurement
   left in the repo.
2. **The two estimated weights**, by hanging scale: `eighteensound-2way-15` (41 kg) and `achenbach-18` (50 kg). Neither
   exists in any source — the whole Shared Drive was searched, and Eighteen Sound publishes no finished weight for a DIY
   kit.
3. **The self-built Tecnare** against the two factory ones. All three share one spec at the factory's 68 kg, and nothing
   has confirmed the copy matches.
4. **The Tecnare's three fly points.** `rigging.points` holds the only rigging positions in the repo and all three are
   derived from the nominal box — two on the top face at x ±0.185, y −0.100, and a pull-back at the centre of the rear
   face 0.120 m up. They exist so `flyable: true` validates; nothing has confirmed where the real track sits. The schema
   has no provenance field for rigging, so the estimate lives in a comment — worth adding one if more flyable gear
   arrives.
5. **The SKRAM mesh's orientation.** Its dimensions verify, but which face carries the mouth has not been checked
   against the real cabinet. `rotate_deg: [90, 0, 0]` puts the open chambers upwards; `[90, 0, 180]` and `[-90, 0, 0]`
   are the other candidates.
6. The Flexy and SKRAM have no `audio.layout` — their drivers sit deep in a folded horn path. Only matters if a render
   ever looks into a mouth from close up.

#### SPEC-2 — geometry still missing

1. **Tecnare top**: add the HF horn's mounting braces, vertical and horizontal (probably not worth a general feature);
   make each side of the connected horns one flat piece instead of three — currently more complex than necessary.
2. Find our **custom Flexy** 3D model with the actual W-like metal braces, in Drive or locally.
3. **2-way top**: find or model the actual horn from available data, or at least close the gap between horn and
   cabinet. Ports are not modelled either — the 18Sound's two Ø100 mm holes come from its CAD, nothing sits behind them,
   and a generated cabinet has no way to declare a port at all.
4. **Handle recesses** are a plain rectangular cut; a rounded dish would read better. Wants a general handle model.

#### SPEC-3 — lighting and the mast

Truss, moving heads, the Gerüst and the racks are **done** — `shape: truss`, `moving-head` and `scaffold` all build from
the shared tube primitive in `blender/lib/tubes.py`, and `specs/` has truss/, lighting/, stands/ and racks/ beside
speakers/. A rack needed no new geometry at all: a case is a box.

1. **Lighting**: 2× 600 W RGB LED strobe, 1 mini moving head, 1 mini laser. No brands stated, so no dimensions — the
   weakest-sourced group left. `shape: moving-head` already exists for the mini head.
2. **A telescoping mast shape.** `truss-tower-4m` and `gmss-tower-5m` are `shape: box` — a 0.203 m column, the folded
   base size. A crank stand is a nested mast on folding outriggers and neither is drawn. The outriggers matter most:
   unfolded they spread to 1.499 × 1.499 m, which is what must be kept clear at the feet, where the scene shows a column
   a fifth of that. A `mast` shape taking a section count and the folded/unfolded base fixes both.
3. **Truss tower feet are three** — noted against the mast shape, since that is where the base belongs. **Ambiguous and
   not acted on:** it could mean each stand has a three-leg base, where the spec's comment describes a square
   1.499 × 1.499 m outrigger spread, or that we own three stands rather than two. The first reading fits where it sits;
   the second changes `truss-tower-4m`'s quantity. One answer settles it.

## TOOL · tooling and CI

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| TOOL-3 | Run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job gated on `blender/` or `specs/` changing | P2 | 1h 30m | — | — | open |
| TOOL-2 | Asset previews are blank because they cannot render in background mode — generate them in the GUI once, or find a headless way | P3 | 1h | — | — | open |
| TOOL-1 | `inventory:import` — the first import was by hand because the source is several spreadsheets and CAD files and every number needed a provenance decision. Worth building when the gear list next grows; see [docs/inventory.md](docs/inventory.md) | P3 | 3h | — | — | open |
| TOOL-4 | GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so mostly packaging and metadata mapping | P3 | 3h | — | — | open |

## INFO · facts worth keeping

| ID | Item | State |
|----|------|-------|
| INFO-1 | **Our 4 m crank stands cannot clear a combined rig.** Every speaker in three stacks reaches 4.563 m as a pyramid and 5.628 m free, both above the 4 m the stands extend to, so a truss on `truss-tower-4m` sits below the tops it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead. Fine for our own 3.125 m rig, not for a combined one — worth knowing before hiring a stage. No three-stack rig is generated at present; the band refuses them all | known |
