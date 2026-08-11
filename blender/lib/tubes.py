"""A capped cylinder between two arbitrary points — the primitive every open-frame device is built from.

Extracted from `truss.py`, which had it first and needed nothing else. Three builders want it now: truss chords and
bracing, a moving head's yoke arms, and a scaffold's posts and braces. All three are the same problem — a tube from
here to there, at any angle — and none of them can use `drivers.py`'s ring machinery, which is locked to the Y axis
because a driver cone only ever points one way.

Vertices and faces are appended to lists the caller owns, so a device with a hundred tubes ends up as one mesh
rather than a hundred objects.
"""

import math

# Vertices per ring. A 50 mm chord in a picture metres wide already reads as round at ten, and a smoother tube would
# spend geometry nobody can see — the same reasoning as the cone rings in drivers.py.
DEFAULT_SIDES = 10


def _normalise(vector):
    length = math.sqrt(sum(component * component for component in vector))
    if length == 0.0:
        raise ValueError("a tube cannot have zero length")

    return tuple(component / length for component in vector)


def _cross(a, b):
    return (
        a[1] * b[2] - a[2] * b[1],
        a[2] * b[0] - a[0] * b[2],
        a[0] * b[1] - a[1] * b[0],
    )


def _perpendicular_basis(axis):
    """Two unit vectors spanning the plane across `axis`, for laying a ring out on.

    The helper vector is chosen along whichever world axis the tube leans on *least*, because crossing with a
    nearly-parallel vector loses all its precision — and these devices have tubes along X, up Z and diagonal
    between, so no single fixed helper is safe for all of them.
    """
    smallest = min(range(3), key=lambda index: abs(axis[index]))
    helper = tuple(1.0 if index == smallest else 0.0 for index in range(3))

    u = _normalise(_cross(axis, helper))

    return u, _cross(axis, u)


def add_tube(verts, faces, start, end, diameter, sides=DEFAULT_SIDES):
    """One capped cylinder between two points, appended to a shared vertex and face list.

    Capped, because an open end shows as a hole from any angle that can see into it — and the cut ends of a truss's
    diagonals and a scaffold's braces all face outwards.
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


def add_box(verts, faces, centre, size):
    """An axis-aligned box, for the solid parts of an otherwise open frame — a scaffold platform, a fixture base."""
    half = tuple(component / 2.0 for component in size)
    base = len(verts)

    for dz in (-1, 1):
        for dy in (-1, 1):
            for dx in (-1, 1):
                verts.append((
                    centre[0] + dx * half[0],
                    centre[1] + dy * half[1],
                    centre[2] + dz * half[2],
                ))

    # Corner order above is x fastest, then y, then z, which gives these six quads.
    for quad in (
        (0, 1, 3, 2), (4, 5, 7, 6),  # bottom, top
        (0, 1, 5, 4), (2, 3, 7, 6),  # front, back
        (0, 2, 6, 4), (1, 3, 7, 5),  # left, right
    ):
        faces.append(tuple(base + index for index in quad))


def mesh_object(name, verts, faces, material):
    """One object from accumulated tubes and boxes, with normals recalculated.

    Shared because all three open-frame builders finish the same way, and because hand-written face winding is not
    to be trusted — bmesh sorts the normals out.
    """
    import bmesh
    import bpy

    mesh = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bm_verts = [bm.verts.new(vert) for vert in verts]
    for face in faces:
        bm.faces.new([bm_verts[index] for index in face])
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(mesh)
    bm.free()

    obj = bpy.data.objects.new(name, mesh)
    obj.data.materials.append(material)

    return obj
