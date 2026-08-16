# Loading the vans

Which gear rides in which transporter, and whether the fleet may legally carry it.

```bash
ddev exec bin/console load:plan --exclude-owner=gmss   # the invocation this collective actually uses
ddev exec bin/console load:plan --owner=sepp           # one owner's gear only
ddev exec bin/console load:plan --vehicle=opel-movano-l4h3   # Sepp is not coming
```

**`--exclude-owner=gmss` is the real one.** GMSS's gear does not travel in these two vans, which is an arrangement
between people rather than a property of the cabinets, so it is stated on the command line and not baked into the
code. Without it the planner is asked to carry 3493.7 kg, which is not a question anybody has.

## The answer, today

```
sepp-transporter-l3h2 (sepp) — 23 units
  weight    1197.9 kg of 1200.0 kg payload  (2.1 kg spare)
  space      14.973 m³ of 13.386 m³ bay  (112 % by bounding box, WHICH ALREADY EXCEEDS IT)

opel-movano-l4h3 (sdwa5) — 12 units
  weight    1020.0 kg of 1024.0 kg payload  (4.0 kg spare)
  space       5.216 m³ of 15.843 m³ bay  (33 % by bounding box)

NOT CARRIED — the fleet has no legal room for these:
     2 × truss-f33-2m                        20.6 kg
```

**The fleet cannot carry the load, and the arithmetic settles it before any assignment is made.** 2238.5 kg of gear
against 2224 kg of combined payload is infeasible, so no ordering and no heuristic gets it in — the planner leaves
the lightest 20.6 kg behind and says so. That is the useful output. A heuristic that hid the remainder to look
successful would be worse than useless, because the remainder *is* the answer.

**Sepp's van also comes out at 112 % of its bay while the Movano sits at 33 %, and that is structural rather than a
bad choice.** Twelve Flexys are 1020 kg of a 2224 kg fleet payload, so wherever they go, that vehicle is full by
weight and everything else has to fit in the other one. They are also the densest thing in the library at 195 kg per
cubic metre, where the rest of the load averages 80 — so the vehicle that takes them ends up light on volume and the
other one ends up buried.

## Weight and space are two answers, and only one of them is a verdict

**Weight is a legal limit** with a number on a registration document behind it. `F.2` minus `G` is the payload, the
driver is already inside `G` by EU definition, and "over by 40 kg" is a fact. An overrun is a fine, a liability
question after an accident and a refused insurance claim, so the command exits non-zero and prints the kilogrammes.

**Space is a lower bound and can never say a load fits.** What the report sums is bounding-box volumes: it counts the
air around every wedge and inside every horn flare, and counts no aisle, no strapping and no stacking rule. So a bay
already exceeded by bounding boxes is real evidence that the load will not go in, and a bay only 60 % accounted for
is not permission. Irregular cabinets in a fixed shape rarely beat about 70 % in practice, and this repository has no
measurement of its own packing efficiency to offer instead.

A bay nobody has measured gets no space answer at all, rather than a pass.

## What the verdict is made of

**Not one weight in this library has been on a scale.** They are datasheet figures, arithmetic and the builder's own
hedging. Half the fleet payload is Sepp's assumed 1200 kg, both masses guessed rather than read off his
Zulassungsbescheinigung. So the report prints what it summed and where those figures came from, and when a margin
lands inside that uncertainty it says `UNDECIDED` instead of pretending to decide:

```
Weights: 0 of 10 devices weighed on a scale. The rest are datasheet figures or estimates
Payload of sepp-transporter-l3h2: 1200 kg, from estimated masses
Payload of opel-movano-l4h3: 1024 kg, from datasheet masses
UNDECIDED for sepp-transporter-l3h2: 2.1 kg of margin is inside the error of the estimates it is made of
```

**Fields F.2 and G off Sepp's papers are the two numbers that would change the answer**, and a tape measure inside
both bays is the other half. See [LOAD-2 in TODO.md](../TODO.md) and [sources.md](sources.md).

## How the assignment is made

It is **not** a 3D packer. Placing irregular cabinets in a fixed bay is NP-hard and the inputs do not exist for it:
every cabinet here is a bounding box, several of them badly — a Tecnare top is a trapezoid, a Flexy is mostly folded
horn, and the truss towers report the mast's footprint rather than their unfolded outriggers. What it does is the
*assignment*: which unit rides in which vehicle, so that no vehicle exceeds its legal payload.

The ordering, written down so it can be argued with:

1. **Vehicles are offered largest payload first.** The two are not the same size, so a left-to-right fill would put
   the heavy half in whichever happened to be listed first.
2. **Cargo is placed heaviest unit first**, not heaviest device. Three 20 kg tops are not a heavier thing than one
   90 kg SKRAM.
3. **A device goes to the bin least strained by taking it**, scored as the worse of its two fills — weight against
   payload, volume against bay. Scoring weight alone produces plans that are legal and unloadable, because 30 % of
   headroom *across a fleet* says nothing about either vehicle. On our own fleet it changes nothing, for the
   structural reason above; it earns its place where a better split exists.
4. **A device is split across vehicles only when no single vehicle can take all of it**, because a matched pair of
   tops in two different vans is a valid plan and an annoying one.
5. **Weight refuses and space only ranks.** A unit that would put a vehicle over its payload is never placed there;
   a unit that would overfill a bay still is, and the bay is reported as exceeded.

## What it does not know yet

The constraints a bounding-box assignment cannot see, and which decide whether a plan is usable:

* **Heavy low.** A 220 kg wall bass or a 90 kg SKRAM goes on the floor, nothing stacks on a cabinet it would crush,
  and nothing is stacked higher than two people can lift.
* **Wheel arches narrow the floor.** The Movano's bay is 1.765 m wide and 1.380 m between the arches, so the width a
  cabinet gets depends on how high it sits.
* **Racks roll and cabinets do not.**
* **The door aperture.** A bay big enough for a cabinet that will not go through the doors is no use.
