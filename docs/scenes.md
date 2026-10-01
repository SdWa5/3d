# Scenes

A PA setup written down instead of assembled by hand. One YAML file per setup in
[`scenes/`](../scenes); `scene:build` turns it into `build/scenes/<id>.blend`.

The reason to write a setup down rather than drag cabinets around in Blender: it becomes reviewable
and repeatable. A layout that worked at an event is a commit, next year's variation is a diff, and
"what if we used four fewer subs" is one edit and a rebuild.

## Example

```yaml
id: full-rig
name: "Full rig — 12 subs, 3 tops"

placements:
  - id: sub-row-bottom
    device: flexy-folded-horn-hybrid
    at: [-0.302, 0.0]
    row: { count: 6, gap_m: 0.02 }

  - id: sub-row-top
    device: flexy-folded-horn-hybrid
    on: sub-row-bottom
    at: [-0.302, 0.0]
    row: { count: 6, gap_m: 0.02 }

  - id: top-left
    device: tecnare-m2122
    on: sub-row-top
    at: [-1.8295, 0.0]
```

## Fields

| Field | Meaning |
|-------|---------|
| `id` | scene id; must match the filename |
| `name` | human label |
| `placements[].id` | name for this placement, referenced by `on`. Defaults to `placement-<n>` |
| `placements[].device` | a device `id` from [`specs/`](../specs) |
| `placements[].at` | ground position `[x, y]` in metres |
| `placements[].on` | sit on top of an **earlier** placement; z is worked out from the specs |
| `placements[].fly` | `{ height_m, point, id }` — hang from a point in the air instead. `point` names one of the device's `rigging.points`; `id` is what the weight is grouped under. Exclusive with `on`; see below |
| `placements[].extend_to_m` | the height a `truss`/`tower` device is cranked to, at most its spec's height. See [a tower cranked lower](#a-tower-cranked-lower) |
| `placements[].yaw_deg` | rotation about Z — aiming. 0 faces −Y, the convention every model uses. An `arc` supplies this instead |
| `placements[].pitch_deg` | down-tilt. Positive is nose-down, for aiming into an audience rather than over it |
| `placements[].roll_deg` | rotation about the front-to-back axis — 180 turns a cabinet upside down, 90 lays it on its side, and either way it keeps facing forward |
| `placements[].aim` | the name of a focus — `focus` for the single unnamed one, or any key of a named `focus` map. The compiler works out yaw *and* down-tilt |
| `placements[].aim_at` | `[x, y]` or `[x, y, z]` — aim at a named point instead |
| `stack.low_end` | `low` or `central` — where the lowest-reaching cabinets belong. See [where the low end goes](#where-the-low-end-goes) |
| `placements[].focus` | this placement's own focus points, in either form the scene's `focus` takes, measured from **its own** front face. Overrides the scene's for everything inside it. See [each system aims at its own focus](#each-system-aims-at-its-own-focus) |
| `focus` *(scene level)* | one focus, `{ distance_m, height_m, x_m }`, or a map of named ones, `{ near: {…}, far: {…} }`. Defaults: 10 m out, 1.8 m high, rig centre |
| `placements[].repeat` | `{ count, step: [x, y, z] }` — repeat along a stated vector |
| `placements[].lattice` | `{ count: [nx, ny, nz], gap_m, step_m, roll_cycle, cycle_axis }` — a 1/2/3-D grid, spaced from what it replicates, centred on `at` in x and y, stacking upward in z |
| `placements[].row` | `{ count, axis, gap_m, step_m, roll_cycle }` — a lattice with one open axis; `axis` defaults to `x` |
| `placements[].line_array` | `{ count, splay_deg, gap_m }` — a hang: elements chained below one another, each tilted further than the last. `splay_deg` is one angle or one per gap |
| `placements[].align` | `{ mode, width_m, across, inside, inset_m }` — how this tier is spread across a width, instead of stating `step_m`. See [align](#align) |
| `placements[].stack` | `{ from, max_width_m, min_width_m, max_height_m, interface_height_m, max_sub_height_m, target_sub_height_m, gap_m, slide_slack_m }` — a whole rig from constraints instead of a tier per row. Replaces `device` and any group. See [stack](#stack) |
| `placements[].in` | list of groups this one is nested **inside**, innermost first: `in[0]` wraps the sibling group, `in[1]` wraps that |
| `placements[].arc` | `{ mode, count, splay_deg, radius_m, gap_m }` — a group seated on an arc. Exclusive with `repeat`; see below |
| `placements[].arc.gap_m` | working gap between neighbours, in metres. Default 0 — cabinets touching |
| `aim_lines` *(scene level)* | `none` (default), `tops` or `all` — whether a render draws where cabinets point |
| `placements[].aim_lines` | `true` or `false` to overrule that mode for this group |
| `notes` | anything worth knowing |

Coordinates follow [conventions.md](conventions.md): metres, X right, Y depth, Z up, cabinets face
−Y. A placement's position is the **bottom-center** of the cabinet, matching the default origin.

## Why `on` and a group matter

**`on` means no height is ever written into a scene.** It reads the supporting cabinet's height from
its spec, so when somebody finally measures the Flexys and the height changes by 8 mm, every stack in
every scene corrects itself. Hard-coded z values would all quietly become wrong.

**A group makes a sub wall two lines.** Twelve cabinets as twelve entries would be unreadable and
unmaintainable; as two rows of six it is obvious what the setup *is*.

Every shipped wall uses `row`, which works its spacing out from the cabinet
([below](#lattices--a-grid-that-works-its-own-spacing-out)). `repeat` is the older shorthand that steps
along a vector you state yourself — still the clearest thing to write when the step *is* the decision, as
in `scenes/detail-check.yaml`:

```yaml
    repeat: { count: 2, step: [0.64, 0.0, 0.0] }
```

The difference is which way the dependency runs. A stated step means the scene has to be corrected when a
cabinet is measured; a derived one means the wall follows the spec. That is why the walls moved off
`repeat` when the Flexy count was corrected from 14 to 12 — a `count:` edit rather than a recomputed edge
position in seven files.

A grouped placement can be stacked on another grouped placement — the row above resolves against the row
below. Give the upper row its own `at` so it lands where you want; without one it inherits the position of
the supporting placement's anchor.

## Mirrored horn pairs

`roll_deg: 180` turns a cabinet over without turning it away, which is how horn-loaded subs get
stacked in mirrored pairs so two mouths meet and behave as one larger mouth:

```yaml
  - id: sub-row-bottom
    device: flexy-folded-horn-hybrid
    at: [-0.302, 0.0]
    roll_deg: 180            # turned over, so its mouths point up at the seam
    row: { count: 6, gap_m: 0.02 }

  - id: sub-row-top
    device: flexy-folded-horn-hybrid
    on: sub-row-bottom
    at: [-0.302, 0.0]
    row: { count: 6, gap_m: 0.02 }
```

`scenes/full-rig-arc.yaml` carries that one line on its bottom row — which is the
argument for keeping setups as files rather than as Blender scenes.

Which row to flip is not obvious and depends on where the mouth sits on the cabinet's face. A Flexy's
mouths are in the *lower* part of its front, so the **bottom** row is the one to turn over; flipping the
top row instead drives the mouths apart. Cheaper to discover in a render than on site.

Two things the compiler handles so this stays honest:

* A rolled cabinet is **lifted back onto its slot**. Geometry runs from z = 0 to the cabinet's height
  in its own frame, so turning it over would otherwise sink it through the floor.
* `on` and the reports use the **rolled** extent, so a cabinet on its side is treated as tall as it is
  wide and anything stacked on it still lands correctly.

## Aiming a cluster

Tops usually need turning inwards and tilting down. Both can be stated per cabinet:

```yaml
  - id: top-left
    device: tecnare-m2122
    on: sub-row-top
    yaw_deg: 10          # toe in
    pitch_deg: 3         # nose down
```

…but for a cluster it is easier to name the point they should all cover, and let each cabinet work out
its own angles from where it actually stands:

```yaml
focus:
  distance_m: 10.0       # out from the rig's front face, into the crowd
  height_m: 1.8          # ear height for a standing audience
  # x_m: 0.0             # optional; defaults to the rig's own x centre

placements:
  - id: top-left
    device: tecnare-m2122
    on: sub-row-top
    at: [-1.8295, 0.0]
    aim: focus
```

`scenes/full-rig-arc.yaml` does exactly that, and resolves to:

```
  top-left     x=-1.8295  yaw= +8.29°   pitch=+1.11°
  top-centre   x=-0.302   yaw= +0.00°   pitch=+1.13°
  top-right    x=+1.2255  yaw= -8.29°   pitch=+1.11°
```

Symmetric toe-in, and the centre cabinet needs no turn because the default focus x *is* the rig's centre.

Worth reading the pitch figures honestly: ~1.1° is almost nothing, and that is correct — a top whose
middle sits 2 m up, aiming at ear height 10 m away, drops only 20 cm over that distance. Bring the focus
closer or lower and the tilt steepens; that is the trade-off aiming actually is.

Raising the tops does the same thing. `scenes/full-rig-arc.yaml` puts a row of Achenbach 18s
between the subs and the tops, which lifts the tops from 1.53 m to 2.15 m, and the same focus then
resolves to:

```
  top-left     x=-1.852   yaw= +8.41°   pitch=+4.35°
  top-centre   x=-0.302   yaw= +0.00°   pitch=+4.40°
  top-right    x=+1.248   yaw= -8.41°   pitch=+4.35°
```

Four times the down-tilt for 62 cm of extra height, and slightly less toe-in because the narrower middle
row pulls the outer tops inwards. Nothing in the scene states an angle: both changes fall out of `aim:
focus` on its own.

Notes on how it behaves:

* **Distance is measured from the rig's front face**, not the world origin, so a deeper rig does not
  quietly pull the focus closer.
* **Aim is resolved per copy**, so a `repeat`ed row of tops each turns towards the target rather than all
  inheriting the first one's angle.
* **Aim is measured from the cabinet's mid-height**, roughly where it radiates from — not from its base,
  which would make a stacked top tilt as if it stood on the floor.
* Combining `aim`/`aim_at` with `yaw_deg`/`pitch_deg` is rejected rather than silently resolved one way.
* An angled cabinet is **lifted back onto its slot** and reports its **exact rotated footprint**, so
  stacking and the camera framing stay correct.
* An **arc** keeps its own yaw and takes only the down-tilt from the aim — see below.

## Arcs — a group on one placement

`aim: focus` turns cabinets towards a point but leaves them side by side, parallel. A cluster of tops is
not that: it is a fan, each cabinet turned relative to its neighbour. `arc` places that fan as one group.

```yaml
- id: tops
  device: tecnare-m2122
  on: sub-row-top
  at: [-0.302, 0.0]      # the middle of the fan
  aim: focus             # the arc owns the yaw, the focus owns the down-tilt
  arc:
    mode: convex         # required: convex | concave
    count: 3
    # splay_deg: 25      # optional ─┐ mutually exclusive. Omitted means the tightest
    # radius_m: 1.5      # optional ─┘ the cabinets can be grouped
```

`scenes/full-rig-arc.yaml` resolves to:

```
  tops-1   x=-0.7167  y=+0.0633   yaw=-17.35°   pitch=+1.19°
  tops-2   x=-0.3020  y= 0.0000   yaw=  0.00°   pitch=+1.13°
  tops-3   x=+0.1127  y=+0.0633   yaw=+17.35°   pitch=+1.19°
```

**A tapered top's taper is its splay angle.** Nothing in that scene states an angle or a radius. The
M2122 narrows from 500 mm to 345 mm over its depth, and 17.35° is the one angle at which two of them sit
side by side with their side faces fully in contact — so it is both the tightest the group can be and the
default. Twist the fan wider and only the back edges stay together.

Which edges touch is a consequence of the shape, not a setting:

| | centre of curvature | touching | behind |
|---|---|---|---|
| `convex` | behind the cabinets | back edges | closed |
| `concave` | in front | front edges | wide open — 307 mm on an M2122 |

* **`mode` has no default.** An arc bent the wrong way is a quiet, serious mistake and there is no
  innocent guess.
* **A concave arc must state its size.** Its front edges touch at *every* angle, so "as close as
  possible" does not pin down an arc — there is nothing to derive. The error message suggests the
  cabinet's own horizontal coverage, which is the angle that actually spaces the coverage out.
* **`radius_m` is the arc the front faces sit on** — the radiating surface, and the same physical thing
  in both modes. For a convex arc it is bounded **above**, not below: a larger radius is a *flatter* fan.
* **`at` is the middle of the fan**, so a single top can be swapped for a group of three without the
  middle one moving. With an even count nothing sits on `at` itself.
* **`on:` an arc** stacks on its middle cabinet, the one standing on `at`.

### A straight row is an arc of infinite radius

`splay_deg: 0` bends the fan flat, and what is left is a row: no turn, no bow, and neighbours spaced by
exactly the room their outlines take up side by side.

```yaml
  arc:
    mode: convex         # required, but it makes no difference at splay 0 — a row bends neither way
    count: 6
    splay_deg: 0         # a straight row, spaced and centred from the cabinet itself
    gap_m: 0.02          # optional: 20 mm of air at every joint. Default 0, cabinets touching
```

This is the case where a scene stops carrying arithmetic somebody did by hand. `full-rig.yaml` used to
state `at: [-2.135, 0.0]` and `step: [0.611, 0, 0]` for its sub row, and both were derived numbers — seven
591 mm Flexys with a 20 mm gap make a 4.257 m wall, and centring it on `x = -0.302` put its left edge at
−2.135. It now says `count: 6, gap_m: 0.02` on the wall's centre and the numbers come back out of the
specs, which is what made correcting the Flexy count from 14 to 12 a one-character edit instead of a
recomputed edge position.

* **It is the same solve, not a special case.** As the splay closes, the arc's spacing along the seam
  converges on the row's: 0.4957 m at 1°, 0.49996 m at 0.01°, against the cabinet's own 0.5 m. Closing the
  angle tenfold takes the difference tenfold closer.
* **A row is centred on `at`**, like a fan — the middle cabinet for an odd count, the gap between the two
  middle ones for an even one. Swapping a single cabinet for a row of seven does not move it.
* **`gap_m` grows the outline, it does not pad the spacing**, so it means the same thing at every angle: a
  splayed seam opens by the gap measured across the seam rather than along x.
* **A cabinet with nothing to taper resolves to a row on its own.** A plain box, or a trapezoid whose back
  is as wide as its front, has no tightest *bend* — but two of them side by side are already in full face
  contact, which is the tightest convex arrangement there is. This used to be an error telling you to
  state an angle.

### Cabinets on their side

`roll_deg: 90` lays a cabinet on its side and, like 180, leaves it facing forward. An arc accepts it now,
because contact is solved on the plan outline of the *turned* cabinet: on its side an M2122's outline is a
plain 0.960 × 0.520 rectangle, the taper having rotated into the vertical where the plan view cannot see
it. So its flush arrangement is the straight row above, spaced 0.960 m — the height, which is what two
cabinets on their sides actually present to each other.

* **A quarter turn is the limit.** `roll_deg` itself takes any angle, but an *arc* wants a multiple of 90.
  In between, the outline's two flanks point at different centres of curvature, there is no arrangement
  that closes both seams at once, and the seam could only be solved loosely — an arc quietly leaving 14 mm
  of air down every joint is worse than one that refuses.
* **Roll never changes where a cabinet points.** It turns about the front-to-back axis, so `aim:` and
  `aim_at:` work on a cabinet on its side exactly as on an upright one. This was not true before: the
  rotation used to be composed in Blender's order, where the roll is applied *after* the down-tilt and
  turns nose-down into nose-up.
* **`pitch_deg` and `aim` now agree on a rolled cabinet.** They used to mean opposite things: a stated
  `pitch_deg: 5` with `roll_deg: 180` aimed at the ceiling while `aim:` on the same cabinet aimed at the
  floor. Nose-down is nose-down whichever way up the cabinet is.

### More than one focus

A rig usually needs two: the tops throw down the room and the near-fills cover the people against the
stage. One point cannot be both — aim everything at 10 m and the front rows sit under the tops' pattern
rather than in it; aim everything at 2 m and the back of the room gets nothing.

```yaml
focus:
  near: { distance_m: 2.0, height_m: 1.4 }   # chest height at 2 m, not ear height at 10
  far:  { distance_m: 10.0, height_m: 1.8 }

placements:
  - id: fills
    device: eighteensound-2way-15
    aim: near
  - id: tops
    device: tecnare-m2122
    aim: far
```

`scenes/full-rig-all-tops.yaml` is that rig. Neither aiming decision states an angle.

`scenes/full-rig-all-tops.yaml` is the same idea on the whole PA: every top we own on one three-tier stack,
the three M2122s spread at 1.55 m taking the far focus and the two 18sound 2-ways tucked inside them taking
the near one. It is also where the sharpest consequence of aiming shows up — see the note at the end of
[Lattices](#lattices--a-grid-that-works-its-own-spacing-out) about spacing cabinets that are toed in.

* **One focus or a map, told apart by shape.** If every value under `focus` is itself a mapping it is a map
  of named ones; otherwise it is the single unnamed one, which keeps the name `focus` so `aim: focus` still
  means it. Every scene written before names existed is unchanged.
* **A focus is a decision about the room, not about a cabinet**, which is why they are named at scene level
  rather than written into each placement. Two clusters sharing one near-field point is the normal case, and
  duplicated numbers drift apart silently.
* **Every distance is measured from the whole rig's front face**, not from each group's own. Otherwise "2 m
  out" and "10 m out" would be measured from two different places and neither could be read off the file —
  if the fills stand 0.8 m behind the sub wall's face, a group-relative 2 m focus is 2.8 m from the audience.
  `x_m` is the escape hatch for a group that is not aiming down the centre line.
* **An unknown name is refused.** `aim: focuss` used to mean *not aimed*: silently, with the cabinet left
  firing straight ahead and nothing in the output to show it.

One consequence worth knowing: `distance_m` comes off the front of the *whole* rig, so adding an end-fire
array that reaches 1.2 m forward pulls every focus 1.2 m further out with it.

### Tilt, and what it does to the seam

Tilting the group breaks full-face contact — the cabinets meet at a corner and the seam opens into a V,
about 22 mm at the top of an M2122 at 4.4°. That is expected and not corrected.

What *is* corrected is the arc's size. Tilting swings the front-top edge forward, and a concave arc solved
flat would then drive its cabinets **20 mm into each other** — a modelling error that looks perfectly
plausible in a render. So the arc is solved on the outline of the tilted cabinet, and it opens up by a few
centimetres when a tilt is applied. Three smaller things follow from the same solve:

* The **grille frame** counts. It is a full-width slab across the front, so the taper only runs over
  `depth − inset` and an M2122's flush angle is 17.35° rather than the 16.95° its bare trapezoid gives.
  At the bare angle the built meshes overlap by 3.5 mm.
* **A tilted arc that is also turned over is a different arc.** Tilt swings the front-top edge forward,
  and rolling the cabinet 180° swings it the other way, so a mirrored tilted group needs 73.7 mm *more*
  radius than a right-way-up one at 4.4°. Solved without the roll it came out that much too tight, with
  the cabinets driven into each other. No shipped scene tilts a rolled row, which is why nothing showed
  it.
* A `mesh_override` cabinet's real shell may not match its declared box, so contact is only as true as
  the spec's dimensions.

Two things worth knowing before checking a render: `chamfer_m` sets each corner back a few millimetres,
so a correct seam still shows a ~10 mm groove; and the whole group's contact is solved at one
representative tilt, which leaves a few tens of microns of slack between neighbours.

## Lattices — a grid that works its own spacing out

A `lattice` repeats whatever is nested inside it along one, two or three axes, and takes its spacing from
that thing's own size rather than from a step somebody worked out.

```yaml
  lattice:
    count: [2, 1, 3]     # required: cells along x, y and z. [7] and [7, 2] are also valid
    gap_m: 0.02          # optional: air at every joint. One number means x and y; [x, y, z] names all three
    # step_m: [0, 1.2, 0]  # optional: state a spacing per axis. 0 means "derive this one"
    # roll_cycle: [0, 180] # optional: turn alternate cells over — see below
    # cycle_axis: z        # required with roll_cycle when more than one axis has cells
```

`row: { count: 6, axis: x }` is the same thing with one open axis, which is the case that dominates. `axis`
defaults to `x`.

A twelve-cabinet mirrored sub wall can be written either way, and the difference is the shape of the
statement rather than the result. As **two** placements the tiers are two entries and the mirroring is a
`roll_deg` on one of them — that is what `scenes/full-rig-arc.yaml` does. As **one**, a `row` of six nests in
a two-tier `lattice` and `roll_cycle` turns the lower tier over, so the wall is one thing with a shape rather
than two things that happen to line up. Both derive their spacing, and both resolve to the same twelve
positions.

* **x and y are centred on `at`; z runs upward from it.** Centring x and y is what lets a single cabinet be
  swapped for a row of six without moving, and one formula covers an odd count (a cell on `at`) and an
  even one (the gap between the middle two). z cannot be centred: the base *is* the floor or the top of
  whatever the placement stands on, and centring three tiers would sink one through it.
* **The anchor is the middle cell of the *top* tier.** `on:` means "stand on top of that" and reads the
  anchor's own top, so anchoring the bottom tier would bury the next placement inside the lattice.
* **A plain `gap_m` never puts air under a cabinet.** One number means x and y only. A gap on x is air
  beside a cabinet, which is normal; a gap on z is air *under* one, which nobody wants by accident — write
  `gap_m: [0.02, 0, 0.10]` if that is really what you mean.
* **`step_m: 0` on an axis means "derive this one".** A step of zero is meaningless for a count above one,
  so it is a safe way to say it. `scenes/end-fire.yaml` uses it: the 1.20 m front-to-back spacing is a
  decision about frequency rather than about geometry, and x is left to work itself out.
* **A lattice does not turn its cabinets**, so `yaw_deg` alongside one turns the whole grid — unlike an
  `arc`, which owns the yaw and refuses to have one stated as well.
* **A stated `step_m` next to an *aimed* placement cannot be worked out from cabinet widths**, and this is
  the easiest trap in the file to fall into. Aiming toes a cabinet in, and a yawed cabinet occupies more x
  than it is wide. In `scenes/full-rig-all-tops.yaml` the near fill sits beside a top that is toed in 8.4°
  while the fill itself is toed in 20.8° — a 2 m focus is a hard turn — so half-widths plus a 20 mm gap
  (a step of 2.0944) drives the two cabinets **88 mm into each other**. `gap_m` has no such problem, because
  it is applied to the outline of the *turned* cabinet; it is only a step you state yourself that has to
  account for the turn. **Do not state one — use [`align`](#align) and let it be solved.**

`scenes/end-fire-lattice.yaml` says the same thing in one lattice instead of two: `count: [3, 2, 2]` is three
columns across, two deep and two tiers up — twelve Flexys, which is the whole holding, where the
four-by-three form asks for twelve and only eight exist. Two deep rather than three is the trade: depth is what
buys rear rejection, so this spends a cabinet of cancellation on a column of width and a tier of height. The
frequency decision is still the only stated number; x derives from the cabinet's own width plus a working gap,
and z says nothing at all.

### align

A group decides *what* is in a tier and how many. `align` decides *where across the width* they end up —
the alignment vocabulary text has, applied to cabinets.

```yaml
  - id: near-fills
    device: eighteensound-2way-15
    on: achenbach-row
    at: [-0.302, 0.0]
    aim: near
    align:
      mode: block
      inside: tops
      inset_m: 0.020
    row:
      count: 2
```

| Key | Meaning |
|-----|---------|
| `mode` | `center` (the natural spacing, what a row already did), `block` (justified — spread until the outer edges land on the width), `stereo` (two columns pushed apart, natural spacing kept within each) |
| `width_m` | the envelope stated outright — a stage, a truss |
| `across` | an **earlier** placement; its own outer edges are the envelope |
| `inside` | an **earlier** placement; the clear gap between its outermost cabinets' facing edges is the envelope |
| `outside` | an **earlier** placement to sit *beyond*; `inset_m` is then the clearance to keep past its outer faces, not a width to span |
| `clear_of` | an **earlier** placement whose *cabinets* these must not come within `inset_m` of. A collision clearance, not an envelope — see below for why that is a different question from `outside` |
| `inset_m` | taken off the envelope on **each** side. Default 0 |

Exactly one of `width_m`, `across`, `inside`, `outside` and `clear_of` is stated, and `center` takes none of them.

**`outside` and `clear_of` are not two spellings of one idea.** `outside` collapses its reference to the x span the
cabinets cover and gets past the whole of it. `clear_of` measures the cabinets themselves and only keeps off them. The
difference bites whenever the reference is *aimed*: a toed-in trapezoid's outermost point is a back bottom corner that
swings behind its neighbour, so two cabinets whose spans overlap can nest without touching. Three 0.5 m tops on a 1.5 m
step leave 1.0 m between neighbours, and a 0.6 m sub asked merely not to touch them sits in that gap where `outside`
would drive it outboard of all three — 1.74 m of span against 4.74 m.

**Why this is a feature and not arithmetic.** An aimed cabinet's outer edge cannot be computed from its
width. Aiming toes it in, a toed-in cabinet occupies more x than it is wide, and how far it toes in depends
on where it ended up — so "put this tier's edges on that one's" is a **fixed point**, not a formula. It is
worse for a trapezoid: once a Tecnare is toed in 11.4° its outermost point is its *back* bottom corner,
0.2206 m off centre against the 0.250 m half-width the arithmetic would use. The naive answer is wrong in
both directions depending on the cabinet.

`full-rig-stereo.yaml` used to carry three numbers — 0.8156, 2.1185 and 2.9709 — bisected by hand, and
`full-rig-all-tops.yaml` a fourth at 1.887. The compiler now bisects them itself, against the same rotated
boxes it uses for contact and camera framing, so they follow the specs when a cabinet is finally measured.
(`full-rig-quarter-turned.yaml` had copied 1.887 from the upright rig, where it was ~5 mm wrong: its tops
stand lower and so toe in differently. Solving it separates the two.)

**`across` and `inside` are different objects**, and the difference is not small: `full-rig-stereo`'s tops
are 4.678 m *across* and 3.628 m *inside*. A tier that sits beside another wants `across`; one that goes
*between* its outer cabinets wants `inside`.

**What `stereo` does.** It splits the count into two columns on the sign of each cabinet's natural offset —
so an even count divides in half with the middle left open, and an odd count leaves the one spare cabinet on
`at`, because that is the only placement for it that stays symmetric. Each column keeps its own `gap_m`
spacing; only the two columns move.

**Limits, all of them refusals rather than surprises:**

* `align` needs a single `row` or `lattice` with nothing nested in it. An `arc`'s spacing is its radius and
  a `line_array`'s is its splay — neither is a step to solve — and scaling a *nested* arrangement would
  stretch the inner group's spacing along with the outer one's.
* `step_m` on the same placement is refused: both decide the spacing. `gap_m` is fine — it is what `center`
  and `stereo`'s columns use, and under `block` it simply cancels.
* `across`/`inside` must name an **earlier** placement, the same rule `on` follows and for the same reason.
* An envelope narrower than the cabinets stacked on one spot is refused, naming both numbers.

### stack

A rig described by what it has to satisfy, instead of by a tier per row somebody wrote out.

```yaml
  - id: main
    at: [-0.302, 0.0]
    aim: focus                  # goes on the TOP tiers only; subs fire straight ahead
    align: { mode: block }      # optional: spread every tier onto the bottom one's edges
    stack:
      max_width_m: 3.70
      interface_height_m: 2.0
      gap_m: 0.02
      from:                     # low frequency first — the order is the fill order
        - flexy-folded-horn-hybrid
        - achenbach-18
        - tecnare-m2122
```

| Key | Meaning |
|-----|---------|
| `from` | the devices, **low frequency first**. Each entry is a bare id, or a mapping with `count` / `align` / `mix_with` — see below |
| `max_width_m` | how wide the stage or the truss lets the rig be. The row count falls out of it. **Optional, and leaving it out means no bound at all** rather than a wide one — see [an unstated width limits nothing](#an-unstated-width-limits-nothing) |
| `interface_height_m` | how high the sub stack's top face should reach, so the tops fire over a standing crowd. **Defaults to 2.0**, and it is an **optimum rather than a requirement** in a generated scene exactly as in a hand-written one — missing it warns; state `0` to stop aiming for it. See [the sub height band](#the-sub-height-band-which-is-an-aim-rather-than-a-gate) |
| `shape` | `pyramid` (no row wider than the one below), `free` (as wide as the bearing rule allows) or `v` (no row narrower than the one below). Default `free`, so an existing scene keeps the rig it had. **All three are width rules, in metres** — see [the three shapes](#the-three-shapes) |
| `max_sub_height_m` | how high the sub stack's top face is *allowed* to reach — the **mirror** of `interface_height_m`. Stating one changes what the solver optimises for and lets a row hold several device types. See [a ceiling on the sub height](#a-ceiling-on-the-sub-height). Missing it warns, in a generated scene as in a hand-written one — see [the sub height band](#the-sub-height-band-which-is-an-aim-rather-than-a-gate). A ceiling below the stack's own `interface_height_m` is an error: the two say opposite things about one number |
| `target_sub_height_m` | the sub/top transition to **aim at**, between the floor and the ceiling. **Defaults to 2.5**, the middle of the band, and it is what the solve optimises — where the two bounds are the band the miss is measured against. A preference and never a refusal. Written into a scene only when it is not the default. See [aiming the sub wall](#aiming-the-sub-wall-rather-than-settling-for-the-lowest-one) |
| `min_width_m` | a floor on the widest tier: how you ask for a wide short wall rather than a tall narrow one out of the same cabinets |
| `max_height_m` | a ceiling or a rigging limit |
| `gap_m` | working gap between neighbours in a row. A row the solver gaps out to its shape uses a wider one of its own, see [Gapping a row out to its shape](#gapping-a-row-out-to-its-shape) |
| `slide_slack_m` | how far sideways a badly-carried row may be moved to get it under something, in metres, or `.inf` for "bounded only by the stage". **Unstated means it may not move at all**, which is the right answer for a stack with a neighbour to slide into. A statement about the rest of the scene rather than about gravity — see [sliding a row rather than losing the rig](#sliding-a-row-rather-than-losing-the-rig) |

A `stack` replaces `device` and any group — both are decided by the solve, and stating one as well is
refused rather than quietly overruled. It expands into one ordinary placement per tier, numbered `main/1`,
`main/2`…, each standing `on` the one below, so anything later in the file can still say `on: main/4`.

Either a width bound or an interface height is enough on its own. **Both together is a solve that can fail**,
and it says so with the number it reached beside the number it needed.

`scenes/full-rig-all-speakers.yaml` is the worked example. Against the gear list it deals out:

```
  1  flexy-folded-horn-hybrid  x6    0.763 m    six fit: (3.70 + 0.02) / (0.591 + 0.02) = 6.09
  2  flexy-folded-horn-hybrid  x6    1.526 m    twelve owned, so a second row — and 1.526 MISSES 2.0
  3  achenbach-18              x6    2.126 m    which is what puts the Achenbachs in, and now it clears
  4  tecnare-m2122             x3               the tops, aimed
```

reached from the constraint rather than chosen, so it follows the specs when a cabinet is finally measured.

**Nothing in the solver assumes a grid, and it could not.** The five cabinets we own have five widths
(0.4656 / 0.500 / 0.591 / 0.600 / 0.610 m) and five heights (0.600 / 0.763 / 0.836 / 0.914 / 0.960 m), no
two of them multiples of anything. Every row count is worked out per cabinet and every height is summed
rather than multiplied — a fill that assumed a module would look right on the Flexys alone and fall apart
the moment an Achenbach or a SKRAM is in the same stack.

### Which way round the tops go

The alignment mode decides the **order** of the tops row, not just its spacing, and the two orders are mirror
opposites:

* **`stereo`** — the widest tops (the long throw) at the **outer ends**, the near-field fills **inboard of them,
  nearest the centre line**. The point of a stereo rig is the width of its image, so the main clusters go as far
  apart as the envelope allows and the fills cover the middle ground between them, which is also the shortest throw
  they make. An **odd cabinet goes to the centre line**, not to one side, so the two clusters stay equal: three
  M2122s and two 2-ways come out `M2122 + 2-way + [M2122] + 2-way + M2122`, a palindrome.
* **`center` / `block`** — the long throw **centred** with the fills outboard. The mono answer: one cluster carrying
  the room from the middle, fills widening the coverage. `block` then justifies the spacing so the row is spread as
  broad and as evenly as the width allows.

**The clusters are pushed apart until the row spans what carries it.** The slack — how much wider the support is
than the row — is handed out equally between neighbouring groups, so each cluster keeps its own internal spacing and
only the air between clusters grows. Bounded by the **support**, not by `max_width_m`: the outer tops' edges land on
the sub wall's edges and no further, because past that they are over nothing. Five tops on our own rig go from
spanning 2.011 m to 3.200 m. Before this a stereo row came out at natural spacing in the middle of the rig — the
ordering was right and the image was still narrow, because `align` cannot spread a tier that landed in several runs
and a mixed row always does.

**The groups are the row's own, not gravity's runs**, since 0.132.0. Gravity merges neighbouring cabinets of one
device on one support into one run without regard to where a group ends, so PSL's five EF 6, built as `2× | 1× | 2×`,
landed over three ESX columns as runs of 2, 2 and 1. Spread by those runs, the odd top sat 0.31 m right of the centre
line with a pair beside it, measured on the next event's combined rig while PSL still brought five. Now a run is cut where its cabinets change
group, each group moves as one, and the row is a pair at each edge with the odd top on the centre line, mirrored to the
millimetre. A row whose runs already were its groups, which is most of them, comes out as before.

**A true palindrome needs every top group's count to be even, or exactly one of them odd.** With two odd groups —
three M2122s and three turbo tops — the centre holds one of each and the row is symmetric everywhere except inside
that block. That is the least imbalance the counts allow: a centimetre in the middle rather than a whole cabinet at
one end.

### The three shapes

The bearing rule permits a row to be **wider** than the one carrying it — two thirds of a cabinet past each end —
and for a long time nothing said it should not be. That is how a stack ends up widening as it rises: 1.34 m on the
floor under 1.82, 1.84, 2.32, 2.18 and 2.51 m. Every one of those rows is legally carried and the rig reads
top-heavy, a V balanced on its point.

**Every shape rule is a width, in metres.** Stated by the owner of the gear, and it is not a stylistic preference:
the pyramid was written as a *count* — no row holding more cabinets than the row below — and the premise that makes
a count stand in for a width is false. Nine of our ten cabinets are 0.45–0.66 m wide and `mid-bass` is
**1.200 m**, so "no more cabinets" and "no wider" stopped meaning the same thing the day it arrived. The V made it
obvious: built on a count rule it produced **21 stacks that narrow against 8 that widen**, and `free` widened more
often than the shape named after widening.

| shape | the rule, per row against the one below |
| --- | --- |
| `pyramid` | may not sit **more than a tenth of its outboard cabinet proud** on either side |
| `free` | may be as wide as the bearing rule allows — two thirds of a cabinet per side |
| `v` | may not be **narrower** at all |

**The pyramid's tenth of a cabinet is derived from the two cases either side of it**, not chosen. It cannot be zero:
six Achenbachs are 3.700 m on six Flexys' 3.646, 27 mm proud per side out of a 600 mm cabinet, and a rule without an
allowance splits them into two rows of three — whereupon the 1.84 m row cannot carry the tops and a 2-way is dropped
from the rig. It cannot be a whole cabinet either: `2× nuke + 1× mid-bass` is 2.420 m on a 1.890 m row,
265 mm proud per side out of a 590 mm cabinet, and that reads as a V to anybody looking at it. A tenth separates them
cleanly, and it is a fraction rather than a number of millimetres so it scales with whatever cabinet ends the row.

`pyramid` and `v` each carry a **fill order** as well as a bound, and it is the half that makes them possible rather
than merely permitted. The pyramid puts the type that can make the widest row on the floor, because a taper can only
narrow a wall and so is decided by how wide its bottom row is; the V puts the *narrowest* row-maker there, because a
wall can only grow by two thirds of a cabinet per side per row and a V asked for on a full-width base has nowhere to
go.

The price is stated rather than hidden: a wide-but-shallow type can end up *under* a deeper one, which is the
inversion the fill order otherwise exists to prevent. That is why **all three shapes are generated** — `free` keeps
the deepest and heaviest cabinets on the floor and accepts whatever silhouette falls out, where the other two choose
one and give up the ordering.

### Gapping a row out to its shape

`gap_m` is the air between every pair of neighbours in a row, so a row packed at that gap is as wide as its cabinets
make it. Where that width breaks the shape's rule, the solver also offers the same rows with the offending one
**gapped out**, one even gap across the whole row:

* **`pyramid`**, top down. The row under a too-wide row is widened until the one above sits no more than its tenth
  of a cabinet proud, and widening it can ask the same of the row under that.
* **`v`**, bottom up. A sub row narrower than the one under it is widened to that row's full width, so a tall wall
  does not narrow by its tolerance on every row.

The gapped arrangement is one more candidate, ranked like the others, and a row that packs to its shape is never
gapped. Measured on the pooled sdwa5 and sepp rig with the SKRAMs and Flexys rolled: a pyramid's third row goes to
148 mm gaps to reach 2.420 m under the 2.511 m tops row. Six Flexys on six Achenbachs in a V go to 31 mm gaps and
3.701 m on the Achenbachs' 3.700. The committed scenes hold gaps from 26 mm to 1200 mm.

**The gap has no cap of its own.** The owner settled that on 2026-10-01, and the bearing rules are the whole limit.
Every cabinet of the row above still has to land on a third of its own width. Each cabinet of a gapped row stands
apart from its neighbours, so it is also weighed on its own, against the supports it actually touches, where a
packed row is weighed as one strapped body. Two rolled SKRAMs gapped out under five tops would leave the middle
Tecnare over 580 mm of air, and that arrangement is refused for having nothing under it.

The bearing rules weigh rows, and a row can pass them and still leave a cabinet over air once the stack is placed,
aligned and mirrored. The seating predicate `SceneCompiler::stackSurvives()` therefore also runs
`PlacementChecks::floatingFaults()` on every arrangement with a gapped row, so such an arrangement loses to one that
stands. Measured on `innschleife-psl-sdwa5-sepp` without it, the aimed PSL tops row shifted 0.2 m and left a
thebox-dsp-112 entirely over air.

The scene records no gap. Like the row budget, the gaps come out of the constraints, so a re-solve reproduces them.
The writer's row comment names a gapped row, for example `6× achenbach-18 at 262 mm gaps`, and `stack.gap_m` stays the
packed gap. Alignment still never spreads a load-bearing tier (ALN-4). A gapped row is chosen by the fill and
verified by the bearing rules, which is a different thing from a tier stretched afterwards.

### Repeating a flanked row

A sub type split evenly over several rows, **each row flanked by the same pairs of a second type of the same height**,
is one more candidate. It is the arrangement on Innschleife's photo of their own stack, taken 2026-10-01: two rows of
[WSX | SBH SBH | WSX] lying down, the four kickers standing on them and three tops above. Nothing else here proposed
it. The mixed bottom row flanks a single row and puts every cabinet of the centre type into it, which makes
[WSX | 4× SBH | WSX] at 7.0 m. The spread deals the centre type one to a row and only under the `central` bias.

* **The centre is the widest sub**, the same rule the mixed bottom row follows, and every other sub type no more than
  **30 mm** taller or shorter is offered as its flank. Innschleife's SBH lies at 0.550 m and both the WSX and the
  kicker stand at 0.570 m, so both are offered and the ranking picks the WSX.
* **The fewest rows that fit, from two up.** The centre count has to divide evenly and every row takes the same
  pairs, so the wall stays symmetric. Fewer rows are wider and lower, so the first row count whose row fits the
  budget is the one built, and leftover flanks are dealt above it.
* **The centre may be up to 30 mm shorter than its flanks.** The mixed bottom row refuses that, because a different
  row lands on it and hangs over the dip. A repeated row lands centre on centre and flank on flank, since each cabinet
  falls onto whatever is under it, so only the last repeated row passes the step on, and the bearing rules weigh it
  there.

The candidate is offered once per row budget and ranked like every other. **It ties on height with the unmixed rows
it competes with.** The photo's rig stands 1.71 m to the tops and SBH and WSX in rows of their own stand 1.69 m, so
against the default 2.5 m target and the 2.0 m interface neither reaches the band, and at a 1.70 m target both miss by
10 mm and the first wins. The `innschleife-next-event` folder is therefore generated against a lower interface and a
transition aimed just above the photo's, both recorded in every scene's regenerate line:

```bash
bin/console scene:stack --owner=innschleife --event=next-event
```

Four of its scenes are the photo row for row, `stacked-1-pooled` in `pyramid` and `v`, `stated`, `alternate`,
`stated`, at `center` and `stereo`. Their middle top is a TMS-4 since 0.132.0, where the photo shows a black top no
drawing has. The second `stated` is the low end, which the event sets to `low` for Innschleife. The event sets Innschleife up turned with the kickers standing, see
[Event rooms and system preferences](#event-rooms-and-system-preferences).

**The combined `next-event` run holds the photo rig because the event narrows the air between stacks.** Our stack
is 4.276 m wide, PSL's 3.58 m and the photo rig 4.66 m. With the default 0.5 m between stacks the rig is 13.516 m
wide and the 13 m room refuses it, measured on 2026-10-01. The event's `stack_clearance_m: 0.24`, chosen by Stefan
the same day, makes it 12.996 m. Since 0.131.0 the event also fixes Innschleife's low end at `low`, so every combined
rig carries the photo layout. The folder holds eight scenes, four `systems-apart` in `pyramid` and four `tops-shared`,
two in `pyramid` and two in `v`, where it had 26 while Innschleife was still swept `central` as well.

**`next-event-light` is the same event without any Achenbach and with nine ESX**, asked for by Stefan on 2026-10-01
as a second version. `events/next-event-light.yaml` is a copy of `next-event.yaml` that differs in two counts, Sepp's
`achenbach-18: 0` and PSL's `concert-audio-esx: 9`, because an event has no way to inherit from another. Both events
bring four EF 6 since the same day. The light rig is 11.77 m wide against 13.00 m, so the room no longer limits the
layout and `v` fits with the systems apart as well. It writes 20 scenes, 18 possible, and PSL alone writes 13:

```bash
bin/console scene:stack --owner=psl --event=next-event-light
bin/console scene:stack --owner=sdwa5 --owner=sepp --owner=psl --owner=innschleife --event=next-event-light \
  --into=next-event-light --order=ours,psl,innschleife
```

Innschleife bring the same gear to both, so `innschleife-next-event` serves both and there is no light folder for
them.

### A ceiling on the sub height

`interface_height_m` is a **floor** and the solver chases it by narrowing rows — narrower rows mean more of them,
which is the only way to buy height out of a fixed pile of cabinets. `max_sub_height_m` is the **ceiling**, and
stating one inverts the search: instead of returning the first arrangement that reaches the interface, the solver
walks the whole space and keeps the *shortest* arrangement that still stands up.

It also switches on the thing that actually makes a stack of many types short: **a row may hold as many device types
as it takes.** A row costs the height of its *tallest* cabinet, so two types in one row cost one height rather than
two. Without that, each type costs at least one row and a stack holding six sub types is six rows tall however wide
the stage — which is why all 39 speakers in three stacks could not get under 3.146 m before, at any width.

```yaml
    stack:
      max_width_m: 3.80
      interface_height_m: 0
      max_sub_height_m: 3.0     # a row may now hold several device types to get under this
      gap_m: 0.05
```

Two rules keep a packed row honest, and both are worth knowing:

* **Lower frequencies stay in lower rows.** A row may only combine types that are *adjacent* in the fill order, so
  the rows are contiguous runs of it and a frequency inversion is unreachable rather than merely avoided.
* **Within a row the tallest cabinet is central**, with the rest stepping down outboard of it. What stands on a mixed
  row lands on its tall segments, so where those sit decides whether the next row is carried at all: a 1.020 m cabinet
  either side of a 0.637 m one leaves the row above two pads a metre apart to bridge, and it lands on 14 % of itself.

Packing is offered **alongside** the ordinary one-type-per-row deal and the shortest arrangement that stands up wins.
Neither is better everywhere — on 2 SKRAMs, 2 wall basses, a mid bass and 2 2-ways the deal finds 1.445 m and the pack
2.465 m — so `mix_with` keeps working under a ceiling too.

**With no `max_width_m`,** the row count is the widest that still reaches the interface height: narrower
rows mean more of them, so the sub stack grows as the count falls, and the answer is the largest count that
still clears.

### What a `from` entry can say

Most entries are just a device id. The mapping form is for the occasional tier that needs something:

```yaml
from:
  - flexy-folded-horn-hybrid          # the shorthand, and what a generated scene writes
  - device: achenbach-18
    count: 8                          # eight against the six we own
    align: block                      # this tier's own alignment
    mix_with: skram                   # share a row with these
```

* **`count`** overrides the spec's `quantity`. This is how a stack over-books deliberately — eight Achenbachs
  against six owned, to see whether the rig would work if two more were borrowed. It needs no new warning:
  `scene:build` already reports *"uses 8, we own 6"*.
* **`align`** is this tier's alignment instead of the whole stack's. It says *which* alignment, not *how many*
  tiers may spread — only a tier nothing stands on can be spread at all, so in a plain tower that is the top
  one.
* **`mix_with`** names devices to share the row with, at whatever height that device sits. It still has to pass
  the height gate, and a mix that cannot be honoured is **refused rather than quietly dropped** — a row that is
  silently not the row you asked for is the worst outcome available, because the rig still builds.

**`from` must list subs before tops.** The fill is bottom-up, so a top listed first would put a Tecnare
under a Flexy and still satisfy every height check.

**Tiers are ordered by frequency, not by size.** `scene:stack`'s default `--from` sorts on
[`audio.passband_hz`](spec-format.md#the-passband-and-the-difference-between-reach-and-use): lowest driven
corner first, so the deepest cabinets end up on the floor. Ordering by cabinet width instead got this wrong in
a way that looked plausible — the Achenbach is 0.600 m against the Flexy's 0.591, so it sorted first and four
Achenbachs ended up carrying twelve Flexys.

**The frequency decides only between two cabinets that both state one, and mass decides the rest.** Nine of our ten
speakers state no passband at all, so a rule that ranked on its absence would rank almost everything on nothing. An
earlier version did exactly that, reading a missing passband as infinitely high and falling back to `quantity × width`,
which sorted every silent cabinet above every cabinet with a passband and put the 40 kg IQ subs under the 220 kg wall
basses. Mass is the fallback because it is stated for every cabinet and because it is what gravity is about anyway.
Where both rules can speak they agree on the gear we own, so this ordering is the one that has always been built.

**Tops do not stack — every top goes in one row**, widest in the middle. Nothing stands on a top, so width is
the only thing it costs, and a 2-way perched on a tilted M2122 is a fill hovering over the middle of the rig.

**Gravity: each cabinet lands on whatever is under it.** Not on the height of the tallest cabinet in the row
below — on the thing directly beneath that particular cabinet, and on the highest of them where it bridges two.
So a Flexy row with two SKRAMs in the middle is 151 mm taller in the middle, and the Flexys above rest at
0.914 m over the SKRAMs and 0.763 m over the Flexys: an uneven top, and nothing hanging in the air.

That is what makes a **mixed row of different heights** legitimate, which is the arrangement the mixed row exists
for. Resting the whole row above at the taller height instead left four of six Flexys floating 151 mm up; the
first fix was to ban mixing unequal heights, and that removed the symptom and the feature with it.

A tier is expanded as one placement per **run** — a maximal group of adjacent cabinets sharing a device and a
support — so a tier standing on level ground is still a single row, and only a stepped one splits. Heights still
come from `on:`, so none of them is ever written into the file.

**A row may be mixed, and the bottom one sometimes has to be.** Only two SKRAMs exist, so a row of nothing
but SKRAMs is 1.240 m — narrower than the 2.460 m Achenbach row that would come to stand on it, and a stack
whose tiers get *wider* as they rise is 610 mm of Achenbach hanging in mid-air at each end. The solver puts
the two SKRAMs in the middle of the bottom row, where the widest and heaviest cabinets belong anyway, and
flanks them with Flexys: 3.684 m, and the rig is a pyramid. Never just because a device is in short supply,
which would drag the Achenbachs below the Flexys in any rig that has more Flexys than Achenbachs.

**And a row may be flanked from below, to close a step rather than an inversion.** Same mechanism, pointed the
other way. The bottom row asks whether it would be narrower than the row coming to stand *on* it — a support
question, which is why it can only ever fire at the bottom. Asking whether a row is narrower than the row it
stands *on* covers the rest of the wall: four Achenbachs on six Flexys is 2.460 m on 3.646 m, perfectly carried
and a 593 mm shoulder each side. A Flexy either side of them makes it 3.682 m, and the whole rig comes out
3.684 / 3.646 / 3.682 — 38 mm of variation across the sub tiers, where the unflanked version was five tiers and
1.260 m.

How many pairs to promote is the **converging-widths** criterion the bottom row's flanks already use: every
cabinet the flanks take is one fewer in the row below, so the flanked row grows while its support shrinks and the
two widths approach from opposite ends. Stop at the crossing. Past it a promotion no longer flattens anything, it
moves the step down a tier and makes the rig top-heavy — a second pair here would be 4.904 m of Achenbach row
over 2.424 m of Flexy.

The two decisions have to know about each other, and that is the whole subtlety. Left alone, the bottom row grew
to three pairs and 4.906 m, because the eight Flexys it left over came to 4.868 m in one row and anything
narrower would have been overhung by it. Knowing that two of those eight go up instead, six come to 3.646 m and
two pairs is enough.

Not applied to a tier that names its own row-mates with `mix_with`, nor to one that needs more than a single row
— that would have to say *which* of its rows gets the flanks, and neither is a guess worth making.

A mixed tier expands into one placement per segment (`main/1a`, `main/1b`, `main/1c` — letters, so they cannot
be confused with the numeric copy suffixes), and it is not `align`ed, because there is nothing sensible to
distribute one segment at a time.

**A cabinet with nothing under it at all is an error.** Falling puts it on the floor, which for a tier above the
bottom means *inside* the tier below. Unreachable in practice — but it used to be held only by the tier-width
rule refusing the shapes that cause it, which is two rules agreeing rather than the invariant being kept.

**A sub tier narrowed to a single column is refused.** The interface chase had no floor: narrower rows mean more
of them and so a taller stack, so on a pile it could not otherwise lift the search kept narrowing, and
`--per-owner` gave `sdwa5` a rig 1.8 m across and 4.9 m tall. Every other rule passed it — each tier exactly as
wide as the one below, nothing overhanging, every cabinet carried — because a column is never more than half a
cabinet wider than the column beneath it. It also came out marginal in the ways only a full compile shows: two
aimed tops 3 m up biting 10.6 mm into each other, and a top bearing on 43 % of its own footprint. So the ordering
extends: support outranks the interface, and a rig that stands up as a rig outranks reaching the height.

**A row is one body, and stability is weighed rather than measured as a fraction.** Two rules replace what used
to be one proxy:

* **A tier tips** when its combined centre of mass — weighted by `weight_kg`, which every spec carries — falls
  outside the span of what carries it. A row's cabinets touch and are strapped, so the question is about the row,
  not each cabinet: four Flexys on an 1.832 m row have their mass dead centre and stand, with the end cabinets
  reaching past the support and held by the neighbours they lean on. The old rule refused that by **half a
  millimetre**, because "half the outer cabinet off the edge" turns out to *be* the per-cabinet centre-of-mass
  rule — 916.5 mm out against a support edge at 916.0.
* **A cabinet must have a third of itself** over what it landed on. Where that boundary sits is the point: it was
  a half, and a half falls exactly between the two arrangements it has to separate. A Flexy resting on a SKRAM
  with the rest cantilevered outward bears **49.9 %** — marginal, and what crews actually stack and strap — while
  a 2-way perched on a 163 mm shoulder and touching by one corner bears **1.2 %**. Forty times apart, and a half
  refused both.

**A surface below an overhang is a safety net, not a hazard**, and getting that backwards cost two attempts. It
can only ever catch a cabinet that tilts; it cannot make one less stable than the same cabinet cantilevered over
thin air. A rule that measured the tilt onto it refused a Flexy row standing on a mixed Flexy-and-SKRAM bottom row
while allowing the identical row on a lone SKRAM — the same cabinets, the same support, the same 49.9 %, and the
only difference was that something harmless sat 151 mm below.

**Bearing is checked per cabinet, not just per tier.** How much of a cabinet is over the thing it landed on, as
a fraction of its own width; under half is an error. Comparing tier widths cannot see this and neither can the
shipped-scene sweep: a flanked Achenbach row is 163 mm taller at its shoulders, and a top row laid contiguously
across that step clips a shoulder by 5.6 mm — whereupon falling does what falling does and lifts a whole 2-way
onto **1.2 % of its own footprint**. The row above is narrower than the row below, so the widths look fine, and
there really is something underneath, so the floating check passes it too.

**So the top tier is re-seated fills outboard when the ordinary row would hang**: the end segments centred on
the end supports, everything between them centred on what is left. The Tecnares land on the Achenbachs, the two
2-ways out on the Flexy shoulders they would otherwise have caught — raised 163 mm and 1.55 m out, which is
where a fill belongs anyway and the shape the top row is already built in. Only as a repair: a tier that is
already carried keeps the layout it had, so no rig that stands up today is quietly restyled.

**Rows are balanced, not greedy.** Eight leftover Flexys at six-per-row come out 4 + 4, not 6 + 2 — same
number of rows, but a 1.222 m row could not carry the Achenbachs above it and a 2.424 m one can.

**Support is checked, and the line is half the outboard cabinet's width.** Under it, a tier standing proud of
the one below is a **warning** with the overhang in millimetres — what feet and working gaps absorb. Over it,
more than half that cabinet's footprint is off the edge: it is standing on air, and that is an **error**.
Neither shows up anywhere else, because `on:` only reads a top face and never asks whether anything is there.

**And a row keeps the working gap between its own cabinets.** Three mechanisms space a run against something else:
`align` justifies it into an envelope, `align.outside` holds it clear of a named neighbour, and the tier chain spaces
each run outside the one inboard of it. None of them asked whether a run's *own* cabinets clear each other, and at
`gap_m` computed from nominal widths they need not: yawing a cabinet towards the focus swings its front corners towards
its neighbour, so the air asked for is not the air there is. That was 17.6 mm of one cabinet inside the next on a by-type
tops row, and twelve refused candidates. The row is now spread just far enough to restore the gap, reported as a warning
because the rig is a few millimetres wider than the spacing it was written with. Bearing has room for that — its
allowance is two thirds of a cabinet past each end — and interpenetration has none, so clearance wins.

The measure is the **shell**, never the bounding box, and the difference is not a refinement. A bounding box grows by
`depth × sin θ` as a cabinet toes in, about 26 mm on a 0.520 m deep Tecnare, while a *tapered* cabinet's outermost point
is its back bottom corner and moves the other way: three aimed Tecnares span 1.5137 m where their nominal widths and gaps
give 1.540. A box measure therefore invents overlaps that do not exist.

**A row wider than its envelope keeps its own spacing.** `block`'s parameter is a factor on each cabinet's offset, so
a solve below 1 would pull the row *tighter* than the working gap it already has — which is what it used to do, and what
put 92 mm of one cabinet inside the next on every `-block` variant the sweep refused. The floor is now the arrangement's
own spacing, and a row that already exceeds its envelope there is left alone with a warning naming both widths. In the
generated rigs that row is then identical to what `center` produces, so the variant is dropped as a duplicate rather
than refused, and the "the same rig as …-center" line is where you see it.

**Also `align` only ever spreads the top tier, and only as wide as the tier carrying it.** Spreading a tier
turns it into gaps, so a tier with load on it has to stay tight; and spreading even the top tier to the bottom
row's width once put two 2-ways 1.84 m out with a 1.54 m Tecnare row under them.

### scene:stack — writing the scene for you

**The default is a sweep of one inventory, not of every rig the library can name.** `bin/console scene:stack` with no
options writes every sensible configuration it can stand up **out of our own gear and Sepp's, pooled** — by one, two
and three stacks, in all three shapes, all three alignments, all seven orientation/mirror pairs and both ends of the
low-end axis. **166 scenes, every one of them possible**, with every refusal printed and its reason given.

**The separation axis contributes nothing here, and that is the grouping working.** `sdwa5` and `sepp` are one
system by default, so a rig that separates them has nothing to separate and `pooled` is the only value offered. The
271 this default used to write were mostly that axis solving our own system against itself.

**THE INVENTORY AXIS USED TO BE A POWERSET AND IS NOW A CHOICE.** Three owners made seven inventories, which read as
generosity and produced the finding this default rests on: a borrowed rig writes more scenes than any single owner,
because Sepp's six Achenbachs cannot stand alone and are excellent under somebody else's tops. Five owners make that
powerset **31** inventories, 26 of them multi-system rigs nobody will ever build, and the sweep trips its own fuse
before writing anything. Stated by the owner: the sweep is always run against a subset, and the default subset is the
pair. So the finding became the default and the enumeration went.

Every other inventory is one option away and **writes into its own folder**:

```bash
bin/console scene:stack                                          # scenes/generated/sdwa5-sepp/
bin/console scene:stack --owner=gmss                             # scenes/generated/gmss/
bin/console scene:stack --owner=psl --owner=innschleife \
                        --owner=sdwa5 --owner=sepp               # scenes/generated/innschleife-psl-sdwa5-sepp/
```

That is the project's goal expressed as a default, and it is worth stating because the flags read as required and are
not: **naming `--from`, `--stacks` or `--per-owner` narrows the sweep to that point**, exactly as naming `--align`
narrows it to one mode. `--owner` is the exception and narrows the inventory without collapsing anything else.

Read the skipped list as well as the scenes. A sweep that writes twenty and silently drops fifteen would look like
"that is all there is"; the reasons are how you find out which rigs the inventory cannot build.

#### Every axis is in the name

A generated scene is named for **all seven axes**, in the order the sweep nests them. Two of the seven arrived late
and each renamed every file that existed: `possible`/`impossible` in 0.89.0 and `pooled`/`systems-apart` in 0.91.0.
The separation axis then gained its third value, `tops-shared`, in 0.96.0 and renamed nothing — 11 characters against
the 13 `systems-apart` had already set the column to, which is why shipping two of three first cost nothing.

```text
gmss/ stacked -1     -pooled -pyramid -upright     -alternate   -center   -possible
 ^      id     stacks  systems  shape    orientation  mirror       alignment  feasibility
 inventory             apart?                         style
```

**The inventory is the directory and is deliberately not in the name as well.** SWP-3's rule is that an axis value
appears in the path or in the name and never in both, and the system name inside a folder named after the system is
redundant: it was stated 271 times in `scenes/generated/` where once in a directory name says the same thing. It also
removes the trap described below — the owner column had to be padded to the widest label the *specs* could produce,
so speccing a fifth owner would have renamed all 1374 files without changing a single rig.

Three of them used to be left out at one value each — `pyramid`, `upright` and `alternate` — so that the ordinary rig
kept a short id. The price was a directory nobody could read: a gap in a name does not say *which* value was omitted,
only that one was, so telling `stacked-gmss-1-center` from its six siblings meant knowing the defaults by heart.

**Every axis has a value, including the one that had none.** A null orientation means
[`--roll-mirror`](#scenestack--writing-the-scene-for-you) named the cabinets outright rather than naming a mode, and
that is written as `stated`. It is deliberately not a `StackOrientation` case: an enum case would be offerable as
`--orientation=stated`, which means nothing without a `--roll-mirror` beside it and would have to be refused everywhere
it appeared alone. It is a fact about how the rig was *asked for* rather than about which cabinets ended up on their
sides, so it belongs to the naming.

**Every axis is also a fixed-width column, padded with dashes**, so a listing lines up and one axis can be scanned down
the page:

```text
gmss/stacked-1-pooled--------pyramid-upright-alternate-center-possible.yaml
sdwa5-sepp/stacked-2-systems-apart-free----turned--column----stereo-possible.yaml
gmss-sdwa5-sepp/stacked-3-pooled--------v-------mixed---centred---block--impossible.yaml
gmss-sdwa5/stacked-2-pooled--------pyramid-stated--alternate-center-possible.yaml
```

Each width comes from the axis's own enum cases, so a new value widens its column by existing rather than by a number
written somewhere. The last field stays ragged, because nothing is lined up behind it and a run of dashes before
`.yaml` buys the reader nothing.

**A replay has to be told its folder, which is the one cost of the directory.** The recorded `Regenerate it with:`
line names cabinets with `--from` rather than owners — deliberately, so that measuring a new sub does not change what
a replay rebuilds — so a replayed command has no inventory to derive and carries `--into=<inventory>` instead. Without
it the whole set would replay into `scenes/generated/` itself, where identically-named siblings from different
inventories would overwrite each other.

**The mirror style is written even where it decides nothing**, and that is deliberate rather than an oversight. The
sweep only ever pairs `upright` with `alternate`, so a style says nothing about an upright rig — but
`--orientation=upright --mirror-style=centred` is accepted and honoured, and a name that dropped a vacuous style would
give those two rigs the same file name.

#### The sub height band, which is an aim rather than a gate

**Stated by the owner: the sub/top interface height is an optimisation problem, not a hard constraint.** Tops standing
below or above head height is not a reason to refuse a rig or to call a scene invalid. `interface_height_m` (2.0 m by
default) and `max_sub_height_m` (3.0 m) bound the sub/top transition, `target_sub_height_m` (2.5 m) aims at it, and all
three are the same kind of thing: **the target is what the solver optimises, and the two bounds are the band around it
that a miss is measured against.**

For two releases the band was a gate instead, and a generated scene that missed it was thrown away. That refused 258
candidates in one family alone — more than every geometry rule in the repository put together — and every one of them
was a rig that stands up perfectly well and is merely shorter or taller than ideal.

So a rig outside the band **is written**, with the measurement in two places. On the terminal, for whoever ran the
sweep and is not going to open 396 files:

```text
noted   stacked-gmss-1-center — the stack's subs reach 3.340 m against the 3.000 m ceiling asked for
  — 340 mm too high, and the rig is written with that miss on it
noted   stacked-sepp-2-center — the sepp stack's subs reach only 1.246 m against the 2.000 m interface
  asked for — 754 mm short, so the tops fire below head height
```

And in the file's own header, so the next reader does not take a knowingly short wall for a solver bug:

```text
# Subs reach 1.246 m against a 2.0 m interface. The stack is 1.85 m of cabinet.
#   * the subs reach 1.246 m against the 2.000 m interface asked for, so the tops sit 754 mm lower than
#     ideal — 1.246 m is the most they reach while every tier is still carried
```

**What stays a gate is everything about whether the rig stands up**: bearing, support, the pillar rule, the silhouette
rules and interpenetration. That is the line, and it is a different question from whether the rig sounds right. A
cabinet hanging off the edge of its support cannot be built at any price; tops a bit low can.

**The bounds still steer, they simply cannot refuse.** The solver prefers an arrangement inside them, and where two
ways of dealing the same cabinets out place the same number, the one nearer the aim wins — with a doubled price on the
part of the miss that falls outside the band, so a stated ceiling still has a say. At the default band that penalty
changes no ranking, because 2.5 m is the midpoint of 2–3 m and every in-band wall is already nearer the aim than every
out-of-band one. It starts mattering the moment somebody states a target off the midpoint.

**The width ladder went with the gate.** The sweep used to walk a rig up and down a list of real stage widths to land
its wall inside the band, which needed both halves of a sentence that no longer has either: a band that refuses, and a
default stage width worth deviating from. See [an unstated width limits nothing](#an-unstated-width-limits-nothing).
The lever that remains is the solver's own search, which chases `target_sub_height_m` directly rather than through the
stage width as a proxy.

**That search is a row width in metres, and also a cabinet count, because neither contains the other.** A width is
divided by each cabinet's own width, so one budget deals as many of each type as that type's size allows — 4.40 m is
7 Flexys and 3 mid-bass, which no single number of cabinets expresses. A count says "the same number of every type"
instead, which reaches arrangements no width does: built as a width alone the search lost 49 rigs, every one of them
refused on bearing rather than on the search running out. So both are walked, as a union rather than a product, and the
widths come from the row widths the inventory can actually make rather than from a list of round numbers. The unbounded
step is always tried first, so nothing is ever bounded by the search itself.

**And every candidate that would win is placed for real before it is accepted.** Two cabinets end up inside each other
because of yaw, taper and chamfer, none of which a row width knows about, so the solver hands the candidate to the
compiler and asks. A candidate that overlaps loses to the next one rather than the whole rig being thrown away, and the
question is asked only of a candidate that would win — a loser's geometry changes nothing, and asking it of everything
is a compile per arrangement.

This is why a generated file's header and its rebuild agree: `scene:stack` and the compiler ask the same question of
the same arrangement. They did not always, and a file whose header described a rig the compiler would rebuild
differently is the sort of thing nothing downstream notices.

**Asking the same question is only half of it — both sides also have to be given the same stack.** The compiler
re-solves from the file, so every input the command solved with has to be a key the file can state. The seating check
was the first place this went wrong and [`slide_slack_m`](#sliding-a-row-rather-than-losing-the-rig) was the second, in
the same rig and with the same symptom: a header describing an arrangement the rebuild never reaches.

#### Sliding a row rather than losing the rig

**For a quantity-bound rig the lever is where the row sits, not how wide the stage is.** A row does not have to be
centred on what carries it: centring is only optimal when the row overhangs a symmetric amount of cabinet at each end,
and a mixed row is asymmetric by construction. GMSS's one arrangement inside the band packs the nukes and mid-bass into a
single row — 2.42 m on a 1.89 m support — and centred, the outboard nuke lands on 8 % of its own width and the whole rig
is refused. Slid along the support, both ends are carried and `stacked-gmss-1-center` writes at 2.84 m.

**Sliding is bounded by what else is in the scene rather than by gravity**, and that bound is the whole of its safety.
Stacks are spaced on their widest tier and their envelopes deliberately overlap in x — tiers at the same height are each
centred and narrower — so a row that slides in a multi-stack rig reaches into the stack beside it: unbounded, 180 mm of
interpenetration across five `all-3` scenes. So only a stack with **nothing beside it** may move a row, and only inside
the stated stage width. A row that is already carried is never moved, and a slide that does not improve the
worst-carried cabinet is discarded, so every rig that stood up before stands up unchanged.

**The scene states it as [`slide_slack_m`](#the-stack-block), and for a long time it could not.** `scene:stack` gives a
solo stack `.inf` and a stack in a rig nothing at all, and the schema had no key for either — so a generated solo scene
was written by a solve that allowed sliding and rebuilt by one that forbade it. That is not a rounding difference.
Measured on `stacked-all--------1-free----turned--alternate-center`, the same inventory came out as four rows reaching
1.860 m of subs with the slack and as two rows reaching 0.660 m without it: a 23.5 m line of cabinet with the tops
1340 mm below the interface, rendered and committed, whose own header comment described the other rig. Unstated the
key still means "may not move", so a hand-written scene keeps the arrangement it had.

#### An unstated width limits nothing

**Stated by the owner: how wide a generated scene comes out does not matter at all unless a parameter limiting the
width is explicitly passed.** `--max-width` used to default to 3.70 m, so every generated scene was solved against a
stage nobody had asked for, and a rig too wide for it was refused for a reason that came from the option's default
rather than from the request. It now has no default: state it and it is obeyed, leave it out and there is no bound.

The machinery was already there and was simply never reached. [`max_width_m`](#the-stack-block) has always been
optional, and the solver reads its absence as "no bound at all" — which is not the same as an infinite bound, and the
difference is a real trap: passing `INF` where `null` belongs casts to `(int)floor(INF)` when the row count is worked
out, which is undefined in PHP and came out as a row of one, every tier a pillar.

Two consequences worth knowing:

* **The recorded command says nothing about width unless you did.** A replay is unbounded exactly as the original was,
  and `--max-width` reappears in the line only when it was stated.
* **The width is now an output rather than an input.** The target sub height decides how the rows are dealt and the
  width falls out of it. Removing the bound on its own would have gone the other way — with nothing deciding how wide
  a row wants to be, the bottom row takes every cabinet of its type and the rig collapses to one row per type — which
  is why this and [the band as an aim](#the-sub-height-band-which-is-an-aim-rather-than-a-gate) landed together. Once
  the target is what the solve optimises, a one-row wall misses it by nearly two metres and loses on its own merits.

#### Aiming the sub wall, rather than settling for the lowest one

**A bound says which arrangements are allowed; a target says which of them is best.** Until `target_sub_height_m`
existed the solver had no answer to the second question, so it kept the **shortest** arrangement that cleared the
interface — a defensible tie-break, and not what anybody wants. It parked the transition just over 2.0 m wherever it
could, when the useful place for it is the middle of the band, where the tops clear a standing crowd with room to spare
and the wall is still well under a truss.

The target defaults to **2.5 m**, the middle of the 2–3 m band, and `--target-sub-height` moves it. Measured across the
sweep, the rigs sit noticeably closer to the aim (measured across the 148 the sweep wrote at the time):

| | mean distance from 2.5 m |
| --- | --- |
| shortest-wins | 0.222 m |
| aiming at 2.5 m | **0.189 m** |

Two rules keep an aim from doing damage, both of which cost a measurement to find:

* **The ceiling binds before the target does.** A target is a preference between *legal* arrangements and may never
  reach past a bound to pick an illegal one. This is inert at the default, since 2.5 m is the band's midpoint and every
  legal arrangement is therefore nearer the aim than any illegal one — and it stops being inert the moment somebody
  states an aim off the midpoint, which is exactly when the option gets used.
* **With nothing legal, the aim falls back to the ceiling.** Five Flexys and three Achenbachs under a 1.0 m ceiling can
  build 1.363 m or 2.126 m and neither fits. 2.126 m is nearer 2.5 m; 1.363 m is nearer being a rig. A preference must
  not pick the worse of two failures just because there is no success to choose between.

The scene file records `target_sub_height_m` **only when it is not the default**, unlike the two bounds, which are
always written. A default written into every file would state a number that says nothing and would have to be
rewritten in every one of them the day the default moves.

#### The inventory axis, and why the borrowed rig became the default

The sweep builds from **one subset of owners**: `--owner` names it, and silence means
`SweepAxes::DEFAULT_OWNERS`, which is `sdwa5` and `sepp` pooled as one rig. Stated by the owner.

**It used to be every non-empty combination**, and that is worth keeping because the finding is what the default now
encodes. Measured on a 66-candidate sweep, back when there were three owners:

| inventory | scenes written |
| --- | --- |
| `sdwa5-sepp` | **50** |
| `gmss-sdwa5` | 25 |
| `sdwa5` | 22 |
| `gmss` | 21 |
| `all` (three owners) | 17 |
| `gmss-sepp` | 13 |
| `sepp` | 0 |

That is the shape of a shared gig rather than a curiosity. `sepp`'s eight cabinets **cannot stand alone** — six
Achenbachs and two 2-ways cannot fill a 2 m wall however they are stacked, so every `sepp`-only rig was refused — and
they are excellent *under* somebody else's tops. And the everything rig wrote fewer than the best pair, because 41
cabinets in one rig is two complete sound systems where 25 is a gig.

**So the powerset had already answered its own question, and then it stopped scaling.** Five owners make it 31
inventories, 26 of them multi-system rigs — Innschleife subs under PSL tops with our Tecnare on top is arithmetic
rather than a gig — and the sweep trips `--max-scenes` before writing a file. Worse, the owner column in every file
name was padded to the widest label the *specs* could produce, so speccing a fifth owner renamed all 1374 committed
scenes without changing one rig.

**What replaced it is a subset per run and a folder per subset.** Thirteen inventories are committed and each is
one command:

| folder | inventory | scenes |
| --- | --- | --- |
| `sdwa5-sepp/` | the default: ours and Sepp's | 132 |
| `gmss-sepp/` | GMSS subs under Sepp's tops, and back | 367 |
| `gmss-sdwa5/` | GMSS and ours | 456 |
| `gmss-sdwa5-sepp/` | the three systems there were figures for before PSL and Innschleife | 496 |
| `gmss/` | GMSS alone | 112 |
| `sdwa5/` | ours alone | 64 |
| `sepp/` | Sepp's alone | 8 |
| `innschleife-psl-sdwa5-sepp/` | the joint rig the two new systems were specced for: everything four systems own | 446 |
| `next-event/` | **the rig the next event actually stands up** — our gear and Sepp's in full, plus both borrowed systems at the counts the event states for them | 8 |
| `next-event-light/` | the same event without any Achenbach and with nine ESX, see [Repeating a flanked row](#repeating-a-flanked-row) | 20 |
| `innschleife-next-event/` | what Innschleife are bringing on its own, two TMS-2 around a TMS-4 on their photo's sub rows. Generated at a 1.6 m interface aimed at 1.75 m, see [Repeating a flanked row](#repeating-a-flanked-row) | 25 |
| `psl-next-event/` | what PSL are bringing on its own: twelve ESX under four EF 6, in front of their deco panel on our truss | 11 |
| `psl-next-event-light/` | the same with nine ESX | 13 |

**`sdwa5-sepp` is the small one now and that is the grouping's doing.** It held 271 scenes while `sdwa5` and `sepp`
counted as two owners, and 171 of those were the separation axis solving our own system standing apart from itself.
One system has nothing to separate, so it writes 132 today against `next-event`'s 8. See below.

**`all` is gone as a label**, and that is the same lesson in one word: a subset covering every owner was called `all`,
which was shorter and stayed correct exactly as long as the owner list did. `all` meant three systems and 39 cabinets,
and the day two more were specced the same word meant five systems and 101 units. `gmss-sdwa5-sepp` means the same rig
whoever gets specced next.

`owner` is still the only discriminator the specs carry, and it is admittedly not quite the right one: "owner" and
"system" are different questions once gear is lent. It is what exists, it separates the systems in practice, and
inventing a `system:` field to serve a sweep would be inventing a property to serve a layout. What the default subset
does is make that gap cheap — a grouping stated at invocation time rather than in the specs, which is what SWP-3's
second ask was really after.

#### Event rooms and system preferences

`events/next-event.yaml` saves the room at 13 m wide and 4 m high, and what each system brings, see
[What a system brings](#what-a-system-brings-against-what-it-owns). Add `--event=next-event` to each next-event
sweep. `--room-width` and `--room-height` can tighten these limits. They cannot loosen an event's limits.
The check uses every compiled device's world box, so it includes space between stacks, rotated cabinets and
flown equipment. A rig outside the room is refused before writing, including a rig otherwise marked impossible.

```bash
bin/console scene:stack --owner=innschleife --event=next-event
bin/console scene:stack --owner=psl --event=next-event
bin/console scene:stack --owner=sdwa5 --owner=sepp --owner=psl --owner=innschleife --event=next-event \
  --into=next-event --order=ours,psl,innschleife
```

The event gives Innschleife sub walls a 1.6 m interface and a 1.75 m target. A wall containing subs from several
owners keeps the ordinary defaults. Borrowed tops do not change which system owns the sub wall.
`--system-interface=OWNER:METRES` and `--system-target=OWNER:METRES` can state these preferences directly.

The event also states how each system is set up, stated by Stefan on 2026-10-01:

```yaml
systems:
  sdwa5: { orientation: upright }
  sepp: { orientation: upright }
  psl: { orientation: turned }
  innschleife: { orientation: turned, stand: [ kicker-15 ], ... }
```

A cabinet follows its own system's orientation, and only cabinets of systems the event does not name follow
`--orientation`. `stand` keeps a cabinet as measured under any orientation. Innschleife's kickers need it, because
`turned` rolls every sub and would stand the 0.95 × 0.57 m kicker on its narrow side, while the photo shows it lying
wide. So Innschleife turned with standing kickers is exactly the photo. When every system of a rig has a stated
orientation, the sweep has nothing left to vary and writes one candidate named `stated`. When only some do, an
orientation that rolls the same cabinets as an earlier one is dropped as the same rig.
`--system-orientation=OWNER:MODE` and `--stand=ID` state the same directly, and the recorded line carries both.

The event states where each system wants its lowest cabinets too, as `low_end`, stated by Stefan on 2026-10-01 against
two renders. Ours and Sepp's are `central`, which builds two rows of [3 Flexy | SKRAM | 3 Flexy] over the six
Achenbach. Innschleife's is `low`, which is their photo. PSL states none, because both values build PSL the same rig.

```yaml
systems:
  sdwa5: { orientation: upright, low_end: central }
  sepp: { orientation: upright, low_end: central }
  innschleife: { orientation: turned, low_end: low, ... }
```

A stack follows the low end its subs' systems agree on, so ours follows sdwa5 and Sepp together and a borrowed top
changes nothing. A pooled wall of two systems that disagree, or that holds a system stating none, follows
`--low-end` as before, and a stack without subs follows the owners of what it does hold. When every system of a rig
states the same low end, the axis has nothing left to vary and the one candidate is named `stated`, which is what
`innschleife-next-event` now holds. `--system-low-end=OWNER:low|central` states the same directly, and the recorded
line carries it.

**The combined rig is named `low` although ours sits `central` in it.** PSL states nothing, so the combined sweep
still varies the low end for PSL. Both values build the same rig, and the deduplication keeps the first one swept,
which is `low` because `LowEndBias::Low` is declared first. The file is
`next-event/stacked-1-systems-apart-pyramid-stated--alternate-center-low-----possible.yaml`, and the `low_end:
central` on our stack inside it is what the solver used. Stating a low end for PSL would not rename it to `stated`
either, because a pooled wall of ours and Innschleife's subs would have two answers.

`stack_clearance_m` sets the air between neighbouring stacks for the event's runs, 0.24 m at the next event instead
of the default 0.5 m. An explicit `--clearance` replaces it, and the recorded line carries the number as
`--clearance=0.24`. See [Repeating a flanked row](#repeating-a-flanked-row) for why it is 0.24.

The event also names the truss a deco device hangs from, as `backdrop: {truss, segments, towers}`. A run whose systems
bring a `deco` device gets that truss behind the rig with the panel on its front, see
[A deco backdrop behind a generated rig](#a-deco-backdrop-behind-a-generated-rig). A run that brings none records no
backdrop, so the block changes no other folder.

Recorded commands save the resolved room dimensions and system preferences rather than the editable event id.
Replaying an old scene therefore keeps its limits even when the event file changes. These limits belong to scene
generation. Editing a scene by hand does not add a room check to `scene:build`.

#### What a system brings, against what it owns

A spec's `quantity` is **how many exist**. It is not how many turn up. Innschleife bring five of the seven cabinet
types currently documented, a box is in the workshop with a blown driver, a rental company brings twelve of a sub its
published package lists six of. None of that is a correction to a spec, so none of it is written into one.

**What a system brings is `systems.<owner>.brings` in the event file**, and it overrides the counts it names for the
runs of `scene:stack` that use the event and nothing else. Until 0.130.0 it was a roster file of its own in
`rosters/`, one per system and event, and every one of them was about exactly one event, so the counts moved into it.

```yaml
systems:
  psl:
    brings:
      concert-audio-esx: 12
      concert-audio-ef6: 4
      thebox-tp218-1600: 0        # not this time
```

```bash
bin/console scene:stack --owner=psl --event=next-event
```

Five things about it are worth knowing before writing one.

**It changes counts and nothing else.** It does not select owners, name a rig or decide a layout. `--owner` still
says whose gear is in the inventory, and a device the map never mentions keeps the quantity its spec states. **Only
the swept systems bring anything**, the `--owner`s, the owners of the `--from` cabinets, or every owner when neither
is stated. So `--owner=innschleife --event=next-event` builds Innschleife's rig with Innschleife's counts and leaves
PSL's ESX and panel out. **A system brings only its own gear**, and an entry naming another owner's device is refused
for every system in the file, so two systems can never state two counts for one device.

**Zero is how a cabinet stays at home**, and naming it at zero is not the same as leaving it out. Left out means
"bring whatever the spec says". So a statement like "PSL are bringing the following" is written with a zero for
every cabinet it excludes, which is why PSL's map has eight of them.

**One system at an event is filed as `<owner>-<event>`.** Without that, `--owner=innschleife --event=next-event` would
land in `innschleife/` under the same file names as the rigs built from everything Innschleife own, and the last run
would win. It is the name the roster file carried, so no folder moved with the merge. Several systems need `--into`,
and so does `--quantity`, which carries no name of its own, rather than being allowed to overwrite:

```bash
bin/console scene:stack --owner=innschleife --quantity=tms4:2 --into=a-name-for-it
```

**Brought cabinets the sweep does not hold are refused.** Counts rewrite specs, and which specs a rig is built from is
still `--owner`'s and `--from`'s decision, so a `--from` list that names some of a system's cabinets and not the rest
it brings would change nothing for the rest. The roster files could be named without their owner, and once
`innschleife-next-event-tms4/` was swept that way and held 146 scenes of sdwa5 and sepp cabinets. The refusal names
the cabinets and the owner:

```
the event has innschleife bring kicker-15, tms2, tms4, which the swept inventory does not hold. Say
--owner=innschleife, or name them with --from
```

**The recorded regenerate line carries the counts, never the event.** It is the same argument the `--from` list is
written out on: a replay has to rebuild *that* scene, and an event is a file somebody can edit. Recording `--event=`
would make every replay depend on what the file says on the day it runs, so a count corrected next week would
silently rewrite last week's rigs under their old names. The event is the human-facing record and the way a folder is
generated in the first place, and the line inside a scene file pins the numbers.

#### A scene's key is its path, and its `id` is a label

**Two notions where there used to be one, and the reason is a defect that shipped for a release.** When the
inventory became a directory, 2072 generated scenes came to share 589 basenames — 421 of those names belonging to
two or more inventories. Everything downstream keyed a scene by its `id`, which is its basename, so eleven
different rigs wrote one `build/scenes/generated/<id>.blend` and one render: the first inventory in sort order won,
and the other ten were found to have an artifact newer than their own source and **skipped as up to date**. The
failure mode of a wrongly-keyed artifact is a skipped rebuild, which looks exactly like a current one.

* **The key** is the path below `scenes/`, without the extension —
  `generated/sdwa5-sepp/stacked-1-pooled--------free----mixed---alternate-center-possible`. It is unique by
  construction, it exists for hand-written scenes too, and it is what every derived path and every set comparison
  uses. `SceneLoader::keyOf()` and `BaseCommand::sceneKey()`.
* **The `id:` field** is the label: what `scene:build` prints and what reaches Blender's log. Today every scene's
  id equals its basename; the point is that only the key is guaranteed to be unique.

Every derived artifact mirrors the scene's own directory, so `scenes/generated/gmss/x.yaml` builds to
`build/scenes/generated/gmss/x.blend`. `scene:build` and `scene:render` accept either form, and a bare basename
that names more than one scene is refused with the paths rather than resolved by sort order. Both also accept a
folder, relative to `scenes/` or as a path, and then take every scene below it, so
`scene:render scenes/generated/innschleife-psl-sdwa5-sepp` renders one event's inventory on its own. A folder with
no scene in it is refused (`SceneLoader::filesUnder()`).

#### Each system aims at its own focus

**A scene's `focus:` is measured from the *rig's* front centre**, which is exactly right for a cluster and the
near-fills beside it, and wrong the moment two sound systems stand side by side: the outer walls swing back toward
a point in front of the middle one, so three systems cover one patch of floor between them instead of each
covering the room it stands in front of. On a two-wall rig that is 23° and 26° of yaw on cabinets that should be
facing straight ahead.

**A placement may state a `focus:` of its own**, in either of the two forms the scene's own takes, and it means the
same thing measured from that placement's own front face:

```yaml
  - id: main-innschleife
    at: [3.6, 0.0]
    aim: far
    focus:
      far:  { distance_m: 10.0, height_m: 1.8 }
      near: { distance_m: 2.0, height_m: 1.8 }
```

`scene:stack` writes one per wall **when the walls are systems** — `systems-apart` and `tops-shared` — and none at
all for `pooled`, because a pooled rig split into two or three stacks is one system in several piles and is aimed
as one cluster. A hand-written scene is untouched unless it adds the block: a fill beside a main cluster belongs to
that cluster and aims where it aims.

The point is resolved once, at expansion, and written into each cabinet as an ordinary `aim_at`, so nothing
downstream has to ask which focus a cabinet meant.

#### Systems, which are not owners

**`owner` is what a spec records and it is not quite the right discriminator.** `sdwa5` and `sepp` are two owners
and one system: they travel together and they are what stands on a stage when this collective plays. Until SWP-3
nothing could say so, and `--systems=systems-apart` read the owner field — so the rig for the next event came out
as **four** walls, Sepp's standing apart from ours, in a render nobody could have built.

```bash
bin/console scene:stack --group=borrowed:gmss+psl --owner=gmss --owner=psl
```

`App\Scene\SystemGrouping` holds it. The default is one line — `ours` is `sdwa5` and `sepp` — and every owner it
does not name is its own system under its own name. `--group=NAME:owner+owner` replaces the default for a run
rather than adding to it, so the option states the whole truth about the run it is typed on.

**Stated at invocation time rather than in the specs, and that is the answer CVR-3 held out for.** Who owns a
cabinet is a fact about the cabinet; who counts as one system is a fact about one gig. This repository deliberately
supports lending gear between owners, so a `system:` field on a spec would be a layout written onto an object.

It changes exactly two things and the second is easy to miss:

* **How the walls are divided.** `StackDeal::groups()` partitions on the system, so `systems-apart` gives three
  walls for the next event: `main-ours` at 25 cabinets, `main-psl` at 17, `main-innschleife` at 14.
* **Whether the separation axis is offered at all.** It is counted in systems now, and a rig drawn from one system
  gets `pooled` alone — which is why the default inventory lost two thirds of its scenes rather than gaining any.

A stated grouping is recorded into each scene's regenerate line, because the grouping decides how many walls a
separated rig has and a replay without it would rebuild three systems as four.

#### Every option narrows one axis, and none of them collapses the sweep

`--shape=pyramid` has always narrowed the shape axis and left everything else walking. `--from`, `--stacks` and
`--per-owner` did not: they were read as *"the caller has one specific rig in mind"* and collapsed the whole cross
product to a single candidate. So **"sweep everything, but only two stacks" could not be asked for.**

```bash
bin/console scene:stack --owner=gmss --stacks=2    # 48 scenes: one stack count, every other axis still walking
```

There is one path now. A caller who wants exactly one scene names one value on every axis — which is what a replay
does, and why `build:all` still rewrites exactly one file per recorded line. Two consequences worth knowing:

* **The scene id carries the rig fields.** `--id` records the base alone and the stack count and separation are
  rebuilt from `--stacks` and `--systems`, because a name built without them would give two different rigs the
  same file name the moment `--from` stopped collapsing the sweep.
* **`--per-owner` is an alias of `--systems=systems-apart`** and is no longer what a scene records. The flag can
  only say one of the three separations, and a replay has to name the one it was.

#### Where the low end goes

**The lowest cabinets end up low because the fill deals them first, and central only by accident.**
`StackMetrics::centred()` puts the *tallest* segment in the middle because that is what carries the row above —
measured at 14 % bearing when it sits outboard — and nothing anywhere aimed a low-frequency cabinet at the centre
line. So the SKRAMs came out in the middle of the floor row and it read as luck, because it was.

```bash
bin/console scene:stack --low-end=central
```

| value | what it builds |
| --- | --- |
| `low` | what the solver has always done: both SKRAMs side by side on the floor, straddling the centre line |
| `central` | one SKRAM on the floor centre and the second directly above it, each flanked to the row's width |

**`low` costs nothing, deliberately.** The fill already deals the lowest-reaching type first and it already lands
on the floor, so pricing that would re-rank every rig in the repository to express a preference they already
satisfy — eleven solver tests said so out loud when it did. It is also declared first, so where the two values
agree the deduplication keeps the `low` name and an unchanged rig is not renamed to claim a preference it merely
happens to satisfy. On the GMSS inventory `central` differs on **7 rigs of 104**; on Sepp's eight cabinets, none.

**Two measures, weighted four to one.** How high the low-frequency mass sits above the floor, and how far it sits
from the centre line. Both are `moment / mass` over the same cabinets, the shape `Stability::tips()` already used
to decide whether a row topples, with the weight changed from *how heavy* to *how low it reaches* — the passband
where a cabinet states one, mass where it does not, under the guard `byFillOrder()` states in words: **frequency
decides only between two cabinets that both state one.** Power is not in the schema at all, so the "most powerful"
half of the ask cannot be weighed and is filed as SPEC-13.

Two things it took a measurement to get right, both worth knowing before touching it:

* **The measure is about one type, not about every sub.** A centroid over the whole wall is dominated by whatever
  there are most of — twelve Flexys against two SKRAMs move it by centimetres however the SKRAMs are placed — so
  it could not see the arrangement it exists to choose.
* **And it counts each cabinet, not each run.** `Tier::seats()` reports a run at its own centre, so a pair read as
  one lump at their midpoint, and an arrangement with the pair shoved up a row and off to one side scored as *more*
  central than the same pair straddling the middle on the floor.

**Nothing is refused for it and no rig comes out narrower.** The shapes keep priority and the bearing rules keep
their keys — re-keying `centred()` or `mixedBottomRow()` on frequency would break the reason they exist — and this
only ranks the arrangements they already accept. The one thing it adds to the search is a candidate that spreads
the lowest type one to a row, each cabinet flanked into a full-width row, because a bare spread gives it a 0.61 m
row of its own that cannot carry the row above and is never returned.

#### Each axis is a directory level or a name field, never both

`--folders=<axis>[,<axis>…]` decides, from `inventory`, `stacks`, `systems`, `shape`, `orientation`,
`mirror-style`, `align` and `feasibility`. **The default is `inventory` alone, which is exactly the tree above** —
the mechanism ships without moving a file.

```bash
bin/console scene:stack --owner=sepp --folders=shape,feasibility
# scenes/generated/sepp/v/possible/stacked-1-pooled--------upright-alternate-center.yaml
```

Four rules make it work rather than merely run.

**A value is in the path or in the name and never in both.** Otherwise every path states the same fact twice and a
rename has two places to go wrong. The nesting order is the order the name already reads in, so switching a level
on is a *move*: the field leaves the name and becomes a directory in the same position.

**A directory carries the raw value; the padding stays in the name.** A file name is read in columns so every
field is padded to its axis's widest value, and `v` becomes `v------`. A directory has no column to line up with,
and padding it would rename every directory the day an axis gains a longer case.

**The inventory is permanently a folder.** It is the only axis whose value cannot be recovered from a scene's own
recorded line — that line names cabinets rather than owners, on purpose — so putting it in the name would mean
inventing a second option to carry the label.

**At most three levels.** The cardinalities are 11 × 3 × 3 × 3 × 4 × 3 × 3 × 2, so switching them all on gives more
directories than files. A fourth is refused rather than silently produced, the same way `--max-scenes` refuses
rather than truncating.

A non-default layout is recorded as `--folders=` in each scene's line; the default records nothing. Every
folder-able value except the inventory is already in the line, so what a replay is missing is not the values but
which axes are folders — without it the replay lands in the right directory and rebuilds a name still carrying the
value that moved out of it, writing a second file beside the first with neither reported stale.

#### The orientation axis, and why laying subs down is the biggest lever there is

**A rolled sub is wider and shorter than a standing one, and both halves of that help.** A wider row fills the stage in
fewer cabinets, and a shorter row keeps the sub/top transition inside the band above. Measured on the same 66-candidate
sweep, changing nothing but which cabinets were rolled:

| orientation | scenes written |
| --- | --- |
| `upright` | 11 |
| every sub rolled | 24 |
| every cabinet rolled, tops included | 18 |

Turning the subs **more than doubles the output**, and it does something no other change has managed: three-stack rigs
appear at all. A turned sub wall is short enough to land in the band, where the upright version of the same rig is
refused for being too tall.

So `--orientation` is swept, three values, and it is the largest single axis in the sweep:

| mode | what lies down |
| --- | --- |
| `upright` | nothing. What every generated scene was before this axis existed |
| `turned` | every sub, whatever it measures — the literal reading of "all cabinets on their sides" |
| `mixed` | only the subs that get **wider and shorter** on their side, which is the whole reason to roll one |

**Tops are never rolled, at any setting.** That is the owner's call about the gear rather than a geometric result, and
the reason is acoustic: a top's horn throws its pattern in one orientation, and rolling the cabinet rolls the pattern
with it. The measurement agrees — rolling everything writes 18 against 24 — but the numbers are not why. Low frequency
is near-omnidirectional, which is why the same objection does not reach a sub.

**`mixed` is read off the specs and states no new fact about the gear.** `subtype` and `dimensions_m` are both recorded
already, so the rule needs nothing measured: roll a sub where its height exceeds its width. Two cabinets are left
standing by it and for two different reasons. `mid-bass` is 1.200 × 0.500, the one sub already wider than it is
tall, so rolling it would make the wall *taller* and the row narrower; `achenbach-18` is 0.600 × 0.600, where rolling is
geometrically nothing at all. This matters beyond tidiness — the repository refuses to invent physical properties to
serve a layout, which is why `--roll-mirror` names cabinets outright rather than deriving them from a "horn-loaded"
field nobody has measured.

**The orientation and the mirror style are swept as pairs, not as two axes.** The mirror style only decides what a
*rolled* row does with the odd cabinet it cannot halve, so it is vacuous wherever nothing is rolled. Multiplied out
independently, a third of every candidate would be a duplicate of another by construction — measured, before the fold:
66 `centred` candidates and 0 scenes written from them. Paired, the vacuous combinations cannot be expressed:

| # | pair |
| --- | --- |
| 1 | `upright` — nothing rolled, so there is no odd cabinet to place |
| 2–4 | `turned` × (`alternate`, `centred`, `column`) |
| 5–7 | `mixed` × (`alternate`, `centred`, `column`) |

A mode that rolls nothing in *this* inventory is dropped the same way, which is a different rule and keeps the file
names honest: `sepp` owns nothing but Achenbach cubes, so `mixed` has nothing to turn there and the candidate it would
produce is `upright` under another name. A `-turned-` or `-mixed-` file always has something turned in it.

**`--roll-mirror` switches the axis off**, so every hand invocation that names cabinets keeps working exactly as it did.
The two options answer the same question at different resolutions, and a line saying `--roll-mirror=skram` means those
cabinets rather than "sweep three modes and ignore what I said". A recorded command line carries `--orientation=MODE`
rather than the cabinets it resolved to, so a replay stays correct when a new sub is measured or a wrong dimension is
corrected.

`stack:` still has to be typed into a file. `scene:stack` is the step before that: give it the gear and the
bounds and it writes one scene per arrangement that works, with a reason for every one it left out.

```bash
bin/console scene:stack --max-width=3.70 --interface-height=2.0
#   wrote      scenes/stacked-center.yaml    23 cabinets — LEFT OUT skram, it cannot be carried in this stack
#   skipped    stacked-block — the same rig as stacked-center
#   skipped    stacked-stereo — the same rig as stacked-center
```

With six Achenbachs the sub row is 3.70 m wide — the same as the stage limit — so there is no width left
for the two SKRAMs anywhere in the stack, and they are left out rather than forced on with an overhang the
rig cannot stand on. `--align` no longer changes anything either: the top row already reaches the full width,
so block and stereo alignment have nothing left to spread it into.

| Option | Meaning |
|--------|---------|
| `--from=ID` | repeatable, low frequency first. Default: every speaker ordered by [`audio.passband_hz`](spec-format.md#the-passband-and-the-difference-between-reach-and-use) — lowest driven corner first where both cabinets state one, heaviest first where either does not, subs before tops |
| `--owner=NAME` | repeatable: build from these owners' gear only. Default: **sweep every non-empty combination of them**, so each owner alone, each pair and everything. It narrows one axis rather than collapsing the sweep, so the stack counts, shapes, orientations and mirror styles are still walked |
| `--per-owner` | one stack per `owner`, side by side in one scene, instead of one rig out of everything. No new spec field: who owns a cabinet already *is* the split between the rigs here. **Not the same option as `--owner`**, which picks whose gear is in the rig at all. It collapses the sweep to that point, where `--systems=systems-apart` says the same thing as an axis narrowing |
| `--systems=VALUE` | repeatable: `pooled`, `systems-apart` or `tops-shared`. How separately the systems stand — see [the seventh axis](#how-separately-the-systems-stand-the-seventh-axis). Narrows the axis rather than collapsing the sweep, and it is the only way to ask for `tops-shared`, which has no flag of its own |
| `--stacks=N` | split each group into N stacks — how a stereo pair is asked for |
| `--split=MODE` | `by-count` (default) gives every stack a share of every device; `by-type` gives each stack whole device types, balanced by `quantity × width`. **`by-type` is what makes a rig low** — a by-count stack holds every type and is as many rows tall as there are types, where a by-type stack holds two or three. It needs at least one type per stack and says so otherwise |
| `--target-sub-height=M` | **defaults to 2.5 m** — the sub/top transition the rig *aims at*, as opposed to the two bounds it has to stay between. A preference and never a refusal: it decides which of the legal arrangements comes back, and the bounds decide which are legal. See [aiming the sub wall](#aiming-the-sub-wall-rather-than-settling-for-the-lowest-one) |
| `--max-sub-height=M` | **defaults to 3.0 m**, the top of the band a sub/top transition should sit in — with `--interface-height` as its floor, and **a rig that misses either is written with the miss on it** rather than refused. See [the sub height band](#the-sub-height-band-which-is-an-aim-rather-than-a-gate). Passed straight to the stack's [`max_sub_height_m`](#a-ceiling-on-the-sub-height). Independent of `--split`: either alone is useful, and together is how a low rig out of the whole inventory is generated |
| `--no-asymmetry` | leave the odd cabinets out rather than giving one stack more than another. **By default every cabinet that can be placed is placed**: three M2122s over two stacks are 1 + 2 with the unevenness named in the scene header, where they used to be 1 + 1 with the third reported as left out |
| `--clearance=M` | air between neighbouring stacks. Default 0.5 |
| `--max-width` / `--min-width` / `--max-height` / `--interface-height` / `--gap` | the `stack:` constraints. **`--max-width` has no default**: state it and it is obeyed, leave it out and the rig is bounded by nothing but what carries it — see [an unstated width limits nothing](#an-unstated-width-limits-nothing) |
| — | **`build:all` replays these commands** as its first stage, so a generated scene follows the specs the way the models and renders already do. It is the only stage that writes outside `build/`; `build:all --dry-run` says how many files it would rewrite |
| — | **Scenes are written to `scenes/generated/`**, and everything derived from one follows it: `build/scenes/generated/`, `build/plans/generated/`, `build/renders/generated/`. Nothing has to know — `SceneLoader` reads `scenes/` recursively and an id is still the file's basename, so `scene:build stacked-center` resolves as before. Each file also carries the **command that made it**, so regenerating it needs no archaeology |
| — | **`build:all` prunes as well as writes**, in two passes. A `.blend`, plan or render under a `generated/` directory whose scene id no longer exists is removed and named. And a generated **scene** file the regenerate stage did not write is deleted too, because a replay *renames* rather than replaces — an axis that gains a value or a name that gains a field leaves the old file sitting there describing a rig the sweep no longer offers. Staleness is decided by the paths `scene:stack` reports writing and never by a timestamp; a run that wrote nothing deletes nothing, and a scene with no `Regenerate it with:` line is left alone because the pipeline did not write it. `--keep-stale` switches the second pass off |
| — | **`build:all` replays those commands as its first stage**, so a generated scene follows the specs the way the models and renders already do. It is the only stage that writes outside `build/` — `build:all --dry-run` says how many files it would rewrite. A file under `generated/` with no recorded command is skipped and named, never guessed at |
| `--at=X,Y` | where the rig is centred. Default `-0.302,0` |
| `--orientation=MODE` | repeatable: `upright` (nothing rolled), `turned` (every sub) or `mixed` (only the subs that get wider on their side). **Tops never roll at any setting**, and the reason is acoustic — see [the orientation axis](#the-orientation-axis-and-why-laying-subs-down-is-the-biggest-lever-there-is). Default all three, and it is the largest axis in the sweep: measured on the 66-candidate sweep it landed in, `upright` alone writes 11 scenes where every sub rolled writes 24. Naming `--roll-mirror` switches it off |
| `--mirror-style=MODE` | repeatable: `alternate`, `centred`, `column`. What a turned row does with the odd cabinet it cannot split in half — `alternate` swaps its side each row so the stack balances, `centred` leaves it standing in the middle so the row is symmetric at the cost of a 172 mm step, `column` sends it to the same side every row so the seam runs straight and the stack is lopsided by one. **Default all three, but only where something is rolled.** With nothing rolled the mirror is a no-op and all three are byte-identical, so the orientation and the style are swept as seven pairs rather than as 3 × 3. Stating the option explicitly always honours it |
| `--shape=MODE` | repeatable: `pyramid`, `free`, `v`. Default all three — **one scene each**, and every one names its shape in its id. All three are width rules in metres, see [the three shapes](#the-three-shapes) |
| `--align=MODE` | repeatable: `center`, `block`, `stereo`. Default all three — **one scene each**. The mode decides the ORDER of the tops row as well as its spacing: see below |
| `--roll-mirror=ID` | repeatable: lay this device on its side, mirrored about the centre line |
| `--low-end=MODE` | repeatable: `low` or `central`. Where the lowest-reaching cabinets belong. Default both — **one scene each**. `low` puts them on the floor, `central` pulls them onto the centre line even when that costs a row, which is what stacks two SKRAMs one above the other. See [where the low end goes](#where-the-low-end-goes) |
| `--event=ID` | a file in `events/`: the room, how each system is set up and what each system brings, overriding the specs' quantities for the swept systems. **A count of zero means left at home**, which is a different fact from a device the event never names. One `--owner` with an event is filed as `OWNER-EVENT` |
| `--system-low-end=OWNER:MODE` | repeatable: where this owner's lowest cabinets go, `low` or `central`, whatever `--low-end` sweeps. A stack follows the value its subs' systems agree on, and a rig whose systems all state one value sweeps one candidate named `stated` |
| `--room-width=M`, `--room-height=M` | Hard limits on the entire compiled rig. An event's limits can be tightened but not loosened |
| `--system-interface=OWNER:M`, `--system-target=OWNER:M` | Repeatable preferences for sub walls belonging to one owner. Pooled walls keep the ordinary defaults |
| `--quantity=DEVICE:COUNT` | repeatable: build with this many instead of the number the spec states. **Requires `--into=NAME`** — it changes the rig without changing its name, so the folder has to be said out loud |
| `--group=NAME:owner+owner` | repeatable: which owners are **one sound system**. Default `ours:sdwa5+sepp`, so a separated rig gives us one wall rather than two |
| `--order=NAME[,NAME]` | repeatable or comma-separated: system labels **left to right**, overriding the tallest-in-the-middle rule. A label the order does not name keeps its place at the end, so naming two of three systems is a partial instruction rather than a filter. **The rank is taken on the system part of the label**, so `--stacks=2`'s `ours-1` and `ours-2` both match `ours` and stay adjacent; and an order naming **none** of a rig's stacks, which is what a system order is to a `pooled` rig, leaves the height rule alone rather than silently putting it in solve order. `next-event` and `innschleife-psl-sdwa5-sepp` are generated with `--order=ours,psl,innschleife` |
| `--folders=AXIS[,AXIS]` | repeatable or comma-separated: axes to make directory levels instead of name fields — `inventory`, `stacks`, `systems`, `shape`, `orientation`, `mirror-style`, `align`, `low-end`, `feasibility`. Default `inventory`. **At most three**, for the same reason `--max-scenes` refuses rather than truncates |

**A near-field fill goes to the outer stacks, on the inner side, aimed at the near focus.** Three rules that only
make sense together:

* A device with fewer than one per stack goes to the **middle** if it is a sub — weight belongs low and central,
  and a sub has to be part of a row that carries something — and to the **outermost stacks, in pairs** if it is a
  top, because the tops too few to give every stack one are the small boxes. Two 2-ways across three stacks come
  out one, none, one.
* The **widest top is the long throw** and every narrower one is fill, which is the same choice `topRow` already
  makes when it centres the widest and puts the smaller boxes outboard. A fill takes `aim: near`; the long throw
  keeps the placement's own aim. Before this, one `aim` covered every top a stack carried, so a 2-way beside an
  M2122 was thrown at the far focus instead of at the front row.
* A fill is solved `align.clear_of` the nearest long-throw run **on its own side**, with the working gap as the
  clearance. That is what a nominal gap cannot do: two tops aimed at one focus from different x take different
  *yaws*, the outer one turns more, and it turns *into* its neighbour. At the far focus that cost 7.9 mm of the
  stated 20 in a one-stack rig and bit **1.7 mm** in a three-stack rig's right stack; at the near focus, with the
  fill toed in 36°, it bit **117 mm**.
* **`clear_of` rather than `outside`, and the fill's height is why.** `outside` measures the span its reference covers,
  and an aimed cabinet's span runs far wider than its body — a 2-way yawed 29.4° presents 0.8523 m on a 0.5 m cabinet.
  Down a chain of fills that compounds, and it drove a GMSS turbo top **514 mm** off the run gravity had seated it on,
  leaving it hanging **228 mm** over a step while the solver still believed it was carried. A fill only has to miss its
  neighbour, so it is measured on the shells and lands on exactly the stated working gap instead of well past it.

The long throw is therefore emitted **first** within its tier, because the reference can only name a placement that
already exists. Only the order changes; which segment is which does not. And the long throw may itself land in
several runs — a stepped tier below splits three M2122s into two — so each fill is solved against whichever is
nearest on its side, which clears the rest by construction.

A chained run **moves as a body**, and that is what the stated side is for. A run of several cabinets is a segment
of one row rather than two columns around a centre line, so splitting it would send its inner half through the run
it was told to clear. See [`side`](#outside--the-room-past-a-placements-outer-faces) for the psl tops row that was
torn in half that way.

`--stacks=N` deals the inventory out **evenly, and mirrors if it can**. Two strategies are tried — split every
device evenly, which makes the stacks identical, or keep a device whole in the middle stack when there are too
few of it to go round — and the one that stands up **more cabinets** wins, with the even split breaking a tie.

Scoring by cabinets rather than by "did it solve" is the point. An even split that cannot be carried does not
fail: the generator drops the offending device and returns a perfectly good rig without it, so falling back only
on an error would take a mirrored 20-cabinet rig over a 22-cabinet one every time. Two **upright** SKRAMs cannot
be split one per stack — a SKRAM is 610 mm and a Flexy 591, so a row above an odd-count row lands on the joints
and gets 49.9 % of itself on the taller cabinet, cantilevered over a 151 mm drop — and they are kept together.
The same pair **turned** can be split, because on its side a SKRAM is 914 × 610 and carries a Flexy row squarely,
so it is: `--stacks=2 --roll-mirror=skram --roll-mirror=flexy-folded-horn-hybrid` gives two identical stacks of
eleven with one SKRAM centred in each.

Only subs are held back by the second rule. A top does not flank anything and nothing stands on it, so one
Tecnare per stack is a perfectly good top row; applying the rule to tops made the middle stack hoard every one of
them. A device with **fewer than one per stack** is always kept whole, since splitting two 2-ways across three
stacks would otherwise leave every one of them out.

The remainder of an even split is **left out and named** rather than dealt to the earlier stacks: three M2122s
over two stacks are 1 + 1 with the third reported, because 2 + 1 makes a stereo pair that is not a pair — one
side would get a wider top row, a different interface height and a different rig.

**One stack of each pair is the mirror image of the other**, and that is correct-by-default rather than an
option: an unmirrored pair is the same rig built twice, with both SKRAM mouths facing the same way, both tops rows
in the same left-to-right order, and the two fills therefore on the same side of their stacks instead of both
facing the middle. It measures identically to a mirrored pair, which is why nothing caught it for so long.
`stack.mirror: true` says a stack is built reflected; `2 * index < of - 1` picks the earlier stack of each pair,
so the later one — and the middle stack of an odd-numbered rig — solve exactly as they would without it.

The reflection itself is `Tier::flipped()`: reverse a row's segments and hand every quarter turn the other way.
Both halves are needed — reversing alone moves the cabinets and leaves them facing as they were, negating alone
turns them without moving them. It is the sibling of `Tier::mirrored()`, which splits a row at its **own** middle
so the row is symmetric about itself; the two are one character apart at a call site and a whole rig apart in the
result. A symmetric row is its own mirror image, so the flip only shows where a row is lopsided — a tops row of
`M2122 + 2-way`, or an odd-count mixed row whose middle cabinet had to pick a side.

**The interface height decides how the subs are arranged, not just how tall the rig is.** Worth knowing because
it produces two genuinely different two-stack rigs out of the same 24 cabinets:

* asking for **2.0 m** puts the Achenbachs in a row of their own — `1.832 / 2.424 / 1.840` and the tops at
  2.277 m, over a standing crowd
* asking for **1.6 m** lets two Flexys move up beside them instead — `3.054 / 3.062`, a flat wall in two rows, and
  the tops 600 mm lower at 1.677 m

Both are shipped (`stacked-two-center` and `stacked-two-flat-center`) because neither is strictly better. Six
Flexys either form a row *under* the Achenbachs or lend two of themselves to flank them; there is no third
arrangement, and which you want depends on whether the tops have to clear heads.

A generated scene is **re-solved on every build**, so everything the solve decided has to be in the file. A split
rig therefore writes each stack's share as `count:`, and a turned one writes `roll_mirror:`. Both were once left
out, and a share left out is the worse of the two: a rig reported as two stacks of eleven was *built* with every
stack holding all twenty-three cabinets, two walls 0.5 m apart and 561 mm inside each other.
| `--subs=WHERE` | `mixed` (default), `beside` (the widest sub stood on the floor next to the rig), or `both` |
| `--id=PREFIX` | base scene id. Default `stacked` |
| `--max-scenes=N` | refuse past this many. **Default 1500** — a fuse against an axis added by mistake, not a cap on the sweep. **It counts one invocation, not the tree**: the thirteen committed inventories come to 2158 scenes across thirteen runs, and the largest committed inventory is `gmss-sdwa5-sepp` at **496** files. The room-limited `next-event` folder holds **8** files, all possible, none pooled, 4 with the systems apart and 4 with the tops shared. Over the limit nothing is written at all |
| `--dry-run` / `--force` | print instead of writing; overwrite an existing scene |
| `--jobs=N` / `-j` | processes to solve the sweep in. **Default 0, which is one per core**; `1` is the serial path. See [the sweep runs across every core](#the-sweep-runs-across-every-core) |

#### How separately the systems stand, the seventh axis

**Every generated scene pooled the gear until 0.91.0**, and that was verified rather than assumed: not one written
file carried `--per-owner` in its recorded command, because naming that option collapses the sweep to a single
point. A rig with each system in its own stack could be asked for by hand and never came out of the sweep.

| value | what stands where |
| --- | --- |
| `pooled` | every stack gets a share of every cabinet, whoever owns it |
| `systems-apart` | each system is its own group, dealt across the stacks in turn |
| `tops-shared` | each system's **subs** are its own group, and every top in the rig is one pool dealt across those walls |

`--systems=VALUE` narrows the axis the way `--shape` and `--align` do, and it is repeatable. `--per-owner` is the
older way to ask for one value of it, means `systems-apart`, and collapses the sweep rather than narrowing it — it
stays exactly as it was, because 433 written scenes record their own regeneration with it.

**None of the three values is marginal.** On the `gmss` + `sepp` pair the sweep writes **134 `systems-apart`, 130
`tops-shared` and 103 `pooled`**, because a system in its own narrower stack stands up more often than two systems in
one wide one, and the tops of one system on the other's subs is a third rig again. Across the whole sweep the three
values come to **641 `systems-apart`, 738 `tops-shared` and 779 `pooled`**, for **2158**. All three values remain represented after the room limits.

**A single-owner rig is offered `pooled` alone**, since one system separated from nothing is one system. That retires
both separated values: one system's subs with its own tops dealt back onto them is the rig `pooled` already wrote.
Leaving it to the deduplication would mean solving every single-owner rig twice to write one file, and single-owner
rigs are 233 of the sweep across six folders: `gmss` 112, `sdwa5` 64, `innschleife-next-event` 25,
`psl-next-event-light` 13, `psl-next-event` 11 and `sepp` 8. **`sdwa5-sepp` is single-owner too**, by grouping rather
than by ownership, which is why it writes 132 `pooled` scenes and no separated ones.

##### What `tops-shared` shares is the pool and not the row

**The tempting reading is one tops row bridging two sub walls, and it cannot be built.** That was settled by
measuring rather than by argument: in the `systems-apart` scenes the three walls come out **2.31 / 2.383 / 1.8 m**
high, and in the upright variant **2.44 / 3.61 / 1.8**. Two walls drawn from two different inventories do not come
out level, and there is no common module to make them — our cabinet heights are 0.600 / 0.763 / 0.836 / 0.914 /
0.960 m, no two of them multiples of anything. A row resting on both would hang in the air over the lower one, which
no solver can fix. A row that really does bridge two walls belongs to a **mirrored pair out of one pool**, whose
walls are identical by construction, and that is SYM-3.

**On the gear we own the shared pool is not a subtlety.** There are exactly three top types and one belongs to each
owner: three Tecnares to `sdwa5`, two 2-ways to `sepp`, three turbo tops to `gmss`. So `pooled` mixes everything
into one stack, `systems-apart` puts each owner's tops straight back onto that owner's own subs, and only this value
can stand a Tecnare on GMSS's wall. Borrowing tops across systems is the ordinary shape of a shared gig, and the
repository supports lending gear on purpose.

**The deal is a second pass over solved walls.** Only a pass that runs after the sub stacks are solved can see how
much top face each wall offers, because the number of rows a wall comes out with is the solver's decision rather
than the inventory's. The rule is one sentence: **widest top first, each cabinet to the wall with the most unused top
face.** Three things follow, all deliberate:

- **The long throw is placed before the fill**, so the biggest boxes get the best walls.
- **Cabinet by cabinet, so the tops spread rather than pile up.** Three Tecnares across three walls come out one
  each, because a wall with no tops on it has nothing firing over the crowd.
- **Every top is dealt somewhere, even where no wall has room left.** The budget goes negative rather than the
  cabinet being held back, and the solver is the backstop: it reports whatever it cannot carry as `LEFT OUT`.

**The budget does not prove anything fits**, and that boundary matters because it looks like a geometric claim. The
deal is made on the walls as the first pass solved them, and the second pass re-solves each stack from a different
inventory — subs plus tops — so the wall it measured may not be the wall it gets. Bearing, height and
interpenetration are all checked afterwards by the same rules every other rig goes through. What the budget is for is
spreading the tops sensibly. See `App\Scene\SharedTops`.

#### Possible and impossible, the sixth axis

**A candidate is one or the other, so this doubles the sweep rather than multiplying it.** Five axes are things you
ask for — the rig, the shape, the orientation, the mirror style, the alignment — and this one is the solver's
answer, which is why there is no `--feasibility` option: asking for a rig that does not stand up is not a request
anybody makes.

A rig that floats a top or buries two cabinets in each other **used to be refused with a sentence**. It is now
written, named `-impossible`, and `scene:build` cages the offending cabinets in red so the failure is something to
look at. "A `turbo-top` would stand at 0.660 m with nothing under it across x" took a debug dump, two probes
and a corrected coordinate mapping to understand; the picture takes a second.

Measured across the committed sweep: **2327 possible and 361 impossible**. The default `sdwa5-sepp` inventory has
none at all, so the examples live in the borrowed rigs, where a wall has cabinets it cannot carry. **The refusal
and deduplication figures behind that 361 have not been re-measured** since the grouping and low-end axes landed;
the older sweep collapsed 144 refusals into 60 names because 84 of them produced the same impossible geometry as a
sibling, and the mechanism is unchanged even though the counts are not.

**Every id says which side it is on**, including the possible ones. A name with a gap in it says a value was left
out and never which one, which is the same argument that took `pyramid`, `upright` and `alternate` out of hiding.
The alignment field is padded now that something lines up behind it, and whatever ends up last stays ragged.

**What is still a refusal.** Only the two checks that name a *cabinet* moved. A rig with no workable arrangement at
all, a scene that will not parse, a compiler violation and a bad request are all still refusals, because there is
no geometry to look at — painting nothing red helps nobody.

An impossible scene carries the reason in its own header and `ShippedScenesTest` skips it by name, through
`Feasibility::isImpossibleId` so the writer and the test cannot disagree about which files are which. A second test
compiles every impossible scene and insists it really does fail a check, so the axis cannot become a way to opt a
rig out of every geometry rule by naming it.

#### The sweep runs across every core

The candidates share nothing. Each is a solve and a compile over the same inventory, none of them reads what another
writes, and the file writing happens afterwards on the survivors — so the only thing the loop ever shared was the CPU.
It is now forked across as many processes as the machine has cores. **Measured on the default sweep as it stood at
1206 candidates, about 100 minutes of CPU between them: 25 minutes serial against 1m58s across 28 cores**, with
byte-identical output. The sweep is 2718 candidates since the seventh axis gained its third value and takes just under
three minutes on the same machine, so the ratio is what this measurement is good for rather than the figure.

Two properties are worth stating, because a fork would break each of them first. **The order out is the order in**:
results are re-keyed from the candidate list rather than from whichever worker finished first, so the scene list, the
refusals and the deduplication all come out in the order a serial run produced. And **work is taken rather than
dealt**: an `all` rig solves in about a minute and a single-owner rig in a second, so workers pull the next candidate
off a shared cursor instead of being given a slice up front.

`--jobs=1` is the serial path, for a debugger or a platform without `pcntl`. Generated scene files are written to a
temporary name and renamed into place, so two replays that name the same file cannot interleave inside it.

How many scenes you get is a parameter, not a decision baked in: `--align=block --subs=mixed` is exactly one,
the default is three. Going over `--max-scenes` is **refused rather than truncated** — a silent cap reads as
"that is every possibility" when it is not.

**A cabinet that cannot be carried is left out, and the scene says so.** The two SKRAMs are the case: nothing
shares their height so they cannot be mixed into a row, and a row of the two of them carries nothing above it.
Refusing the whole twenty-three-cabinet rig over that is far less useful than placing the twenty-one that work
and naming the omission in the file's own header — which is what the solver's error already advises.

Every candidate is **compiled before it is written**, and arrangements that resolve to the same rig are
written once. A worked refusal, which is also a real answer about our gear:

```bash
bin/console scene:stack --max-width=10.0 --interface-height=2.0
#   skipped stacked-center — stack.interface_height_m (2.000): the subs stack 1.514 m high …
```

At 10 m wide the bottom row swallows all twelve Flexys, so only two sub tiers are left and the tops would
fire into the crowd. A wide stage is not automatically a better rig.

The output is a `stack:` block rather than the expanded tiers, so a generated scene **re-solves every build**
and follows the specs when a cabinet is finally measured, instead of freezing today's answer into a list of
rows.

`align` on a stack applies to every tier **except the bottom one**, whose edges become the envelope when no
`across`/`inside`/`width_m` is stated. Only the **topmost** tier is spread: a tier that carries another one
has to stay tight, because spreading it turns it into gaps and the tier above then stands over air.

### Groups inside groups

Any group nests inside any other. The sibling key is the **cell**; `in` is what that cell is nested inside,
read inside-out.

```yaml
  arc: { mode: convex, count: 3 }     # the cell: three tops in a fan
  in:
    - lattice: { count: [1, 1, 2] }   # …and that fan, in two tiers
    # - lattice: { count: [2, 1, 1] } # …and two of *those*, side by side
```

* **An outer group spaces itself on what it actually replicates.** Two tiers of a three-wide fan step by
  the fan's extent, not by one cabinet's width — which is the number nobody should have to work out.
* **A group is a rigid body.** The outer turn moves the inner arrangement's offsets as well as its
  rotations, so nesting a group *places* it. Put a touching pair inside a convex fan and the pair's step
  runs along the cabinet's own axis; left in the world's axis, the second cabinet of each pair would stand
  155 mm behind its own seam.
* **Ids read outermost first, and skip any dimension with only one value.** A row of seven is still
  `sub-row-1 … sub-row-7`; two tiers of that row are `sub-wall-1-1 … sub-wall-2-7`.
* **Geometry decides layout; aiming only decides where cabinets point.** Nesting is composed from stated
  rotations alone, never aimed ones, which is what keeps the rig's front face — and so the focus point, and
  so the aiming — from being circular at any depth.
* Turning a multi-cell cell over does what turning an arrangement over physically does: a fan comes out
  mirrored, and a centred row comes out mirrored *into itself*, which is why `at` is the middle of a group
  rather than its edge.

### Alternating orientation

`roll_cycle` turns successive cells over as it walks one axis, which is how a mirrored horn wall is one
placement instead of two.

```yaml
  row: { count: 6, gap_m: 0.02 }
  in:
    - lattice:
        count: [1, 1, 2]
        roll_cycle: [180, 0]   # lower tier upside down, upper tier the right way up
        cycle_axis: z          # required: x and z both have cells
```

* **It composes with `roll_deg`**, which is where the second alternating row the rig needs comes from:
  `roll_cycle: [0, 180]` gives 0, 180, 0, 180…, and `roll_deg: 90` alongside it gives 90, 270, 90, 270…
  One mechanism, no special case.
* **`cycle_axis` is required when more than one axis has cells.** The same line `mode` draws on an arc: a
  cycle running down x instead of z on a six-by-two wall turns every *column* over instead of every tier,
  which is twelve cabinets wrong and renders perfectly plausibly.
* **Values must be quarter turns.** The cell arrives as an axis-aligned box, and only a quarter turn can be
  applied to one exactly; anything else would need the cell's real outline and would silently over-space.
* **Spacing stays uniform, on the widest attitude in the cycle.** Solving each joint separately would make
  a cell's position depend on its neighbours while the anchor sits in the middle, so changing the cycle
  would move whatever stacks on the placement. The cost only shows up mixing quarter turns with half turns,
  which is not a real setup — `[0, 180]` and `[90, 270]` both leave the extent alone.
* **An all-quarter-turn cycle derives its own spacing**, by laying out the **bodies** at a uniform pitch rather
  than the origins. That distinction is the whole of it: a rolled cabinet is not centred on its origin, so
  uniform origins drive adjacent rolled cabinets 591 mm into each other. `full-rig-quarter-turned.yaml` used to
  work around it with a hand-stated `step_m: 0.02` inside a two-cabinet row nested twice; it is now one row of
  six with a `gap_m`, and the geometry is identical to the micrometre. A cycle mixing `0` with `90` still cannot
  — those bodies are two different widths, so there is no uniform pitch — and a stated `step_m` always wins,
  because that is the author overriding the spacing outright.

### `roll_mirror` — the halves turned opposite ways

```yaml
  row:
    count: 6
    gap_m: 0.02
    roll_mirror: 90        # right half 90, left half its mirror image at 270
```

A **mirrored** row instead of an alternating one: the half past the middle takes the stated quarter turn, the
half before it takes `360 −` that, and the wall is symmetric about the rig's centre line. Folded horns are the
reason it is wanted — `scenes/full-rig-mirrored-subs.yaml` is `full-rig-quarter-turned.yaml` with three mouths
opening left and three opening right instead of alternating pairs.

**Same envelope**, which is the useful part. Within a half two same-rolled bodies need `W + gap`; at the seam,
where the two halves fall away from each other, they need only `gap`. So the row still measures
`n·W + (n−1)·gap` — 4.678 m for six rolled Flexys, exactly what the alternating pattern measures. Only the
handedness differs, and the two scenes are a straight comparison.

**And the spacing is derived, where `roll_cycle`'s cannot be.** This is the real difference between the two
keys. A rolled cabinet is **not centred on its own origin**: geometry runs from its bottom-centre, so at 90 the
body ends up entirely to the right of where the origin was and at 270 entirely to the left. `roll_cycle` spaces
*origins* uniformly, which is why `full-rig-quarter-turned` has to state `step_m: 0.02` by hand and warns that
a derived gap "drives adjacent cabinets 591 mm into each other". `roll_mirror` lays the *bodies* out at a
uniform pitch and puts each origin wherever its own body needs it, so the seam falls out of the gap with
nothing stated — and it stays right if a cabinet is ever re-measured.

* **90 or 270 only.** A half turn leaves the body centred and would mirror nothing.
* **`step_m` is refused alongside it**, because a stated step is applied to the origins, which is exactly what
  opens the seam by a whole cabinet.
* **`roll_cycle` is refused alongside it** — two keys deciding the same cells' roll is a contradiction.
* **An odd count cannot be mirrored exactly.** `intdiv(n, 2)` cabinets go left and the rest right, so the middle
  one joins the right-hand half and the row is lopsided by one cabinet.

It is also a **stack entry key**, which is how a solved rig gets turned subs:

```yaml
    stack:
      from:
        - device: flexy-folded-horn-hybrid
          roll_mirror: 90
        - achenbach-18            # upright, as before
```

The solver then fits and stacks that device by its rolled dimensions throughout — four rolled Flexys fill a
3.70 m stage where six standing up do, and a tier of them raises what is above by 591 mm rather than 763. Every
one of those numbers comes from the same rotated box the compiler places the cabinet with, via
`RolledBox::of()`; swapping width and height by hand is near enough for a plain box and wrong for a Tecnare,
whose shell is a chamfered trapezoid.

Named per device rather than inferred, because **no spec field says which cabinets are horn-loaded** and adding
one to drive a rotation would be inventing a property to serve a layout. `scene:stack --roll-mirror=<id>` is the
command-line form, repeatable.

`--orientation` is the coarser form of the same question and the one the sweep walks — see
[the orientation axis](#the-orientation-axis-and-why-laying-subs-down-is-the-biggest-lever-there-is). It leans on
`subtype: sub` and on the recorded dimensions, which are claims the specs already make for their own reasons, and never
on a property invented for it. Naming `--roll-mirror` switches the axis off, so the two never disagree about one rig.

**A limit worth knowing before planning a turned rig:** a rolled SKRAM is 610 mm tall and a rolled Flexy 591 mm,
so a bottom row mixing them has a 19 mm step through it and the tier above straddles that step. Gravity lifts
each cabinet onto the taller neighbour it catches, the bearing check then reports 17 %, and the whole-inventory
turned rig is refused. Turned rigs work when the row below is level — the Flexys, the Achenbachs and the tops
together come out with nothing worse than 94 % bearing.

### `outside` — the room past a placement's outer faces

`across` and `inside` are both widths a tier has to **span**. `outside` is a clearance it has to **keep**, on the
far side of somebody else's edges — the case a fill beside a group actually needs, and the one `align` could not
express:

```yaml
  - id: side-fills
    on: achenbach-row
    aim: focus
    align:
      mode: stereo
      outside: tops        # sit beyond the arc's outer faces…
      inset_m: 0.020       # …with 20 mm of air
    row:
      count: 2
```

`full-rig-arc` carried the gap as a comment for as long as it existed: *"the fills have to clear the arc's outer
faces, and `align` can measure a placement's extent (`across`) or the gap between its outermost cabinets
(`inside`) but not the room outboard of it. 2.60 puts them about 20 mm clear."* It does not — **that spacing left
37.8 mm**, nearly twice what the comment claimed, which is what a round number picked by hand tends to do. Stating
the 20 mm and solving for the spacing puts the fills 18 mm further in and makes the number mean something.

The clearance comes out of the two measurements `Envelope` already makes: the free span between my own outermost
cabinets, less the obstacle's extent, halved — per side, because the arrangement is symmetric about `at`. Same
bisection as every other alignment, against the same rotated boxes, so the spacing it lands on is the spacing
that actually clears.

**`inset_m` is a minimum, not a target.** Cabinets already further out are left exactly where they are rather than
pulled back in, and that is the reading that keeps other decisions intact: a fill that gravity re-seated onto a
shoulder for its bearing sits 517 mm clear, and dragging it back to 20 mm would undo a repair made for a reason.
Where the natural spacing does bite — which is every contiguous tops row once toe-in is applied — the solve pushes
out until the air is really there.

**`side: left` or `side: right` says the group is on one side of its reference, and then the whole group moves.**
Without it each copy reads its side from the sign of its own offset, which *is* the column split, and that is what
a pair straddling its reference wants — `full-rig-arc`, `full-rig-arc-turned`, `full-rig-truss` and
`both-systems-side-by-side` all write `outside: tops` on a `count: 2` row with no side, meaning one fill each way.
With a side stated, every copy takes it and the group translates, keeping its own internal spacing.

A lone cabinet needs it in either case, because a single copy sits at offset 0 and has no sign to read. A stack
knows the answer for both: its fill is the segment beside the long throw, and which side of it is a fact about the
tier.

**Reading the column split on a run of two was a real bug and not a corner case**, because a stack states a side
for every chained run of a tops row. A tops row wider than what carries it lands in several runs, and a run of two
was then dealt ±2.7735 m about its own centre instead of being translated 55 mm outboard. Measured on
`stacked-1-systems-apart-free----mixed---centred---center-low-----impossible`, whose psl tops row is five EF6s
landing as three and two: one of the pair ended at +3.6435 m, about a whole row width past its place, and 0.2020 m
inside a cabinet of the stack standing next to it. It is also what made that rig impossible — with the group moved
rather than split, the row is regular, every top is carried and the scene has no faults at all.

## Line arrays — a hang rather than a fan

`line_array` chains elements below one another, each tilted a little further than the one above.

```yaml
  line_array:
    count: 6
    splay_deg: 5           # one angle for every gap…
    # splay_deg: [1, 2, 3, 5, 8]   # …or one per gap, which is a J array
    # gap_m: 0.01          # optional: air at every joint, measured along the hang
```

It is a **sibling of `arc`, not a rolled version of it**, and the difference is structural. An arc rests on
one centre of curvature shared by every cabinet, which is what makes its wedge argument work and its radius
one maximum. An array is a chain: every gap has its own angle, so there is no shared centre and nothing to
take one maximum over.

What it does share is the contact solve, transposed. An arc works on the plan outline, where cabinets meet
side to side and the turn is about Z; an array works on the **side** outline, where they meet top to bottom
and the turn is about X. The wedge story transposes with it: an arc's flush splay is the angle between the
outline's two side edges, and an array's is the angle between its top and bottom edges —
`atan((height − front_height) / depth)`, which for a 0.96 m box with a 0.80 m front is 17.10°.

* **The joint hinges where a frame would pin it**, and which edge that is follows from the geometry. A
  downward-curving array pins the **rear** edge; one curving up pins the **front**; at a wedge's own taper
  both give the same answer because the faces meet flat. Pinning the front edge of a downward curve instead
  drives a 0.52 m deep cabinet 45 mm into its neighbour — the vertical twin of the concave interpenetration
  the arc warns about, and just as plausible in a render.
* **The splay is the cabinet's own tilt, not a turn of the group.** So the array owns the increments and the
  placement's `pitch_deg` or `aim` owns the base tilt — the same division of labour an arc uses for yaw. Five
  degrees per gap on a 0.52 × 0.96 element drops the next one 0.979 m and sets it 0.085 m back.
* **No element is seated.** `zLift` puts a tilted cabinet back on its slot, which is right for anything
  standing on something and wrong for a hang: each element is tilted differently, so lifting each one would
  pull the array apart at every joint.

* **A hang is aimed once, not element by element.** It is one rigid body, so `aim` decides the attitude of
  the whole array at its anchor and the splay is added on top. Aimed per element instead, each one turns
  towards the target on its own and the splay cancels out exactly — four cabinets all pointing at the same
  spot, which is the opposite of a J.
* **And the whole frame is solved at that attitude**, which is the other half of "one rigid body" and easier
  to get wrong. A joint is not scale-free in the angle: the one that closes between 0° and 2° is not the one
  that closes between 14.3° and 16.3°, so the base tilt has to reach the chain rather than being applied to
  each element afterwards. The chain swings with the hang's yaw for the same reason — the joints are solved in
  the side view, which has no x in it, so their offsets come out along the world's y while every element is
  turned towards the target. `scenes/flown-array.yaml` is aimed 11.1° off-axis and tilted 14.3° down; getting
  either of those wrong put 21.7 mm of each element inside the one above it, invisibly.

Hang it with `fly`, below.

## Flying a hang

`at` is a ground position and `on` is the top of an earlier placement. Between them they cover everything
that stands up, and nothing that hangs — which is why a `line_array` was unusable until this existed: its
elements grow *downwards* from their anchor, so anchoring one on the floor puts it under the floor.

```yaml
  - id: hang-left
    device: tecnare-m2122
    at: [-3.0, 0.0]          # where the hang is over
    fly:
      height_m: 6.0          # required: how high the suspension point is
      point: top-left        # optional: which of the device's rigging.points is up there
      id: main-bar           # optional: what the weight is grouped under. Defaults to the placement id
    aim: far
    line_array: { count: 4, splay_deg: [2, 4, 7] }
```

`scenes/flown-array.yaml` is two of those off one bar.

* **`point` is what makes this more than an absolute z.** A cabinet does not hang from its own
  bottom-centre; it hangs from hardware somewhere on its shell, and `rigging.points` already says where.
  Naming one lets the scene state the thing that is true — *that* point is at 6 m — and the cabinet's slot is
  worked out from it. An M2122's `top-left` sits 0.960 m up and 0.185 m left of centre, so hanging it at 6 m
  over `x = -3.0` puts the cabinet's slot at 5.040 m and its centre-line at −2.815.
* **Rigging positions are in the measuring frame**, the same frame a slot position is in
  ([conventions.md](conventions.md#positions-are-always-measured-from-the-footprint-centre)), so this is a
  plain subtraction. `geometry.origin` is not involved and does not have to change — the same spec stays
  usable both ground-stacked and flown, which an `origin: rigging-point` commitment would not allow.
* **A flown cabinet is not lifted onto a slot.** `zLift` puts a tilted cabinet back on the thing it stands
  on, which is right for a stack and wrong for a hang: the hardware decides where it is.
* **`id` is for the truss, not for the placement.** Two hangs off one bar name the same `id` and the report
  adds them up, because what gets checked against a capacity is the total.
* **A hang that reaches through the floor is reported**, with how far by. It is the one arrangement that can
  be told to sit above the floor and still end up below it, so it is worth saying out loud rather than
  leaving to a render.

## All speakers, owner ignored

Four scenes deal both systems' cabinets into stacks without caring whose they are — the comparison the per-owner
scenes cannot make:

| scene | cabinets | what it is |
|---|---|---|
| `all-speakers-one-center` | 41 | one stack, every cabinet. Hand-written — see below |
| `all-speakers-two-center` | 41 | everything in, asymmetric: two stacks of unequal height |
| `all-speakers-two-matched-center` | 28 | a true pair, 14 + 14, at the cost of 13 cabinets |
| `all-speakers-three-center` | 39 | three stacks, the widest of the set |
| `all-speakers-three-low-center` | 41 | three stacks, **every transition under 3 m** |

**One stack needs a stated `mix_with`, and that is the only reason it is hand-written** — `--from` on the command
line can only name devices, not say which of them share a row.

Without the mix, three GMSS middle subs pin a 1.825 m tier that no row count widens; the bearing rule then caps
everything above near 2.5 m, while all eight tops in one row are 3.744 m and cannot be split. `scene:stack --stacks=1`
refuses it at every width from 3.70 m to 10 m. Mixing one turbo sub either side of the middle subs takes that tier to
2.885 m and the whole stack widens with it — **nine tiers become seven and every cabinet goes in**.

**The old text below is kept because the arithmetic still explains the shape of the answer:** `scene:stack --stacks=1` refuses this
inventory at every width from 3.70 m to 10 m, and since the fill was fixed to size rows against their support that
refusal is arithmetic rather than a limitation: three GMSS middle subs make a 1.825 m tier and there are only three
of them, so no row count widens it; every tier above is bounded to two thirds of a cabinet's overhang by
`Gravity::MIN_BEARING`, capping the stack's top near 2.5 m; and eight tops in one row are 3.744 m, with no second
row available because nothing stands on a top. So the scene **states which five tops go up** and leaves the three
Tecnares out. It is still solver-solved — a `stack:` block re-dealt on every build — with a human choosing the list.

**Two stacks cannot be symmetric with this inventory, so both answers ship.** Devices with two or three units are
kept together because a lone cabinet cannot be flanked, which piles them into one stack; forcing a true pair means
dropping every odd-quantity device — 13 of 41, including all the Tecnares and turbo tops. Neither is better, which
is why `stacked-two-center` and `stacked-two-flat-center` already coexist for the same reason.

### The sub/top transition, and what actually moves it

**Widening the stage does not move it at all.** Every width from 3.70 m to 8 m deals the same rows, because what
forces the row count is the cabinet counts and the support cap — not the stage. The lever is `interface_height_m`:
it is a height the tops must *clear*, and the solver picks the widest row that still reaches it, so raising it raises
the transition. All four scenes now state 2.5 m rather than 2.0.

Where that lands, and why not every stack can be in a 2–3 m band:

| scene | sub/top transition | |
|---|---|---|
| `all-speakers-two-matched-center` | 2.763 / 2.763 | in band |
| `all-speakers-two-center` | 2.763 / **4.359** | one stack in band |
| `all-speakers-three-center` | 3.146 / 3.122 / 3.146 | 122–146 mm over, and this is its floor |
| `all-speakers-one-center` | **4.163** | 33 subs in one stack cannot be under 3 m |

**A stack that is too tall cannot be brought down by width or by stack count.** More stacks does not help either:
the devices with two or three units are kept together because a lone cabinet cannot be flanked, so one stack always
inherits them and goes tall — at four stacks it is 3.722 m and at five, 4.833. The only things that lower a stack are
fewer cabinets in it, or wider rows; and wider rows are capped by the 1.825 m middle-sub tier. Widening *that* means
mixing it with a neighbouring device, which `mix_with` now does correctly — it took the one-stack transition from
6.546 m to 4.163 m and let all 41 cabinets in.

**A transition under 3 m needs the stack CONTENTS stated, which is what `all-speakers-three-low-center.yaml` does**
— 1.363, 2.037 and 1.445 m across three stacks, with all 41 cabinets. Each device type costs at least one row, so a
stack holding six sub types is six rows tall however wide the stage; giving each stack two or three types keeps all
three low. That split is the one thing `scene:stack` decides for itself and `--from` cannot override, which is why the
scene is hand-written. Its `from` lists are **widest-row-first**, which is load-bearing rather than cosmetic: the fill
is bottom-up, so a wide row listed after a narrow one lands on top of it and fails the bearing check.

The trade is width. Two or three types per stack means they stand side by side rather than piling up — 10.59 m across
against the generated version's 6.77 — and ten metres is every truss segment we own, so no goalpost spans outboard of
it. **A low rig and a truss over it are not both available with this much gear**, which is why `everything.yaml` keeps
the taller, narrower arrangement.

**The generated scenes do not reach the band, and that part is arithmetic rather than a setting.** Each device type costs at least
one row, and a mixed row is as tall as its *tallest* member — so merging two types only buys height when it removes a
row outright, which needs the whole flanking stock to fit in one row and the support cap decides that. A stack holding
seven device types is about seven rows tall whatever the stage width.

**None of these aims its tops.** Aiming a mixed tops row of more than a couple of device types places two cabinets
391 mm inside each other — one turbo top's width — and a wider `gap_m` barely moves it, so it is not toe-in. The
overlap sweep in `ShippedScenesTest` catches it; the aim is removed until the underlying fault is fixed.

### everything.yaml

`scenes/everything.yaml` is the only place every device in the repository stands in one picture: the three-stack
speakers, 10 m of truss with all four MACs hung, both Gerüste and all three racks. 55 cabinets, 3237 kg.

It also produced a fact worth having: **our 4 m crank stands cannot clear this rig.** Three stacks of 39 cabinets
reach 4.085 m, so a truss at 4.000 m hangs its fixtures inside the stacks — the sweep caught a MAC 285 mm inside
stack 1. The scene stands the goalpost on GMSS's 5.2 m towers instead.

## Truss over a rig

`scenes/full-rig-truss.yaml` is `full-rig-arc` with a goalpost over it: 8 m of three-point truss on two 4 m
telescopic stands. It exists because every other scene is cabinets on an infinite grey floor, which is fine for
checking a stack and useless for showing anybody what a stage looks like.

```yaml
  - id: tower-left
    device: truss-tower-4m
    at: [ -4.302, 0.0 ]

  - id: truss
    device: truss-f33-2m
    at: [ -0.302, 0.0 ]
    fly:
      height_m: 4.000
    row:
      count: 4
      gap_m: 0.0
```

Three things in that are worth knowing:

* **`fly` is how a span between two supports is placed**, even though nothing is hanging. `on:` would put the
  truss at the right height too, but it would claim the truss stands on *one* named stand. `fly` says "this sits
  at this height" and leaves what holds it up to the scene, which is what a goalpost needs. The height is not
  chosen either — the stands are 4.000 m at full extension, so that is where the truss's bottom chords sit.
* **`gap_m: 0.0` between segments.** A working gap is exactly what a truss coupler does not leave. Cabinets
  standing side by side want 20 mm; truss bolts up flush.
* **The hung weight is reported under the `fly` id**, so the four segments come out as one 41.2 kg total rather
  than four line items. `fly.id` is how two placements sharing one bar are added together — which is the case
  that matters once lights hang from it.

A truss is the one device whose geometry is not its bounding box: see
[spec-format.md](spec-format.md#truss) for why, and `roll_deg: 180` for a triangular truss the other way up.

### A tower cranked lower

A wind-up stand is not one height, and a spec states only its full extension. `extend_to_m` on a placement states
how far a `truss`/`tower` device is cranked:

```yaml
  - id: tower-left
    device: truss-tower-4m
    at: [ -5.2, 1.258 ]
    extend_to_m: 3.742
```

The compiler places a copy of the spec that is 3.742 m tall, so contact, the overlap check, the room check and the
report all read the cranked height. Blender instances the one model built at full extension and scales it along
its own height. Any other device, and any height above the spec's, is refused.

### A deco backdrop behind a generated rig

PSL bring a deco panel of 10 × 3.03 m to the next event, stated on 2026-10-01 together with their print file. It hangs from the front of our
F33 truss, and the truss stands on our two wind-up towers behind the systems. `specs/other/psl/deco-panel-10x3-03.yaml`
is the panel, PSL's `brings` in `events/next-event.yaml` brings it, and `events/next-event.yaml` names the truss:

```yaml
backdrop:
  truss: truss-f33-2m
  segments: 5
  towers: truss-tower-4m
```

`scene:stack` reads `subtype: deco` as "hang this". The solver never places it. After the rig is solved and compiled,
`StackBackdrop` adds four placements and the scene is compiled again, so every check sees them:

| Placement | Where, and why there |
|---|---|
| `backdrop-tower-left`, `-right` | under the two ends of the truss, inset by half the tower's width. Their centre lines stand 0.8 m behind the rig's deepest back face, because the unmodelled outriggers spread to 1.6 m |
| `backdrop-truss` | five segments flush, 10 m, centred on the rig. It rests at the ceiling less its own 0.258 m, so at 3.742 m under the 4 m room, with the towers cranked to that |
| `backdrop-deco` | flush on the truss's front face. Its top goes up to the ceiling, but at most half the panel stands above the truss's top. Under the next event's 4 m ceiling the truss already touches it, so the panel's top is the truss's top and its bottom is at 0.97 m. With no ceiling the panel's top is at 4.258 + 1.515 = 5.773 m |

The truss and the panel share `fly.id: backdrop`, so the report adds them up as one bar.

**Four refusals, each a fact about the gear rather than an arrangement.** A panel wider than the truss, more segments
than are owned, a panel that would reach the floor, and a tower load over the tower's `max_load_kg`. Our Varytec
stands are rated 85 kg. Five segments are 46.5 kg and the panel's estimate is 45.45 kg, so each tower carries 45.975 kg,
and the panel may weigh up to 123.5 kg. A run that brings a deco device with no truss named is refused too, and
`--backdrop=TRUSS:SEGMENTS:TOWER` states one without an event.

The recorded line carries `--backdrop` and the panel's `--quantity`, even though one panel matches its spec. A count
that restates the spec is otherwise dropped from the line, and the replay would lose the backdrop.

## Hanging fixtures from a truss

`scenes/gmss-full-stack-truss.yaml` hangs four moving heads under a 9 m truss, and it is the first scene here where
anything hangs from anything:

```yaml
  - id: macs
    device: mac-2000-performance-ii
    at: [ 0.0, 0.0 ]
    roll_deg: 180
    fly:
      height_m: 5.130
      id: truss
    row:
      count: 4
      gap_m: 1.610
```

**`fly.id` is what makes them one hang.** All four name `truss`, and the truss itself is flown under the same id, so
the report adds them into a single bar total — **195.2 kg**, being 37.2 kg of truss plus 158 kg of fixture. That is
the number a truss's capacity gets checked against, and it is why `Fly` has an `id` at all.

**`roll_deg: 180`, because a fixture clamped under a bar is upside down.** A moving head's spec models it in the pose
its datasheet height is quoted in — base down, head straight up — and hanging one means turning it over.

**Rolling changes what `fly.height_m` means, and this is the easy mistake.** A model is built in bottom-center
coordinates, so its mesh runs from the placement point *upwards*; rolled 180° about that point it runs *downwards*.
So for an inverted fixture `height_m` is its **top**, not its bottom. Getting it wrong the first time left the
fixtures hanging 800 mm below the truss instead of 70 mm — a gap the render showed immediately and no test could.

## What it tells you before Blender opens

```
Cabinets:      15
Total weight:  1224.0 kg
Tallest stack: 2.486 m
Footprint:     3.65 × 0.96 m
  flexy-folded-horn-hybrid   12 × =  1020.0 kg
  tecnare-m2122               3 × =   204.0 kg
  owner sdwa5                15 cabinets, 1224.0 kg
```

With anything flown, one more line per suspension point — which is the number a truss's capacity is checked
against:

```
  point main-bar               8 cabinets, 544.0 kg
```

Plus warnings that are cheap here and expensive on site:

* **more cabinets than we own** — easy to do by copying a stack; the report compares against each
  spec's `quantity`
* **borrowed gear** — any placement whose device has an `owner` other than `sdwa5`, so a setup cannot
  silently depend on someone else's cabinets
* **un-measured cabinets** — positions computed from design figures rather than measurements

`--dry-run` prints all of that without running Blender, which is also how CI checks scenes.

## Usage

```bash
ddev exec bin/console scene:build                    # every scene
ddev exec bin/console scene:build full-rig           # one, by id
ddev exec bin/console scene:build generated/sepp     # every scene in a folder
ddev exec bin/console scene:build --dry-run          # report only, no Blender
```

Models have to exist first: the command refuses rather than assemble a scene from stale models, so run
`models:build` after changing a spec.

Each device's collection is appended **once** and instanced per placement, so a 14-cabinet wall costs
one copy of the geometry — the shipped 15-cabinet scene is under 100 KB.

## Rendering

```bash
ddev exec bin/console scene:render full-rig                       # three-quarter / studio, 1600x900
ddev exec bin/console scene:render full-rig -c crowd -l stage     # eye height, event lighting
ddev exec bin/console scene:render full-rig -c top -l daylight    # plan view on grass
ddev exec bin/console scene:render generated/sepp                 # every scene in a folder
ddev exec bin/console scene:render --presets                      # list every preset
```

Output goes to `build/renders/<scene>-<camera>.png`. On the container's CPU a 15-cabinet scene takes
about 8 seconds at the default 64 samples.

### Camera presets (`-c`, `--camera`)

| Preset | Looks from | Lens |
|--------|-----------|------|
| `three-quarter` *(default)* | front right, slightly above | 42 mm |
| `front` | straight on, as the audience sees it | 42 mm |
| `side` | stage right, showing cabinet depth | 42 mm |
| `top` | plan view, for checking the footprint | 32 mm |
| `crowd` | eye height (1.65 m), aimed slightly low | 50 mm |

**Nothing is hardcoded to a particular rig.** Each preset is a *direction*; the distance is computed by
fitting the scene's bounding box as the camera actually sees it, so a wide shallow sub wall fills the
frame instead of sitting in the middle of it. The same preset therefore frames a single floor monitor
and a twelve-wide wall equally well — which is the whole reason the maths lives in PHP
(`src/Render/RenderPlan.php`) where it can be tested, instead of in the Blender script where camera
framing quietly drifts.

`crowd` deliberately frames tighter than the others: standing in front of a 4 m sub wall it fills your
view, and a shot that politely fits it all in undersells it.

### Lighting presets (`-l`, `--lighting`)

| Preset | For |
|--------|-----|
| `studio` *(default)* | neutral three-point — readable and honest |
| `stage` | warm key with coloured rims, event-like |
| `daylight` | sun and sky, for outdoor setups — which is most of them |
| `flat` | even and shadowless, for inspecting geometry (a chamfer, a grille inset) |

Light positions are multiples of the scene radius from its centre, and area-light power scales with the
square of that radius — otherwise a big rig comes out dark at the settings that light one cabinet
nicely. A sun is left alone, since irradiance does not fall off.

### Other options

| Option | Default | Notes |
|--------|---------|-------|
| `--samples` | 64 | Cycles samples; 16 is enough to check a layout, 200+ for something to show people |
| `-r`, `--resolution` | `1600x900` | `WIDTHxHEIGHT` |
| `--no-ground` | off | leave out the ground plane |
| `-a`, `--aim-lines` | off | draw where cabinets point: `tops` (the default when the flag is given) or `all` |
| `-o`, `--out` | `build/renders/<scene>-<camera>.png` | single scene only |

### Aim lines

```bash
ddev exec bin/console scene:render full-rig-arc --aim-lines            # tops only
ddev exec bin/console scene:render full-rig-arc --aim-lines=all        # subs too
```

Draws a thin glowing rod from the centre of each cabinet's front face along the direction it points,
stopping where it meets the floor and leaving a marker there. Off by default.

A nearly level ray meets the floor a very long way out — 1° of down-tilt from 2 m up needs over 100 m —
so beyond 40 m the ray is simply truncated and **no** floor marker is drawn. A marker therefore always
means the ray genuinely lands there, which is the only way the picture stays trustworthy.

The rod follows the cabinet's **actual** front axis, not the point it was told to aim at. That is
deliberate: it turns "these all aim at one place" from a claim into something visible, and a mistake in
the aiming shows up instead of being drawn over. When the framing is switched on the camera widens to
include the rays, since where they converge and land is the whole point of asking for them.

Rigging markers and the orange "estimated" tag are render-invisible, so they never appear in an image.

#### Asking for them in the scene

A scene whose whole point is where things aim should not need a flag remembered on the command line, so it
can say so itself — and a group inside it can disagree:

```yaml
aim_lines: tops        # scene level: none (default), tops or all

placements:
  - id: sub-wall
    device: flexy-folded-horn-hybrid
    aim_lines: true    # `tops` skips subs; this one is worth seeing anyway
  - id: fills
    device: eighteensound-2way-15
    aim_lines: false   # …and this one is not
```

`scenes/full-rig-all-tops.yaml` does exactly that: two groups aiming at two different points is invisible in a still
render otherwise.

* **The flag overrules the scene only when it is actually typed.** Whether `--aim-lines` was given has to be
  told apart from the value it defaults to, because bare `--aim-lines` already yields nothing — that is how
  it means `tops`.
* **A placement's `true`/`false` beats the mode's subtype rule**, so a sub can be shown and a top hidden.
* **`--aim-lines=none` beats everything**, which keeps it the way to get a clean picture of a scene that
  normally draws them.

Renders are gitignored along with the rest of `build/`. Cycles runs on the CPU because the container
has no GPU — EEVEE Next needs one.

Real reference layouts from past events live in Drive under
`Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/` as SVG — porting those into scene files is
TODO item 2.5.
