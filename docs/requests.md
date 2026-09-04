# Requests

What to ask each system's owner for, and why it matters. **One row per missing figure**, deleted rather than
ticked once the answer arrives and the spec is updated — the same convention as [`../TODO.md`](../TODO.md).

This file exists because two systems' specs were written from sources that give a front elevation and nothing
else. [docs/sources.md](sources.md) records where each number came from; this one records the numbers that
have no source at all, so that a spec's weakest field is a question somebody can answer rather than a
footnote nobody reads.

**Why not simply wait for the answers.** A spec that exists can be rendered, packed and argued with; a spec
that does not exist is invisible to every command in the repository. The GMSS specs spent six months as a
photograph reconstruction and the reconstruction is what made the right questions askable — it got the width
and height of every cabinet inside 35 mm and missed one depth by 205 mm, and only asking settled it. So the
numbers below are placeholders on purpose, and this list is the point of them.

## How to read the columns

* **Field** — the spec field that has no source, in the form the schema uses.
* **Now** — what the spec carries today, and where it came from.
* **Why it matters** — what reads that field, so a request can be prioritised by what it unblocks rather than
  by how wrong it looks.

## Innschleife

**Nothing about this system is published anywhere.** Searching returns nothing, and the only source in
existence is two setup drawings in our own Drive:

* `Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/Staudham_Sdwa5_Innschleife_06_12_25/`
* `Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/06.12.25 Staudham/` — the 2026-02-28 revision

Each drawing embeds one photograph per cabinet type at **one pixel to the centimetre**, so the front width and
height are readable and nothing else is. Four cabinet types are drawn. **Two more are specced from what
Innschleife said**, which is now the better source of the two by some distance — and for the SBH it is the only
one, since no drawing shows that cabinet at any size. **20 cabinets, 1506 kg as estimated.**

### The identities, four of which arrived on 2026-09-02

**Three names are settled and one cabinet is left over.** Innschleife said what they are bringing — four "SBH 18",
four "WSX 18", four "die blauen Kicker 15" — and three sub types at four each was exactly what the three drawn
specs said, so the names were mapped one to one onto them on 2026-09-02. **Reading the mapping back corrected two
of the three at once**: the 0.600 × 0.600 box is a small Electro-Voice wbin and *is* the blue kicker, and the SBH
is a 1.20 × 0.55 × 0.80 horn that appears in no drawing at all. So there are four sub types where the statement
listed three, and `sub-95x57` is the one nobody has named and nobody is bringing.

**A MAPPING THE NUMBERS PERMIT IS NOT A MAPPING ANYBODY CONFIRMED.** Three types at four each made the fit look
forced when it was only consistent. That is the same lesson the count correction taught a day earlier, and it now
covers names as well.

| Ask | Cabinet | What is known |
|---|---|---|
| Ask | Cabinet | What is known |
|---|---|---|
| **What is the 0.950 × 0.570 m cabinet?** | `sub-95x57` | **The one left over, and the highest-value question here.** Four are drawn, near black, two horn mouths side by side, placed above the Achenbach row and below the tops. It carried the name "blaue Kicker 15" for an hour on 2026-09-02 and lost it again when that turned out to be the wbin. It was not in the event statement either, so the roster leaves it at home |
| **Are the wbins factory cabinets or built to EV's plans?** | `kicker-15` | Stated: "Electrovoice wbins, sind die blauen". Electro-Voice published W-bin drawings and they were built by hand for twenty years, so the phrase reads both ways. `build: original` is the weaker assertion — a factory box, no model. If it is a self-build the field becomes `self-built` with a `clone_of`. **And which model?** |
| **What blue?** | `kicker-15` | The photograph averages #1D1D1D, near black, which is what the spec carried until the owners said otherwise. The value in it now is a mid blue standing in for a shade nobody has measured. One photograph in daylight settles it |
| **Is the SBH's horn flare asymmetrical?** | `sbh-18`, `kicker-15` | Innschleife build the SBH lying down. For the SBH that changes the box — 0.550 wide becomes 1.200 — so the sweep's `turned` and `mixed` variants show it. **The wbin's front is square**, so if it is ever laid down the box does not change at all and no render could show it; only an asymmetrical flare would make the orientation mean anything |
| **What drives any of them?** | all six | Not one Innschleife spec has an `audio` block. "SBH 18" and "Kicker 15" name driver sizes and nothing else — no passband, no coverage, no count. `Passband::orderingLowHz()` decides which cabinet goes at the bottom of a stack, so with none of them stating one the fill order runs entirely on mass |
| **Which purple cabinet is the TMS-4?** | `tms4`, `thl4` | **The sharpest question in this file, and it is worth asking exactly this way.** You say the small tops are Turbosound TMS-4 and the big ones THL-4. The published TMS-4 is **1143 × 502 × 730 mm and 74.8 kg**, which is the *big* one's front to the millimetre — the small ones measure 0.430 × 0.870 in both your drawings. Either the two names sit on the wrong boxes, or one of the drawn fronts is wrong by 270 mm, or the published figures are a different revision. **No published figure has been copied into either spec**, because a Turbosound of a similar class was once let in as a size anchor for `turbo-top` and came out 61 % too tall |
| **Is the 0.600 × 0.600 m box one cabinet or two?** | `kicker-15` | The two drawing generations use two different photographs of the same size in the same position. Read as one cabinet redrawn; if it is two, the spec splits |
| **How many of each, and does the SBH ever stand up?** | all six | Answered for the event and worth keeping as a warning. The statement said "alle 4 kleinen Tops" and was corrected to two; the name mapping it implied was corrected the next day on two cabinets at once. **Read a count and a name back before writing either down** |

### The figures

| Field | Cabinet | Now | Why it matters |
|---|---|---|---|
| `geometry.dimensions_m.depth` | all but `sbh-18` | Derived by analogy: 0.900 from our horn subs' 0.700–0.964, 0.600 from `mid-bass`, 0.700 from `achenbach-18`'s identical front, 0.430 from `eighteensound-2way-15` | **The axis this repository has already been badly wrong about.** The GMSS reconstruction missed the IQ sub's depth by 205 mm, a third of the cabinet, having got its width and height inside 35 mm. Depth decides the footprint every load plan and every stack clearance is computed from |
| `physical.weight_kg` | all six | Modelled volume at 200 kg/m³, this library's own central density | Twenty cabinets here run 139 to 333 kg/m³, so the method carries ±50 %. On 1506 kg of Innschleife gear that is ±753 kg, against a fleet whose real payload is already one weighbridge ticket and one assumption. See [load.md](load.md) |
| `quantity` | all six | The drawn count for four of them — 4, 4, 4, 2 — and the owner confirms all four. The THL-4's 2 is the owner's own figure | A drawing shows what came to one gig, and for once the two agree. Every generated rig is built from these counts, so a wrong count is a wrong rig rather than a wrong number — and `turbo-top` is the worked example, where a photograph showing four against a stated three is still unresolved |
| `audio.passband_hz` | all six | **Absent.** No spec claims one | `Passband::orderingLowHz()` decides which cabinet goes at the bottom of a stack. With no passband the fill falls back to mass, so an Innschleife sub is ordered by how heavy it is rather than by how low it goes |
| `audio.drivers` | all six | **Absent.** The photographs show mouths and cells, which describe a front rather than a driver | Nothing in a render depends on it, but it is the difference between a cabinet and a box |
| `audio.coverage_deg` | `tms4` | **Absent** | A top's pattern is what a coverage check reads. A rig mixing our 60 × 40 Tecnare with an unknown pattern cannot be checked at all |
| `rigging.points` | all six | `flyable: false`, which is a statement about our knowledge rather than about the cabinets | Whether anything of theirs flies, and from where |

## PSL

**PSL is Pro Sound & Light, Paunzhausen**, a rental company. Their own site publishes the full technical data
for their whole speaker inventory at <https://pro-sound-light.de/equipment/tontechnik/>, so unlike Innschleife
**every dimension and weight here has a real source** and ten specs are `provenance: datasheet`. What the site
does not publish is how many of each they own, and that is most of this section.

The two Concert Audio cabinets also appear in our own drawing,
`Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/setups/PSL_SdWa5_Kraut_26_05_23/`, which agrees with the
published figures to the centimetre and is where the white finish comes from.

| Field | Cabinet | Now | Why it matters |
|---|---|---|---|
| `quantity` | `thebox-achat-115m`, `thebox-dsp-112`, `thebox-pa302`, `hk-linear5-112x` | **1 each, which is an admission rather than a figure.** These four are in no published package and in none of our drawings, so nothing states a count. `quantity` cannot be 0 — the validator refuses it and the sweep filters on it — so 1 is the smallest thing that can be written, and it is almost certainly wrong for gear bought in pairs | Every generated rig is built from these counts |
| `quantity` | `concert-audio-ef6`, `concert-audio-esx` | 4 and 6, from the "Hornsystem Mod" package's stated four horn tops and six hybrid-horn subs, **confirmed independently** by our own drawing placing exactly four and six. PSL stated on 2026-09-02 what they are bringing to the next event, and [`rosters/psl-next-event.yaml`](../rosters/psl-next-event.yaml) carries that. **What they own is a different fact and is deliberately not inferred from it** | Two sources agreeing is the best count in this section, and a package is still not an inventory. **Ask them outright how many ESX and EF 6 they own** |
| `quantity` | `concert-audio-esf`, `thebox-tp118-800`, `thebox-tp218-1600` | 2 each, the floor stated by one package. Bundle 3 pairs two TP118 with two TP218, so the packages are not disjoint sets of stock and none of them bounds the total | As above |
| `geometry.dimensions_m` | `concert-audio-esx`, `thebox-tp118-800` | Published **"o. Rollen"**, without castors | The ESX has four 100 mm castors and the TP118's set ships with it. A sub on its wheels is the one at the bottom of a stack, so the height a rig is built from is about 100 mm short. Which axis the wheels are on depends on which way up the ESX stands, so it cannot be corrected for |
| `rigging.points` | `concert-audio-ef6`, `concert-audio-esf` | `flyable: false` | Both carry an "integriertes EVOLUTION Flugsystem mit Verbinderelementen", so they **do** fly. Where the points sit is published nowhere, and the validator rightly refuses `flyable: true` with no points: a fly point nobody can place is worse than none |
| `rigging.points` | `thebox-achat-112m`, `thebox-pa302`, `hk-linear5-112x` | `flyable: false` | Three M8 points on the PA302 and the HK, an Aeroquip rail and an eye bolt on the Achat. All published as existing, none positioned |
| Revision | `thebox-tp218-1600` | The id carries none | The equipment page says **MkIII** and the package pages say **MK2**, and every published figure is identical in both. So either both revisions are there or one page is stale. It changes no number in the spec, which is why it is a question rather than a gap |
| `audio.passband_hz` | `thebox-dsp-112` | **Absent** | The catalogue prints a max level and a pattern for this one but no frequency range, and an active cabinet's response belongs to its DSP preset rather than to its box |
| `audio.drivers` HF | `thebox-pa302` | Only the 12" is recorded | The source says "Horn mit 44 mm Titaniumtreiber", and 44 mm is a voice coil diameter. `Driver.size_in` wants a driver or throat size, and converting between them is arithmetic on a number that means something else. See SPEC-6 |
| `audio.coverage_deg` | `hk-linear5-112x` | 60 × 55, from HK's data table | The listing carries two figures. The sales copy says a "drehbare 60 x 40 CD-Horn" and the data table below it says 60–90 asymmetric × 55. The table is the one taken, because it is a measurement under an EN 60268-5 note, and 40 against 55 vertical stays an open difference |
| `audio.coverage_deg` | `concert-audio-esx` | **Absent** | The published "ca. 180° horizontal" is measured with four cabinets side by side, so it describes an array of four rather than one box |
| `appearance.color` | the three Concert Audio cabinets | White, from the drawing and from the "Mod" packages being "in weiß" | The catalogue's own finish is matte black. So PSL have a white build of a black product, and this is the one field in those three specs that the technical data does not support |

## Ours, and Sepp's

Not new, and not this file's job — the measuring backlog lives in [measuring.md](measuring.md) and the three
unsourced weights in [sources.md](sources.md#still-unsourced). One item is worth repeating here because it is
a request rather than a measurement and it is the largest single number in the repository:

| Ask | Now | Why it matters |
|---|---|---|
| **Put the Movano on a weighbridge** | Payload 1024 kg, derived `F.2 − G` from its registration document | Sepp's van was documented at 1365 kg and **weighed at 1000**, because a fit-out added after type approval appears in no field of the paper. The Movano's figure is the same class of number and has never been checked. One ticket settles whether the fleet is short by 134.9 kg with the generator aboard, as measured, or by well over 250. LOAD-2 in [`../TODO.md`](../TODO.md) |
