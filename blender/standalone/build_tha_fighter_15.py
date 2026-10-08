"""The 15" THA Fighter horn, a folded front-loaded horn sub, as a standalone model.

    blender -b --factory-startup --python blender/standalone/build_tha_fighter_15.py -- \\
        --out standalone/tha-fighter-horn-15 [--ref <dir with side-view.png>]

Every panel is a section polygon in plan centimetres, extruded across the width.
Plan frame: x = depth from the back wall's inner face toward the front, z = height above
the bottom panel's inner face, y = across the width (0 = centre).
World frame as everywhere in sdwa5-3d: metres, +Z up, front faces -Y, origin bottom-centre.
"""

import math
import os
import sys

import bmesh
import bpy
from mathutils import Matrix, Vector

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import common  # noqa: E402  (after sys.path)

OUT, REF = common.parse_args(sys.argv)
MODEL_ID = "tha-fighter-horn-15"
T = 1.5  # panel thickness, cm

# Plan image calibration (15-fighter.jpg, 1083x718): 6.257 px/cm, plan origin at pixel (129, 640).
PX_PER_CM = 6.257
ORIGIN_PX = (129, 640)
CROP = (0, 55, 535, 650)  # x, y, w, h of side-view.png inside the plan image


# --- 2D helpers in plan cm ------------------------------------------------------------------

def add(a, b):
    return (a[0] + b[0], a[1] + b[1])


def sub(a, b):
    return (a[0] - b[0], a[1] - b[1])


def mul(a, s):
    return (a[0] * s, a[1] * s)


def dot(a, b):
    return a[0] * b[0] + a[1] * b[1]


def unit(a):
    length = math.hypot(*a)
    return (a[0] / length, a[1] / length)


def intersect(p, d, q, e):
    den = d[0] * e[1] - d[1] * e[0]
    t = ((q[0] - p[0]) * e[1] - (q[1] - p[1]) * e[0]) / den
    return add(p, mul(d, t))


def at_z(p, d, z):
    return add(p, mul(d, (z - p[1]) / d[1]))


def at_x(p, d, x):
    return add(p, mul(d, (x - p[0]) / d[0]))


def to_world(x, y, z):
    return Vector((y / 100.0, (30.0 - x) / 100.0, (z + 1.5) / 100.0))


def to_plan(v):
    return (30.0 - v.y * 100.0, v.x * 100.0, v.z * 100.0 - 1.5)


# --- Geometry read off the plan -------------------------------------------------------------

# Baffle back face (D) and its front face 1.5 toward the chamber.
BAF_TOP = (5.1, 84.3)
BAF_BOT = (22.2, 32.0)
d_b = unit(sub(BAF_BOT, BAF_TOP))
n_b = (-d_b[1], d_b[0])
baf_front = add(BAF_TOP, mul(n_b, T))
baf_front_top = at_z(baf_front, d_b, 84.3)

# Bend panel underside (D), 1.5 thick upward.
Q = (40.5, 38.0)
d_e = unit(sub(Q, BAF_BOT))
n_e = (-d_e[1], d_e[0])
bend_top = add(BAF_BOT, mul(n_e, T))
U0 = intersect(BAF_BOT, d_e, baf_front, d_b)
T0 = intersect(bend_top, d_e, baf_front, d_b)

# Flare roof underside (D), 1.5 thick upward, mitred with the bend.
FLARE_END = (61.5, 63.8)
d_f = unit(sub(FLARE_END, Q))
n_f = (-d_f[1], d_f[0])
flare_top = add(Q, mul(n_f, T))
Qp = intersect(bend_top, d_e, flare_top, d_f)
x_t = at_z(flare_top, d_f, 64.3)[0]

# Lower back deflector, path face (D), corner face 1.5 toward the corner.
DEF_A = (0.0, 21.0)
DEF_B = (16.0, 0.0)
d_g = unit(sub(DEF_B, DEF_A))
n_g = (d_g[1], -d_g[0])
def_corner = add(DEF_A, mul(n_g, T))
DEF_C0 = at_x(def_corner, d_g, 0.0)
DEF_C1 = at_z(def_corner, d_g, 0.0)

# Driver cutout: centre 20.5 along the baffle back face from its top (D), diameter 36 (S).
CUT_R = 18.0
cut_back = add(BAF_TOP, mul(d_b, 20.5))
cut_mid = add(cut_back, mul(n_b, T / 2))
cut_front = add(cut_back, mul(n_b, T))

# Driver dummy (E). Rear-mounted: flange on the baffle's chamber face. The frame shrinks
# until its top corner clears the hatch cleat.
CLEAT_BOTTOM = 82.8
FRAME_T = 0.6
frame_r_max = (CLEAT_BOTTOM - 0.1 - cut_front[1] - FRAME_T * n_b[1]) / (-d_b[1])
FRAME_R = min(19.35, frame_r_max)

SECTIONS = {
    # name: (collection, material, polygon in (x, z), y0, y1)
    "Side wall -X": ("Shell", "ply", [(-1.5, -1.5), (61.5, -1.5), (61.5, 85.8), (-1.5, 85.8)], -22.5, -21.0),
    "Side wall +X": ("Shell", "ply", [(-1.5, -1.5), (61.5, -1.5), (61.5, 85.8), (-1.5, 85.8)], 21.0, 22.5),
    "Bottom": ("Shell", "ply", [(-1.5, -1.5), (61.5, -1.5), (61.5, 0.0), (-1.5, 0.0)], -21.0, 21.0),
    "Back wall": ("Shell", "ply", [(-1.5, 0.0), (0.0, 0.0), (0.0, 84.3), (-1.5, 84.3)], -21.0, 21.0),
    "Top back": ("Shell", "ply", [(-1.5, 84.3), (baf_front_top[0], 84.3), (baf_front_top[0], 85.8), (-1.5, 85.8)],
                 -21.0, 21.0),
    "Top front": ("Shell", "ply", [(35.3, 84.3), (61.5, 84.3), (61.5, 85.8), (35.3, 85.8)], -21.0, 21.0),
    "Baffle": ("Horn panels", "ply", [BAF_TOP, baf_front_top, U0, BAF_BOT], -21.0, 21.0),
    "Bend": ("Horn panels", "ply", [U0, Q, Qp, T0], -21.0, 21.0),
    "Flare roof": ("Horn panels", "ply", [Q, FLARE_END, (61.5, 64.3), (x_t, 64.3), Qp], -21.0, 21.0),
    "Deflector": ("Horn panels", "ply", [DEF_A, DEF_B, DEF_C1, DEF_C0], -21.0, 21.0),
    "Centre divider": ("Horn panels", "ply", [
        (0.0, 38.0), at_z(BAF_TOP, d_b, 38.0), BAF_BOT, Q, at_x(Q, d_f, 50.2),
        (50.2, 0.0), DEF_B, DEF_A,
    ], -0.75, 0.75),
    "Port floor": ("Horn panels", "ply", [(41.4, 75.8), (60.0, 75.8), (60.0, 77.3), (41.4, 77.3)], -11.5, 11.5),
    "Port side -X": ("Horn panels", "ply", [(41.4, 77.3), (60.0, 77.3), (60.0, 84.3), (41.4, 84.3)], -11.5, -10.0),
    "Port side +X": ("Horn panels", "ply", [(41.4, 77.3), (60.0, 77.3), (60.0, 84.3), (41.4, 84.3)], 10.0, 11.5),
    "Hatch lid": ("Hatch", "lid", [(baf_front_top[0], 84.3), (35.3, 84.3), (35.3, 85.8), (baf_front_top[0], 85.8)],
                  -21.0, 21.0),
    "Cleat back": ("Hatch", "ply", [baf_front_top, (9.7, 84.3), (9.7, 82.8), at_z(baf_front, d_b, 82.8)], -21.0, 21.0),
    "Cleat front": ("Hatch", "ply", [(33.8, 82.8), (36.8, 82.8), (36.8, 84.3), (33.8, 84.3)], -21.0, 21.0),
}

# Front panel as a (y, z) outline extruded along x: 20 x 7 port opening at the top centre.
FRONT_PANEL = [(-21.0, 64.3), (21.0, 64.3), (21.0, 84.3), (10.0, 84.3), (10.0, 77.3),
               (-10.0, 77.3), (-10.0, 84.3), (-21.0, 84.3)]

# Driver profile (r, axial) in cm; axial runs from the baffle's chamber face into the chamber.
DRIVER_PROFILE = [
    (0.0, 7.5),        # dust cap
    (6.0, 9.0),        # cone neck
    (16.5, FRAME_T),   # cone lip
    (16.5, 0.0),
    (FRAME_R, 0.0),    # frame on the baffle
    (FRAME_R, FRAME_T),
    (11.0, 10.0),      # basket to magnet
    (11.0, 16.0),      # magnet
    (8.0, 16.0),
    (8.0, 17.0),       # backplate
    (0.0, 17.0),
]

README = f"""15" THA Fighter horn, standalone model

Source plan "15 THA Fighter Horn" (side section + front view):
  https://lh6.googleusercontent.com/-wRB8_m2lDl0/T2tDNvZ44JI/AAAAAAAABK0/dGQQE8Lo8m8/s1024/15%2520fighter.jpg
Thread with build photos:
  https://www.freespeakerplans.com/kunena/15-sub-bass/17435-my-fighter-horn-built?start=10

Units metres, +Z up, front faces -Y, origin bottom-centre. All panels 1.5 cm.
D = dimensioned on the plan, S = scaled off the drawing, E = estimate.

Outer box 45.0 W x 87.3 H x 63.0 D, inside 42.0 x 84.3 x 61.5 ............ D
Baffle back face (5.1, 84.3) -> (22.2, 32.0) in plan cm ................... D
Driver cutout centre 20.5 along the baffle from the top ................... D
Driver cutout diameter 36 ................................................. S
Bend underside (22.2, 32.0) -> (40.5, 38.0) ............................... D
Flare roof underside (40.5, 38.0) -> (61.5, 63.8) ......................... D
Front panel above the mouth, 20 x 7 opening at the top centre ............. D
Port tunnel floor 41.4..60.0 at 75.8..77.3 ................................ D
Port tunnel sides at +-10.0..11.5 ......................................... E
Lower back deflector (0, 21.0) -> (16.0, 0) ............................... D
Centre divider, front edge 11.3 behind the front, top at 38.0 ............. D
Top opening 6.7..35.3, lid full width ..................................... S/E
Hatch cleats 3.0 x 1.5 .................................................... D/S
Driver dummy (generic 15", rear-mounted from the chamber side) ............ E
  Frame reduced to diameter {2 * FRAME_R:.1f} cm so it clears the 3 cm cleat.
  A real 15" frame (~38.7 cm) would touch that cleat.

Check: enable collection "Reference (plan)" and look along +X (Left view, Ctrl+Numpad3).
The plan's side view sits at true scale behind the model.
"""


# --- Blender helpers ------------------------------------------------------------------------

def dedupe(poly):
    out = []
    for p in poly:
        if not out or math.dist(p, out[-1]) > 1e-6:
            out.append(p)
    if math.dist(out[0], out[-1]) <= 1e-6:
        out.pop()
    return out


def prism(name, poly, w0, w1, coll, mat, along_x=False):
    poly = dedupe(poly)
    bm = bmesh.new()

    def vert(a, b, w):
        return bm.verts.new(to_world(w, a, b) if along_x else to_world(a, w, b))

    lo = [vert(a, b, w0) for a, b in poly]
    hi = [vert(a, b, w1) for a, b in poly]
    bm.faces.new(lo)
    bm.faces.new(hi)
    for i in range(len(poly)):
        j = (i + 1) % len(poly)
        bm.faces.new((lo[i], lo[j], hi[j], hi[i]))
    return MODEL.mesh_object(name, bm, coll, mat)


def lathe(name, profile, coll, mat, segments=64):
    bm = bmesh.new()
    verts = [bm.verts.new((r / 100.0, 0.0, z / 100.0)) for r, z in profile]
    edges = [bm.edges.new((verts[i], verts[i + 1])) for i in range(len(verts) - 1)]
    bmesh.ops.spin(bm, geom=verts + edges, cent=(0, 0, 0), axis=(0, 0, 1),
                   angle=2 * math.pi, steps=segments, use_merge=True)
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-6)
    bmesh.ops.dissolve_degenerate(bm, edges=bm.edges, dist=1e-6)
    return MODEL.mesh_object(name, bm, coll, mat)


def axis_rotation(n):
    """Rotation about X that turns local +Z into the plan normal n (x, z)."""
    return Matrix.Rotation(math.atan2(n[0], n[1]), 4, "X")


# --- Scene ----------------------------------------------------------------------------------

MODEL = common.Model(MODEL_ID, ("Shell", "Horn panels", "Hatch", "Driver (estimated)", "Reference (plan)"))
mats = {
    "ply": MODEL.material("Plywood", "b98a5e", 0.7),
    "lid": MODEL.material("Plywood lid", "c99c70", 0.7),
    "driver": MODEL.material("Driver", "1a1a1a", 0.5),
}

panels = {}
for name, (coll, mat, poly, y0, y1) in SECTIONS.items():
    panels[name] = prism(name, poly, y0, y1, coll, mats[mat])
panels["Front panel"] = prism("Front panel", FRONT_PANEL, 60.0, 61.5, "Horn panels", mats["ply"], along_x=True)

# Driver cutout, baked into the baffle.
bm = bmesh.new()
bmesh.ops.create_cone(bm, cap_ends=True, segments=96, radius1=CUT_R / 100.0, radius2=CUT_R / 100.0, depth=0.10)
cutter = MODEL.mesh_object("Driver cutout cutter", bm, "Horn panels", mats["ply"])
cutter.matrix_world = Matrix.Translation(to_world(cut_mid[0], 0.0, cut_mid[1])) @ axis_rotation(n_b)
common.difference(panels["Baffle"], [cutter])
bpy.data.objects.remove(cutter, do_unlink=True)

driver = lathe("Driver 15in (estimated)", DRIVER_PROFILE, "Driver (estimated)", mats["driver"])
driver.matrix_world = Matrix.Translation(to_world(cut_front[0], 0.0, cut_front[1])) @ axis_rotation(n_b)

# The plan's side view as a true-scale image empty, seen from -X (Left view), only when it is at hand (--ref).
centre_x = (CROP[0] + CROP[2] / 2 - ORIGIN_PX[0]) / PX_PER_CM
centre_z = (ORIGIN_PX[1] - (CROP[1] + CROP[3] / 2)) / PX_PER_CM
centre = to_world(centre_x, 0.0, centre_z)
centre.x = -0.30
if REF:
    MODEL.reference_image("Reference (plan)", os.path.join(REF, "side-view.png"), "Plan side view",
                          CROP[3] / PX_PER_CM / 100.0, centre, ((0, 0, -1), (-1, 0, 0), (0, 1, 0)))
MODEL.hide_in_viewport("Reference (plan)")
MODEL.readme(README)
MODEL.metadata(0.450, 0.873, 0.630, "plans",
               "https://lh6.googleusercontent.com/-wRB8_m2lDl0/T2tDNvZ44JI/AAAAAAAABK0/dGQQE8Lo8m8/s1024/15%2520fighter.jpg")

# --- Checks ---------------------------------------------------------------------------------

print(f"CHECK plan geometry: baffle front top x={baf_front_top[0]:.2f}, U0=({U0[0]:.2f},{U0[1]:.2f}), "
      f"T0=({T0[0]:.2f},{T0[1]:.2f}), Q'=({Qp[0]:.2f},{Qp[1]:.2f}), flare top at z64.3 x={x_t:.2f}")
print(f"CHECK cutout centre on back face ({cut_back[0]:.2f},{cut_back[1]:.2f}); "
      f"frame r max {frame_r_max:.2f} -> {FRAME_R:.2f}")


def describe(obj, lo, hi, volume):
    p_lo, p_hi = to_plan(lo), to_plan(hi)
    return (f"{obj.name:26s} x {p_hi[0]:6.1f}..{p_lo[0]:6.1f}  y {p_lo[1]:6.1f}..{p_hi[1]:6.1f}  "
            f"z {p_lo[2]:6.1f}..{p_hi[2]:6.1f}  vol {volume * 1e6:8.1f} cm3")


parts = [*panels.values(), driver]
common.check_parts(parts, 0.450, 0.873, 0.630, describe)

# --- Previews (Cycles CPU, the container has no GPU) ----------------------------------------

previews = common.Previews(MODEL, OUT, samples=32)
walls = [panels["Side wall -X"], panels["Side wall +X"]]
if REF:
    # Side ortho at the plan crop's scale, both walls off so air shows white like the plan.
    previews.ortho("model-side-ortho.png", (CROP[2], CROP[3]), CROP[3] / PX_PER_CM / 100.0,
                   (-2.0, centre.y, centre.z), (math.radians(90), 0, math.radians(-90)), [*walls, driver], REF)
previews.perspective("preview-closed.png", (900, 1000), (-1.15, -1.45, 1.10), (0, 0, 0.42))
previews.perspective("preview-cutaway.png", (900, 1000), (-1.65, -0.85, 1.05), (0, 0, 0.40),
                     [panels["Side wall -X"], panels["Hatch lid"]])

# --- Save -----------------------------------------------------------------------------------

common.bake_transforms(parts)
common.save(MODEL, OUT)
