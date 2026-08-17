"""A transporter drawn as a cage — the vehicle's outline with its load bay inside it, for `shape: load-bay`.

**What you pack into is the inside, so the inside is what this draws.** Stated by the owner: the vans need at least
wire-type models so a pack can be planned. A solid 6.8 m van would be the largest object in any picture that
included it and would hide the very thing it is there to help with.

Three parts, each answering a different question a packer asks:

* **The vehicle outline**, from `dimensions_m`. It is there for scale and because the model's bounding box has to
  equal the declared dimensions — `tools/check-glb.py` checks a model against its own metadata, and a bay-only
  model would fail that check while being perfectly correct about the bay.
* **The load bay**, in its own colour. This is the volume cabinets go in.
* **The floor between the wheel arches**, when the spec states it. On our Movano the bay is 1.765 m wide and
  1.380 m between the arches, so 385 mm of that width exists only above arch height — which is the difference
  between a cabinet fitting on the floor and not.

**WHERE THE BAY SITS INSIDE THE OUTLINE IS A DIAGRAM, NOT A CLAIM.** It is drawn flush to one end, centred across,
and resting on the vehicle's own floor line. The real load floor is roughly half a metre up and **no registration
document states it**, so drawing it there would be inventing a number. Neither omission matters to a pack: what
decides a pack is the bay's internal dimensions and that cabinets stand on its floor, and both are true here.

Bars rather than a Wireframe modifier, which would need applying before the glTF export and would give no control
over which edges exist. Twelve boxes per cage is simpler to reason about and exports as it stands.
"""

import bpy

from . import materials, tubes

# 25 mm bars, the same gauge the fault cages in `build_scene.py` use: thick enough to read in a 960 x 540 preview,
# thin enough not to swallow a 0.4 m cabinet standing beside one.
BAR_M = 0.025

# The arch band is a floor area rather than a volume, so it is drawn as a thin slab instead of a cage.
ARCH_SLAB_M = 0.012


def build(plan, material_set):
    """The whole cage as one object, with a material per part."""
    geometry = plan["geometry"]
    dims = geometry["dimensions_m"]
    # **Null when nobody has measured the inside, and that is a picture rather than an error.** A bay is optional —
    # a van can be specified from its registration document before anybody has been in the back of it, and no
    # registration document states a load bay. An outline with nothing caged inside it says exactly that.
    bay = geometry.get("load_bay")

    verts = []
    faces = []
    # Which material each face belongs to, in the order the faces are appended — the mesh gets all three materials
    # and every polygon points at one of them.
    face_material = []

    def emit(centre, size, slot):
        before = len(faces)
        tubes.add_box(verts, faces, centre, size)
        face_material.extend([slot] * (len(faces) - before))

    _cage(emit, _box(dims["width"], dims["depth"], dims["height"]), slot=0)

    if bay is not None:
        # Flush to one end and centred across. See the note at the top of this file for why the depth offset is a
        # diagram rather than a measurement.
        front = dims["depth"] / 2.0 - bay["depth"]
        _cage(emit, {
            "x": (-bay["width"] / 2.0, bay["width"] / 2.0),
            "y": (front, dims["depth"] / 2.0),
            "z": (0.0, bay["height"]),
        }, slot=1)

        arches = bay.get("width_between_arches")
        if arches is not None and arches < bay["width"]:
            emit(
                (0.0, (front + dims["depth"] / 2.0) / 2.0, ARCH_SLAB_M / 2.0),
                (arches, bay["depth"], ARCH_SLAB_M),
                2,
            )

    body = tubes.mesh_object(plan["id"], verts, faces, material_set[materials.VEHICLE_OUTLINE])
    mesh = body.data
    mesh.materials.append(material_set[materials.BAY])
    mesh.materials.append(material_set[materials.BAY_FLOOR])
    for index, polygon in enumerate(mesh.polygons):
        polygon.material_index = face_material[index] if index < len(face_material) else 0

    return body


def _box(width, depth, height):
    """A box centred on the origin in plan and standing on it, as the ranges `_cage` reads."""
    return {
        "x": (-width / 2.0, width / 2.0),
        "y": (-depth / 2.0, depth / 2.0),
        "z": (0.0, height),
    }


def _cage(emit, ranges, slot):
    """The twelve edges of a box, each a thin bar of the given material slot.

    **The bars are inset so the cage's outer surface is the declared box**, rather than centred on its edges. A bar
    straddling an edge puts half its thickness outside, and `tools/check-glb.py` compares a model's bounding box
    against its own `dimensions_m` — a 25 mm bar centred on each edge made the Movano come out 2.095 x 2.833 x
    6.873 m against a declared 2.070 x 2.808 x 6.848, failing on all three axes and on the origin, since the
    lowest point sat 12.5 mm below the floor. Correct as a diagram and wrong as a model of a 2.070 m van.
    """
    (x0, x1), (y0, y1), (z0, z1) = ranges["x"], ranges["y"], ranges["z"]
    span_x, span_y, span_z = x1 - x0, y1 - y0, z1 - z0
    mid_x, mid_y, mid_z = (x0 + x1) / 2.0, (y0 + y1) / 2.0, (z0 + z1) / 2.0
    half = BAR_M / 2.0

    # Each edge is named by the two extremes it lies on, and each of those pulls the bar inward by half its width.
    for y, dy in ((y0, half), (y1, -half)):
        for z, dz in ((z0, half), (z1, -half)):
            emit((mid_x, y + dy, z + dz), (span_x, BAR_M, BAR_M), slot)
    for x, dx in ((x0, half), (x1, -half)):
        for z, dz in ((z0, half), (z1, -half)):
            emit((x + dx, mid_y, z + dz), (BAR_M, span_y, BAR_M), slot)
    for x, dx in ((x0, half), (x1, -half)):
        for y, dy in ((y0, half), (y1, -half)):
            emit((x + dx, y + dy, mid_z), (BAR_M, BAR_M, span_z), slot)
