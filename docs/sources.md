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
| `achenbach-18` | no — needs one export | `Achenbach 18.FCStd` (in both CAD folders). Blender cannot read FreeCAD |
| `eighteensound-2way-15` | no — needs one export | `Eighteensound 2 Way Point Source 15.FCStd`, plus `18sound_15 2ways.pdf` with dimensioned front/side/top/back views, sections and an exploded view — enough to model the baffle by hand: Ø353 mm driver cut-out, 2× Ø100 mm ports, 215 × 260 mm horn mouth |
| `skram` | **no source anywhere** | Only `Skram Panel List.csv`. Plans are sold by [JW Sound](https://www.jwsound.live/designs/riccis-skram-subwoofer); the sibling SKHORN is distributed with STEP and DXF, so SKRAM CAD may exist on request |
| `tecnare-m2122`, `-clone` | **no source anywhere** | Two photos and the L2122LT datasheet's line drawings. Nothing modellable |

Not owned, but present in Drive if ever needed: `Selenium PAS1MA1 full.obj`, twelve DWG + twelve DXF
sheets for `KIT S21HL` (also on Stefan's disk at `~/PhpstormProjects/Extension_Jonas/`), dimensioned
Inlow Sound PDFs, a 149-part `PAS4MA1 e HB1505D1.dae` driver-and-horn assembly, and `horn.blend` — a
22-part 2.3 m horn extension.

FreeCAD is installed neither on Stefan's machine nor in the ddev container, so the two `.FCStd`
devices need one manual export each — see [`../meshes/README.md`](../meshes/README.md).

## Original datasheets

| Original | Datasheet | Retrieved |
|----------|-----------|-----------|
| Tecnare L2122LT | https://www.tecnare.co/wp-content/uploads/2020/01/l2122lt.pdf | 2026-07-30 |
| Eighteen Sound 15″ 2 Ways Kit | `18sound/18sound_15 2ways.pdf` in Drive (© Eighteen Sound 2013) | 2026-07-30 |
