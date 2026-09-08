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
| `iq-sub` | — (GMSS self-build) | — | **The owner of GMSS**, answering questions about `GMSS.jpeg` | "6 IQ subs: 53w, 56d, 67h, 40kg each" — count, all three dimensions and the weight |
| | | | An earlier message from GMSS | "8 turbo subs 3000rms" — now known to cover **two** cabinets, 6 IQ subs + 2 nukes, so the power rating cannot be attributed to either |
| | | | `GMSS.jpeg`, one site photo, no scale reference | Which cabinets these are: the six grille-fronted boxes with a blue illuminated logo, **three to an outer column** |
| `nuke` | — (GMSS self-build) | — | **The owner of GMSS** | "2 nukes: 59w, 70d, 77h, 58kg each", and that the outer bottom-row boxes are turbo subs — "the ones on the outside of the bottom row are also turbo subs" |
| | | | `GMSS.jpeg` | Which cabinets these are: the two on the ground at the foot of each outer column, **no grille**, plain face with a recessed oval. Previously mis-read as the mid bass |
| `wall-bass` | — (GMSS self-build) | — | **The owner of GMSS** | "2 wall basses: 66w, 100d, 140h, maybe 220kg each" — count and dimensions stated, the **weight hedged by him** |
| | | | An earlier message from GMSS | "Middle subs are 18/1600rms" — an 18″ driver and 1600 W RMS, tied to this cabinet by elimination across the two messages |
| | | | `GMSS.jpeg` | Which cabinets these are: the pair of fin-mouthed folded horns standing in the centre of the stack |
| `mid-bass` | — (GMSS self-build) | — | **The owner of GMSS** | "USB: 120w, 60d, 50h, ~120kg" (weight **hedged**), and the identity: asked which box the mid bass is, "That's the mid bass (usb)" of the horn in the middle. So **one** cabinet, not two |
| | | | An earlier message from GMSS | "USB 2x 700rms mid bass" — two drivers at 700 W RMS. Their size is not stated, nor is what "USB" refers to |
| | | | `GMSS.jpeg` | Which cabinet this is: the single wide cross-braced horn mouth lying across the two wall basses. Its stated 1.20 m spans the 1.32 m pair, which is what makes one cabinet certain rather than merely allowed |
| `turbo-top` | — (GMSS self-build) | — | **The owner of GMSS** | "Tops: 45w, 38d, 71h, 26kg each" — dimensions and weight, **neither hedged**. No count |
| | | | An earlier message from GMSS | "3 turbo top 2500rms" — the count and the power rating. Still the only source for `quantity: 3`, though the photo appears to show **four** |
| | | | [Turbosound TMS-4](https://www.warehousesound.com/turtms4.php), [manual](https://archive.org/stream/Turbosound/Turbosound%20TMS-4_djvu.txt) | 1143 × 502 × 730 mm and 74.8 kg — used as a size anchor before the owner's figures arrived and **contradicted by them**: 61 % too tall, nearly 3× too heavy. Kept as a rejected line of reasoning |
| `concert-audio-ef6` | — (factory cabinet) | `datasheet` | **[PSL's own published technical data](https://pro-sound-light.de/equipment/tontechnik/)**, retrieved 2026-09-02 | "780 x 588 / 224 x 720 mm (H x B x T)", 65 kg, 2× 12″ with 76 mm coils, 1.5″ compression driver with a 75 mm coil, 70–17 000 Hz ±3 dB, 55° × 40° at −6 dB from 600–10 000 Hz, 8 Ω per section, 1000 W programme low-mid and 160 W mid-high, 109/112 dB sensitivity, 146 dB peak. **The two widths are the front and back faces**, which is what makes it a trapezoid |
| | | | `setups/PSL_SdWa5_Kraut_26_05_23/`, our own drawing at 1 px = 1 cm | Independent confirmation of 0.59 × 0.78 m to the centimetre, and the **white** finish — the catalogue's own is black |
| | | | The "Hornsystem Mod" package, same site | **Quantity 4**: "vier Horntopteile", agreeing with the four the drawing places |
| `concert-audio-esf` | — (factory cabinet) | `datasheet` | Same PSL page | "identische Abmessungen wie EF-6 System" **in words as well as in figures**, then the same 780 × 588/224 × 720 mm. 46 kg, 1× 18″ long-throw with a 100 mm coil, 40–220 Hz −6 dB and 37 Hz with the controller, 8 Ω, 1400 W programme, 96 dB / 131 dB peak. Radial pattern, so no coverage figure |
| | | | The "Evolution Mod System 1" package | **Quantity 2**, a package floor. This cabinet appears in none of our drawings |
| `concert-audio-esx` | — (factory cabinet) | `datasheet` | Same PSL page | "1180 x 590 x 915 mm (H x B x T o. Rollen)", 94 kg, 2× 18″ with 100 mm coils, 38–220 Hz −6 dB and 33 Hz with the controller, 39 Hz resonance, 2 × 8 Ω, 2800 W programme, 99 dB / 138 dB peak, 18 mm birch ply, ten metal handles, four 100 mm castors, 2 mm mesh grille at 70 % over acoustic foam. **The depth excludes the castors** |
| | | | `setups/PSL_SdWa5_Kraut_26_05_23/` | **Quantity 6**, agreeing with the "Hornsystem Mod" package's "sechs Hybrid-Horn Subwoofer". The drawing has them **rolled**, 118 wide by 59 high, which is the same box on its side |
| `thebox-achat-112m` | — (factory cabinet) | `datasheet` | Same PSL page | 360 × 600 × 365 mm, 21 kg, 12″ + 1.4″ neodymium with a 3″ coil, 60–18 000 Hz, 60° × 40° rotatable, 8 Ω, 350 W RMS, 131 dB max. **The axis order is not stated for this one entry** and is read as width × height × depth, which is the page's usual order and the only reading that holds a 12″ driver |
| | | | Bundles 1, 2 and 3, same site | **Quantity 2**, a floor. Three packages each name two, which bounds nothing since Bundle 3 shows the packages are not disjoint |
| `thebox-achat-115m` | — (factory cabinet) | `datasheet` | Same PSL page | "(B x T x H): 436 x 438 x 766 mm" — **depth in the middle**, stated. 32 kg, 15″ + 1.4″, 60–17 000 Hz, 60° × 40°, 350 W RMS, 131 dB max |
| `thebox-dsp-112` | — (factory cabinet) | `datasheet` | Same PSL page | "(B x H x T): 348 x 607 x 355 mm", 14.6 kg **including its amplifier**, 12″ + 1″ with a 1.4″ coil, 300 W RMS class-D, DSP with four presets, 134 dB max, 90° × 60°. **No frequency range is published**, so the spec carries no passband |
| `thebox-pa302` | — (factory cabinet) | `datasheet` | Same PSL page | "(H x B x T): 61,5 x 42 x 39 cm", 18.6 kg, 12″ plus a horn with a 44 mm titanium driver, 40–20 000 Hz, 55° × 55–100° VCD, 300 W RMS, 121 dB max, moulded PP cabinet, three M8 points on top. **44 mm is a voice coil**, not a throat, so the HF driver is not in `audio.drivers` |
| `thebox-tp118-800` | — (factory cabinet) | `datasheet` | Same PSL page | "(B x H x T): 55 x 61,8 x 68 cm ohne Rollen", 37 kg, 1× 18″, 35–150 Hz at **−3 dB** rather than the −6 dB the Concert Audio cabinets are quoted at, 8 Ω, 600 W RMS, 96 dB / 129 dB peak |
| | | | Bundles 1 and 3 | **Quantity 2**, a floor |
| `thebox-tp218-1600` | — (factory cabinet) | `datasheet` | Same PSL page | "(H x B x T): 1200 x 550 x 680 mm", **82 kg net** (90 kg shipping, which includes the pallet), 2× 18″, 34–150 Hz with no low-pass, 4 Ω, 1600 W AES, 100 dB / 136 dB max, twelve handles, 100 mm castors. The equipment page calls it **MkIII** and the package pages **MK2** with identical figures |
| | | | Bundles 2 and 3 | **Quantity 2**, a floor |
| `hk-linear5-112x` | — (factory cabinet) | `datasheet` | Same PSL page, which reproduces **HK's own data table** | "(BxHxT): 37 x 66,8 x 30 cm", 19.5 kg, 12″ with a 2.5″ coil + 1″ with a 1.75″ coil, −6 dB 79 Hz–18 kHz (−10 dB 60 Hz–19 kHz), 8 Ω per EN 60268-5, 103 dB, 129 dB average and 135 dB peak, crossover 1.7 kHz. **The page carries two coverage figures**, 60° × 40° in the sales copy and 60–90° asymmetric × 55° in the table. The table is used |
| `wsx-18` | — (unidentified) | — | `setups/Staudham_Sdwa5_Innschleife_06_12_25/` and `setups/06.12.25 Staudham/`, **one embedded photograph per cabinet type at 1 px = 1 cm**, a scale stated by the owner | Front **0.570 × 1.100 m** and nothing else. Mid grey, a wide curved horn mouth over four cells. Placed on the ground beside our Flexy subs, which is what makes it a `sub`. Four copies drawn |
| | | `estimated` | Depth **0.900 m** by analogy with our own horn subs (Flexy 0.964, SKRAM 0.813, Achenbach 0.700) | The deepest class we have figures for, at the deep end because it is the tallest |
| | | `estimated` | Weight **110 kg**, modelled volume at 200 kg/m³ | This library's central density. Twenty cabinets run 139–333 kg/m³, mean 212, and the wooden horn subs sit at 196–199 |
| `kicker-15` | **Electro-Voice wbin**, model unknown | — | The two drawings, plus Innschleife on 2026-09-05: *"nicht die 4 schwarzen JBL 60x60, stattdessen die blaue Electro-Voice 95x57"* | Front **0.950 × 0.570 m**. Two horn mouths side by side across the full width. Placed above the Achenbach row and under the tops, which is a mid-bass position. Four copies drawn, and four are coming to the next event. **The manufacturer and the colour are stated; the model and whether it is factory or built to EV's published W-bin plans are not** |
| | | `estimated` | Depth **0.600 m** from `mid-bass` (1.200 × 0.500 × 0.600), the one cabinet here of the same shape; weight **65 kg** at 200 kg/m³ | Both Innschleife tops had analogous depths corrected upward by 148 and 210 mm the moment they were identified, so read this as probably short |
| | | — | The colour, which the photograph contradicts | The drawing's own photograph averages **#1D201C**, near black, and the owners say blue. Same conflict `sub-60x60` had for two days in the other direction, and it resolves the same way: an average is not a paint colour. Our own Flexy averages #C7C7C7 where the cabinet is black |
| `sbh-18` | — (name only) | — | **Innschleife, 2026-09-03: "120 lang, 55 breit, 80 tief"** | The only Innschleife cabinet no drawing shows, and the only one whose three edges the builders stated. Upright it is 0.550 × 1.200 × 0.800; they build it **lying**, so four side by side are **4.80 m of horn mouth** — "als großes Horn". Weight 106 kg is the modelled volume at 200 kg/m³ and is the one number here nobody stated |
| | | `estimated` | Depth **0.600 m** from `mid-bass` (1.200 × 0.500 × 0.600), the one cabinet here of the same shape; weight **65 kg** at 200 kg/m³ | |
| `sub-60x60` | **JBL**, model unknown | — | The two drawings, plus Innschleife on 2026-09-05: *"die 4 schwarzen JBL 60x60"* | Front **0.600 × 0.600 m**, four-cell face, near black. **Exactly `achenbach-18`'s front**, and drawn with a *different* symbol in the same drawing, which is what proves it is a different cabinet. Four copies drawn, and **none coming to the next event**. A model would be worth having: a JBL of a stated model has published dimensions and a weight, and this spec has neither |
| | | `estimated` | Depth **0.700 m** from `achenbach-18`'s identical front; weight **50 kg** at 200 kg/m³, which lands on the Achenbach's own estimate | The strongest analogy in this set, and still an analogy. One piece of evidence rather than two, since both derivations start from the same box |
| `tms2` | **Turbosound TMS-2**, identified 2026-09-05 | `datasheet` | Front off the two drawings; depth and weight off [Turbosound TMS-2](http://warehousesound.com/turtms2.php), **432 × 865 × 578 mm and 48 kg** | Drawn front **0.430 × 0.870 m**, which is the published 432 × 865 to **2 and 5 mm** — inside the drawing's own 1 px = 1 cm resolution. **Purple with a script logo**, four stacked sections, a round horn mouth at the top, which is the published 15"/10"/1" complement. Two copies in the top row of the right-hand column, mirroring our two Tecnare on the left |
| | | — | The width and height are **not** taken from the datasheet | 0.430 × 0.870 drawn is kept over 0.432 × 0.865 published. Where a measurement of *this gear* and a measurement of *the model* both exist the first wins, and 2 mm changes no rig. Depth and weight are adopted only because no measurement of them exists |
| | | — | What the identification moved | Depth **0.430 → 0.578 m** and weight **32 → 48 kg**, both estimates optimistic. Its sibling moved 210 mm and 15.8 kg in the same direction, so the 200 kg/m³ model reads light on a big multi-way top rather than at random |
| `tms4` | **Turbosound TMS-4**, identified 2026-09-05 | `datasheet` | Front off the **first two revisions only** of `setups/Staudham_Sdwa5_Innschleife_06_12_25/`; depth and weight off [Turbosound TMS-4](https://www.warehousesound.com/turtms4.php) and the [manual](https://archive.org/stream/Turbosound/Turbosound%20TMS-4_djvu.txt), **1143 × 502 × 730 mm and 74.8 kg** (45" × 19.75" × 28.75", 165 lb) | Drawn front **0.500 × 1.140 m**, which is the published 502 × 1143 to **2 and 3 mm**. Purple, like the small top. Gone from every later drawing revision, which is why it went unspecced until Innschleife's statement proved it real rather than a draughting correction |
| | | — | **The owners called this one a THL-4 and it is not.** | The published THL-4.3 is **1007 × 574 × 718 mm and 92 kg**, which misses both purple cabinets by over 70 mm on both edges. The TMS-3 misses too at 1019 × 844 × 578. One model in the series matches and its neighbours miss by a lot, which is what separates an identification from a resemblance — and is why the `turbo-top` precedent below does not apply here |
| | | — | What the identification moved | Depth **0.520 → 0.730 m** and weight **59 → 74.8 kg**. The spec's own header had predicted the weight would come out light, on the grounds that a big three-way runs nearer 272 kg/m³ than 200 |
| `truss-f33-2m` | — (factory truss) | `datasheet` | **[Stairville Wind Up DJ Bundle III](https://www.thomann.de/de/stairville_wind_up_dj_bundle_iii.htm)**, Thomann article 267121, supplied by the owner 2026-08-17 | **Global Truss F33200 identified outright.** 2.0 m, tube spacing 290 mm outer, chord Ø 50 × 2 mm, AlMgSi F31, TÜV Nord, **9.3 kg**. Three came in the bundle and two were bought afterwards |
| | | | The owner | That we have **5 segments at 2 m, three-point**. The class is an inference from that |
| `truss-tower-4m` | — (factory stand) | `datasheet` | **[Stairville Wind Up DJ Bundle III](https://www.thomann.de/de/stairville_wind_up_dj_bundle_iii.htm)**, supplied by the owner 2026-08-17 | **Varytec Wind Up 85 kg identified**, replacing an assumed Global Truss ST-132. 25 kg, max height 4.0 m, **transport 1.75 m**, **max load 85 kg**, min load 25 kg, 1 3/8″ receiver, crossbar 1300 × 35 × 35 mm, base spread 1.6 m, TÜV |
| | | | The owner | That we have **2 telescopic stands at 4 m** |
| `truss-9m` | — (GMSS) | — | A message from GMSS | A **9 m span**, and nothing else. Cross-section, brand, chord count and segmentation all unstated |
| `tower-5m` | — (GMSS) | — | A message from GMSS | **2 towers, max 5.2 m**. Nothing else — the weight is inferred from our ST-132 |
| `mac-2000-performance-ii` | — (factory fixture) | `datasheet` | [Martin MAC 2000 Performance II](https://www.martin.com/en-US/products/mac-2000-performance-ii) | 408 × 490 × 743 mm head straight up, 39.5 kg, 1200 W lamp, 540°/267° pan and tilt |
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

**THE TOWER WAS THE WRONG PRODUCT WITH THE RIGHT NUMBERS, AND THAT IS THE INSTRUCTIVE PART.** It was modelled as a
Global Truss ST-132 on the reasoning that the class is standardised. The real machine is a Varytec Wind Up 85 kg, and
the substitution got the **height and the weight exactly right** — 4 m and 25 kg to the kilogramme — while
overstating the **max load by 15 kg**, 100 against 85. The two figures easy to check agreed, so the one that decides
what may hang from a truss bar never got checked. A truss loaded to 100 kg on a pair of stands rated 85 is an error
that shows up once.

It also missed two figures the class standard does not carry at all: a **minimum** load of 25 kg, because a wind-up
needs weight on it to crank safely, and a **transport length of 1.75 m** against the 4 m this spec models. That
second one is why the packed convoy render showed a mast standing out of a trailer, and it is filed as SPEC-15.

**AND THE TRUSS WEIGHT WAS DERIVED, CONVINCINGLY, AND WRONG.** 10.3 kg came from fitting a line through three
published F33 weights — 6.4 kg at 1.0 m, 8.2 at 1.5, 14.1 at 3.0 — giving 2.55 kg + 3.85 kg/m, which reproduces all
three to within 0.13 kg. At 2 m it said 10.25. The published F33200 figure is **9.3 kg**, so the fit was 10 % out on
the one length nobody had published, and across five segments that is 5 kg. A derivation that matches its own inputs
can still be wrong between them.

The older reasoning, kept because it is what got replaced: `truss-tower-4m` matched the ST-132 on the stated
description, so its **weight and heights were
published**: 25 kg, 4.0 m max, 1.8 m min, 100 kg load. What is estimated is its *shape* — a telescopic mast on
folding outriggers is neither a hexahedron nor a truss, so it is drawn as a 0.203 m column, which is the folded
base size. **The outriggers are not modelled**: unfolded they spread to 1.499 × 1.499 m, which is the footprint
that actually has to be kept clear on a stage. So the footprint these specs report is the mast's — not the working
footprint, and not the folded transport size either. It is the one number in them to be careful with.

`tower-5m` has no datasheet behind it at all. Its 33 kg is our ST-132's published 25 kg at 4 m scaled by
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
| `nuke` | "2 nukes: 59w, 70d, 77h, 58kg each" | 0.590 × 0.770 × 0.700 | 58 |
| `iq-sub` | "6 IQ subs: 53w, 56d, 67h, 40kg each" | 0.530 × 0.670 × 0.560 | 40 |
| `wall-bass` | "2 wall basses: 66w, 100d, 140h, maybe 220kg each" | 0.660 × 1.400 × 1.000 | 220 *(hedged)* |
| `mid-bass` | "USB: 120w, 60d, 50h, ~120kg" | 1.200 × 0.500 × 0.600 | 120 *(hedged)* |
| `turbo-top` | "Tops: 45w, 38d, 71h, 26kg each" | 0.450 × 0.710 × 0.380 | 26 |

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
| 2 nukes | `nuke` | *split out* | "8 turbo subs" is **two** cabinets: 6 IQ subs + 2 nukes. The nukes are the plain-faced boxes on the ground at the foot of each outer column — no grille, recessed oval logo — which `mid-bass` had claimed as its own pair |
| 6 IQ subs | `iq-sub` | `gmss-turbo-sub` (qty 8) | The six grille-fronted boxes, three to an outer column. "Turbo sub" is GMSS's umbrella word for the outer columns' low end, not the name of a box, so no spec keeps that id |
| 2 wall basses | `wall-bass` | `gmss-middle-sub` (qty 3) | Same cabinet, and now with a name from the builder instead of a position. Its "third" cabinet was never a wall bass |
| USB | `mid-bass` (qty **1**) | `mid-bass` (qty 2) | The owner confirmed the horn in the middle *is* the mid bass. It is one cabinet 1.20 m wide, which is what the old set had modelled twice at half the width and once more as a middle sub laid on its side |
| Tops | `turbo-top` | `turbo-top` (unchanged id) | "3 turbo top" was GMSS's own wording, so the id stands. Count still from the first message |

`quantity: 3` on the tops is the one count the owner did **not** restate, and the photo appears to show four — three
on the mid bass plus one on the right-hand outer column. Left at 3, flagged in the spec: it is the next thing to ask.

### What the photograph got right, and what it could never give

Worth keeping, because the reconstruction is exactly the kind of work this repository will do again:

| Device | Photo reconstruction | Stated | Where it went |
|--------|---------------------|--------|---------------|
| `iq-sub` | 0.510 × 0.637 × 0.765, 54 kg | 0.530 × 0.670 × 0.560, 40 kg | Width and height within 35 mm. **Depth wrong by 205 mm** |
| `wall-bass` | 0.595 × 1.020 × 0.850, 81 kg | 0.660 × 1.400 × 1.000, 220 kg | Height short by 380 mm, weight by 139 kg |
| `mid-bass` | 0.595 × 0.425 × 0.595, 45 kg | 1.200 × 0.500 × 0.600, 120 kg | Width short by 605 mm — the old box was **half the cabinet**, because the count was wrong |
| `turbo-top` | 0.391 × 0.935 × 0.552, 56 kg | 0.450 × 0.710 × 0.380, 26 kg | 225 mm too tall, more than twice the weight |
| `nuke` | modelled as part of two other specs | 0.590 × 0.770 × 0.700, 58 kg | A cabinet that was never a cabinet |

Three lessons, and the first two are the ones the old files predicted about themselves:

* **Front-on ratios survive a photograph; depth does not.** The side faces are foreshortened past reading, the old
  files called depth "the least certain of the three axes", and depth is where the IQ sub missed by a third.
* **Even ratios only hold between things the same distance from the camera, and only front-on.** The old reasoning
  trusted the photo for proportions while distrusting it for absolute size, and read the wall bass as 1.4–1.6× a
  turbo sub's height. Stated, it is 1.400 against 0.670 — a factor of **2.09**. The middle row is 1.00 m deep where
  the outer columns are 0.56, so it stands further back and reads shorter than it is. Obliquity does the same to
  width: the IQ sub is 1.26:1 tall to wide, it reads about 1.39:1 in the nearly front-on left-hand column and about
  2.4:1 where the same box is seen at a steep angle on the right. That is why `turbo-top`, which sits entirely
  in the oblique part of the frame, read as a much narrower cabinet than it is.
* **A cabinet of a similar shape is not evidence of size.** `turbo-top` was anchored to the Turbosound TMS-4
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
| `iq-sub` (was `gmss-turbo-sub`) | 0.800 × 0.950 × 0.900 | 0.600 × 0.750 × 0.900 | 0.510 × 0.637 × 0.765 | 0.530 × 0.670 × 0.560 |
| `wall-bass` (was `gmss-middle-sub`) | 0.900 × 1.350 × 1.100 | 0.700 × 1.200 × 1.000 | 0.595 × 1.020 × 0.850 | 0.660 × 1.400 × 1.000 |
| `mid-bass` | 0.900 × 0.600 × 0.850 | 0.700 × 0.500 × 0.700 | 0.595 × 0.425 × 0.595 | 1.200 × 0.500 × 0.600 |
| `turbo-top` | 0.460 × 1.150 × 0.700 | 0.460 × 1.100 × 0.650 | 0.391 × 0.935 × 0.552 | 0.450 × 0.710 × 0.380 |

The 0.85 set scale had brought every GMSS cabinet *below* our own gear one for one. The stated figures scatter:

* `wall-bass` is now the **biggest and heaviest cabinet in this repository** — larger than our Flexy
  (0.591 × 0.763 × 0.964) on all three axes, and 220 kg against the SKRAM's 90.
* `nuke` is about the Flexy's width and height but 264 mm shallower and 27 kg lighter.
* `iq-sub` is smaller and lighter than anything we own, and there are six of them.
* `turbo-top` is a much smaller box than our `tecnare-m2122` (0.50 × 0.96 × 0.52, 68 kg) — 26 kg against 68.

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

### The transporters: both documented, neither measured inside

`opel-movano-l4h3` and `fiat-ducato-250-l3h2` are the devices here that are not gear. **Both sets of masses are now
read off a registration document and both load bays are still manufacturer figures.** Worth reading before either is
planned against, because **a wrong cabinet weight makes a bad render and a wrong payload makes an overloaded van** —
a fine, a liability question after an accident and a refused insurance claim.

| Device | Reference | Source | What came from it |
|--------|-----------|--------|-------------------|
| `opel-movano-l4h3` | `datasheet` | **Zulassungsbescheinigung Teil I and Teil II**, both photographed by the owner on 2026-08-16 | Length 6848 (18), width 2070 (19), height 2792–2808 (20), mass in service 2476 kg (G), permitted gross 3500 kg (F.2 and F.1), axle loads 1850/2300 (7.1/7.2), towing 2500/750 (O.1/O.2), 3 seats (S.1), first registered 25.04.2016 (B), colour WEISS (R). **Payload 1024 kg is derived, `F.2 − G`, and stored nowhere** |
| | `datasheet` | Manufacturer body figures for the Renault Master / Opel Movano **L4H3 rear-wheel-drive** shell | Load bay 4383 × 1765 × 2048 mm, 1380 mm between the wheel arches, 15.8 m³. **Estimated**, and it describes a bare shell |
| `fiat-ducato-250-l3h2` | `measured` | **A weighbridge**, by the vehicle's own owner on 2026-08-16 | **2500 kg with a full tank and a driver aboard**, which is already the mass-in-service definition. This is the only weight in the entire library that has been on a scale, and it is **365 kg heavier than the registration document** |
| | `measured` | **Austrian Zulassungsschein**, photographed on 2026-08-16 | Fiat Ducato, type 250/DMMFC/EYL1 (D1/D3/D2), van body (A8), N1 Gruppe III (J), Eigengewicht 2060 kg (G), permitted gross 3500 kg (F2, and F1 agrees), **Nutzlast 1365 kg (A10)**, axle loads 2100/2400 (N), towing 3000/750 (O1/O2), first registered 11.06.2014 (B), colour gelb (R), 2287 ccm / 96 kW diesel EURO 5b, 215/75R16C |
| | — | Manufacturer body figures for the Ducato 250 **L3H2** shell | Outer 5998 × 2050 × 2522 mm, load bay 3705 × 1870 × 1932 mm, 1422 mm between the arches, 13 m³. **Estimated** — an Austrian Zulassungsschein carries no dimensions at all, so even the outer box is a catalogue figure here |

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
* **The lower of Sepp's two possible roofs.** L3H2 and L3H3 differ in exactly one dimension, 1932 mm against
  2168 mm, and the papers settle neither. An estimate against a legal limit is rounded in the **safe** direction: a
  bay estimated small makes the packer refuse a load that would have fitted, which costs a second trip, where the
  other direction costs a prosecution. One look at the roof removes 236 mm of doubt.

**A REGISTRATION DOCUMENT IS AUTHORITATIVE ABOUT WHAT A VEHICLE MAY WEIGH AND MERELY HISTORICAL ABOUT WHAT IT
DOES**, and Sepp's van is the worked example. Its payload has been three numbers in one day:

| source | payload | how it was arrived at |
|---|---|---|
| estimate | 1200 kg | mass in service guessed at 2300, deliberately heavy for an unseen fit-out |
| Zulassungsschein, field A10 | 1365 kg | Eigengewicht 2060 + 75 kg driver, against a 3500 kg permitted gross |
| **weighbridge** | **1000 kg** | **2500 kg measured, full tank and driver aboard** |

The estimate was pessimistic, the document was optimistic, and only the scale had been near the vehicle. The 365 kg
the paper is missing is about 75 kg of diesel in a full 90 litre tank plus a fit-out — shelving, a bulkhead, a ply
floor — added *after* type approval, which no registration field has ever seen. **So a payload needs both sources:
the legal ceiling from the paper, which no scale can supply, and the actual mass from the scale, which the paper
cannot.**

**The driver still has to be reconciled between the two documents.** Austrian **Eigengewicht** is the vehicle
without one; the German field **G** on the Movano is the *mass in service* and includes 75 kg by EU definition. The
weighbridge figure had the driver aboard, so it needs no adjustment — but the 2135 kg it replaced was 2060 + 75 for
exactly that reason, and stored literally the 2060 would have derived 1440 kg against the document's own 1365.

**The same question now hangs over the Movano.** Its 1024 kg comes off field G of its own Zulassungsbescheinigung,
which is the same class of figure that has just been shown 365 kg light. Until it is weighed, the fleet's total
payload is one measurement plus one assumption, and the assumption is the optimistic kind.
  L3H2 and L3H3 differ only in height, 1932 against 2168, so that assumption costs 236 mm and nothing else.

**What replaces all of this:** a tape measure inside both vans — length at the floor, width between the walls and
between the arches, height under the roof and through the rear door aperture, and whether anything is bolted in that
never comes out — plus Sepp's Zulassungsbescheinigung, fields F.2 and G. One photograph of that document replaces
every number in his spec.

**Nothing identifying is recorded.** The VIN, the registration plate and the owner's home address are all on the
Movano's papers and none of them is a packing input.

### PSL: a rental company publishes what a self-build crew cannot

PSL is **Pro Sound & Light** in Paunzhausen, a rental firm rather than a crew, and that single fact makes their
ten specs the best-sourced borrowed gear in this repository. Their own site prints the full technical data for
every cabinet they hire out — dimensions, weight, drivers, power, impedance, sensitivity, coverage — so all ten
are `provenance: datasheet` on both axes.

**That is a different kind of source from a manufacturer's datasheet and it is worth naming.** The Tecnare
figures come from Tecnare; the PSL figures come from the *owner of the cabinets*, republishing what the
manufacturer told them. For a factory product the two agree, and where PSL reproduce HK's own data table under
their sales copy it is visibly the same document. The place it stops being equivalent is the finish: PSL's
Concert Audio cabinets are **white** and the catalogue's own product is black, so the colour comes from our
drawing and their "Mod" package descriptions rather than from the technical data.

**What a rental catalogue cannot tell you is how many there are**, and that is the whole of PSL's request list.
A package that says "vier Horntopteile, sechs Hybrid-Horn Subwoofer" states a *configuration*, not an
inventory, and Bundle 3 proves the packages overlap by pairing subwoofers from two other bundles. Four of the
ten specs are in no package at all and carry `quantity: 1`, which is the floor the validator allows rather
than a count.

**Two of their pages disagree with each other, in a way worth keeping.** The equipment page calls the double-18
sub a MkIII and the package pages call it a MK2, with every published figure identical. And the HK top's sales
copy says 60° × 40° where the data table under it says 60–90° asymmetric × 55°. The spec follows the table on
the grounds that a measurement under an EN 60268-5 note beats a headline, and 40 against 55 vertical is a real
disagreement rather than rounding.

**One number is missing from the geometry rather than from the notes.** The ESX's published depth and the
TP118's published width are both "ohne Rollen", and both cabinets have castors. A sub on its wheels is the one
at the bottom of the stack, so the height a rig is built from is about 100 mm short — and because the ESX is
stacked both upright and rolled, there is no single axis to correct.

### Innschleife: a scaled drawing, which is a photograph with one number added

Innschleife publish nothing and searching for them returns nothing, so their five specs rest entirely on two
setup drawings in our own Drive. Each embeds one photograph per cabinet type, and the owner states the scale
outright: **one pixel is one centimetre.** So the embedded image's pixel size *is* the cabinet's front size.

**That one number is what separates this from the GMSS photo reconstruction, and it separates it only on two
axes.** The GMSS work had a site photograph with no scale reference in it and had to derive one — three times,
each differently, before the builder's own figures arrived and replaced all of it. Here the scale is given, so
width and height are read rather than inferred. Depth and weight are in exactly the same position as they were
for GMSS, and the lesson recorded below applies unchanged: **front-on ratios survive a photograph; depth does
not.** The IQ sub's depth was wrong by 205 mm, a third of the cabinet, while its width and height came out
within 35 mm.

**So the five depths are analogies to cabinets that do have a source**, each named in the table above, and the
five weights are the modelled volume at **200 kg/m³** — this library's own central density, with twenty
cabinets running 139 to 333 kg/m³ and a mean of 212. The method is reproducible and its error bar is ±50 %,
which on 1569.6 kg of Innschleife gear is ±785 kg — though the two tops are now datasheet figures and out of that error bar.

**A SECOND SOURCE ARRIVED ON 2026-09-02, AND IT IS BETTER THAN THE DRAWINGS ON EVERYTHING IT TOUCHES — AND IT
TOOK TWO PASSES TO READ.**
Innschleife said what they are bringing to the next event and named the cabinets doing it: four "SBH 18", four
"WSX 18", four "die blauen Kicker 15" and either the small tops or the two big "THL4". Three sub types at four
each is exactly what the drawings had already produced, so the mapping is the only one the numbers allow, and
four of the five ids are those names now. It is second-hand and is going back to them for confirmation, and one
piece of evidence pulls against it: the "blauen Kicker" photograph averages #1D201C, which is not blue. **A
photograph's average is not a paint colour** — our own Flexy averages #C7C7C7 where the cabinet is black — so
this is a case of the method's known weakness rather than a contradiction, and the spec's colour is the thing
most likely to be wrong.

**READ BACK TO THEM ON 2026-09-03, TWO OF THE THREE SUB NAMES WERE WRONG.** The 0.600 × 0.600 box is a small
Electro-Voice wbin and *is* the blue kicker; the SBH is a 1.20 × 0.55 × 0.80 horn that appears in no drawing at
all and now has a spec of its own; and the 0.950 × 0.570 cabinet is left with no name and no place in the
statement. **A mapping the numbers permit is not a mapping anybody confirmed** — three types at four each made the
fit look forced when it was only consistent. The count lesson below and this one are the same lesson.

It also settled the one question the drawings could not answer either way, which is worth more than the names:
**the 0.500 × 1.140 m purple box is a real cabinet.** They are bringing two. A spec for a possible draughting
error would have been worse than the question; a cabinet somebody plans to load into a van is not one.

Two facts from the drawings are worth more than the numbers:

* **Two different symbols of the same size mean two different cabinets.** Both drawings place six Achenbach 18
  and four 0.600 × 0.600 m boxes using two different photographs, and a draughtsman drawing one cabinet twice
  reuses the symbol. Without that observation the two would be indistinguishable — same front, same estimated
  weight, and only the `owner` field to tell them apart.
* **The purple livery is Turbosound's and it identifies no model.** A 0.500 × 1.140 m purple sibling appears in
  the first two revisions and in none after, and its size matches the **TMS-4's published 502 × 1143 mm to the
  millimetre** — the same cabinet this repository once used as a size anchor for `turbo-top` and was
  contradicted about. The specced 0.430 × 0.870 m top matches no Turbosound whose figures could be found; the
  TMS-3 is 1019 × 844 × 578 mm and 134 kg. **Not writing `clone_of: Turbosound` on the strength of a colour is
  the same call that was right for the GMSS top**, and letting a same-class cabinet set the size anyway is the
  part that was wrong there. Neither is done here.

**Where the counts come from.** Four, four, four and two, which is what the drawings draw — **and what the owner
confirmed, cabinet by cabinet, on 2026-09-02.** A drawing shows what came to one gig, and `turbo-top` is the
standing example of what that costs: a stated three against a photograph showing four, still unresolved. Here
the two sources agree, which is the first time that has happened for a borrowed system. The TMS-4's two is the
owner's figure alone, since only the earliest drawing revisions place it.

**Four corrections ran the other way, and the sequence is the most useful thing in this section.**

1. It said "alle 4 kleinen Tops", the small top's count was raised to four on the principle that a spoken count
   outranks a drawing, and the answer was "small tops nur 2x". Cost: one number.
2. Reading the sub mapping back moved two of three names. Cost: two renames of ids that generated scenes refer to.
3. **That second correction was itself wrong**, and it took until 2026-09-05 to find out. `kicker-15` had been on
   the 0.950 × 0.570 cabinet on 02.09, moved to the 0.600 × 0.600 on 03.09, and moved back on 05.09.
4. Both purple tops were named one box over, and no amount of asking would have caught it.

**So "read a name back before writing it down" is necessary and it is not sufficient.** Reading it back is exactly
what produced correction 2, and correction 2 was the error — the confirmation came back about the wrong cabinet,
because the question and the answer both pointed with words rather than with numbers. What finally settled the
subs was an unprompted sentence naming a colour, a manufacturer and a size *together*: "nicht die 4 schwarzen JBL
60x60, stattdessen die blaue Electro-Voice 95x57". Three coordinates fix a cabinet where one name does not.

**And what settled the tops was not asking at all.** Innschleife had already given their best answer twice, and
both times it was wrong. Holding the drawn fronts against published figures identified both: the big one is the
TMS-4 to 2 and 3 mm, the small one the TMS-2 to 2 and 5 mm, and the THL-4 they named fits neither. **A
measurement that matches a published measurement is an identification** — the `turbo-top` precedent above warns
against the opposite move, letting a *name* set an unmeasured size, and the two are not the same thing. The
payoff was four estimates becoming datasheet figures, every one of them optimistic: 358 mm of depth and 31.8 kg
that the rigs did not know about.

**What is brought is not what is owned**, and the difference now has a home: [`rosters/`](../rosters) holds what
a system brings to one event, as counts that override the specs for one run. The specs keep saying what exists.
Every gap in both systems is listed in [requests.md](requests.md).

### Not owned

`PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` also carries SKHORN, GHORN and OTHORN with
full dimensions and weights. Those were **evaluated, not bought** — only SKRAM was built. They are
deliberately absent from `specs/`; if one is ever acquired, the spreadsheet already has its numbers.

### The Audio Routing sheet, and the four places it contradicts this file

`Audio Routing.xlsx`, read 2026-09-08. It is **not in the SdWa5 shared drive** but in the rclone
account's My Drive, so the `SdWa5:` remote cannot see it without `--drive-team-drive ""`, because that
remote is scoped to `team_drive 0AFDifygC0zQZUk9PVA`. Beside it sit `Amp_GainSelector.csv`,
`drivers.csv` and **four files all named `AmpLimiterCalc.csv`**. Those four are not redundant copies: re-measured
2026-09-08 they carry three distinct sizes, 7700, 7718 and 7432 bytes with the last appearing twice at the same
timestamp, so three of them are hand-kept versions. Which one the figures above came from is therefore not
determined, and an import has to pick a version deliberately rather than take whatever the name resolves to.

It is a **working sheet, not a datasheet.** Someone is calculating limiter settings in it, and the
numbers move while they do. So where it disagrees with what is recorded above, **neither side is
promoted**: both claims are written down with their source, and settling them is a front-panel or
tape-measure job. Two figures agree exactly and are worth stating for that reason: `Top 15 2-way` at
550 W and `Sub FH` at 1800 W match `Hardware/Hardware Overview.xlsx`.

| Device | This file says | The sheet says | Status |
|--------|----------------|----------------|--------|
| `achenbach-18` driver | B&C 18TBW100, from `Hardware/Hardware Overview.xlsx` | RCF L18P300 | **Unresolved.** Two different 18″ drivers in the same cabinet. One of the two records is stale, and only opening the box settles it |
| `achenbach-18` passband | 35–1000 Hz, from `Hardware/Hardware Overview.xlsx` | 35–1500 Hz | **Unresolved**, and it matters: the upper bound decides what the cabinet is asked to reproduce |
| `tecnare-m2122` drivers | Celestion 12″, RCF ND650, B&C DE25 — the re-fitted complement | 2× 12NMB1000 LF, plus 2× D280Ti-B and 1× D4400Ti-Nd HF | **Unresolved.** The sheet also splits the cabinet into an LF and an HF channel at ~6.5 kHz, which this file does not record at all |
| `tip10000q` and `mm14k` gain | not recorded here | The sheet's live `Amp_GainSelector` has all four amps at 32 or 34 dB. `Amp_GainSelector.csv`, in the same Drive folder, recommends **41 dB** for the TIP10000q and **44 dB** for the MM14K, and says in its own notes that a lower setting "cannot reach BR RMS limit" | **The sheet disagrees with the CSV beside it** about the one thing the CSV exists to decide. A gain setting is a DIP switch, so this is readable off the amplifier |

**What the sheet adds rather than contradicts** is the electrical half that no spec here carries. RMS
wattage, nominal impedance and a passband for six speaker groups, which is `SPEC-13`; and the
amplifier complement, which half-answers `SPEC-8` by naming **GISEN M60D** for what was recorded as
"gisen md60" and matched no product. Importing any of it is `SIG-1`, and each figure needs its own
`provenance` like every other number in this file.

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
| PSL's whole speaker inventory | https://pro-sound-light.de/equipment/tontechnik/ | 2026-09-02 |
| PSL's packages, for the counts | https://pro-sound-light.de/equipment/komplettpakete/ | 2026-09-02 |
| Eighteen Sound 15″ 2 Ways Kit | `18sound/18sound_15 2ways.pdf` in Drive (© Eighteen Sound 2013) | 2026-07-30 |
