# Measuring a cabinet

Most SdWa5 cabinets are DIY builds of commercial designs, so there are two ways to fill in a spec:
copy the original's published numbers, or measure the box we actually own. The first is much faster
and gets the library usable immediately; the second is the only one that is true of *our* cabinet.
Both are legitimate — `provenance` just has to say which one you did.

Start from the datasheet, measure later. See [inventory.md](inventory.md) for identifying originals.

## What drifts between a clone and its original

Worth measuring first, in this order:

1. **Weight** — different plywood, different hardware, different bracing. Easily 20 % off, and it is
   the number that matters for truss loading and for how many people it takes to lift.
2. **Outer dimensions** — a build follows plans loosely; a few centimetres are normal, and they are
   what decide whether the stack fits the stage and the van.
3. **Rigging points** — if there are any, their positions are build-specific. Never take these from
   a datasheet if the cabinet is flown.
4. Driver complement and coverage — usually as designed; least likely to have changed.

## The measuring frame

Everything is in **metres**, measured in the frame described in
[conventions.md](conventions.md#positions-are-always-measured-from-the-footprint-centre):

* origin at the **middle of the footprint, on the floor**
* **+Z up**, **front towards −Y**, **+X to the cabinet's right seen from the front**

So a point on the top front left corner of a 0.8 m wide, 0.45 m deep, 0.58 m high cabinet is at
`[-0.40, -0.225, 0.58]`.

This frame never changes, whatever `geometry.origin` the model uses — `origin` only moves the
finished model, it never reinterprets your numbers.

## Checklist

Per cabinet:

- [ ] **Width, height, depth** — outer, at the widest point, over the whole cabinet including
      corners and feet. Not the front baffle, not the internal volume.
- [ ] **Weight** — a luggage scale is fine; anything better than a guess is an improvement.
- [ ] **Grille inset** — how far the grille sits behind the front frame. A few millimetres, and it
      is the difference between a cabinet that looks right and a plain box. Omit if there is no
      grille.
- [ ] **Chamfer** — the radius of the rounded edges. Usually 10–15 mm.
- [ ] **Handles** — which sides have them (`left`, `right`, `back`, `top`).
- [ ] **Rigging points** — for each: an id, its position in the frame above, and the thread
      (`M10`, …). Only if the cabinet is actually flown.
- [ ] **Taper** — for a tapered top, the **back** width; for a wedge monitor, the **front** height.

Then in the spec file:

- [ ] set the provenance for **what you actually did**. Weighing alone is worth recording on its own:

  ```yaml
  provenance:
    dimensions: plans      # unchanged
    weight: measured       # you put it on the scale
  ```

  Once both are done, `provenance: measured` as a single value says the same thing more briefly.
- [ ] note anything surprising under `deviations` — that is what tells the next person the numbers
      are not the original's
- [ ] `ddev exec bin/console specs:validate && ddev exec bin/console models:build --id=<id>`

## Sanity checks

The validator catches impossible values, but not wrong ones. Two quick checks:

* Open `build/blend/<id>.blend`, select the body, press **N**: the dimensions panel must show
  exactly what the spec says, in metres.
* Run `ddev exec bin/console catalog`. A cabinet whose weight or volume looks out of line next to
  its neighbours usually means a unit slipped — centimetres written as metres is the classic.

`catalog` is the progress bar for this job — it reports dimensions and weights measured as two
separate counts, so an afternoon spent only with the hanging scale still shows up. The orange
viewport tag disappears once a spec's *dimensions* are no longer `estimated`.
