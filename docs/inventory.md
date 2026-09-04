# Inventory

The gear list lives in the **SdWa5** Shared Drive (Geteilte Ablage), under `Hardware/`. It has been read and turned into
the specs in [`specs/`](../specs) — this page records where it came from and what is still missing.

## What is in Drive

| Path                                                                                                    | Contents                                                                                                                                                                                                                     |
|---------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Hardware/Hardware Overview.xlsx`                                                                       | The inventory proper: enclosure models, brands, **quantities owned** ("Anzahl in Besitz"), frequency ranges, power handling, per-channel driver counts, and every driver with model, brand, quantity, impedance and diameter |
| `Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` | Dimensions, empty/driver/total weights and RMS power for Flexy, SKRAM, SKHORN and GHORN, with efficiency-per-kg and per-m³ columns                                                                                           |
| `…/<design>/`                                                                                           | Per-design folders with build plans, cut lists, CAD (`.FCStd`, `.obj`), DWG/DXF, datasheets and photos — see [sources.md](sources.md)                                                                                        |
| `…/setups/`                                                                                             | ~13 event stack layouts as SVG/PNG, one folder per gig (Staudham, Kraut, Unite Parade, Ballonfabrik, NND, Poltek, Sirius, Houbatik, WHG, Rotek, Mark, Scheiterhaufen). **These are a source, not just a record**: each embeds one photograph per cabinet at 1 px = 1 cm, which is where PSL's and every Innschleife figure came from |
| `Hardware/Amps _ Verstärker _ DSP/`                                                                     | Gisen MM14K (settings + photos), Behringer Europower 4000, t.amp Proline 3000                                                                                                                                                |

## What is in the specs

**Five systems, 38 devices, 101 units, 6740.4 kg.** `bin/console catalog` is the authority and
[catalog.md](catalog.md) is its written form; this table is the shape of the library rather than its contents.

| System | Owner | Cabinets | kg | Provenance | Where the numbers came from |
|--------|-------|---------:|---:|-----------|------------------------------|
| ours | `sdwa5` | 17 speakers | 1404 | `plans` / `cad` / `datasheet` | CAD, cut lists and one manufacturer's datasheet, plus racks, truss, stands and a van |
| Sepp's | `sepp` | 8 speakers | 382 | `plans`, weights `estimated` | The 18sound kit drawings and the Achenbach FreeCAD, plus a van, a trailer and the generator |
| GMSS | `gmss` | 14 speakers | 994 | `estimated` | **The builder's own figures**, in centimetres, replacing a photo reconstruction. Plus truss, towers and four MAC 2000 |
| PSL | `psl` | 22 speakers | 1281 | `datasheet` | **Pro Sound & Light publish their whole rental inventory**, so all ten specs are sourced on both axes |
| Innschleife | `innschleife` | 20 speakers | 1506 | `estimated` | Two setup drawings at 1 px = 1 cm, plus what Innschleife said on 2026-09-02 and corrected on 2026-09-03. **Front width and height only** from the drawings, with depth and weight derived. The SBH is the exception and the best-sourced cabinet of the six: all three edges are the builders' own figures |

Read [sources.md](sources.md) for the row-by-row provenance and [requests.md](requests.md) for what is missing and
who to ask. Two things about this table are worth knowing before planning anything against it:

* **Only ours and Sepp's travel in our vans.** GMSS, PSL and Innschleife arrive in their own, which is why
  `load:plan` and the fleet test look at those two owners alone. The 3239 kg of borrowed gear in the total above is
  not cargo.
* **`sdwa5` + `sepp` is what a bare `scene:stack` builds**, one combined rig, stated by the owner. Every other
  inventory is one `--owner` away and lands in its own folder under `scenes/generated/`.

## What is still missing

1. **Every weight and dimension is still un-measured** — 0 of 36 dimensions and 1 of 36 weights, and the one is a
   van on a weighbridge. Every spec describes a design, a datasheet or a drawing rather than the object we have. See
   [measuring.md](measuring.md).
2. **Three estimated weights of our own and Sepp's** — `eighteensound-2way-15` (41 kg), `achenbach-18` (50 kg) and
   the self-built Tecnare (68 kg, the factory figure as a placeholder). Not in Drive, not published anywhere: all
   1166 files in the Shared Drive were searched. **A hanging scale is the only source.**
3. **Twelve figures across the two new systems** — every Innschleife depth and weight, and every PSL quantity.
   Listed device by device in [requests.md](requests.md), which exists for exactly this.
4. **Amps and DSP** are documented in Drive and modelled only as a rack's total weight. `SPEC-9` records that this
   is deliberate: five boxes nobody can see inside a closed rack.

## Drive access

`rclone` is the org's Drive convention — the VPS backup uses the same tool (see
[`sdwa5-vps/docs/backup.md`](https://github.com/bestcodename/sdwa5-vps/blob/main/docs/backup.md)).

A read-only remote named `SdWa5` is configured on Stefan's workstation:

```ini
[SdWa5]
type = drive
scope = drive.readonly
team_drive = 0AFDifygC0zQZUk9PVA    # the SdWa5 Shared Drive
```

Created with `rclone config` — note it needs a real terminal, since it is an interactive menu. Useful calls:

```bash
rclone lsd SdWa5:                                   # top-level folders
rclone lsf SdWa5:Hardware -R                        # everything under Hardware
rclone copy "SdWa5:Hardware/Hardware Overview.xlsx" .
rclone cat --drive-export-formats csv SdWa5:path/to/sheet   # Google Sheets as CSV
```

The OAuth token lives in `~/.config/rclone/rclone.conf` and **is not in this repository**. For a headless or shared
setup, prefer a service account instead: enable the Drive API, create a service account and JSON key, add its address as
**Viewer** on the Shared Drive, and point the remote at the key with `service_account_file`. That avoids a personal
token entirely and is revocable on its own.

## Importing

`inventory:import` is not implemented — the first import was done by hand, because the source is several spreadsheets
and CAD files rather than one list, and each number needed a provenance decision. It stays on [`../TODO.md`](../TODO.md)
for the next time the gear list grows.

## Related data elsewhere

* **Dolibarr** (`https://erp.sdwa5.org`) has product/stock modules but is used for accounting; no gear inventory is
  recorded there.
* The merch catalogue in
  [`sdwa5-vps/docs/shopware/merch.md`](https://github.com/bestcodename/sdwa5-vps/blob/main/docs/shopware/merch.md)
  is shop products, not equipment — its "Mini Speaker" is a €5 novelty item.
