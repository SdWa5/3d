# The favourite next-event-light rig with our stack swapped for a solo variant from scenes/_solo/.
#
#   python3 tools/combined-variants.py achenbach-flipped [more variants]
#
# Writes scenes/_solo/fav-ours-<variant>.yaml from scenes/_solo/solo-ours-<variant>.yaml. Run it from the repository
# root.
# Our tops aim at our own focus, 10 m and 2 m out from our front face (y -0.482) on our centre line, as the solved
# stack did, rather than at the scene's rig centre.
import re
import sys

FAV = "scenes/generated/next-event-light/stacked-1-systems-apart-pyramid-stated--alternate-stereo-low-----possible.yaml"
with open(FAV) as f:
    fav = f.read()
start = fav.index("  # ours, 19 cabinets in 3 rows\n")
end = fav.index("  # psl, ")

# A variant narrower than the solved stack moves toward PSL by the width it lost, so every gap between stacks stays the
# event's 0.24 m clearance, and the backdrop moves half that to stay centred on the rig.
SHIFT = {"flipped-turned": 0.287}


def shifted(text, dx, pattern):
    return re.sub(pattern, lambda m: f"{m.group(1)}{float(m.group(2)) + dx:.4f}", text)


for variant in sys.argv[1:]:
    with open(f"scenes/_solo/solo-ours-{variant}.yaml") as f:
        solo = f.read()
    body = solo[solo.index("placements:\n") + len("placements:\n"):]
    body = re.sub(r"(id|on): ", lambda m: m.group(0) + "ours-", body)
    body = body.replace("    aim: far\n", "    aim_at: [-4.662, -10.482, 1.8]\n")
    body = body.replace("    aim: near\n", "    aim_at: [-4.662, -2.482, 1.8]\n")
    dx = SHIFT.get(variant, 0.0)
    body = shifted(body, dx, r"(at: \[|aim_at: \[)(-?[0-9.]+)")
    rest = shifted(fav[end:], dx / 2, r"(?m)(^  - id: backdrop-[^\n]*\n(?:    [^\n]*\n)*?    at: \[ ?)(-?[0-9.]+)")
    sid = f"fav-ours-{variant}"
    header = f"  # ours, the solo-ours-{variant} variant written out cabinet by cabinet\n"
    text = fav[:start] + header + body + "\n" + rest
    text = re.sub(r"^id: .*$", f"id: {sid}", text, count=1, flags=re.M)
    name = f'name: "Favourite light next-event rig, ours as solo-ours-{variant}"'
    text = re.sub(r"^name: .*$", name, text, count=1, flags=re.M)
    text = "# The favourite scene with our stack replaced, written by tools/combined-variants.py.\n" + text
    with open(f"scenes/_solo/{sid}.yaml", "w") as f:
        f.write(text)
    print("wrote", sid)
