"""Scaffold geometry — posts, bracing and a platform, for `shape: scaffold`.

Open like a truss and drawn from tubes for the same reason: a 5 m solid block standing beside a rig would hide it.
What makes it a scaffold rather than a truss is the **platform**, so that is the one solid part.

GUARDRAILS AND RUNGS ARE NOT MODELLED. The four posts, the bracing and the platform are what make the shape read as
a scaffold at a glance; a guardrail is another four tubes at a height nobody published. Same judgement as a
cabinet's ports — modelled where there is a source, left out where there is not.

`platform_height_m` is where somebody stands and is NOT the working height. A tower sold as "AH7" has its platform
at 5 m, because the convention adds two metres for a person's reach. That two metres is a fact about people, so it
stays in the notes and out of the bounding box.
"""

from . import materials, tubes


def _parts(dims, spec):
    """Four posts, bracing on all four sides, and the platform."""
    width = dims["width"]
    depth = dims["depth"]
    height = dims["height"]

    post = spec["post_diameter_m"]
    brace = spec["brace_diameter_m"]
    platform_height = spec["platform_height_m"]
    platform_thickness = spec["platform_thickness_m"]

    radius = post / 2.0
    half_x = width / 2.0 - radius
    half_y = depth / 2.0 - radius

    corners = [
        (-half_x, -half_y), (half_x, -half_y),
        (half_x, half_y), (-half_x, half_y),
    ]

    # The posts, floor to full frame height.
    for x, y in corners:
        yield "tube", (x, y, 0.0), (x, y, height), post

    # One diagonal brace per side per lift, alternating direction so the frame reads as braced rather than as a
    # ladder. A lift is the platform height divided into whole steps of about a metre, which is what a scaffold's
    # frames actually come in — and whole steps mean the top brace lands on the platform rather than through it.
    lifts = max(1, int(round(platform_height / 1.0)))
    lift = platform_height / lifts

    for index in range(4):
        first = corners[index]
        second = corners[(index + 1) % 4]
        for step in range(lifts):
            z0 = step * lift
            z1 = z0 + lift
            near, far = (first, second) if step % 2 == 0 else (second, first)
            yield (
                "tube",
                (near[0], near[1], z0),
                (far[0], far[1], z1),
                brace,
            )

    # The platform, sitting with its top face at the stated height — that is the surface somebody stands on.
    yield (
        "box",
        (0.0, 0.0, platform_height - platform_thickness / 2.0),
        (width, depth, platform_thickness),
    )


def build(plan, material_set):
    """The whole tower as one object."""
    geometry = plan["geometry"]
    spec = geometry["scaffold"]

    verts = []
    faces = []
    for part in _parts(geometry["dimensions_m"], spec):
        if part[0] == "box":
            tubes.add_box(verts, faces, part[1], part[2])
        else:
            tubes.add_tube(verts, faces, part[1], part[2], part[3])

    return tubes.mesh_object(plan["id"], verts, faces, material_set[materials.CABINET])
