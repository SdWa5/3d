"""Wind-up stand geometry, for `shape: mast`: legs, a telescoping mast, a winch and a truss adapter.

Drawn from the Varytec Wind Up 85 kg as Thomann photographs it. It stands on three black square legs hinged from a
hub collar, braced by struts from a lower collar where the chrome outer sleeve ends. Round chrome stages slide out of
the sleeve, a black winch with a crank sits on its side, and a galvanised truss adapter sits on top. Every size this
module states as a constant is estimated from those photographs and has no published figure.

THE BOUNDING BOX IS THE MAST COLUMN, NOT THE WHOLE STAND. That breaks the rule `truss.py` keeps, and deliberately.
The mast tubes stay inside `dimensions_m`, so scene placement, the overlap sweep and the catalog go on reading it.
The legs, the winch and the adapter reach outside it in plan view, but only within the `base_spread_m` square, and
nothing reaches above `height` or below the floor. `tools/check-glb.py` holds a mast to that wider square.

THE STAGES ARE SEPARATE OBJECTS so a scene can crank the stand down without squashing its base. Each carries
`sdwa5_stage` = k, counted from 1 for the stage out of the sleeve, and the base carries `sdwa5_mast_stages`. A
cranked placement in `build_scene.py` moves stage k down by k/N of the height it loses, which leaves every joint
with the same overlap, as on the real stand.

FOLDED, FOR A PACK. `build_folded()` draws the stand as it travels, to the plan's `transport_m` box: every stage inside
the sleeve, the sleeve's foot at the floor, the legs swung up about the hub collar until they lie along the sleeve, and
the strut collar slid up the sleeve so each strut keeps its length. The truss adapter is not drawn, because its 0.4 m
bar fits no cross-section a folded stand has and it travels on its own. The crank's handle is turned down along its
arm. `build_model.py` puts it in a second collection, `<id>@packed`, which a packed placement instances.

Every length the stages need arrives worked out in the plan (`tube_length_m`, `travel_m`, `sleeve_bottom_m`,
`head_m`), from `App\\Spec\\Mast::planArray()`, so nothing here derives one figure from another.
"""

import math

from . import materials, tubes

# Rings per tube. The mast is the one round thing in a frame metres wide that the eye follows from top to bottom,
# and at ten sides its silhouette reads as faceted.
MAST_SIDES = 16

# A leg is square tube, and four sides drawn round its axis are exactly that.
LEG_SIDES = 4

# How far a collar stands proud of the tube it sits on, all round, and how tall it is.
COLLAR_PROUD_M = 0.015
COLLAR_HEIGHT_M = 0.08

# A foot pad under each leg, whose outer edge is what `base_spread_m` measures to.
FOOT_M = (0.06, 0.06, 0.02)

# The winch: a box on the sleeve's back face, with a crank on its right side. Its height on the sleeve is a fraction
# of the sleeve's length, so a shorter stand keeps it on the sleeve rather than above it.
WINCH_M = (0.10, 0.08, 0.14)
WINCH_AT = 0.6
CRANK_DIAMETER_M = 0.015
CRANK_ARM_M = 0.15
HANDLE_DIAMETER_M = 0.025
HANDLE_LENGTH_M = 0.08


def _collar(verts, faces, diameter, z):
    tubes.add_tube(
        verts, faces,
        (0.0, 0.0, z - COLLAR_HEIGHT_M / 2.0), (0.0, 0.0, z + COLLAR_HEIGHT_M / 2.0),
        diameter + 2.0 * COLLAR_PROUD_M, sides=MAST_SIDES,
    )


def _base(mast, verts, faces):
    """Collars, legs, struts and feet. Everything that stays put when the stand is cranked."""
    sleeve = mast["sections_m"][0]
    hub = mast["hub_height_m"]
    low = mast["sleeve_bottom_m"]
    collar_radius = sleeve / 2.0 + COLLAR_PROUD_M

    _collar(verts, faces, sleeve, hub)
    _collar(verts, faces, sleeve, low)

    reach = mast["base_spread_m"] / 2.0 - FOOT_M[0] / 2.0
    for index in range(mast["legs"]):
        angle = math.radians(mast["leg_yaw_deg"] + index * 360.0 / mast["legs"])
        direction = (math.cos(angle), math.sin(angle))

        def at(radius, z, direction=direction):
            return (radius * direction[0], radius * direction[1], z)

        foot = at(reach, FOOT_M[2])
        tubes.add_tube(verts, faces, at(collar_radius, hub), foot, mast["leg_width_m"], sides=LEG_SIDES)

        middle = tuple((at(collar_radius, hub)[axis] + foot[axis]) / 2.0 for axis in range(3))
        tubes.add_tube(verts, faces, at(collar_radius, low), middle, mast["leg_width_m"] * 0.8, sides=LEG_SIDES)

        tubes.add_box(verts, faces, at(reach, FOOT_M[2] / 2.0), FOOT_M)


def _winch(mast, verts, faces):
    """The winch box on the sleeve's back face, +Y, and its crank to the right."""
    sleeve = mast["sections_m"][0]
    low = mast["sleeve_bottom_m"]
    z = low + WINCH_AT * mast["tube_length_m"]
    y = sleeve / 2.0 + WINCH_M[1] / 2.0
    tubes.add_box(verts, faces, (0.0, y, z), WINCH_M)

    # Axle out of the right side, an arm down, a handle out to the right again: the Z the photographs show.
    axle_start = (WINCH_M[0] / 2.0, y, z)
    axle_end = (WINCH_M[0] / 2.0 + 0.04, y, z)
    arm_end = (axle_end[0], y, z - CRANK_ARM_M)
    handle_end = (arm_end[0] + HANDLE_LENGTH_M, y, arm_end[2])
    tubes.add_tube(verts, faces, axle_start, axle_end, CRANK_DIAMETER_M)
    tubes.add_tube(verts, faces, axle_end, arm_end, CRANK_DIAMETER_M)
    tubes.add_tube(verts, faces, arm_end, handle_end, HANDLE_DIAMETER_M)


def _adapter(mast, height, verts, faces):
    """Spigot, bar and the two chord saddles, with the saddles' top face at the stand's full height.

    The clamps are drawn as their lower half. The real ones close round the chord, and the chord starts at `height`,
    so the upper half would stand above the box.
    """
    adapter = mast["adapter"]
    bar = adapter["bar_m"]
    top_of_stage = height - mast["head_m"]
    bar_low = height - 2.0 * bar

    tubes.add_tube(verts, faces, (0.0, 0.0, top_of_stage), (0.0, 0.0, bar_low), mast["spigot_diameter_m"])
    tubes.add_box(verts, faces, (0.0, 0.0, bar_low + bar / 2.0), (bar, adapter["length_m"], bar))
    for side in (-1.0, 1.0):
        tubes.add_box(
            verts, faces,
            (0.0, side * adapter["clamp_spacing_m"] / 2.0, height - bar / 2.0),
            (bar * 1.5, bar * 1.5, bar),
        )


def build(plan, material_set):
    """The base as the body, and the sleeve and each stage as parts parented to it."""
    geometry = plan["geometry"]
    mast = geometry["mast"]
    height = geometry["dimensions_m"]["height"]
    low = mast["sleeve_bottom_m"]
    length = mast["tube_length_m"]
    chrome = material_set[materials.RIGGING]

    verts, faces = [], []
    _base(mast, verts, faces)
    if mast["winch"]:
        _winch(mast, verts, faces)
    body = tubes.mesh_object(plan["id"], verts, faces, material_set[materials.HARDWARE])
    body["sdwa5_mast_stages"] = mast["moving_stages"]

    verts, faces = [], []
    tubes.add_tube(verts, faces, (0.0, 0.0, low), (0.0, 0.0, low + length), mast["sections_m"][0], sides=MAST_SIDES)
    parts = [tubes.mesh_object("%s-sleeve" % plan["id"], verts, faces, chrome)]

    count = mast["moving_stages"]
    for stage in range(1, count + 1):
        bottom = low + stage * mast["travel_m"]
        verts, faces = [], []
        tubes.add_tube(
            verts, faces, (0.0, 0.0, bottom), (0.0, 0.0, bottom + length), mast["sections_m"][stage],
            sides=MAST_SIDES,
        )
        if stage == count and mast["adapter"] is not None:
            _adapter(mast, height, verts, faces)
        part = tubes.mesh_object("%s-stage-%d" % (plan["id"], stage), verts, faces, chrome)
        part["sdwa5_stage"] = stage
        parts.append(part)

    return body, parts


def _leg_length(mast):
    """Hub collar to foot, which is what a leg swings through when it folds."""
    collar_radius = mast["sections_m"][0] / 2.0 + COLLAR_PROUD_M
    reach = mast["base_spread_m"] / 2.0 - FOOT_M[0] / 2.0
    return math.hypot(reach - collar_radius, mast["hub_height_m"] - FOOT_M[2])


def build_folded(plan, material_set):
    """The stand folded to its transport box: body with legs, struts, collars and winch, and one tube per stage.

    Everything is in the erected stand's own frame moved down by `sleeve_bottom_m`, so the sleeve's foot is the floor.
    The hub collar stays where it is on the sleeve, and each leg turns about it until it points straight up.
    """
    mast = plan["geometry"]["mast"]
    sleeve = mast["sections_m"][0]
    low = mast["sleeve_bottom_m"]
    hub = mast["hub_height_m"] - low
    length = mast["tube_length_m"]
    collar_radius = sleeve / 2.0 + COLLAR_PROUD_M
    leg_radius = collar_radius + mast["leg_width_m"] / 2.0
    leg = _leg_length(mast)
    chrome = material_set[materials.RIGGING]

    # The strut keeps its erected length, from the strut collar to the middle of its leg, so the collar slides up to
    # wherever that length reaches the folded leg's middle.
    reach = mast["base_spread_m"] / 2.0 - FOOT_M[0] / 2.0
    middle_radius = (collar_radius + reach) / 2.0
    middle_z = (mast["hub_height_m"] + FOOT_M[2]) / 2.0
    strut = math.hypot(middle_radius - collar_radius, middle_z - mast["sleeve_bottom_m"])
    folded_middle = hub + leg / 2.0
    strut_collar = folded_middle - math.sqrt(max(0.0, strut ** 2 - (leg_radius - collar_radius) ** 2))

    verts, faces = [], []
    _collar(verts, faces, sleeve, hub)
    _collar(verts, faces, sleeve, strut_collar)
    for index in range(mast["legs"]):
        angle = math.radians(mast["leg_yaw_deg"] + index * 360.0 / mast["legs"])
        direction = (math.cos(angle), math.sin(angle))

        def at(radius, z, direction=direction):
            return (radius * direction[0], radius * direction[1], z)

        tubes.add_tube(
            verts, faces, at(leg_radius, hub), at(leg_radius, hub + leg), mast["leg_width_m"], sides=LEG_SIDES,
        )
        tubes.add_tube(
            verts, faces, at(collar_radius, strut_collar), at(leg_radius, folded_middle), mast["leg_width_m"] * 0.8,
            sides=LEG_SIDES,
        )
        tubes.add_box(verts, faces, at(leg_radius, hub + leg + FOOT_M[2] / 2.0), (FOOT_M[0], FOOT_M[1], FOOT_M[2]))

    if mast["winch"]:
        z = WINCH_AT * length
        y = sleeve / 2.0 + WINCH_M[1] / 2.0
        tubes.add_box(verts, faces, (0.0, y, z), WINCH_M)
        axle_start = (WINCH_M[0] / 2.0, y, z)
        axle_end = (WINCH_M[0] / 2.0 + 0.04, y, z)
        arm_end = (axle_end[0], y, z - CRANK_ARM_M)
        tubes.add_tube(verts, faces, axle_start, axle_end, CRANK_DIAMETER_M)
        tubes.add_tube(verts, faces, axle_end, arm_end, CRANK_DIAMETER_M)
        tubes.add_tube(verts, faces, arm_end, (arm_end[0], y, arm_end[2] - HANDLE_LENGTH_M), HANDLE_DIAMETER_M)

    body = tubes.mesh_object("%s@packed" % plan["id"], verts, faces, material_set[materials.HARDWARE])

    parts = []
    for stage, diameter in enumerate(mast["sections_m"]):
        verts, faces = [], []
        tubes.add_tube(verts, faces, (0.0, 0.0, 0.0), (0.0, 0.0, length), diameter, sides=MAST_SIDES)
        if stage == len(mast["sections_m"]) - 1:
            # The spigot the adapter sits on stays with the stand.
            spigot_top = (0.0, 0.0, length + mast["head_m"] / 2.0)
            tubes.add_tube(verts, faces, (0.0, 0.0, length), spigot_top, mast["spigot_diameter_m"])
        parts.append(tubes.mesh_object("%s@packed-tube-%d" % (plan["id"], stage), verts, faces, chrome))

    return body, parts
