# TODO

**Closed items are deleted from this file rather than marked done**, so what is here is what is left. An id mentioned
in passing with no row of its own — "unblocked by GEO-12", "that is CVR-7" — is one of those: it shipped, and
[CHANGELOG.md](CHANGELOG.md) is where it lives now. This file is compacted rather than split, so it grows by what is
outstanding and shrinks by what is done.

## The goal

As many *sensible* speaker configurations as possible, generated automatically by a single command in its default
settings, with priority on the configurations actually used in praxis as well as ones algorithmically derived.

Not only enforce the subwoofer ceiling strictly (optimum 2–3 m) but improve the existing logic to reach it: **do not
avoid generating a scene, ignore the ceiling, or use fewer speakers if there is any other possibility to solve it.**

**And the band is an aim rather than a gate**, which settles how to read the sentence above. Stated by the owner: a sub
wall that puts the tops below or above head height is **not** a reason to refuse a rig or to call a scene invalid,
because the sub/top interface height is an optimisation problem. So "do not avoid generating a scene" wins outright, and
the two bounds cannot throw a rig away. That is CVR-7, it is **built**, and it was the largest single change in the
file: 551 of the sweep's refusals were that one message.

**The stage width is the same statement about a different number.** Also stated by the owner: how wide a generated scene
comes out does not matter at all unless a parameter limiting the width is explicitly passed. That is CVR-8, **built**
together with CVR-7, and no generated scene carries a width any more.

Where that stands: bare `scene:stack` writes **2726 scenes**, every stack's sub/top transition
**aimed at 2.5 m** and its miss written on the file where it misses, every refusal named, and every shape rule stated in
**metres rather than in cabinet counts**. What is still refused is geometry and duplicates — grouped below by cause.

**How it got here is in the CHANGELOG rather than repeated here**, but two findings from it govern what to build next.
**The orientation axis is worth more than every other axis put together** — 11 scenes became 61 in 0.77.0, and
three-stack rigs became possible at all, because a rolled sub wall is short enough for the band where an upright one is
not. And **the borrowed-gear pairs beat every single owner**: `sdwa5-sepp` writes 50 scenes, more than `all` at 17.

**The target is stated once, in SWP-1**, as a six-axis cross product. All of it exists, and the seventh axis SWP-2 added
is complete as of 0.96.0.

## How to read this

* **One table per group.** Groups are by shared root cause, so finishing one closes several rows.
* **Everything is sorted by priority**, rows inside a table and the group sections themselves. A section sits where its
  own top row does, so the first table in the file holds the highest-priority row in the file. Ties keep the order they
  already had rather than being re-argued.
* **IDs are stable and never renumbered.** Plain numbers broke every cross-reference twice in one session; `GEO-2`
  keeps meaning `GEO-2` even when rows are added, reordered or deleted.
* **Prio** — `P1` blocks the goal above · `P2` a real defect or a wanted feature · `P3` when it's next touched · `P4`
  wanted, but nothing waits on it and it may sit for a long time · **`P5` and beyond** a *direction* rather than a task:
  nothing is planned around it, nothing waits on it, and it may never be built. Kept because knowing where the project
  might go changes how the things above it are built.
* **Effort** — estimate for one focused pass *including* tests and docs, to 5 minutes. `phys` means physical work
  (tape measure, hanging scale, opening a rack), which no estimate here can shorten.
* **Buys** — the measured payoff, in refused sweep candidates or affected scenes. `—` means it buys nothing
  measurable and is wanted for its own sake.
* **Needs** — the IDs that must land first, or `decision` when a question has to be answered before any code.
* **State** — `open` · `partial` (some of it shipped) · `decision` (blocked on a call, not on work) · `known` (a fact
  worth keeping, not a task).
* Resolved rows are **deleted**, not ticked. Measured figures are re-measured when touched, never carried forward on
  trust — several were wrong for two releases.
* **This file is never split, however long it gets.** Stated by the owner, and it applies to `README.md` and
  `CHANGELOG.md` the same way. Each of them promises the reader everything on its subject, so hunting across several
  files for an item this one said it holds is worse than the length. When it gets unwieldy, **compact it** — delete
  resolved rows, drop text a later measurement has superseded, merge sections saying the same thing — and only then
  move genuine reference material into `docs/`. The line count is worth reporting; a split is not worth proposing.
* **Runtime is not a constraint on this project.** Stated by the user outright. A sweep that takes ten minutes and a
  test suite that takes longer are both acceptable if they produce more correct scenes, so no estimate, no design and no
  prioritisation here may trade coverage away for speed. Measure it, record it, and do not treat it as a blocker.
* **GEO, SYM and ALN together are the highest priority** — geometry, symmetry and alignment are the same placement
  problem seen from three sides. While working inside any of the three: fix every bug hit immediately, and implement
  every related TODO it turns up immediately, rather than filing it for later.
* **Every silhouette rule is a width, in metres, never a cabinet count.** Stated by the owner for the pyramid, the V and
  the tower alike. Nine of our ten cabinets are 0.45–0.66 m wide and `mid-bass` is 1.200 m, so a count stopped
  standing in for a width the day it arrived. They all live in `StackChecks::silhouetteProblem()`; anything new goes
  there too.

### The order to pick things up in

Settled with the owner, so a new session can act on it without re-deriving it:

**GEO-12, GEO-11's stack-local half, TOOL-6, TOOL-7, TOOL-8 and SWP-2 are done**, which is why the list now starts
where it does. TOOL-7 left a narrow remainder, filed as TOOL-15.

**SWP-2's third value shipped in 0.96.0** and cost 398 scenes of the fuse's headroom, so the sweep is 1374 against a
limit of 1500. What it did *not* settle is SYM-3's spreading half, which this item was supposed to be designed
together with: the two turned out to be different problems rather than one. `tops-shared` shares the **pool**, and a
tops row physically bridging two walls needs the walls to be level — which two different owners' inventories never
are, measured at 2.31 / 2.383 / 1.8 m. A bridging row therefore belongs to a mirrored pair out of one pool, and that
is SYM-3 alone.

**Before the list, one errand: put the Movano on a scale.** LOAD-2 is back at P1 for one reason. Sepp's payload was
estimated at 1200 kg, documented at 1365 and then **weighed at 1000** — his van is 365 kg heavier than its own
registration document, because a fit-out added after type approval appears in no field of it. The Movano's 1024 kg is
exactly the same class of figure and has never been checked. If it is out by anything like as much, the fleet is short
by well over 250 kg rather than 134.9, and every load plan drawn from it is optimistic in the direction that ends in a
fine. **One weighbridge ticket settles it**, and no amount of code substitutes.

**The runtime cluster came off this list entirely, and it is worth saying why.** TOOL-10 was ranked second on a
measured 6m40s. The stage now takes **34 s**, because 0.85.0 forked the very thing TOOL-10 was going to teach to skip,
so it is 1h 30m of work for half a minute and drops to P3 with the rest of them. The lesson is the ranking rather than
the item: **a priority argued from a measurement expires when the measurement does**, and this one expired inside a
single release.

1. **SWP-3**, sweep configuration and system grouping. After SWP-2, since a seventh axis with three values on it is
   the thing that makes the enable/disable surface worth building — and **the directory is now 2726 files**, which is
   the half of SWP-3 that has stopped being a preference.
2. **SYM-3 and GEO-9**, both raised to P1 by the owner. Placement breadth and the two missing shapes. SYM-3 did **not**
   fall out of SWP-2 as this list expected: `tops-shared` shares the pool, where SYM-3 needs a row bridging two walls,
   which only a mirrored pair can carry level. So the mechanism is still to be built, and the evenness rule is **equal
   pitch**, settled, with its 169 mm cost on the tightest tops row measured. GEO-13's gapped rows are equal pitch
   already, one gap per row.
3. **GEO-11's scene-level half** — aiming and cross-placement alignment, which is the genuinely circular part. The
   stack-local half shipped in 0.83.0 and unblocked GEO-9 and GEO-4 as far as it can; what is left needs the front face
   and the solve to stop depending on each other.

**GEO-14 arrived after this order was settled and has not been placed in it.** It is P1, it was stated by the owner and
its four blocking questions are answered: the shapes keep priority, height and acoustics are weighted metrics traded off
against each other, "central" is the rig's centre line for mono and each stack's own for stereo, and the fill key is
frequency. **The frequency quarter is done.** One sub-question is left before the rest can start, which is where the
weights live — a constant, a CLI option or a scene key. The power quarter waits on SPEC-13. Pick this up after SWP-3,
which is where the settled order now starts.

**CVR-1 is parked on the owner rather than on code** and is P4 for that reason.

## GEO · placement geometry

**One root cause across this group:** a row is positioned and spaced as if its cabinets were unrotated and centred, and
neighbouring stacks are spaced on nominal tier widths rather than on where the cabinets actually ended up. GEO-1, GEO-2,
GEO-3, GEO-6 and GEO-10 are done and their rows are deleted — the CHANGELOG has what each of them cost and bought.

**What is left does not run in a chain**, and every entry below records what was measured rather than what was expected.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-14 | **The lowest subs belong as low and as central as the rig allows — built as the eighth sweep axis, and two of its four quarters are closed.** `--low-end=central|low` ranks the arrangements the shapes and the bearing rules already accept, never refusing one and never narrowing a rig, and `StackSolver::spreadRows()` adds the candidate the search did not contain: the lowest type one to a row, each cabinet flanked into a full-width row, which is what puts two SKRAMs one above the other on the centre line. **What is left is quarter 4, central across stacks**: in a 2- or 3-stack rig nothing puts the deepest cabinets in the inner stacks, `StackDeal::byType()` balances on `quantity × width` alone, and `$index === $middle` in `inventoryFor()` is the existing inner/outer vocabulary to build it on. **And the power half cannot be built at all** — no wattage, sensitivity or SPL on a `DeviceSpec` — which is SPEC-13 | P2 | 4h | the low end in the inner stacks of a split rig, which is the only quarter with no incumbent rule to argue with | SPEC-13 for the power half | partial |
| GEO-11 | **Stack-local half DONE in 0.83.0**, scene-level half open. `StackSolver::solve()` takes a seating predicate and `SceneCompiler` supplies it, so the fill refuses an arrangement that overlaps *while it is still searching* rather than the whole rig being discarded at the end. It caught a rig being shipped with two cabinets inside each other. **What is left is aiming and cross-placement alignment**, which is the genuinely circular half: aiming needs the front face, the front face needs every placement, and every placement needs the solve | P1 | 6h | GEO-4, the rest of GEO-5 and GEO-9's tower | — | partial |
| GEO-9 | **`tower` and `mixed` shapes** — a wall of one width, and a tower base with a tapering top. **The cheap version is measured and does not work**: a bound in `ceilingFor()` cannot make a wall flush, so this is a change to `packedRows()`'s objective, which is GEO-11. **Both must be width rules**, stated by the owner, so they belong in `StackChecks::silhouetteProblem()` beside the other three | P1 | 8h | nothing measurable — it does **not** buy GEO-2, see there. A shape people build, which is worth having on its own | GEO-11 | measured |
| GEO-4 | Multi-stack row sliding. **Measured three times and still net negative** (10 scenes against 11). The lookahead bound is built and correct and does not help, because gravity decides support before the compiler decides final x. Waits on GEO-11 | P2 | 6h | 2 `LEFT OUT` cabinets, and `--per-owner` writing at all | GEO-11 | measured |
| GEO-5 | **Mostly closed by 0.81.0.** The cap is a width now, in `StackChecks::silhouetteProblem()`, with a tenth of a cabinet per side as the shoulder — so the false premise this entry was written about is gone. What is left is that the width rules refuse arrangements mid-search and the sweep got four times slower, which is GEO-11's shape again | P3 | 3h | — | GEO-11 | partial |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |

#### GEO-14 — low and central, which is four rules and only one of them exists

Where: `SceneStackCommand::byFillOrder()`, `StackSolver::fill()`'s `widestFirst()` branch, `StackMetrics::centred()`,
`statedMix()` / `mixedBottomRow()` via `widestSub()`, `topRow()` / `stereoTopRow()`, and `SceneStackCommand::byType()`.

Stated by the owner as one sentence, so it is recorded as one row. It is four separate pieces of work in the code, and
they are in four different states.

**Four questions are settled**, all by the owner:

* **The shapes keep priority and the acoustic rule is an optimisation problem**, not a gate. That is the same answer the
  sub height band got in 0.82.0 and it should be built the same way, as a cost the search ranks candidates on rather
  than as a refusal. `pyramid` and `v` go on re-ordering the inventory `widestFirst()`, and within the freedom a shape
  leaves, the arrangement that puts the lowest cabinets lowest and most central wins. **So nothing is ever refused for
  it and no rig gets narrower**, which is what makes it safe to add to a search that already has a ceiling and a target
  in it.
* **The key is frequency, not weight.** Done, see below.
* **"Central" is the rig's centre line for mono and each stack's own centre line for stereo.** So `center` and `block`
  push the lowest and most powerful subs towards the middle of the whole rig, inner stacks and inner positions both,
  while `stereo` centres them inside each stack and leaves the stacks where they are. That mirrors the split
  `topRow()` already makes for tops, where `center` and `block` put the long throw central with the fills outboard and
  `stereo` puts the fills inboard nearest the centre line, because a stereo rig exists for the width of its image.
  **Both mechanisms get built** and the alignment picks between them, which is the most work of the three answers and
  the only one that does not contradict what the code already believes about the two cases.
* **Height and acoustics are traded off against each other, as differently weighted metrics.** Not a tiebreak and not a
  gate. The arrangement that wins is the one with the best weighted score across several named measures, so a rig may
  legitimately give up some height to put the low end lower and more central, or the reverse, depending on what the
  weights say.

**1. Low, inside one stack. Done.** `byFillOrder()` now sorts on the driven low corner and falls back to mass, and the
guard is the part that matters: **frequency decides only between two cabinets that both state one**. An earlier
frequency-first sort read a missing passband as `INF` and fell back to `quantity × width`, which put the 40 kg IQ subs
under the 220 kg wall basses. Nine of our ten speakers state no passband, so a rule that ranks on absence ranks almost
everything on nothing. **The change is inert on the gear we own**, checked rather than assumed: our three cabinets with
a passband come out in the same order either way, because the Achenbach reaches 35 Hz but is high-passed at 38 on
purpose so that it sits above the Flexys, and GMSS's four state none at all and fall through to mass.

**2. Low, versus the shape. Settled, and now an optimisation.** `fill()` re-orders the inventory `widestFirst()` for
every shape but `free`, and its comment states the price outright: a wide-but-shallow type ends up *under* a deep one,
which is the inversion `byFillOrder()` exists to prevent. That was not an oversight and it buys a metre and a sixth of
height on the GMSS wall, six IQ subs on the floor giving 3.28 / 2.56 / 1.54 in three rows at 2.070 m against
1.34 / 1.20 / 1.63 / 1.63 / 1.54 in five rows at 3.240 m. **The shape wins and the acoustic order becomes a preference
between the arrangements that shape allows.** Where to put it is `fill()`'s ranking, beside
`SceneStackCommand::heightCost()`, since that is the one place a legal arrangement is already scored rather than
accepted.

**3. Central, inside a row. Wanted, and it can only ever be a weighted preference.** `centred()` puts the **tallest** take in the
middle, `widestSub()` puts the **widest** sub in the middle of a mixed bottom row, and `topRow()` centres the long
throw. Every one of those is load-bearing for a reason that is not acoustics: the tall segment in the middle is what
carries the row above at all, measured at 14 % bearing when it sits outboard instead. **Re-keying them on frequency
would break the reason they exist**, so this has to rank arrangements the bearing rules already accept and may never
replace their key.

**4. Central, across stacks. Wanted, and nothing exists.** In a 2- or 3-stack rig nothing puts the deepest cabinets in
the inner stacks. `byType()` balances the split on `quantity × width` alone, which is a packing heuristic with no side
to it, and `by-count` has none either. This is the only quarter with no incumbent rule to argue with, so it is the one
to build first.

**The objective becomes a weighted score over several metrics, and that is the shape of the whole item.** Settled by the
owner: height and acoustics are trade-offs weighted against each other rather than one deciding and the other breaking
ties. `SceneStackCommand::heightCost()` is already two thirds of it — distance from `target_sub_height_m` plus
`OUT_OF_BAND_PENALTY` times the part of the miss outside the band — so what it becomes is a general weighted sum with
the acoustic measures added beside the height ones, in the one place a preference between *legal* arrangements is
already expressed. Nothing there can refuse an arrangement, which is what makes it the right home for a rule the owner
has said is an optimisation rather than a gate.

The metrics to name, each scored so that lower is better and each weighted separately:

* distance from the target sub height, and the part of the miss outside the band, both of which exist today
* how far the lowest cabinets sit above the floor, weighted by how low and how powerful they are
* how far those same cabinets sit from the centre line, which is the rig's for `center` and `block` and each stack's
  own for `stereo`

**The one open sub-question is where the weights live**, and it should be answered before any of it is built, because it
decides whether a scene can be reproduced from its own file. A constant is the smallest thing that works and means
nobody can trade the two off per rig. A CLI option is reproducible, since the recorded command carries it, and it adds
an axis the sweep would then want to walk. A scene key is the most expressive and puts a search parameter into the
schema, which is the thing the `max_width_m` work has just finished arguing against.

**Power is not in the schema.** `DeviceSpec` carries `weightKg`, `passband`, `coverage` and `drivers`, and `Driver`
carries size, type and count. Neither has a wattage, a sensitivity or an SPL figure anywhere. So the "highest power"
half of the sentence cannot be implemented at all until a field exists and is filled, and every number that goes into it
needs provenance like every other spec figure. Filed as **SPEC-13** rather than guessed at here.

#### GEO-11 — the three stages that cannot see each other

Where: `StackSolver::fillWith()` and `packedRows()`, `Gravity::resolve()`, `SceneCompiler`.

**The fill decides row widths, `Gravity` decides where cabinets land, the compiler decides final x, and no stage sees
the next one's answer.** Written out here because it has been re-derived four times from four different symptoms, each
time by somebody who did not know it was the same thing:

| found as | what it looked like |
| --- | --- |
| **GEO-2** | a fill was moved sideways after gravity had seated it and kept the old height, leaving a top 228 mm in the air |
| **GEO-4** | a row slides for its own bearing and walks out from under the tier it carries, which no bound in stack clearance can see |
| **GEO-5** | a width-based pyramid narrows rows, narrow rows make more of them, and gravity splits that staircase into runs 127 mm inside each other |
| **GEO-9** | a bound in `ceilingFor()` cannot make a wall flush, because the fill cannot ask what the row above will need |

Each was attacked on its own and each was measured net negative or inert. That is the evidence: they are one problem.

**What it needs is that the fill can ask a question it currently cannot** — "if I build this row, what will gravity do
with the row above it, and where will the compiler put both". Two shapes are worth weighing before writing anything: a
**two-pass compile**, where the whole rig is solved once, gravity and the compiler run, and the fill re-runs knowing the
answer; or a **lookahead inside the fill**, where each candidate row is speculatively seated before it is accepted. The
first is simpler and slower, the second is faster and duplicates knowledge.

**This is the one item in the file worth a plan before a line of code.** It touches all three stages that every hard
entry here runs into, and a wrong structure would be expensive to unwind.

##### What shipped in 0.83.0, and the three defects it cost to get there

**The stack-local half is built.** `StackSolver::solve()` takes an optional predicate on a candidate arrangement and
`fill()` asks it alongside `supportChecks()`; `SceneCompiler::seatingCheck()` supplies one that expands the tiers and
compiles them, so the answer comes from the same `orientationFor()` and `worldBox()` the finished scene uses rather than
from a second opinion about where a cabinet's edge is. Callers that cannot place a cabinet pass nothing and get the old
behaviour.

**It found a rig being shipped with an overlap in it.** `--from=gmss-* --max-width=3.70 --orientation=turned` used to
answer with five rolled IQ subs slid along a 2.77 m support at 2.66 m of subs, and two of those cabinets are inside each
other once placed. Nothing had ever checked it, because a stated `--max-width` keeps an invocation out of the shipped
scene set the interpenetration sweep covers. The check refuses exactly that one candidate and the search falls to a
clean 2.70 m.

**Three defects on the way, each caught by a number and none by reading.** They are the reason this took as long as it
did and they are worth not repeating:

* **Where the check goes was got wrong twice, and only the third arrangement is affordable.** Asked of every candidate
  it is a compile per arrangement and the sweep stops finishing at all. Moved *out* of the search — place the answer,
  strike it out if it overlaps, re-run the fill — the same test took **58 minutes**, because a re-run is another walk
  of a fifty-step ladder and a fill costs far more than a compile. **Asked inside the search, memoised by arrangement**,
  it is 23m40s against 20m14s with the check off. The memo is what makes it work: the ladder proposes the same rows
  from many budgets, so most steps are already judged. It is still asked only of a candidate that would win, and the
  fallback arrangement is held to the same bar, because returning an overlapping rig is the failure the seam exists to
  stop.
* **`scene:stack` solved with no predicate and then compiled the answer with one**, so the command wrote the
  arrangement its own solve liked and the compiler rebuilt a different one from the same file. That is this very item's
  bug, reappearing between two stages instead of three, *inside the change meant to fix it*. Both go through one entry
  point now.
* **The probe judged the top tiers firing straight ahead** where the file states `aim: far`. A turned cabinet's
  outermost corner moves when it is aimed, so it was measuring a different rig and refusing overlaps that existed only
  inside the check: the `all` inventory's turned rigs came back with **0.660 m of subs** against 1.860 m once fixed.
  `StackSceneWriter::focusPoints()` is now the one definition both sides read, because two copies of `far: 10 m / 1.8 m`
  is how they drift apart again.
* **A fourth, found in 0.84.0 by looking at a render rather than at a number, and the reason this list is worth
  keeping.** Asking the same question of both sides is only half of it — both sides also have to be given the same
  stack. `scene:stack` solves a solo rig with `slideSlackM: INF` and the scene schema had no key for it, so the
  compiler re-solved every written solo scene with `null`, which forbids a slide entirely. Same rig as the third
  defect and the same headline number: **0.660 m of subs against the 1.860 m its own header reported**, two rows of a
  23.5 m line where the file described four. Fixed by `slide_slack_m` on the `stack:` block. **The invariant now has a
  test of its own** rather than each field getting one, because this is the third time it has broken in a new place.

**Measured, with GEO-12, against 0.82.0:** 396 → 450 scenes written, 110 → 137 fully in band, 94 new rigs, 21
consolidated into siblings, 19 refused. The refusals are 13 interpenetration and 6 bearing, all rigs where every
arrangement in reach fails.

**The check that the approximation is honest passed.** The plan said that if judging an unspread top row were too
strict, the sweep would write *fewer* scenes. It writes more.

##### The plan, and the line it draws

**The circularity is real and it is bounded, which is the finding that makes this tractable.** Aiming needs the scene's
front face, the front face needs every placement, and the placements need the solve — so the fill genuinely cannot ask
what the *scene* will do with its rows. But everything a **stack decides on its own** is knowable at solve time and is
where all the measured damage is: which cabinets share a row, where gravity seats each run, and how far apart the
stacks stand. `Stack::expand()` already computes all three, from the tiers alone.

So GEO-11 splits into two halves and only the first is worth doing now:

* **Stack-local reconciliation.** `StackSolver::solve()` takes an optional predicate on a candidate arrangement, and
  `SceneCompiler` supplies one that expands the tiers, places them at the stack's own base and asks
  `Interpenetration::worst()`. The fill then rejects an arrangement that overlaps *while it is still searching*,
  instead of the command discarding the whole rig at the end.
* **Scene-level reconciliation**, which is aiming and cross-placement alignment. Left open, because it is the half that
  is actually circular.

**Why a predicate rather than a two-pass or a lookahead.** The entry weighed those two and both have a real cost: a
two-pass re-runs the whole solve, and a lookahead "duplicates knowledge" by teaching the solver geometry it should not
own. A predicate is the lookahead shape with the knowledge **injected** instead of duplicated — `StackSolver` stays
free of the compiler, and the one stage that already knows how to place a cabinet is the one that answers. A caller
that cannot place, such as `StackSolverTest`, passes nothing and gets today's behaviour.

**The approximation to watch, and it must be measured rather than argued.** The predicate places each run at its own
base without solving `align`, so a top row that alignment would spread is judged at its unspread spacing. Overlaps
inside a run and between runs of one tier are impossible by construction, so what this can get wrong is a *between-tier*
overlap that spreading would have resolved — which would show up as the sweep writing **fewer** scenes. If it does, the
approximation is too strict and belongs in the second half instead.

#### GEO-5 — closed by 0.81.0, and one negative result worth not repeating

**The item is gone**: the pyramid's cap was a cabinet count resting on "every cabinet is 0.45–0.66 m wide", which
`mid-bass` at 1.200 m falsified, and 0.81.0 made every shape rule a width in `StackChecks::silhouetteProblem()`.

**Kept because it was tried twice and reverted twice.** A *pure* width bound, with no shoulder allowance, is worse
than the count rule: bounding by the row below gave 7 scenes against 11 with 2 interpenetrations, bounding by the
base gave 8 with 3, and a tolerance is not the lever either. What makes it work is `PYRAMID_SHOULDER = 0.1` of the
outboard cabinet per side; both bounds on that number are measured in the CHANGELOG. **Why the pure bound fails is
structural and is still open as GEO-11**: narrower rows make more rows, and a wall of many thin rows is a staircase
`Gravity` splits into runs inside each other.

#### Kept from GEO-2, which is closed: splitting the tops row is the wrong lever

**Measured and rejected.** Splitting a too-wide tops row into two rows makes the rig taller without making it
narrower, because the widest row is what a stage bound reads and the second row inherits the first one's width.
What retires those refusals is a narrower inventory, not a cleverer row: 8 tops in one row are 3.921 m against a
3.80 m stage, so no geometry stands `all-1` up.

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
`StackShape::cases()` in its own error, so a new case parses and documents itself. 0.81.0's `v` proved that half.

**A WIDTH BOUND CANNOT MAKE A WALL FLUSH, AND THAT WAS TRIED AND REVERTED.** The cheap version of `tower` is one branch
in `StackMetrics::ceilingFor()`, bounding a row by `min($stack->maxWidthM, $supportM)`. It builds, it leaves the other
shapes byte-identical, and it does not work, because **`ceilingFor()` is an upper bound and a narrow row is not a row
that was capped — it is a row whose device ran out of cabinets.** Lowering a ceiling cannot add cabinets to a row, so it
can only ever make a wall narrower. Measured on the five GMSS types at 5 m, the best `tower` still tapered from 3.280 m
to 1.200 m over four rows, and the branch wrote **0 scenes out of 66 candidates** while losing 2 cabinets out of 23 in a
test rig.

**What a flush wall actually needs is a fill, not a bound.** Every row has to be built from *several* device types
chosen to reach a target width. `StackSolver::packedRows()` already does that and sizes its rows through `ceilingFor()`
too, so it inherits the same limit. The work is a width **target** carried into `packTo()` beside its existing budget,
and a pack that keeps taking types until a row reaches it rather than until the next one does not fit. That is a change
to the packer's objective, which is GEO-11, and it is nowhere near the estimate this row carried before. Re-estimate
before starting, and watch `DEFAULT_MAX_SCENES` when it lands.

#### GEO-4 — resolved extents, and the row that may not move

Where: `Gravity::slidSeats()` (bounded by `Stack::$slideSlackM`), `Stack::spreadApart()`, `SceneCompiler`.

A row does not have to be centred on what carries it, and 0.70.0 acts on that where nothing stands beside the stack —
which is what recovered `stacked-gmss-1-center` at 2.84 m. **This is the concrete instance of the goal's "do not use
fewer speakers":** `stacked-all-2-center` ships with `mid-bass: LEFT OUT, it cannot be carried in this stack`, for
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

**Two diagnoses were tried here and both were wrong.** The first said the tops row had to learn to stand on its
support's plateau, which `Gravity::reseat()` settled without moving the trade. The second said the slide needed a bound
that also kept the row under what stands on it. **That was built and did not move the trade either**, and why not is the
useful part.

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
| SYM-3 | Stereo/mono placement breadth: subs mono where possible and spread only as far as the tops need; tops as wide and as evenly spaced as possible; symmetry wins ties. **No longer blocked on a decision or on another item.** The spreading half is a tops row shared across a **mirrored pair** rather than a change to `Alignment`, and the evenness rule is **equal pitch**, settled by the owner. Both measured, see the section — and 0.96.0 established that SWP-2's `tops-shared` does *not* supply the mechanism, since it shares the pool rather than the row | P1 | 5h | broadest stereo image; the mono spread. Costs 169 mm on the tightest tops row, which only bites where a width is explicitly stated — see CVR-8 | — | open |
| SYM-2 | Stack ordering cannot make the flanks *equal*, only place the tall ones | P3 | 1h | 3 of 13 multi-stack scenes are height-asymmetric | GEO-4 | partial |

#### SYM-3 — both blockers settled, and it is now work rather than a decision

The ask is "in a stereo scene the subs get spread out as wide as possible or necessary so that the tops can be set as
far apart as possible", and "in a mono scene the outermost tops as wide apart as possible but all tops spaced as evenly
as possible", with symmetry between and inside stacks optimised.

**Both blockers are settled.** The first turned out to be a different item, and the second was answered by the owner
after it was measured. Nothing here waits on a decision any more.

##### Resolved: spreading subs is a shared tops **row**, not a change to `Alignment` — and SWP-2 does not supply it

**Two different things wear the word "spread", and only one of them was ever the problem.**

Spreading a sub *row* means air between cabinets inside one tier. That is what ALN-4 forbids and the ban is right rather
than conservative: `Gravity::MIN_BEARING` is `1/3`, so a cabinet must land on at least a third of its own width, and
stretching the row underneath hands it air instead. This is not a preference that can be switched on and preferred where
possible. It is a rig that falls over, so there is nothing to gain by making it optional.

Spreading sub *columns* means each sub stack stays solid and the stacks move apart. Nothing stands over air, because
every tier still sits on a whole stack. **This is what the ask actually wants, and half of it already exists**:
`--stacks=N` splits the rig and `--clearance` sets the air between the stacks, default 0.5 m.

**What is missing is that every stack carries its own tops row.** `StackTops::topRow()` builds the tops from what is
left in *that stack*, so widening the clearance moves the subs and their tops together. The tops cannot be held in place
while the subs open underneath them, and a top cannot go wider than its own stack. So this half is not a change to
`Alignment` at all, and **SYM-3 no longer waits on ALN-4.**

**It does not wait on SWP-2 either, and that correction is worth keeping.** This section used to say the mechanism *was*
SWP-2's third value arriving from the other direction. It is not, and 0.96.0 settled it by measuring: `tops-shared`
shares the **pool**, dealing every top across the sub walls, and a row physically bridging two walls needs those walls
to be level. Two different owners' walls come out at 2.31 / 2.383 / 1.8 m and there is no common module in our cabinet
heights to make them agree, so a bridging row can only stand on a **mirrored pair out of one pool** — which is SYM-3's
own case and nothing SWP-2 built. The mechanism is still to be written.

**One real constraint comes with it, and it is computable rather than a guess.** A tops row spanning two sub stacks has
cabinets over the gap, which is the same standing-on-air problem one level up. Every top must still land on a third of
its width, so a 0.450 m top may hang about 0.300 m off an edge and two tops meeting over the gap put a rough ceiling
near 0.600 m on the clearance. **That bound is what "only as far as the tops need" is solving against**, which is the
sentence the ask was always making and nothing could express.

##### Equal air against equal pitch, and why it was a real question

**"Evenly spaced" has two meanings and they stop agreeing the moment the cabinets differ in width.** Our tops are 0.450
(`turbo-top`), 0.4656 (`eighteensound-2way-15`) and 0.500 (`tecnare-m2122`), so equal *air* between boxes and equal
*pitch* between centres are different placements. Pinning the outer two to the envelope — which is what "as wide apart
as possible" means — leaves no freedom to satisfy both.

Measured on all eight tops on a 4.40 m stage, in the order `topRow()` deals them, by
[`tools/tops-row-spread.php`](tools/tops-row-spread.php) so it can be re-measured rather than trusted:

| | equal air | equal pitch |
| --- | --- | --- |
| air between boxes | 88.4 mm everywhere | **64.3 to 114.3 mm** |
| centre to centre | 546 to 596 mm | 564.3 mm everywhere |

Worst centre disagreement between the two: **44 mm**. Equal pitch puts the widest three cabinets 64 mm apart while the
narrow ones get 114 mm, which reads as a mistake in a render. Equal air puts the centres on an irregular grid, which is
invisible unless somebody measures it.

**And today's `block` is neither.** A `Tier` lays its cabinets edge to edge with a constant `gap_m`, so an *unspread*
row is exactly equal air. `Alignment::apply()` then scales every centre offset by one factor `t`, so the air between a
pair grows with that pair's own width:

```
gap(i, i+1) = (wᵢ + wᵢ₊₁)/2 · (t − 1) + gap_m · t
```

On that 4.40 m stage the factor is 1.1379 and the row lands 6 mm from equal air, so today it is close by accident. The
error is proportional to `t − 1`, so a rig spreading twice as far drifts about seven times further.

**That is the part that decides the size of this item.** Equal pitch is a one-scalar solve and fits the current model
exactly. Equal air with the ends pinned is a per-copy offset, which one scalar cannot express, so `Alignment` gains a
second path beside the scalar one its whole docblock rests on.

##### Settled by the owner: equal pitch

**Equal pitch wins.** Stated outright, against a recommendation of equal air, so this is decided rather than open.

That leaves one consequence, measured after the call rather than argued before it. **A uniform pitch has to clear the
widest adjacent pair**, which leaves every narrower pair holding more air than it needs, so the tightest possible row
gets wider:

| the eight tops at their tightest | width |
| --- | --- |
| equal air, `gap_m` everywhere | 3.9212 m |
| equal pitch, 0.520 m everywhere | **4.0900 m** |

**169 mm, and CVR-8 is what makes that harmless.** This paragraph used to say the number decides whether a rig exists,
on the grounds that eight tops already do not fit a 3.70 m stage at 3.921 m and 4.090 m needs a wider one again. **The
owner then settled that an unstated width limits nothing**, so a row growing by 169 mm costs a wider scene and not a
lost rig. The number stays because it is still real where somebody *does* state a width: pass `--max-width=3.7` and the
169 mm is the difference between a rig and a refusal.

**So the rule needs a floor, and the floor is the existing one read correctly.** Equal pitch whenever there is slack to
distribute, never below the pitch that clears the widest pair, and the tightest arrangement below that is the equal-air
packing a `Tier` already builds. That is the same shape as `Alignment::minParameter()`, which exists because a factor
under 1 drives neighbours into each other — the argument transfers, but it is an argument about factors and has to be
re-derived for a pitch.

**What it costs to build.** Less than equal air would have. Equal pitch puts the centres on a regular grid, which is an
affine function of the index, which is what a `row` group's `step_m` already is — so it stays inside the one-scalar
model `Alignment` rests on. What changes is that `Tier::seats()` packs by equal air, so the natural offsets a spread
scales are the wrong ones to scale, and the row has to be rebuilt on a step before the solver touches it.

#### SYM-2 — what ordering can and cannot do

The taller stacks go to the middle in mono and the ends in stereo, ordered by solved sub height. Both halves are
exercised now — 5 multi-stack stereo scenes ship. Symmetry is *improved* rather than delivered: equal flanks depend on
the split giving each stack similar contents, so `stacked-gmss-2-stereo` reads 2.74 | 2.07 and `stacked-all-2-center`
2.033 | 2.833. `--per-owner` can never be symmetric — three owners are three different systems — and currently writes
nothing at all: 6 variants refused by GEO-2's family and the rest by the sub height band.

## SWP · the sweep's axes

**What the autogeneration should produce, stated as one cross product. Nothing is open here.** All seven axes
exist, and SWP-3's three asks are built: **every option narrows one axis instead of collapsing the sweep**, a
**grouping** says which owners are one system, and **each axis is a directory level or a name field** by
`--folders`. SWP-1's powerset over owners is deliberately retired — five owners make 31 inventories, 26 of them
rigs nobody would build — so a run builds one subset and silence means `sdwa5` + `sepp`.

**The inventory is three questions rather than one.** Whose gear is in the rig is `--owner`; **how much of it turns
up** is a roster in [`rosters/`](rosters), because a spec's `quantity` is what a system owns and an event is what it
brings; and **who counts as one system** is `--group`, stated at invocation time rather than in the specs. All three
are written up in [docs/scenes.md](docs/scenes.md).

## CVR · coverage, and the inputs that were secretly gates

**Most of the sweep's candidates used to be refused, and the largest share was not geometry** but two inputs stated
as preferences and enforced as gates: the height band (CVR-7) and the stage width (CVR-8). **Both are built** and
the sweep went from 150 scenes of 1206 candidates to 396. The reasoning lives in
[docs/scenes.md](docs/scenes.md#the-sub-height-band-which-is-an-aim-rather-than-a-gate), where a reader of the
behaviour looks for it. **What they cost is filed as GEO-12**: the width ladder was also a search dimension, and
without it 18 scenes are lost and 28 more sit further from the aim.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| CVR-9 | **Labels and a legend on a render.** **Built in 0.95.0** as `scene:render --labels`: one label per device per vehicle with the count in the text, the vehicles named, and a legend standing beside the scene naming what each cage colour means. What is left is **overlap** — eighteen labels on the packed convoy at 960 × 540 have several sitting on each other, and nothing lays them out to avoid it. A real fix needs screen-space placement, which means projecting through the camera; a cheap one nudges a label up when its anchor is close to another's | P3 | 2h | labels that never hide each other | — | partial |
| CVR-6 | **Derive a smaller rig from one that fails** — drop cabinets until the same inventory stands up, and write that as its own scene beside the refusal | P2 | 4h | a buildable scene for every rig that currently produces none, `all-1` included | CVR-5 | open |
| CVR-4 | Port the ~13 real event setups from Drive (`…/setups/`, 2D SVG) into scene files | P3 | 4h | "actually used in praxis", which nothing covers today | — | open |
| CVR-2 | Decide whether the sweep keeps offering `free` where the pyramid already solves — it misses the ceiling far more often, inherently | P3 | 15m | fewer named refusals, or more scenes | decision | decision |
| CVR-1 | **A top may stand on something that is not a cabinet** — riser, stand or fly point. A rig too small for a 2 m sub wall is a real rig, not an impossible one. **Deferred by the owner**, and CVR-7 removes the urgency entirely: a short wall stops being a refusal, so this becomes a modelling feature rather than a fix. It still waits on what we actually own | P4 | 6h | nothing once CVR-7 lands — the 258 refusals it was written for are CVR-7's | decision | decision |

#### CVR-6 — the same rig, small enough to stand

Where: the sweep in `SceneStackCommand`.

Walk the **cabinet count** down until the rig stands, and write that. The sweep used to walk a width ladder for the
band, which is the same idea on a different axis, and that ladder is gone — so this is the retry mechanism rather than
a second one beside it. `all-1` is the case that proves it is worth having: 41 cabinets in one stack cannot be made to
stand, and nothing about that is interesting, whereas "here is the biggest one-stack rig those cabinets *can* build"
is the answer somebody actually wanted.

Which cabinets to drop is the question, and it is not obvious. Dropping the deepest loses the bottom row that carries
everything; dropping the tops changes what the rig is *for*. A first cut worth measuring is to drop whole rows from the top
of the sub wall, since that is what a crew does when the wall is too tall, and to stop at the first arrangement that
stands. Report what was left out by name — `statedMix` and the `LEFT OUT` reporting already do this elsewhere, so the
convention exists.

Depends on CVR-5 only for the framing: once a failing rig is emitted rather than skipped, "and here is the reduced one
that works" is the obvious companion output rather than a second mechanism.

#### CVR-1 — tops that do not stand on the sub wall

**Dropped to P4, and CVR-7 is why.** Now that a short wall is a miss reported on the file rather than a refusal, this
entry buys nothing it was written for and is a modelling feature somebody may want for its own sake. The measurement
below stays because it answers "what should we buy", which is the question this was really about. It was taken across
the 258 short walls counted at 0.78.0; the sweep now writes 211 of them outright, so the shape of the distribution is
what to trust rather than the count.

The family is one message: the rig cannot fill a 2 m wall out of the cabinets it is given, so the tops fire below head
height. In reality you solve that with a riser or a pair of stands, and neither is modelled — support in the solver is
always another cabinet.

**What it is blocked on is gear, not code.** Measured across the 258: the shortfalls run 60 mm to 1470 mm with a median
of 818 mm, and a riser would cover them like this —

| riser | covers |
| --- | --- |
| 200 mm | 30 |
| 400 mm | 111 |
| 600 mm | 117 |
| 800 mm | 123 |
| 1000 mm | 204 |
| 1400 mm | 237 |
| 1600 mm | 267 |

A **stage deck is a property of the venue rather than gear we own**, so modelling it needs no spec invented and is the
cheap option (ca. 4h) if it is wanted. A **speaker stand is gear**, and `specs/stands/` holds only the two Krause AH7
scaffold towers — so height, weight and quantity would all have to be invented, which the spec rules forbid. Say what we
own and it becomes buildable. **Flying** is the third answer and the most expensive: we own 2 `truss-tower-4m`, 2
`tower-5m`, 5 `truss-f33-2m` and 1 `truss-9m`, SCN-1 wants the Tecnare tops flown, and INFO-1 records that the
4 m towers cannot clear a combined rig.

#### CVR-3 — `owner` was standing in for "system", and no longer is

**Answered, and the answer is a grouping rather than a spec field.** `owner` stays the discriminator in the data,
because the repository deliberately supports borrowing gear between owners and a `system:` field on a cabinet would
be a fact about one gig written onto an object. `App\Scene\SystemGrouping` sits on top of it and is stated at
invocation time: `sdwa5` and `sepp` are one system by default, `--group=NAME:owner+owner` says otherwise for a run.

## LOAD · transporters and packing

**Raised to P1 across the group by the owner.** It used to sit low because nothing in the solver waited on it, which
was the wrong test: this is the only group whose output somebody needs on the day, and a rig that cannot be transported
is not a rig. A payload overrun is also the one failure in this repository that is a **legal** problem rather than a
bad-looking render.

**The gear side of this was always finished and the vehicle side is now half filled.** Two transporters exist, one
Stefan Ripper's and one Sepp Fronz's. Stefan's is an Opel Movano L4H3 and its papers settle every mass and the outer
box, including the number that matters most: **1024 kg of payload against 2660 kg of cabinets in the `all` rig**, so one
van carries about two fifths of one generated scene. Sepp's is still described rather than documented.

**Stated by the owner: GMSS gear does not travel in these two vans.** That is a scope constraint rather than a
measurement, and it is the one that decides the size of the problem. It takes 21 cabinets, 1255.2 kg and 6.098 m³ off
the load and leaves **2238.5 kg against 2224 kg of fleet payload**, so the pack is one trip or very nearly one, and the
`all` rig above is a picture rather than a load. The two vans still travel together, because neither carries sdwa5's own
gear alone.

**The code half is done and what is left is a tape measure.** LOAD-1 landed whole in 0.86.0 and LOAD-2 has both specs
in `specs/vehicles/`, one documented and one an explicit placeholder. What remains is **the inside of both vans with a
tape measure** — no registration document states a load bay — and **fields F.2 and G off Sepp's Zulassungsbescheinigung**,
which are the two numbers the whole one-trip-or-two question turns on. LOAD-3 and LOAD-4 are ordinary work whose inputs
now exist, and whose answer is currently a statement about two estimates rather than about a van.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| LOAD-5 | **The trailer is specced and is a third bin.** 750 kg gross, ca. 200 kg unladen, so 550 kg of payload — of which the generator is 465, leaving 85. It does **not** reach one journey: re-measured on 2026-09-02 the plan is **134.9 kg short** with the generator aboard and leaves three devices behind, against 214.5 kg short over the two vans alone before the trailer existed. Without the generator the same plan fits everything with **340.5 kg spare**, so what the fleet cannot carry is the generator rather than the gear. What is left is the schema's side of towing: **O1 and O2 are on both sets of papers and in no field**, nothing pairs a trailer with the vehicle that tows it, and both would go unchallenged for a trailer above 750 kg. 750 is exactly O2 unbraked on both vans, so today it is legal by construction rather than by check | P2 | 2h | a towing check, which nothing performs | the trailer's own plate | partial |
| LOAD-6 | **A pack has to be allowed to turn things over, and nothing does.** Stated by the owner as an important aspect. `PackLayout` places every unit in its spec orientation, so a 2 m truss segment across a 1.38 m floor simply does not fit and is reported as overflow — it would lie along the bay without difficulty. Measured on the current pack: **7 of 32 assigned units are unplaceable**, and truss segments and scaffold towers are most of them. Two parts: the layout has to try a unit's six axis-aligned orientations, and `Orientation` already exists for exactly that on the rig side, so the geometry is not new. What is new is the choice of which orientation, which is a search rather than a rule | P1 | 4h | most of the 9 unplaceable units, and a pack that stops lying about long objects | — | open |
| LOAD-2 | Specs for the **two transporters**. Sepp's Fiat Ducato is **weighed**: 2500 kg with a full tank and driver, so 1000 kg of payload — **365 kg heavier than its own Zulassungsschein**, which knows nothing about a fit-out added after type approval. **Stefan's Movano has not been weighed** and its 1024 kg is the same class of paper figure that just proved 365 kg optimistic. What is left: **the Movano on a scale**, a tape measure inside both bays, and one look at Sepp's roof for L3H2 against L3H3 | P1 | 45m | the difference between a load plan and a fine | — | needs the owner |

#### LOAD-5 — the trailer, and why 750 kg is not enough

Where: `specs/vehicles/` for the trailer itself, a towing block on the vehicle, and a pairing rule the schema has no
way to express yet.

**Stated by the owner on 2026-08-17, before the purchase: 750 kg permitted gross, ca. 200 kg unladen.** So 550 kg of
payload, and the generator is 465 of it.

| | |
| --- | --- |
| trailer payload | 550 kg |
| the generator | 465 kg |
| **spare on the trailer** | **85 kg** |

**IT DOES NOT REACH ONE JOURNEY, WHICH IS THE POINT OF WRITING THIS DOWN NOW.** The whole load is 2698.5 kg — the
2233.5 kg of van gear plus the generator — against 2574 kg of capacity once the trailer is counted. Re-measured on
2026-09-02 that is **134.9 kg short**, which is more than the 124.5 kg the totals differ by because a bin pack
cannot fill every bin to the last kilogramme. The trailer is a net gain of only 85 kg, because it brings 550 kg of capacity and 465 kg of new
load with it: it is a generator trailer, not spare space.

**What would close the remaining 134.9 kg** is a trailer payload near 680 kg, which means roughly 900 to 1000 kg
gross and braked. Both vans tow that without difficulty — O1 is 3000 kg braked on the Ducato and 2500 on the Movano.
Where it stops being a towing question is the **combination mass**: 3500 + 750 is 4250 kg and 3500 + 1000 is 4500,
and trailer classes above 750 kg have licence implications that are worth checking before buying rather than after.
**750 kg is also exactly O2, the unbraked limit on both sets of papers**, which is likely why that size was chosen.

**Specced on the owner's instruction, before the purchase.** `specs/` is an inventory of what the collective has, so
`quantity: 1` for a trailer on order overstates it, and the spec says so in its own notes — that is the honest way to
carry the tension rather than to hide it. Correct it the day the trailer either arrives or does not.

**What the schema is actually missing**, and it is more than the trailer:

* **Towing capacity.** O1 and O2 are on both sets of papers and in no field of any spec. A trailer's laden mass has
  to be checked against what tows it, and nothing can express that today.
* **Which vehicle tows which trailer.** A pairing, not a property of either.
* **An open bed has no height**, and the trailer therefore states no `load_bay_m` at all. `load_bay_m` requires all
  three axes when present, so a side height would be read as a ceiling and the planner would refuse anything taller
  than the sides. No bay means no space answer, which is the honest outcome for a flatbed: its constraint is mass.
  What is weak is that nothing distinguishes "no bay because it is open" from "no bay because nobody measured it".

#### LOAD-2 — both vans documented, neither measured inside

Where: `specs/vehicles/`, and a row each in [docs/sources.md](docs/sources.md).

**Both sets of masses come off a registration document and both load bays are still catalogue figures.** That is the
whole state of it. The detail lives in the two spec headers and in `sources.md`; what follows is only what is left to
do and the one thing that would be easy to get wrong twice.

| vehicle | masses | payload | bay |
| --- | --- | --- | --- |
| `opel-movano-l4h3` | Zulassungsbescheinigung, G 2476 / F.2 3500 | **1024 kg**, derived | estimated, Master L4H3 RWD shell |
| `fiat-ducato-250-l3h2` | **weighbridge, 2500 kg** with full tank and driver | **1000 kg** | estimated, Ducato 250 L3H2 shell |

**What is left, and none of it is a keyboard job:**

1. **The Movano on a scale.** Its 1024 kg is field G of a registration document — the same class of figure that came
   out **365 kg light** on the van that got weighed. It is half the fleet payload and the error, if there is one,
   points at a fine rather than a wasted trip. One weighbridge ticket settles it.
2. **A tape measure inside both bays.** Length at the floor, width between the walls and between the arches, height
   under the roof and through the rear door aperture, and anything bolted in.
3. **One look at Sepp's roof**, which settles L3H2 against L3H3 and 236 mm of bay height. His papers state no
   dimensions at all, so even his outer box is a catalogue figure.

**THE TRAP THAT COST 365 KG, WRITTEN DOWN SO IT IS NOT WALKED INTO AGAIN.** A registration document is authoritative
about what a vehicle **may** weigh and merely historical about what it **does**. Field F.2 is the law and no scale
can supply it; field G is a figure from the day of type approval, and a van fitted out afterwards with shelving, a
bulkhead and a ply floor carries every kilogramme of that in no field of the document. **A payload needs both
sources.** And the two countries differ on the driver: Austrian `Eigengewicht` excludes one where German field `G`
includes 75 kg, so a mass has to be normalised before it enters a spec that derives payload as `F.2 − G`.

**Two seats in the Ducato, three in the Movano**, so a passenger comes off the payload. Both stored masses assume a
driver and nobody else, and the report has no way to know who else is coming.

## TOOL · tooling and CI

**Raised across the runtime cluster by the owner**, and the evidence for it was this release rather than a preference.
0.84.0 took the sweep from **450 written scenes to 483**, so every per-scene cost in the pipeline got 7 % worse on the
same afternoon that a 20-minute sweep and a two-hour suite made three separate measurements expensive to take. **Slow
tooling does not just cost time, it costs evidence**: the `slide_slack_m` defect shipped because looking at a render was
cheaper than running the thing that would have caught it, and the wrong runtime attribution in TOOL-9 was made twice
because re-measuring cost twenty minutes a go.

**0.85.0 answered most of it with parallelism rather than with any of these items.** Stated by the owner: kill the
running suite and make the thing fast. The sweep is forked across every core and went from 25 minutes to 1m58s, the
regenerate stage went the same way, **the JIT was believed to be turned on for another fifth and was not** (0.113.0: the buffer it needed is `PHP_INI_SYSTEM` and was 0, so the `ini_set` did nothing, and 0.114.0 then found the follow-up measurement wrong as well), and `build:all` renders one picture per scene
instead of eight. Nothing on this list was needed to get there, which is worth remembering the next time a runtime
item looks like the only way to a runtime answer.

What that changes here. **TOOL-9 drops to P3**: the symptom is gone and the CPU bill it is really about is unchanged,
so it now reads as a bill rather than as pain. **TOOL-10 stays P1** and is now the largest remaining saving by a
distance, because a stage that skips its work beats a stage that does it quickly on 28 cores. **TOOL-11 stays P1** for
the same reason. TOOL-12, TOOL-13 and TOOL-14 all shave CPU off a sweep that no longer hurts, so they are speculative
until somebody runs this on a small machine.

**0.107.0 added the static checks, and they are a gate rather than a report.** PHPStan at level 5, PHP-CS-Fixer on
the Symfony ruleset and Ruff over `blender/` and `tools/`, all three in the `static` job beside the suite. The level
was chosen from measured counts rather than from taste, all 64 errors at that level were fixed instead of baselined,
and what the pass found is the argument for it: twelve array shapes in `Stack.php` named a class that does not exist,
three more had gone stale against their own data, and a `Stack::nearest()` fallback would have thrown if the case it
guarded had ever occurred. See [docs/pipeline.md](docs/pipeline.md#static-checks).

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| TOOL-20 | **`full` cannot succeed as written and has never once succeeded.** It ran on 5, 6 and 7 September and was cancelled all three times with "the job has exceeded the maximum execution time of 6h0m0s", which is a hard GitHub ceiling rather than a setting. Measured on run 34099956636: `phpunit` 3 h 07 m 52 s and `full` 6 h 01 m 17 s, so about 548 minutes a night that bought nothing, roughly 1644 minutes across the three. The runner is the reason rather than the work: 23 m 20 s locally against 2 h 15 m 20 s for the same sampled `phpunit` on a runner is a factor of 5.8, and an un-sampled replay of 1374 scenes has nowhere near that headroom. **0.109.0 removed the nightly trigger**, so nothing is burning minutes now and the job is `workflow_dispatch` only. **0.113.0 gave it `timeout-minutes: 330`**, under GitHub's 360 ceiling on purpose, so the next overrun fails with a log instead of being cancelled without one — which is the whole difference between this row having three data points and having none. The JIT is on in that job too, and 0.114.0 re-measured it at 1.53x on this suite rather than at nothing, so it takes about a third off and still does not close the gap. What is left is a call between three options: shard it over a matrix by inventory folder, sample harder than `composer test` but far less than everything, or drop the job and run the full replay by hand before a release. **And the 5.8 factor no longer means what it did.** 0.114.0 put `ShippedScenesTest` on every core, so the local suite is 9 min 04 s on twenty-eight of them and the same work is 2489 scenes in 219 s on two — which is what a private runner has, and four is what a public one has. A local-to-runner ratio now depends on the core count on both sides, so any figure quoted for this job has to name the cores it was taken on. **The first push that ever executed says the same thing about `phpunit`, and it is the newest data point this row has.** Run `34759228276`, 2026-09-13: `phpunit` was cancelled at its own `timeout-minutes: 90` after 90 m 16 s, while `static` finished in 44 s and `secrets` in 14 s. That commit already carried 0.114.0's parallel `ShippedScenesTest` and the JIT, so neither is the missing lever. The runner was a private one, which is two cores; a public repository gets four, which is the cheapest remaining change and the reason this row and the root repository's go-public item now point at each other | P3 | 3h | a full replay that can actually run in CI, or the honesty of not pretending one does | decision | decision |
| TOOL-21 | **Every push cost 2 h 15 m of runner time, including a push that only touched a `.md` file. 0.113.0 took 26.12 min off that and the rest stands.** What went was the duplicated `bin/console scene:build --dry-run` step, measured on run 33846381809 as 26.12 min of a 135-minute job, compiling scenes `ShippedScenesTest` had already compiled to a stricter standard. A `concurrency` group now cancels a superseded push, which is the other half of 8 September's bill. **What is still open is the path filter**, and the original reasoning is unchanged: Measured on run 33846381809, the last push that started at all. `paths-ignore` on `**.md` and `docs/**` is the obvious lever and would have saved several of 8 September's pushes, but it is workflow-level and would take the `secrets` job with it — and a secret can land in a Markdown file as easily as in a PHP one. So the choice is between per-job path filters, which GitHub does not offer directly and which need a `dorny/paths-filter` step, and accepting the cost. **Still moot while billing is unresolved**: no run in any of the three repositories has started since the account's Actions minutes ran out, every one failing in 2 to 4 seconds with "recent account payments have failed or your spending limit needs to be increased". A public repository gets unlimited free minutes, which is why the root repo's go-public item now carries this. **The gate job the filter was thought to need is not needed yet, measured rather than assumed.** `repos/.../branches/main/protection` and `.../rulesets` both answer 403 with "Upgrade to GitHub Pro or make this repository public", so there are no required status checks on this repository at all and a skipped job cannot block a merge. That changes the moment it goes public and protection is switched on, so the trailing `always()` gate belongs in the same change as the branch rules rather than in this one. **What it buys has also shrunk**: 0.114.0 took the suite from about 18 minutes to 9 min 04 s locally, so the job this filter would skip is no longer a two-hour one | P3 | 1h 30m | a docs-only push that runs the secret scan and nothing else, once anything runs at all | TOOL-20 and the billing question | decision |
| TOOL-19 | **A regeneration commit buries every code change in it, and the scenes have to stay readable on the web.** The last three commits touched 1208, 937 and 4167 files, one of them at 514 463 insertions, because a cabinet rename rewrites the whole tree. Git's rename detection gives up above 2733 files and says so on every `git log --stat`. Gitignoring `scenes/generated/` is **not** the answer — stated by the owner, the point of committing them is that any scene can be read on GitHub without a checkout and a solve. So the fix has to keep the files and cut the churn, and which way is a call rather than work: separate every regeneration into its own commit as a rule, set `diff.renameLimit`, move the tree to its own repository behind a submodule pointer, or hold it on a branch of its own. **The cheap half is free**: a commit that is either code or regeneration, never both, costs nothing but discipline and is what makes a diff reviewable again. **P3 at most**, stated by the owner | P3 | 3h | a code diff somebody can actually read, and `git log --stat` that works | decision | decision |
| TOOL-18 | **PHPStan levels 6 to 8, which is 167 further errors and almost entirely annotations.** Level 5 is clean and enforced; the step up is missing generic array-shape and iterable annotations on internal helpers, measured at 231 errors at level 8 and 522 at max. Worth doing precisely because the level-5 pass proved the shapes go stale: three of them had lost a key the code was already reading. Do it a level at a time, because level 6 alone is the `iterable` and `array` annotations and is the bulk of the value | P3 | 5h | shapes that cannot drift from their data unnoticed, which is what caught `split`, `faults` and `LowEndBias` | — | open |
| TOOL-16 | **The last two extractions out of `SceneStackCommand`.** It came down from 2357 lines to 1374 in 0.100.0 and is 1442 today, the low-end axis having added 51 and the static pass 17 — seven collaborators, all green — and the two that are left are the ones whose methods run inside the `Parallel` fork and already take up to fifteen arguments: `build`/`solveEach`/`solveGroup`/`stackFor` into a `StackCandidate`, and the option parsing into a `StackRequest`. Moving them as they stand is parameter plumbing; doing it properly means resolving the options into a value object first, so the collaborator is constructed once in the parent and the fork copies it rather than reaching back for `$this`. **`SceneStackCommandTest::testTheSweepSaysTheSameThingInOneProcessAsInTwentyEight()` is the guard**: it asserts serial and parallel output are byte-identical. Named rather than numbered, because the 0.109.0 split moved it from line 1613 to 650. **Raised to P2 on the level-5 evidence**: the six-element task tuple had lost the `LowEndBias` from its annotation, which is exactly the drift a value object cannot suffer | P2 | 4h | a command under 800 lines, and a solve that can be tested without a command | — | open |
| TOOL-15 | **A scene the sweep collapses as a duplicate survives the replay**, which is what is left of TOOL-7 after 0.84.0 closed the rest of it. Dedup is a decision across the whole sweep — "the same rig as X" — and a replay is one file with nothing to compare against, so it rebuilds itself happily. **Measured: 6 of 489**, all six confirmed duplicates of a sibling that is also on disk, deleted by hand. Narrow and cosmetic next to what it was: nothing is lost, the rig exists under the other name, and the pipeline no longer aborts. The honest fix is the one TOOL-7 named, which is running the sweep as the stage instead of replaying files | P3 | 3h | a tree that matches a fresh `--force` sweep without `comm` and `rm` | — | measured |
| TOOL-10 | **`build:all`'s regenerate stage is the one stage with no staleness check**, and its own comment boasts that every stage skips what is already current. It replays all 483 recorded commands unconditionally, when a generated scene can only change if a spec or the solver did — both ordinary mtime inputs, and exactly the shape of the three checks `Staleness` already serves. **Re-measured after 0.85.0 and demoted on the number: the stage is 34 s, not the 6m40s it was ranked on.** 1h 30m of work for 34 s is no longer the cheapest thing in the file, and it was only ever cheapest because the stage was slow | P3 | 1h 30m | 34 s off every build where nothing changed | — | measured |
| TOOL-11 | **`scene:render` compiles the whole scene again**, rather than reading anything `scene:build` produced from the identical inventory a moment earlier. `build/plans/` holds `_library.json`, per-model plans and `_render-*.json`, but no compiled-scene plan a later stage reads back — so the natural place for the answer exists and is unused. Re-solving *from the file* is a stated design choice and this does not touch it: the specs demonstrably have not changed between two stages of one build | P1 | 2h 30m | one solve per scene per build instead of two, on every render pass | — | open |
| TOOL-12 | **`scene:stack` compiles each candidate twice**: once in the solve's seating check and again in `compileYaml()` immediately after. The two are not the same check — one judges a stack in isolation mid-search, the other judges the finished file and also catches a scene that will not parse — so this is a reuse question rather than a deletion | P2 | 1h 30m | unmeasured, and it is on the hot path of a 20-minute sweep | — | open |
| TOOL-13 | **The seating check's memo lives for one solve.** Across a 483-scene sweep every solve rebuilds it from empty, and identical arrangements recur across rigs that share cabinets. Worth a shared cache only once its hit rate across rigs is measured, because the key is a whole arrangement and most of them are unique | P3 | 1h | unmeasured, and possibly nothing | — | open |
| TOOL-14 | **Every command re-reads and re-parses every spec.** Negligible once, and the regenerate stage makes 483 invocations of it. Last of these by a distance: the cost is unmeasured, the fix touches every command's bootstrap, and TOOL-10 removes most of the invocations that make it matter | P3 | 2h | unmeasured | TOOL-10 first | open |
| TOOL-9 | **The wall clock is fixed and the CPU bill is not.** 0.85.0 forked the sweep across every core, so 25 minutes became 1m58s with byte-identical output, and that was the whole of the pain. What is left is the bill itself: 1206 candidates cost about 100 minutes of CPU between them, because GEO-12's ladder walks roughly fifty steps where the old cabinet count walked a dozen. **Measured rather than assumed**: the same sweep with the seating check short-circuited off is 20m14s serial, so GEO-11's compile adds only two to four minutes and the ladder is the whole of the rest. The lever is still pruning ladder steps that cannot change the answer, never dropping a dimension. **Demoted because the symptom is gone**, and it comes back the moment somebody runs this on a four-core laptop | P3 | 3h | a sweep that is cheap rather than merely quick, and the same speed on a small machine | — | partial |
| TOOL-3 | Run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job gated on `blender/` or `specs/` changing | P2 | 1h 30m | — | — | open |
| TOOL-2 | Asset previews are blank because they cannot render in background mode — generate them in the GUI once, or find a headless way | P3 | 1h | — | — | open |
| TOOL-1 | `inventory:import` — the first import was by hand because the source is several spreadsheets and CAD files and every number needed a provenance decision. Worth building when the gear list next grows; see [docs/inventory.md](docs/inventory.md) | P3 | 3h | — | — | open |
| TOOL-4 | GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so mostly packaging and metadata mapping | P3 | 3h | — | — | open |

#### TOOL-19 — the regeneration diff, and why gitignoring the tree is refused

Where: `scenes/generated/`, 2707 files and 18 MB of YAML at an average of 7192 bytes each.

**The requirement first, because it rules out the obvious fix.** The scenes are committed so that any one of them
can be opened on GitHub and read, with no checkout, no container and no solve. That is stated by the owner and it
is not negotiable, so every option below keeps the files in a repository somebody can browse.

**What it costs today**, measured on the three most recent regenerations:

| commit | files | insertions | deletions |
|---|---|---|---|
| 0.105.0 | 1208 | 39 454 | 40 193 |
| 0.104.2 | 937 | 71 715 | 70 611 |
| 0.104.0 | 4167 | 514 463 | 185 584 |

Each of those also carried real code: 19, 8 and 105 non-scene files respectively. A reviewer looking for the
`kicker-15` rename in 0.105.0 is looking for 5 spec files among 1189 scenes.

**And `.git` is not the problem.** The pack is 4.55 MiB for the whole history, because YAML this repetitive
compresses to almost nothing. Any argument for moving the tree has to rest on reviewability rather than on size.

**Four candidates, cheapest first.**

* **One commit per kind, as a rule.** A commit is either code or regeneration and never both. Costs nothing,
  needs no tooling, and fixes the one thing that actually hurts. It leaves `git blame` on a scene meaningless,
  which it is anyway.
* **`diff.renameLimit`.** Git skips exhaustive rename detection above 2733 files and prints a warning instead, so
  a folder rename reads as thousands of deletions and additions. One config line makes `git log --stat` work
  again. It does not reduce the diff, only the lie about it.
* **A submodule.** `scenes/generated/` becomes its own repository, still browsable on the web, and the parent
  records one pointer per regeneration. That gives an exact pairing between a code commit and the scenes it
  produced, which none of the others do. It is also the option that makes a fresh clone a two-step affair.
* **A branch of its own.** The generated tree lives on `scenes` and never on `main`. Browsable, and `main`'s
  history becomes pure code — but the pairing between code and scenes is then a convention in a commit message
  rather than something git enforces.

The first two are compatible with all of the others and with each other, which is why the effort above is 3h
rather than a range: it assumes the two cheap halves plus whichever structural one is chosen.

## SIG · signal chain, amplifiers and DSP

**Nothing in this repository knows what is plugged into what.** Every spec describes a box and its geometry; none of
them says what drives it, at what level, through which crossover, on which cable. That is a whole half of a PA and it
exists today only as a spreadsheet.

**The sheet has now been read**, on 2026-09-08, so SIG-1 is no longer blocked on reading it and the schema question it
waited on is answered below. What it holds turned out to be more than routing: it carries the wattage and impedance
figures that `SPEC-13` says no spec has, and it names the amplifier that `SPEC-8` could not identify.

**The arithmetic has now landed and one rig is written down.** `src/Signal/LimiterSetting.php` is the four-line limiter
calculation the sheet does in formulas, tested against the sheet's own figures, and `docs/signal-chain.md` records the
MARK Salzburg rig of 2026-09-19 as the first patch in the repository. **That settles how a rig is written down**: master
data in specs, one hand-written patch per event, and the table derived from the two. It does not settle SIG-3, because a
patch in prose still has no schema, and it does not settle SIG-1, because nothing generates the workbook yet.

**The 8x8 has since been on the bench**, on 2026-09-29, and on the scale its thresholds use it clips at about +12.5
rather than at the 18 dBu the sheet assumes. That is SIG-7. The bench also settles what the gain in SIG-6 would do on
the 8x8, and the rest of what it found is in `docs/signal-chain.md`.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SIG-2 | **Read the Drive from the repository instead of transcribing it by hand.** `docs/sources.md` cites Drive paths that a human opened and typed out, so nothing can tell whether a cited file changed. A read-only `rclone` remote `SdWa5:` already exists. **Two gotchas, both measured**: the remote is scoped to `team_drive 0AFDifygC0zQZUk9PVA`, and `Audio Routing.xlsx` is not in that shared drive but in the account's My Drive, so reaching it needs `--drive-team-drive ""`. See the section | P2 | 3h | every sourced figure becomes checkable against its source, rather than trusted | — | open |
| SIG-1 | **Import the Audio Routing sheet, once, and make the repository the master.** Six sheets, read 2026-09-08. Speakers, amplifiers, DSP routing, limiter thresholds and delays, as specs the repository owns and can diff. **The schema decision is answered**: repo as master with the workbook generated, not a two-way sync. **The limiter arithmetic is already done** in `src/Signal/LimiterSetting.php`, so what is left is reading the specs and writing the workbook. See the section | P2 | 9h | the half of the rig that is currently invisible, `SPEC-13`'s wattages for six groups, and the first answer to "does this rig even have enough amp channels" | SIG-3 | open |
| SIG-3 | **There is no home in the schema for a signal chain at all.** A `DeviceSpec` describes a box. Nothing can say which amplifier channel drives it, at what gain, behind which crossover, with how much delay. The sheet supplies all four per speaker group, so the shape is known rather than guessed: an amplifier needs channels with a gain and a selectable-gain list, a DSP needs a matrix of inputs against outputs, and an output needs a limiter threshold in dBu and a delay in ms. **`docs/signal-chain.md` now holds one real patch in prose**, so the fields a schema has to carry are no longer inferred from a spreadsheet alone. Whether an 8x8 threshold is dBu at all is SIG-7 | P2 | 4h | the field SIG-1 has nowhere to put its data | decision on where an amp channel lives, on the amp or on the speaker | decision |
| SIG-5 | **Measure the DCX2496's throughput latency and correct the Tecnare delay for it.** At MARK Salzburg the tops ran through the DCX and the subs did not, so the tops carry one extra converter pair that their 4.7 ms alignment figure does not account for. No source this repository has states it, so `docs/signal-chain.md` still says 4.7 | P2 | 45min | the tops and the subs are actually time-aligned, rather than aligned on paper | a measurement or the DCX manual | open |
| SIG-7 | **Pin the 8x8's threshold scale to dBu, and with it the ceiling the limiter table assumes.** On the bench the 8x8 clips at about +12.5 on that scale for a 100 Hz signal and at about +14.8 for a 1 kHz one, where `AmpLimiterCalc` puts its maximum at 18 dBu. Either the scale is dBu, the subs keep 0.70 dB of headroom rather than 6.20 and the lowest MM14K step with 6 dB left is 38 dB rather than 32, or the 8x8 reaches 18 dBu and every sub threshold holds the output 5.5 dB above its figure. A voltmeter on a 100 Hz sine at one output decides it. See `docs/signal-chain.md` | P2 | 30min | the subs' limiters hold at the voltage the table says, or the table stops promising 6 dB it does not have | a voltmeter | open |
| SIG-6 | **Settle what `DSP_Output_Gain_Set_dB` means.** In the sheet the column is a formula setting it equal to the limiter threshold. An output level is not a dBu threshold, so one of the two is mislabelled, and it was carried over unchanged rather than guessed at. **What such a gain does on the 8x8 is measured.** It acts before the limiter, so it drives the programme harder into the limiter and leaves the limit where the threshold is. What the sheet meant by it is still open | P3 | 30min | a generated workbook stops propagating a column nobody can read | the owner, or the DSP's manual | open |
| SIG-4 | **Write the workbook back to the Drive once the repository is master.** Deferred on purpose, because the `SdWa5:` remote is `scope = drive.readonly`, so an export needs a new OAuth scope and a re-auth. Until then SIG-1 writes the workbook into `build/` and a human uploads it, which needs no new permission | P4 | 2h | the humans keep the spreadsheet they actually work in, without it drifting from the specs | SIG-1 | open |

#### SIG-1 — the routing that lived in a spreadsheet

**The sheet has been read, so this block records what it holds rather than telling the next person to go and look.**
`Audio Routing.xlsx`, six sheets, read 2026-09-08 out of the rclone account's My Drive.

| Sheet | What it holds |
|-------|---------------|
| `Drivers` | Six speaker groups with driver list and count, nominal impedance, RMS wattage, passband, and a per-group delay. `Vspk_RMS_V` is a formula, `=SQRT(ohm × watt)` |
| `Amp_GainSelector` | Four amplifiers with a current gain in dB and the selectable-gain list the DIP switches offer |
| `AmpLimiterCalc` | One row per DSP output: which speaker group, which amplifier, the limiter threshold in dBu, ratio, attack, release, delay, and a headroom check against the DSP's maximum output |
| `AmpLimiterCalc Mode B` | The same table for a second routing, where the 8×8 feeds a partner DSP and a DCX2496 instead of driving the tops directly |
| `DSP_RoutingMatrix` | A 9×9 input-to-output matrix for the 8×8, and a 6×3 one for the DCX2496 |
| `DSP_ChannelLinkage` | Which input and output pairs are stereo-linked, for both devices |

So the hardware it describes is **two DSPs**, an 8×8 and a Behringer DCX2496, and **four amplifiers**, a
Tulun/Play/Prokustk TIP10000q, a GISEN MM14K, a GISEN M60D and a Behringer EP4000.

**The schema decision this row was blocked on is answered: the repository becomes master and the workbook becomes
generated output.** Stated by the owner, who asked whether a two-way sync was possible and said that otherwise the
Drive sheet is not needed as master. A two-way sync is the wrong tool regardless:

* A spreadsheet has no merge. Two-way means last-writer-wins per cell, and a real conflict is not resolved but lost.
* The `SdWa5:` remote is `scope = drive.readonly`, so any write needs a new OAuth scope and a re-auth. That is SIG-4.
* `Vspk_RMS_V` is a formula. A round trip either drops it or has to rebuild it.
* The Drive already shows the drift a two-way arrangement produces. **Four files all named
  `AmpLimiterCalc.csv` sit in one folder with three distinct sizes**, re-measured 2026-09-08 at 7700, 7718 and 7432
  bytes with the last appearing twice. So somebody is versioning by duplicating a filename, which is a worse kind of
  drift than redundant copies would be, and reconciling them is a reading job rather than a delete.

Repo-as-master is also better than a tie, because the limiter arithmetic stops being spreadsheet formulas and becomes
testable PHP.

**Three things it touches that the repository already has opinions about:**

* **`SPEC-13`'s power figure is no longer missing, it is sourced.** The sheet carries an RMS wattage and a nominal
  impedance for all six groups. SPEC-13 and SIG-1 should land together or the schema change happens twice.
* **`SPEC-8` is half answered.** It asks what "gisen md60" is, because it matches no product. The sheet says **GISEN
  M60D**. The EP4000-versus-Proline-3000 half stays open, because the sheet names an EP4000 without saying which rack
  holds it.
* **Provenance applies here exactly as it does to a dimension.** A crossover frequency is trivially easy to invent,
  impossible to check by looking at a render, and it silently decides what every cabinet is asked to do. And this sheet
  is a **working sheet rather than a datasheet**: it disagrees with `docs/sources.md` in four places, recorded there,
  and it disagrees with a CSV sitting beside it in the same Drive folder about amplifier gain. So an import must carry
  `provenance` per figure and must not quietly pick a side.
* **`nuke`'s "8 turbo subs 3000rms" is a rating covering two cabinet types together**, already recorded as
  unsplittable. A routing model has to be able to say that rather than force a number per box.

**Cabling is geometry as well as topology**, and this is still open. A cable run has a length, and a length depends on
where the cabinets stand, which this project already knows. The sheet does **not** hold cable lengths, so it settles
nothing here: whether SIG-1 models runs from placement, or records stated lengths and leaves the geometry alone, is
still a decision.

## ALN · alignment features

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| ALN-1 | Align scenes / stacks / rows at their **front faces** instead of their centres | P2 | 1h 30m | — | — | open |
| ALN-4 | Only the top tier can be spread — per-tier `align` picks *which* alignment the top tier uses, never how many tiers spread | P2 | — | — | decision | decision |
| ALN-2 | `align` on **nested** groups — scaling x would stretch the inner group's spacing with the outer one's; needs the level named. Same for `arc` and `line_array`, which own their spacing | P3 | 2h | — | — | known |
| ALN-3 | `stereo` splits into halves only — `floor(n/2)`; 2 + 2 out of six with two in the middle needs a `columns:` key | P3 | 1h | — | — | known |

**ALN sits below the P1 groups on its own top row.** It used to say here that ALN-4 is what SYM-3 waits on. **That is no
longer true**, and the reason is worth keeping: SYM-3 wanted to spread the subs, ALN-4 forbids spreading a load-bearing
tier, and the resolution is that SYM-3 never needed a tier spread at all. It needs sub *stacks* moved apart under a
shared tops row, which is a mechanism nobody has built — SWP-2's third value shares the tops **pool** and not the row,
measured in 0.96.0. ALN-4's rule stands untouched and the two items are now independent.

## SCN · scenes and renders

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SCN-1 | `full-rig-truss-three-quarter.png`: add the two SKRAMs; Gerüste replace the truss stands (remove those), turned 90° with fronts aligned to the system front; fly the Tecnare tops wide apart on the truss; 18Sound 2-ways near field beside them | P2 | 1h 15m | — | CVR-1 (flying) | open |
| SCN-2 | `everything-three-quarter.png`: remove unused truss stands, turn Gerüste 90°, align their fronts with the system front | P2 | 30m | — | — | open |
| SCN-8 | `end-fire-lattice-three-quarter.png`: vertical gaps between the subs; add the two SKRAMs, Achenbach, Tecnare and 2-way tops | P2 | 45m | — | — | open |
| SCN-9 | **Several scenes in one image, as an overview.** An option on `scene:render` that lays out more than one scene in a single picture, so a set can be compared at a glance instead of by opening 450 files one at a time. **The sweep is what makes this worth having**: an axis is only legible side by side, and today the only way to see what `pyramid` does against `free` is to flick between two windows | P3 | 4h | 450 generated scenes that nobody can currently take in | — | open |
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
| SPEC-14 | **No schema field holds electrical output.** Sepp's generator is 25 kVA on a Hatz 3M41 and both figures live in its `notes`, because the closest thing here is the `audio` section, which is about what a cabinet radiates. Worth a `power:` block once there is a second device that needs one — a distro, a second generator — and not before, since a field with one user is a field that will be wrong about the second | P3 | 1h 30m | somewhere for kVA to live that a report can read | a second power device | open |
| SPEC-15 | **A device has an erected size and a transport size and the schema has one field for both.** The 4 m truss lift transports at 1.75 m, its published figure, and is modelled at 4 m because that is what a rig render needs — so the packed convoy render showed a mast standing out of a trailer. Same for the 5 m scaffold tower. `dimensions_m` cannot answer both questions, and a pack that reads the erected size will always be wrong about anything that folds. Needs a `transport:` block that defaults to `dimensions_m` when a device does not fold | P1 | 2h | a pack that is right about every folding device, which is four of ours | — | open |
| SPEC-1 | Finish GMSS — five specs exist from the builder's own figures; measuring them is what is left | P2 | phys | provenance for 14 cabinets, 994 kg | — | partial |
| SPEC-5 | Measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec describes a design or a datasheet, not our build | P2 | phys | — | — | partial |
| SPEC-6 | `audio.drivers` cannot record a count without a size — `size_in` is required, so "2× unknown" has to omit the whole `audio` block | P2 | 45m | `mid-bass` keeps what is known | — | open |
| SPEC-8 | **Half answered by the Audio Routing sheet on 2026-09-08.** "gisen md60" is **GISEN M60D**, and the amplifier complement is TIP10000q, GISEN MM14K, GISEN M60D and Behringer EP4000. What is left is the fourth amp's identity in the rack: the sheet names an EP4000 but not which rack holds it, and EP4000 2U/16.6 kg against Proline 3000 3U/37 kg is 69 kg versus 79 per rack. Still a front-panel reading | P2 | phys | rack weights | — | partial |
| SPEC-11 | **Two 3 × 3 m tents** — new gear, no spec, no model. The 3 × 3 m footprint is what we call them by; make, model, eave and ridge height, packed size and weight are all unsourced, and a tent is a frame with a canopy rather than a box | P2 | phys | two items of gear that exist and are invisible to every scene and every pack | — | open |
| SPEC-12 | **Five Euro pallets** — new gear, no spec, no model. Footprint is the EPAL standard, so it can be sourced rather than measured, but ours need weighing and their condition and height class checking. They are what a riser is built from, so CVR-1 wants them modelled | P2 | phys | five items of gear, and a real answer to what a top stands on | — | open |
| SPEC-13 | **No speaker carries a power or sensitivity figure.** `DeviceSpec` has `weightKg`, `passband`, `coverage` and `drivers`, and `Driver` has size, type and count. Nothing anywhere says how much a cabinet takes or how loud it goes, so **GEO-14's "highest power" half cannot be built at all**. The schema change is small and the figures are the work, since every one needs provenance like every other spec number | P2 | 1h 30m + phys | GEO-14's second key, and the first honest answer to which sub is the main one | — | open |
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
2. **A telescoping mast shape.** `truss-tower-4m` and `tower-5m` are `shape: box` — a 0.203 m column, the folded
   base size. A crank stand is a nested mast on folding outriggers and neither is drawn. The outriggers matter most:
   unfolded they spread to 1.499 × 1.499 m, which is what must be kept clear at the feet, where the scene shows a column
   a fifth of that. A `mast` shape taking a section count and the folded/unfolded base fixes both.
3. **Truss tower feet are three** — noted against the mast shape, since that is where the base belongs. **Ambiguous and
   not acted on:** it could mean each stand has a three-leg base, where the spec's comment describes a square
   1.499 × 1.499 m outrigger spread, or that we own three stands rather than two. The first reading fits where it sits;
   the second changes `truss-tower-4m`'s quantity. One answer settles it.

## VIS · the long view

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| VIS-1 | **Make the whole thing an interactive real-time simulator.** Move the rig in a viewport and see the solve, the coverage and the stability answer as you go, instead of editing a scene file and waiting on Blender. A direction rather than a task — nothing waits on it and it may never be built | P5 | — | — | — | open |

**Recorded because it changes how the things above it are built, which is the only reason a P5 row is worth carrying.**
Two of today's decisions point straight at it and one points away:

* **Runtime stopped being free the moment this is on the table.** The project states outright that it is not a
  constraint, and that is right for a batch sweep. A 20-minute solve is not a simulator, so TOOL-9's ladder pruning and
  the TOOL-10 to TOOL-14 caching rows are the groundwork whether or not this is ever built. **0.85.0's parallelism is
  not that groundwork**, and this is where the difference bites: 28 cores fix a batch that a person waits for once and
  do nothing at all for a single solve that has to land inside a frame.
* **Re-solving from constraints is exactly the right shape for it.** A scene records what the rig has to satisfy rather
  than where the cabinets ended up, so a viewport that moves a stack asks the same solver the same question. Freezing
  solved tiers into the files would have had to be undone.
* **The seating check's cost is the warning.** One compile per accepted arrangement is affordable in a batch and is not
  affordable at 60 Hz, so GEO-11's scene-level half should be designed knowing that.

## INFO · facts worth keeping

| ID | Item | State |
|----|------|-------|
| INFO-2 | **The gmss inventories are deliberately left on the height rule**, while `ours`, `psl` and `innschleife` are ordered by `--order`. No order was ever stated for gmss, so there is nothing to order them by, and inventing one would put cabinets somewhere nobody asked for. Recorded when the ordering work closed in 0.104.2, because its row was the only place this said so | known |
| INFO-1 | **Our 4 m crank stands cannot clear a combined rig.** Every speaker in three stacks reaches 4.563 m as a pyramid and 5.628 m free, both above the 4 m the stands extend to, so a truss on `truss-tower-4m` sits below the tops it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead. Fine for our own 3.125 m rig, not for a combined one — worth knowing before hiring a stage. **Three-stack rigs are generated as of 0.77.0** — 12 of them, all `turned` or `mixed`, since a rolled sub wall is short enough for the band where the upright one is not | known |
