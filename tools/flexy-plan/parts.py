"""Parts data of the Flexy build plan: the parts list, the cutlistoptimizer.com CSV and the stock estimate.

Standard library only, so CI can test it without the PDF dependencies of build.py. `parts.json` is the one source.
Every part's blank is the drawing's size, which equals the oriented bounding box of its body in
`meshes/flexy-subwoofer-v28.obj` (named in `body`, measured on 2026-10-17).
"""

import csv
import io
import json
import math
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
DATA = HERE / "parts.json"

# cutlistoptimizer.com splits each record on plain commas, so no field may contain a comma or a quote. The column
# order follows the Fusion 360 ExportCutlist add-in's CutlistOptimizerFormat.
CSV_HEADER = ["Length", "Width", "Qty", "Material", "Label", "Enabled"]


def load(path=DATA):
    return json.loads(pathlib.Path(path).read_text(encoding="utf-8"))


def blanks(data):
    """The parts that are cut as a blank of their own, with each size rounded up to whole mm."""
    return [
        {**part, "blank": (math.ceil(part["length"]), math.ceil(part["width"]))}
        for part in data["parts"]
        if "from_sheet" not in part
    ]


def label(part):
    return f"FLEXY-{part['sheet']:02d} {part['en']}"


def csv_rows(data):
    rows = []
    for part in blanks(data):
        length, width = part["blank"]
        enabled = "false" if part.get("optional") else "true"
        rows.append([str(length), str(width), str(part["qty"]), data["material"]["csv"], label(part), enabled])
    return rows


def csv_text(data):
    out = io.StringIO()
    writer = csv.writer(out, lineterminator="\n", quoting=csv.QUOTE_NONE)
    writer.writerow(CSV_HEADER)
    writer.writerows(csv_rows(data))
    return out.getvalue()


def number(value, lang):
    """A dimension as the drawings print it: no trailing zeros, decimal comma in German."""
    text = f"{value:.2f}".rstrip("0").rstrip(".")
    return text.replace(".", ",") if lang == "de" else text


def area_m2(data, optional=False):
    """Total blank area in square metres, without or with the optional parts."""
    return sum(
        part["blank"][0] * part["blank"][1] * part["qty"] / 1e6
        for part in blanks(data)
        if optional or not part.get("optional")
    )


def minimum_sheets(data, stock, optional=False):
    """A lower bound on the stock sheets needed: total blank area over sheet area, ignoring kerf and nesting."""
    return math.ceil(area_m2(data, optional) / (stock[0] * stock[1] / 1e6))


if __name__ == "__main__":
    print(csv_text(load()), end="")
