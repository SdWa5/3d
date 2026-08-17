# Loading the vans

Which gear rides in which transporter, and whether the fleet may legally carry it.

```bash
# the invocation this collective actually uses
ddev exec bin/console load:plan --exclude-owner=gmss --exclude=sepp-generator-25kva

ddev exec bin/console load:plan --owner=sepp                 # one owner's gear only
ddev exec bin/console load:plan --vehicle=opel-movano-l4h3   # Sepp is not coming
```

**`--exclude-owner=gmss --exclude=sepp-generator-25kva` is the real one**, and the second half of it is newer than
the first. GMSS's gear does not travel in these two vans, and Sepp's 465 kg generator travels on a **trailer** — both
stated by the owner, both facts about arrangements between people rather than properties of the cabinets, so both are
stated on the command line and not baked into the code.

**Without the exclusion the generator eats 465 kg of van payload** and the plan reports a 679.5 kg shortfall nobody
actually has. Until the trailer is a spec of its own and becomes a third bin — LOAD-5 — naming the device is the only
way to tell the truth about what the vans carry.

**And the trailer turns out not to be the answer, which is worth knowing before it is bought.** Sepp is buying one
at **750 kg permitted gross, ca. 200 kg unladen** — so 550 kg of payload, of which the generator is 465. That leaves
**85 kg** spare: it is a generator trailer rather than spare space.

The whole load in one journey comes to 2703.5 kg against 2574 kg of capacity, which is **129.5 kg short**. The
trailer is a net gain of only 85 kg, because it brings 550 kg of capacity and 465 kg of new load with it. Closing the
rest would need a payload near 680 kg — roughly 900 to 1000 kg gross and braked, which both vans tow easily but which
takes the combination to 4500 kg and has licence implications worth checking first. **750 kg is exactly O2, the
unbraked limit on both sets of papers.** See LOAD-5.

Without either exclusion the planner is asked to carry 3958.7 kg, which is not a question anybody has.

## The answer, today

```
opel-movano-l4h3 (sdwa5) — 23 units
  weight    1023.0 kg of 1024.0 kg payload  (1.0 kg spare)

fiat-ducato-250-l3h2 (sepp) — 14 units
  weight     997.3 kg of 1000.0 kg payload  (2.7 kg spare)
  space       4.823 m³ of 13.386 m³ bay  (36 % by bounding box)

NOT CARRIED — the fleet has no legal room for these:
     1 × rack-power-12u, 2 × eighteensound-2way-15, 2 × truss-tower-4m, 4 × truss-f33-2m
  short by 218.2 kg
```

**The fleet is 214.5 kg short of carrying the library in one trip**, and both vehicles finish within three
kilogrammes of their legal limit — which is why both come back `UNDECIDED` rather than as a pass.

## The number moved twice in a day, and that is the lesson

| source for Sepp's payload | figure | the fleet against a 2238.5 kg load |
| --- | --- | --- |
| estimated, deliberately cautious | 1200 kg | 14.5 kg short |
| his Zulassungsschein, field A10 | 1365 kg | 150.5 kg **spare** |
| **a weighbridge — full tank, driver aboard** | **1000 kg** | **214.5 kg short** |

**The estimate was pessimistic and the document was optimistic**, which is not the order anybody expects. The
estimate guessed heavy on purpose, reasoning that an ex-fleet van carries shelving a catalogue kerb weight knows
nothing about. That reasoning was right and the guess was still 200 kg out — in the other direction from the paper.

**A registration document is authoritative about what a vehicle may weigh and merely historical about what it
does.** Field F2 — 3500 kg — is the law and no scale can tell you it. Field G, or the Austrian Eigengewicht, is a
number from the day of type approval, and this van has had shelving, a bulkhead and a ply floor added since. Add
75 kg of diesel for a full 90 litre tank and the 365 kg gap is accounted for. **For a payload you need both
sources: the ceiling from the paper, the mass from the scale.**

**The same question now hangs over the Movano.** Its 1024 kg comes off field G of its Zulassungsbescheinigung — the
same class of figure that has just been shown 365 kg light on the van that got weighed. Until it goes on a scale
the fleet total is one measurement plus one assumption, and the assumption is the optimistic kind.

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

**One payload is measured, one is read off a document, and no cabinet weight has been on a scale at all.** The
report prints which is which, because after today the difference between those two is not academic:

```
Weights: 0 of 10 devices weighed on a scale. The rest are datasheet figures or estimates
Payload of opel-movano-l4h3: 1024 kg, from datasheet masses
Payload of fiat-ducato-250-l3h2: 1000 kg, from measured masses
UNDECIDED for opel-movano-l4h3: 1 kg of margin is inside the error of the estimates it is made of
UNDECIDED for fiat-ducato-250-l3h2: 2.7 kg of margin is inside the error of the estimates it is made of
```

**A margin inside the error of its own inputs is reported `UNDECIDED` rather than passed**, and both vehicles are
there: 1.0 kg and 2.7 kg of headroom, off masses good to tens of kilogrammes. The plan is a way to see roughly what
goes where, not a clearance to drive.

**A tape measure inside both bays is what is left.** Both load bays are still manufacturer figures rather than
measurements, and an Austrian Zulassungsschein carries no dimensions at all — so Sepp's outer box is estimated as
well, and whether it is an L3H2 or an L3H3 is still one look at the roof. See [LOAD-2 in TODO.md](../TODO.md) and
[sources.md](sources.md).

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
   headroom *across a fleet* says nothing about either vehicle. **On our own fleet it decides nothing, because
   weight decides everything**: both vans finish within three kilogrammes of their limit, so there is no slack for
   a second dimension to spend. It earns its place on a fleet with room in it.
4. **A device is split across vehicles only when no single vehicle can take all of it**, because a matched pair of
   tops in two different vans is a valid plan and an annoying one.
5. **Weight refuses and space only ranks.** A unit that would put a vehicle over its payload is never placed there;
   a unit that would overfill a bay still is, and the bay is reported as exceeded.

## Seeing it

**Both vans are models now, drawn as cages.** Stated by the owner: the vans need at least wire-type models so a pack
can be planned. `shape: load-bay` draws three things, each answering a different question —

* the **vehicle outline**, faint, for scale;
* the **load bay** inside it, in blue, which is the volume cabinets go in;
* the **floor between the wheel arches**, in yellow, when the spec states it. On the Movano the bay is 1.765 m wide
  and 1.380 m between the arches, so 385 mm of that width exists only above arch height.

```bash
ddev exec bin/console models:build --id=opel-movano-l4h3
```

**Where the bay sits inside the outline is a diagram, not a claim.** It is drawn flush to one end, centred across,
and resting on the vehicle's own floor line. The real load floor is roughly half a metre up and no registration
document states it, so drawing it there would be inventing a number — and it changes nothing about a pack, which
turns on the bay's internal dimensions and on cabinets standing on its floor.

A van whose inside nobody has measured draws its outline alone. That is deliberate: the bay stays optional, because
a van can be specified from its papers before anybody has been in the back of it.

## What it does not know yet

The constraints a bounding-box assignment cannot see, and which decide whether a plan is usable:

* **Heavy low.** A 220 kg wall bass or a 90 kg SKRAM goes on the floor, nothing stacks on a cabinet it would crush,
  and nothing is stacked higher than two people can lift.
* **Wheel arches narrow the floor.** The Movano's bay is 1.765 m wide and 1.380 m between the arches, so the width a
  cabinet gets depends on how high it sits.
* **Racks roll and cabinets do not.**
* **The door aperture.** A bay big enough for a cabinet that will not go through the doors is no use.
