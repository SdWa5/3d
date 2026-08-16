# Sources

Where each device's numbers come from. Every spec's `provenance` and `clone_of.reference` point at a
row here, so a number can always be traced back to something — or visibly to nothing.

Keep this in step with the specs. It is the difference between "0.573 m wide" and "0.573 m wide
*because the design's CAD mesh says so*".

Drive paths are relative to the **SdWa5** Shared Drive, folder
`Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/`.

## Devices

| Device | Clones | Reference | Source | What came from it |
|--------|--------|-----------|--------|-------------------|
| `flexy-folded-horn-hybrid` | MrFlexySMP Folded Horn Hybrid | `cad` | `MrFlexySMPs Folded Horn Hybrid/3d models/subwoofer v28.obj` | Height and depth off the mesh bounding box: 0.763 × 0.964 m. Its width reads 0.573 m and is **wrong** — see `deviations` |
| | | | `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx`, confirmed by the owner | Width 0.59 m; weight 85 kg (70 empty + 15 driver) |
| | | | `Hardware/Hardware Overview.xlsx` | Quantity 14, 38–200 Hz, 1800 W, driver models |
| `skram` | Josh Ricci SKRAM | `plans` | `Josh Ricci (SKRAM, OTHORN, GHORN, SKHORN)/Skram Panel List.csv` | Cut list: largest panel 914 × 813 mm at 18 mm, and 813 × 574 giving 574 + 2×18 = 610 mm width |
| | | | Josh Ricci's published SKRAM design (24″ × 32″ × 36″) | Independent confirmation of the same 0.610 × 0.813 × 0.914 m |
| | | | `PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` | Weight 90 kg (56 empty + 34 driver), 1800 W |
| | | | `Josh Ricci (…)/SKRAM Driver.xlsx` | 21″ driver shortlist ranked by efficiency per euro |
| `tecnare-m2122` | — (factory cabinet) | `datasheet` | [Tecnare L2122LT datasheet](https://www.tecnare.co/wp-content/uploads/2020/01/l2122lt.pdf) | 96 × 50 front / 34.5 rear × 52 cm, 68 kg, 60° × 40°, 150 Hz–20 kHz, crossover, impedance. Same cabinet geometry as the M2122 |
| | | | `Hardware/Hardware Overview.xlsx` | The re-fitted driver complement (Celestion 12″, RCF ND650, B&C DE25). Its quantity of 2 counts only the factory pair |
| `tecnare-m2122-clone` | Tecnare M2122 | `datasheet` | Same L2122LT datasheet, via its own originals | Geometry, assumed copied exactly. Weight is the factory figure used as a placeholder — a self-built cabinet rarely matches it |
| | | | The owner | That three M2122-shaped tops exist: two factory, one self-built |
| `eighteensound-2way-15` | Eighteen Sound 15″ 2 Ways Kit | `plans` | `18sound/18sound_15 2ways.pdf` — FRONT VIEW (p10) and SIDE VIEW (p12) | Dimensions read off the drawings: 420 × 800 × 335 mm; 15 mm Baltic birch; 96 mm vents |
| | | | Same PDF, driver pages | 15W700 (8.6 kg) + ND1460 (1 kg) + XT1464 horn, crossover ≈ 1.5 kHz |
| | | | `Hardware/Hardware Overview.xlsx` | Quantity 2, owner, 60–20000 Hz, 550 W |
| `achenbach-18` | Achenbach 18 | `cad` | `Achenbach 18/Achenbach 18.FCStd` | Panel geometry: 600 × 700 × 18 top/bottom, 18 × 700 × 564 sides → 0.600 × 0.600 × 0.700 m |
| | | | `Hardware/Hardware Overview.xlsx` | Quantity 4, owner, driver B&C 18TBW100, 35–1000 Hz, 1000 W |
| `gmss-iq-sub` | — (GMSS self-build) | — | **The owner of GMSS**, answering questions about `GMSS.jpeg` | "6 IQ subs: 53w, 56d, 67h, 40kg each" — count, all three dimensions and the weight |
| | | | An earlier message from GMSS | "8 turbo subs 3000rms" — now known to cover **two** cabinets, 6 IQ subs + 2 nukes, so the power rating cannot be attributed to either |
| | | | `GMSS.jpeg`, one site photo, no scale reference | Which cabinets these are: the six grille-fronted boxes with a blue illuminated logo, **three to an outer column** |
| `gmss-nuke` | — (GMSS self-build) | — | **The owner of GMSS** | "2 nukes: 59w, 70d, 77h, 58kg each", and that the outer bottom-row boxes are turbo subs — "the ones on the outside of the bottom row are also turbo subs" |
| | | | `GMSS.jpeg` | Which cabinets these are: the two on the ground at the foot of each outer column, **no grille**, plain face with a recessed oval. Previously mis-read as the mid bass |
| `gmss-wall-bass` | — (GMSS self-build) | — | **The owner of GMSS** | "2 wall basses: 66w, 100d, 140h, maybe 220kg each" — count and dimensions stated, the **weight hedged by him** |
| | | | An earlier message from GMSS | "Middle subs are 18/1600rms" — an 18″ driver and 1600 W RMS, tied to this cabinet by elimination across the two messages |
| | | | `GMSS.jpeg` | Which cabinets these are: the pair of fin-mouthed folded horns standing in the centre of the stack |
| `gmss-mid-bass` | — (GMSS self-build) | — | **The owner of GMSS** | "USB: 120w, 60d, 50h, ~120kg" (weight **hedged**), and the identity: asked which box the mid bass is, "That's the mid bass (usb)" of the horn in the middle. So **one** cabinet, not two |
| | | | An earlier message from GMSS | "USB 2x 700rms mid bass" — two drivers at 700 W RMS. Their size is not stated, nor is what "USB" refers to |
| | | | `GMSS.jpeg` | Which cabinet this is: the single wide cross-braced horn mouth lying across the two wall basses. Its stated 1.20 m spans the 1.32 m pair, which is what makes one cabinet certain rather than merely allowed |
| `gmss-turbo-top` | — (GMSS self-build) | — | **The owner of GMSS** | "Tops: 45w, 38d, 71h, 26kg each" — dimensions and weight, **neither hedged**. No count |
| | | | An earlier message from GMSS | "3 turbo top 2500rms" — the count and the power rating. Still the only source for `quantity: 3`, though the photo appears to show **four** |
| | | | [Turbosound TMS-4](https://www.warehousesound.com/turtms4.php), [manual](https://archive.org/stream/Turbosound/Turbosound%20TMS-4_djvu.txt) | 1143 × 502 × 730 mm and 74.8 kg — used as a size anchor before the owner's figures arrived and **contradicted by them**: 61 % too tall, nearly 3× too heavy. Kept as a rejected line of reasoning |
| `truss-f33-2m` | — (factory truss) | `datasheet` | [Global Truss F33 300](https://globaltruss.de/en/F33-300cm/F33300), [StageSpot](https://www.stagespot.com/global-truss-f33-triangular-truss-straight-segments.html) | Chord Ø 50 × 2 mm, diagonal Ø 20 × 2 mm, overall width 290 mm; weights at 1.0 m / 1.5 m / 3.0 m |
| | | | The owner | That we have **5 segments at 2 m, three-point**. The class is an inference from that |
| `truss-tower-4m` | — (factory stand) | `datasheet` | [Global Truss ST-132](https://www.globaltruss.com/st-132), [manual](https://www.globaltruss.com/pub/media/globaltrdownloads/downloads/s/t/st132_manual.pdf) | 25 kg, max height 4.0 m, min 1.8 m, max load 100 kg, folded base 8″, unfolded base 59″ |
| | | | The owner | That we have **2 telescopic stands at 4 m** |
| `gmss-truss-9m` | — (GMSS) | — | A message from GMSS | A **9 m span**, and nothing else. Cross-section, brand, chord count and segmentation all unstated |
| `gmss-tower-5m` | — (GMSS) | — | A message from GMSS | **2 towers, max 5.2 m**. Nothing else — the weight is inferred from our ST-132 |
| `gmss-mac-2000-performance-ii` | — (factory fixture) | `datasheet` | [Martin MAC 2000 Performance II](https://www.martin.com/en-US/products/mac-2000-performance-ii) | 408 × 490 × 743 mm head straight up, 39.5 kg, 1200 W lamp, 540°/267° pan and tilt |
| | | | A message from GMSS | That they have **4 of them** — "4pcs Martin mac performance 2", read as the Performance II |
| `geruest-krause-ah7` | — (factory scaffold) | `datasheet` | [Krause Plattformgerüst AH7](https://www.bauhaus.at/kleingerueste/krause-plattformgeruest-ah7/p/29059229) | Arbeitshöhe 7 m, platform 1.50 × 0.60 m rated 200 kg, frame field 1.50 × 0.65 m, ~84 kg |
| | | | The owner | That we have **2 of them**, and the AH7 convention: 7 m working height means a **5 m platform** |
| `rack-amp-12u` | — (flightcase) | — | The owner | That there are **2 amp racks and 1 distro rack**, and what is in them |
| | | | 19″ standard | 12U of rails is exactly 12 × 44.45 = 533.4 mm. The case around them is estimated |
| | | | `Hardware/Amps _ Verstärker _ DSP/behringer europower 4000.pdf` (Drive) | EP4000: ca. 88 × 482.6 × 402 mm, ca. **16.6 kg** |
| | | | `Hardware/Amps _ Verstärker _ DSP/t.amp proline 3000.pdf` (Drive) | Proline 3000: 482 × 460.5 × 132 mm, **37 kg** |
| | | | [Gisen MM14K](https://www.gisenaudio-europe.com/en/m-serie) via dealer listings | 19″, 2HE, 396 mm deep, **12 kg**, 2-ch Class-TD, 14 000 W bridged at 4 Ω |
| | | | [FP10000Q datasheet](https://www.fullcompass.com/common/files/4660-FP10000QDatasheet.pdf) | 88 × 483 × 396 mm, **12 kg**, 4 channels |
| | | | Gisen M-series DSP listings | 1HE, under 13 kg — the "md60", whose exact model is **not pinned down** |
| `rack-power-12u` | — (flightcase) | — | The owner | That one rack is for power distribution. **Nothing about its contents** |

### The 18sound drawings are partial dimensions

`18sound_15 2ways.pdf` prints 420 mm, 800 mm and 335 mm, and those were used as the outer box until the
CAD contradicted them. Reading the drawings again: the dimension lines sit **inside** the overhanging
top and bottom panels (parts F and E). 420 is the baffle width, 800 the side-panel height, and 335 a
partial depth that stops short of the back. The CAD's 465.6 × 836 × 426.8 mm is the true external size,
and it agrees with the drawings where they do measure the same thing — its baffle is 418.3 mm wide and
its side panels are exactly 800 mm.

The overhanging front lip is confirmed by the owner at about 56 mm, which is independent support for the
CAD over the drawings on the depth axis specifically — see above.

The CAD also uses 18 mm panels where the note specifies 15 mm Baltic birch, so this build is both
larger and heavier than the published kit. The weight estimate was recomputed accordingly: 41 kg.

### Still unsourced

Three weights are **estimates**, not measurements or published figures:

| Device | kg | Basis |
|--------|----|-------|
| `eighteensound-2way-15` | 30 | Calculated: 1.42 m² of 15 mm birch ply at ~680 kg/m³ + braces + drivers + hardware |
| `achenbach-18` | 50 | Calculated: 2.4 m² of 18 mm ply + internal horn panels + B&C 18TBW100 + hardware |
| `tecnare-m2122-clone` | 68 | Placeholder: the factory figure, which a self-built cabinet rarely matches |

The whole Shared Drive was searched for weight data — 1166 files, no hits in any spreadsheet,
document or filename. Eighteen Sound publishes no finished-cabinet weight either, since the 15″
2 Ways is a DIY kit whose weight depends on the builder. These three need the hanging scale; see
[measuring.md](measuring.md).

### Truss: the length is ours, the cross-section is a class standard

The truss specs invert the usual problem. For a cabinet the dimensions are the hard part and the power rating is
the throwaway; for a truss the **length** is what matters structurally and it is the one thing that was stated —
"5x 2m three point truss segments", and GMSS's "9m truss". What is missing is the cross-section and the brand.

So the lengths are ours and everything else is the 300 mm three-point class standard, for which
[Global Truss F33](https://globaltruss.de/en/F33-300cm/F33300) publishes real figures. Prolyte X30 and Eurotruss
FD32 are within a few millimetres, which is what makes it a class rather than a guess at a brand.

**The weight is derived from three published points, not interpolated by eye.** F33 is quoted at 6.4 kg for 1.0 m,
8.2 kg for 1.5 m and 14.1 kg for 3.0 m. Those fit a straight line:

| | |
|---|---|
| model | `2.55 kg + 3.85 kg/m` |
| 1.0 m | 6.40 (published 6.4) |
| 1.5 m | 8.33 (published 8.2) |
| 3.0 m | 14.10 (published 14.1) |

The 2.55 kg intercept is the end connectors, which is why a short segment is so heavy per metre. Our 2 m segment
is therefore **10.3 kg** and GMSS's 9 m run **37.2 kg** — though bolted up from three 3 m pieces it would be
42.3 kg, since each segment brings its own pair of connectors. The 37.2 is what is modelled; the 42.3 is what
would be on the truck.

**The cross-section is derived from the same figures.** Chords are Ø 50 inside a 290 mm overall width, so the chord
centres form an equilateral triangle of side `290 − 50 = 240 mm`. Its height is `240 × √3/2 = 207.8 mm`, and the
bounding box is that plus one chord diameter: **257.8 mm tall, 290 mm across**, rounded to 0.258 × 0.290.

`bay_length_m` — the pitch of the zigzag — has **no source at all**. Manufacturers publish tube sizes and weights
but rarely the brace pitch. It changes how many diagonals are drawn and nothing about the box or the weight.

### The towers are placeholders, and look it

`truss-tower-4m` matches the Global Truss ST-132 exactly on the stated description, so its **weight and heights are
published**: 25 kg, 4.0 m max, 1.8 m min, 100 kg load. What is estimated is its *shape* — a telescopic mast on
folding outriggers is neither a hexahedron nor a truss, so it is drawn as a 0.203 m column, which is the folded
base size. **The outriggers are not modelled**: unfolded they spread to 1.499 × 1.499 m, which is the footprint
that actually has to be kept clear on a stage. So the footprint these specs report is the mast's — not the working
footprint, and not the folded transport size either. It is the one number in them to be careful with.

`gmss-tower-5m` has no datasheet behind it at all. Its 33 kg is our ST-132's published 25 kg at 4 m scaled by
height into a taller class — an inference from one datapoint in a neighbouring class, and the first number to
replace if GMSS ever names the brand.

### The MAC is the one GMSS device with a datasheet

Every GMSS cabinet carries figures the builder stated rather than published — no datasheet, no plans, nothing to
check them against. The moving heads are the exception: "4pcs Martin mac performance 2"
identifies a catalogue product, the **MAC 2000 Performance II**, and Martin publishes its dimensions and weight. So
in `scenes/gmss-full-stack-truss.yaml` the *lights are better sourced than the speakers under them*.

**One thing the schema cannot express, and it applies to every moving head.** The outer box and the weight are
datasheet; how the height divides between base, yoke and head is not published anywhere and comes off product
photographs. `provenance.dimensions` is a single field, so the spec says `datasheet` — which is what the bounding box
everything downstream uses actually is — and the qualification lives in prose. Recorded as a TODO.

Martin quotes "408 length × 490 width". This repository's `width` is across the device and `depth` front to back, so
490 is the width and 408 the depth. Reversing them would put the yoke arms on the wrong faces.

### The Gerüst, and why it is not 7 m tall

`geruest-krause-ah7` matches the Krause Plattformgerüst AH7 on every stated figure, so its footprint, platform height,
capacity and weight are published. **What is estimated is only the tube sections** — Krause gives the tower's
dimensions and weight but not its tube diameters, so 50 mm posts and 25 mm braces are the ordinary aluminium sizes,
and like a truss's bay pitch they change the picture and nothing else.

**AH7 means *Arbeitshöhe* 7 m, and a working height is the platform plus about two metres of a person's reach.** So
the platform stands at **5 m** and that is what `dimensions_m.height` carries. A spec that put 7 in the box would
clear a truss it does not clear, which is why `SpecValidator` refuses a platform above the frame. Guardrails and rungs
are not modelled, so the box stops at the deck and understates the standing structure by roughly a guardrail.

### The amp racks: derived weights, estimated cases

The rack specs invert the usual balance one more time. A rack case IS a box, so there is no geometry to argue about —
what matters is the **weight**, and that comes from published amplifier figures rather than a guess.

The stated complement is "3× gisen mm14k, 1× ep4000 or proline (not sure), 1× fp1000q, 1× gisen md60". Two of those
datasheets are in our own Drive under `Hardware/Amps _ Verstärker _ DSP/` — the Behringer and t.amp manuals, both
with a *Technische Daten* table — and the other three are published by their makers:

| amp | rack units | weight |
|---|---|---|
| Gisen MM14K × 3 | 2U each | 12.0 kg each |
| Behringer EP4000 | 2U | 16.6 kg |
| FP10000Q | 2U | 12.0 kg |
| Gisen M60-series DSP | 1U | ~13 kg |
| **total** | **11U** | **77.6 kg** |

**Two independent numbers explain why there are two amp racks, and it is not space.** Eleven rack units fits a
single 12U rack by height. But 77.6 kg of amplifier plus about 30 kg of case is a **108 kg rack**, which two people
cannot lift; split across two it is 69 kg each. The second rack exists for the weight.

**Two things are unresolved and both are in the specs.** The fourth amp is either the EP4000 or the Proline 3000 —
the owner is not sure — and the Proline is 3U and 37 kg where the EP4000 is 2U and 16.6, which takes the complement
to 12U and 98 kg and each rack to 79 kg. And "md60" matches no Gisen product: their **M60-series DSP** amplifiers fit
the description at 1HE and under 13 kg, but M60Q-DSP and M60.12 are both plausible and the name on the front panel
would settle it.

**The amplifiers have no specs of their own**, deliberately: they are invisible inside a closed rack and would put
five boxes nobody can see into `detail-check`. Their figures are recorded here and in the rack's own header, which is
where a weight is supposed to be traceable to.

`rack-power-12u` is the weakest spec in the repository. Its case follows the amp racks, and its 15 kg of contents is
a guess at breakers, socket panels and cable — there is no component list to add up.

### GMSS: the owner's figures replaced the photo reconstruction

GMSS (Gena Made Sound System) is a self-built system and searching for it online returns nothing, which is what you
would expect for a locally built rig rather than a product. So there is no datasheet and there are no plans, and the
GMSS specs were for a long time the one place in this repository where **not a single dimension or weight was
sourced**: they were reconstructions from counts, power ratings and one site photograph with no scale reference in
it.

That changed. GMSS's figures arrived in two rounds, and the second one supersedes the first on every dimension and
every weight:

1. **Counts and power ratings**, plus `GMSS.jpeg`: "8 turbo subs 3000rms", "Middle subs are 18/1600rms", "USB 2x
   700rms mid bass", "3 turbo top 2500rms".
2. **The owner's own figures**, itemised in centimetres as `w, d, h` and answering direct questions about that
   photograph — including the two questions the reconstruction had got wrong: whether the horn in the middle of the
   stack was missing from his list, and whether the outer bottom-row boxes were the mid bass.

| Device | Stated by the owner | m (w × h × d) | kg |
|--------|--------------------|---------------|----|
| `gmss-nuke` | "2 nukes: 59w, 70d, 77h, 58kg each" | 0.590 × 0.770 × 0.700 | 58 |
| `gmss-iq-sub` | "6 IQ subs: 53w, 56d, 67h, 40kg each" | 0.530 × 0.670 × 0.560 | 40 |
| `gmss-wall-bass` | "2 wall basses: 66w, 100d, 140h, maybe 220kg each" | 0.660 × 1.400 × 1.000 | 220 *(hedged)* |
| `gmss-mid-bass` | "USB: 120w, 60d, 50h, ~120kg" | 1.200 × 0.500 × 0.600 | 120 *(hedged)* |
| `gmss-turbo-top` | "Tops: 45w, 38d, 71h, 26kg each" | 0.450 × 0.710 × 0.380 | 26 |

That is 994 kg of GMSS speaker across 14 cabinets, of which the two wall basses are 440.

**They are still `provenance: estimated`, and that is a limitation of the enum rather than a judgement about the
figures.** `Provenance` has `datasheet` / `plans` / `measured` / `estimated`. There is no datasheet and no plans for
a self-built rig; `measured` means somebody here put a tape on the cabinet, which nobody has. "The builder stated it
for his own box" is a fourth kind of source the enum cannot name, so it lands on `estimated` — much better than the
photo reconstruction it replaced, still second-hand, and still flagged in the catalog. **Two of the weights are
estimates by the owner's own wording as well** ("maybe 220kg", "~120kg") and are marked as such in their specs;
`physical.weight_kg` has no way to carry that, so it lives in prose. A `reported` case on the enum would be the fix.

### The names changed, so the mapping was the work

The owner's five entries do not line up one-to-one with the four specs that existed, and sorting that out mattered
more than the numbers:

| Owner's entry | Spec now | Was | Why |
|---------------|----------|-----|-----|
| 2 nukes | `gmss-nuke` | *split out* | "8 turbo subs" is **two** cabinets: 6 IQ subs + 2 nukes. The nukes are the plain-faced boxes on the ground at the foot of each outer column — no grille, recessed oval logo — which `gmss-mid-bass` had claimed as its own pair |
| 6 IQ subs | `gmss-iq-sub` | `gmss-turbo-sub` (qty 8) | The six grille-fronted boxes, three to an outer column. "Turbo sub" is GMSS's umbrella word for the outer columns' low end, not the name of a box, so no spec keeps that id |
| 2 wall basses | `gmss-wall-bass` | `gmss-middle-sub` (qty 3) | Same cabinet, and now with a name from the builder instead of a position. Its "third" cabinet was never a wall bass |
| USB | `gmss-mid-bass` (qty **1**) | `gmss-mid-bass` (qty 2) | The owner confirmed the horn in the middle *is* the mid bass. It is one cabinet 1.20 m wide, which is what the old set had modelled twice at half the width and once more as a middle sub laid on its side |
| Tops | `gmss-turbo-top` | `gmss-turbo-top` (unchanged id) | "3 turbo top" was GMSS's own wording, so the id stands. Count still from the first message |

`quantity: 3` on the tops is the one count the owner did **not** restate, and the photo appears to show four — three
on the mid bass plus one on the right-hand outer column. Left at 3, flagged in the spec: it is the next thing to ask.

### What the photograph got right, and what it could never give

Worth keeping, because the reconstruction is exactly the kind of work this repository will do again:

| Device | Photo reconstruction | Stated | Where it went |
|--------|---------------------|--------|---------------|
| `gmss-iq-sub` | 0.510 × 0.637 × 0.765, 54 kg | 0.530 × 0.670 × 0.560, 40 kg | Width and height within 35 mm. **Depth wrong by 205 mm** |
| `gmss-wall-bass` | 0.595 × 1.020 × 0.850, 81 kg | 0.660 × 1.400 × 1.000, 220 kg | Height short by 380 mm, weight by 139 kg |
| `gmss-mid-bass` | 0.595 × 0.425 × 0.595, 45 kg | 1.200 × 0.500 × 0.600, 120 kg | Width short by 605 mm — the old box was **half the cabinet**, because the count was wrong |
| `gmss-turbo-top` | 0.391 × 0.935 × 0.552, 56 kg | 0.450 × 0.710 × 0.380, 26 kg | 225 mm too tall, more than twice the weight |
| `gmss-nuke` | modelled as part of two other specs | 0.590 × 0.770 × 0.700, 58 kg | A cabinet that was never a cabinet |

Three lessons, and the first two are the ones the old files predicted about themselves:

* **Front-on ratios survive a photograph; depth does not.** The side faces are foreshortened past reading, the old
  files called depth "the least certain of the three axes", and depth is where the IQ sub missed by a third.
* **Even ratios only hold between things the same distance from the camera, and only front-on.** The old reasoning
  trusted the photo for proportions while distrusting it for absolute size, and read the wall bass as 1.4–1.6× a
  turbo sub's height. Stated, it is 1.400 against 0.670 — a factor of **2.09**. The middle row is 1.00 m deep where
  the outer columns are 0.56, so it stands further back and reads shorter than it is. Obliquity does the same to
  width: the IQ sub is 1.26:1 tall to wide, it reads about 1.39:1 in the nearly front-on left-hand column and about
  2.4:1 where the same box is seen at a steep angle on the right. That is why `gmss-turbo-top`, which sits entirely
  in the oblique part of the frame, read as a much narrower cabinet than it is.
* **A cabinet of a similar shape is not evidence of size.** `gmss-turbo-top` was anchored to the Turbosound TMS-4
  (1143 × 502 × 730 mm, 74.8 kg) on the grounds that a published cabinet of the same class beats a photograph. The
  real box is 710 mm tall and 26 kg. The refusal to write `clone_of: Turbosound` was right; letting the TMS-4 set
  the size anyway was not.

**And a wrong count hides a wrong size completely.** Two half-width mid basses fill the same 1.2 m span as one real
one, and every internal consistency check still passes. Nothing in the reconstruction could have caught that — only
asking the owner did.

Three earlier scales, for the record. The first read the blue illuminated logo on the sub faces as a ~60 mm badge
for ~3 mm per pixel. The second matched the turbo sub to our Flexy. The third — the last one before the owner's
figures — derived a single 0.85 factor for all four cabinets from the stated 18″ in the wall bass, on the reasoning
that an 18″ needs a ~526 mm baffle at minimum (460 mm frame + 36 mm of walls + ~30 mm of mounting margin) and that
one factor across the set preserves the proportions the photo does support. **That whole apparatus is gone**: five
stated cabinets are five absolute sizes, nothing is scaled from anything, and the specs no longer have to move
together.

| Device | logo-scale (0.52–0.54) | Flexy-matched (0.55) | 18″-driver scale (0.85) | stated |
|--------|------------------------|----------------------|-------------------------|--------|
| `gmss-iq-sub` (was `gmss-turbo-sub`) | 0.800 × 0.950 × 0.900 | 0.600 × 0.750 × 0.900 | 0.510 × 0.637 × 0.765 | 0.530 × 0.670 × 0.560 |
| `gmss-wall-bass` (was `gmss-middle-sub`) | 0.900 × 1.350 × 1.100 | 0.700 × 1.200 × 1.000 | 0.595 × 1.020 × 0.850 | 0.660 × 1.400 × 1.000 |
| `gmss-mid-bass` | 0.900 × 0.600 × 0.850 | 0.700 × 0.500 × 0.700 | 0.595 × 0.425 × 0.595 | 1.200 × 0.500 × 0.600 |
| `gmss-turbo-top` | 0.460 × 1.150 × 0.700 | 0.460 × 1.100 × 0.650 | 0.391 × 0.935 × 0.552 | 0.450 × 0.710 × 0.380 |

The 0.85 set scale had brought every GMSS cabinet *below* our own gear one for one. The stated figures scatter:

* `gmss-wall-bass` is now the **biggest and heaviest cabinet in this repository** — larger than our Flexy
  (0.591 × 0.763 × 0.964) on all three axes, and 220 kg against the SKRAM's 90.
* `gmss-nuke` is about the Flexy's width and height but 264 mm shallower and 27 kg lighter.
* `gmss-iq-sub` is smaller and lighter than anything we own, and there are six of them.
* `gmss-turbo-top` is a much smaller box than our `tecnare-m2122` (0.50 × 0.96 × 0.52, 68 kg) — 26 kg against 68.

So GMSS is not uniformly bigger or smaller than our rig, which is precisely what a single set scale could never
express. Scenes that compare the two systems — the `both-systems-*` set — were laid out against the old numbers.

**What is deliberately absent is as important as what is there.** No passbands, no crossover points, and no driver
sizes except the wall bass's stated 18″ — those are the numbers this repository refuses to invent, so the `audio`
block is simply missing from four of the five specs. The schema cannot record "two drivers, size unknown" either,
since `audio.drivers` requires a `size_in`, so the mid bass's driver count lives in its notes. The power ratings are
worse off than before: "8 turbo subs 3000rms" is now known to describe a mixed group of **two** cabinet types, so it
is recorded in both specs' notes as history and claimed for neither.

Round centimetres throughout, which is what the owner gave and is honest about the precision. Every one of the five
still needs a tape measure and a scale before it plans a real load-in — the mid bass's 120 kg first, since that is
the one figure the box's own volume argues against.

### The transporters: one documented, one described

`opel-movano-l4h3` and `sepp-transporter-l3h2` are the first devices here that are not gear, and they are sourced
very differently from each other. Worth reading before either is planned against, because **a wrong cabinet weight
makes a bad render and a wrong payload makes an overloaded van** — a fine, a liability question after an accident
and a refused insurance claim.

| Device | Reference | Source | What came from it |
|--------|-----------|--------|-------------------|
| `opel-movano-l4h3` | `datasheet` | **Zulassungsbescheinigung Teil I and Teil II**, both photographed by the owner on 2026-08-16 | Length 6848 (18), width 2070 (19), height 2792–2808 (20), mass in service 2476 kg (G), permitted gross 3500 kg (F.2 and F.1), axle loads 1850/2300 (7.1/7.2), towing 2500/750 (O.1/O.2), 3 seats (S.1), first registered 25.04.2016 (B), colour WEISS (R). **Payload 1024 kg is derived, `F.2 − G`, and stored nowhere** |
| | `datasheet` | Manufacturer body figures for the Renault Master / Opel Movano **L4H3 rear-wheel-drive** shell | Load bay 4383 × 1765 × 2048 mm, 1380 mm between the wheel arches, 15.8 m³. **Estimated**, and it describes a bare shell |
| `sepp-transporter-l3h2` | — | **The owner of the vehicle**, describing it | "L3H2 or L3H3 (not sure which), probably peugot, old deutsche post vehicle". That is the entire source |
| | — | Manufacturer body figures for the Peugeot Boxer **L3H2** shell | Outer 5998 × 2050 × 2522 mm, load bay 3705 × 1870 × 1932 mm, 1422 mm between the arches, 13 m³. **Estimated, and the vehicle is not identified** |
| | — | Nothing at all | The 3500 kg permitted gross and the 2300 kg mass in service are **guesses**, so the 1200 kg payload is a guess twice over |

**No registration document states the inside of a van**, which is why even the Movano's bay is `estimated` while its
masses are not. Its `provenance.dimensions` is therefore `estimated` and its `provenance.weight` `datasheet`: one
value has to cover both boxes, and the bay is the half a packer actually reads.

**Three deliberate choices, each of which could have gone the other way:**

* **The rear-wheel-drive bay for the Movano.** The L4 body is not offered front-wheel drive and the RWD floor sits
  higher, so H3 is 2048 mm rather than the 2144 mm an FWD H3 gets. The document agrees with the drivetrain — 1850 kg
  on the front axle against 2300 on the rear. The front-wheel-drive row would have invented 96 mm of headroom.
* **The Movano B and not the Movano C.** A 2016 Movano is Renault Master-based; the Boxer-based Movano C arrived in
  2021 and its L4 is 6363 mm where field 18 states 6848. Every bay figure would be out by a third of a metre. The
  owner puts the build year at 2014, which is the same generation and changes nothing.
* **The lower of Sepp's two possible roofs, and a heavy guess at his kerb weight.** An estimate against a legal
  limit is rounded in the **safe** direction: a bay estimated small and a payload estimated low make the packer
  refuse a load that would have fitted, which costs a second trip, where the other direction costs a prosecution.
  L3H2 and L3H3 differ only in height, 1932 against 2168, so that assumption costs 236 mm and nothing else.

**What replaces all of this:** a tape measure inside both vans — length at the floor, width between the walls and
between the arches, height under the roof and through the rear door aperture, and whether anything is bolted in that
never comes out — plus Sepp's Zulassungsbescheinigung, fields F.2 and G. One photograph of that document replaces
every number in his spec.

**Nothing identifying is recorded.** The VIN, the registration plate and the owner's home address are all on the
Movano's papers and none of them is a packing input.

### Not owned

`PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` also carries SKHORN, GHORN and OTHORN with
full dimensions and weights. Those were **evaluated, not bought** — only SKRAM was built. They are
deliberately absent from `specs/`; if one is ever acquired, the spreadsheet already has its numbers.

## What counts as a source

| `reference` | Means |
|-------------|-------|
| `datasheet` | a published spec sheet — the original manufacturer's, or for factory gear its own |
| `plans` | the build plans or cut list the cabinet was made from |
| `cad` | a published CAD/3D model of the design |
| `none` | nothing yet; the numbers are measured or guessed |

## Licensing

Only relevant for `mesh_override`, and only ever for models we did **not** generate:

* Everything the builder produces is our own geometry, generated from measurements or published
  dimensions. Dimensions are facts about an object, not a creative work.
* The CAD files in Drive (`subwoofer v28.obj`, `Achenbach 18.FCStd`, the 18sound `.FCStd`) are other
  people's work. Using them as a `mesh_override` means checking their licence first and recording it
  here. They are fine as a *dimension reference* either way.
* Nothing binary is committed to this repository — `build/` is ignored — so a `mesh_override` mesh
  lives outside it and is referenced by path. A third-party licence question can therefore never
  become a question about this repo's history.

## 3D geometry, per device

Two CAD folders exist in Drive — `Hardware/Speaker Enclosures _ .../<design>/` and a second, richer
`Medien/Bildbearbeitung/Merch/CAD/`. The whole Shared Drive was swept for 3D formats; this is
everything, and what it is good for.

| Device | Detailed geometry? | Source |
|--------|--------------------|--------|
| `flexy-folded-horn-hybrid` | **yes** — four folded-horn mouths | `Merch/CAD/3D Print Smoking paper rolls case - 3381901/files/flexy.stl`: 1:10, watertight, 2817 faces. `units: cm`, `rotate_deg: [90, 0, 90]`, 3 mm narrower than the spec |
| | (rejected alternative) | `…/MrFlexySMPs Folded Horn Hybrid/3d models/subwoofer v28.obj`: has the horn but is a non-watertight shell and 18 mm narrow |
| `achenbach-18` | **yes** — driver cut-out and corner braces | `Achenbach 18.FCStd`, converted with `ddev mesh-convert`. Its 600 × 700 × 600 mm matches the spec exactly — a third confirmation after the panel geometry and lsv-achenbach.de's published panel sizes |
| `eighteensound-2way-15` | **yes** — octagonal horn cut-out, Ø353 driver hole, 2× Ø100 ports | `Eighteensound 2 Way Point Source 15.FCStd`, converted with `ddev mesh-convert`. **Its outer box is the authority, not the drawings** — see below |
| `skram` | **yes** — Josh Ricci's own CAD | The [SKRAM DIY Package](https://www.jwsound.live/designs/riccis-skram-subwoofer) contains `STEP Files/SKRAM 3D.step` (full assembly, 52 solids) plus individual panel STEPs, 29 DXFs, Fusion `.f3d`, SolidWorks parts and a cut sheet. Converted with `ddev mesh-convert`; measures 619.6 × 812.8 × 924.4 mm — height exactly 32″, width and depth each 10 mm over the published 24″/36″ |
| `tecnare-m2122` | **no source anywhere** | Two photos and the L2122LT datasheet's line drawings. Nothing modellable — its baffle is generated from estimates, see below |

Not owned, but present in Drive if ever needed: `Selenium PAS1MA1 full.obj`, twelve DWG + twelve DXF
sheets for `KIT S21HL` (also on Stefan's disk at `~/PhpstormProjects/Extension_Jonas/`), dimensioned
Inlow Sound PDFs, a 149-part `PAS4MA1 e HB1505D1.dae` driver-and-horn assembly, and `horn.blend` — a
22-part 2.3 m horn extension.

FreeCAD is installed neither on Stefan's machine nor in the ddev container, so the two `.FCStd`
devices need one manual export each — see [`../meshes/README.md`](../meshes/README.md).

### The 18sound's baffle sits 56 mm inside the cabinet

Measured out of its mesh, this cabinet's front is stepped: the top and bottom panels project furthest
forward with a curved front edge, the two side panels stop 30–35 mm behind them, and the baffle carrying
the horn and driver cut-outs is **55.8 mm behind the front-most point**. So the openings on this cabinet
sit visibly deeper than on any other in the library — its features are flush with its baffle, but the
baffle itself is recessed behind a lip.

**Confirmed against the real cabinet** — the owner reports the lip is there and about 56 mm deep. So the
deep-set openings are a real feature of this cabinet rather than a modelling artifact, and nothing in the
layout needs moving.

That confirmation settles this cabinet's disputed depth as well. The drawings say 335 mm and the CAD's
bounding box says 426.8 mm; the 56 mm lip is part of what accounts for the difference, so the CAD wins
again — the same conclusion the panel dimensions reached below, now from a second direction.

### Baffle layouts, per device

`audio.layout` describes the openings on a cabinet's front. These numbers are the easiest in the whole
repository to invent, so each layout carries its own `provenance` separate from the cabinet's.

| Device | Layout provenance | Where the numbers come from |
|--------|-------------------|------------------------------|
| `eighteensound-2way-15` | `plans` | The kit drawings' front view dimensions both openings and their spacing: octagonal horn cut-out 362 × 285 mm centred 142.5 mm below the baffle top, Ø353 mm driver hole 502.5 mm below it. The 800 mm baffle is centred in the 836 mm outer box, so baffle-frame z maps straight onto the cabinet's frame. The **horn itself is elliptical** even though the cut-out around it is the octagon the drawing dimensions (100.7 + 160.6 + 100.7) — owner, against the cabinet. Its **flare law is in no source** — `exponential` was chosen because an XT1464 is a flared horn |
| `achenbach-18` | `plans` | Measured out of the FreeCAD mesh: the baffle carrying the cut-out is the cabinet's **front-most panel**, so its inset is 0, and the cut-out is Ø416 mm centred on the 600 × 600 baffle. 416 mm is the driver's cut-out, not its nominal 18″ (457 mm) frame — the frame sits behind the panel and only the cone shows through |
| `tecnare-m2122` | `estimated` | **Nothing here is measured.** The arrangement is as the owner describes it — mid horn on top, two 12″ LF horns stacked below, a 1″ horn inside each as a phase plug — but every mouth size, centre height, throat size, depth and flare law is derived from the outer dimensions and the driver sizes. The **shapes are not estimates**: all five horns are straight-edged at the mouth and round at the throat, confirmed by the owner against the cabinet — the LF pair at their 12″ drivers, the mid and the two 1″ HF plugs at their compression drivers. The **two LF horns are connected**, also owner: they share one mouth, so the wall between them stops behind the baffle. How far back is an estimate like the rest — 0.190 m of the 0.200 m they are deep, i.e. as connected as the geometry allows while a wall still exists, leaving a 10 mm lip at the drivers. A tape measure on one horn mouth would promote the sizes to `measured` too |

The Flexy and SKRAM have no layout: their drivers sit deep inside a folded horn path and are not visible
from outside, so there is nothing to model.

## Original datasheets

| Original | Datasheet | Retrieved |
|----------|-----------|-----------|
| Tecnare L2122LT | https://www.tecnare.co/wp-content/uploads/2020/01/l2122lt.pdf | 2026-07-30 |
| Eighteen Sound 15″ 2 Ways Kit | `18sound/18sound_15 2ways.pdf` in Drive (© Eighteen Sound 2013) | 2026-07-30 |
