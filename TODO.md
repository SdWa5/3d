# TODO

## The goal

As many *sensible* speaker configurations as possible, generated automatically by a single command in its default
settings, with priority on the configurations actually used in praxis as well as ones algorithmically derived.

Not only enforce the subwoofer ceiling strictly (optimum 2–3 m) but improve the existing logic to reach it: **do not
avoid generating a scene, ignore the ceiling, or use fewer speakers if there is any other possibility to solve it.**

Where that stands: bare `scene:stack` writes **11 scenes of 66 candidates**, every stack's sub/top transition inside
2–3 m, every refusal named. The 55 refusals are the work — grouped below by what actually causes them. (The candidate
count halved in 0.73.0: the `--mirror-style` axis was being swept when nothing was rolled, where the mirror is a no-op
and the two styles are byte-identical.)

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
**GEO-2 is done as well**, and it was neither of the things this section spent three attempts on: two separate movers
shifted a row after gravity had seated it and neither re-asked what it now stood on. That took floating refusals from 6
to 1 and the sweep from 10 scenes to 11.

What is left does not run in a chain. **GEO-4 no longer waits on GEO-2** — it was measured again after GEO-2 closed and
the trade did not move — and **GEO-9 buys GEO-2 nothing**, since the tops row is the same width at every stage on the
ladder. Both entries record what was measured rather than what was expected.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-2 | **Done.** Two movers shifted a row after gravity had seated it and neither re-asked what it stood on. Fills over-pushed on an inflated span (fixed with `align.clear_of`) and the stereo spread never reseated (fixed with `Gravity::reseat()`) | — | done | floating refusals **6 → 1**, scenes **10 → 11**, no existing scene changed. The one left is `all-1`, which cannot stand at any width | — | done |
| GEO-4 | Multi-stack row sliding. **Measured three times and still net negative** (10 scenes against 11). The lookahead bound is built and correct and does not help, because gravity decides support before the compiler decides final x — the same split GEO-2 was. Needs the two-pass compile | P2 | 6h | 2 `LEFT OUT` cabinets, and `--per-owner` writing at all | — | measured |
| GEO-5 | The pyramid cap now reaches `statedMix` **and lifts**. What is left is that the cap is a *count*, and its stated reason — every cabinet 0.45–0.66 m — is false: `gmss-mid-bass` is **1.200 m**. **Needs a decision** on the tolerance | P2 | — | 2 of the 5 remaining overhangs; the other 3 are the deliberately uncapped tops row | decision | decision |
| GEO-6 | The whole inventory cannot be **turned** at once — a rolled SKRAM is 19 mm taller than a rolled Flexy and the row above straddles the step | P2 | 2h | 3 of 10 turned siblings | — | partial |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |
| GEO-9 | **`tower` and `mixed` shapes** — a wall of one width, and a tower base with a tapering top. **The cheap version is measured and does not work**: a bound in `ceilingFor()` cannot make a wall flush, so this is a change to `packedRows()`'s objective | P3 | 8h | nothing measurable — it does **not** buy GEO-2, see there. A shape people build, which is worth having on its own | — | measured |
| GEO-10 | A **`mixed` orientation** beside upright and turned — some device types on their sides, the rest standing, rather than the whole inventory one way | P2 | 3h | the 3 refused turned siblings GEO-6 names, and every rig where turning helps one type and ruins another | GEO-6 | open |

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

**The decision is the tolerance, and it is a decision because both extremes are already known to be wrong.** A strict
width cap was tried and is too blunt: it forbids a 27 mm shoulder the bearing rule allows four hundred of, which split
six Achenbachs into two rows of three and cost a 2-way from the rig. No width bound at all is what ships now. So the
answer is a stated tolerance, and what it should be derived from is the open question.

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

Watch the candidate count when this lands. Four shapes times three alignments times two mirror styles is 24 variants per
rig against today's 12, and `DEFAULT_MAX_SCENES` is 80.

#### GEO-10 — a mixed orientation

Where: `--roll-mirror` in `SceneStackCommand`, `StackEntry::$rollMirror`, and whatever the sweep decides to offer.

**The option already exists per device and the sweep does not use it that way.** `--roll-mirror` takes device ids, so a
mixed orientation is expressible today — the `-turned-` scenes in `scenes/generated` come from an invocation naming
`flexy-folded-horn-hybrid` and `skram` specifically. What is missing is the sweep ever *choosing* a subset: it offers
upright, or it offers everything named on the command line turned, and nothing in between.

**This is the answer to GEO-6 rather than a separate feature.** That item records why turning the whole inventory fails —
a rolled SKRAM is 19 mm taller than a rolled Flexy, so the row above straddles the step — and a subset is exactly the
escape: turn the types where it buys width, leave the ones that would introduce a step. Read GEO-6 first, since the 19 mm
measurement is the constraint any subset has to respect.

The open question is what the sweep enumerates, because the subsets are a power set and the candidate count is already a
concern. Worth measuring before choosing: turning only the types that are *deepest* is one rule, turning the types that
share a rolled height is another, and either is a fixed handful of candidates rather than 2ⁿ.

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

## CVR · coverage of the height band

**121 of 132 candidates are refused, and the two height bounds account for 72 of them.** Not because the band is wrong
but because the model has no way to raise tops other than stacking subs under them.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| CVR-1 | **A top may stand on something that is not a cabinet** — riser, stand or fly point. A rig too small for a 2 m sub wall is a real rig, not an impossible one | P2 | 6h | 54 refusals — every `sepp` rig, `gmss-3`, `sdwa5-3` | — | open |
| CVR-3 | The default `--from` means both sound systems in one stack; make it mean one system — an `--owner` narrowing, or owner-awareness in `everySpeaker()` | P1 | 1h | **12 of GEO-2's 22 refusals**, since `all-1` cannot stand at any geometry | decision | decision |
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
| TOOL-2 | Asset previews are blank because they cannot render in background mode — generate them in the GUI once, or find a headless way | P3 | 1h | — | — | open |
| TOOL-1 | `inventory:import` — the first import was by hand because the source is several spreadsheets and CAD files and every number needed a provenance decision. Worth building when the gear list next grows; see [docs/inventory.md](docs/inventory.md) | P3 | 3h | — | — | open |
| TOOL-4 | GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so mostly packaging and metadata mapping | P3 | 3h | — | — | open |

## INFO · facts worth keeping

| ID | Item | State |
|----|------|-------|
| INFO-1 | **Our 4 m crank stands cannot clear a combined rig.** Every speaker in three stacks reaches 4.563 m as a pyramid and 5.628 m free, both above the 4 m the stands extend to, so a truss on `truss-tower-4m` sits below the tops it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead. Fine for our own 3.125 m rig, not for a combined one — worth knowing before hiring a stage. No three-stack rig is generated at present; the band refuses them all | known |
