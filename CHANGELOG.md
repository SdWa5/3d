# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.31.1] - 2026-08-11

### Removed

- `scenes/aimed-close.yaml` — the full rig aimed at a 2 m / 1 m focus instead of 10 m / 1.8 m
- `scenes/driver-detail.yaml` — a contact sheet of three baffles

## [0.31.0] - 2026-08-11

### Added

- **`bin/console scene:stack` — scene files written from constraints instead of typed out.** `stack:` already
  solved a rig, but somebody had to write the scene first; this is the step before that. One scene per
  arrangement that works, and a stated reason for every one it left out
- How many scenes you get is a parameter rather than a decision baked in: `--align` (repeatable),
  `--subs=mixed|beside|both`, `--from`, the `stack:` bounds, `--at`, `--id`. `--align=block --subs=mixed` is
  exactly one scene; the default is three. Going over `--max-scenes` is **refused, not truncated** — a silent
  cap reads as "that is every possibility" when it is not
- Every candidate is **compiled before it is written**, arrangements that resolve to the same rig are written
  once, and an existing scene is never clobbered without `--force`. Output is a `stack:` block rather than the
  expanded tiers, so a generated scene re-solves every build instead of freezing today's answer
- The default `--from` is every speaker, **widest first within each band**, subs before tops — the order that
  stacks without inverting. It produces a cleaner pyramid than listing the Flexys first

### Changed

- **A tier may be mixed, and the bottom one sometimes has to be.** Only two SKRAMs exist, so a row of nothing
  but SKRAMs is 1.240 m — narrower than the 2.460 m Achenbach row that would stand on it. The solver puts the
  two SKRAMs in the middle of the bottom row and flanks them with Flexys: 3.684 m, and the rig is a pyramid.
  Strictly **only to remove an inverted step** — the looser rule "mix whenever a device is in short supply"
  would have dragged `full-rig-stacked`'s Achenbachs below its Flexys, since the Achenbach is the widest sub
  we own at 0.600 m against the Flexy's 0.591
- A mixed tier expands into one placement per segment — `main/1a`, `main/1b`, `main/1c`, letters so they cannot
  be confused with the numeric copy suffixes — and whatever stands on it rests on its **tallest** segment,
  because that is the top face `on:` reads
- **Rows are balanced rather than greedy.** Eight leftover Flexys at six-per-row come out 4 + 4, not 6 + 2:
  the same number of rows, but a 1.222 m row could not carry the Achenbachs above it and a 2.424 m one can
- `scenes/full-rig-all-speakers.yaml` — all 23 cabinets in one stack, which was not expressible before

### Fixed

- **A stack could produce a rig that cannot be built, silently.** Asked for all 23 cabinets it put a 2-wide
  SKRAM row under a 4-wide Achenbach row — 610 mm of Achenbach hanging in mid-air at each end. Nothing noticed:
  `on:` only reads a top face, and `ShippedScenesTest` only catches cabinets *inside* each other, never one
  standing on air. There is now a support check, reported as a **warning** with the overhang in millimetres,
  alongside one for a mixed row's step (151 mm between a SKRAM and a Flexy) — both things a crew shims rather
  than refuses, and neither silent
- **`scene:build` and `scene:render` treated any violation as fatal**, so the first warning-severity scene
  violation swallowed the entire build report — which is where the numbers are. Both now filter with
  `Violation::errorsIn()`, exactly as their own spec checks twenty lines above already did
- `scenesDir()` was defined identically in `SceneBuildCommand` and `SceneRenderCommand`; moved to
  `BaseCommand` rather than adding a third copy

## [0.30.0] - 2026-08-10

### Added

- **`stack` on a placement: a rig solved from constraints instead of a tier per row written by hand.**
  `{ from, max_width_m, min_width_m, max_height_m, interface_height_m, gap_m }`. `from` lists the cabinets
  low frequency first and takes the counts from each spec's `quantity`; the solver deals them into rows
  bottom-up and expands into ordinary placements — `main/1`, `main/2`… — each standing `on` the one below
- **The sub/top interface is the constraint worth having.** `interface_height_m` (default **2.0 m**) is how
  high the sub stack's top face has to reach so the tops fire over a standing crowd rather than into it.
  Against what we own, two Flexy tiers reach 1.526 m and miss, and two Flexy tiers plus an Achenbach row
  reach 2.126 m and clear — which is exactly what `full-rig-three-tier` arrived at by hand, so the
  constraint reproduces a stack we already trust
- Either a width bound or the interface height is enough on its own; both together is a solve that can
  fail, and it fails with the number it reached beside the number it needed rather than shipping a near
  miss. `min_width_m` and `max_height_m` are the other two bounds
- **Nothing in the solver assumes a common module**, which is the real work: five cabinets, five widths
  (0.4656 / 0.500 / 0.591 / 0.600 / 0.610) and five heights (0.600 / 0.763 / 0.836 / 0.914 / 0.960), no two
  of them multiples of anything. Every row count is worked out per cabinet and every height is summed
  rather than multiplied — tested over the whole inventory twice, with and without the SKRAM, because the
  SKRAM is the widest thing we own and the second tallest and so is what a tier boundary has to bend around
- `scenes/full-rig-stacked.yaml` — `full-rig-three-tier` with nobody choosing the tiers. It agrees with the
  hand-written scene on the Flexy rows and the interface height, and corrects it on one point: that scene
  calls for six Achenbachs and only four exist
- `align` on a stack applies to every tier but the bottom one, whose edges become the envelope — so a whole
  constraint-solved rig can also be justified, and `full-rig-stacked`'s four rows all run -2.1250 .. +1.5210
- **`align` on a placement: a tier spread across a width instead of a step somebody bisected by hand.**
  `{ mode, width_m | across | inside, inset_m }`. `mode: block` justifies a tier until its outer edges land
  on the width; `center` is the natural spacing every row already had; `stereo` splits the count into two
  columns pushed apart, keeping each column's own spacing
- The reason it cannot be arithmetic: **an aimed cabinet's outer edge does not follow from its width.**
  Aiming toes it in, a toed-in cabinet occupies more x than it is wide, and how far it toes in depends on
  where it ended up — so aligning one tier's edge with another's is a fixed point. It is worse for a
  trapezoid: a Tecnare toed in 11.43° reaches 0.2206 m off centre on its *back* bottom corner, against the
  0.250 m half-width the sum would use, so the naive answer is wrong in both directions depending on the
  cabinet
- The fixed point is bisected against the same rotated boxes the compiler already uses for contact, camera
  framing and the build report — the trial layout goes through the very same `orientationFor()` and
  `worldBox()` as the final one, so the solve and the geometry cannot hold different opinions about where an
  edge is
- `across` and `inside` are separate words because they are separate objects: `full-rig-stereo`'s tops are
  4.678 m *across* and 3.628 m *inside*. A tier standing beside another wants the first; a fill going
  between its outer cabinets wants the second
- Refusals rather than silent near-misses, all naming the number they reached and the one they needed: an
  envelope narrower than the cabinets stacked on one spot, an inset that eats the envelope, a forward or
  self reference, `step_m` alongside `align`, an `arc`/`line_array`/nested group that has no step to solve

### Changed

- `scenes/full-rig-stereo.yaml` no longer carries **0.8156**, **2.1185** or **2.9709**, and
  `scenes/full-rig-all-tops.yaml` no longer carries **1.887**. All four are solved, and the old values are
  now the regression test that the solver reproduces them — to within the 4-decimal rounding of the numbers
  themselves (the exact fixed points are 2.118461, 2.970946 and 1.886983)
- A placement now **rejects unknown keys**. It used to accept anything, so `algn:` read as "not aligned" and
  `aim_lies:` as "follow the scene mode" — both rendering perfectly plausibly with nothing to see. Same
  argument `arc` and `lattice` already made for their own keys

### Fixed

- `scenes/full-rig-quarter-turned.yaml`'s near-fill step was **1.887 copied from the upright rig, where it
  did not belong**. Its subs lie on their sides, so its tops stand 344 mm lower and toe in by a different
  angle; the step that really leaves 20 mm there is 1.8918. The scene had ~5 mm less clearance than its own
  comment claimed. Solved per scene, the two no longer share a number they never shared geometry for

## [0.29.0] - 2026-08-09

### Added

- **`join` on a baffle feature: two horns that share one mouth.** The Tecnare's two 12″ horns are
  connected on the real cabinet — the wall between them stops behind the baffle, so the front is one
  opening and the two throats only part company inside. Nothing in the spec could say that. Two mouths
  moved until they touch still read as two holes with a line between them, and the wall carries on to the
  throats; the model showed the pair with 12 mm of baffle standing between them
- The field is a relation, `join: { with: <earlier horn>, depth_m: … }`, stated on the later of the two the
  way `inside` names the horn it sits in. `depth_m` is the length of the wall that is *missing*, measured
  from the baffle inwards
- Down to that depth the pair is cut as **one common section**, not as two cavities with the strip between
  them knocked out. Cutting only the strip is the obvious reading and it is wrong: every horn's
  cross-section narrows towards its throat, so two of them cut separately turn inwards near the side walls
  and never meet there. The renders showed it immediately — a shelf at each end of the wall, at every depth
  tried. Cut as one section the walls run unbroken from one horn's far edge to the other's
- The section is measured off **both** horns at every depth, on their own flare laws, roundness and the
  same cutter span the walls were cut with, rather than extruded straight back from the mouth plane. The
  gap between two exponential horns widens as they narrow: a straight prism would have undercut the flare
  walls sideways by 39 mm at 60 mm deep and left a 4.5 mm fin of the wall it was removing standing in front
  of its own nose. Thin fins are also the worst input a boolean solver can get
- The cutter's far end **rolls off over a 15 mm radius** instead of stopping square, so the wall runs into
  the horns' side walls through a fillet. That junction is the part of a joined pair you look straight at,
  and a square end left it as a hard inside corner no cabinet has
- This is the first geometry in the library where two cutters overlap, which is what `_carve`'s
  `use_self = True` was set for in 0.24.0. The cutter stops 0.1 mm short of both horns' side walls on
  purpose: reaching them exactly would pair two coplanar, oppositely wound faces, which is the one surface
  arrangement the EXACT solver handles worst

### Changed

- **The Tecnare's two LF horns are joined, 0.190 m of the 0.200 m they are deep.** The owner's account is
  that the pair is connected, not that the wall ends at a measured depth, so this is that account taken as
  far as the geometry allows: at 0.200 no wall would be left at all, which is a different cabinet — one
  horn with two throats — and not something this spec can say. What survives is a 10 mm lip, 37 mm thick,
  right at the two drivers; the baffle reads as one 0.45 × 0.572 opening. Estimated like every other size
  in this layout. Judged from renders at 18, 60, 100, 150 and 190 mm, which is what the setback needed
- `specs:validate` rejects a `join` that describes no cabinet: one on a cone or on a `mesh_override` spec
  (there the CAD cut the holes and there is no baffle of ours to open up), one naming itself, an unknown
  feature, a later one, a cone or a nested plug, a `depth_m` of zero or one reaching the shallower horn's
  throat, and a pair whose mouths overlap or line up on neither axis
- `TODO.md` 2.1.1 deleted
- `TODO.md` 1.1 (tetris stacking) written out: it is driven by a **constraint** rather than by a tier list —
  `max_width_m`, or a stated height at the sub/top interface so the tops fire over the crowd, plus
  `min_width_m` and `max_height_m` as the other two bounds. Recorded with the arithmetic that matters: two
  Flexy tiers reach 1.526 m and miss a ~2.0 m interface, two plus an Achenbach row reach 2.126 m and clear,
  which is what `full-rig-three-tier` arrived at by hand
- The same item now names the reason it is real work: our five cabinets have five different widths and five
  different heights, none a multiple of any other, so nothing can assume a grid. Two acceptance scenes are
  listed for it — the whole inventory without the two SKRAM (21 cabinets), and with them (23), the SKRAM
  being both the widest cabinet we own and the second tallest
- `TODO.md` 5 deleted: `tools/check-glb.py` no longer fails on `tecnare-m2122`, fixed in 0.28.2. Its
  misnested sub-item, the audio routing table, is now an item of its own

## [0.28.3] - 2026-08-04

### Added

- **`tests/Scene/ShippedScenesTest.php` — every shipped scene checked for cabinets inside each other.** CI
  already proved that all eighteen *compile*; nothing proved any of them could be *built*. The one bug that
  slips past a render is interpenetration, because from a three-quarter camera a cabinet buried in another
  one looks like a cabinet in front of it — which is exactly how `two-foci.yaml`'s fills shipped 0.41 m
  inside the sub wall in 0.21.0. That was found with a throwaway script; this is the same sweep as a test
- Separation is measured with a **separating-axis test on each cabinet's own eight corners**, and that is
  the whole reason the test is worth having. Comparing axis-aligned bounding boxes — the cheap version —
  reports `full-rig-arc`, `sub-wall-lattice` and `flown-array` as broken when they are not: a yawed
  cabinet's bounding box is far larger than the cabinet, so an arc's neighbouring seats always look like
  they overlap. A check that fails on a third of the library gets switched off
- A test that the check catches a deliberate intersection, so the eighteen passes mean something

### Fixed

- **A hang's joints were solved at plumb and its elements then tilted individually, which drove
  `flown-array`'s cabinets 21.7 mm into one another.** Found by the sweep above on its first run. `aim`
  resolves a hang as one rigid body, so by the time the chain is laid out its top element is already 14.263°
  down — and a joint is not scale-free in the angle: the one that closes between 0° and 2° is not the one
  that closes between 14.263° and 16.263°. The chain is seeded with the placement's own tilt now, so the
  joints are solved at the angles the array actually reaches. Worst joint: 21.7 mm → 9 µm
- **And the chain now swings with the hang's yaw.** The remaining 3.5 mm after the fix above: joints are
  solved in the elevation plane, which has no x in it, so their offsets came out along the world's y while
  every element was yawed 11.1° towards the focus. A frame does not do that. `PlacementCopy::yawedBy()`
  turns a rigid group's offsets, gated on `Group::decidesPitch()` — which only `LineArray` answers yes to,
  and for exactly this reason. A yawed `row` is deliberately left alone: a straight line of toed-in cabinets
  is what a toed-in sub wall is
- `flown-array` therefore leans back along its aim instead of dropping straight down, and its bottom sits at
  2.195 m rather than 2.13 m. Still walkable underneath
- The `line_array.splay_deg` nose check counts the aim's tilt too — 14° of aim plus 76° of splay stands an
  element on its nose just as surely as 90° of splay does

### Changed

- The splay is accumulated on its own rather than subtracted back out of the running tilt. Both describe the
  same hang, but `14.263 + 2 − 14.263` is `1.9999999999999982`, and these increments end up in a committed
  build plan where a gap the scene wrote as 2° has to read as 2°

## [0.28.2] - 2026-08-04

### Fixed

- **The chamfer was pushing geometry outside the declared bounding box**, which is the one promise the asset
  library rests on, and `tools/check-glb.py` had been failing on `tecnare-m2122` because of it. The chamfer
  was added as a *live* modifier **before** the baffle openings were carved into the same shell, so when the
  exporter evaluated the stack the bevel rounded the carved mouths' rims as well as the cabinet's own
  corners. Where three of those rims met on the mid horn it pushed **two vertices of 2636** 0.9 mm in front
  of the baffle plane. Applied immediately instead — the same idiom the handle booleans one function up
  already use — so the openings are cut into an already-chamfered shell, which is the right order
  physically as well. Depth and height are now exact
- The cost, stated because it was the previous behaviour's stated reason: the `.blend` no longer carries an
  editable bevel modifier

### Changed

- **`tools/check-glb.py` is asymmetric now, and the equality it used to check was wrong.** Easing a corner
  can only remove material, and on a **tapered** cabinet the widest point *is* a corner — so a 500 mm-front
  trapezoid with 10 mm eased edges genuinely measures 497 mm across, and the model was right where the check
  was not. A box is unaffected: its side faces stay put and only the corners between them go. Overshoot now
  fails at 0.1 mm as before, because nothing may lie outside the declared box; undershoot is allowed up to
  the chamfer
- `chamfer_m` travels in the glTF metadata so the checker can reason about it, alongside `origin`, which is
  there for the same purpose
- `docs/conventions.md` said the bounding box "always equals width × depth × height", which is knowably
  false for a chamfered trapezoid. It now says nothing ever lies *outside* the box, and says which shape is
  legitimately smaller and by how much

## [0.28.1] - 2026-08-04

### Changed

- **Every visible material now takes `appearance.color`.** The horn flares were `#3a3a3c` against a `#141414`
  cabinet — deliberately lighter, "so the mouth reads as an opening with something inside it" — and the
  handles were `#1a1a1a`. On the 18sound that horn is 0.362 m across a 0.466 m cabinet, so it dominated the
  baffle and read as a differently-coloured panel rather than as a flare. It was never spec-specific: the
  Tecnare's three flares had it too, and all five specs have shared one `#141414` shell for some time
- `metallic` goes with the colour rather than staying: it changes apparent shade more than roughness does, so
  a metallic handle at the cabinet's own colour still would not have matched it. `roughness` is kept, because
  it changes how sharp a highlight is rather than what colour a surface is
- The parts keep their own materials, so per-device colour (`TODO.md` 3.2 — flexy bracings green) can
  differentiate them again without restructuring anything

## [0.28.0] - 2026-08-04

### Added

- **`scenes/full-rig-stereo.yaml` — the widest image the inventory allows.** `full-rig-quarter-turned` with
  every tier pushed apart until its **outer edges line up with the sub wall's** rather than sitting centred
  and narrow on top of it. The sub wall on its side is the widest thing we can build at 4.678 m, so it sets
  the envelope and everything above fills it: the Achenbach row spread to 0.8156 m of step (216 mm of air
  between each), the tops out to 2.1185 m, the fills following them
- **Spreading the middle tier is the mechanism, not a compromise.** The tops stand on the Achenbachs, so the
  row has to reach out to where the tops need to be — which is why the Achenbachs get gaps
- Outer tops move from ±1.55 m to ±2.12 m, a **37% wider** image, at the cost of a thinner middle: three tops
  2.1 m apart leave a wider hole between their patterns, and the centre of the room is covered by the middle
  cabinet alone. Three versions of the same 23 cabinets now exist, narrowest to widest, so that trade can be
  looked at rather than argued about

### Fixed

- Nothing new, but worth recording: the near-fills follow the tops. With the tops out at 2.1185 the old
  1.887 step left them floating mid-gap instead of tucked against the outer tops, so it is re-solved to
  2.9709 — again against the rotated boxes, and again 20 mm exactly

### Notes

- **Every step in that file is solved rather than derived, and `TODO.md` 1.2 now exists because of it.**
  Aligning an *aimed* cabinet's outer edge cannot be computed from its width: aiming toes it in, a toed-in
  cabinet occupies more x than it is wide, and how far it toes in depends on where it ended up — so it is a
  fixed point, not a formula. Three numbers were bisected out by hand (0.8156, 2.1185, 2.9709) and every one
  goes stale the moment a cabinet is measured or a focus moves. That is the third time this trap has bitten:
  it overlapped two cabinets by 88 mm in `full-rig-all-tops` and put the fills 0.41 m inside the sub wall in
  `two-foci`. The TODO proposes named layouts — `center`, `block`, `stereo`, borrowing the text vocabulary —
  resolved against the rotated boxes, which would remove the whole class

## [0.27.0] - 2026-08-04

### Added

- **`scenes/full-rig-quarter-turned.yaml` — the same rig with the subs on their sides**, twelve Flexys in
  three groups of four, every one rolled a quarter turn and alternating 270/90. Everything above the subs is
  identical to `full-rig-all-tops.yaml`, so it is a straight comparison: the wall goes from 3.65 × 1.53 m
  standing up to **4.68 × 1.18 m** on its side. Wider and lower — the Achenbach row now sits comfortably
  inside the wall instead of overhanging it, and every top drops 344 mm, which flattens their down-tilt
- Twelve cabinets in **one placement, three levels of nesting**: a back-to-back pair, two tiers of that pair
  to make a group of four, three of those groups side by side. The deepest nest any shipped scene uses, and
  the first to use `roll_cycle` with quarter turns rather than `[180, 0]`

### Notes

- **A quarter turn puts a cabinet entirely to one side of its own origin**, because geometry runs from the
  bottom-centre — to the right at 90, to the left at 270. Two cabinets whose origins nearly coincide, rolled
  opposite ways, therefore fall to opposite sides and meet back to back rather than overlapping. That pair is
  what this wall is built from, and its `step_m: 0.02` is 20 mm of air rather than a spacing
- **The order of the cycle matters, and that is not obvious.** `[270, 90]` opens the pair outward from its
  shared origin and the two cabinets touch exactly; `[90, 270]` closes it inward and overlaps them by exactly
  the 20 mm step. Same two angles, opposite result
- **The pair's spacing cannot be derived, unlike every other wall in the repository.** A `row` of four with
  `roll_cycle: [90, 270]` and a derived `gap_m` looks obviously right and drives adjacent cabinets **591 mm**
  into each other, because derived spacing assumes a cabinet occupies a box centred on its position — true
  for every upright cabinet, false for one on its side. Only the pair needs stating; the two levels above it
  derive from what the pair actually occupies

### Fixed

- **The near-fills in `two-foci.yaml` were inside the sub wall.** At `y = -0.6` they overlapped it by
  **0.41 m**: the wall is 0.964 m deep so its front face is already 0.482 m forward of its own centre line,
  and a fill aimed at a 2 m focus is turned far enough that its box reaches 0.42 m behind its position. Moved
  to `y = -1.05`, which leaves 40 mm of air in front of the mouths. Shipped wrong in 0.21.0 and invisible in
  a three-quarter render, which is how it survived

## [0.26.0] - 2026-08-04

### Fixed

- **We own twelve Flexys, not fourteen.** `quantity: 14` came from `Hardware Overview.xlsx` and was simply
  wrong. Correcting it is one character; what it forced is the interesting part

### Changed

- **Every sub wall is now two rows of six, and derives its own spacing.** The seven scenes that built a
  fourteen-cabinet wall would otherwise all have reported "uses 14, we own 12", so they are cut to 6 + 6 —
  and converted from `repeat: { count: 7, step: [0.611, 0.0, 0.0] }` at a hand-computed left edge to
  `row: { count: 6, gap_m: 0.02 }` on the wall's centre. Cutting the count the other way would have meant
  typing `-1.8295` into five files; deriving it means the wall follows the spec and the next correction is a
  `count:` edit. `gap_m: 0.02` reproduces the 0.611 m step from the cabinet's own width, so no position
  moves for any reason other than the two missing cabinets
- Every wall is now **15 cabinets, 1224.0 kg, 3.65 × 0.96 m** where it was 17, 1394.0 kg and 4.26 m. The
  tops that sat over the wall's outer subs moved with them, to −1.8295 and +1.2255; the three scenes whose
  tops are an `arc` on the rig centre did not move at all
- The Achenbach row's hand-computed left edge went the same way. It is unchanged in position — a centred
  `row` of six with a 20 mm gap resolves to exactly the `at: [-1.852, 0.0]` plus `step: 0.62` it stated
- **`docs/scenes.md`'s `repeat` section is now about the choice rather than about walls**, since no wall uses
  `repeat` any more. It points at `scenes/skram-detail.yaml`, where a stated step *is* the decision, and says
  which way the dependency runs: a stated step means the scene needs correcting when a cabinet is measured, a
  derived one means the wall follows the spec
- The library is **23 cabinets, 1686 kg, 8.2 m³** (was 25, 1856 kg, 9.0 m³)

### Added

- **`scenes/full-rig-all-tops.yaml` — every top we own on one rig.** The three-tier stack with Sepp's two
  18sound 2-ways added inside the outer M2122s as near-field fill. Two named foci rather than any stated
  angle: the M2122s take `aim: far` down the room, the 2-ways `aim: near` for the people against the stage
  who sit underneath the M2122s' pattern rather than in it. The M2122s stay spread at their 1.55 m step,
  because wide spacing is a coverage decision and an `arc` would pull them onto their own taper. 23 cabinets,
  1606 kg, and the tops are exactly the whole top inventory — three M2122s and two 2-ways
- **The near fill's spacing had to be solved against the turned boxes, not the widths, and the gap between
  those two answers is 108 mm.** Both cabinets are aimed, so both are toed in — the outer top by 8.4° and
  the fill by 20.8°, because a 2 m focus is a hard turn — and a yawed cabinet occupies more x than it is
  wide. Half-widths plus a 20 mm gap gives a step of 2.0944 and drives them **88 mm into each other**; the
  real answer is 1.887, which leaves 20 mm to the outer top and 323 mm to the middle one. Worth writing down
  because the arithmetic looks completely right

### Notes

- The Achenbach row (3.70 m) is now **wider than the wall it stands on** (3.646 m), overhanging 27 mm each
  side. Nothing structurally — cabinets overhang in reality — but it is why the three-tier scenes report the
  row's width as their footprint and not the wall's, and the comment claiming the row is the narrower of the
  two has been corrected

## [0.25.0] - 2026-08-04

### Added

- **Coverage cones from `audio.coverage_deg`.** `Coverage`'s own docblock has promised "it drives the
  optional coverage cones" since it was written, and the angles have been crossing into Python in every
  build plan the whole time with nothing reading them. Stating the angles now draws the pattern
- **10 m of throw, reusing the default focus distance.** A cone is an angle, so something has to choose a
  length, and that is the one number in the library that already means "out where aiming matters" — so a
  cone reaches exactly as far as a scene's default aim and "does the pattern cover the dancefloor" reads
  directly against it. A 60° × 40° Tecnare spreads 11.55 m across and 7.28 m high by the time it gets there
- `Coverage::spreadAt()` and `tests/Spec/CoverageTest.php` — the one part of a cone that can be checked
  without opening Blender, so the trigonometry is unit-tested even though the bpy code cannot be
- The decision stays in PHP: `BuildPlan` emits `coverage_throw_m` and `coverage_spread_m` beside the angles,
  the same way `mark_estimated` is decided in PHP and merely consumed. No new spec field and no CLI flag —
  the cone is built iff the angles are there, exactly the way a missing `baffle_layout` simply builds nothing

### Changed

- **The cone is a wireframe, not a solid.** Solid, ten metres of it swallows the half-metre cabinet it
  belongs to, which defeats the point of drawing it; as a wireframe it is something to sight along
- **It is render-invisible, and that is load-bearing rather than cosmetic.** `export_glb` passes
  `use_renderable=True` precisely so markers stay in the `.blend` and out of the `.glb`, and
  `tools/check-glb.py` compares the exported bounding box against the declared dimensions to 1e-4 m — a
  visible cone would fail that on every axis of every model that had one. Verified: the Tecnare's `.blend`
  carries an 11.547 × 10.000 × 7.279 m cone and its exported box is still its own 0.500 × 0.960 × 0.520
- It lives in `blender/lib/drivers.py` rather than with the other markers in `geometry.py`, because the
  ring-and-shell machinery there is what draws it and a dispersion pattern is the audio side of a cabinet
  rather than part of its box. New `sdwa5-coverage` material; the material table in `docs/conventions.md`
  gains it, and the `sdwa5-cone`/`sdwa5-horn` rows it had been missing since the baffle work

### Notes

- The apex sits at the middle of the baffle, which is a simplification worth naming: a real pattern comes
  from the drivers, spread across the baffle and crossing over at different distances. The cone answers
  "roughly where does this cabinet throw", not "what does the summed response do"
- Only `tecnare-m2122` states coverage angles today, so this is a no-op for the other four specs

## [0.24.0] - 2026-08-04

### Added

- **`fly` — a third way to say where a placement's base is.** `at` is a ground position and `on` is the top
  of an earlier placement; between them they cover everything that stands up and nothing that hangs. That
  gap is why `line_array` shipped unusable: its elements grow *downwards* from their anchor, so anchoring
  one on the floor put it under the floor
- **`fly.point` names the hardware, which is what makes this more than an absolute z.** A cabinet does not
  hang from its own bottom-centre, and `rigging.points` already says where it does hang from — so naming one
  lets a scene state the thing that is true, that *this* point is at 6 m, and the slot is worked out from it.
  An M2122's `top-left` sits 0.960 m up and 0.185 m left of centre, so hung at 6 m over `x = -3.0` the
  cabinet's slot lands at 5.040 m and its centre-line at −2.815. Rigging positions are already in the
  measuring frame, the same frame a slot position is in, so it is a plain subtraction — `geometry.origin`
  stays out of it and the same spec remains usable both ground-stacked and flown, which committing a spec to
  `origin: rigging-point` would not have allowed
- **Weight per suspension point in the report**, which is the number checked against a truss's capacity.
  `fly.id` groups it, so two hangs off one bar add up rather than reporting separately. Absent entirely when
  nothing is flown, so every ground-stacked scene's report is byte-identical
- **A hang that reaches through the floor is reported, with how far by.** It is the one arrangement that can
  be told to sit above the floor and still end up below it, and it is exactly the condition that made
  `line_array` unusable — so `fly` polices itself rather than leaving it to a render
- `scenes/flown-array.yaml` — two J-splayed hangs of four M2122s off one 6 m bar, the first scene to use
  `line_array` at all. `src/Scene/Fly.php`, `tests/Scene/FlyTest.php`

### Changed

- **A hang is aimed once, at its anchor, and the splay adds to that one angle.** A line array is one rigid
  body: every element shares its attitude and differs only by the accumulated splay. Aimed per element
  instead — which is right for a row of tops and was what the code did — each element turns towards the
  target on its own and the splay cancels out exactly. The four-element J in the new scene came out at
  14.26°, 12.63°, 12.87°, 16.10°: not even monotonic, when a J array's whole point is that it opens
  downwards. It now reads 14.26°, 16.26°, 20.26°, 27.26° — one base tilt plus 2°, 4° and 7° — and all four
  share one yaw. `Group::decidesPitch()` is what tells the two cases apart
- **A flown cabinet is not lifted onto a slot.** `zLift()` exists so a tilted or upside-down cabinet still
  rests on the thing it stands on; a hang has no such thing, and the hardware decides where it is. `fly` now
  implies this for the whole placement, not just for `line_array` elements — a single flown top is flown too

### Fixed

- **A group could put part of itself below its own base with nothing to lift it back.** `PlacedDevice::zLift()`
  does this for one rotated cabinet; a `roll_cycle` on a lattice whose cell is more than one tier tall maps
  the cell's offsets `z → −z` and buried the lower tier. Same argument, one level out, and exempt for a hang —
  which belongs below its anchor by construction
- **`GroupStack` measured a nested hang as if it stood on the floor.** `boxOf()` and `cabinetBox()` both built
  their measuring boxes without the `seated` flag, so a `line_array` inside a lattice was sized one cabinet
  tall instead of the whole array's height, and the lattice above spaced its cells on a height the hang does
  not have — two hangs 0.96 m apart rather than 2.88 m

## [0.23.0] - 2026-08-04

### Added

- **`build:all` — the stage order, written down.** Every stage already refuses to run on stale input, but
  nothing knew the order, so getting from an edited spec to a new render meant remembering five commands and
  which of them the edit had invalidated. It delegates through the application rather than reimplementing, so
  each stage's own staleness rules, reporting and refusals are the ones that apply, and a failing stage stops
  the run because everything after it would be building on what just went wrong
- **`--lighting-variants` and `--aim-line-variants`**, which are what make it more than a shell alias: four
  lighting presets against with-and-without aim lines is eight passes into eight folders, from one command.
  `--dry-run` lists the stages and the variant folders and runs nothing, which is also the only way to test
  any of it where there is no Blender
- **`scene:render --out-dir`** — a directory to write into, keeping the `<scene>-<camera>.png` names, and
  unlike `--out` it works for a whole run. There was previously *no* way to render every scene somewhere
  else: `--out` is refused for more than one scene
- `src/Command/BuildAllCommand.php` and `tests/Command/BuildAllCommandTest.php`

### Fixed

- **Two variants of one scene used to overwrite each other**, both the picture and the plan beside it. Neither
  filename mentioned the lighting or the aim mode, so `-l studio` followed by `-l stage` left one PNG and one
  plan that did not describe it. The plan name now carries both; the picture is separated by its folder
- `--out` together with `--out-dir` is refused rather than one of them quietly winning

## [0.22.0] - 2026-08-04

### Added

- **`aim_lines` as a scene option, and a per-group override.** A scene whose whole point is where things aim
  should not need a flag remembered on the command line: `aim_lines: tops` at scene level says it, and
  `aim_lines: true` or `false` on a placement disagrees with the mode for that group — so a sub can be shown
  and a top hidden. `scenes/two-foci.yaml` uses both, because two groups aiming at two different points is
  invisible in a still render otherwise

### Changed

- **`--aim-lines` overrules the scene only when it is actually typed.** Whether the flag was given has to be
  told apart from the value it defaults to, and the option's default cannot answer that — bare `--aim-lines`
  already yields nothing, which is how it means `tops`. `hasParameterOption()` answers it
- `--aim-lines=none` beats every placement, which keeps it the way to get a clean picture of a scene that
  normally draws them. It is the one thing a placement cannot overrule, and the only place the command line
  wins outright
- The resolved flag rides on `PlacedDevice`, so `RenderPlan` still reads nothing but placed devices

## [0.21.0] - 2026-08-04

### Added

- **More than one focus per scene, referenced by name.** A rig usually needs two: the tops throw down the
  room and the near-fills cover the people against the stage, and one point cannot be both. `focus: { near:
  {…}, far: {…} }` plus `aim: near` says so in a line, and `scenes/two-foci.yaml` is that rig with no angle
  stated anywhere
- One focus or a map of them are told apart by **shape alone** — if every value under `focus` is itself a
  mapping it is a map, otherwise it is the single unnamed one, which keeps the name `focus` so `aim: focus`
  still means it. Every scene written before names existed is unchanged. Same shorthand-or-expanded test
  `ArrayReader::isSection()` already makes for `provenance`
- `ArrayReader::keys()`, for a block whose field *names* are the scene's data rather than part of the schema

### Changed

- A focus stays a decision about the **room** rather than about a cabinet, which is why they are named at
  scene level rather than written into each placement: two clusters sharing one near-field point is the
  normal case, and duplicated numbers drift apart silently
- **Every distance is still measured from the whole rig's front face**, not from each group's own. Measuring
  per group would mean "2 m out" and "10 m out" came from two different places and neither number could be
  read off the file — if the fills stand 0.8 m behind the sub wall, a group-relative 2 m focus is 2.8 m from
  the audience. It would also reintroduce, once per group, the circularity the rig-wide front-face pre-pass
  exists to avoid
- `SceneSpec::$focus` is now `$focusByName`; `Placement::$aimAtFocus` (a bool) is now `$aimFocus` (a name)

### Fixed

- **`aim: focuss` meant *not aimed*** — silently, with the cabinet left firing straight ahead and nothing in
  the output to show it. An unknown focus name is now a violation that lists the ones the scene defines

## [0.20.0] - 2026-08-04

### Added

- **`line_array` — a hang, chained rather than fanned.** A sibling of `arc` and not a rolled version of it,
  because the difference is structural: an arc rests on one centre of curvature shared by every cabinet,
  which is what makes its wedge argument work and its radius a single maximum, while an array is a chain
  where every gap has its own angle and there is no shared centre to take a maximum over
- `splay_deg` takes one angle for every gap or **one per gap**, which is what a J array is — `[1, 2, 3, 5, 8]`
  opens up towards the front rows, and writing them out is the whole point
- **The joint hinges where a frame would pin it, and which edge that is follows from the geometry.** A
  downward-curving array pins the rear edge, one curving up pins the front, and at a wedge's own taper both
  give the same answer because the faces meet flat. Solved rather than assumed for a quantified reason:
  pinning the front edge of a downward curve drives a 0.52 m deep cabinet **45 mm** into its neighbour — the
  vertical twin of the concave interpenetration `Arc` already warns about, and just as plausible in a render
- Contact is solved on `Outline::elevation()`, which Stage 1 built for exactly this: an arc's flush splay is
  the angle between the plan outline's two *side* edges, and an array's is the angle between the side
  outline's *top and bottom* edges — `atan((height − front_height) / depth)`, 17.10° for a 0.96 m box with a
  0.80 m front. Verified against the elements' own corners by separating-axis distance: exact contact at
  every joint for a constant splay, a J, and a flat stack
- `src/Scene/LineArray.php` and `tests/Scene/LineArrayTest.php`

### Changed

- **`PlacementCopy` gains a pitch increment, separate from its rotation**, and the reason is worth stating:
  composing an array's splay as a rotation would put it *outside* the placement's yaw, and
  `Rx(σ)·Rz(ψ)·Rx(θ)` is not `Rz(ψ)·Rx(θ + σ)`. An arc's yaw is a turn of the cell; an array's splay **is**
  the cabinet's own tilt, since every element of a hang shares the hang's yaw and differs only in how far it
  is tilted. So it adds to the pitch and leaves the aim, the roll and the yaw where they are
- **`PlacedDevice` gains `seated`, and a flown cabinet is not lifted.** `zLift()` exists so a tilted or
  upside-down cabinet still rests on its slot, which is right for anything standing on something and wrong
  for a hang — each element is tilted differently, so lifting each one back onto its own slot would pull the
  array apart at every joint

### Notes

- **No shipped scene uses `line_array`, because there is no way to hang one yet.** Elements take negative z
  and `at`/`on` can only name a ground position or the top of something, so an array written today ends up
  under the floor. The geometry and the schema are done and unit-tested; a fly-point anchor is a separate
  feature and is now on `TODO.md` next to reporting weight per flown point

## [0.19.0] - 2026-08-04

### Added

- **`lattice` — a 1-, 2- or 3-D grid spaced from the size of whatever it replicates.** This is the group
  that stops a scene carrying arithmetic somebody did by hand. `full-rig-mirrored.yaml` states
  `at: [-2.135, 0.0]` and `step: [0.611, 0.0, 0.0]` twice, and all three numbers are derived: seven 591 mm
  Flexys with a 20 mm working gap make a 4.257 m wall, centred on `x = -0.302`, left edge at −2.135. The
  new `scenes/sub-wall-lattice.yaml` states none of them and resolves to the same fourteen positions, so
  measuring a Flexy moves the wall instead of quietly invalidating it
- **`row` — a lattice with one open axis**, which is the case that dominates; `axis` defaults to `x`. Not a
  second class, because naming the axis outright is also what makes `roll_cycle` unambiguous for free
- **`in` — groups nested inside groups, to any depth.** The sibling key is the cell and `in` is what it is
  nested inside, read inside-out. An outer group spaces itself on the whole inner arrangement's extent, so
  two tiers of a three-wide fan step by the fan and a row of three 2×2 blocks steps by the block — the
  numbers nobody should have to work out. Every scene written before this is untouched rather than merely
  still parsing, because `in` is simply absent
- **`roll_cycle` and `cycle_axis` — alternate cells turned over**, which is a mirrored horn wall as one
  placement instead of two. It composes with `roll_deg` rather than special-casing, so `[0, 180]` gives
  0, 180, 0, 180… and `roll_deg: 90` alongside it gives 90, 270, 90, 270… — both rows the rig needs, from
  one mechanism. `cycle_axis` is required when more than one axis has cells: a cycle down x instead of z on
  a seven-by-two wall turns every *column* over instead of every tier, which is fourteen cabinets wrong and
  renders perfectly plausibly. Values must be quarter turns, because the cell arrives as an axis-aligned
  box and only a quarter turn can be applied to one exactly
- **`step_m`, with 0 meaning "derive this axis"** — a step of zero is meaningless for a count above one, so
  it is a safe way to say it. `scenes/end-fire.yaml` uses it: subs stepping 1.20 m front to back, which is a
  decision about frequency (a quarter wavelength at 71 Hz) rather than about geometry, with x left to work
  itself out
- `gap_m` accepts one number or three. One number means **x and y only**, and that is the real decision: a
  gap on x is air beside a cabinet, which is normal, while a gap on z is air *under* one, which nobody wants
  by accident
- `src/Scene/{Group,GroupStack,GroupReader,Repeat,Lattice,Axis,UnresolvableRotationException}.php`,
  `scenes/{sub-wall-lattice,end-fire}.yaml`, `tests/Scene/{LatticeTest,GroupStackTest}.php`, and
  `Orientation::after()`/`fromMatrix()`

### Changed

- **Expansion happens through one interface instead of one method.** `repeat` and `arc` were the only two
  ways to make copies and they were mutually exclusive, both expanded in a single method in the compiler.
  That holds while "a placement is one group" holds, and stops holding the moment a *cell* is itself an
  arrangement, because then the same expansion has to happen at every level and the levels have to
  multiply. Verified against all eleven shipped scenes: **121 cabinets, zero differences** in position,
  orientation, top height or bounding box
- **`PlacementCopy` carries one rotation rather than a loose yaw.** Composition is matrix multiplication:
  rolling a group over reverses the yaw of everything inside it (`Ry(180)·Rz(θ) = Rz(−θ)·Ry(180)`), which
  is physically what turning an arrangement upside down does and arithmetically not addition. Its `index`
  becomes a `path`, so a nested id says which tier before which cabinet
- **The outer turn moves the inner arrangement's offsets, not only its rotations** — a group is a rigid
  body, and nesting one places it rather than scattering its parts. Put a touching pair inside a convex fan
  of Tecnares and the pair's 0.52 m step has to run along the *cabinet's* x; left in the world's, the second
  cabinet of each pair lands **155 mm** behind its own seam, which reads as an arc bug rather than a nesting
  one
- `repeat` is kept as its own group rather than folded into `lattice`, which can express the same thing. Its
  anchor is the **last** copy, so `on:` a sub row stacks on the far end of it, and seven shipped scenes
  depend on that; a lattice anchors on the middle cell, which is what lets a single cabinet be swapped for a
  group. One class cannot own both rules without a flag whose only purpose is remembering which spelling it
  was written as
- Two group keys on one placement, `in` with nothing to nest, and a mistyped group name inside `in` are all
  refused when the scene is read. The last one matters: an unrecognised name would otherwise build an empty
  wrapper and silently drop a whole level of the nest
- `build: clone` is now `build: self-built`. The build kind and the block naming the original had the same
  word for two different things, and `self-built` against `own-design` says what actually differs — whose
  drawing it was built from. `clone_of` keeps its name, because what it names really is an original

### Fixed

- Composed offsets are rounded to the nearest picometre. `sin(180°)` is 1.2e-16 rather than zero in binary,
  so a cycled row picked up about 1e-16 m of height per cabinet — nothing physically, and a seventeen-digit
  number in a build plan that should read `0`

## [0.18.0] - 2026-08-04

### Added

- **`splay_deg: 0` is a straight row — spaced and centred from the cabinet itself.** A row is the arc of
  infinite radius, so it is the same contact solve rather than a second mechanism: as the splay closes,
  the arc's spacing converges on the cabinet's own width (0.4957 m at 1°, 0.49996 m at 0.01°, against
  0.500 m), and `centreRadiusM()` now says `INF` rather than reporting a large number that happens not to
  overflow. This is the arithmetic scenes currently carry by hand — `full-rig.yaml`'s `at: [-2.135, 0.0]`
  and `step: [0.611, 0, 0]` are seven 591 mm Flexys plus a 20 mm gap, centred on `x = -0.302`
- **`arc.gap_m`** — the working gap every sub row in the repository already has, stated instead of baked
  into a step. Defined by growing the outline before the contact solve, so it means the same thing at
  every angle: a splayed seam opens by the gap measured *across* the seam
- **A cabinet with nothing to taper resolves to a row on its own.** A plain box, or a trapezoid whose back
  is as wide as its front, has no tightest *bend*, but two of them side by side are already in full face
  contact — which is the tightest convex arrangement there is. It used to be an error demanding an explicit
  angle
- **`roll_deg: 90` works.** Contact is solved on the plan outline of the *turned* cabinet, so an arc no
  longer refuses anything but upright or turned over. On its side an M2122's outline is a plain
  0.960 × 0.520 rectangle — the taper has rotated into the vertical, where the plan view cannot see it — so
  its flush arrangement is a straight row spaced by its **height**, which is what two cabinets on their
  sides actually present to each other. An arc still wants a multiple of 90: in between, the outline's two
  flanks point at different centres of curvature, nothing closes both seams at once, and a solve that
  quietly leaves 14 mm of air down every joint is worse than one that refuses
- `src/Scene/Outline.php` — the cabinet's silhouette in one plane, as a convex hull, in the two planes
  contact happens in: plan for cabinets meeting side to side, elevation for meeting top to bottom. The
  corner sets move onto `DeviceSpec` as `shellCorners()` and `contactCorners()`, so the footprint and the
  contact solve read one definition of the cabinet's shape and can disagree only about the grille frame
- `Orientation::matrix()` and `eulerXYZ()`, `tests/Scene/OrientationTest.php` and
  `tests/Scene/OutlineTest.php`

### Changed

- **A rotation is now composed in the order a scene decides it** — roll the cabinet in its own frame, then
  tilt it down, then aim it (`Rz·Rx·Ry`) — and converted to the euler triple Blender applies (`Rz·Ry·Rx`)
  at the boundary. The two orders agree only while roll is a multiple of 180, which is why the old code
  could flip the sign of the pitch at exactly 180 and be right, and had nothing to say about 90. The
  build plan carries both: `pitch_deg`/`roll_deg`/`yaw_deg` as the scene stated them, and
  `rotation_euler_deg` as Blender wants them
- `Orientation::pitchTowards()` loses its roll parameter. In this order `Ry(roll)` leaves the −Y axis
  fixed, so roll cannot change where a cabinet points and cannot change the angles that aim it either
- **The tightest radius is solved as an angular width about the centre of curvature**, pairwise and in
  closed form, instead of fitting the outline into half a wedge. Measuring it both ways round rather than
  assuming the outline is symmetric about its own axis is what lets a cabinet be rolled: on its side the
  plan outline sits entirely to one side of its origin and there is no half-wedge to fit it into. Verified
  against the previous closed form and, independently, by separating-axis distance between neighbours'
  real corners — exact contact at roll 0, 90, 180 and 270, in both modes
- The flush angle is read off the outline's flanks — the arrangement that puts a whole face in contact is
  the one whose flank, extended, passes through the centre of curvature — rather than off
  `width − back_width` over `depth − inset`. Same 17.35° for an upright M2122, same 16.95° without its
  grille frame, and it survives a roll, which the old derivation could not
- 121 cabinets across the ten shipped scenes are unchanged. Nine values in `full-rig-arc` move by up to
  1e-14 — `atan2(dx, dy)` where the old code divided first, the same angle rounded differently

### Fixed

- **A tilted arc that was also turned over was solved as if it were the right way up.** Tilt swings the
  front-top edge forward and rolling the cabinet 180° swings it the other way, so a mirrored tilted group
  needs 73.7 mm *more* radius at 4.4° than an upright one — and got 73.7 mm less, with the cabinets driven
  into each other in a render that looks entirely plausible. No shipped scene tilts a rolled row, which is
  why nothing showed it
- **Stated pitch and aimed pitch meant opposite things on a rolled cabinet.** `pitch_deg: 5` with
  `roll_deg: 180` aimed at the ceiling while `aim:` on the same cabinet aimed at the floor, because the
  roll was applied after the tilt and turned nose-down into nose-up. Nose-down is now nose-down whichever
  way up the cabinet is
- `Orientation::isUpright()` had no callers and is gone

## [0.17.1] - 2026-07-30

### Changed

- **The Tecnare's mid horn is 0.12 m deep rather than 0.18 m.** At 0.18 it read as a tunnel beside the two
  LF horns rather than as a horn mouth
- **The two 1" HF phase plugs now share the mid horn's 2.25 : 1 mouth** — 0.135 x 0.06 instead of square,
  which is the same proportion as the mid's 0.45 x 0.20 at the same mouth area the plugs had before, so
  only their shape changed and not how much of the 12" cone behind them they cover
- `TODO.md`: two items still named `tecnare-m2122-clone`, a spec deleted in 0.14.0, and quoted the
  18sound's superseded 30 kg. Both now describe what is actually open — weighing the two estimates, and
  confirming the self-built Tecnare against the factory pair the merged spec speaks for


## [0.17.0] - 2026-07-30

### Added

- **`arc` on a placement — a group of cabinets seated on a circular arc**, which is what a cluster of tops
  actually is. Until now the only way to make copies was `repeat`, a linear step vector that leaves every
  cabinet pointing the same way, so the three tops in every rig scene were three hand-written placements.
  `arc: {mode: convex, count: 3}` needs nothing else: **a tapered top's taper is its splay angle**, since
  that is the one angle at which two of them sit side by side with their side faces fully in contact. For
  an M2122 that is 17.35° and a 1.65 m radius, both derived from the cabinet
- `mode` is `convex` (fronts fanning outward, centre of curvature behind, **back** edges touching) or
  `concave` (fronts inward, centre in front, **front** edges touching). It has no default — an arc bent
  the wrong way is a quiet, serious mistake
- `splay_deg` or `radius_m` open the arc up from the tightest; `radius_m` is the arc the front faces sit
  on, the same physical thing in both modes. Counter-intuitively a *larger* convex radius is a *flatter*
  fan, so it is bounded above rather than below
- An arc combines with `aim: focus`: **the arc owns the yaw, the focus owns the down-tilt**, resolved per
  cabinet. `Orientation::pitchTowards()` is new for that, and it measures the tilt along the cabinet's own
  axis rather than the straight line to the target — the outer boxes of a three-wide arc need 4.75° rather
  than 4.48°, and a five-wide cluster splayed 30° needs **2.28×** the tilt
- `scenes/full-rig-arc.yaml`, and `src/Scene/{Arc,ArcMode,PlacementCopy}.php`. `ArrayReader::unknownKeys()`
  makes `arc` the one block that rejects an unknown key, because `step` instead of `splay_deg` would
  otherwise fall back to the default angle and silently move every cabinet in the group

### Changed

- **Contact is solved on the plan-view outline of the *tilted* cabinet, including its grille frame**, not
  on the nominal trapezoid. Both terms matter and neither is cosmetic: the frame is a full-width slab
  across the front, so the taper only runs over `depth − inset` and at the bare trapezoid's 16.95° the
  built meshes overlap by 3.5 mm; and tilt swings the front-top edge forward, so a concave cluster solved
  flat drives its cabinets **20 mm into each other** at 4.4°. Tilt still breaks full-face contact — the
  seam opens into a V, ~22 mm at the top of an M2122 — which is expected and left alone
- **The rig's front face now accounts for every rotation that does not depend on the focus**, arc yaw
  included. A concave arc's outer cabinets stand well in front of its middle one, and measuring them as
  unrotated boxes put the front face 62 mm too far back, landing the focus that much further out than the
  scene asked for. No shipped scene's aiming figures move — verified against `scene:build --dry-run`
  before and after
- Expansion happens in one place. `repeat` and `arc` both produce `PlacementCopy` objects, so the
  placement loop and the front-face walk no longer carry a copy of the arithmetic each

### Fixed

- **`PlacedDevice::box()` treated every trapezoid as a full-width box**, against this class's promise of an
  exact rotated footprint. A concave arc came out 9% too wide, and `aimed-close`'s reported footprint drops
  from 4.36 m to 4.26 m now that its toed-in tops are measured on their real taper
- **`aimedAt()` inverted the down-tilt of a cabinet rolled 180°**, aiming it nose-*up* by exactly the angle
  it should have been nose-down. No committed scene triggered it — the mirrored sub rows are not aimed —
  but an arc plus roll would have
- A yaw of `-0.0` serialised as `-0` into the build plan, so a concave arc's middle cabinet would have
  shown up as a diff every time a scene was rebuilt

## [0.16.0] - 2026-07-30

### Added

- **`scenes/full-rig-three-tier.yaml`** — the mirrored Flexy sub wall, a row of six Achenbach 18s on top
  of it, and the three M2122 tops above that, each aimed at the crowd focus. The extra tier lifts the tops
  from 1.53 m to 2.15 m, which takes their down-tilt from 1.1° to 4.4° and their toe-in from ±9.92° to
  ±8.41° — both computed from `aim: focus`, with no angle written in the scene
- The tops sit over the outer Achenbachs rather than over the ends of the sub wall, because the middle row
  is 3.70 m against the wall's 4.26 m and the old positions would have left them overhanging its edges
- `docs/scenes.md` uses the new scene to show what raising a rig does to its aiming, next to the existing
  numbers for the same focus at 1.53 m

### Notes

- The scene calls for **six Achenbachs and only four exist**, so `scene:build` warns that it exceeds the
  inventory. That is deliberate — it is the configuration that was asked for, and the file says in a
  comment how to cut it back to four. It also depends on borrowed gear, which `scene:build` reports too

## [0.15.3] - 2026-07-30

### Changed

- **All five Tecnare horns are square-mouthed and round-throated**, confirmed by the owner against the
  cabinet — so the mid horn's round throat, added in 0.15.0 as an inference from its 2″ compression-driver
  bolt flange, is now an observation, and the two 1″ HF phase plugs get the same treatment instead of
  staying plain four-sided flares
- `docs/sources.md` separates what is now known about this layout from what is not: the **shapes** are
  confirmed, the **sizes** — every mouth, centre height, throat, depth and flare law — are still derived
  from the outer dimensions and the driver sizes, which is why the layout's provenance stays `estimated`

## [0.15.2] - 2026-07-30

### Changed

- **The 18sound's 56 mm front lip is confirmed against the real cabinet** by the owner, so its deep-set
  horn and driver are a real feature of that cabinet and not a modelling artifact. The open question in
  `TODO.md` is closed, and the confirmation is independent support for the CAD's 426.8 mm depth over the
  drawings' 335 mm — the same conclusion the panel dimensions already reached, now from a second direction.
  `provenance.dimensions` stays `plans`: a confirmed lip is not a tape measure over the whole cabinet

## [0.15.1] - 2026-07-30

### Fixed

- **The Achenbach's cone sat 18 mm too deep and was 37 mm too wide.** Both numbers were guesses, and
  measuring the FreeCAD mesh replaced them: the baffle carrying the cut-out is the cabinet's front-most
  panel, so `inset_m` is 0, not one panel thickness; and the cut-out is Ø416 mm, not the driver's nominal
  18″ (457 mm) frame diameter. The oversized cone had its rim buried in the baffle, so it read as a disc
  set back in a hole instead of a driver filling one. Its layout provenance goes from `estimated` to
  `plans`
- The 18sound's `inset_m` is the measured 0.0558, not the rounded 0.056

### Changed

- `docs/sources.md` records what the 18sound's mesh actually says about its front: the baffle is 55.8 mm
  behind the front-most point because the top and bottom panels project forward on a curved edge, which is
  why its openings look deeper than every other cabinet's. Its features are flush with that baffle — the
  baffle is what is deep. Whether the lip is real is now an open question in `TODO.md`, because it is also
  where this cabinet's disputed depth lives
- `scenes/eighteensound-detail.yaml`, since that step only reads from the side or up close

## [0.15.0] - 2026-07-30

### Added

- **`throat_profile`** — a horn's mouth and throat can now be different shapes, and the flare morphs
  between them along its length. `profile: pyramid` with `throat_profile: elliptical` is a horn with
  straight edges on the outside and a round throat, which is what a compression-driver horn is: the
  throat is a round bolt flange, so it cannot be square. Defaults to the mouth's shape, so a horn with
  one cross-section throughout is unchanged
- Rings on a morphing flare are oversampled to a multiple of `2 × sides`, so the polygon's corners and
  the middles of its walls both land on sample points — without that the flat end comes out faceted.
  Only horns that ask for two shapes pay the extra polygons

### Changed

- **The 18sound's horn is elliptical**, not the octagon its baffle cut-out is — owner, against the
  cabinet. The cut-out in the CAD is unchanged, so the model now shows a round horn behind an octagonal
  hole, which is what the cabinet looks like
- **The Tecnare's two LF horns are straight-edged at the mouth and round at the driver** — owner, against
  the cabinet. The mid horn gets the same treatment by inference from its 2″ compression-driver throat,
  which is recorded as inference rather than observation in the spec and in `docs/sources.md`

## [0.14.0] - 2026-07-30

### Added

- **`audio.layout` — the openings on a cabinet's front baffle.** Until now the CAD meshes cut the holes
  and left nothing behind them, so any view that saw into a cabinet saw an empty box. A layout lists
  driver cones and horn flares in the **baffle frame** (origin at the centre of the front face), each
  with its own `depth_m`, and the whole layout carries its own `provenance` — these are the easiest
  numbers in the repo to invent
- **Driver cones** built as a lathed shell: frame lip, surround half-roll crested level with the baffle,
  the cone proper, and a dust cap doming forward at its centre. The crest is what makes it read as a
  driver rather than as a funnel
- **Configurable horn mouths and flares.** `profile: pyramid` with any `sides` (4 for the usual
  rectangular flare, 8 for an octagon) or `profile: elliptical`; `flare: linear` for a straight-walled
  conical horn or `flare: exponential`, where the area grows exponentially with depth as most real horns
  do. Both laws meet the declared mouth and throat exactly, so the choice changes the walls and never the
  sizes, and the defaults (`pyramid`, 4 sides, `linear`) reproduce the previous geometry
- `driver_in` on a horn puts a cone at its throat and bores the driver chamber through to it — what a
  horn-loaded 12″ actually looks like
- `inside: <id>` nests a feature at another horn's throat, facing forward, which is how "the HF horn sits
  inside the LF horn as a phase plug" stays in the data instead of in two hand-matched sets of coordinates
- Layouts for the three cabinets whose drivers are visible from outside: the Achenbach's 18″ cone
  (`estimated`), the 18sound's octagonal horn and 15″ cone (`plans` — both openings are dimensioned in
  the kit drawings), and the Tecnare's mid horn over two 12″ LF horns with a 1″ phase plug in each
  (`estimated`, nothing measured). The Flexy and SKRAM get none: their drivers sit deep in a folded horn
  path and are not visible
- `sdwa5-cone` and `sdwa5-horn` materials
- New validator rules for all of the above, and a `scenes/tecnare-detail.yaml` for inspecting the one
  cabinet whose baffle is entirely generated

### Changed

- **The three Tecnare tops are one spec at quantity 3.** The two factory cabinets and the self-built copy
  are geometrically identical, so modelling them twice was wasted work. `tecnare-m2122-clone` is gone and
  the five scenes that placed it now place `tecnare-m2122`. The cost is that `build` and `provenance` can
  only describe one of the two cases, so the self-built one is recorded in the spec's notes as prose
  rather than as data — split the spec again if that starts to matter. Weights and totals are unchanged:
  68 kg × 3 is what 68 × 2 plus the clone's 68 already was
- A generated cabinet's baffle openings are **cut into the shell**, so a horn's flare is carved out of the
  cabinet and its own material forms the walls — which is what a wooden horn is. A cabinet with a
  `mesh_override` already has its holes, so nothing is cut and only the parts behind them are added
- The generated grille panel is skipped when a spec has a layout: a solid panel across the front would
  hide every horn. The frame bars stay

### Fixed

- **`models:build` reported success on a build that had crashed.** Blender can exit 0 after a Python
  error, leaving the previous build's files in place, so checking that the outputs exist passed on stale
  ones. They now have to be newer than the run that claimed to write them

## [0.13.1] - 2026-07-30

### Fixed

- **Aim lines drew a floor marker under a ray that never reached the floor.** A nearly level ray meets
  the floor a very long way out — the 10 m focus gives ~1.1° of tilt from 2 m up, which needs **103 m** —
  so the 40 m cap truncated it in mid-air while `hits_floor` still said true, and the marker landed on
  the floor beneath a line that stopped above it. Beyond the cap the ray is now truncated with no marker,
  so a marker always means the ray genuinely lands there. Reported from the render, where the
  disconnected dots were visible

## [0.13.0] - 2026-07-30

### Added

- **`scene:render --aim-lines`** — draws a glowing rod from the centre of each cabinet's front face along
  the direction it points, stopping where it meets the floor and leaving a marker there. `--aim-lines`
  covers tops, `--aim-lines=all` includes the subs. Off by default
- The rod follows each cabinet's **actual** front axis rather than the point it was told to aim at, so it
  turns "these all aim at one place" from a claim into something visible — and a mistake in the aiming
  shows up instead of being drawn over
- Camera framing widens to include the rays when they are on, since where they converge and land is the
  point of asking for them
- `PlacedDevice::frontFaceCentre()` and `frontDirection()`; four new tests (suite now 143)
- `scenes/aimed-close.yaml` — the same rig aimed at 2 m / 1 m instead of 10 m / 1.8 m: 18–22° of tilt and
  ±36° of toe-in. Kept as a contrast case, with a note that it is not a setup anybody would build

### Fixed

- `README.md` was stale: total weight 1834 → **1856 kg**, the 18sound row still had the superseded
  0.420 × 0.800 × 0.335 and 30 kg, and SKRAM's height and depth were the wrong way round. The current-state
  table now also says **which devices have real CAD and which are still generated blocks**, which is the
  question most often asked of this repo
- `README.md` gained `ddev mesh-convert`, the `meshes/` directory and the `--aim-lines` flag; `docs/scenes.md`
  documents aim lines; `docs/inventory.md` carries the 18sound's corrected 41 kg
- `TODO.md`: splayed sub arcs no longer need schema work — `pitch_deg`, `roll_deg` and `aim: focus` cover it

## [0.12.0] - 2026-07-30

### Added

- **`pitch_deg`** on a placement — down-tilt, so tops can be aimed into an audience instead of over it
- **`aim: focus` and a scene-level `focus` block** — `{ distance_m: 10.0, height_m: 1.8, x_m: … }`. Every
  cabinet that aims at the focus works out **its own yaw and down-tilt** from where it actually stands,
  which is what "all the tops point at the middle of the dancefloor" means in practice. Stated as a
  distance and a height rather than absolute coordinates, because that is how the decision is really made
- **`aim_at: [x, y, z]`** for naming a point outright
- `scenes/full-rig-aimed.yaml` — the mirrored wall with all three tops aimed at one point: symmetric
  ±9.92° toe-in and ~1.1° down-tilt, resolved per cabinet
- `src/Scene/Orientation.php` and `src/Scene/Focus.php`; 8 new tests (suite now 141)

### Changed

- **Rotation handling is now exact.** `PlacedDevice` rotates the cabinet's eight corners through pitch,
  roll and yaw in Blender's own order, and everything derived from that — the lift back onto its slot,
  the height for `on` stacking, the report's footprint and the camera framing — reads the same box. The
  previous code approximated a yawed cabinet as `max(width, depth)` across and ignored pitch entirely
- Distance to the focus is measured from the rig's **front face**, computed from ground positions and
  unrotated depths only, so it cannot become circular with the aiming it feeds

### Notes

- ~1.1° of down-tilt at the default focus is correct, not a bug: a top whose middle is 2 m up, aiming at
  1.8 m from 10 m away, drops 20 cm over that run. A nearer or lower focus steepens it

## [0.11.0] - 2026-07-30

### Added

- **`achenbach-18` and `eighteensound-2way-15` now use their own CAD**, converted from the FreeCAD
  models in Drive with `ddev mesh-convert`. Four of six devices carry real geometry now: the Achenbach
  contributes its driver cut-out and corner braces, the 18sound its octagonal horn cut-out, Ø353 mm
  driver hole and two Ø100 mm ports
- The Achenbach's CAD measures 600 × 700 × 600 mm, matching the spec **exactly** — a third independent
  confirmation after the panel geometry and lsv-achenbach.de's published panel sizes

### Fixed

- **`eighteensound-2way-15` outer dimensions were wrong: 0.4656 × 0.836 × 0.4268 m, not
  0.420 × 0.800 × 0.335.** The published drawing's numbers are *partial*: its dimension lines sit
  inside the overhanging top and bottom panels (parts F and E), so 420 is the baffle width, 800 the
  side-panel height, and 335 a depth that stops short of the back. The CAD agrees with the drawings
  wherever they measure the same feature — its baffle is 418.3 mm and its side panels exactly 800 mm —
  and is 46 mm wider, 36 mm taller and ~92 mm deeper overall
- The same spec's weight estimate recomputed for the larger box and the CAD's 18 mm panels (the note
  specifies 15 mm Baltic birch, so this build deviates): **41 kg**, up from 30. Library total is now
  1856 kg

### Notes

- `mesh-convert` finished the Achenbach in seconds this time, having timed out past ten minutes before
  the solids filter was added in 0.9.0 — it was meshing datum planes and intermediate features

## [0.10.0] - 2026-07-30

### Added

- **`roll_deg` on a placement** — turns a cabinet over about its own front-to-back axis, so 180 leaves
  it facing forward but upside down. That is how horn-loaded subs are stacked in mirrored pairs, with
  two mouths meeting to behave as one larger one
- `scenes/full-rig-mirrored.yaml` — `full-rig.yaml` plus that one line, so the two can be compared
- The compiler **lifts a rolled cabinet back onto its slot**: geometry runs z = 0 to height in a
  cabinet's own frame, so turning it over would otherwise sink it through the floor. The lift is
  computed from the rotated corners, so it is right for any angle, not just 180
- `on` stacking, the scene report's footprint and the camera framing all use the **rolled** extent, so
  a cabinet on its side counts as tall as it is wide and anything stacked on it still lands correctly
- Four tests covering the lift, stacking onto a rolled cabinet, the swapped extent at 90°, and the
  footprint. Suite is now 133

### Notes

- Which row to flip is not obvious: a Flexy's mouths sit in the *lower* part of its face, so the
  **bottom** row is the one to turn over. Flipping the top row instead drives the mouths apart —
  documented in `docs/scenes.md`, having been rendered wrong the first time

## [0.9.1] - 2026-07-30

### Fixed

- **SKRAM orientation.** Its CAD is authored for machining (the Fusion files are named "CAM"), so it
  arrived lying on its back with the vents pointing away. `rotate_deg: [0, 0, 180]` stands it up and
  turns it round: the front now shows the vent array with the 21″ cone visible through it, and the
  small recessed rectangles are handles on the top and sides — not vents, as first assumed
- **SKRAM height and depth were the wrong way round**: 0.914 m high × 0.813 m deep, not the reverse.
  The cut list supports it — its largest panel is 914 × 813, which fits sides of 914 high by 813 deep.
  `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` says the opposite, and is noted in the spec as
  contradicting it; a tape measure settles it. **This changes stacking heights**, so it matters

## [0.9.0] - 2026-07-30

### Added

- **`ddev mesh-convert <file>`** — meshes `.FCStd`, `.step` and `.iges` into something Blender can
  read, which was the bottleneck for every remaining cabinet. FreeCAD runs in a throwaway container;
  the command is a ddev **host** command because `bin/console` runs in the web container, which has no
  docker, and baking FreeCAD into the ddev image would add over a gigabyte for a rarely-used step
- `tools/freecad-export.py` — exports **solids only** (a FreeCAD document is full of datum planes and
  axes, and meshing those inflates the bounding box until the dimension check fails for no real
  reason), prefers a finished `PartDesign::Body` over its intermediate pads, and prints the bounding
  box in millimetres so `units` and `rotate_deg` can be chosen without guessing
- **SKRAM now uses Josh Ricci's own CAD.** The SKRAM DIY Package's `STEP Files/SKRAM 3D.step` — a
  52-solid full assembly — converted and wired up: recessed hatches with bolt holes, handle grooves
  and internal bracing instead of a block
- `scenes/skram-detail.yaml`

### Changed

- `docs/sources.md`: SKRAM moves from "no source anywhere" to Ricci's CAD, with what the package
  actually contains (full and per-panel STEP, 29 DXF, Fusion, SolidWorks, cut sheet)
- SKRAM's `mesh_override` carries `tolerance_m: 0.011`: the CAD measures 619.6 × 812.8 × 924.4 mm —
  height exactly 32″, but width and depth each 10 mm over the published 24″ and 36″. The nominal
  figures stay in the spec because the cut list corroborates them, and the tolerance admits the CAD
  until somebody measures the built cabinet

### Known issue

- The SKRAM mesh's **orientation is unconfirmed**. Its dimensions verify, but which face carries the
  horn mouth has not been checked against a real cabinet — `rotate_deg: [90, 0, 0]` currently puts the
  open chambers upwards, which is probably wrong. Tracked in `TODO.md` item 4.2

### Notes

- Tecnare's own downloads were checked directly: data sheets, manuals, rigging guides and firmware
  only — no CAD, DWG, DXF, STEP, EASE or GLL for any product, discontinued ones included. The M2122
  can only be measured
- `Achenbach 18` identified as an [LSV Achenbach](http://lsv-achenbach.de/plaene/plan_sub18.htm)
  design: bassreflex, RCF L18P300/L18P200, 155 l net. Its published panel sizes (564 × 700 sides,
  564 × 564 front/back) match what was extracted from the FreeCAD file, independently confirming
  0.600 × 0.600 × 0.700 m. Plans are PDF only
- Eighteen Sound offers only a PDF for the 15″ 2-way — most of their kits ship a blueprint archive,
  this one does not

## [0.8.0] - 2026-07-30

### Added

- **The Flexy has real geometry.** `flexy-folded-horn-hybrid` now uses the design's own CAD via
  `mesh_override` — four folded-horn mouths and the throat flares instead of a black box. Found in a
  second, previously unexamined CAD folder in Drive (`Medien/Bildbearbeitung/Merch/CAD/`) as a 1:10
  print model: watertight, 2817 faces, and only 3 mm narrower than the spec, so it passes the default
  tolerance untouched
- `scenes/detail-check.yaml` — one of every device in a row. Render it with `-l flat` after touching
  the geometry builder and every chamfer, grille and horn mouth is visible side by side
- `meshes/README.md` — what override meshes are, why they are not committed, the exact `rclone`
  command to fetch each one, and how to export a `.FCStd` from FreeCAD
- `docs/sources.md` gained a per-device 3D-geometry table: the whole Shared Drive was swept for 3D
  formats, so it records what exists, what is unusable and what has no source at all

### Changed

- **A declared-but-absent override mesh is now a warning, not an error.** Override meshes are
  third-party CAD this repository deliberately does not commit, so a spec naming a file the current
  checkout lacks is normal: `specs:validate` warns, and the build falls back to the generated block.
  Previously this made the whole library invalid for anyone without the file
- `Violation` carries a severity, and commands refuse on errors while still printing warnings
- `tools/check-glb.py` honours the override's own `tolerance_m` instead of the tight default, and
  reports the worst axis. It was failing every override that was not sub-0.1 mm exact, which made it
  useless for exactly the models it most needed to check
- **Framing fits the bounding box as the camera sees it, not the bounding sphere.** A sub wall is wide
  and shallow, so its sphere is far larger than its silhouette and the old fit pushed the camera back
  until the rig was a smudge in the middle of the frame. Every render is now noticeably tighter
- glTF `extras` records the override's basename, units and tolerance — the basename only, because a
  local absolute path has no business travelling inside a `.glb`

## [0.7.0] - 2026-07-30

### Added

- **`scene:render`** — renders an assembled scene to `build/renders/<id>-<camera>.png`. A preview now
  costs one command and no Blender knowledge; the 17-cabinet rig takes about 8 seconds on the
  container's CPU
- **Camera presets** (`-c`): `three-quarter` (default), `front`, `side`, `top`, `crowd`. Each is a
  *direction*, not a position — the distance is computed from the scene's own size, so the same preset
  frames a single floor monitor and a fourteen-wide sub wall equally well, and no scene ever needs a
  camera placed by hand. (This release fitted the bounding sphere; 0.8.0 replaced that with a box fit.)
- **Lighting presets** (`-l`): `studio` (default), `stage` (warm key, coloured rims), `daylight` (sun
  and sky — where most SdWa5 events are), `flat` (even and shadowless, for inspecting a chamfer or a
  grille inset). Light positions are multiples of the scene radius and area-light power scales with its
  square, so a big rig is not dark at the settings that light one cabinet
- `--samples`, `--resolution`, `--no-ground`, `--out`, and `--presets` to list what is available
- `src/Render/` (`CameraPreset`, `LightingPreset`, `RenderPlan`) and `blender/render_scene.py`. The
  framing maths lives in PHP so it is unit-testable — camera framing is exactly the kind of thing that
  drifts silently when nobody can assert on it
- `tests/Render/RenderPlanTest.php` — 12 tests covering bounds, framing distance scaling with scene
  size, per-preset placement, eye-height handling and light power scaling. Suite is now 127 tests
- `docs/scenes.md` gained a full Rendering section; `docs/blender.md` no longer says lighting has to be
  set up by hand

### Changed

- The `crowd` preset frames deliberately tighter than 1:1 (margin 0.92): standing in front of a 4 m sub
  wall it fills your view, and a shot that politely fits it all in undersells it

## [0.6.0] - 2026-07-30

### Added

- **`scene:build`** — a PA setup written as YAML in `scenes/` becomes `build/scenes/<id>.blend` with
  every cabinet placed at true scale. A setup that worked is now a commit rather than somebody's
  memory, and next year's variation is a diff
- `scenes/full-rig.yaml` — the whole PA in about twenty lines: a 14-cabinet sub wall as two stacked
  rows of seven, plus the three M2122 tops. 17 cabinets, 1394 kg, 2.486 m tall, 4.26 × 0.96 m footprint
- **`on:` stacking** — a placement sits on an earlier one and its height is worked out from the specs,
  so no z coordinate is ever written into a scene. When somebody finally measures the Flexys, every
  stack in every scene corrects itself instead of quietly becoming wrong
- **`repeat: { count, step }`** — a sub wall is two lines instead of fourteen entries. A repeated row
  can be stacked on another repeated row
- Scene report, printed before Blender runs: cabinet count, total weight, tallest stack, floor
  footprint from cabinet extents, and per-device and per-owner breakdowns. Plus three warnings that
  are cheap here and expensive on site — using **more cabinets than the inventory has**, depending on
  **borrowed gear** (any device whose `owner` is not `sdwa5` — what that field was added for), and
  positions resting on **un-measured** cabinets
- `--dry-run` reports without touching Blender, which is how CI checks scenes
- `src/Scene/` (`Placement`, `SceneSpec`, `SceneLoader`, `SceneCompiler`, `PlacedDevice`,
  `SceneReport`) and `blender/build_scene.py`. All the arithmetic lives in PHP so stacking and
  repetition are unit-testable without Blender
- `docs/scenes.md`; `ArrayReader::numberList()`; 22 new tests (suite now 116)

### Changed

- Each device's collection is appended **once** and instanced per placement, so a 14-cabinet wall
  costs one copy of the geometry — the shipped 17-cabinet scene is under 100 KB
- `scene:build` refuses to assemble a scene from stale or missing models, and from invalid specs
- CI now also runs `scene:build --dry-run`

## [0.5.0] - 2026-07-30

### Added

- **`mesh_override` now works.** A spec can point at a real mesh and it replaces the generated shell
  outright — grille and handle recesses included, since real CAD models those better than the builder
  can. `blender/lib/mesh_import.py` imports `.obj`, `.glb`, `.gltf`, `.stl`, `.ply` and `.blend`
- `mesh_override` accepts a bare path, or an expanded form with `units` (m/cm/mm), `rotate_deg` and
  `tolerance_m`. Real CAD arrives in whatever units and orientation its author used, so the spec has
  to say which rather than the builder guessing
- **The spec stays the authority:** after importing, scaling and rotating, the builder measures the
  mesh and fails the build if any axis is off by more than the tolerance, printing all three numbers.
  A silently mis-scaled cabinet still looks like a cabinet and would quietly poison every setup built
  from it
- `src/Spec/MeshOverride.php`; `SpecValidator` rejects a missing path, a format Blender cannot import
  (naming `.FCStd` explicitly, since that is the likely mistake here), unknown units and a negative
  tolerance
- `/meshes/` added to `.gitignore` — override meshes are third-party files and stay out of git

### Changed

- The Flexy's CAD mesh was tested against this: it imports correctly with `units: mm` and
  `rotate_deg: [90, 0, 90]`, and its depth and height match the spec exactly, but it is **18 mm
  narrower** than the cabinet — a second, independent confirmation that the mesh omits something.
  Its `mesh_override` therefore stays `null` until that is reconciled, and the finding is recorded in
  the spec and in `TODO.md`
- `docs/spec-format.md` gained a "Mesh overrides" section; `docs/conventions.md` no longer describes
  `mesh_override` as unimplemented

## [0.4.0] - 2026-07-30

### Changed

- **`provenance` is now tracked per field** — `provenance.dimensions` and `provenance.weight`, with a
  single value still accepted as shorthand for both. They diverge in practice: a hanging scale
  settles a cabinet's weight in a minute, while taping fourteen subs is an afternoon. With one field
  for both, a weighed-but-unmeasured cabinet still reported `plans` and the easy half of the work
  showed no progress at all
- `catalog` reports "Dimensions measured: n of m" and "Weights measured: n of m" as separate counts,
  and the Provenance column shows `plans/estimated` when the two differ
- `eighteensound-2way-15`, `achenbach-18` and `tecnare-m2122-clone` now declare
  `weight: estimated` against `dimensions: plans`/`datasheet`, which is what was true all along and
  previously only visible in prose
- Only an estimated **shape** gets the orange viewport tag; an estimated weight does not distort the
  model, so it is reported by `catalog` rather than drawn
- `SpecValidator` checks the clone/`clone_of` rule against each provenance field separately
- `flexy-folded-horn-hybrid`: width refined to 0.591 m
- Stale `tree/master` links updated to `tree/main` after the parent repo's branch rename

### Added

- `src/Spec/ProvenanceSet.php` with `weakest()`, `isFullyMeasured()` and a compact `label()`
- `ArrayReader::isSection()` — needed for fields that accept either a scalar shorthand or an
  expanded mapping
- `tests/Spec/ProvenanceSetTest.php`; suite is now 92 tests

## [0.3.0] - 2026-07-30

### Added

- `specs/speakers/tecnare-m2122-clone.yaml` — three M2122-shaped tops exist, not two: two genuine
  Tecnare cabinets plus one self-built. Kept as its own spec rather than raising the factory pair's
  quantity, because build, provenance and weight all differ and a setup should be able to tell which
  cabinet is which. `Hardware Overview.xlsx` counts only the factory pair
- Inventory totals are now 6 specs, 25 cabinets, 1834 kg, 8.96 m³

### Fixed

- `flexy-folded-horn-hybrid`: width corrected to **0.590 m** (owner-confirmed, and what the
  comparison spreadsheet said all along). The CAD mesh in Drive reads 0.573 m, so it omits ~17 mm of
  the real cabinet; height and depth still come from the mesh, which agrees with the spreadsheet's
  rounded figures while being more precise

### Changed

- The two remaining estimated weights are now **calculated rather than guessed**, with the working
  written into the specs: plywood surface area × density, plus internal panels, drivers and hardware.
  The whole Shared Drive (1166 files) was searched for weight data and has none, and Eighteen Sound
  publishes no finished-cabinet weight for a DIY kit — a hanging scale is the only remaining source
- `docs/sources.md`: per-device rows updated; the unsourced weights are now a table with their basis
- `docs/inventory.md`, `README.md`, `TODO.md`: totals, the Tecnare split, and the measuring backlog

## [0.2.0] - 2026-07-30

### Added

- The real inventory, read out of the SdWa5 Shared Drive (`Hardware/Hardware Overview.xlsx` and
  `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx`): 5 enclosures, 24 cabinets, 1766 kg, 8.5 m³
  - `specs/speakers/flexy-folded-horn-hybrid.yaml` — 14×, 0.573 × 0.763 × 0.964 m, 85 kg; dimensions
    measured off the design's own CAD mesh
  - `specs/speakers/skram.yaml` — 2×, 0.610 × 0.813 × 0.914 m, 90 kg; dimensions cross-checked
    against both the cut list in Drive and Josh Ricci's published 24″ × 32″ × 36″
  - `specs/speakers/tecnare-m2122.yaml` — 2×, tapered 0.500 m front / 0.345 m rear × 0.960 × 0.520 m,
    68 kg, 60° × 40°; from the Tecnare L2122LT datasheet. First spec to use `shape: trapezoid`
  - `specs/speakers/eighteensound-2way-15.yaml` — 2×, 0.420 × 0.800 × 0.335 m; dimensions read off
    the FRONT and SIDE VIEW drawings of Eighteen Sound's 15″ 2 Ways Kit application note
  - `specs/speakers/achenbach-18.yaml` — 4×, 0.600 × 0.600 × 0.700 m; derived from the panel geometry
    in the FreeCAD model in Drive
- `owner` spec field (default `sdwa5`) — some cabinets at an SdWa5 event are lent by a member, and a
  setup that quietly depends on borrowed boxes can fall apart. `catalog` gained an Owner column and
  reports units and weight per owner: sdwa5 18 units / 1506 kg, sepp 6 units / 260 kg
- `docs/sources.md` now records, per device, exactly which file each number came from

### Changed

- `SpecValidator`: `provenance: datasheet`/`plans` only requires `clone_of` for a **clone**. Factory
  gear cites its own datasheet, so demanding an "original" there forced a fiction — found while
  entering the Tecnare, which is a bought cabinet
- `SpecValidator`: rigging-point bounds are not checked when a dimension is impossible. An inverted
  bounding box made every point look out of bounds and buried the one error worth fixing
- `docs/inventory.md` rewritten: what is in Drive, what is in the specs, what is still missing, and
  the configured read-only `rclone` remote
- `README.md`, `TODO.md`: current state is a real inventory rather than photo estimates; the measuring
  backlog is now item 1

### Removed

- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — the photo-estimated placeholders,
  superseded by the five real specs

### Notes

- Nothing has been **measured** yet: every dimension describes a design or a datasheet, and the
  weights for `eighteensound-2way-15` and `achenbach-18` are outright estimates. `catalog` reports
  the un-measured count on every run
- SKHORN, GHORN and OTHORN appear in the comparison spreadsheet with full dimensions but were
  evaluated, not bought — deliberately absent from `specs/`

## [0.1.0] - 2026-07-30

### Added

- Initial repository: spec-driven parametric 3D models of SdWa5 speakers and stage equipment, for
  Blender event previews and PA setup planning
- `specs/` — one YAML file per device as the single source of truth for dimensions, weight, rigging
  points, and the provenance of every number. Most SdWa5 cabinets are DIY clones of commercial
  designs, so `build`/`clone_of`/`provenance` record which original a cabinet copies and whether its
  numbers come from a datasheet, build plans, a measurement or a guess
- `specs/speakers/top-a.yaml`, `specs/speakers/sub-a.yaml` — worked examples estimated from
  `~/Documents/SdWa5/2024-07-17_pa-lautsprecher-foto.jpeg`, marked `provenance: estimated`
- `bin/console` — Symfony Console application with `specs:validate`, `models:build`,
  `library:build` and `catalog`
- `src/Spec/` — spec value objects, `SpecLoader`, and `SpecValidator` enforcing the shared
  conventions: positive dimensions, chamfer and grille inset within bounds, unique ids matching
  their filenames, valid subtype per category, `clone_of` present for clones, rigging points inside
  the cabinet, taper dimensions stated explicitly per shape
- `src/Build/` — `BuildPlan` (the PHP → Python hand-off), `BlenderRunner` (headless invocation with
  `--factory-startup` for reproducibility), `ModelBuilder` (up-to-date checks, output verification)
- `src/Catalog/CatalogRenderer` — equipment table with total weight, total volume and the count of
  devices that have never been measured
- `src/Process/` — `ProcessRunner`, `ProcOpenProcessRunner` and `BinaryChecker`, ported from the
  sibling `yt-dlp-tools` project
- `blender/build_model.py`, `blender/build_library.py` and `blender/lib/` — geometry, the shared
  material set, metadata and export. Box, trapezoid and wedge shells, chamfer, recessed grille
  behind a frame, handle recesses cut as applied booleans, rigging markers, and an orange tag on
  estimated cabinets
- `blender/build_library.py` also marks every device as a Blender collection asset with stable
  catalog UUIDs, so the library is draggable from the Asset Browser
- `tools/check-glb.py` — verifies an exported model against its own glTF `extras`: bounding box
  equals the declared dimensions, and the cabinet sits on the floor
- `tests/` — 83 PHPUnit tests mirroring `src/`, including validator rejection cases and the Blender
  invocation via a fake process runner; no Blender needed
- `.ddev/` — PHP 8.3 CLI project with Blender 4.3.2 installed into the web image, pinned to the
  version Debian 13 ships so container-written `.blend` files open on the host
- `.github/workflows/tests.yml` — PHPUnit and `specs:validate` on every push
- `docs/` — conventions, spec format, pipeline, Blender usage, measuring checklist, inventory and
  Drive access, sources and licensing, plus the generated `docs/catalog.md`
- `README.md`, `TODO.md`, `CHANGELOG.md`

### Notes

- Canonical model format is glTF 2.0 (`.glb`), chosen because it is Blender-native and the model
  format embedded in GDTF/MVR — the entertainment industry's scene exchange standard, which already
  covers truss and rigging and has audio speakers on its roadmap
- Nothing generated is committed: `build/` is gitignored and reproducible from the specs
