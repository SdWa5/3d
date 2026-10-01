"""Geometry construction for one device, driven entirely by its build plan.

Conventions (see docs/conventions.md) — every model in the library obeys them, which is what
makes models from different specs stack and snap together:

* metres, 1 Blender unit = 1 m
* +Z up, cabinet front faces −Y, +X is the cabinet's right seen from the front
* geometry is built in bottom-center coordinates and shifted at the end according to the
  spec's `origin`, so the outer bounding box is always exactly width × depth × height

Fidelity is deliberately "block level": true outer dimensions, chamfered edges, a recessed
grille behind a frame, handle recesses and rigging markers. No internal components.
"""

import math

import bmesh
import bpy

from . import materials

# Frame border around the grille panel, capped so it never eats a small cabinet's front.
_FRAME_MARGIN_MAX = 0.035
_FRAME_MARGIN_RATIO = 0.06

# Grille panel thickness and the size of a handle recess.
_GRILLE_THICKNESS = 0.006
_HANDLE_SIZE = (0.13, 0.05)
_HANDLE_DEPTH = 0.02
_HANDLE_HEIGHT_RATIO = 0.62
# What a side handle cup leaves of the wall beside a baffle opening, and the shallowest cup still worth cutting.
_HANDLE_WALL_M = 0.006
_HANDLE_MIN_DEPTH = 0.008

_RIGGING_MARKER_SIZE = 0.02
_ESTIMATED_MARKER_SIZE = 0.05


def origin_offset(plan):
    """Translation applied to every object so the spec's `origin` ends up at (0, 0, 0).

    Returns a 3-tuple. For `rigging-point` the first rigging point becomes the origin, which is
    what makes a flown cabinet hang naturally from where it is actually suspended.
    """
    geometry = plan["geometry"]
    height = geometry["dimensions_m"]["height"]
    mode = geometry["origin"]

    if mode == "bottom-center":
        return (0.0, 0.0, 0.0)
    if mode == "geometric-center":
        return (0.0, 0.0, -height / 2.0)
    if mode == "rigging-point":
        points = plan["rigging"]["points"]
        if not points:
            raise ValueError("origin 'rigging-point' needs at least one rigging point")
        x, y, z = points[0]["position_m"]
        return (-x, -y, -z)

    raise ValueError("unknown origin %r" % mode)


def _mesh_object(name, verts, faces, material):
    mesh = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bm_verts = [bm.verts.new(vert) for vert in verts]
    for face in faces:
        bm.faces.new([bm_verts[index] for index in face])
    # Winding of the hand-written face list is not guaranteed; let bmesh sort the normals out.
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(mesh)
    bm.free()

    obj = bpy.data.objects.new(name, mesh)
    # Boolean cutters get no material — an empty slot would survive into the export.
    if material is not None:
        obj.data.materials.append(material)

    return obj


def apply_front_image(plan, body, material):
    """Map the front photograph onto every forward-facing polygon of the shell.

    **Selected by normal rather than by index, on purpose.** The front is `faces[2]` when the block
    is built, but `cut_handles` runs a boolean and `add_chamfer` runs a bevel, and both reindex the
    mesh and may split the front into several polygons. Picking by direction survives all of that and
    also does the right thing on a chamfered edge, which is no longer flat and therefore correctly
    keeps the cabinet material.

    UVs come from the cabinet's own extents, so the image is stretched to the front and nothing
    depends on the photograph's pixel size. `SpecValidator` has already checked that the pixel size
    agrees with the cabinet, which is what makes the stretch faithful rather than merely tidy.

    Returns the number of polygons that took the image, so the caller can say nothing happened.
    """
    dims = plan["geometry"]["dimensions_m"]
    width = dims["width"]
    front_height = plan["geometry"].get("front_height_m") or dims["height"]
    rotate = int((plan.get("front_image") or {}).get("rotate_deg", 0)) % 360

    mesh = body.data

    slot = len(mesh.materials)
    mesh.materials.append(material)

    uv_layer = mesh.uv_layers.active or mesh.uv_layers.new(name="UVMap")

    applied = 0
    for polygon in mesh.polygons:
        # Forward is -Y. The tolerance is tight because a chamfer's own faces sit a few degrees off
        # and must keep the cabinet material rather than a slice of the photograph.
        if polygon.normal.y > -0.999:
            continue

        polygon.material_index = slot
        applied += 1

        for loop_index in polygon.loop_indices:
            vertex = mesh.vertices[mesh.loops[loop_index].vertex_index].co
            u = (vertex.x + width / 2.0) / width
            v = vertex.z / front_height if front_height > 0 else 0.0
            uv_layer.data[loop_index].uv = _turn_uv(u, v, rotate)

    return applied


def _turn_uv(u, v, degrees):
    """Turn one UV coordinate about the centre of the image.

    The photograph's own orientation is a property of the file, not of the cabinet: a cabinet
    photographed lying down has to be turned to match a model built upright. Only right angles,
    because anything else means the photograph was not taken square to the cabinet and the fix for
    that is a better crop.
    """
    if degrees == 90:
        return (v, 1.0 - u)
    if degrees == 180:
        return (1.0 - u, 1.0 - v)
    if degrees == 270:
        return (1.0 - v, u)

    return (u, v)


def _body_geometry(plan, front_y):
    """The eight corners and six faces of the cabinet body.

    Box, trapezoid and wedge are the same hexahedron with different corners: a trapezoid narrows
    towards the back, a wedge is lower at the front. Both tapers come from explicit spec fields,
    never from a guessed ratio.
    """
    geometry = plan["geometry"]
    dims = geometry["dimensions_m"]
    width = dims["width"]
    depth = dims["depth"]
    height = dims["height"]

    back_width = geometry.get("back_width_m") or width
    front_height = geometry.get("front_height_m") or height

    half_front = width / 2.0
    half_back = back_width / 2.0
    back_y = depth / 2.0

    verts = [
        (-half_front, front_y, 0.0),          # 0 front bottom left
        (half_front, front_y, 0.0),           # 1 front bottom right
        (half_back, back_y, 0.0),             # 2 back bottom right
        (-half_back, back_y, 0.0),            # 3 back bottom left
        (-half_front, front_y, front_height),  # 4 front top left
        (half_front, front_y, front_height),   # 5 front top right
        (half_back, back_y, height),          # 6 back top right
        (-half_back, back_y, height),         # 7 back top left
    ]
    faces = [
        (0, 1, 2, 3),  # bottom
        (4, 5, 6, 7),  # top
        (0, 1, 5, 4),  # front
        (3, 2, 6, 7),  # back
        (0, 4, 7, 3),  # left
        (1, 2, 6, 5),  # right
    ]

    return verts, faces, front_height


def build_body(plan, material_set):
    """The cabinet shell. Returns (object, front plane y, front face height)."""
    dims = plan["geometry"]["dimensions_m"]
    inset = plan["appearance"]["grille"]["inset_m"] or 0.0

    # With a grille, the shell's front plane moves back by the inset so the frame bars can fill
    # the gap and the overall depth still matches the spec exactly.
    front_y = -dims["depth"] / 2.0 + inset
    verts, faces, front_height = _body_geometry(plan, front_y)

    obj = _mesh_object(plan["id"], verts, faces, material_set[materials.CABINET])

    return obj, front_y, front_height


def _box(name, center, size, material):
    x, y, z = center
    half_x, half_y, half_z = size[0] / 2.0, size[1] / 2.0, size[2] / 2.0
    verts = [
        (x - half_x, y - half_y, z - half_z),
        (x + half_x, y - half_y, z - half_z),
        (x + half_x, y + half_y, z - half_z),
        (x - half_x, y + half_y, z - half_z),
        (x - half_x, y - half_y, z + half_z),
        (x + half_x, y - half_y, z + half_z),
        (x + half_x, y + half_y, z + half_z),
        (x - half_x, y + half_y, z + half_z),
    ]
    faces = [
        (0, 1, 2, 3),
        (4, 5, 6, 7),
        (0, 1, 5, 4),
        (3, 2, 6, 7),
        (0, 4, 7, 3),
        (1, 2, 6, 5),
    ]

    return _mesh_object(name, verts, faces, material)


def build_grille(plan, material_set, front_y, front_height):
    """Recessed grille panel plus the four frame bars around it.

    Returns a list of objects, empty when the spec declares no grille inset.

    When the spec has a baffle layout, the panel is left out and only the frame is built: the horns and
    drivers are the whole point of that layout, and a solid panel across the front would hide every one
    of them.
    """
    inset = plan["appearance"]["grille"]["inset_m"] or 0.0
    if inset <= 0.0:
        return []

    dims = plan["geometry"]["dimensions_m"]
    width = dims["width"]
    outer_y = -dims["depth"] / 2.0

    margin = min(_FRAME_MARGIN_MAX, width * _FRAME_MARGIN_RATIO, front_height * _FRAME_MARGIN_RATIO)
    thickness = min(_GRILLE_THICKNESS, inset / 2.0)
    objects = []

    if not plan.get("baffle_layout"):
        panel = _box(
            "%s-grille" % plan["id"],
            center=(0.0, front_y - thickness / 2.0, front_height / 2.0),
            size=(width - 2.0 * margin, thickness, front_height - 2.0 * margin),
            material=material_set[materials.GRILLE],
        )
        objects.append(panel)

    # Frame bars fill the ring between the outer front plane and the shell's front plane.
    bar_depth = inset
    bar_y = outer_y + bar_depth / 2.0
    cabinet = material_set[materials.CABINET]
    bars = [
        ("top", (0.0, bar_y, front_height - margin / 2.0), (width, bar_depth, margin)),
        ("bottom", (0.0, bar_y, margin / 2.0), (width, bar_depth, margin)),
        ("left", (-(width - margin) / 2.0, bar_y, front_height / 2.0), (margin, bar_depth, front_height)),
        ("right", ((width - margin) / 2.0, bar_y, front_height / 2.0), (margin, bar_depth, front_height)),
    ]
    for label, center, size in bars:
        objects.append(_box("%s-frame-%s" % (plan["id"], label), center, size, cabinet))

    return objects


def _side_handle_depth(plan, z):
    """How deep a side handle cup at height `z` may go before it breaks into an opening of the baffle layout.

    A cell, horn or cone that reaches back past the cup's front edge and overlaps it in height leaves only the
    wall between its mouth and the side of the cabinet, and the cup takes that wall less 6 mm. Cut to the full
    20 mm, the SBH's and the kicker's cups showed as holes in the side walls of their mouths, which sit 15 mm
    inside the cabinet's sides. Returns 0 when no usable cup is left.
    """
    dims = plan["geometry"]["dimensions_m"]
    layout = plan.get("baffle_layout") or {}
    cup_front = dims["depth"] / 2.0 - _HANDLE_SIZE[0] / 2.0
    cup_bottom, cup_top = z - _HANDLE_SIZE[1] / 2.0, z + _HANDLE_SIZE[1] / 2.0
    depth = _HANDLE_DEPTH
    for feature in layout.get("features", []):
        if feature.get("inside") or feature["kind"] in ("fin", "grille", "plug"):
            continue
        reach = feature["depth_m"]
        if feature["kind"] == "horn" and feature.get("cone_diameter_m"):
            # The driver's chamber behind the throat, as `drivers.build_features()` bores it.
            reach += 0.13
        elif feature["kind"] == "cell" and feature.get("angle_deg"):
            extent = feature["mouth_m"][0] if feature.get("turn") == "yaw" else feature["mouth_m"][1]
            reach += extent / 2.0 * abs(math.tan(math.radians(feature["angle_deg"])))
        at_x, at_z = feature["at_m"]
        half_w, half_h = feature["mouth_m"][0] / 2.0, feature["mouth_m"][1] / 2.0
        centre_z = at_z + dims["height"] / 2.0
        if reach <= cup_front or centre_z + half_h <= cup_bottom or centre_z - half_h >= cup_top:
            continue
        depth = min(depth, dims["width"] / 2.0 - (abs(at_x) + half_w) - _HANDLE_WALL_M)

    return depth if depth >= _HANDLE_MIN_DEPTH else 0.0


def cut_handles(plan, body, front_height):
    """Cut handle recesses into the shell for every side listed in the spec.

    Booleans are applied immediately and the cutter deleted, so nothing invisible is left behind
    to leak into the export, and the cabinet's outer dimensions stay exactly as declared.
    """
    handles = plan["physical"]["handles"]
    if not handles:
        return

    dims = plan["geometry"]["dimensions_m"]
    width, depth, height = dims["width"], dims["depth"], dims["height"]
    long_side, short_side = _HANDLE_SIZE
    z = front_height * _HANDLE_HEIGHT_RATIO

    side_depth = _side_handle_depth(plan, z)
    placements = {
        # side: (center, size) — the cutter reaches slightly past the surface so the cut is clean.
        "left": ((-width / 2.0, 0.0, z), (side_depth * 2.0, long_side, short_side)),
        "right": ((width / 2.0, 0.0, z), (side_depth * 2.0, long_side, short_side)),
        "back": ((0.0, depth / 2.0, z), (long_side, _HANDLE_DEPTH * 2.0, short_side)),
        "top": ((0.0, 0.0, height), (long_side, short_side, _HANDLE_DEPTH * 2.0)),
    }

    for side in handles:
        placement = placements.get(side)
        if placement is None:
            print("sdwa5-3d: ignoring unknown handle side %r" % side)
            continue
        center, size = placement
        if size[0] <= 0.0:
            print("sdwa5-3d: no wall left beside the baffle openings for a %s handle, skipping" % side)
            continue
        # Too deep a recess for a shallow cabinet would punch through it.
        if min(width, depth, height) <= _HANDLE_DEPTH * 2.5:
            print("sdwa5-3d: cabinet too small for handle recesses, skipping")
            return

        cutter = _box("%s-handle-cut-%s" % (plan["id"], side), center, size, None)
        bpy.context.collection.objects.link(cutter)

        modifier = body.modifiers.new(name="handle-%s" % side, type="BOOLEAN")
        modifier.operation = "DIFFERENCE"
        modifier.object = cutter
        modifier.solver = "FAST"

        bpy.context.view_layer.objects.active = body
        bpy.ops.object.modifier_apply(modifier=modifier.name)

        bpy.data.objects.remove(cutter, do_unlink=True)


def _cylinder(name, center, radius, length, axis, material, segments=24):
    """A closed cylinder along world axis `axis` (0 = x, 1 = y, 2 = z)."""
    others = [index for index in range(3) if index != axis]
    verts = []
    for end in (-length / 2.0, length / 2.0):
        for step in range(segments):
            angle = 2.0 * math.pi * step / segments
            point = list(center)
            point[axis] += end
            point[others[0]] += radius * math.cos(angle)
            point[others[1]] += radius * math.sin(angle)
            verts.append(tuple(point))
    faces = [tuple(range(segments)), tuple(range(segments, 2 * segments))]
    faces += [(k, (k + 1) % segments, segments + (k + 1) % segments, segments + k) for k in range(segments)]

    return _mesh_object(name, verts, faces, material)


def build_castors(plan, material_set):
    """Four castors on the face `physical.castors` names, one near each corner, outside the declared box.

    Each is a mounting plate, a swivel, a fork and the wheel in its own colour, and the `locking` ones nearest the
    floor get a brake pedal. They stand `protrusion_m` off the face, the figure tools/check-glb.py allows on that
    axis. The wheel's axle lies across the face, so the cabinet rolls along its height when it lies on them.
    """
    castors = plan["physical"].get("castors")
    if not castors:
        return []

    dims = plan["geometry"]["dimensions_m"]
    diameter = castors["diameter_m"]
    face = castors["face"]
    # The face as (normal axis, its sign, the axis across it, the extent across it).
    normal, sign, across, extent = {
        "back": (1, 1.0, 0, dims["width"]),
        "left": (0, -1.0, 1, dims["depth"]),
        "right": (0, 1.0, 1, dims["depth"]),
    }[face]
    surface = sign * (dims["depth"] if normal == 1 else dims["width"]) / 2.0
    inset = max(0.06, diameter * 0.8)
    plate, wheel_width = diameter * 0.8, diameter * 0.35
    hardware = material_set[materials.HARDWARE]
    wheel = materials.feature("castor", castors["color"], 0.6) if castors["color"] else hardware

    def point(off, out, z):
        """World coordinates of `off` across the face, `out` off it and `z` up."""
        coords = [0.0, 0.0, z]
        coords[across] = off
        coords[normal] = surface + sign * out
        return tuple(coords)

    def size(off, out, z):
        sizes = [0.0, 0.0, z]
        sizes[across] = off
        sizes[normal] = out
        return tuple(sizes)

    objects = []
    offsets = (-extent / 2.0 + inset, extent / 2.0 - inset)
    corners = [(off, z) for z in (inset, dims["height"] - inset) for off in offsets]
    for index, (off, z) in enumerate(corners):
        name = "%s-castor-%d" % (plan["id"], index + 1)
        axle = castors["protrusion_m"] - diameter / 2.0
        objects.append(_box(name + "-plate", point(off, 0.003, z), size(plate, 0.006, plate), hardware))
        objects.append(_cylinder(name + "-swivel", point(off, 0.006 + diameter * 0.06, z), diameter * 0.25,
                                 diameter * 0.12, normal, hardware))
        for side in (-1.0, 1.0):
            objects.append(_box(
                name + "-fork-%s" % ("a" if side < 0 else "b"),
                point(off + side * (wheel_width / 2.0 + 0.005), (axle + 0.018) / 2.0, z),
                size(0.004, axle + 0.006, diameter * 0.45), hardware,
            ))
        objects.append(_cylinder(name + "-wheel", point(off, axle, z), diameter / 2.0, wheel_width, across, wheel))
        if index < castors["locking"]:
            objects.append(_box(name + "-brake", point(off, axle * 0.55, z + diameter * 0.32),
                                size(wheel_width * 1.4, 0.008, diameter * 0.22), material_set[materials.RIGGING]))

    return objects


def build_rigging_markers(plan, material_set):
    """Small markers at the suspension points.

    Hidden from renders on purpose: they exist to snap to while building a setup, and would
    otherwise show up as odd metal cubes in a preview image.
    """
    objects = []
    for point in plan["rigging"]["points"]:
        marker = _box(
            "%s-rig-%s" % (plan["id"], point["id"]),
            center=tuple(point["position_m"]),
            size=(_RIGGING_MARKER_SIZE,) * 3,
            material=material_set[materials.RIGGING],
        )
        marker.hide_render = True
        objects.append(marker)

    return objects


def build_estimated_marker(plan, material_set):
    """Orange tag on cabinets whose numbers are only estimated.

    Also hidden from renders — its job is to nag whoever opens the model, not to appear in a
    preview handed to the rest of the crew.
    """
    if not plan["appearance"].get("mark_estimated"):
        return []

    dims = plan["geometry"]["dimensions_m"]
    marker = _box(
        "%s-estimated" % plan["id"],
        center=(
            -dims["width"] / 2.0 + _ESTIMATED_MARKER_SIZE,
            -dims["depth"] / 2.0 - _ESTIMATED_MARKER_SIZE,
            dims["height"] + _ESTIMATED_MARKER_SIZE,
        ),
        size=(_ESTIMATED_MARKER_SIZE,) * 3,
        material=material_set[materials.ESTIMATED],
    )
    marker.hide_render = True

    return [marker]


def add_chamfer(body, chamfer):
    """Bevel the shell's edges.

    Applied immediately rather than left live, and that is load-bearing rather than tidiness. The baffle
    openings are carved into this same shell *after* this runs, so a live bevel would still be sitting in
    the modifier stack when the export evaluated it — and it would then round the carved mouths' rims as
    well as the cabinet's own corners. Where three of those rims met, on the Tecnare's mid horn, it pushed
    two vertices **0.9 mm in front of the baffle plane**, which broke the one promise the whole library
    rests on: that a model's bounding box equals its declared dimensions. `tools/check-glb.py` caught it.

    Applying here means the openings are cut into an already-chamfered shell, which is also the right way
    round physically: the cabinet is built and its corners eased, then the baffle is cut.

    The cost is that the .blend no longer carries an editable bevel. Same trade the handle booleans already
    make one function up, and for the same reason.
    """
    if chamfer <= 0.0:
        return

    modifier = body.modifiers.new(name="chamfer", type="BEVEL")
    modifier.width = chamfer
    modifier.segments = 2
    modifier.limit_method = "ANGLE"
    modifier.angle_limit = 0.5236  # 30°, so only real corners get rounded

    bpy.context.view_layer.objects.active = body
    bpy.ops.object.modifier_apply(modifier=modifier.name)
