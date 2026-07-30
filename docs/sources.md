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
| `tecnare-m2122` | `estimated` | **Nothing here is measured.** The arrangement is as the owner describes it — mid horn on top, two 12″ LF horns stacked below, a 1″ horn inside each as a phase plug — but every mouth size, centre height, throat and flare law is derived from the outer dimensions and the driver sizes. Two things are **not** estimates: the LF horns are straight-edged at the mouth and round at the driver (owner, against the cabinet), and the mid horn's throat is round because a compression driver's throat is a round bolt flange. A tape measure on one horn mouth would promote the rest to `measured` |

The Flexy and SKRAM have no layout: their drivers sit deep inside a folded horn path and are not visible
from outside, so there is nothing to model.

## Original datasheets

| Original | Datasheet | Retrieved |
|----------|-----------|-----------|
| Tecnare L2122LT | https://www.tecnare.co/wp-content/uploads/2020/01/l2122lt.pdf | 2026-07-30 |
| Eighteen Sound 15″ 2 Ways Kit | `18sound/18sound_15 2ways.pdf` in Drive (© Eighteen Sound 2013) | 2026-07-30 |
