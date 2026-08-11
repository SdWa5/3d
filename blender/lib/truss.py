"""Truss geometry — chords and bracing, for `shape: truss`.

Every other shape in the library is a hexahedron built by {geometry.build_body}. A truss is not, and the reason is
not fidelity for its own sake: **a truss is mostly air**. Drawn as its bounding box, a 9 m run would stand as a
solid wall hiding the entire rig behind it in every render, and the picture would be worse than no truss at all.

Conventions are the shared ones (see docs/conventions.md): metres, +Z up, +X the device's right, geometry built in
bottom-center coordinates so the outer bounding box is exactly width × depth × height. A segment therefore **lies
along X** — its `width` is the length of the span, and `depth` and `height` are the cross-section.

THE BOUNDING BOX IS THE INPUT, NOT THE OUTPUT. Chord centres are derived *inwards* from the stated box by one chord
radius, so the tubes touch its faces exactly and the box stays the truth the rest of the repository measures a truss
by — scene placement, the overlap sweep and the catalog's shipping volume all read `dimensions_m` and none of them
knows a truss from a sub.

A three-chord segment is built **apex up**: two chords along the bottom, one along the top. Real triangular truss is
flown either way round, and a scene that wants it inverted has `roll_deg: 180` — the same key the Flexy rows use.
"""

import math

import bmesh
import bpy

from . import materials

# Vertices per tube ring. A chord is 50 mm across in a picture metres wide, so ten sides already read as round and
# a smoother tube would spend geometry nobody can see — the same reasoning as the cone rings in drivers.py.
_TUBE_SIDES = 10


def _normalise(vector):
    length = math.sqrt(sum(component * component for component in vector))
    if length == 0.0:
        raise ValueError("a truss member cannot have zero length")

    return tuple(component / length for component in vector)


def _cross(a, b):
    return (
        a[1] * b[2] - a[2] * b[1],
        a[2] * b[0] - a[0] * b[2],
        a[0] * b[1] - a[1] * b[0],
    )


def _perpendicular_basis(axis):
    """Two unit vectors spanning the plane across `axis`, for laying a ring out on.

    The helper vector is chosen along whichever world axis the member leans on *least*, because crossing with a
    nearly-parallel vector loses all its precision — and a truss has members along X, across Y and diagonal, so
    no single fixed helper is safe for all of them.
    """
    smallest = min(range(3), key=lambda index: abs(axis[index]))
    helper = tuple(1.0 if index == smallest else 0.0 for index in range(3))

    u = _normalise(_cross(axis, helper))

    return u, _cross(axis, u)


def _add_tube(verts, faces, start, end, diameter, sides=_TUBE_SIDES):
    """One capped cylinder between two arbitrary points, appended to a shared vertex and face list.

    Capped, because an open tube end shows as a hole from any angle that can see into it — and on a truss, the
    cut ends of every diagonal face outwards.
    """
    axis = _normalise(tuple(end[index] - start[index] for index in range(3)))
    u, v = _perpendicular_basis(axis)
    radius = diameter / 2.0

    base = len(verts)
    for point in (start, end):
        for step in range(sides):
            angle = 2.0 * math.pi * step / sides
            offset = tuple(
                radius * (math.cos(angle) * u[index] + math.sin(angle) * v[index])
                for index in range(3)
            )
            verts.append(tuple(point[index] + offset[index] for index in range(3)))

    for step in range(sides):
        nxt = (step + 1) % sides
        faces.append((base + step, base + nxt, base + sides + nxt, base + sides + step))

    # Both caps as n-gons. Winding is left to bmesh, which recalculates normals for the whole mesh anyway.
    faces.append(tuple(base + step for step in range(sides)))
    faces.append(tuple(base + sides + step for step in range(sides)))


def _chord_positions(dims, chords, radius):
    """Where each chord's centre line sits in the cross-section, as (y, z).

    Derived inwards from the stated bounding box by one radius, so the tubes touch its faces and the box is exactly
    what the spec says it is.
    """
    half_y = dims["depth"] / 2.0 - radius
    low = radius
    high = dims["height"] - radius

    if chords == 3:
        # Apex up: the flat pair carries, the single chord rides the top.
        return [(-half_y, low), (half_y, low), (0.0, high)]

    if chords == 4:
        return [(-half_y, low), (half_y, low), (half_y, high), (-half_y, high)]

    # Two chords is a ladder, and which way it lies is whichever cross-section axis is the longer — a flat ladder
    # states a wide depth, an upright one a tall height.
    if dims["depth"] >= dims["height"]:
        return [(-half_y, dims["height"] / 2.0), (half_y, dims["height"] / 2.0)]

    return [(0.0, low), (0.0, high)]


def _faces_between(chords):
    """Which pairs of chords have bracing between them: every adjacent pair around the section.

    Two chords have one face, not two — the pair is braced once, where wrapping around a triangle or a box comes
    back to where it started and braces every side.
    """
    if chords == 2:
        return [(0, 1)]

    return [(index, (index + 1) % chords) for index in range(chords)]


def _members(dims, spec):
    """Every tube in the segment, as (start, end, diameter)."""
    radius = spec["chord_diameter_m"] / 2.0
    length = dims["width"]
    half_x = length / 2.0
    section = _chord_positions(dims, spec["chords"], radius)

    for y, z in section:
        yield (-half_x, y, z), (half_x, y, z), spec["chord_diameter_m"]

    # Whole bays only, so the zigzag meets the segment's ends squarely instead of being cut off mid-diagonal.
    # The stated bay length is what the pitch is rounded to, and the actual pitch divides the span exactly.
    bays = max(1, int(round(length / spec["bay_length_m"])))
    pitch = length / bays

    for first, second in _faces_between(spec["chords"]):
        for bay in range(bays):
            x0 = -half_x + bay * pitch
            x1 = x0 + pitch
            # Alternating, which is what makes it a zigzag rather than a row of parallel struts.
            near, far = (first, second) if bay % 2 == 0 else (second, first)
            yield (
                (x0, section[near][0], section[near][1]),
                (x1, section[far][0], section[far][1]),
                spec["diagonal_diameter_m"],
            )


def build(plan, material_set):
    """The whole segment as one object. Returns it, ready to link and parent extras to.

    One mesh rather than an object per tube: a truss is a single rigid thing, it is exported as one asset, and a
    9 m run would otherwise be a hundred objects in the outliner for no gain.
    """
    geometry = plan["geometry"]
    spec = geometry["truss"]

    verts = []
    faces = []
    for start, end, diameter in _members(geometry["dimensions_m"], spec):
        _add_tube(verts, faces, start, end, diameter)

    mesh = bpy.data.meshes.new(plan["id"])
    bm = bmesh.new()
    bm_verts = [bm.verts.new(vert) for vert in verts]
    for face in faces:
        bm.faces.new([bm_verts[index] for index in face])
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(mesh)
    bm.free()

    obj = bpy.data.objects.new(plan["id"], mesh)
    obj.data.materials.append(material_set[materials.CABINET])

    return obj
