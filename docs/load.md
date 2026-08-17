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

**The trailer is specced and it is not the answer.** 750 kg permitted gross against ca. 200 kg unladen is 550 kg of
payload, of which the generator is 465 — **85 kg** spare. It is a generator trailer rather than spare space.

The whole load in one journey comes to 2703.5 kg against 2574 kg of capacity, which is **129.5 kg short**. The
trailer is a net gain of only 85 kg, because it brings 550 kg of capacity and 465 kg of new load with it. Closing the
rest would need a payload near 680 kg — roughly 900 to 1000 kg gross and braked, which both vans tow easily but which
takes the combination to 4500 kg and has licence implications worth checking first. **750 kg is exactly O2, the
unbraked limit on both sets of papers.** See LOAD-5.

Without either exclusion the planner is asked to carry 3958.7 kg, which is not a question anybody has.

## The answer, today

```
opel-movano-l4h3 (sdwa5) — 14 units
  weight    1018.0 kg of 1024.0 kg payload  (6.0 kg spare)
  space     14.153 m³ of 15.843 m³ bay  (89 % by bounding box)

fiat-ducato-250-l3h2 (sepp) — 14 units
  weight     997.3 kg of 1000.0 kg payload  (2.7 kg spare)
  space       4.823 m³ of 13.386 m³ bay  (36 % by bounding box)

trailer-750kg (sepp) — 3 units
  weight     540.0 kg of 550.0 kg payload  (10.0 kg spare)
  space        bay not measured, so no space answer can be given
     1 × sepp-generator-25kva               465.0 kg

NOT CARRIED — short by 148.2 kg
```

**Three bins, all three within ten kilogrammes of their limit, and 148.2 kg still at home.** Every one of them
reports `UNDECIDED` for that reason: a margin that small, off masses good to tens of kilogrammes, is not a decision.

## An open bed has no height, so the trailer gets no space answer

The trailer states **no `load_bay_m` at all**, and that is deliberate rather than missing. A flatbed has a length and
a width and no ceiling: what limits a load on it is the mass and the straps. `load_bay_m` requires all three axes
when present, so stating a side height as though it were a roof would make the planner refuse anything taller than
the sides. **A vehicle with no bay gets no space answer**, which is the honest outcome — the constraint on a trailer
is weight.

## Some devices can only ride on one bin

**`carried_on` is a field on the device, and without it the plan was unloadable.** The planner scores bins by how
strained they are, and the trailer holding a 465 kg generator against a 550 kg payload is by far the most strained of
the three. So left to the score it sent the generator to a **van** and filled the trailer with speaker cabinets — a
plan that passes every weight check and that nobody can load, since two people cannot lift it and no van has a ramp.

```yaml
carried_on: trailer-750kg
```

Pinned devices are placed first, before anything else can take the room, and **a pin that cannot be honoured leaves
the device behind rather than quietly unpinning it**. Sending it to a van instead would report success on a plan that
cannot be executed, which is worse than a remainder. `specs:validate` refuses a pin naming something that is not a
transporter, because a typo would otherwise turn "this rides on the trailer" into "this does not travel" while the
plan looked complete.

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

0. **A pinned device goes to the bin it names**, before anything else can take the room. See above.
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

## Seeing a pack

```bash
ddev exec bin/console scene:pack --exclude-owner=gmss --write
ddev exec bin/console scene:build packed-convoy
ddev exec bin/console scene:render packed-convoy --quick-preview
```

**`load:plan` says what goes where and `scene:pack` shows it.** They are separate commands because they answer
separate questions and one of them is legal: a payload verdict has to be readable without Blender anywhere near it,
and CI has none. The pack adds geometry on top and nothing else — the assignment, the verdicts and the remainder are
all the planner's, unchanged.

**The positions come from one stated rule rather than an optimal pack.** Two passes over each bay: **heaviest first
onto the floor** in rows across the width, then **columns** on top of whatever can hold them. Heaviest-first comes
from the planner and puts the mass low without a rule of its own. No z is written anywhere — a stacked unit says
`on:` and names the cabinet beneath it, so measuring a Flexy corrects every pack.

**The floor row stays between the wheel arches**, which is the one piece of real geometry the rule knows. On the
Movano that is 1.380 m of usable width against a 1.765 m bay.

**What the rule cannot do, and the scene's own notes list it**: nothing is rotated, nothing is interleaved, and a
trapezoid is packed as its bounding box. A real pack is tighter. Anything the rule cannot place is reported as
overflow rather than squeezed in — **7 of 32 assigned units on the current pack**, mostly truss segments and
scaffold towers, all of which would lie down without difficulty. That is LOAD-6.

**Render it with `--labels` or it is a picture of anonymous boxes.** Three cages and twenty-five cabinets say nowhere
which is which without them:

```bash
ddev exec bin/console scene:render packed-convoy --labels
```

One label per device **per vehicle**, with the count in the text — seven Flexys labelled seven times is noise, and the
same cabinet in two vans is two facts. Plus a legend standing beside the convoy naming what each cage colour means,
which is knowledge that otherwise lives only in `blender/lib/materials.py`. Labels are drawn at *render* time like
the aim lines, so the same assembled `.blend` draws with them or without and neither is the canonical picture.

Several labels still sit on top of each other at 960 × 540, since nothing lays them out to avoid it. That is CVR-9.

It is written as an ordinary scene into `scenes/packs/`, so `ShippedScenesTest` sweeps the result for cabinets inside
each other exactly as it does a rig. If the layout rule produces an overlap, the repository's own checks say so
rather than the picture merely looking odd.

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
* **Wheel arches narrow the floor**, and they are now **drawn** as solid boxes so a clash is visible rather than
  arithmetic. The Movano's bay is 1.765 m wide and 1.380 m between the arches, so each one reaches in 192.5 mm —
  derived from those two figures — and the width a cabinet gets depends on how high it sits. Their length, height and
  position along the bay are estimates; the axle position is in no document we hold.
* **Nothing turns.** A 2 m truss segment across a 1.38 m floor does not fit and would lie along the bay without
  difficulty. LOAD-6.
* **A folding device is modelled erected.** The truss lift transports at 1.75 m and is modelled at its working 4 m,
  because `dimensions_m` is one field answering two questions. SPEC-15.
* **Labels overlap.** `--labels` names everything and a legend explains the colours, but eighteen labels at
  960 × 540 have several sitting on each other. CVR-9.
* **Racks roll and cabinets do not.**
* **The door aperture.** A bay big enough for a cabinet that will not go through the doors is no use.
