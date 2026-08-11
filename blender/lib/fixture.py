"""Moving-head geometry — base, yoke and head, for `shape: moving-head`.

**A moving head's shape is its identity.** A rack really is a box and loses nothing by being drawn as one; a moving
head drawn as a box is unrecognisable, and four of them hung on a truss would read as four flight cases. So the
three parts are built: a base, two yoke arms rising from it, and the head slung between them.

Conventions are the shared ones: metres, +Z up, geometry in bottom-center coordinates so the outer bounding box is
exactly width × depth × height.

PAN AND TILT ARE ZERO — the head points straight up. That is the pose every datasheet quotes its height in, and so
the only pose in which `dimensions_m` is true. A fixture's aim is a cue rather than a dimension; a scene that wants
one aimed uses `yaw_deg` and `pitch_deg` like anything else.

THE BOX IS THE INPUT. The base sits on the floor and the head's top touches the box's top, so the parts are derived
from the stated dimensions rather than adding up to whatever they like — the same rule the truss chords follow.
"""

from . import materials, tubes


def _parts(dims, spec):
    """The base box, the two yoke arms and the head, as drawable primitives.

    Yields ('box', centre, size) and ('tube', start, end, diameter) so `build` can stay a loop.
    """
    width = dims["width"]
    depth = dims["depth"]
    height = dims["height"]

    base_height = spec["base_height_m"]
    arm = spec["yoke_arm_thickness_m"]
    head_diameter = spec["head_diameter_m"]
    head_length = spec["head_length_m"]

    # The base carries the electronics and the pan bearing: full footprint, its own height.
    yield "box", (0.0, 0.0, base_height / 2.0), (width, depth, base_height)

    # The head hangs with its top at the box's top, which is what the quoted height means.
    head_top = height
    head_bottom = head_top - head_length
    head_centre_z = (head_top + head_bottom) / 2.0

    # Yoke arms rise from the base to the head's tilt axis, one at each side. Round tubes: a yoke arm is a casting
    # with a rounded section, and a tube reads far closer to it than a slab would.
    arm_x = (width - arm) / 2.0
    for side in (-1.0, 1.0):
        yield (
            "tube",
            (side * arm_x, 0.0, base_height),
            (side * arm_x, 0.0, head_centre_z),
            arm,
        )

    # The head itself, a cylinder on the tilt axis — which runs across the fixture, so along X.
    half_head = (width - 2.0 * arm) / 2.0
    yield (
        "tube",
        (-half_head, 0.0, head_centre_z),
        (half_head, 0.0, head_centre_z),
        min(head_diameter, head_length),
    )

    # The lens end, pointing up. A short stub of the head's diameter, so the fixture reads as having a front.
    yield (
        "tube",
        (0.0, 0.0, head_centre_z),
        (0.0, 0.0, head_top),
        head_diameter,
    )


def build(plan, material_set):
    """The whole fixture as one object.

    One mesh, as for a truss: it is exported as a single asset and nothing downstream wants the yoke separately.
    """
    geometry = plan["geometry"]
    spec = geometry["moving_head"]

    verts = []
    faces = []
    for part in _parts(geometry["dimensions_m"], spec):
        if part[0] == "box":
            tubes.add_box(verts, faces, part[1], part[2])
        else:
            tubes.add_tube(verts, faces, part[1], part[2], part[3])

    return tubes.mesh_object(plan["id"], verts, faces, material_set[materials.CABINET])
