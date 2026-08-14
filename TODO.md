# TODO

## The goal

As many *sensible* speaker configurations as possible, generated automatically by a single command in its default
settings, with priority on the configurations actually used in praxis as well as ones algorithmically derived.

Not only enforce the subwoofer ceiling strictly (optimum 2–3 m) but improve the existing logic to reach it: **do not
avoid generating a scene, ignore the ceiling, or use fewer speakers if there is any other possibility to solve it.**

Where that stands: bare `scene:stack` writes **61 scenes of 426 candidates**, every stack's sub/top transition inside
2–3 m, every refusal named. The 365 refusals are the work — grouped below by what actually causes them.

**The orientation axis landed in 0.77.0 and it was worth more than every other axis put together: 11 scenes became 61.**
Three-stack rigs are generated for the first time, and `stacked-all-2-turned-centred-stereo` stands **39 cabinets** where
the upright rig of the same gear stands 38.

**The target is stated once, in SWP-1**, as a six-axis cross product. Two of its values are still missing — owner
combinations and a `V` shape — plus the `impossible` half of the last axis.

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
* **Runtime is not a constraint on this project.** Stated by the user outright. A sweep that takes ten minutes and a
  test suite that takes longer are both acceptable if they produce more correct scenes, so no estimate, no design and no
  prioritisation here may trade coverage away for speed. Measure it, record it, and do not treat it as a blocker.
* **GEO, SYM and ALN together are the highest priority** — geometry, symmetry and alignment are the same placement
  problem seen from three sides. While working inside any of the three: fix every bug hit immediately, and implement
  every related TODO it turns up immediately, rather than filing it for later.

## GEO · placement geometry

**One root cause across this group:** a row is positioned and spaced as if its cabinets were unrotated and centred, and
neighbouring stacks are spaced on nominal tier widths rather than on where the cabinets actually ended up. GEO-1 and GEO-3
are done, which cleared all 8 interpenetration refusals, all 6 spread-envelope refusals and the 12 by-type overlaps.
**GEO-2 is done as well**, and it was neither of the things this section spent three attempts on: two separate movers
shifted a row after gravity had seated it and neither re-asked what it now stood on. That took floating refusals from 6
to 1 and the sweep from 10 scenes to 11.

**GEO-6 and GEO-10 are done, as the orientation axis in 0.77.0** — see SWP-1's step 2. The decision GEO-6 was blocked on
was settled by the owner of the gear: any sub may be laid on its side and **no top ever is**, because a horn throws its
pattern in one orientation and rolling the cabinet rolls the pattern with it. `mixed` then fell out of the specs rather
than needing a rule invented for it, since a sub is worth rolling exactly where rolling makes it wider and shorter.

What is left does not run in a chain. **GEO-4 no longer waits on GEO-2** — it was measured again after GEO-2 closed and
the trade did not move — and **GEO-9 buys GEO-2 nothing**, since the tops row is the same width at every stage on the
ladder. Both entries record what was measured rather than what was expected.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-2 | **Done.** Two movers shifted a row after gravity had seated it and neither re-asked what it stood on. Fills over-pushed on an inflated span (fixed with `align.clear_of`) and the stereo spread never reseated (fixed with `Gravity::reseat()`) | — | done | floating refusals **6 → 1**, scenes **10 → 11**, no existing scene changed. The one left is `all-1`, which cannot stand at any width | — | done |
| GEO-4 | Multi-stack row sliding. **Measured three times and still net negative** (10 scenes against 11). The lookahead bound is built and correct and does not help, because gravity decides support before the compiler decides final x — the same split GEO-2 was. Needs the two-pass compile | P2 | 6h | 2 `LEFT OUT` cabinets, and `--per-owner` writing at all | — | measured |
| GEO-5 | The pyramid cap reaches `statedMix` and lifts now. Its premise is false — `gmss-mid-bass` is **1.200 m** against 0.45–0.66 for the other nine — and **both pure-width replacements measured worse** (11 scenes → 7 and 8, and both introduce interpenetration). Waits on the fill/gravity reconciliation | P3 | 6h | 2 V rows, and only once the cascade is solvable | GEO-4 | measured |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |
| GEO-9 | **`tower` and `mixed` shapes** — a wall of one width, and a tower base with a tapering top. **The cheap version is measured and does not work**: a bound in `ceilingFor()` cannot make a wall flush, so this is a change to `packedRows()`'s objective | P3 | 8h | nothing measurable — it does **not** buy GEO-2, see there. A shape people build, which is worth having on its own | — | measured |

#### GEO-5 — the pyramid cap, and the cabinet that breaks its premise

Where: `StackSolver::perRowCap()`, and `liftPairs()` for the half that is now done.

**Done: a lift can no longer step outward.** Every other row-building path asks `perRowCap()` how many cabinets the row
below holds, and a lift could not, because it reserves its flanks before a single tier exists. Enforcing it at emission
is not possible either — by then the source's own rows are built, so handing surplus cabinets back would strand them.
`liftPairs()` therefore *predicts* the cap the same way it already predicts the support's width, through the new
`lastRowCount()`. **It is inert on today's inventory** (11 scenes, no scene file changed, 5 overhang warnings before and
after), so it is a guard against a shape that can occur rather than a fix for one that does.

**What is left is that the cap is a count, and its premise is no longer true.** `perRowCap()` explains at length why it
counts cabinets instead of comparing widths, and the argument ends: *"Width then takes care of itself, because the
cabinets are all 0.45–0.66 m wide and a row of `n` is about `n` cabinets across whatever they are."* That was true when it
was written. It is not true now:

| width | cabinets |
| --- | --- |
| 0.450 – 0.660 m | nine of the ten |
| **1.200 m** | `gmss-mid-bass` |

At nearly double the widest of the others, one mid bass in a row breaks the equivalence between "no more cabinets" and
"no wider", and the V comes straight back. Two of the five remaining overhang warnings are exactly this — the
`2× gmss-nuke + 1× gmss-mid-bass` row at 2.420 m on a 1.890 m row, and `1× gmss-iq-sub + 1× gmss-mid-bass` at 1.750 m on
1.080 m. Both are *legal*, since the bearing rule allows ⅔ of a 1.200 m cabinet per side, and both read as a V.

The other three are the **tops row**, which `topRow()` deliberately never caps because nothing stands on it, so those
belong to GEO-2's family rather than here.

**BOTH PURE-WIDTH RULES WERE THEN BUILT AND MEASURED, AND BOTH ARE WORSE THAN THE BROKEN COUNT RULE.** `perRowCap()`
was deleted outright and the pyramid expressed in `ceilingFor()` as a width, twice, with these results on the
66-candidate sweep:

| rule | scenes | sub-row V's | interpenetration |
| --- | --- | --- | --- |
| **count (what ships)** | **11** | 2, both the mid bass | **0** |
| width, bounded by the row below | 7 | 0 | 2 |
| width, bounded by the base | 8 | 1 | 3 |

A tolerance is **not** the lever, which was the previous entry's guess. Tried at 10 mm — the checker's own
`OVERHANG_TOLERANCE_M`, "what the rubber feet and the working gaps absorb" — and at 30 mm, and both gave 7 scenes,
identical to no tolerance at all. The losses are not a few millimetres of shoulder.

**Why both fail is the same reason, and it is structural rather than a tuning problem.** A width bound makes rows
narrower, narrower rows make more of them, and a wall of many thin rows is a *staircase*. `Gravity` then splits each row
into runs at the different heights that staircase presents, and those runs end up inside each other — measured at
**127 mm** on `stacked-all-2-center` and 28 mm on `stacked-all-2-stereo`. That is the same cascade three attempts at
splitting the tops row hit at 203 mm. Bounding by the base instead of the row below cannot cascade and still hits it,
because the *inventory* forces thin rows regardless of what the bound permits.

So the honest state is: **the pyramid's count rule is justified by a premise that is false, and is still the best
arrangement measured.** The two mid-bass rows are legal under the bearing rule, stand up, and read as a V.

**What a width-based pyramid actually waits on** is the same reconciliation GEO-2 and GEO-4 turned out to need, and this
is the third instance of it. The fill decides row widths, `Gravity` decides where cabinets land, the compiler decides
final x, and no stage sees the next one's answer — so the fill cannot know that the wall it is narrowing is one gravity
will break into overlapping runs. Until the fill can ask that question, any width rule is choosing between a V and an
overlap blind.

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
measurement.** `topRow()` puts every top in one row unconditionally, so the row is **3.921 m wide for the `all`
inventory's 8 tops at every stage width on the ladder from 2.00 m to 6.00 m**. Dead flat, because nothing about it
responds to the stage. Re-measuring after GEO-9 is therefore pointless, and this item does not depend on GEO-9 at all.

**Note there is no fixed stage limit, and an earlier version of this entry wrongly claimed one.** It argued that the
tops row is wider than any wall a 3.80 m stage can carry. `DEFAULT_MAX_WIDTH_M` is 3.70 m rather than 3.80 m, and the
sweep does not hold a rig there anyway: `SceneStackCommand::WIDTH_LADDER_M` walks `2.00 … 6.00 m` whenever a rig misses
the sub height band, which is what the "tried stages 2–6 m" in the refusals means. The conclusion survives for a
different and simpler reason, measured across the whole ladder:

| stage | tops row | top sub row | overhang per side | sub height |
| --- | --- | --- | --- | --- |
| 2.00 m | 3.921 | 1.200 | 1361 mm | 8.676 m |
| 3.20 m | 3.921 | 2.420 | 751 mm | 6.399 m |
| 3.70 m | 3.921 | 2.420 | 751 mm | 4.966 m |
| 5.20 m | 3.921 | 2.420 | 751 mm | 4.366 m |
| 6.00 m | 3.921 | 3.050 | 436 mm | 3.840 m |

**`all-1` cannot reach the 2–3 m sub height band at any width on the ladder.** Widening the stage does shorten the wall,
from 8.676 m down to 3.840 m, and 3.840 m is the best case and still 840 mm over the ceiling. So the widths that would
give the tops a usable support are exactly the widths where the wall is too tall to be allowed. The rig fails on two
independent counts and neither is geometry: **one stack is being asked to hold 41 cabinets, two complete sound
systems.**

**AND 12 OF THE 22 REFUSALS ARE NOT DEFECTS AT ALL.** `all-1` puts 41 cabinets in one stack and cannot reach the 2–3 m
band at any width on the ladder, as the table above measures. A generator that declines an impossible rig is a generator
working correctly — that is the whole promise `scene:stack` makes, that "a generator which emits a scene the compiler
rejects is worse than no generator". So those 12 want no geometry and no code. At most they want the candidate not to be
offered, which is cosmetic.

**That leaves 10 real ones, the `all-2` and `all-3` refusals**, where the tops are already spread over two and three
stacks and a rig that plausibly should work still does not. That is the whole of GEO-2 now.

**DIAGNOSED. The cause is that a near-field fill is moved sideways after gravity has decided how high it sits, and it
keeps the old height.** Neither the checker nor the tops row's width is at fault, and `Gravity` is not wrong either.

Reproduce with `--stacks=2 --shape=free --align=center` on the whole inventory, which refuses with `a gmss-turbo-top
would stand at 4.668 m with nothing under it across x`. In stack `main-2`:

| | position / span | top face |
| --- | --- | --- |
| `5a` achenbach | box 0.026 … 0.626 | **4.440** |
| `5b` 3× iq-sub | box 0.646 … 2.276 | **4.668** |
| `6a` turbo-top | x = 0.2589, bottom z = **4.668**, box −0.108 … 0.504 | — |

`Gravity` seats `6a` at x 0.7732 where it rests on `5b` with **78 % bearing**, cantilevering 78 mm over the step down to
`5a`. That is sound. The compiled scene then puts it at x 0.2589, which is **514 mm to the left**, over `5a` alone — and
it still carries `5b`'s 4.668 m. So it hangs 228 mm in the air and `floating()` is right to refuse it.

**Which cabinets move says exactly why.** Measured against their gravity seats:

| run | device | seat x | actual x | moved | role |
| --- | --- | --- | --- | --- | --- |
| `6a` | turbo-top | 0.7732 | 0.2589 | **−514 mm** | fill |
| `6b` | 2-way | 1.2510 | 1.1049 | −146 mm | fill |
| `6c` | 2× tecnare | 2.0138 | 2.0138 | **0** | **long throw** |
| `6d` | turbo-top | 2.7688 | 3.0926 | **+324 mm** | fill |

The long throw does not move and every fill does. `nearFieldFills()` calls every top narrower than the widest a fill, and
a fill is placed with `align.outside` against the long throw, which solves its x to clear that cabinet's **aimed**
footprint — and the yaws here are large, up to **−53.6°**. `Stack::spreadApart()` is *not* involved, since it only fires
for `stereo` and this is `center`.

**Two traps cleared on the way, both worth keeping.** The `z` gate is innocent: `CONTACT_TOLERANCE_M` is 0.001 m and the
measured `dz` was exactly `0.0000`. And comparing *box* centres instead of positions invents displacements that are not
there, because a yawed trapezoid's box is inflated asymmetrically — the same two turbo-tops show boxes 0.6125 m and
0.8263 m wide on a 0.45 m cabinet. Compare `liftedPosition()`, never `worldBox()`.

So the defect was that **x comes from an alignment solve and z comes from a gravity seat, and nothing reconciled them.**

**FIXED, IN TWO STEPS, AND THE FAMILY IS DOWN TO ONE.** There were two movers, not one, and each needed its own answer.

| | mover | fix | floating refusals |
| --- | --- | --- | --- |
| baseline | — | — | 6 |
| 0.74.0 | `align.outside` on a fill, over-pushing on an inflated span | `align.clear_of`, measured on the shells | 3 |
| 0.74.1 | `Stack::spreadApart()` on a stereo tops row, never re-asking | `Gravity::reseat()` after the spread | **1** |

Scenes written went **10 → 11**, the new one being `stacked-all-2-stereo`, and no existing scene changed. The one
remaining refusal is `stacked-all-1-stereo`, which is the rig that cannot stand at any width on the ladder, so it is a
correct refusal rather than a defect.

**The general lesson is written into `Gravity::reseat()`'s docblock and is worth repeating here.** `Gravity::resolve()`
never had this bug, because its own two repairs hand back *seats* and go through the fill again, which re-asks what each
run stands on. Any future code that moves a finished run must call `reseat()` afterwards, and the reason it is easy to
forget is that nothing downstream notices: the compiler reads the run's stated height, and the tier checks read bearings
computed before the move.

Below is kept for the `all-1` case only, in case the cosmetic half is ever wanted.

**Two levers, and picking between them is a decision rather than a measurement.**

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

Watch the candidate count when this lands, and the fuse with it. A rig is now 2 shapes × 7 orientation/mirror pairs ×
3 alignments = **42 variants**, and four shapes make it 84. The sweep writes 61 scenes against
`DEFAULT_MAX_SCENES = 80`, so doubling the shapes will hit the fuse — which refuses rather than truncating, so it fails
outright until the limit is raised deliberately.

#### GEO-4 — resolved extents, and the row that may not move

Where: `Gravity::slidSeats()` (bounded by `Stack::$slideSlackM`), `Stack::spreadApart()`, `SceneCompiler`.

A row does not have to be centred on what carries it, and 0.70.0 acts on that where nothing stands beside the stack —
which is what recovered `stacked-gmss-1-center` at 2.84 m. **This is the concrete instance of the goal's "do not use
fewer speakers":** `stacked-all-2-center` ships with `gmss-mid-bass: LEFT OUT, it cannot be carried in this stack`, for
exactly the bearing failure a slide fixes.

**The bound is written, it is safe, and it is not the problem. Measured twice.** `max(0.0, clearance / 2 - gap)` follows
from `StackSceneWriter::centres` leaving exactly `--clearance` between two envelopes, and with it no interpenetration
appears anywhere — the unbounded version's **180 mm across five `all-3` scenes** is gone. The second measurement was
taken deliberately after GEO-2 closed, on the theory that a tops row which could no longer be left hanging would change
the answer:

| | scenes | `LEFT OUT` | nothing-under-at-all |
| --- | --- | --- | --- |
| off (shipped) | **11** | 2 | 3 |
| on, bounded | 10 | **0** | 6 |

It gains `stacked-gmss-2-center` and clears both left-out cabinets, and it loses **both** `stacked-all-2-center` and
`stacked-all-2-stereo`. Net one scene worse.

**So the diagnosis changes twice, and both earlier versions here were wrong.** The first said the tops row had to learn
to stand on its support's plateau; `Gravity::reseat()` settled that and the trade did not move. The second said the
slide needed a bound that also kept the row under what stands on it. **That was built and it did not move the trade
either**, and why not is the useful part.

A one-tier lookahead was added to `Gravity::slidSeats()` and to the repair-acceptance test in `resolve()`, so a repair is
now scored on the row *and* on the tier it carries. Two things came out of it:

* **`worstBearing()` could not see a floating cabinet at all.** `landsOn()` answers `bearing => 1.0` for a run with no
  support, which is right for the bottom tier on the floor and exactly wrong above it. So the first lookahead scored an
  arrangement that abandoned cabinets to the air as *perfectly carried*. `Gravity::carriedBearing()` now reads `on`
  rather than the bearing, and `GravityTest` pins the trap.
* **Even with that fixed, the trade is unchanged: 10 scenes against 11.** Because the lookahead models the tier above as
  its **nominal seats**, and the real tops row is moved downstream by `throwFirst()`, the `clear_of` fill solves and
  `spreadApart()` — all in the compiler, after gravity has finished. A slide that is safe against nominal tops starves
  the tops that actually get built.

**That is the same architectural split GEO-2 turned out to be**, and it is the real blocker: gravity decides support
before the compiler decides final x. Closing it needs the compiler in the loop — the two-pass compile that was costed at
ca. 6h and deferred — rather than anything further inside `Gravity`. The lookahead is kept because it is correct and
inert (11 scenes, no scene file changed, 680 tests green), and it is a prerequisite rather than a fix.

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
| SYM-1 | An **odd mirrored row is lopsided** by one cabinet. **Already implemented as `MirrorStyle::Upright`**, and the axis is no longer swept when nothing is rolled, since the mirror is then a no-op | — | done | 132 candidates → 66, suite 53 s → 28 s, `scenes/generated` byte-identical | — | done |
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

**RESOLVED. `--mirror-style=upright` *is* the centred variant, so SYM-1 was already implemented**, and the follow-up
question — why no `upright` scene is ever written — is answered and fixed.

`mirrored()` acts only on segments lying on a quarter turn, and **the default sweep names no `--roll-mirror` device**,
which the written scenes confirm with zero `roll_mirror` keys. So the mirror was a no-op for every default candidate and
`upright` came out byte-identical to `alternate`. The measurement: **66 `upright` candidates, 0 written**, 18 of them
recognised as duplicates by `deduplicate()` and the other 48 refused earlier on the height band or on support, in each
case identically to their `alternate` twin.

`deduplicate()` catching them afterwards was not good enough, because a candidate is a full solve plus a compile plus an
interpenetration sweep. `readMirrorStyles()` now sweeps the axis only when something is rolled, which took the default
run from **132 candidates to 66** with `scenes/generated` byte-identical, and the test suite from 53 s to 28 s. A stated
`--mirror-style` is still honoured whatever is rolled.

The row's "40 odd rows across all 11 mirrored stacks, in 11 of 19 scenes" was **wrong** and is corrected: 8 odd *rolled*
rows across 4 scenes, all of them in the `-turned-` scenes, which come from a separate invocation that does pass
`--roll-mirror`.

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

## SWP · the sweep's axes

**What the autogeneration should produce, stated as one cross product.** Today's sweep is **426 candidates writing 61
scenes**; the target below is **2646 candidates**, and the work left is that two of its values and one whole half are not
implemented.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SWP-1 | **The full sweep cross product** — the target set of autogenerated rigs, stated once. Six axes, order in the section below. Steps 1 to 3 are **done**: `column`, the orientation/mirror fold and the fuse. Owner *combinations*, a `V` shape and the `impossible` half are not | P1 | 8h | 2646 candidates against today's 426 | CVR-3, CVR-5 | partial |

#### SWP-1 — the cross product

```
(1 / 2 / 3 stacks)
  x (every non-empty combination of the owners: sdwa5, gmss, sepp)
  x (center, block, stereo)
  x (pyramid, free, V)
  x (7 orientation/mirror pairs)
  x (possible, impossible)            <- impossible = emitted anyway, offenders painted red
```

**Five axes, not six, and folding the last two is a modelling fix rather than a tidy-up.** Orientation says *which*
cabinets lie down; mirror style says what a rolled row does with the odd cabinet it cannot split in half. Different
questions — a `turned` rig has three genuinely different forms — but the mirror style is **vacuous** when nothing is
rolled, because {@see Tier::mirrored} only acts on segments turned a quarter turn. So the meaningful combinations are:

| # | pair |
| --- | --- |
| 1 | `upright` — nothing rolled, so no odd cabinet to place |
| 2–4 | `turned` x (`alternate`, `centred`, `column`) |
| 5–7 | `mixed` x (`alternate`, `centred`, `column`) |

Enumerating those seven makes the vacuous combinations **unrepresentable** instead of guarded. That guard is exactly the
bug fixed in 0.73.0, where the axis was swept unconditionally and produced 66 candidates that wrote nothing: 18 caught as
duplicates and 48 refused identically to their twin. Two options stay on the command line, because a hand invocation
wants `--orientation=turned` without an opinion on the odd cabinet; the *sweep* walks the seven pairs.

**And a pair that rolls nothing in *this* inventory is dropped as well, which is a second rule and not the same one.**
`mixed` rolls only the cabinets that get wider on their side, so `sepp` — whose subs are all 0.600 × 0.600 Achenbach
cubes — has nothing for it to turn, and the candidate it would produce is `upright` under another name. Dropping it
before solving rather than letting `deduplicate()` find it afterwards is what keeps the file names honest: a `-turned-`
or `-mixed-` file always has something turned in it. Measured: the seven pairs come out as 426 candidates rather than
`66 × 7 = 462`, and the 36 missing are exactly this.

**Count.** `3 x 7 x 3 x 3 x 7 x 2` = **2646 candidates**, six times today's 426.

The `impossible` half is **CVR-5**: rather than refusing a rig that cannot stand, emit it with every offending cabinet
coloured red. It is not a variant of a possible rig but its complement — a candidate is one or the other — so as an axis
it doubles the count rather than multiplying the written output. **The orientation axis made this much larger**: today's
365 refusals would become written scenes, where before the axis there were 55. **88 of the 365 are duplicates rather than
refusals** and would not be written, which still leaves 277 against `DEFAULT_MAX_SCENES = 80`. So CVR-5 is where the fuse
has to be raised deliberately, and by a lot.

##### Implementation order

Each step ends green and measured, and the safety check is the same every time: **`scenes/generated` must not change
except where a step is meant to change it**, and `ShippedScenesTest` is the gate rather than a scene diff, since scene
files record the stack spec and not solved positions.

1. **DONE in 0.75.0 — `column`, and `upright` renamed to `centred`.** Self-contained, no new axis.
2. **DONE in 0.77.0 — the folded orientation/mirror axis.** `StackOrientation`, `--orientation`, the seven pairs and a
   nullable `?StackOrientation` threaded through five signatures. **426 candidates writing 61 scenes against 11**, and
   every one of the 11 previous ids is still written, so the axis is purely additive.
3. **DONE — `DEFAULT_MAX_SCENES` stays at 80.** Step 2 writes 61 and fits, so the fuse needed no raise after all. It
   refuses rather than truncating, which is what makes leaving it alone safe: the next step that writes past 80 fails
   outright rather than silently shipping a subset. Doubling the shapes (GEO-9) or adding the `impossible` half (CVR-5)
   is where it will bind.
4. **Owner combinations** (ca. 2h) — CVR-3. Seven subsets rather than three groups. Expect `sepp`-only to produce
   nothing until CVR-1.
5. **`V` as a stated shape** (ca. 4h) — last, because it is the one most likely to fail. Read GEO-5 first: both
   pure-width pyramid bounds were measured and both produced interpenetration, because narrow stepped walls make gravity
   split rows into overlapping runs. A V bound hits the same cascade from the other direction.

##### What exists, per axis

| axis | values | state |
| --- | --- | --- |
| stacks | 1, 2, 3 | **done** — hard-coded `[1, 2, 3]` in `SceneStackCommand` |
| alignment | `center`, `block`, `stereo` | **done** — `LayoutMode`. `center` and `block` are both mono |
| shape | `pyramid`, `free` | 2 of 3 — **`V` is missing**, see below |
| orientation | `upright`, `turned`, `mixed` | **done** in 0.77.0 — `StackOrientation`, swept as pairs with the mirror style |
| mirror style | `alternate`, `centred`, `column` | **done** in 0.75.0 |
| inventory | `sdwa5`, `gmss`, `all` | 3 of 7 — **combinations are missing**, see CVR-3 |

##### The values still missing

**`V` as a stated shape.** `free` is not it. `free` means "as wide as the bearing rule allows", which *permits* a rig to
widen going up and does not ask for it — the V is a side effect there, not an intent. A stated `V` would widen
deliberately, which is a real rig: it throws the top boxes wider apart for coverage and puts the weight low. It needs its
own bound in `StackSolver::ceilingFor()`, the mirror image of the pyramid's. **Read GEO-5 first**: both pure-width
pyramid bounds were measured and both produced interpenetration, because narrow stepped walls make gravity split rows
into overlapping runs. A V bound will hit the same cascade from the other direction.

**Owner combinations.** Seven non-empty subsets of `{sdwa5, gmss, sepp}` rather than today's three fixed groups. Note
that **`sepp` alone cannot currently produce anything** — CVR-1 records that its eight cabinets cannot fill a 2 m wall
however they are stacked, so every `sepp` rig is refused until a top can stand on something that is not a cabinet. So
this axis does not pay off on its own.

##### Step 2 shipped in 0.77.0 — what it cost, what it bought and what the failures were

**What it bought.** 426 candidates writing **61 scenes** against 11, and every one of the 11 previous ids is still
written, so the axis is purely additive. Three-stack rigs appear for the first time, and
`stacked-all-2-turned-centred-stereo` carries **39 cabinets** where the upright rig of the same gear carries 38.

**The shape of it**, for anything that has to touch it again: one enum (`StackOrientation`, three cases plus `rolls()`
and `rollsAnything()`), the pairs built once by `orientationPairs()`, an `--orientation` option, and a nullable
`?StackOrientation` threaded through `buildInBand()`, `build()`, `solveGroup()`, `stackFor()` and `commandLine()`.
`stackFor()` resolves the rolled set from the orientation, or from `--roll-mirror` when that names it outright.
`readMirrorStyles()` no longer decides its own default, since which cabinets roll is the orientation's answer.

**The clock got much worse and that is accepted.** The suite is **9 minutes 34 seconds** for 770 tests and 11 229
assertions, against 88 seconds for 662 before, since a candidate is a solve plus a compile plus an interpenetration sweep
and `ShippedScenesTest` compiles all 61 written scenes on top. **Runtime is not a constraint on this project** — see the
reading rules at the top. It will get several times worse again at 2646 candidates. Do not spend effort optimising it
unless something else asks for that.

**The 10 test failures the first attempt was reverted over are read, and none of them was the axis being wrong.** Worth
keeping, because six of them are a pattern that will recur on every axis added after this one:

* **6 were tests that name a rig but not the axis**, so they counted scenes across a dimension that had just grown and
  asserted 1 where 3 or 9 is correct. Fixed by pinning `--orientation=upright`, exactly as those same tests already pin
  `--shape=pyramid` — and the comment explaining why was already there for the shape axis. **Expect this on steps 4 and
  5 too**, and read it as arithmetic rather than as a regression.
* **1 was a latent bug in a test**, found by the axis rather than caused by it. `testASingleStackIsNeverMirrored`
  asserted the output does not contain `mirror:`, and a rolled cabinet states `roll_mirror: 90.0` — the same seven
  characters. The loose form passed only for as long as nothing was ever rolled, and would have failed on a perfectly
  correct stack the moment one was. It asserts `mirror: true` now, which is the key it always meant.
* **1 is a real difference between rigs and worth knowing.** `mixed` rolls the Flexys, so a sub row is 3.112 m of four
  cabinets instead of 3.646 m of six, the wall tapers faster and the two 2-ways have nothing left to stand on: 21
  cabinets carried, and **both of the missing two named in the file**. The test asserted a flat 23 for every scene, which
  was true only while one gear list produced one rig. It now asserts 23 less whatever the file states it left out, so a
  *silent* drop still fails and a refusal the file explains is allowed to be one.
* **2 were the new tests themselves** — a console line wrap splitting `allowed: upright, turned, mixed` across two lines,
  and a `free` + `turned` combination that legitimately refuses on bearing, so the assertion moved onto a shipped file.

##### The id collision is resolved: old autogenerated scenes are disposable

A prototype's scene ids collided with four committed files, because `stacked-sdwa5-1-turned-center` and three siblings
had been written by a hand invocation rolling only `flexy` and `skram`, where an orientation mode rolls **every** sub —
same id, different rig, silently rewritten by `--force`.

**Settled by the user: the old autogenerated scenes are not wanted.** All ten hand-invoked `-turned-` scenes were deleted
in 0.76.0. They were exactly the set the current sweep does not produce, every one of them carried an explicit
`--roll-mirror` in its recorded command line, and nothing referenced them but one historical aside in a docblock. So
`scenes/generated/` now holds precisely what `scene:stack` writes, which is the invariant it was always supposed to have,
and the orientation axis has nothing left to collide with.

The general rule this establishes, worth keeping: **a file in `scenes/generated/` that the sweep does not produce is
stale, not precious.** `comm -23` between the directory listing and the sweep's ids is the whole check.

## CVR · coverage of the height band

**121 of 132 candidates are refused, and the two height bounds account for 72 of them.** Not because the band is wrong
but because the model has no way to raise tops other than stacking subs under them.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| CVR-1 | **A top may stand on something that is not a cabinet** — riser, stand or fly point. A rig too small for a 2 m sub wall is a real rig, not an impossible one | P2 | 6h | 54 refusals — every `sepp` rig, `gmss-3`, `sdwa5-3` | — | open |
| CVR-3 | The inventory axis is three fixed groups (`sdwa5`, `gmss`, `all`); **SWP-1 wants all seven owner combinations**. Needs owner-awareness in `everySpeaker()` and an `--owner` narrowing | P1 | 2h | 4 of SWP-1's 7 inventory values; `sepp` alone still needs CVR-1 | — | open |
| CVR-4 | Port the ~13 real event setups from Drive (`…/setups/`, 2D SVG) into scene files | P3 | 4h | "actually used in praxis", which nothing covers today | — | open |
| CVR-2 | Decide whether the sweep keeps offering `free` where the pyramid already solves — it misses the ceiling far more often, inherently | P3 | 15m | fewer named refusals, or more scenes — pinned at floor, scale has no P4 | decision | decision |
| CVR-5 | **Emit the impossible rigs instead of refusing them, with every offending cabinet coloured red.** A refusal is a sentence in a terminal that scrolls away; a render shows *which* cabinet and *why* | P2 | 5h | all 56 refusals become lookable-at, and the diagnosis stops being prose | — | open |
| CVR-6 | **Derive a smaller rig from one that fails** — drop cabinets until the same inventory stands up, and write that as its own scene beside the refusal | P2 | 4h | a buildable scene for every rig that currently produces none, `all-1` included | CVR-5 | open |

#### CVR-5 — show the failure instead of describing it

Where: `SceneStackCommand`'s refusal paths, `StackChecks`, `SceneStackCommand::floating()`, and a material override in the
Blender build.

Every check in this repository answers with a sentence and then throws the geometry away. That is the wrong way round for
the failures that are hard to picture, which is most of them: "a gmss-turbo-top would stand at 4.668 m with nothing under
it across x" took a debug dump, two probes and a corrected coordinate mapping to understand, and a render with that one
cabinet in red would have said it immediately.

So the refusal becomes a **scene plus a marking** rather than a skip. Each checker already names the cabinet or the run it
objects to, so the information exists and is currently discarded at the point of refusal.

Three things to settle while building it, none of them yet decided:

* **Where the colour lives.** A `debug_colour` on the placement is the smallest thing that works and it puts a rendering
  concern into the scene schema. An override passed to `scene:build` keeps the schema clean and means the marking is not
  reproducible from the scene file alone.
* **These scenes must not be mistaken for buildable ones.** A separate output directory, or a required prefix, and they
  stay out of whatever the sweep counts as written.
* **`ShippedScenesTest` must keep refusing them.** The test's whole promise is that a shipped scene stands up, so the
  marked scenes have to be excluded by construction rather than by a list somebody maintains.

#### CVR-6 — the same rig, small enough to stand

Where: the sweep in `SceneStackCommand`, alongside {@see WIDTH_LADDER_M}'s existing retry.

The sweep already walks a **width** ladder when a rig misses the height band. This is the same idea on the other axis:
walk the **cabinet count** down until the rig stands, and write that. `all-1` is the case that proves it is worth having —
41 cabinets in one stack cannot reach the band at any width, and nothing about that is interesting, whereas "here is the
biggest one-stack rig those cabinets *can* build" is the answer somebody actually wanted.

Which cabinets to drop is the question, and it is not obvious. Dropping the deepest loses the bottom row that carries
everything; dropping the tops changes what the rig is *for*. A first cut worth measuring is to drop whole rows from the top
of the sub wall, since that is what a crew does when the wall is too tall, and to stop at the first arrangement that
stands. Report what was left out by name — `statedMix` and the `LEFT OUT` reporting already do this elsewhere, so the
convention exists.

Depends on CVR-5 only for the framing: once a failing rig is emitted rather than skipped, "and here is the reduced one
that works" is the obvious companion output rather than a second mechanism.

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
| TOOL-5 | **`SceneStackCommand` is 1866 lines** and grew 130 with the orientation axis. Worth splitting before it passes 2000: the four `readShapes`/`readModes`/`readOrientations`/`readMirrorStyles` parsers plus `orientationPairs()` are one cluster with no dependency on the solve, and `floating()`/`coveredFraction()` are a second. Neither is a behaviour change | P3 | 2h | — | — | open |
| TOOL-2 | Asset previews are blank because they cannot render in background mode — generate them in the GUI once, or find a headless way | P3 | 1h | — | — | open |
| TOOL-1 | `inventory:import` — the first import was by hand because the source is several spreadsheets and CAD files and every number needed a provenance decision. Worth building when the gear list next grows; see [docs/inventory.md](docs/inventory.md) | P3 | 3h | — | — | open |
| TOOL-4 | GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so mostly packaging and metadata mapping | P3 | 3h | — | — | open |

## INFO · facts worth keeping

| ID | Item | State |
|----|------|-------|
| INFO-1 | **Our 4 m crank stands cannot clear a combined rig.** Every speaker in three stacks reaches 4.563 m as a pyramid and 5.628 m free, both above the 4 m the stands extend to, so a truss on `truss-tower-4m` sits below the tops it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead. Fine for our own 3.125 m rig, not for a combined one — worth knowing before hiring a stage. **Three-stack rigs are generated as of 0.77.0** — 10 of them, all `turned` or `mixed`, since a rolled sub wall is short enough for the band where the upright one is not | known |
