"""Driver cones and horn flares on the front baffle.

Until this existed, a cabinet's CAD cut the holes and left nothing behind them — so any view that saw
into a cabinet saw an empty box. These are the parts you actually look at.

Everything is built recessed **behind** the baffle plane, which keeps a model's bounding box equal to
its declared dimensions. That is the guarantee the whole library rests on, and adding scenery to the
front of a cabinet is the easiest way to break it by accident.

Geometry conventions follow the rest of the builder: metres, +Z up, front towards −Y. A feature's
`at_m` is `[x, z]` in the baffle frame — origin at the centre of the front face.

Both shapes are the same construction: a stack of rings along −Y with quads between them. Nothing here
uses Blender's mesh primitives. They add caps you cannot switch off — a truncated cone primitive fills
its wide end with an n-gon, which is exactly what made a driver read as a flat disc — and they arrive
rotated and scaled, which would break the assumption tools/check-glb.py makes about untransformed
objects.
"""

import math

import bmesh
import bpy

from . import materials

# Mouth cross-sections. A pyramid takes `sides` (4 = the usual rectangular flare, 8 = an octagonal one);
# an elliptical mouth is the same ring generator run at full resolution.
PYRAMID = "pyramid"
ELLIPTICAL = "elliptical"

# How the cross-section grows from throat to mouth. Linear is a straight-walled conical horn; exponential
# grows the area exponentially with axial distance, which is what most real horns do.
LINEAR = "linear"
EXPONENTIAL = "exponential"

_ELLIPSE_SEGMENTS = 40

# Rings along an exponential flare. A linear one needs two by definition; a curve needs enough to read as
# a curve rather than as a bevelled cone.
_FLARE_STEPS = 12

# A cone's dust cap, as a fraction of the driver's diameter. Real caps run 20-35%; this reads correctly
# at the fidelity these models are built to without pretending to be a particular driver.
_DUST_CAP_RATIO = 0.28

# How far the cap domes forward out of the cone, again as a fraction of the diameter.
_DUST_CAP_RISE = 0.06

_DOME_RINGS = 4

# How far a cutter reaches past the surface it breaks through, and how far two cutters overlap each other.
# Coincident or coplanar faces are what the boolean solver is worst at, and every use of this constant is
# there to avoid producing one.
_CUTTER_OVERLAP = 0.01

# The moving assembly seen from the front, as (radius fraction, depth fraction) pairs: the frame lip sits
# slightly recessed, the surround's half-roll crests level with the baffle, and the cone proper starts
# behind it. The crest is the reason a driver reads as a driver — a bare cone reads as a funnel.
_SURROUND_PROFILE = (
    (1.00, 0.10),
    (0.96, 0.00),
    (0.90, 0.10),
)


def _ring(centre, half_w, half_h, y, sides):
    """One cross-section at depth `y`, as a list of vertices.

    `sides` None or 0 gives an ellipse; an integer gives an n-gon phased and scaled so it touches the
    half_w/half_h bounds — 4 sides land exactly on the corners of the mouth rectangle, 8 give the
    familiar octagon with the corners cut off. A zero-size ring collapses to a single vertex, which is
    how both an apex and a flat cap are expressed.
    """
    cx, cz = centre
    if half_w <= 1e-9 and half_h <= 1e-9:
        return [(cx, y, cz)]

    if sides:
        count = int(sides)
        phase = math.pi / count
        # Divide by cos(phase) so the polygon is circumscribed about the ellipse through the bounds
        # rather than inscribed in it: with 4 sides that is the difference between the mouth rectangle
        # the spec asked for and one 71% of its size.
        norm = math.cos(phase)
    else:
        count = _ELLIPSE_SEGMENTS
        phase = 0.0
        norm = 1.0

    ring = []
    for index in range(count):
        angle = phase + 2.0 * math.pi * index / count
        ring.append((
            cx + half_w * math.cos(angle) / norm,
            y,
            cz + half_h * math.sin(angle) / norm,
        ))

    return ring


def _shell_geometry(rings):
    """Stitch a stack of rings into vertices and faces: quads between equal-length rings, triangles into
    an apex."""
    verts = []
    offsets = []
    for ring in rings:
        offsets.append(len(verts))
        verts.extend(ring)

    faces = []
    for index in range(len(rings) - 1):
        near, far = rings[index], rings[index + 1]
        near_at, far_at = offsets[index], offsets[index + 1]

        if len(near) == 1:
            for k in range(len(far)):
                faces.append((near_at, far_at + k, far_at + (k + 1) % len(far)))
        elif len(far) == 1:
            for k in range(len(near)):
                faces.append((near_at + k, near_at + (k + 1) % len(near), far_at))
        else:
            for k in range(len(near)):
                k2 = (k + 1) % len(near)
                faces.append((near_at + k, near_at + k2, far_at + k2, far_at + k))

    return verts, faces


def _mesh_object(name, verts, faces, material):
    mesh = bpy.data.meshes.new(name)
    bm = bmesh.new()
    bm_verts = [bm.verts.new(vert) for vert in verts]
    for face in faces:
        bm.faces.new([bm_verts[i] for i in face])
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(mesh)
    bm.free()

    obj = bpy.data.objects.new(name, mesh)
    if material is not None:
        obj.data.materials.append(material)

    return obj


def _shell(name, rings, material):
    verts, faces = _shell_geometry(rings)

    return _mesh_object(name, verts, faces, material)


def _flare_rings(
    mouth, throat, front_y, depth, centre,
    profile=PYRAMID, sides=4, flare=LINEAR, cap_throat=True, cap_mouth=False, bore_depth=0.0,
):
    """Rings of a horn: `mouth` at the baffle, narrowing to `throat` at `depth`.

    The mouth is normally open — a horn is a hole you look into. The throat is closed off by default so
    you cannot see straight through the cabinet, but stays open when a driver sits behind it, which is
    the whole point of a horn-loaded cabinet. `cap_mouth` closes the front as well, which only boolean
    cutters want: the solver is happiest with a closed volume.

    `bore_depth` carries the throat's cross-section straight on past the throat, which is the driver
    chamber behind a horn-loaded cabinet's aperture. It belongs in this one shell rather than in a second
    cutter: two interpenetrating closed volumes in one cutter mesh are self-intersecting geometry, and the
    boolean solver discards that outright unless told otherwise. That is what silently left a wall across
    the throat of every driver-loaded horn.
    """
    ring_sides = None if profile == ELLIPTICAL else (sides or 4)
    steps = 1 if flare == LINEAR else _FLARE_STEPS

    # Ratios rather than absolute sizes, so one interpolation covers both axes and both flare laws.
    ratio_w = throat[0] / mouth[0] if mouth[0] else 1.0
    ratio_h = throat[1] / mouth[1] if mouth[1] else 1.0

    rings = []
    if cap_mouth:
        rings.append(_ring(centre, 0.0, 0.0, front_y, ring_sides))

    for step in range(steps + 1):
        t = step / steps
        if flare == EXPONENTIAL:
            # Geometric interpolation: area grows exponentially with depth, so each linear dimension
            # grows as ratio**t. At t=0 and t=1 it meets the mouth and throat exactly, like the linear
            # case, so switching laws never changes the sizes the spec declared.
            scale_w, scale_h = ratio_w ** t, ratio_h ** t
        else:
            scale_w, scale_h = 1.0 + (ratio_w - 1.0) * t, 1.0 + (ratio_h - 1.0) * t
        rings.append(_ring(
            centre, mouth[0] / 2.0 * scale_w, mouth[1] / 2.0 * scale_h, front_y + depth * t, ring_sides,
        ))

    back_y = front_y + depth
    if bore_depth > 0.0:
        back_y += bore_depth
        rings.append(_ring(centre, throat[0] / 2.0, throat[1] / 2.0, back_y, ring_sides))

    if cap_throat:
        # A ring of zero size at the same depth fans the far end shut flat, rather than pointing it.
        rings.append(_ring(centre, 0.0, 0.0, back_y, ring_sides))

    return rings


def _flare(name, material, *args, **kwargs):
    return _shell(name, _flare_rings(*args, **kwargs), material)


def _driver_cone(name, diameter, front_y, depth, material, centre):
    """A driver's moving assembly: surround roll, cone, and a dust cap doming forward at its centre."""
    radius = diameter / 2.0
    cap_radius = max(radius * _DUST_CAP_RATIO, 0.004)
    # The dome must not reach past the baffle on a shallow cone, or the model's bounding box grows.
    rise = min(diameter * _DUST_CAP_RISE, depth * 0.5)

    rings = [
        _ring(centre, radius * r, radius * r, front_y + depth * d, None)
        for r, d in _SURROUND_PROFILE
    ]
    # The cone proper: straight from the surround down to the neck the cap sits on.
    rings.append(_ring(centre, cap_radius, cap_radius, front_y + depth, None))

    base_y = front_y + depth
    for index in range(1, _DOME_RINGS + 1):
        angle = (math.pi / 2.0) * index / _DOME_RINGS
        dome_radius = cap_radius * math.cos(angle)
        rings.append(_ring(centre, dome_radius, dome_radius, base_y - rise * math.sin(angle), None))

    return [_shell(name, rings, material)]


def _carve(body, name, openings):
    """Cut every opening out of the body in one boolean, then delete the cutter.

    One cutter holding all the openings rather than one boolean per opening. Sequentially cutting a solid
    body five times leaves the mesh progressively messier and the EXACT solver starts dropping cuts — the
    symptom was one of two identical LF horns getting its driver bore and the other silently not. Disjoint
    closed volumes in a single cutter is a problem the solver handles exactly once, and faster.
    """
    if not openings:
        return

    verts, faces = [], []
    for opening in openings:
        offset = len(verts)
        verts.extend(opening[0])
        faces.extend(tuple(index + offset for index in face) for face in opening[1])

    cutter = _mesh_object(name, verts, faces, None)
    bpy.context.collection.objects.link(cutter)

    modifier = body.modifiers.new(name="baffle-openings", type="BOOLEAN")
    modifier.operation = "DIFFERENCE"
    modifier.object = cutter
    modifier.solver = "EXACT"
    # Nothing here builds an intentionally self-intersecting cutter, but two features close together on a
    # baffle can still overlap, and the default is to discard such a cut without a word.
    modifier.use_self = True

    bpy.context.view_layer.objects.active = body
    bpy.ops.object.modifier_apply(modifier=modifier.name)
    bpy.data.objects.remove(cutter, do_unlink=True)


def _disc_rings(diameter, front_y, depth, centre):
    """A closed cylinder, for punching a driver's round hole in a generated baffle."""
    radius = diameter / 2.0

    return [
        _ring(centre, 0.0, 0.0, front_y, None),
        _ring(centre, radius, radius, front_y, None),
        _ring(centre, radius, radius, front_y + depth, None),
        _ring(centre, 0.0, 0.0, front_y + depth, None),
    ]


def build_features(plan, material_set, baffle_y, carve_into=None):
    """Build every baffle feature. Returns a list of objects, empty when there is no layout.

    `carve_into` is the cabinet body when the shell was generated rather than imported. A generated body
    is a solid closed box, so a feature drawn behind its front face would simply be buried in the wood —
    the openings have to be cut. A horn *is* a tapered hole in a baffle, so for those the flare is carved
    straight into the cabinet and its own material forms the walls, which is what a wooden horn actually
    is. An imported CAD shell already has its holes, so nothing is cut and only the parts behind them are
    added.
    """
    layout = plan.get("baffle_layout")
    if not layout:
        return []

    dims = plan["geometry"]["dimensions_m"]

    # A feature's `at_m` is [x, z] in the baffle frame — origin at the centre of the front face, which is
    # the frame a tape measure across a baffle gives you. The builder works in bottom-center coordinates,
    # so every z shifts up by half the height. Without this the features sit below the floor.
    baffle_z0 = dims["height"] / 2

    horn_material = material_set[materials.HORN]
    cone_material = material_set[materials.CONE]

    objects = []
    openings = []
    placed = {}

    for feature in layout["features"]:
        name = "%s-%s" % (plan["id"], feature["id"])
        mouth = feature["mouth_m"]
        depth = feature["depth_m"]
        nested = bool(feature.get("inside"))

        if nested:
            parent = placed.get(feature["inside"])
            if parent is None:
                print("sdwa5-3d: skipping %s — no such parent feature %r"
                      % (feature["id"], feature["inside"]))
                continue
            # A phase plug sits at the far end of its host horn, facing forward out of the throat.
            centre = parent["centre"]
            front = parent["front_y"] + parent["depth"] - depth
        else:
            at_x, at_z = feature["at_m"]
            centre = (at_x, at_z + baffle_z0)
            front = baffle_y

        # Only openings that break the outer surface are cut; a nested plug already sits inside one.
        cut = carve_into is not None and not nested

        if feature["kind"] == "cone":
            if cut:
                openings.append(_shell_geometry(
                    _disc_rings(mouth[0], front - _CUTTER_OVERLAP, depth, centre),
                ))
            objects += _driver_cone(name, mouth[0], front, depth, cone_material, centre)
        else:
            throat = feature["throat_m"] or min(mouth) * 0.2
            driver = feature.get("cone_diameter_m")
            shape = {
                "profile": feature.get("profile") or PYRAMID,
                "sides": feature.get("sides"),
                "flare": feature.get("flare") or LINEAR,
            }

            visible = min(driver, throat) if driver else None
            driver_depth = min(visible * 0.6, 0.12) if visible else 0.0

            if cut:
                # The flare becomes a cavity in the cabinet itself. Starting the cutter slightly proud of
                # the baffle guarantees it breaks the surface instead of leaving a paper-thin skin.
                # A driver-loaded horn is cut through into its driver chamber, so the cone behind the
                # throat can actually be seen; an unloaded one stops at the throat.
                openings.append(_shell_geometry(_flare_rings(
                    mouth, (throat, throat), front - _CUTTER_OVERLAP, depth + _CUTTER_OVERLAP, centre,
                    cap_throat=True, cap_mouth=True,
                    bore_depth=(driver_depth + _CUTTER_OVERLAP) if visible else 0.0,
                    **shape,
                )))
            else:
                objects.append(_flare(
                    name, horn_material, mouth, (throat, throat), front, depth, centre,
                    cap_throat=not driver, **shape,
                ))

            if visible:
                # A horn-loaded driver sits behind the throat, seen through the aperture. Its cone is
                # clamped to the throat: a 12" cone behind a narrower throat would otherwise push
                # straight through the flare walls.
                objects += _driver_cone(
                    name + "-driver", visible, front + depth, driver_depth, cone_material, centre,
                )

        placed[feature["id"]] = {"centre": centre, "front_y": front, "depth": depth}

    _carve(carve_into, plan["id"] + "-openings", openings)

    if objects:
        print("sdwa5-3d: %d baffle feature object(s) (%s)"
              % (len(objects), layout["provenance"]))

    return objects
