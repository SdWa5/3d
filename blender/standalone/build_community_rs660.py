"""Community RS660, a 1995 three-way trapezoid, as a standalone model.

    blender -b --factory-startup --python blender/standalone/build_community_rs660.py -- \\
        --out standalone/community-rs660 [--ref <dir with front-ref.png and side-ref.png>]

Source: Community data sheet "RS660 Three-way electronically controlled loudspeaker system", (c) 1995 Community
Light & Sound, page 1 front drawing and page 2 front/rear/side/top views. Lengths below are millimetres. World frame
as everywhere in sdwa5-3d: metres, +Z up, front faces -Y, +X right, origin bottom-centre.

Front drawing scale (page 1 at 400 dpi): the box spans 663 x 1117 px, i.e. 1.289 px/mm across and 1.303 px/mm up,
so each axis is scaled on its own. Both data sheet pages carry the same 1.1 % vertical stretch.
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
MODEL_ID = "community-rs660"

# --- Data sheet (D) -------------------------------------------------------------------------
IN = 25.4
W_FRONT = 20.25 * IN          # 514.35
W_BACK = 12.19 * IN           # 309.63, top view
DEPTH = 20.25 * IN            # 514.35
HEIGHT = 33.75 * IN           # 857.25 (page 1 prints 857.35 mm, page 2 857.25; 33.75 in is 857.25)
VOLUME_L = 98.0               # stated internal volume

Y_F = -DEPTH / 2
Y_B = DEPTH / 2
K = (W_FRONT - W_BACK) / 2 / DEPTH     # side slope, 11.26 deg per side
SEC = math.sqrt(1 + K * K)             # 1 / cos

# --- Estimates (E) and drawing reads (S) ----------------------------------------------------
WALL = 18.0                   # E: 13-ply Baltic birch; the drawing's border reads 18.4-19.4
Z0, Z1 = WALL, HEIGHT - WALL
FP_RECESS = 8.0               # E: faceplate set back so straps and screws stay inside the box
FP_T = 6.0                    # E: fiberglass faceplate
Y_FP = Y_F + FP_RECESS        # faceplate front, d = 0
Y_FPB = Y_FP + FP_T           # faceplate back

MID_ZC = 714.6                                  # S
MID_MOUTH = (180.4, 93.65)                      # S: half width, half height (360.8 x 187.3)
MID_THROAT = (57.8, 25.7)                       # S: 115.6 x 51.4
MID_DEPTH = 200.0                               # E
MID_WALL = 5.0                                  # E

LF_ZC = (438.3, 165.0)                          # S: upper (coaxial HF), lower
LF_LIP = (111.0, 117.0)                         # S: rolled lip rings, 222 / 234 diameter
LF_THROAT = 71.0                                # S: lower throat 142 diameter
LF_FLARE_END = 86.0                             # E: flare depth 80 behind the faceplate
LF_WALL = 4.0                                   # E
LF_FRAME_R = 130.5                              # E: 10" frame 261

HF_MOUTH = 61.0                                 # S: 122 inner / 130 outer ring
HF_THROAT = 12.7                                # D: 1" exit
HF_LEN = 70.0                                   # E

PORT_X, PORT_Z = 197.0, 304.7                   # S
PORT_AB = (23.0, 37.5)                          # S: 46 x 75 ovals
PORT_END = 60.0                                 # E: tube depth, limited by the sloping side wall
PORT_WALL = 3.0                                 # E

STRAP_R = (66.0, 120.0)                         # S
STRAP_W = 24.0                                  # S
SHELF_Z = (575.85, 593.85)                      # E: 18 mm shelf behind the drawn 12 mm strip
GROOVE_Z = (578.7, 591.0)                       # S: the strip on the faceplate

STEEL_SIDE, STEEL_TOP, STEEL_T = 47.0, 19.4, 3.0    # S, S, E
BOLT_DEPTHS = (71.0, 164.0, 257.0, 351.0, 446.0)    # S: from the front, side view
BOLT_Z = (35.25, 824.25)                            # S

HANDLE_DEPTHS = (109.0, 370.5)                  # S, front taken as the right edge of the side view
HANDLE_ZC = 505.0                               # S
FLANGE = (81.5, 137.5)                          # S: 163 x 275
POCKET = (56.0, 112.5)                          # S: 112 x 225
POCKET_DEPTH = 45.0                             # E
GRIP_Z = (449.25, 477.25)                       # S

PLATE_ZC, PLATE_HALF = 430.7, (63.0, 88.0)      # S: 126 x 176
PLATE_POCKET = 15.0                             # E

SCREW_X_SIDE = 218.2                            # S
SCREW_Z_SIDE = (785.2, 708.8, 632.4, 530.0, 454.4, 378.0, 301.2, 225.3, 148.9, 72.6)
SCREW_X_ROW = (-155.4, -77.6, 0.0, 77.6, 155.4)
SCREW_Z_ROW = (29.9, 819.7)

README = """Community RS660, standalone model

Source: Community data sheet "RS660 Three-way electronically controlled loudspeaker system",
(c) 1995 Community Light & Sound:
  https://downloads.biamp.com/assets/docs/default-source/discontinued/biamp_data_sheets_community_rs660_jun21.pdf

Units metres, +Z up, front faces -Y, origin bottom-centre. Lengths below in mm.
D = data sheet figure, S = scaled off the data sheet drawings, E = estimate.

Shell 514.35 W x 857.25 H x 514.35 D, back 309.6 wide, 22.5 deg trapezoid ..... D
Walls 18, black paint ........................................................... D (paint), E (18)
Faceplate one-piece black fiberglass, set back 8, 6 thick ........................ D (one piece), E
Mid horn mouth 360.8 x 187.3, throat 115.6 x 51.4, M200 grille 51 ............... S
Mid horn depth 200 ............................................................. E
LF flares lip 234 / 222, throat 142, centres at Z 438.3 and 165.0 ............... S
LF flare depth 80, drivers 10" with 261 frames .................................. E
Coaxial HF horn in the upper LF, mouth 130 outer ................................ D (coaxial), S
Ports 46 x 75 ovals at X +-197, Z 304.7 ......................................... S
Port depth 60 ................................................................... E
Strip between mid and LF section at Z 578.7..591.0 .............................. S
Shelf behind it, 18 thick ........................................................ E (inferred)
Four steel edges along the sides' top and bottom, 47 x 19.4 L ................... D (four), S
Steel thickness 3 ................................................................ E
Two dish handles per side, flange 163 x 275, opening 112 x 225 .................. D (four), S
Handle positions 109 and 370.5 behind the front ................................. S, front side inferred
Handle dish depth 45 ............................................................ E
Rear connector plate 126 x 176, 2 Speakon + banana ............................... D (connectors), S
Logo ............................................................................ omitted

Volume check: the data sheet states 98 L internal volume. The build prints the modelled LF chamber
(below the inferred shelf) for comparison.

Built with --ref, the collection "Reference (data sheet)" holds the drawings at true scale: enable it and look
along +Y (Front view, Numpad1) or along +X (Left view, Ctrl+Numpad3). The committed file is built without them.
"""


# --- Helpers --------------------------------------------------------------------------------

def V(x, y, z):
    return Vector((x / 1000.0, y / 1000.0, z / 1000.0))


def hw_out(y):
    return W_FRONT / 2 - (y - Y_F) * K


def side_point(sgn, y, b, z):
    """Point at depth y, b mm inward from the outer face of side sgn (-1 left, +1 right)."""
    return (sgn * (hw_out(y) - b * SEC), y, z)


def add_solid(bm, loop_a, loop_b):
    """Closed prism-like solid between two loops of equal length (mm tuples)."""
    a = [bm.verts.new(V(*p)) for p in loop_a]
    b = [bm.verts.new(V(*p)) for p in loop_b]
    bm.faces.new(a)
    bm.faces.new(b)
    n = len(a)
    for i in range(n):
        j = (i + 1) % n
        bm.faces.new((a[i], a[j], b[j], b[i]))


def add_tube(bm, inner, outer):
    """Closed hollow tube from stations of inner and outer loops (lists of loops)."""
    ii = [[bm.verts.new(V(*p)) for p in loop] for loop in inner]
    oo = [[bm.verts.new(V(*p)) for p in loop] for loop in outer]
    n = len(inner[0])

    def band(a, b):
        for i in range(n):
            j = (i + 1) % n
            bm.faces.new((a[i], a[j], b[j], b[i]))

    for k in range(len(ii) - 1):
        band(ii[k], ii[k + 1])
        band(oo[k], oo[k + 1])
    band(ii[0], oo[0])
    band(ii[-1], oo[-1])



def finish(name, bm, coll, mat):
    return MODEL.mesh_object(name, bm, coll, mat)


def solid(name, loop_a, loop_b, coll, mat):
    bm = bmesh.new()
    add_solid(bm, loop_a, loop_b)
    return finish(name, bm, coll, mat)


def prism_z(name, poly_xy, z0, z1, coll, mat):
    return solid(name, [(x, y, z0) for x, y in poly_xy], [(x, y, z1) for x, y in poly_xy], coll, mat)


def box(name, x0, x1, y0, y1, z0, z1, coll, mat):
    return prism_z(name, [(x0, y0), (x1, y0), (x1, y1), (x0, y1)], z0, z1, coll, mat)


def obox_loops(origin, u, n, a0, a1, b0, b1, z0, z1):
    def p(a, b, z):
        return (origin[0] + u[0] * a + n[0] * b, origin[1] + u[1] * a + n[1] * b, z)

    lo = [p(a0, b0, z0), p(a1, b0, z0), p(a1, b1, z0), p(a0, b1, z0)]
    hi = [p(a0, b0, z1), p(a1, b0, z1), p(a1, b1, z1), p(a0, b1, z1)]
    return lo, hi


def obox(name, frame, a, b, z, coll, mat):
    return solid(name, *obox_loops(*frame, *a, *b, *z), coll, mat)


def side_frame(sgn, depth):
    """Origin on the outer face, u along the side (front to back), n inward, in mm."""
    y = Y_F + depth
    u = (-sgn * K / SEC, 1 / SEC)
    n = (-sgn / SEC, -K / SEC)
    return (sgn * hw_out(y), y), u, n


def lathe(name, profile, coll, mat, segments=96, closed=False, place=None):
    """Spin an (r, d) profile in mm around local +Z; `place` is the object matrix."""
    bm = bmesh.new()
    verts = [bm.verts.new((r / 1000.0, 0.0, d / 1000.0)) for r, d in profile]
    pairs = list(zip(verts, verts[1:], strict=False))
    if closed:
        pairs.append((verts[-1], verts[0]))
    edges = [bm.edges.new(p) for p in pairs]
    bmesh.ops.spin(bm, geom=verts + edges, cent=(0, 0, 0), axis=(0, 0, 1),
                   angle=2 * math.pi, steps=segments, use_merge=True)
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-7)
    bmesh.ops.dissolve_degenerate(bm, edges=bm.edges, dist=1e-7)
    obj = finish(name, bm, coll, mat)
    if place is not None:
        obj.matrix_world = place
    return obj


def front_axis(x, z, d=0.0):
    """Local +Z pointing into the box (+Y), origin on the faceplate front plus d."""
    return Matrix.Translation(V(x, Y_FP + d, z)) @ Matrix.Rotation(-math.pi / 2, 4, "X")


def ellipse(cx, cz, a, b, y, n=48):
    return [(cx + a * math.cos(2 * math.pi * i / n), y, cz + b * math.sin(2 * math.pi * i / n)) for i in range(n)]


def rect(hw, hh, zc, y):
    return [(-hw, y, zc - hh), (hw, y, zc - hh), (hw, y, zc + hh), (-hw, y, zc + hh)]



# --- Scene ----------------------------------------------------------------------------------

MODEL = common.Model(MODEL_ID, ("Shell", "Faceplate & horns", "Drivers (estimated)", "Hardware",
                                  "Reference (data sheet)", "Cutters"))
difference = common.difference
material = MODEL.material

M = {
    "paint": material("Black paint", "141414", 0.55),
    "glass": material("Fiberglass", "0c0c0c", 0.3),
    "steel": material("Steel", "3c3c3c", 0.35, 0.9),
    "cone": material("Driver", "1e1e1e", 0.7),
    "foam": material("Urethane horn", "181818", 0.5),
    "grille": material("Grille", "4a4a4a", 0.45, 0.6),
    "cutter": material("Cutter", "ff00ff"),
}

parts = []          # every visible mesh, for the checks
by_side = {-1: [], 1: []}


def keep(obj, sgn=None):
    parts.append(obj)
    if sgn is not None:
        by_side[sgn].append(obj)
    return obj


def cutter(obj):
    obj.display_type = "WIRE"
    obj.hide_render = True
    return obj


# Shell
outer = [(-W_FRONT / 2, Y_F), (W_FRONT / 2, Y_F), (hw_out(Y_B), Y_B), (-hw_out(Y_B), Y_B)]
top = keep(prism_z("Top", outer, Z1, HEIGHT, "Shell", M["paint"]))
bottom = keep(prism_z("Bottom", outer, 0.0, Z0, "Shell", M["paint"]))
sides = {}
for sgn, label in ((-1, "-X"), (1, "+X")):
    poly = [side_point(sgn, Y_F, 0, 0)[:2], side_point(sgn, Y_B, 0, 0)[:2],
            side_point(sgn, Y_B, WALL, 0)[:2], side_point(sgn, Y_F, WALL, 0)[:2]]
    sides[sgn] = keep(prism_z(f"Side {label}", poly, Z0, Z1, "Shell", M["paint"]), sgn)
yb_in = Y_B - WALL
back = keep(prism_z("Back", [side_point(-1, yb_in, WALL, 0)[:2], side_point(1, yb_in, WALL, 0)[:2],
                             side_point(1, Y_B, WALL, 0)[:2], side_point(-1, Y_B, WALL, 0)[:2]],
                    Z0, Z1, "Shell", M["paint"]))
shelf = keep(prism_z("Shelf (estimated)", [side_point(-1, Y_FPB, WALL, 0)[:2], side_point(1, Y_FPB, WALL, 0)[:2],
                                           side_point(1, yb_in, WALL, 0)[:2], side_point(-1, yb_in, WALL, 0)[:2]],
                     *SHELF_Z, "Shell", M["paint"]))

# Faceplate with its openings
fp = keep(prism_z("Faceplate", [side_point(-1, Y_FP, WALL, 0)[:2], side_point(1, Y_FP, WALL, 0)[:2],
                                side_point(1, Y_FPB, WALL, 0)[:2], side_point(-1, Y_FPB, WALL, 0)[:2]],
                  Z0, Z1, "Faceplate & horns", M["glass"]))
fp_cuts = [cutter(box("cut mid mouth", -MID_MOUTH[0], MID_MOUTH[0], Y_FP - 5, Y_FPB + 5,
                      MID_ZC - MID_MOUTH[1], MID_ZC + MID_MOUTH[1], "Cutters", M["cutter"]))]
for i, zc in enumerate(LF_ZC):
    fp_cuts.append(cutter(lathe(f"cut lf {i}", [(0, -5), (LF_LIP[0], -5), (LF_LIP[0], FP_T + 5), (0, FP_T + 5)],
                                "Cutters", M["cutter"], place=front_axis(0, zc))))
for sgn in (-1, 1):
    fp_cuts.append(cutter(solid(f"cut port {sgn}", ellipse(sgn * PORT_X, PORT_Z, *PORT_AB, Y_FP - 5),
                                ellipse(sgn * PORT_X, PORT_Z, *PORT_AB, Y_FPB + 5), "Cutters", M["cutter"])))
fp_cuts.append(cutter(box("cut groove", -260, 260, Y_FP - 5, Y_FP + 2, *GROOVE_Z, "Cutters", M["cutter"])))
difference(fp, fp_cuts)

# Mid horn, straight-walled pattern-control flare from the faceplate back to the throat
d_a, d_b = FP_T, MID_DEPTH


def mid_half(d):
    t = d / MID_DEPTH
    return (MID_MOUTH[0] + (MID_THROAT[0] - MID_MOUTH[0]) * t, MID_MOUTH[1] + (MID_THROAT[1] - MID_MOUTH[1]) * t)


bm = bmesh.new()
inner = [rect(*mid_half(d), MID_ZC, Y_FP + d) for d in (d_a, d_b)]
outer = [rect(mid_half(d)[0] + MID_WALL, mid_half(d)[1] + MID_WALL, MID_ZC, Y_FP + d) for d in (d_a, d_b)]
add_tube(bm, inner, outer)
keep(finish("Mid horn", bm, "Faceplate & horns", M["glass"]))

# LF flares: lip, exponential flare shell, throat plate
for i, zc in enumerate(LF_ZC):
    tag = ("upper", "lower")[i]
    keep(lathe(f"LF lip {tag}", [(LF_LIP[0], -3), (LF_LIP[1], -3), (LF_LIP[1], 0), (LF_LIP[0], 0)],
               "Faceplate & horns", M["glass"], closed=True, place=front_axis(0, zc)))
    steps = 12
    span = LF_FLARE_END - FP_T
    inner_r = [(LF_THROAT * (LF_LIP[0] / LF_THROAT) ** (1 - s / steps), FP_T + span * s / steps)
               for s in range(steps + 1)]
    profile = inner_r + [(r + LF_WALL, d) for r, d in reversed(inner_r)]
    keep(lathe(f"LF flare {tag}", profile, "Faceplate & horns", M["glass"], closed=True, place=front_axis(0, zc)))
    keep(lathe(f"LF throat plate {tag}",
               [(LF_THROAT, LF_FLARE_END), (LF_FRAME_R, LF_FLARE_END), (LF_FRAME_R, LF_FLARE_END + 6),
                (LF_THROAT, LF_FLARE_END + 6)], "Faceplate & horns", M["glass"], closed=True,
               place=front_axis(0, zc)))
    # Straps: upper ones carry the coaxial HF horn
    bm = bmesh.new()
    for phi in ((90, 180, 0), (180, 0, 270))[i]:
        e = (math.cos(math.radians(phi)), math.sin(math.radians(phi)))
        t = (-e[1], e[0])
        quad = []
        for r, w in ((STRAP_R[0], -STRAP_W / 2), (STRAP_R[1], -STRAP_W / 2), (STRAP_R[1], STRAP_W / 2),
                     (STRAP_R[0], STRAP_W / 2)):
            quad.append((e[0] * r + t[0] * w, e[1] * r + t[1] * w + zc))
        add_solid(bm, [(x, Y_FP - 7, z) for x, z in quad], [(x, Y_FP - 3, z) for x, z in quad])
    keep(finish(f"LF straps {tag}", bm, "Hardware", M["steel"]))

# Coaxial HF horn in the upper LF
hf_inner = [(HF_THROAT * (HF_MOUTH / HF_THROAT) ** (1 - s / 10), -3 + HF_LEN * s / 10) for s in range(11)]
keep(lathe("HF horn", hf_inner + [(r + 4, d) for r, d in reversed(hf_inner)], "Faceplate & horns", M["foam"],
           closed=True, place=front_axis(0, LF_ZC[0])))

# Ports
for sgn in (-1, 1):
    bm = bmesh.new()
    add_tube(bm, [ellipse(sgn * PORT_X, PORT_Z, *PORT_AB, Y_FP + d) for d in (FP_T, PORT_END)],
             [ellipse(sgn * PORT_X, PORT_Z, PORT_AB[0] + PORT_WALL, PORT_AB[1] + PORT_WALL, Y_FP + d)
              for d in (FP_T, PORT_END)])
    keep(finish(f"Port {'-X' if sgn < 0 else '+X'}", bm, "Faceplate & horns", M["glass"]))

# Faceplate screws
bm = bmesh.new()
spots = [(sx * SCREW_X_SIDE, z) for sx in (-1, 1) for z in SCREW_Z_SIDE]
spots += [(x, z) for x in SCREW_X_ROW for z in SCREW_Z_ROW]
for x, z in spots:
    add_solid(bm, ellipse(x, z, 3.5, 3.5, Y_FP - 1.5, 16), ellipse(x, z, 3.5, 3.5, Y_FP, 16))
keep(finish("Faceplate screws", bm, "Hardware", M["steel"]))

# Drivers (E)
lf_profile = [(0, 48), (35, 60), (112, 4), (112, 0), (LF_FRAME_R, 0), (LF_FRAME_R, 6), (60, 75), (60, 120),
              (40, 120), (40, 130), (0, 130)]
for i, zc in enumerate(LF_ZC):
    keep(lathe(f"LF driver 10in {('upper', 'lower')[i]}", lf_profile, "Drivers (estimated)", M["cone"],
               segments=64, place=front_axis(0, zc, LF_FLARE_END + 6)))
keep(lathe("VHF100 HF driver", [(0, HF_LEN - 3), (16.7, HF_LEN - 3), (16.7, HF_LEN + 2), (45, HF_LEN + 2),
                                (45, HF_LEN + 47), (0, HF_LEN + 47)], "Drivers (estimated)", M["cone"],
           segments=48, place=front_axis(0, LF_ZC[0])))
keep(lathe("HF phase plug", [(0, HF_LEN - 20), (8, HF_LEN - 3), (0, HF_LEN - 3)], "Drivers (estimated)",
           M["cone"], segments=32, place=front_axis(0, LF_ZC[0])))
keep(lathe("M200 mid driver", [(0, MID_DEPTH), (66, MID_DEPTH), (66, MID_DEPTH + 8), (82.5, MID_DEPTH + 8),
                               (82.5, MID_DEPTH + 90), (50, MID_DEPTH + 90), (50, MID_DEPTH + 100),
                               (0, MID_DEPTH + 100)], "Drivers (estimated)", M["cone"], segments=64,
           place=front_axis(0, MID_ZC)))
keep(lathe("M200 grille", [(0, MID_DEPTH - 2), (25.5, MID_DEPTH - 2), (25.5, MID_DEPTH), (0, MID_DEPTH)],
           "Drivers (estimated)", M["grille"], segments=48, place=front_axis(0, MID_ZC)))

def steel_loop(sgn, section, y, z_edge, zs):
    """An L-section, given as (inward, from the edge) pairs, placed at depth y on one of the four edges."""
    return [side_point(sgn, y, b, z_edge + zs * h) for b, h in section]


# Steel edges with bolts, and their rebates in the shell
steel_cuts = []
for sgn in (-1, 1):
    label = "-X" if sgn < 0 else "+X"
    for z_edge, zs in ((HEIGHT, -1), (0.0, 1)):
        sec = [(0, 0), (STEEL_TOP, 0), (STEEL_TOP, STEEL_T), (STEEL_T, STEEL_T), (STEEL_T, STEEL_SIDE),
               (0, STEEL_SIDE)]
        grown = [(-1, -1), (STEEL_TOP, -1), (STEEL_TOP, STEEL_T), (STEEL_T, STEEL_T), (STEEL_T, STEEL_SIDE),
                 (-1, STEEL_SIDE)]

        where = "top" if zs < 0 else "bottom"
        keep(solid(f"Steel edge {label} {where}", steel_loop(sgn, sec, Y_F, z_edge, zs),
                   steel_loop(sgn, sec, Y_B, z_edge, zs), "Hardware", M["steel"]), sgn)
        steel_cuts.append((sgn, cutter(solid(f"cut steel {label} {where}", steel_loop(sgn, grown, Y_F - 1, z_edge, zs),
                                             steel_loop(sgn, grown, Y_B + 1, z_edge, zs),
                                             "Cutters", M["cutter"]))))
    bm = bmesh.new()
    for depth in BOLT_DEPTHS:
        (ox, oy), u, n = side_frame(sgn, depth)
        for z in BOLT_Z:
            ring_in = [(ox + u[0] * 5 * math.cos(a), oy + u[1] * 5 * math.cos(a), z + 5 * math.sin(a))
                       for a in (2 * math.pi * k / 16 for k in range(16))]
            ring_out = [(x - n[0] * 2, y - n[1] * 2, z) for x, y, z in ring_in]
            add_solid(bm, ring_in, ring_out)
    keep(finish(f"Steel bolts {label}", bm, "Hardware", M["steel"]), sgn)

# Handles: flange, dish, grip; dish pockets cut the side panel and notch the shelf
pocket_cuts = {-1: [], 1: []}
for sgn in (-1, 1):
    label = "-X" if sgn < 0 else "+X"
    for k, depth in enumerate(HANDLE_DEPTHS):
        frame = side_frame(sgn, depth)
        zc = HANDLE_ZC
        name = f"Handle {label} {('front', 'rear')[k]}"
        flange = keep(obox(f"{name} flange", frame, (-FLANGE[0], FLANGE[0]), (-STEEL_T, 0),
                           (zc - FLANGE[1], zc + FLANGE[1]), "Hardware", M["steel"]), sgn)
        hole = cutter(obox(f"cut {name} hole", frame, (-POCKET[0], POCKET[0]), (-10, 10),
                           (zc - POCKET[1], zc + POCKET[1]), "Cutters", M["cutter"]))
        difference(flange, [hole])
        cup = keep(obox(f"{name} dish", frame, (-POCKET[0] - 2, POCKET[0] + 2), (0, POCKET_DEPTH + 2),
                        (zc - POCKET[1] - 2, zc + POCKET[1] + 2), "Hardware", M["steel"]), sgn)
        inner_cut = cutter(obox(f"cut {name} dish", frame, (-POCKET[0], POCKET[0]), (-10, POCKET_DEPTH),
                                (zc - POCKET[1], zc + POCKET[1]), "Cutters", M["cutter"]))
        difference(cup, [inner_cut])
        keep(obox(f"{name} grip", frame, (-POCKET[0], POCKET[0]), (3, 15), GRIP_Z, "Hardware", M["steel"]), sgn)
        pocket_cuts[sgn].append(cutter(obox(f"cut {name} pocket", frame, (-POCKET[0] - 2, POCKET[0] + 2),
                                            (-5, POCKET_DEPTH + 2), (zc - POCKET[1] - 2, zc + POCKET[1] + 2),
                                            "Cutters", M["cutter"])))

for sgn in (-1, 1):
    difference(sides[sgn], [c for s, c in steel_cuts if s == sgn] + pocket_cuts[sgn])
difference(top, [c for s, c in steel_cuts if "top" in c.name])
difference(bottom, [c for s, c in steel_cuts if "bottom" in c.name])
difference(shelf, pocket_cuts[-1] + pocket_cuts[1])

# Rear connector plate in a pocket of the back panel
difference(back, [cutter(box("cut plate pocket", -PLATE_HALF[0], PLATE_HALF[0], Y_B - PLATE_POCKET, Y_B + 5,
                             PLATE_ZC - PLATE_HALF[1], PLATE_ZC + PLATE_HALF[1], "Cutters", M["cutter"]))])
keep(box("Rear plate", -PLATE_HALF[0], PLATE_HALF[0], Y_B - PLATE_POCKET, Y_B - PLATE_POCKET + 2,
         PLATE_ZC - PLATE_HALF[1], PLATE_ZC + PLATE_HALF[1], "Hardware", M["steel"]))
bm = bmesh.new()
y_plate = Y_B - PLATE_POCKET + 2
for x in (-21.0, 21.0):
    add_solid(bm, [(x + 13 * math.cos(a), y_plate, 466.7 + 13 * math.sin(a)) for a in
                   (2 * math.pi * k / 32 for k in range(32))],
              [(x + 13 * math.cos(a), y_plate + 12, 466.7 + 13 * math.sin(a)) for a in
               (2 * math.pi * k / 32 for k in range(32))])
for x in (-17.5, 1.5):
    add_solid(bm, [(x + 4 * math.cos(a), y_plate, 437.7 + 4 * math.sin(a)) for a in
                   (2 * math.pi * k / 16 for k in range(16))],
              [(x + 4 * math.cos(a), y_plate + 10, 437.7 + 4 * math.sin(a)) for a in
               (2 * math.pi * k / 16 for k in range(16))])
keep(finish("Speakon and banana", bm, "Hardware", M["cone"]))

# Cutters are baked in; drop them so the file holds only the model
for o in list(MODEL.collections["Cutters"].objects):
    bpy.data.objects.remove(o, do_unlink=True)
bpy.data.collections.remove(MODEL.collections.pop("Cutters"))

# Data sheet drawings at true scale, 1 px = 1 mm, only when they are at hand (--ref)
if REF:
    for fname, loc, rows in (
        ("front-ref.png", (0.0, -0.40, HEIGHT / 2000), ((1, 0, 0), (0, 0, -1), (0, 1, 0))),
        ("side-ref.png", (-0.40, 0.0, HEIGHT / 2000), ((0, 0, -1), (-1, 0, 0), (0, 1, 0))),
    ):
        MODEL.reference_image("Reference (data sheet)", os.path.join(REF, fname),
                              f"Data sheet {fname.split('-')[0]} view", HEIGHT / 1000, loc, rows)
MODEL.hide_in_viewport("Reference (data sheet)")
MODEL.readme(README)
MODEL.metadata(W_FRONT / 1000, HEIGHT / 1000, DEPTH / 1000, "datasheet",
               "https://downloads.biamp.com/assets/docs/default-source/discontinued/"
               "biamp_data_sheets_community_rs660_jun21.pdf")

# --- Checks ---------------------------------------------------------------------------------


def describe(obj, lo, hi, volume):
    lo, hi = lo * 1000, hi * 1000
    return (f"{obj.name:34s} x {lo.x:7.1f}..{hi.x:7.1f}  y {lo.y:7.1f}..{hi.y:7.1f}  z {lo.z:6.1f}..{hi.z:6.1f}  "
            f"vol {volume * 1000:7.3f} L")


volumes = {name: v * 1e9 for name, v in
           common.check_parts(parts, W_FRONT / 1000, HEIGHT / 1000, DEPTH / 1000, describe).items()}

back_x = max(abs((obj.matrix_world @ v.co).x) * 1000 for obj in parts for v in obj.data.vertices
             if abs((obj.matrix_world @ v.co).y * 1000 - Y_B) < 0.01)
print(f"BACK WIDTH {2 * back_x:.2f} mm (data sheet {W_BACK:.2f})")
assert abs(2 * back_x - W_BACK) < 1, "back width"

# LF chamber volume against the stated 98 L: gross trapezoid between the faceplate, the back panel, the bottom and
# the inferred shelf, minus what sits in it or is open to the outside.
y0, y1 = Y_FPB, yb_in
gross = (SHELF_Z[0] - Z0) * ((2 * hw_out(y0) - 2 * WALL * SEC) + (2 * hw_out(y1) - 2 * WALL * SEC)) / 2 * (y1 - y0)
solids = sum(v for n, v in volumes.items() if n.startswith(("LF driver", "LF flare", "LF throat", "Port")))
n_int = 400
span = LF_FLARE_END - FP_T
flare_air = 2 * sum(math.pi * (LF_THROAT * (LF_LIP[0] / LF_THROAT) ** (1 - (k + 0.5) / n_int)) ** 2 * span / n_int
                    for k in range(n_int))
throat_air = 2 * math.pi * LF_THROAT ** 2 * 6
cone_air = 2 * math.pi / 3 * 60 * (112 ** 2 + 35 ** 2 + 112 * 35)
port_air = 2 * math.pi * PORT_AB[0] * PORT_AB[1] * (PORT_END - FP_T)
frac = (SHELF_Z[0] - (HANDLE_ZC - POCKET[1] - 2)) / (2 * POCKET[1] + 4)
cups = 4 * frac * (2 * POCKET[0] + 4) * (2 * POCKET[1] + 4) * (POCKET_DEPTH + 2 - WALL)
net = gross - solids - flare_air - throat_air - cone_air - port_air - cups
print(f"VOLUME LF chamber gross {gross / 1e6:.1f} L, parts {solids / 1e6:.1f}, "
      f"horn air {(flare_air + throat_air + cone_air) / 1e6:.1f}, ports {port_air / 1e6:.1f}, "
      f"handle dishes {cups / 1e6:.1f} -> net ~{net / 1e6:.1f} L (data sheet {VOLUME_L:.0f} L)")

# --- Previews (Cycles CPU, the container has no GPU) ----------------------------------------

previews = common.Previews(MODEL, OUT)
if REF:
    # Ortho views at 1 px = 1 mm, the drawings' own scale, for an overlay check. They go next to the drawings.
    previews.ortho("model-front-ortho.png", (514, 857), HEIGHT / 1000, (0.0, -2.0, HEIGHT / 2000),
                   (math.radians(90), 0, 0), out_dir=REF)
    previews.ortho("model-side-ortho.png", (514, 857), HEIGHT / 1000, (-2.0, 0.0, HEIGHT / 2000),
                   (math.radians(90), 0, math.radians(-90)), out_dir=REF)
previews.perspective("preview-closed.png", (900, 1000), (-1.15, -1.55, 1.15), (0, 0, 0.42))
previews.perspective("preview-rear.png", (900, 1000), (1.25, 1.55, 1.10), (0, 0, 0.42))
previews.perspective("preview-cutaway.png", (900, 1000), (-1.65, -0.75, 1.0), (0, 0, 0.42),
                     [sides[-1], *by_side[-1]])

# --- Save -----------------------------------------------------------------------------------

common.bake_transforms(parts)
common.save(MODEL, OUT)
