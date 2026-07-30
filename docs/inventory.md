# Inventory

The list of gear SdWa5 actually owns lives in the **SdWa5 Shared Drive** (Geteilte Ablage), not in
this repo. Until it is imported, the specs under [`specs/`](../specs) are two worked examples
estimated off a photo — placeholders, not an inventory.

## What is needed from the list

In order of value:

1. **Which original each cabinet clones** — brand *and* model. This is the unlock: with those names,
   dimensions, weight, driver complement and coverage angles can be filled in from the originals'
   datasheets, which is faster *and* more accurate than measuring, and gets the whole library to
   `provenance: datasheet` in one pass. Measuring then becomes a per-box correction job
   (see [measuring.md](measuring.md)) instead of the only way in.
2. **Quantities** — how many of each we own. Drives the weight and volume totals in `catalog`.
3. Anything already recorded about dimensions or weight.

A cabinet whose original nobody has written down yet is still recorded as
`build: clone` with `clone_of.manufacturer: unknown` — that keeps the gap visible instead of
pretending it is an own design. `catalog` shows those as `unknown`.

## Getting at the Drive

`rclone` is the org's Drive convention already — the VPS backup uses a remote called `SdWa5`
(see [`sdwa5-vps/docs/backup.md`](https://github.com/bestcodename/sdwa5-vps/blob/main/docs/backup.md)).

### Preferred: a read-only service account

No browser OAuth, independently revocable, and it never touches a personal account's tokens.

1. In a Google Cloud project on the `sdwa5.org` Workspace: enable the **Drive API**, create a
   **service account**, create a **JSON key**.
2. In Drive, add the service account's e-mail as **Viewer** on the Shared Drive `SdWa5` — or on just
   the folder holding the gear list.
3. Store the key **outside this repo**: `~/.config/rclone/sdwa5-drive-sa.json`, `chmod 600`, with a
   copy in Vaultwarden. It must never be committed.
4. Configure the remote:

   ```ini
   [sdwa5-drive]
   type = drive
   scope = drive.readonly
   service_account_file = /home/<user>/.config/rclone/sdwa5-drive-sa.json
   team_drive = <shared drive id>
   ```

5. Check it:

   ```bash
   rclone lsd sdwa5-drive:
   rclone ls sdwa5-drive: | grep -iE 'material|inventar|technik|equipment|liste'
   ```

### Fallback: reuse the VPS remote

The VPS already has a working `SdWa5` OAuth remote at `/opt/docker/rclone-config/rclone.conf`, which
could be copied to a workstation. Faster, but it is a read-write backup token living somewhere it
does not need to — the service account is the better hygiene.

Either way, the credential stays out of this repository. Nothing here reads Drive on its own.

## Importing

Not implemented yet — it is the first item in [`../TODO.md`](../TODO.md) that depends on access.
The intended shape:

```bash
ddev exec bin/console inventory:import ~/Downloads/gear-list.csv
```

It should create or update one spec per row, fill `clone_of`, `quantity` and whatever dimensions the
list already carries, set `provenance` according to where each number came from, and leave every
field it has no source for absent rather than guessed.

## Related data elsewhere

* **Dolibarr** (`https://erp.sdwa5.org`) has product/stock modules but is used for accounting; no
  gear inventory is recorded there today.
* The merch catalogue in
  [`sdwa5-vps/docs/shopware/merch.md`](https://github.com/bestcodename/sdwa5-vps/blob/main/docs/shopware/merch.md)
  is shop products, not equipment — its "Mini Speaker" is a €5 novelty item.
