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

    placements = {
        # side: (center, size) — the cutter reaches slightly past the surface so the cut is clean.
        "left": ((-width / 2.0, 0.0, z), (_HANDLE_DEPTH * 2.0, long_side, short_side)),
        "right": ((width / 2.0, 0.0, z), (_HANDLE_DEPTH * 2.0, long_side, short_side)),
        "back": ((0.0, depth / 2.0, z), (long_side, _HANDLE_DEPTH * 2.0, short_side)),
        "top": ((0.0, 0.0, height), (long_side, short_side, _HANDLE_DEPTH * 2.0)),
    }

    for side in handles:
        placement = placements.get(side)
        if placement is None:
            print("sdwa5-3d: ignoring unknown handle side %r" % side)
            continue
        center, size = placement
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
