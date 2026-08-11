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
| `placements[].yaw_deg` | rotation about Z — aiming. 0 faces −Y, the convention every model uses. An `arc` supplies this instead |
| `placements[].pitch_deg` | down-tilt. Positive is nose-down, for aiming into an audience rather than over it |
| `placements[].roll_deg` | rotation about the front-to-back axis — 180 turns a cabinet upside down, 90 lays it on its side, and either way it keeps facing forward |
| `placements[].aim` | the name of a focus — `focus` for the single unnamed one, or any key of a named `focus` map. The compiler works out yaw *and* down-tilt |
| `placements[].aim_at` | `[x, y]` or `[x, y, z]` — aim at a named point instead |
| `focus` *(scene level)* | one focus, `{ distance_m, height_m, x_m }`, or a map of named ones, `{ near: {…}, far: {…} }`. Defaults: 10 m out, 1.8 m high, rig centre |
| `placements[].repeat` | `{ count, step: [x, y, z] }` — repeat along a stated vector |
| `placements[].lattice` | `{ count: [nx, ny, nz], gap_m, step_m, roll_cycle, cycle_axis }` — a 1/2/3-D grid, spaced from what it replicates, centred on `at` in x and y, stacking upward in z |
| `placements[].row` | `{ count, axis, gap_m, step_m, roll_cycle }` — a lattice with one open axis; `axis` defaults to `x` |
| `placements[].line_array` | `{ count, splay_deg, gap_m }` — a hang: elements chained below one another, each tilted further than the last. `splay_deg` is one angle or one per gap |
| `placements[].align` | `{ mode, width_m, across, inside, inset_m }` — how this tier is spread across a width, instead of stating `step_m`. See [align](#align) |
| `placements[].stack` | `{ from, max_width_m, min_width_m, max_height_m, interface_height_m, gap_m }` — a whole rig from constraints instead of a tier per row. Replaces `device` and any group. See [stack](#stack) |
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
| `inset_m` | taken off the envelope on **each** side. Default 0 |

Exactly one of `width_m`, `across` and `inside` is stated, and `center` takes none of them.

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
| `max_width_m` | how wide the stage or the truss lets the rig be. The row count falls out of it |
| `interface_height_m` | how high the sub stack's top face should reach, so the tops fire over a standing crowd. **Defaults to 2.0**, and it is an **optimum rather than a requirement** — missing it warns; state `0` to stop aiming for it |
| `min_width_m` | a floor on the widest tier: how you ask for a wide short wall rather than a tall narrow one out of the same cabinets |
| `max_height_m` | a ceiling or a rigging limit |
| `gap_m` | working gap between neighbours in a row |

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

**Also `align` only ever spreads the top tier, and only as wide as the tier carrying it.** Spreading a tier
turns it into gaps, so a tier with load on it has to stay tight; and spreading even the top tier to the bottom
row's width once put two 2-ways 1.84 m out with a 1.54 m Tecnare row under them.

### scene:stack — writing the scene for you

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
| `--from=ID` | repeatable, low frequency first. Default: every speaker ordered by [`audio.passband_hz`](spec-format.md#the-passband-and-the-difference-between-reach-and-use) — lowest driven corner first, subs before tops |
| `--per-owner` | one stack per `owner`, side by side in one scene, instead of one rig out of everything. No new spec field: who owns a cabinet already *is* the split between the rigs here |
| `--stacks=N` | split each group into N stacks — how a stereo pair is asked for. The remainder goes to the earlier stacks, so three M2122s over two is 2 + 1 and never 1 + 1 with the third dropped |
| `--clearance=M` | air between neighbouring stacks. Default 0.5 |
| `--max-width` / `--min-width` / `--max-height` / `--interface-height` / `--gap` | the `stack:` constraints |
| `--at=X,Y` | where the rig is centred. Default `-0.302,0` |
| `--align=MODE` | repeatable: `center`, `block`, `stereo`. Default all three — **one scene each** |
| `--roll-mirror=ID` | repeatable: lay this device on its side, mirrored about the centre line |

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
* A fill is solved `align.outside` the nearest long-throw run **on its own side**, with the working gap as the
  clearance. That is what a nominal gap cannot do: two tops aimed at one focus from different x take different
  *yaws*, the outer one turns more, and it turns *into* its neighbour. At the far focus that cost 7.9 mm of the
  stated 20 in a one-stack rig and bit **1.7 mm** in a three-stack rig's right stack; at the near focus, with the
  fill toed in 36°, it bit **117 mm**.

The long throw is therefore emitted **first** within its tier, because `outside` can only name a placement that
already exists. Only the order changes; which segment is which does not. And the long throw may itself land in
several runs — a stepped tier below splits three M2122s into two — so each fill is solved against whichever is
nearest on its side, which clears the rest by construction.

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

A generated scene is **re-solved on every build**, so everything the solve decided has to be in the file. A split
rig therefore writes each stack's share as `count:`, and a turned one writes `roll_mirror:`. Both were once left
out, and a share left out is the worse of the two: a rig reported as two stacks of eleven was *built* with every
stack holding all twenty-three cabinets, two walls 0.5 m apart and 561 mm inside each other.
| `--subs=WHERE` | `mixed` (default), `beside` (the widest sub stood on the floor next to the rig), or `both` |
| `--id=PREFIX` | base scene id. Default `stacked` |
| `--max-scenes=N` | refuse past this many. Default 24 |
| `--dry-run` / `--force` | print instead of writing; overwrite an existing scene |

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

**A single cabinet needs `side: left` or `side: right`.** Every other case reads its side from the sign of the
copy's own offset, which *is* the column split. A lone cabinet sits at offset 0, so there is no sign to read and
nothing can say which way outboard is. A stack knows — its fill is the segment beside the long throw — and states
it.

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
