# TODO

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

Where that stands: bare `scene:stack` writes **396 scenes of ~1200 candidates**, every stack's sub/top transition
**aimed at 2.5 m** and its miss written on the file where it misses, every refusal named, and every shape rule stated in
**metres rather than in cabinet counts**. What is still refused is geometry and duplicates — grouped below by cause.

**How it got here is in the CHANGELOG rather than repeated here**, but two findings from it govern what to build next.
**The orientation axis is worth more than every other axis put together** — 11 scenes became 61 in 0.77.0, and
three-stack rigs became possible at all, because a rolled sub wall is short enough for the band where an upright one is
not. And **the borrowed-gear pairs beat every single owner**: `sdwa5-sepp` writes 50 scenes, more than `all` at 17.

**The target is stated once, in SWP-1**, as a six-axis cross product. All of it exists except the `impossible` half of
the last axis, which is CVR-5, and SWP-2 then adds a seventh axis.

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
  the tower alike. Nine of our ten cabinets are 0.45–0.66 m wide and `gmss-mid-bass` is 1.200 m, so a count stopped
  standing in for a width the day it arrived. They all live in `StackChecks::silhouetteProblem()`; anything new goes
  there too.

### The order to pick things up in

Settled with the owner, so a new session can act on it without re-deriving it:

**GEO-12, GEO-11's stack-local half, TOOL-6, TOOL-7 and TOOL-8 are done**, which is why the list now starts where it
does. TOOL-7 left a narrow remainder, filed as TOOL-15.

1. **LOAD-1 and LOAD-2**, the transporter half, raised to P1 across the group by the owner. It is the only group whose
   output somebody needs on the day, and the only place where getting it wrong is a legal problem rather than a bad
   render. **Both blockers have cleared**: the schema decision is made and Stefan's Zulassungsbescheinigung is in, so
   LOAD-1 is ordinary work and LOAD-2 is down to a tape measure inside two vans plus Sepp's papers. Write the schema
   and the Movano spec first, since neither waits on the measurement.
2. **TOOL-10**, the staleness check the regenerate stage has never had. 1h 30m against a **measured 6m40s off every
   build**, the same shape as three checks `Staleness` already serves, and the cheapest thing in the file by a
   distance. It is now the largest remaining runtime saving, because 0.85.0's parallelism made the stage quick and
   left it doing all of its work: **a stage that skips beats a stage that is fast**. Then **TOOL-11**, which is the
   same argument one stage later. The rest of the runtime cluster is speculative until somebody runs this on a
   machine with four cores.
3. **CVR-5**, the `impossible` axis. The last value of SWP-1's cross product, so it closes the stated goal, and it turns
   the rest of the refusals from sentences that scroll away into rigs somebody can look at. Read its three unsettled
   sub-questions first. **The fuse is at 600 and the sweep is at 483**, up from 450 in 0.84.0, so the headroom is 117
   candidates and shrinking. CVR-5 turns refusals into written scenes, so it is the item most likely to blow the fuse,
   and the fuse refuses outright rather than truncating.
4. **GEO-13**, gaps inside a row. Unblocked by GEO-12 and it is the same lever: a row-width budget wider than the
   cabinets need *is* a gapped row, so the search that landed already does half of it.
5. **SWP-2**, the system-separation axis. It multiplies the candidate count by up to three, so it goes after the two
   above rather than before them — and after TOOL-10, which is what makes a tripled sweep bearable.
6. **SWP-3**, sweep configuration and system grouping. After SWP-2, since a seventh axis is the thing that makes the
   enable/disable surface worth building.
7. **SYM-3 and GEO-9**, both raised to P1 by the owner. Placement breadth and the two missing shapes. SYM-3 falls out of
   SWP-2 almost entirely and is no longer blocked on anything: the evenness rule is **equal pitch**, settled, and its
   169 mm cost on the tightest tops row is measured.
8. **GEO-11's scene-level half** — aiming and cross-placement alignment, which is the genuinely circular part. The
   stack-local half shipped in 0.83.0 and unblocked GEO-9 and GEO-4 as far as it can; what is left needs the front face
   and the solve to stop depending on each other.

**GEO-14 arrived after this order was settled and has not been placed in it.** It is P1, it was stated by the owner and
its four blocking questions are answered: the shapes keep priority, height and acoustics are weighted metrics traded off
against each other, "central" is the rig's centre line for mono and each stack's own for stereo, and the fill key is
frequency. **The frequency quarter is done.** One sub-question is left before the rest can start, which is where the
weights live — a constant, a CLI option or a scene key. The power quarter waits on SPEC-13. Pick this up after GEO-12,
which is where the settled order still starts.

**CVR-1 is parked on the owner rather than on code** and is P4 for that reason.

## GEO · placement geometry

**One root cause across this group:** a row is positioned and spaced as if its cabinets were unrotated and centred, and
neighbouring stacks are spaced on nominal tier widths rather than on where the cabinets actually ended up. GEO-1, GEO-2,
GEO-3, GEO-6 and GEO-10 are done and their rows are deleted — the CHANGELOG has what each of them cost and bought.

**What is left does not run in a chain**, and every entry below records what was measured rather than what was expected.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| GEO-12 | **DONE in 0.83.0.** The fill's search knob is a row width in metres, divided by each cabinet's own width, walked as a union with the old cabinet count because **neither contains the other** — see the section. Ladder derived from the cabinets rather than written down. **396 → 450 scenes, 110 → 137 fully in band**, 94 new rigs, 21 consolidated into siblings, 19 refused | P1 | — | — | — | **done** |
| GEO-13 | **Gaps inside a row, chosen rather than constant.** `gap_m` is one uniform working gap everywhere and nothing can space a row out. The **checking** half is already built and load-bearing — see the section — so what is missing is the half that proposes the gaps. **Same lever as GEO-12**: a row-width budget wider than the cabinets need *is* a gapped row | P2 | 6h | rows that reach wider than their cabinet count allows, which is what a wide base and SYM-3's equal pitch both want | GEO-12 | open |
| GEO-14 | **The lowest and most powerful subs belong as low and as central as the rig allows.** Stated by the owner and **settled on four counts**: the shapes keep priority, height and acoustics are traded off as **differently weighted metrics** rather than one gating the other, "central" is the **rig's** centre line for `center` and `block` and **each stack's own** for `stereo`, and the fill key is frequency. The frequency quarter is **done**. What is left is the weighted objective and the two centring rules it scores. **Power is in no spec field** | P1 | 7h | the acoustic reason the fill order exists at all, and the first rule that reaches across stacks rather than inside one | SPEC-13 for power, and where the weights live | partial |
| GEO-11 | **Stack-local half DONE in 0.83.0**, scene-level half open. `StackSolver::solve()` takes a seating predicate and `SceneCompiler` supplies it, so the fill refuses an arrangement that overlaps *while it is still searching* rather than the whole rig being discarded at the end. It caught a rig being shipped with two cabinets inside each other. **What is left is aiming and cross-placement alignment**, which is the genuinely circular half: aiming needs the front face, the front face needs every placement, and every placement needs the solve | P1 | 6h | GEO-4, the rest of GEO-5 and GEO-9's tower | — | partial |
| GEO-9 | **`tower` and `mixed` shapes** — a wall of one width, and a tower base with a tapering top. **The cheap version is measured and does not work**: a bound in `ceilingFor()` cannot make a wall flush, so this is a change to `packedRows()`'s objective, which is GEO-11. **Both must be width rules**, stated by the owner, so they belong in `StackChecks::silhouetteProblem()` beside the other three | P1 | 8h | nothing measurable — it does **not** buy GEO-2, see there. A shape people build, which is worth having on its own | GEO-11 | measured |
| GEO-4 | Multi-stack row sliding. **Measured three times and still net negative** (10 scenes against 11). The lookahead bound is built and correct and does not help, because gravity decides support before the compiler decides final x. Waits on GEO-11 | P2 | 6h | 2 `LEFT OUT` cabinets, and `--per-owner` writing at all | GEO-11 | measured |
| GEO-5 | **Mostly closed by 0.81.0.** The cap is a width now, in `StackChecks::silhouetteProblem()`, with a tenth of a cabinet per side as the shoulder — so the false premise this entry was written about is gone. What is left is that the width rules refuse arrangements mid-search and the sweep got four times slower, which is GEO-11's shape again | P3 | 3h | — | GEO-11 | partial |
| GEO-8 | Stability is weighed per row, never for the **whole rig** — 2 200 kg on a 1.34 m base is compared against nothing | P3 | 1h 15m | — (wants reporting, not refusing) | — | open |
| GEO-7 | Only one tier per pass is flanked from below, and only if it fits a single row — the general case is untested | P3 | 1h | — | — | known |

#### GEO-12 — done, and the count turned out to be load-bearing too

**Shipped in 0.83.0. Kept for two results that cost real time to find.**

**A width does not contain a count, and the entry used to say it did.** The plan here read "`$perRow` becomes a row-width
budget", full stop. Built that way it lost 49 rigs, every one refused on bearing rather than on the search running out —
arrangements a count reaches and no width does, because a count says "the same number of every type" where a width says
"the same metres of every type". Proven rather than argued: the count ladder alone reproduces the old solver's answer
exactly, and no width does. So {@see RowBudget} walks **both**, as a union rather than a product, and a count is turned
into a per-device width at the point of use — which is what lets one parameter carry both. **This is CVR-8's lesson
recurring inside the item written about CVR-8**: a mechanism built for one reason was load-bearing for a second nobody
had written down.

**A row's budget is one number for the whole row, not one per device.** The first cut asked the budget per cabinet type
inside the packing loop, so a row full at 3.28 m for six IQ subs became roomy again the moment a 0.670 m wall bass was
considered, because six of *those* are 4.12 m. It pulled a wall bass into the bottom row and cost the GMSS pyramid its
whole arrangement. Device-independent where a row can hold several types, per-device where it holds one — the support
and pyramid ceilings stay per device, since both are allowances scaled by the cabinet on the end of the row.

**And one prediction that was simply wrong.** This section used to say `packTo()`'s seat check "goes away entirely" once
the pyramid hint was a width. It does not: the hint needs `PYRAMID_SHOULDER` to work as a width at all, and the seat
count is still what the search's own count dimension bounds a packed row by.

#### GEO-12 — the original entry, kept for the measurement that motivated it

Where: `StackSolver::fill()`'s `for ($perRow = $widest; $perRow >= 1; --$perRow)`, `perRowCap()`, and the twenty-odd
call sites that thread `$perRow` through the fill.

**Found by removing the width ladder in CVR-8, and it is not CVR-8's fault.** The ladder was a stage bound and it was
also, by accident, a *second search dimension* — and only the bound was the thing nobody had asked for.

A stage width in metres caps **each device's row count by that device's own cabinet width**: 4.40 m deals 7 Flexys and
3 mid-bass. `$perRow` caps every device to the same integer, so no setting of it reproduces that arrangement. Measured
against the 150 scenes the ladder used to write:

| | |
| --- | --- |
| scenes lost outright | **18** (6 of them ladder rescues at 4.40 and 5.20 m, 12 on the plain 3.70 m stage) |
| scenes kept but pushed outside the band | **28** — `stacked-all-2-free-mixed-column-center` went 2.381 → **4.173** m of subs |
| fully inside 2–3 m | 150 → **110** of 396 |
| baseline scenes whose recorded width was not 3.70 m | **70**, so the ladder was doing real work rather than decorating |

**What changes and what does not.** `$perRow` becomes a row-width budget in metres, walked coarse to fine, with the
unbounded case always in the search — so nothing is bounded by it and no rig can be refused for missing a budget.
**Counts stay where counts belong**, which is the owner's own reading: parity and symmetry (`flankingPairs`, the pairs
per side of a mixed bottom row, `mirrored`, `centred`, `share()`, `outerShare`, `splitRemainder`) and the pillar rule's
"a row of one", all of which are genuinely about how many cabinets there are.

**The ladder is derived from the cabinets, never written down.** That is the difference between this and the
`WIDTH_LADDER_M` that CVR-8 deleted, which was eight metre figures nobody could source. The candidate budgets are the
row widths the inventory can actually make: for each device and each `n` from its stage fit down to one,
`Tier::of($device, $n, $roll)->widthM($gap)`, collected, deduplicated within an epsilon and walked descending with the
unbounded case first. So the search still returns the widest arrangement on its first hit where there is no ceiling, and
a budget that no row can land on is never tried. It costs roughly the sum of the per-device counts in passes against
today's single count, which is a few times slower and therefore free, since runtime is not a constraint here.

**`perRowCap()` is the trap in this item and the naive fix is measured wrong.** Turning `min($perRow, $last->count())`
into a plain width cap at the row below was already tried, and its own docblock records what it cost: six Achenbachs are
3.700 m on six Flexys' 3.646 m, a 27 mm shoulder per side that the bearing rule allows four hundred of, and forbidding
it split them into two rows of three, whereupon the 1.84 m row could not carry the tops and a 2-way was dropped from the
rig. **A flush wall is not a V.** So the width form has to carry the same shoulder the real rule already does, which is
`below + 2 × PYRAMID_SHOULDER × cabinet width`, exactly what `StackChecks::silhouetteProblem()` allows. With that it is
the same hint it is today, expressed in the unit the rule is actually written in.

**Where it lands in `packTo()` is a simplification rather than a translation.** That loop already computes a width
ceiling per device — `min(ceilingFor(…), $budgetM)` — and a separate seat count from `perRowCap()`. Once the pyramid hint
is a width the two are one quantity, so the `$count + $take + 1 <= $seats` half of the inner condition goes away
entirely and the row is bounded by width alone.

**The scene records nothing new**, and the sweep's file names do not change, so this is the rare solver change that can
be checked by diffing the generated set against the previous one rather than by reading it.

The scene records nothing new. A budget is a search parameter rather than a constraint, the search is deterministic,
and `testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet` is what proves the rebuild still agrees.

#### GEO-13 — gaps inside a row

Where: `Tier::seats()` and `Tier::widthM()`, which take one `$gapM` and use it between every pair of neighbours.

**The checking half is finished and the proposing half does not exist.** Worth writing down in that order, because the
hard part is the one already built:

* **`Gravity::runs()`** splits a row into runs and `topFacesOf()` hands the tier above whatever faces those runs
  offer. A gapped row is already representable — it is a row of several runs with air between them.
* **`Gravity::MIN_BEARING = 1/3`** is the "does not fall through" rule, per cabinet.
* **`StackChecks::bearingProblems()`** catches both halves: nothing underneath at all, and touching rather than
  sitting on what carries it.
* **`Stability::tips()`** asks it of the row as one body, which no per-cabinet rule can.

So the condition — either the row above fits on the runs below, or its cabinets are wide enough to bridge the gaps and
still land on a third of themselves — is already enforced. What is missing is a solver that *chooses* the gaps.

**Do it as GEO-12's budget rather than as its own mechanism.** A row given more metres than its cabinets need is a
gapped row, so the two are one search and building them apart would build it twice. The open question is what the
scene records: a solved gap has to survive the re-solve, either by being reproduced deterministically like the budget
or by being written out per row, and `gap_m` today is a single scalar with no place to put a row.

**ALN-4 stays untouched.** That rule forbids *spreading a load-bearing tier* through the placement alignment, and it
forbids it for exactly this reason. A gap the fill chose and the bearing rules verified is a different object from a
tier stretched after the fact by a scalar that cannot see what stands on it.

#### GEO-14 — low and central, which is four rules and only one of them exists

Where: `SceneStackCommand::byFillOrder()`, `StackSolver::fill()`'s `widestFirst()` branch, `StackSolver::centred()`,
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

**The item this section was written about is gone.** The pyramid's cap was a cabinet count justified by "the cabinets are
all 0.45–0.66 m wide, so a row of `n` is about `n` cabinets across", which stopped being true when `gmss-mid-bass` at
1.200 m arrived. 0.81.0 made every shape rule a width in `StackChecks::silhouetteProblem()`, so the false premise is
gone and `perRowCap()`'s count survives only as a search hint.

**Kept because it was tried twice and reverted twice:** a *pure* width bound, with no shoulder allowance, is worse than
the count rule it replaces. Bounding a row by the row below gave 7 scenes against 11 with 2 interpenetrations, and
bounding by the base gave 8 with 3. A tolerance is not the lever either — 10 mm and 30 mm both gave the same 7 scenes as
no tolerance at all. What made 0.81.0's version work is the shoulder, `PYRAMID_SHOULDER = 0.1` of the outboard cabinet
per side, and both bounds on that number are measured in the CHANGELOG.

**Why the pure bound fails is structural, and it is the part that is still open.** A width bound makes rows narrower,
narrower rows make more of them, and a wall of many thin rows is a staircase that `Gravity` splits into runs inside each
other — measured at 127 mm. That is GEO-11, and it is also why the sweep got four times slower in 0.81.0: the width
rules refuse arrangements mid-search, so the solver walks far more of the space before it settles.

#### Kept from GEO-2, which is closed: splitting the tops row is the wrong lever

**The item is done and its row is deleted. This section stays for the negative results in it**, which are the sort of
thing that gets retried every six months by somebody who has not read them.

Where: `StackSolver::topRow()`; the rule already exists in `swallows()`.

**Splitting the tops across two rows was tried three times and never nets positive.** Both systems' eight tops come out
3.921 m in one row on a 2.420 m sub wall, so the outer turbo top at each end is over air entirely. Measured against a
baseline of 10 scenes written and 23 refusals across the three families:

| attempt | written | interpen | top-on-nothing | nothing-under | bearing |
|---|---|---|---|---|---|
| baseline | 10 | 0 | 12 | 6 | 5 |
| unbounded loop | 9 | 12 | 14 | 0 | 0 |
| capped at two rows | 9 | 2 | 16 | 4 | 4 |

The unbounded version cascaded, since each row becomes the next one's support and each budget is narrower: eight tops
became seven ever-thinner rows, a 7-tier rig grew to 13, and gravity broke those rows into runs overlapping by 203 mm.
Capping at two rows fixes the cascade and still loses a scene.

**AND THE SHAPE OF THE WALL IS NOT THE LEVER EITHER**, which was a previous version of this entry and is refuted by
measurement. `topRow()` puts every top in one row unconditionally, so the row is **3.921 m wide for the `all`
inventory's 8 tops at every stage width on the ladder from 2.00 m to 6.00 m**. Dead flat, because nothing about it
responds to the stage. So GEO-9 buys this nothing and re-measuring after it is pointless.

**What actually caused the family was two movers, and the lesson outlived the fix.** x came from an alignment solve and
z from a gravity seat, and nothing reconciled them — `align.outside` on a near-field fill in 0.74.0, and
`Stack::spreadApart()` on a stereo tops row in 0.74.1. Floating refusals went 6 → 3 → 1. **Any future code that moves a
finished run must call `Gravity::reseat()` afterwards**, and the reason it is easy to forget is that nothing downstream
notices: the compiler reads the run's stated height and the tier checks read bearings computed before the move. That is
written into `reseat()`'s own docblock, which is where it belongs.

**Two traps cleared on the way, both still live.** `CONTACT_TOLERANCE_M` is 0.001 m and the measured `dz` was exactly
`0.0000`, so the `z` gate is innocent whenever this shape recurs. And comparing *box* centres instead of positions
invents displacements that are not there, because a yawed trapezoid's box is inflated asymmetrically — two turbo-tops
measured 0.6125 m and 0.8263 m wide on a 0.45 m cabinet. Compare `liftedPosition()`, never `worldBox()`.

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
in `StackSolver::ceilingFor()`, bounding a row by `min($stack->maxWidthM, $supportM)`. It builds, it leaves the other
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
| SYM-3 | Stereo/mono placement breadth: subs mono where possible and spread only as far as the tops need; tops as wide and as evenly spaced as possible; symmetry wins ties. **No longer blocked on a decision.** The spreading half is SWP-2's shared tops row rather than a change to `Alignment`, and the evenness rule is **equal pitch**, settled by the owner. Both measured, see the section | P1 | 5h | broadest stereo image; the mono spread. Costs 169 mm on the tightest tops row, which only bites where a width is explicitly stated — see CVR-8 | SWP-2 | open |
| SYM-2 | Stack ordering cannot make the flanks *equal*, only place the tall ones | P3 | 1h | 3 of 13 multi-stack scenes are height-asymmetric | GEO-4 | partial |

#### SYM-3 — both blockers settled, and it is now work rather than a decision

The ask is "in a stereo scene the subs get spread out as wide as possible or necessary so that the tops can be set as
far apart as possible", and "in a mono scene the outermost tops as wide apart as possible but all tops spaced as evenly
as possible", with symmetry between and inside stacks optimised.

**Both blockers are settled.** The first turned out to be a different item, and the second was answered by the owner
after it was measured. Nothing here waits on a decision any more.

##### Resolved: spreading subs is SWP-2, not a new mechanism

**Two different things wear the word "spread", and only one of them was ever the problem.**

Spreading a sub *row* means air between cabinets inside one tier. That is what ALN-4 forbids and the ban is right rather
than conservative: `Gravity::MIN_BEARING` is `1/3`, so a cabinet must land on at least a third of its own width, and
stretching the row underneath hands it air instead. This is not a preference that can be switched on and preferred where
possible. It is a rig that falls over, so there is nothing to gain by making it optional.

Spreading sub *columns* means each sub stack stays solid and the stacks move apart. Nothing stands over air, because
every tier still sits on a whole stack. **This is what the ask actually wants, and half of it already exists**:
`--stacks=N` splits the rig and `--clearance` sets the air between the stacks, default 0.5 m.

**What is missing is that every stack carries its own tops row.** `StackSolver::topRow()` builds the tops from what is
left in *that stack*, so widening the clearance moves the subs and their tops together. The tops cannot be held in place
while the subs open underneath them, and a top cannot go wider than its own stack. That is exactly **SWP-2's middle
value, "subs apart, tops shared"**, arriving from the other direction. So this half is not a change to `Alignment` at
all, and **SYM-3 no longer waits on ALN-4.**

**One real constraint comes with it, and it is computable rather than a guess.** A tops row spanning two sub stacks has
cabinets over the gap, which is the same standing-on-air problem one level up. Every top must still land on a third of
its width, so a 0.450 m top may hang about 0.300 m off an edge and two tops meeting over the gap put a rough ceiling
near 0.600 m on the clearance. **That bound is what "only as far as the tops need" is solving against**, which is the
sentence the ask was always making and nothing could express.

##### Equal air against equal pitch, and why it was a real question

**"Evenly spaced" has two meanings and they stop agreeing the moment the cabinets differ in width.** Our tops are 0.450
(`gmss-turbo-top`), 0.4656 (`eighteensound-2way-15`) and 0.500 (`tecnare-m2122`), so equal *air* between boxes and equal
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

**What the autogeneration should produce, stated as one cross product.** Today's sweep is **~1200 candidates writing 150
scenes**; SWP-1's own target is **2646 candidates**, and SWP-2 adds a seventh axis on top of that.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SWP-1 | **The full sweep cross product** — the target set of autogenerated rigs, stated once. Six axes, order in the section below. Steps 1 to 5 are **done**: `column`, the orientation/mirror fold, the fuse, the owner combinations and the `V` shape. Only the `impossible` half is missing, which is CVR-5. SWP-2 then adds a seventh axis | P1 | 4h | 2646 candidates against today's ~1200 | CVR-5 | partial |
| SWP-2 | **How separate the sound systems stand, as a swept axis.** Three values: each system its own stack, the **subs** per system with the **tops shared**, and everything pooled. Stated by the owner. Today only the third exists in the output, so a rig where the systems stand apart is not generated at all | P1 | 10h | **0 of 150 scenes** put the systems in their own stacks today, and the middle value is a rig nothing in the code can currently build | — | open |
| SWP-3 | **Sweep configuration** — turn each axis value on and off individually, **group sound systems so the grouping overrides `owner`**, and let each axis be a **subfolder** instead of a field in the file name, nested in the name's own order with the value in one place or the other but never both. All three stated by the owner | P1 | 12h | control over an output that is ~1200 candidates and growing, a directory somebody can navigate at 396 files and rising, and the grouping is what CVR-3's discriminator question was really asking | decision on the discriminator, see CVR-3 | open |

#### SWP-1 — the cross product

```
(1 / 2 / 3 stacks)
  x (every non-empty combination of the owners: sdwa5, gmss, sepp)
  x (center, block, stereo)
  x (pyramid, free, V)
  x (7 orientation/mirror pairs)
  x (possible, impossible)            <- impossible = emitted anyway, offenders painted red
```

**A seventh axis is stated in SWP-2** and is deliberately not folded into the count above, because SWP-1's arithmetic is
quoted in several places and the two should not be confused. Written out it is `x (pooled, subs apart, systems apart)`,
which multiplies everything below by up to three.

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

**Count.** `3 x 7 x 3 x 3 x 7 x 2` = **2646 candidates**, roughly twice today's ~1200. The gap is the `impossible` half
and nothing else.

The `impossible` half is **CVR-5**: rather than refusing a rig that cannot stand, emit it with every offending cabinet
coloured red. It is not a variant of a possible rig but its complement — a candidate is one or the other — so as an axis
it doubles the count rather than multiplying the written output. **The two axes added in 0.77.0 and 0.78.0 made this much
larger**: today's refusals would become written scenes, where before them there were 55. A share of them are duplicates
rather than refusals and would not be written. `DEFAULT_MAX_SCENES` was raised to **600** in 0.81.0 for exactly this, so
the first job in CVR-5 is to re-count the refusals against that headroom rather than to assume it is enough.

**The largest single family of refusals was one message and CVR-7 has taken all of it**: 551 of 1056, the tops firing
below or above head height. Painting cabinets red never answered that one — there is nothing wrong with those rigs,
they are simply short or tall — so they stopped being refusals rather than becoming red renders. **What CVR-5 inherits
is the 810 that are left**, and they are geometry and duplicates, which is exactly the kind a render can answer.

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
4. **DONE in 0.78.0 — owner combinations**, CVR-3. Seven subsets rather than three groups, plus an `--owner` narrowing
   that does *not* collapse the sweep. **804 candidates writing 149 scenes against 426 and 61**, and no previously
   shipped scene changed by a byte. `sepp` alone writes nothing, as CVR-1 predicted, and the **borrowed-gear pairs are
   the biggest inventory in the sweep**: `sdwa5-sepp` writes 50 scenes, more than any single owner and more than `all`.
   `DEFAULT_MAX_SCENES` was raised 80 → 200 here, which is the deliberate raise step 3 exists for.
5. **DONE in 0.81.0 — `V` as a stated shape**, and it forced the thing GEO-5 had been circling. The V built as a
   *count* rule produced 21 stacks that narrow against 8 that widen, because raising a seat count only permits a wider
   row where a row's width is decided by what cabinets are left. **Stated by the owner: the pyramid, the V and the
   tower are all width rules.** All three now live in `StackChecks::silhouetteProblem()`, in metres.

##### What exists, per axis

| axis | values | state |
| --- | --- | --- |
| stacks | 1, 2, 3 | **done** — hard-coded `[1, 2, 3]` in `SceneStackCommand` |
| alignment | `center`, `block`, `stereo` | **done** — `LayoutMode`. `center` and `block` are both mono |
| shape | `pyramid`, `free`, `v` | **done** in 0.81.0 — all three are width rules in `StackChecks::silhouetteProblem()` |
| orientation | `upright`, `turned`, `mixed` | **done** in 0.77.0 — `StackOrientation`, swept as pairs with the mirror style |
| mirror style | `alternate`, `centred`, `column` | **done** in 0.75.0 |
| inventory | all 7 combinations of `sdwa5`, `gmss`, `sepp` | **done** in 0.78.0 — `ownerCombinations()`, narrowed by `--owner` |
| system separation | pooled / subs apart, tops shared / systems apart | **missing entirely**, and it is SWP-2. Only the first is generated |

##### The values still missing

**The whole system-separation axis**, which is SWP-2 below. Everything else in the six is either shipped or is CVR-5.

**The guess about owner combinations was wrong in a useful direction**, which is why the measurement stays. The entry
read "this axis does not pay off on its own", on the grounds that `sepp` alone cannot produce anything. That half is
still true and `sepp` writes nothing. What it missed is that the *pairs* are where the payoff is:

| inventory | scenes |
| --- | --- |
| `sdwa5-sepp` | **50** |
| `gmss-sdwa5` | 25 |
| `sdwa5` | 22 |
| `gmss` | 21 |
| `all` | 17 |
| `gmss-sepp` | 13 |
| `sepp` | 0 |

`sepp`'s eight cabinets cannot stand alone and are excellent *under* somebody else's tops, which is precisely the
borrowing the repository was built to support. The pair beats every single owner and beats `all`, because 41 cabinets in
one rig is two sound systems and 25 is a gig.

##### What every axis after this one should expect

Distilled from the ten test failures the orientation axis was first reverted over, none of which was the axis being
wrong. The CHANGELOG has what each step cost and bought; this is only the part that recurs.

* **Tests that name a rig but not the new axis will fail on arithmetic**, counting scenes across a dimension that just
  grew and asserting 1 where 3 or 9 is right. Six did. The fix is to pin the axis in the test, exactly as those tests
  already pin `--shape=pyramid`. Read it as arithmetic rather than as a regression. It happened again in 0.81.0.
* **A new axis finds latent test bugs rather than causing them.** `assertStringNotContainsString('mirror:')` passed only
  while nothing was ever rolled, because `roll_mirror: 90.0` contains those seven characters.
* **A count asserted flat across every scene will break the first time one gear list produces two rigs.** Assert the
  count *less whatever the file names as left out*, so a silent drop still fails and an explained refusal does not.

##### Two rules established on the way, both still live

**A file in `scenes/generated/` that the sweep does not produce is stale, not precious.** Settled by the user when ten
hand-invoked `-turned-` scenes collided with the orientation axis' ids; all ten were deleted in 0.76.0. `comm -23`
between the directory listing and the sweep's ids is the whole check.

**Runtime is not a constraint, and it is going to get much worse.** The suite went 88 seconds → 9 minutes at the
orientation axis and 9 → 24 at the width rules, because a candidate is a solve plus a compile plus an interpenetration
sweep. Do not spend effort optimising it unless something else asks for that.

#### SWP-2 — how separate the sound systems stand

Where: `SceneStackCommand::groups()` and `isSweep()`, `SplitMode`, and a new axis in `SweepAxes`.

**Every generated scene pools the gear.** Verified rather than assumed: 0 of the 150 files carry `--per-owner` in their
recorded command, and none states `--split`, so every one of them shares every device type out across every stack. A rig
where the two systems stand as two systems is not in the output at all.

The three values the owner asked for:

| value | what stands where | today |
| --- | --- | --- |
| **pooled** | every stack gets a share of every cabinet, whoever owns it | the only thing generated |
| **subs apart, tops shared** | each system's subs make their own stack; the tops come from the whole pool | **nothing in the code can build this** |
| **systems apart** | each system is its own stack, subs and tops | `--per-owner` builds it but is excluded from the sweep |

**Two existing things are close and are not it.** `--per-owner` is the third value, but `isSweep()` treats naming it as
collapsing the sweep to a single point, so it never appears in generated output. And `SplitMode::ByType` gives whole
device types to one stack each, which *looks* like separation and is not: it balances by how much row each type is worth
and pays no attention to who owns the cabinet, so it will happily put one system's sub under another's top.

**The middle value is the real work.** It breaks an assumption the code holds everywhere: that a stack's tops come from
the same pool its subs came from. `groups()` returns one id list per stack and the solver builds the whole stack from it,
so "these subs, those tops" has no way to be expressed. Decide first whether that is a second list on the group or a
second pass that deals the tops after the sub stacks are solved — the second is the one that can see the sub wall
heights, which is what the tops row has to sit on.

**Watch the count.** This is a seventh axis at three values, so it multiplies the candidate count by up to three on top
of SWP-1's target. `DEFAULT_MAX_SCENES` is at 600 and will bind. Do it after CVR-7 rather than before, or the same rigs
get counted twice.

**SYM-3's spreading half is this item**, which is worth knowing while designing the middle value. "Spread the subs only
as far as the tops need" is a solve for the clearance between sub stacks under a shared tops row, and the bound is the
bearing rule one level up: a top over the gap must still land on a third of its width, which puts a rough ceiling near
0.600 m on the clearance for a 0.450 m top. Build the shared tops row so that clearance is solvable rather than fixed,
and SYM-3 becomes a ranking question instead of a mechanism.

#### SWP-3 — configuring the sweep, and grouping systems

Where: `SweepAxes`, `SceneStackCommand`'s option list, and whatever CVR-3's discriminator turns out to be.

Three asks, all from the owner, and they are one item because they are the same surface.

**1. Every axis value on and off individually.** Part of this exists and part of it does not, and the difference matters
before anybody estimates it. `--shape`, `--align`, `--orientation`, `--mirror-style` and `--owner` are repeatable, so
naming a subset already narrows that axis — `--shape=pyramid --shape=v` is `free` switched off today. What is missing is
that `--from`, `--stacks` and `--per-owner` **collapse the sweep entirely** rather than narrowing one axis, so there is
no way to say "sweep everything, but only two stacks". `isSweep()` is the line that decides it.

**2. Grouping sound systems, overriding `owner`.** `sdwa5` and `sepp` are two owners and are sometimes one system, and
today there is no way to say so: `--owner=sdwa5 --owner=sepp` narrows the sweep to that one combined inventory rather
than telling it to treat the two as one system across the whole sweep. **This is CVR-3's question arriving with an
answer attached** — that section asked what the right discriminator is and refused to invent a `system:` field to serve a
layout. A grouping stated at invocation time rather than in the specs is a different answer from a spec field, and it is
the one the owner asked for.

**3. Each axis may become a subfolder, nested in the same order the file name uses.** Stated by the owner. The sweep
writes 396 files into one flat directory and the count only grows, so the configuration decides per axis whether it is
a directory level or a field in the name.

**The rule is that an axis value appears in exactly one of the two, never in both.** A file under
`free/turned/column/` is not called `stacked-all-2-free-turned-column-center` as well — it is `stacked-all-2-center`.
Otherwise every path states the same fact twice and a rename has two places to go wrong.

The nesting order is the name's order, which is the order the sweep itself nests: **rig (owners and stack count),
shape, orientation, mirror style, alignment.** Naming a directory level moves that field out of the name and leaves the
remaining fields in their existing order, so switching a level on or off is a move rather than a rewrite.

Three things this has to answer, and two of them are already load-bearing elsewhere:

* **`SceneLoader::files()` is recursive and an id is the file's basename**, so directories cost the loader nothing
  today. What they do cost is uniqueness: `free/…/stacked-all-2-center` and `v/…/stacked-all-2-center` are two files
  with **the same id**, and the whole repository keys scenes by id. Either the id keeps the axis values the path
  dropped, which contradicts the rule above, or ids stop being basenames.
* **`build:all` replays each scene's recorded command and prunes what the sweep no longer writes.** Both walk paths, so
  a layout change is a change to the prune's idea of stale — and the prune is the thing that once deleted 742 files.
* **`--id` is what a replay reconstructs the path from**, so whatever the layout is has to be derivable from the
  recorded line alone, without reading the file it is in.

**The three interact, which is why they are one item.** A grouping changes what `ownerCombinations()` enumerates, the
enable/disable surface is where a grouping would be stated, and the layout is per axis — so all three are the same
per-axis configuration seen three ways. Building them separately means building that surface three times.

**Decide before code:** whether the configuration lives on the command line, in a config file, or both. Repeatable
options are fine for four axes and stop being fine at seven with groupings and a per-axis layout flag; a file is
testable and is one more thing to keep in step with `--help`.

## CVR · coverage, and the inputs that were secretly gates

**Most of the sweep's candidates used to be refused, and the largest share was not geometry.** It was that two inputs
stated as preferences were enforced as gates: the height band, which is CVR-7, and the stage width, which is CVR-8.
Both were settled by the owner in the same direction, neither was a claim that the number is wrong, and **both are
built**. The sweep went from 150 scenes of 1206 candidates to **396**, and the 551 refusals that were band misses are
gone. What is left refused is geometry and duplicates.

**The one thing they cost is filed as GEO-12**, because it belongs to the solver rather than to coverage: the width
ladder was also a search dimension, and without it 18 scenes are lost and 28 more sit further from the aim.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| CVR-7 | **The sub/top interface height is an optimisation problem, not a hard constraint.** Stated by the owner. Tops below or above head height are **not** a reason to refuse a rig or to call a scene invalid. The bounds are terms in the ranking beside `target_sub_height_m` rather than gates, and the miss is reported on the terminal and on the file. **Built** | P1 | 6h | **551 refusals**, measured — 411 walls too short and 140 too tall. The sweep writes **396 scenes against 150** | — | **done** |
| CVR-8 | **An unstated width must not limit anything.** Stated by the owner. `--max-width` has no default, `WIDTH_LADDER_M` and `buildInBand()` are deleted, and no generated scene carries a `max_width_m`. **Built with CVR-7**, since removing the bound alone degenerates a rig to one row. **It cost a search dimension nobody had noticed, which is GEO-12** | P1 | 4h | every refusal that was a stage the rig does not fit. Widest row 4.89 → 9.376 m | CVR-7 | **done** |
| CVR-5 | **Emit the impossible rigs instead of refusing them, with every offending cabinet coloured red.** A refusal is a sentence in a terminal that scrolls away; a render shows *which* cabinet and *why* | P1 | 5h | the refusals that survive CVR-7 become lookable-at, and the diagnosis stops being prose. The fuse is already at 600 for it | CVR-7 to avoid duplicated work | open |
| CVR-6 | **Derive a smaller rig from one that fails** — drop cabinets until the same inventory stands up, and write that as its own scene beside the refusal | P2 | 4h | a buildable scene for every rig that currently produces none, `all-1` included | CVR-5 | open |
| CVR-4 | Port the ~13 real event setups from Drive (`…/setups/`, 2D SVG) into scene files | P3 | 4h | "actually used in praxis", which nothing covers today | — | open |
| CVR-2 | Decide whether the sweep keeps offering `free` where the pyramid already solves — it misses the ceiling far more often, inherently | P3 | 15m | fewer named refusals, or more scenes | decision | decision |
| CVR-1 | **A top may stand on something that is not a cabinet** — riser, stand or fly point. A rig too small for a 2 m sub wall is a real rig, not an impossible one. **Deferred by the owner**, and CVR-7 removes the urgency entirely: a short wall stops being a refusal, so this becomes a modelling feature rather than a fix. It still waits on what we actually own | P4 | 6h | nothing once CVR-7 lands — the 258 refusals it was written for are CVR-7's | decision | decision |

#### CVR-7 and CVR-8 — what they turned out to be

Built together, and the three questions each of them was filed with are answered rather than open.

**What is a gate now and what is not.** The two height bounds went soft and **everything about whether the rig stands
up stayed hard** — bearing, support, the pillar rule, the silhouette rules and interpenetration. That line was the
working assumption and it survived contact: a cabinet hanging off its support cannot be built at any price, and tops a
bit low can. It is worth restating whenever a new check is written, because "is this a rig" and "is this a good rig"
are two questions and only the first may refuse.

**Where the miss is written, and it needed no new surface.** `StackChecks::boundsProblems()` has reported both misses
as warnings since long before this, and `StackSceneWriter::header()` has always written every warning into the file.
So the scene already said it and the gate was throwing the scene away anyway. What was added is a `noted` line on the
terminal for whoever ran the sweep and is not going to open 396 files.

**What the ranking does with a miss.** `heightCost()` — distance from the target plus `OUT_OF_BAND_PENALTY` times the
part of the miss that falls outside the band. **Inert at the default band and kept anyway**, the same argument
`StackSolver::fill` already makes about the same numbers: 2.5 m is the midpoint of 2–3 m, so every in-band wall is
already nearer the aim than every out-of-band one. Without it a stated `--max-sub-height` would have no say in
anything at the command level, which is a bound that can neither refuse nor rank and therefore means nothing.

**What a written scene records: nothing.** The key is omitted and the unbounded solve is deterministic, which is the
first of the two options CVR-8 was filed with. `testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet` is
what holds it, exactly as predicted.

**What the ladder was for afterwards: nothing, and that was the wrong answer.** `WIDTH_LADDER_M` and `buildInBand()`
are deleted, on the argument that a width is either stated — in which case deviating from it is disobeying it — or
absent, in which case there is nothing to deviate from. The argument is right about the *bound* and wrong about what
else the ladder was doing, which is **GEO-12**. Recorded here rather than there as well, because the shape of the
mistake generalises: a mechanism built for one reason can be load-bearing for a second nobody wrote down, and deleting
it on the first reason alone will not show up until the counts are compared.

**The measurement, before and after, on the bare sweep:**

| | before | after |
| --- | --- | --- |
| scenes written | 150 | **396** |
| candidates skipped | 1056 | 810 |
| of those, band refusals | **551** — 411 short, 140 too tall | **0** |
| widest row anywhere | 4.89 m | 9.376 m |
| `max_width_m` in a generated file | 150 | 0 |
| fully inside 2–3 m | 150 | 110, and see GEO-12 |

`DEFAULT_MAX_SCENES` is 600 and 396 fits, so the fuse did not bind. CVR-5 is the next thing to need that room.

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
`gmss-tower-5m`, 5 `truss-f33-2m` and 1 `gmss-truss-9m`, SCN-1 wants the Tecnare tops flown, and INFO-1 records that the
4 m towers cannot clear a combined rig.

#### CVR-3 — `owner` is standing in for "system"

**The owner has since answered this, and the answer is SWP-3.** Grouping is stated at invocation time — `sdwa5` and
`sepp` swept as one system when that is the gig — rather than as a field in the specs. So the discriminator stays
`owner` in the data and a grouping sits on top of it. What is below was written before that and is kept because it is
still the reason a spec field would have been wrong.

`owner` is not quite the right discriminator: the repository deliberately supports borrowing gear between owners, so a
rig can legitimately mix them. It separates the two systems in practice, and inventing a `system:` field to serve a
sweep would be inventing a property to serve a layout. **Decide the discriminator before writing code.**

**This item got considerably more valuable, and the note that used to be here got it backwards.** It read "the one-stack
mixed rig now fails on the tops row and one top floating at 4.196 m, that is GEO-2, not a `--from` problem". The width
arithmetic in GEO-2 says the reverse: 8 tops in one row are 3.921 m, wider than the widest wall a 3.80 m stage can carry,
so `all-1` cannot be made to stand by any geometry. It is a `--from` problem, and narrowing the default retires **12 of
GEO-2's 22 refusals** without touching the solver.

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

> **IN FLIGHT — LOAD-1 and LOAD-2 are built and green, and are waiting for 0.84.0 to commit before being applied.**
> They live in a sandbox copy of the repo at
> `<session scratchpad>/load-wt`, kept out of the working tree only because the full suite was
> reading it at the time. **187 tests pass there, 8 of them new** (`tests/Spec/VehicleTest.php`).
>
> What is in it: `Category::Vehicle` with `van`/`trailer` and `Category::producesAModel()`; `src/Spec/Vehicle.php`;
> the `?Vehicle $vehicle` field on `DeviceSpec`; `SpecValidator::validateVehicle()`; vehicles excluded from the
> catalog's weight and volume totals but kept in its provenance tally and reported as a `fleet`;
> `models:build` and `library:build` skipping them; `specs/vehicles/opel-movano-l4h3.yaml` and
> `specs/vehicles/sepp-transporter-l3h2.yaml`; `docs/spec-format.md`, `docs/sources.md` and a regenerated
> `docs/catalog.md`. **Still owed when it lands:** a `CHANGELOG.md` section, a `README.md` line and a version bump to
> 0.85.0. Delete this block once it is applied.

**Both non-code halves have moved and what is left of them is one measurement.** LOAD-1's schema decision is made, so it
is ordinary work now. LOAD-2 has Stefan's Zulassungsbescheinigung, so what remains there is **the inside of both vans
with a tape measure** — no registration document states a load bay — plus the whole of Sepp's vehicle. LOAD-3 and LOAD-4
were always ordinary work and now only wait on the bay.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| LOAD-1 | Where a **vehicle** belongs in the schema — a transporter is not a speaker, and a load bay is not a bounding box. **Decided: `specs/`, a real `vehicle` category, a `vehicle:` block for the permitted gross and the load bay, payload derived.** Ordinary work now | P1 | 1h 30m | LOAD-3 and LOAD-4, which cannot start without it | — | decided |
| LOAD-2 | Specs for the **two transporters**. **Stefan's is documented**: Opel Movano L4H3, 6.848 × 2.070 × 2.808 m, 2476 kg in service against 3500 kg permitted, so **1024 kg of payload**, all off the Zulassungsbescheinigung. **Sepp's is still unknown** — L3H2 or L3H3, probably a Peugeot, ex-Deutsche-Post. **Neither load bay is measured and no registration document states one**, so the tape measure is what is left | P1 | phys | provenance for the only two objects a pack is ever checked against | LOAD-1 | partial |
| LOAD-3 | **Pack** the whole inventory or a stated subset onto one transporter, or across both — this is bin packing, so a named heuristic rather than an implied optimal solver | P1 | 6h | a load plan, which nothing produces today | LOAD-1, LOAD-2 | open |
| LOAD-4 | **Report space and weight separately** — a pack can fit the bay and still be overloaded, and a payload overrun is a legal problem rather than an inconvenience | P1 | 2h | — | LOAD-3 | open |

#### LOAD-1 — a vehicle is not a speaker

Where: `src/Spec/Category.php`, `src/Spec/DeviceSpec.php`, `src/Spec/SpecValidator.php`, `docs/spec-format.md`.

**Question 1 is settled by the owner: a transporter belongs in `specs/`.** Stated as "of course a transporter belongs
in specs, where else". So there is no separate directory and no second loader, and everything a spec already gets —
`owner`, `provenance`, a row in `sources.md`, the catalog's weight and volume totals — a vehicle gets for free.

That decides question 2 with it. `other/vehicle` was only attractive because it needed no schema change, and a load bay
and a legal payload need validated fields whatever the category is, so the change is owed either way. It is a real
`vehicle` case with `van` and `trailer` subtypes.

**Question 3 is the one left, and it is a design call rather than a question for the owner.** `geometry.dimensions_m`
is the true outer bounding box, which for a van is the wrong number entirely — what a pack reads is the **inside**:
length, width, width between the wheel arches, height under the roof and height through the door aperture, which is
usually the binding one. That is a second dimension set on one device and the schema has nothing like it. Payload is
the same shape of problem seen from the mass side: `physical.weight_kg` is what the object weighs, and a van also
carries what it is *allowed* to weigh.

The shape that follows from what the Zulassungsbescheinigung actually states:

* `physical.weight_kg` stays what it always was and takes **field G**, the mass of the vehicle in service. No new
  field, and the catalog's totals keep meaning what they mean.
* A new `vehicle:` block holds `permitted_gross_kg` (**field F.2**) and the `load_bay_m` set. **The payload is
  derived** as `F.2 − G` rather than stored, because both halves are citable to a numbered field on a document and
  their difference is not.
* `load_bay_m` is **optional**, and a vehicle without one is refused by the packer by name rather than by the
  validator. Stefan's van can be specified today from its papers and cannot be packed into until somebody measures the
  inside, and that distinction is worth being able to express.

Also part of the call: `owner`. The vans are personal property, and `owner` already carries `sdwa5`, `sepp` and `gmss`.
Both men are sdwa5 members, so Sepp's is `sepp` and Stefan's is `sdwa5` unless a personal value is wanted. CVR-3 is the
same discriminator problem seen from the rig side.

#### LOAD-2 — Stefan's van is documented, Sepp's is not

Where: `specs/` (a `vehicle` category, per LOAD-1), and a row each in [docs/sources.md](docs/sources.md).

##### Stefan's van, from the Zulassungsbescheinigung itself

Both parts of the document were supplied by the owner on 2026-08-16, so these are `document` provenance rather than
estimates. **The masses and the outer box are settled and the load bay is not** — the inside of a van appears on no
registration document, so item 3 below still needs the tape measure.

**Opel Movano, Kastenwagen, body L4H3** — the body designation is the owner's, and the two figures on the document
agree with it: 6848 mm is Movano L4 and the H3 roof is the only one near 2.8 m.

| what | value | where it is stated |
| --- | --- | --- |
| make, model | Opel Movano | D.1 `OPEL`, D.3 `MOVANO` |
| type, variant, version | MR / F6YF / S2BFC3 | D.2 |
| vehicle class | N1, `Fz.z.Gü.bef. b. 3,5 t`, body `BB` Van | J, (5), (4) |
| **length** | **6.848 m** | 18 |
| **width** | **2.070 m** | 19 — body only, mirrors not included |
| **height** | **2.792–2.808 m** | 20, stated as a range |
| **mass in service** | **2476 kg** | G — includes the 75 kg driver by EU definition |
| **permitted gross** | **3500 kg** | F.2, and F.1 technically permitted is the same |
| **payload** | **1024 kg** | derived, `F.2 − G`, driver already counted |
| axle loads | 1850 kg front, 2300 kg rear | 7.1 / 7.2 |
| towing | 2500 kg braked, 750 kg unbraked | O.1 / O.2 |
| seats | 3 | S.1 |
| first registered | 25.04.2016 | B |

**1024 kg of payload, against a library that weighs 3493.7 kg.** That is the first real number this group has ever had
and it reframes what LOAD-3 is for. Three comparisons, all off `bin/console catalog` and the scene reports:

| load | weight | volume | Movano loads |
| --- | --- | --- | --- |
| everything in `specs/` | 3493.7 kg | 26.588 m³ | 3.4 |
| **what actually travels, GMSS excluded** | **2238.5 kg** | **20.490 m³** | **2.2** |
| what sdwa5 itself owns | 1856.5 kg | 18.645 m³ | 1.8 |
| what Sepp owns | 382.0 kg | 1.845 m³ | 0.4 |
| GMSS, **not carried in these two vans** | 1255.2 kg | 6.098 m³ | — |
| the `all` rig, 38 cabinets | 2660.0 kg | — | 2.6 |

**Even the collective's own gear does not fit in one van**, so the packer's question was never "does it fit". It is
which subset goes in which load, and with two vans of different sizes that is the two-bin problem LOAD-3 already
describes. The 1024 kg also has the driver counted already, since field G includes 75 kg, but a second person on board
comes straight off it.

**Stated by the owner: GMSS is not carried in these two vans.** That takes 1255.2 kg and 6.098 m³ out of the problem and
leaves a load of **2238.5 kg in 20.490 m³** against a fleet payload of **2224 kg** and roughly **29.2 m³ of bay**, so the
two vans are **14.5 kg short of one trip** and have volume to spare. The whole packing problem now turns on 0.6 % of the
load.

**Which means the deciding number is the one nobody has looked up.** The 2224 kg is 1024 documented plus Sepp's
**estimated** 1200, and that estimate is a guess twice over, since both the mass in service and the permitted gross were
assumed rather than read. Field F.2 and field G off Sepp's Zulassungsbescheinigung move the answer between "one trip" and
"two", and no amount of packing cleverness substitutes for reading them. That promotes the paperwork above the code:
**LOAD-3 should not be written until LOAD-2's second vehicle is real.**

**The volume headroom is thinner than 70 % fill sounds.** 20.490 m³ is the sum of gross bounding boxes, so it already
counts the air around every wedge and every horn flare, but it counts no aisle, no strapping and no stacking rule. The
Movano's floor is also 1.380 m between the arches against a 1.765 m bay, so the width a cabinet actually gets depends on
how high it sits.

**Neither van does its own owner's gear alone.** sdwa5 owns 1856.5 kg in 18.645 m³ against the Movano's 1024 kg and
15.844 m³, over on both counts, so the split is genuinely across the two vehicles rather than one van per owner. That is
the two-bin problem LOAD-3 describes, and with the GMSS gear gone it is a **single-trip** two-bin problem with a hard
weight constraint and a soft volume one.

**Nothing identifying goes in the spec file.** The VIN, the plate and the owner's home address are all on the document
and none of them is a packing input, so the spec carries make, model, body, dimensions and masses and stops there.

##### The load bays, estimated on the owner's instruction

**Stated by the owner: estimate both bays and Sepp's vehicle for now.** So these go in as `provenance: estimated` with a
`sources.md` row naming where they came from, which is the mechanism this repository already has for exactly this — all
21 GMSS cabinets are `estimated` and say so. What stays forbidden is an *unlabelled* number.

**An estimate against a legal limit is rounded in the safe direction, and that is not the same as rounding to the
nearest.** A payload estimated low and a bay estimated small make the packer refuse loads that would in fact have
fitted, which costs a second trip. The other direction costs a fine and an insurance claim. So where a figure is a
range or a guess, the pessimistic end goes in the spec.

| | Stefan, Movano L4H3 | Sepp, Boxer L3H2 (assumed) |
| --- | --- | --- |
| load length | 4383 mm | 3705 mm |
| load width | 1765 mm | 1870 mm |
| between wheel arches | 1380 mm | 1422 mm |
| load height | 2048 mm | 1932 mm |
| load volume | 15.8 m³ | 13 m³ |

Two things to know about those columns before anybody trusts them:

* **The Movano figures are the rear-wheel-drive ones**, and that is deliberate rather than incidental. The L4 body is
  not offered front-wheel drive, and the RWD floor sits higher, so the H3 bay is **2048 mm rather than the 2144 mm** a
  front-wheel-drive H3 gets. The registration document agrees with the drivetrain: 1850 kg on the front axle against
  2300 kg on the rear. Taking the FWD figure would have invented 96 mm of headroom.
* **Sepp's column assumes the smaller of the two roofs he might have.** L3H2 and L3H3 share every dimension but the
  height, 1932 mm against 2168 mm, so the assumption costs 236 mm and no more. It is the safe end of his own
  uncertainty and it is the one number in the table that a single look at the vehicle would settle.

Both are manufacturer catalogue figures for the body, so they describe a bare van. **They do not know about ply lining,
a bulkhead, load rails, a shelf or anything an ex-Post fleet fitted**, which is item 4 below and is why the tape measure
still matters. Sepp's payload stays open, because a catalogue kerb weight for a fleet-specification vehicle is the exact
mistake the GMSS reconstruction is the worked example of.

##### Sepp's van, still unknown

**L3H2 or L3H3, probably a Peugeot, an ex-Deutsche-Post vehicle** — the owner's own description, and explicitly
uncertain in both the height and the make. So nothing about it may be written into a spec yet. What settles it is the
same document Stefan's van produced, and an ex-Post Boxer is likely to differ from the catalogue anyway, since those
were bought to a fleet specification.

**No dimension, payload or weight for either transporter may be invented, and that is not a style preference here.**
Every number in this repository points at a row in `sources.md`, and the GMSS reconstruction is the worked example of
what happens when it does not: a photograph got a cabinet's depth wrong by 205 mm and its weight by 139 kg, and the
whole apparatus had to be thrown away when the builder finally stated his figures. A guessed payload is worse than that,
because a wrong cabinet weight makes a bad render and a wrong payload makes an overloaded van.

**No dimension, payload or weight for either transporter may be invented, and that is not a style preference here.**
Every number in this repository points at a row in `sources.md`, and the GMSS reconstruction is the worked example of
what happens when it does not: a photograph got a cabinet's depth wrong by 205 mm and its weight by 139 kg, and the
whole apparatus had to be thrown away when the builder finally stated his figures. A guessed payload is worse than that,
because a wrong cabinet weight makes a bad render and a wrong payload makes an overloaded van.

What is needed, per vehicle:

1. **Make and model**, which pins the class and nothing else. **Stefan's: done.** Sepp's: open.
2. **The legal payload**, from the **Zulassungsbescheinigung**. Field F.2 is the permitted gross weight and G is the mass
   in service, and the payload is the difference. That is the citable number, and it is stated on a document rather than
   derived from a brochure figure for a different trim level. **Stefan's: done, 1024 kg.** Sepp's: open.
3. **The internal load bay**, with a tape measure. Length at the floor, width between the walls and width between the
   wheel arches, height under the roof and height through the door aperture. **Open for both, and it is now the only
   thing standing between Stefan's van and a spec that can be packed into.** No registration document states it.
4. Whether either vehicle has anything fixed in the bay that never comes out. Open for both.

Until 3 exists, a pack that reports "it fits" is reporting nothing, however good the payload figure is.

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

**Two bins, one trip, and the weight is what binds.** GMSS does not travel in these vans, so the load is 2238.5 kg in
20.490 m³ against 2224 kg of payload and about 29.2 m³ of bay. Volume has 30 % of headroom and weight is 14.5 kg short,
which fixes what the heuristic is optimising for. Ordering by volume into the deepest free space solves the constraint
that is not binding, so the ordering has to be **heaviest first with weight as the refusal**, and volume is the check
that runs second. **Sepp's payload is estimated, so the 14.5 kg deficit is inside the error bar of its own input** and
the honest output is a load plan with the provenance of the limit printed beside the verdict, exactly as LOAD-4 requires.
An instance this tight also makes a first-fit result nearly meaningless: two bins at 99.3 % combined utilisation is a
region where the ordering decides feasibility, so the report says which ordering was used and what it left over.

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
2238.5 kg of `estimated` cabinets is a planning aid, not a clearance.

**And on this fleet both sides of the comparison are estimates.** The load is unmeasured to a cabinet, and half the
payload is Sepp's assumed 1200 kg, so a verdict of "14.5 kg over" is a statement about two guesses rather than about a
van. The report therefore prints the provenance of the limit as well as of the load, and a verdict this close to the
line reads as "cannot be decided from the data" rather than as a refusal.

## TOOL · tooling and CI

**Raised across the runtime cluster by the owner**, and the evidence for it was this release rather than a preference.
0.84.0 took the sweep from **450 written scenes to 483**, so every per-scene cost in the pipeline got 7 % worse on the
same afternoon that a 20-minute sweep and a two-hour suite made three separate measurements expensive to take. **Slow
tooling does not just cost time, it costs evidence**: the `slide_slack_m` defect shipped because looking at a render was
cheaper than running the thing that would have caught it, and the wrong runtime attribution in TOOL-9 was made twice
because re-measuring cost twenty minutes a go.

**0.85.0 answered most of it with parallelism rather than with any of these items.** Stated by the owner: kill the
running suite and make the thing fast. The sweep is forked across every core and went from 25 minutes to 1m58s, the
regenerate stage went the same way, JIT was turned on for another fifth, and `build:all` renders one picture per scene
instead of eight. Nothing on this list was needed to get there, which is worth remembering the next time a runtime
item looks like the only way to a runtime answer.

What that changes here. **TOOL-9 drops to P3**: the symptom is gone and the CPU bill it is really about is unchanged,
so it now reads as a bill rather than as pain. **TOOL-10 stays P1** and is now the largest remaining saving by a
distance, because a stage that skips its work beats a stage that does it quickly on 28 cores. **TOOL-11 stays P1** for
the same reason. TOOL-12, TOOL-13 and TOOL-14 all shave CPU off a sweep that no longer hurts, so they are speculative
until somebody runs this on a small machine.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| TOOL-15 | **A scene the sweep collapses as a duplicate survives the replay**, which is what is left of TOOL-7 after 0.84.0 closed the rest of it. Dedup is a decision across the whole sweep — "the same rig as X" — and a replay is one file with nothing to compare against, so it rebuilds itself happily. **Measured: 6 of 489**, all six confirmed duplicates of a sibling that is also on disk, deleted by hand. Narrow and cosmetic next to what it was: nothing is lost, the rig exists under the other name, and the pipeline no longer aborts. The honest fix is the one TOOL-7 named, which is running the sweep as the stage instead of replaying files | P3 | 3h | a tree that matches a fresh `--force` sweep without `comm` and `rm` | — | measured |
| TOOL-10 | **`build:all`'s regenerate stage is the one stage with no staleness check**, and its own comment boasts that every stage skips what is already current. It replays all 483 recorded commands unconditionally, **measured at 6m40s**, when a generated scene can only change if a spec or the solver did — both ordinary mtime inputs, and exactly the shape of the three checks `Staleness` already serves. Simple and the biggest single saving in the pipeline | P1 | 1h 30m | 6m40s off every build where nothing changed | — | measured |
| TOOL-11 | **`scene:render` compiles the whole scene again**, rather than reading anything `scene:build` produced from the identical inventory a moment earlier. `build/plans/` holds `_library.json`, per-model plans and `_render-*.json`, but no compiled-scene plan a later stage reads back — so the natural place for the answer exists and is unused. Re-solving *from the file* is a stated design choice and this does not touch it: the specs demonstrably have not changed between two stages of one build | P1 | 2h 30m | one solve per scene per build instead of two, on every render pass | — | open |
| TOOL-12 | **`scene:stack` compiles each candidate twice**: once in the solve's seating check and again in `compileYaml()` immediately after. The two are not the same check — one judges a stack in isolation mid-search, the other judges the finished file and also catches a scene that will not parse — so this is a reuse question rather than a deletion | P2 | 1h 30m | unmeasured, and it is on the hot path of a 20-minute sweep | — | open |
| TOOL-13 | **The seating check's memo lives for one solve.** Across a 483-scene sweep every solve rebuilds it from empty, and identical arrangements recur across rigs that share cabinets. Worth a shared cache only once its hit rate across rigs is measured, because the key is a whole arrangement and most of them are unique | P3 | 1h | unmeasured, and possibly nothing | — | open |
| TOOL-14 | **Every command re-reads and re-parses every spec.** Negligible once, and the regenerate stage makes 483 invocations of it. Last of these by a distance: the cost is unmeasured, the fix touches every command's bootstrap, and TOOL-10 removes most of the invocations that make it matter | P3 | 2h | unmeasured | TOOL-10 first | open |
| TOOL-9 | **The wall clock is fixed and the CPU bill is not.** 0.85.0 forked the sweep across every core, so 25 minutes became 1m58s with byte-identical output, and that was the whole of the pain. What is left is the bill itself: 1206 candidates cost about 100 minutes of CPU between them, because GEO-12's ladder walks roughly fifty steps where the old cabinet count walked a dozen. **Measured rather than assumed**: the same sweep with the seating check short-circuited off is 20m14s serial, so GEO-11's compile adds only two to four minutes and the ladder is the whole of the rest. The lever is still pruning ladder steps that cannot change the answer, never dropping a dimension. **Demoted because the symptom is gone**, and it comes back the moment somebody runs this on a four-core laptop | P3 | 3h | a sweep that is cheap rather than merely quick, and the same speed on a small machine | — | half-done |
| TOOL-3 | Run `tools/check-glb.py` in CI — needs Blender in the workflow, so probably a separate job gated on `blender/` or `specs/` changing | P2 | 1h 30m | — | — | open |
| TOOL-2 | Asset previews are blank because they cannot render in background mode — generate them in the GUI once, or find a headless way | P3 | 1h | — | — | open |
| TOOL-1 | `inventory:import` — the first import was by hand because the source is several spreadsheets and CAD files and every number needed a provenance decision. Worth building when the gear list next grows; see [docs/inventory.md](docs/inventory.md) | P3 | 3h | — | — | open |
| TOOL-4 | GDTF/MVR export once the standard covers audio devices — the models are already glTF, which is what GDTF embeds, so mostly packaging and metadata mapping | P3 | 3h | — | — | open |

#### TOOL-6 — the one stage that writes into the repository and is never run

Where: `BuildAllCommand::regenerate()` and `prune()`, and `BuildAllCommandTest`.

**The cause was a code defect, and that is why this is P1 rather than P2.** The removed `regenerateTurned()` pass
detected an already-turned rig by looking for `--roll-mirror=` in the recorded command line. After 0.77.0 a turned rig
records `--orientation=turned` instead, so the pass no longer recognised its own output and turned the turned rigs
again, producing ids like `stacked-sdwa5-sepp-2-turned-turned-column-center`. **149 scenes in, 290 out**, plus two
committed files silently rewritten. Nothing about the environment, the invocation or anything running alongside it
contributed. A stale string comparison against a format that had moved is the whole of it.

**What was fixed and what was not.** The offending pass was deleted in 0.78.0 and
`testReplayingEveryRecordedCommandRewritesExactlyTheSameSceneSet` was added, which pins the contract the stage rests on
— replaying all recorded commands rewrites exactly those files, byte for byte, in 18 seconds. That is the contract and
not the stage. **`regenerate()` itself is still only ever run with `--dry-run`**, so the same class of defect would land
the same way: silently, into the working tree, found by `git status` rather than by the suite.

**The obstacle is `prune()`.** The stage deletes generated files the sweep no longer produces, so a test that fails
midway could take real renders with it. Covering it needs a way to run the stage without pruning, which is one flag or
one seam and is the actual work here. The rest is a fixture directory and an assertion on what came out.

#### TOOL-8 — what covering the slide turned up

**A repair could win by throwing a cabinet away**, and the two halves of the rule that forbids it sat one line apart.
`Gravity::resolve()` scores a candidate repair with `worstBearing()`, and a run standing on nothing reports a bearing of
**1.0** — so "walked clean off its support" scored as perfect and took the slot. `carriedBearing()` exists for exactly
this and reads `on` rather than the bearing, and the *lookahead onto the tier above* already used it. The row's own
score did not.

Measured on the case that surfaced it: `2× gmss-nuke + 1× gmss-mid-bass` on two wall basses is carried at 8.5 % centred,
and the slide replaced it with an arrangement carrying a run on nothing.

**Effectively inert on the shipped set, and this time it is isolated rather than asserted.** 450 scenes and 313 band
notes either way. The release around it took the sweep from 450 to 483, which looked at first like this line and is
not: a worktree at 0.83.0 writes **450**, the same worktree with **only this line changed also writes 450**, and the
full 0.84.0 tree writes 483. The 33 belong to `slide_slack_m` — `scene:stack` compiles each candidate's YAML before
accepting it, and that compile disagreed with the solve that produced it. So this is a latent defect fixed at no
cost, exactly as recorded.

**The lesson is about the attribution and not about the fix.** A release-level count says nothing about which change
caused it, and the tempting answer — "the only line that touches the solver" — was wrong here even though the
reasoning was sound. A worktree, one line, one sweep is 25 minutes and settles it. Worth the 25 minutes whenever a
number is going into a changelog, because the alternative is a plausible attribution nobody can check later.

**Sliding never changes the answer on a flat support.** Found by searching every sub pair against every one, two and
three cabinet support: not one case. Every case where the slide helps has a *stepped* support underneath, where a
cabinet perches on the edge of the taller run — the measured example is an Achenbach carried at 3.2 % on a wall bass's
edge, landing at 98.5 % on the Flexy beside it after a 30 mm slide. That is why the mechanism is invisible until a mixed
row appears, and it is worth knowing before searching for a case again.

## SIG · signal chain, amplifiers and DSP

**Nothing in this repository knows what is plugged into what.** Every spec describes a box and its geometry; none of
them says what drives it, at what level, through which crossover, on which cable. That is a whole half of a PA and it
exists today only as a spreadsheet.

| ID | Item | Prio | Effort | Buys | Needs | State |
|----|------|------|--------|------|-------|-------|
| SIG-1 | **Bring the Audio Routing sheet into the project.** Speakers, amplifiers and DSP, their settings, and the cabling between them, as specs the repository owns rather than a Google table nobody can diff. See the section | P2 | 12h | the half of the rig that is currently invisible, and the first answer to "does this rig even have enough amp channels" | decision on the schema | open |

#### SIG-1 — the routing that lives in a spreadsheet

**Read the sheet before designing anything.** What it actually holds decides the schema, and guessing at that is how a
spec format ends up with the wrong shape. The obvious pieces are a device's inputs and outputs, an amplifier's channels
and their power, a DSP's crossover and delay and gain per output, and a cable's ends and its length — but which of
those the sheet records, and which it only implies, is not something to invent from here.

**Three things it plainly touches that the repository already has opinions about:**

* **`SPEC-13`'s power figure.** No speaker carries a wattage or a sensitivity, and an amplifier assignment is the one
  thing that would make those numbers load-bearing rather than decorative. The two items should land together or the
  first one lands twice.
* **Provenance applies here exactly as it does to a dimension.** A crossover frequency is trivially easy to invent,
  impossible to check by looking at a render, and it silently decides what every cabinet is asked to do. Same rule as
  `audio.passband_hz`, same reason.
* **`gmss-nuke`'s "8 turbo subs 3000rms" is a rating covering two cabinet types together**, which is already recorded
  as unsplittable. A routing model has to be able to say that rather than force a number per box.

**Cabling is geometry as well as topology**, and this is the part worth deciding early. A cable run has a length, and a
length depends on where the cabinets stand — which this project already knows. Whether SIG-1 models that, or records
stated lengths and leaves the geometry alone, is the schema decision the row is blocked on.

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
shared tops row, which is SWP-2. ALN-4's rule stands untouched and the two items are now independent.

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
| SPEC-1 | Finish GMSS — five specs exist from the builder's own figures; measuring them is what is left | P2 | phys | provenance for 14 cabinets, 994 kg | — | partial |
| SPEC-5 | Measure the cabinets — [docs/measuring.md](docs/measuring.md). Every spec describes a design or a datasheet, not our build | P2 | phys | — | — | partial |
| SPEC-6 | `audio.drivers` cannot record a count without a size — `size_in` is required, so "2× unknown" has to omit the whole `audio` block | P2 | 45m | `gmss-mid-bass` keeps what is known | — | open |
| SPEC-8 | Two amplifier facts, both settled by reading the front panels: the fourth amp (EP4000 2U/16.6 kg vs Proline 3000 3U/37 kg — 69 kg vs 79 per rack), and "gisen md60", which matches no product | P2 | phys | rack weights | — | open |
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
2. **A telescoping mast shape.** `truss-tower-4m` and `gmss-tower-5m` are `shape: box` — a 0.203 m column, the folded
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
| INFO-1 | **Our 4 m crank stands cannot clear a combined rig.** Every speaker in three stacks reaches 4.563 m as a pyramid and 5.628 m free, both above the 4 m the stands extend to, so a truss on `truss-tower-4m` sits below the tops it spans. `scenes/everything.yaml` uses GMSS's 5.2 m towers instead. Fine for our own 3.125 m rig, not for a combined one — worth knowing before hiring a stage. **Three-stack rigs are generated as of 0.77.0** — 12 of them, all `turned` or `mixed`, since a rolled sub wall is short enough for the band where the upright one is not | known |
