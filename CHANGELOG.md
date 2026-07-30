# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
