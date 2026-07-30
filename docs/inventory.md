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

| Device | Owner | Qty |
|--------|-------|-----|
| Flexy Folded Horn Hybrid | sdwa5 | 14 |
| SKRAM | sdwa5 | 2 |
| Tecnare M2122 | sdwa5 | 2 |
| Eighteensound 2-Way 15″ | sepp | 2 |
| Achenbach 18 | sepp | 4 |

SKHORN, GHORN and OTHORN appear in the comparison spreadsheet but were evaluated, not bought, so
they are not in the library.

## What is still missing

1. **Every weight and dimension is still un-measured** — they describe designs and datasheets, not
   the cabinets we own. `catalog` lists them all as un-measured until somebody checks. See
   [measuring.md](measuring.md).
2. **Two estimated weights** — `eighteensound-2way-15` (30 kg) and `achenbach-18` (50 kg) are not in
   any source at all. Highest-value thing to weigh.
3. **Amps, DSP, racks, truss, stands** — documented in Drive, not modelled. The schema already has
   `rack`, `truss` and `stand` categories; the geometry builder needs shapes for them.
4. **Whether the Tecnare is factory or a clone** — recorded as `build: original` because the
   Hardware Overview names Tecnare as the brand. If it is self-built from L2122LT dimensions, flip
   `build` to `clone` and add a `clone_of` block.

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
