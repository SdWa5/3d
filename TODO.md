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
neighbouring stacks are spaced on nominal tier widths rather than on where the cabinets actually ended up. GEO-1 and GEO-3
are done, which cleared all 8 interpenetration refusals, all 6 spread-envelope refusals and the 12 by-type overlaps.
What is left no longer runs in a chain. **GEO-2 does not depend on GEO-9**, and the entry that said so is refuted in
GEO-2's own section: the tops row is wider than the widest wall the stage can legally carry, so no shape reaches it.
**GEO-4 still waits on GEO-2**, because sliding a sub row moves what the tops row stands on, and enabling it first made
things worse, measured as 6 "nothing under it" refusals becoming 12.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-2 | The tops row is **3.921 m whatever the stage is**, so it is wider than the widest legal wall and no shape can carry it. Splitting is the only geometric lever and cost more than it bought three times. **Needs a decision** | P2 | — | 22 refusals, all `all-*`, 12 of them `all-1` | decision, CVR-3 | decision |
| GEO-4 | Switch multi-stack row sliding on — the bound is written and measured, `clearance / 2 - gap` | P1 | 15m | the `LEFT OUT` mid-bass in `stacked-all-2-center`; `--per-owner` writing at all | GEO-2 | partial |
| GEO-5 | The **pyramid cap** reaches `statedMix` now; `reserveLifts` reserves its flanks before any tier exists, so it needs the cap at emission instead | P2 | 1h 15m | 9 of 13 pyramid stacks still step outward (the V shape) | — | partial |
| GEO-6 | The whole inventory cannot be **turned** at once — a rolled SKRAM is 19 mm taller than a rolled Flexy and the row above straddles the step | P2 | 2h | 3 of 10 turned siblings | — | partial |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |
| GEO-9 | **`tower` and `mixed` shapes** — a wall of one width, and a tower base with a tapering top. **The cheap version is measured and does not work**: a bound in `ceilingFor()` cannot make a wall flush, so this is a change to `packedRows()`'s objective | P3 | 8h | nothing measurable — it does **not** buy GEO-2, see there. A shape people build, which is worth having on its own | — | measured |

#### GEO-2 — the tops row and the plateau, and why splitting is the wrong lever

Where: `StackSolver::topRow()`; the rule already exists in `swallows()`.

The diagnosis is exact and unchanged. Both systems' eight tops come out **3.921 m in one row on a 2.420 m sub wall**, so
the outer turbo top at each end is over air entirely and two more sit at 0.20 and 0.29 bearing, under the third the rule
requires. Twelve tops-on-nothing, six nothing-under-at-all and five bearing refusals, every one of them an `all-*` rig.

**Splitting the tops across two rows has now been tried three times and it never nets positive.** Measured against a
baseline of 10 scenes written and 23 refusals across the three families:

| attempt | written | interpen | top-on-nothing | nothing-under | bearing |
|---|---|---|---|---|---|
| baseline | 10 | 0 | 12 | 6 | 5 |
| unbounded loop | 9 | 12 | 14 | 0 | 0 |
| capped at two rows | 9 | 2 | 16 | 4 | 4 |

The unbounded version cascaded: each row becomes the next one's support, so each budget is narrower and takes fewer
cabinets, and eight tops became seven ever-thinner rows — a 7-tier rig grew to 13 and gravity broke those rows into runs
overlapping by 203 mm. Capping at two rows fixes the cascade and still loses: it trades `nothing-under` and `bearing` for
`top-on-nothing` and costs a scene.

**AND THE SHAPE OF THE WALL IS NOT THE LEVER EITHER. That was the previous entry here and it is refuted by
arithmetic.** `topRow()` puts every top in one row unconditionally, so the row is **3.921 m wide for the `all`
inventory's 8 tops whatever the stage is** — measured identical at 3.80 m and at 5.00 m, because nothing about it
responds to the stage. The widest wall a 3.80 m stage can legally carry is 3.80 m. **The tops row is wider than the
widest possible support**, so no shape can hold it: `pyramid` leaves 751 mm per side over air and `free` leaves 1421 mm,
and a perfectly flush wall filling the whole stage would still leave 60 mm. Re-measuring after GEO-9 is therefore
pointless, and this item does not depend on GEO-9 at all.

**Which leaves exactly two levers, and picking between them is a decision rather than a measurement.**

* **Split the tops after all.** It is the only *geometric* option, and the table above is what it costs. The three
  attempts predate the width arithmetic above, so they were made while a cheaper fix still looked available; that is a
  reason to re-read them, not evidence that a fourth attempt would go differently.
* **Stop building the rig.** All 22 refusals are `all-*`, and 12 of the 22 are `all-1`, which is one stack holding both
  complete sound systems and 8 tops in one row. Nobody builds that. Every per-owner rig — `sdwa5-*` and `gmss-*` — is
  clean in this family. That makes it **CVR-3**, which already asks whether the default `--from` should mean one system
  rather than both, and it would retire half of this item without any geometry at all.

The remaining 10 refusals are `all-2` and `all-3`, where the tops are already spread over two and three stacks. Those are
a smaller and separate case, reported by the sweep against the *compiled* scene rather than by `StackChecks` ("a
gmss-turbo-top would stand at 4.668 m with nothing under it across x"), and they should be measured on their own once the
`all-1` question is settled.

#### GEO-9 — the two shapes that are missing

Where: `src/Scene/StackShape.php` and `StackSolver::packedRows()` / `packTo()`.

`pyramid` narrows going up and `free` lets the bearing rule decide, and neither expresses a wall of constant width. Worth
having because it is a shape crews build, and **not** worth having for GEO-2: that item is refuted independently of this
one, since its tops row is wider than any wall the stage permits. This is now a shape for its own sake, which is why it
dropped to P3.

* **`tower`** — every row about one width, so the top of the wall is as usable as its base.
* **`mixed`** — a tower base with a tapering top, which is the common real rig and the only shape that makes a wide
  bottom and a usable top face compatible.

**Adding the enum cases is free**, since `Stack::shapeFrom()` goes through `StackShape::tryFrom()` and lists
`StackShape::cases()` in its own error, so a new case parses and documents itself.

**A WIDTH BOUND CANNOT MAKE A WALL FLUSH, AND THAT WAS TRIED AND REVERTED.** The cheap version of `tower` is one branch
in `StackSolver::ceilingFor()`: bound a row by `min($stack->maxWidthM, $supportM)` where the other shapes get
`$supportM + 2 × OVERHANG_PER_SIDE × RolledBox::widthOf(...)`. It builds, it leaves `pyramid` and `free` regenerating
byte-identical, and it does not work, because **`ceilingFor()` is an upper bound and a narrow row is not a row that was
capped — it is a row whose device ran out of cabinets.** Lowering a ceiling cannot add cabinets to a row, so it can only
ever make a wall narrower.

Measured on the five GMSS types at 5 m, with `widestFirst` extended to `tower` so the floor row is not the two wall
basses:

| shape | rows, bottom up | sub height |
| --- | --- | --- |
| `pyramid` | 2.180 / 2.440 / 1.200 / 1.200 | 3.340 m |
| `tower`, weight order | 1.340 / 1.200 / 1.200 / 1.080 / 1.080 / 1.080 | 4.680 m |
| `tower`, width order | 3.280 / 1.340 / 1.200 / 1.200 | 3.340 m |

The best of those tapers by two metres over four rows. Across the whole sweep the branch wrote **0 scenes out of 66
`tower` candidates**: 36 missed the height band, 12 deduplicated against their `pyramid` or `free` sibling, 12 were
refused by the shipped-scene sweep and 6 interpenetrated. `SceneStackCommandTest` also caught
`zz-test-stack-tower-block.yaml` placing 21 of 23 cabinets, so the branch loses gear as well as buying nothing.

**What a flush wall actually needs is a fill, not a bound.** Every row has to be built from *several* device types
chosen to reach a target width, which is what `StackSolver::packedRows()` already does — and `packedRows` sizes its rows
through `ceilingFor()` too, so it inherits the same limit. The work is a width **target** carried into `packTo()` beside
its existing budget, and a pack that keeps taking types until a row reaches it rather than until the next one does not
fit. That is a change to the packer's objective, not a new enum case, and it is nowhere near the 4h estimated here.
Re-estimate before starting.

Watch the candidate count when this lands. Four shapes times three alignments times two mirror styles is 24 variants per
rig against today's 12, and `DEFAULT_MAX_SCENES` is 80.

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
| SYM-1 | An **odd mirrored row is lopsided** by one cabinet. **`MirrorStyle::Upright` already centres it**, so implementing this as worded collapses the two styles into one and deletes a sweep axis. **Needs a decision** | P2 | — | 8 odd rolled rows across 4 scenes; possibly a whole redundant axis removed | decision | decision |
| SYM-3 | Stereo/mono placement breadth: subs mono where possible and spread only as far as the tops need; tops as wide and as evenly spaced as possible; symmetry wins ties | P2 | 3h | broadest stereo image; the mono spread | decision, ALN-4 | decision |
| SYM-2 | Stack ordering cannot make the flanks *equal*, only place the tall ones | P3 | 1h | 3 of 13 multi-stack scenes are height-asymmetric | GEO-4 | partial |

#### SYM-1 — centre the odd cabinet

Where: `Tier::mirrored()`.

**READ THIS BEFORE WRITING ANY CODE: the fix is already in the repository, and implementing SYM-1 as worded would delete
a sweep axis.** `mirrored()` does not mirror *positions*, it mirrors **roll direction**, and it touches only segments
whose roll is a quarter turn. An odd row therefore cannot be a palindrome in roll, because a cabinet has to lie one way
or the other, unless one is left standing up. Leaving one standing up is exactly `MirrorStyle::Upright`, which is
implemented, is generated by default and produces `2 + 1 upright + 2` for a row of five.

So "centre the odd cabinet" in `MirrorStyle::Alternate` yields `2 + 1 upright + 2`, which **is** `Upright`. The two
styles already agree on every even row, so they would then agree everywhere a row is uniformly rolled, and
`MirrorStyle`, the `--mirror-style` option and half the sweep's candidates become redundant. The lopsidedness is not an
oversight either: the code says so, and alternating the extra cabinet by row index is what balances the *stack* when no
single row can be.

Two ways out, and picking one is a decision rather than a measurement:

* **SYM-1 is already done.** `--mirror-style=upright` *is* the centred variant. The work is then to stop calling
  `Alternate` a defect and to find out why no `upright` scene is ever written — of the 10 scenes the bare sweep writes,
  every one is `alternate`, so the upright siblings are all refused or deduplicated and the axis is costing candidates
  without ever producing a rig.
* **Delete `MirrorStyle`.** Keep centring only, drop the enum and the option, and halve the mirrored half of the sweep.

The row's "40 odd rows across all 11 mirrored stacks, in 11 of 19 scenes" is **wrong** and was corrected once already by
measurement: it is 8 odd *rolled* rows across 4 scenes. Re-measure it as part of whichever way out is chosen.

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
| CVR-3 | The default `--from` means both sound systems in one stack; make it mean one system — an `--owner` narrowing, or owner-awareness in `everySpeaker()` | P1 | 1h | **12 of GEO-2's 22 refusals**, since `all-1` cannot stand at any geometry | decision | decision |
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
sweep would be inventing a property to serve a layout. **Decide the discriminator before writing code.**

**This item got considerably more valuable, and the note that used to be here got it backwards.** It read "the one-stack
mixed rig now fails on the tops row and one top floating at 4.196 m, that is GEO-2, not a `--from` problem". The width
arithmetic in GEO-2 says the reverse: 8 tops in one row are 3.921 m, wider than the widest wall a 3.80 m stage can carry,
so `all-1` cannot be made to stand by any geometry. It is a `--from` problem, and narrowing the default retires **12 of
GEO-2's 22 refusals** without touching the solver.

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

## LOAD · transporters and packing

**The gear side of this is finished and the vehicle side is empty.** Two transporters exist, one Stefan Ripper's and one
Sepp Fronz's, and their make, model, internal load bay and legal payload are all unsourced — so every cabinet can be
weighed and measured out of `specs/` today, and there is nothing to pack it into.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| LOAD-1 | Where a **vehicle** belongs in the schema — a transporter is not a speaker, and a load bay is not a bounding box | P2 | 1h 30m | — | decision | decision |
| LOAD-2 | Specs for the **two transporters**, Stefan's and Sepp's — make, model, internal load bay and legal payload are all unsourced. Zulassungsbescheinigung for the payload, tape measure for the bay | P2 | phys | provenance for the only two objects a pack is ever checked against | LOAD-1 | open |
| LOAD-3 | **Pack** the whole inventory or a stated subset onto one transporter, or across both — this is bin packing, so a named heuristic rather than an implied optimal solver | P3 | 6h | a load plan, which nothing produces today | LOAD-1, LOAD-2 | open |
| LOAD-4 | **Report space and weight separately** — a pack can fit the bay and still be overloaded, and a payload overrun is a legal problem rather than an inconvenience | P3 | 2h | — | LOAD-3 | open |

#### LOAD-1 — a vehicle is not a speaker

Where: `src/Spec/Category.php`, `src/Spec/DeviceSpec.php`, `src/Spec/SpecValidator.php`, `docs/spec-format.md`.

Three questions, and none of them is settled:

1. **Does a transporter belong in `specs/` at all?** Every spec in there is a thing that gets built, placed in a scene
   and exported as a `.glb`. A van is none of that. It is the container the rest goes into, so it may want its own
   directory and its own loader rather than a sixth category beside speakers, truss, racks, stands and lighting.
2. **If it is a category, which one?** `Category::Other` accepts any subtype and is the documented escape hatch, so
   `other/vehicle` needs no schema change at all. A real `vehicle` case with `van`/`trailer` subtypes is the honest
   version and costs a validator pass and a docs pass.
3. **What is a load bay in schema terms?** `geometry.dimensions_m` is defined as the true outer bounding box, which for
   a van is the wrong number entirely — what a pack reads is the **inside**: length, width, height at the wheel arches
   and height at the door aperture, which is usually the binding one. That is a second dimension set on one device, and
   the schema has nothing like it. Payload is the same shape of problem: `physical.weight_kg` is what the object weighs,
   and a van also carries what it is *allowed* to weigh, which is a different field.

Also part of the call: `owner`. The vans are personal property, and `owner` already carries `sdwa5`, `sepp` and `gmss`,
so Sepp's is `sepp` and Stefan's is either `sdwa5` or a new personal value. CVR-3 is the same discriminator problem seen
from the rig side.

#### LOAD-2 — nothing about either vehicle is known

Where: `specs/` (a new directory or category, per LOAD-1), and a row each in [docs/sources.md](docs/sources.md).

**No dimension, payload or weight for either transporter may be invented, and that is not a style preference here.**
Every number in this repository points at a row in `sources.md`, and the GMSS reconstruction is the worked example of
what happens when it does not: a photograph got a cabinet's depth wrong by 205 mm and its weight by 139 kg, and the
whole apparatus had to be thrown away when the builder finally stated his figures. A guessed payload is worse than that,
because a wrong cabinet weight makes a bad render and a wrong payload makes an overloaded van.

What is needed, per vehicle:

1. **Make and model**, which pins the class and nothing else.
2. **The legal payload**, from the **Zulassungsbescheinigung**. Field F.2 is the permitted gross weight and G is the kerb
   weight, and the payload is the difference. That is the citable number, and it is stated on a document rather than
   derived from a brochure figure for a different trim level.
3. **The internal load bay**, with a tape measure. Length at the floor, width between the walls and width between the
   wheel arches, height under the roof and height through the door aperture.
4. Whether either vehicle has anything fixed in the bay that never comes out.

Until those exist, `provenance` for both is `estimated` at best, and a pack that reports "it fits" is reporting nothing.

#### LOAD-3 — packing, and what a heuristic can honestly claim

Where: reads `DeviceSpec::$dimensions` and `physical.weight_kg` straight out of `SpecLoader`; `CatalogRenderer::summary()`
already sums weight and volume across the library.

**The gear side needs no new data.** Every spec carries `geometry.dimensions_m` and a required `physical.weight_kg`, and
`bin/console catalog` already totals both — total weight, total volume and a per-owner weight and unit count. A packer
reuses that loader and those totals wholesale; what it adds is the vehicle, an ordering and a fit test.

**The catalog's volume total is not a packing figure.** It is the sum of bounding-box volumes times quantity, so it
ignores that boxes do not tessellate, that a load bay has a fixed shape, and that a horn mouth is not a brick. It is a
lower bound on the space needed and can never say a load fits. (`docs/catalog.md` had also gone stale against the GMSS
rename, still listing `gmss-turbo-sub` and `gmss-middle-sub`. It was regenerated in 0.72.4 and the totals moved by
61 kg. It is a generated file, so re-run `catalog --write` rather than trusting a figure quoted from it.)

**3D bin packing is NP-hard, so what gets built is a heuristic with its ordering written down**, not a solver that
claims an optimum. Heaviest and largest first into the deepest free space is the usual shape of it, and the ordering is
part of the output so a load plan can be argued with. Two transporters make it a bin-packing problem with two bins of
different sizes, which is the same heuristic run twice with a rule for which bin a device is offered to first.

The constraints a naive box packer misses are the ones that decide whether a plan is usable:

* **Heavy low.** A 220 kg wall bass or a 90 kg SKRAM goes on the floor. Nothing stacks on top of a cabinet it would
  crush, and no cabinet is stacked higher than two people can lift it.
* **What a cabinet can carry** is already modelled on the rig side, in `Stability` and the stack checks, and a load has
  the same rule with a different ceiling.
* **Irregular shapes.** A Tecnare top is a trapezoid, a Flexy is mostly folded horn, and the truss towers report the
  mast's footprint rather than their unfolded outriggers, which
  [docs/sources.md](docs/sources.md#the-towers-are-placeholders-and-look-it) calls the one number in those specs to be
  careful with. A bounding box overstates some of these and understates none.
* **Racks roll**, cabinets do not, and the two amp racks are already split by weight rather than by height at 69 kg
  each.

#### LOAD-4 — two constraints, two verdicts

Where: report shaped like `CatalogRenderer::renderMarkdown()`; `SceneReport` is the precedent for naming every refusal
rather than failing silently.

**Space and weight are independent, and a pack can pass one while failing the other.** The output therefore has to state
both outcomes separately, with the numbers behind each: cubic metres used against the bay, and kilogrammes against the
payload from the Zulassungsbescheinigung. Reporting one figure, or a single pass/fail, hides exactly the case that
matters — a load that fits the bay comfortably and is 300 kg over the axle.

**An overrun on payload is a legal problem.** It is a fine, a liability question after an accident and a refused
insurance claim, so it is reported as a refusal with the overrun in kilogrammes, never as a warning to be scrolled past.
A space overrun is an inconvenience by comparison, and it is fixed by a second run.

**How much the payload verdict can be trusted is bounded by SPEC-5.** No weight in the library is `measured` — the
catalog reports 0 of 17 — and several are estimates by arithmetic or by the builder's own hedging, including the mid
bass's 120 kg, which the cabinet's own volume argues against. So the report states the provenance of the weights it
summed alongside the total, the same way the catalog flags what still needs the hanging scale. A payload check against
994 kg of `estimated` GMSS cabinets is a planning aid, not a clearance.

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
