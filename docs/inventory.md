# Inventory

The gear list lives in the **SdWa5** Shared Drive (Geteilte Ablage), under `Hardware/`. It has been
read and turned into the specs in [`specs/`](../specs) — this page records where it came from and
what is still missing.

## What is in Drive

| Path | Contents |
|------|----------|
| `Hardware/Hardware Overview.xlsx` | The inventory proper: enclosure models, brands, **quantities owned** ("Anzahl in Besitz"), frequency ranges, power handling, per-channel driver counts, and every driver with model, brand, quantity, impedance and diameter |
| `Hardware/Speaker Enclosures _ Lautsprecher-Gehäuse/PA-Gehaeuse_Vergleich_und_Effizienzberechnung.xlsx` | Dimensions, empty/driver/total weights and RMS power for Flexy, SKRAM, SKHORN and GHORN, with efficiency-per-kg and per-m³ columns |
| `…/<design>/` | Per-design folders with build plans, cut lists, CAD (`.FCStd`, `.obj`), DWG/DXF, datasheets and photos — see [sources.md](sources.md) |
| `…/setups/` | ~13 event stack layouts as SVG/PNG (PSL, Staudham, Kraut, Unite Parade, Ballonfabrik, NND, Poltek, Sirius, Houbatik, WHG, Rotek, Mark, Scheiterhaufen) |
| `Hardware/Amps _ Verstärker _ DSP/` | Gisen MM14K (settings + photos), Behringer Europower 4000, t.amp Proline 3000 |

## What is in the specs

All five enclosures listed as owned, with dimensions from CAD, cut lists, published plans or a
datasheet — see [sources.md](sources.md) for which is which:

| Device | Owner | Qty | Note |
|--------|-------|-----|------|
| Flexy Folded Horn Hybrid | sdwa5 | 14 | |
| SKRAM | sdwa5 | 2 | |
| Tecnare M2122 | sdwa5 | 2 | the two factory cabinets |
| Tecnare M2122 (clone) | sdwa5 | 1 | self-built third top, separate spec |
| Eighteensound 2-Way 15″ | sepp | 2 | |
| Achenbach 18 | sepp | 4 | |

25 cabinets, 1834 kg, 8.96 m³ in total. Note that `Hardware Overview.xlsx` lists 2 Tecnare, counting
only the factory pair — the third, self-built one is not in the spreadsheet.

SKHORN, GHORN and OTHORN appear in the comparison spreadsheet but were evaluated, not bought, so
they are not in the library.

## What is still missing

1. **Every weight and dimension is still un-measured** — they describe designs and datasheets, not
   the cabinets we own. `catalog` lists them all as un-measured until somebody checks. See
   [measuring.md](measuring.md).
2. **Three estimated weights** — `eighteensound-2way-15` (41 kg), `achenbach-18` (50 kg) and
   `tecnare-m2122-clone` (68 kg, the factory figure as placeholder). Not in Drive, not published
   anywhere: all 1166 files in the Shared Drive were searched, and Eighteen Sound publishes no
   finished-cabinet weight for a DIY kit. **A hanging scale is the only source.** Possibly the
   two-way tops were weighed at some point — if a number exists, it is not written down.
3. **Amps, DSP, racks, truss, stands** — documented in Drive, not modelled. The schema already has
   `rack`, `truss` and `stand` categories; the geometry builder needs shapes for them.

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

Created with `rclone config` — note it needs a real terminal, since it is an interactive menu.
Useful calls:

```bash
rclone lsd SdWa5:                                   # top-level folders
rclone lsf SdWa5:Hardware -R                        # everything under Hardware
rclone copy "SdWa5:Hardware/Hardware Overview.xlsx" .
rclone cat --drive-export-formats csv SdWa5:path/to/sheet   # Google Sheets as CSV
```

The OAuth token lives in `~/.config/rclone/rclone.conf` and **is not in this repository**. For a
headless or shared setup, prefer a service account instead: enable the Drive API, create a service
account and JSON key, add its address as **Viewer** on the Shared Drive, and point the remote at the
key with `service_account_file`. That avoids a personal token entirely and is revocable on its own.

## Importing

`inventory:import` is not implemented — the first import was done by hand, because the source is
several spreadsheets and CAD files rather than one list, and each number needed a provenance
decision. It stays on [`../TODO.md`](../TODO.md) for the next time the gear list grows.

## Related data elsewhere

* **Dolibarr** (`https://erp.sdwa5.org`) has product/stock modules but is used for accounting; no
  gear inventory is recorded there.
* The merch catalogue in
  [`sdwa5-vps/docs/shopware/merch.md`](https://github.com/bestcodename/sdwa5-vps/blob/main/docs/shopware/merch.md)
  is shop products, not equipment — its "Mini Speaker" is a €5 novelty item.
