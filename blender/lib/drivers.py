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

# How long the wall between two joined horns takes to reach its full thickness, and the rings that taper
# spends. The wall does not start out of nowhere: it comes to a straight edge across the horns and thickens
# behind it, which is how a splitter between two horn paths is made. Clamped to whatever length is left
# between the join and the shallower horn's throat.
_JOIN_WEDGE_M = 0.060

_JOIN_WEDGE_RINGS = 4

# What is left of the wall at its edge. A wedge that closed to nothing would be a zero-area face for the
# boolean solver to choke on; half a millimetre is a knife edge to look at and a real surface to cut with.
_JOIN_WEDGE_EDGE_M = 0.002

# The radius a carved opening's mouth is rolled over with, where the flat baffle meets the flare walls,
# and the rings it takes. A wooden horn's mouth is never a knife edge, and this is the edge a cabinet is
# looked at along. The opening at the face grows by twice this over the declared mouth, which is how a
# roundover works on a real one: the size is quoted at the outside of the roll.
_MOUTH_ROUNDOVER_M = 0.012

_MOUTH_ROUNDOVER_RINGS = 4

# How far a join's cutter stops short of its two horns' side walls. Reaching them exactly is what a
# shared mouth wants and what the solver handles worst: over the strip where the cutter reaches into
# each horn, the two side walls would be one coplanar, oppositely wound pair. A tenth of a millimetre
# of wood left standing is invisible, and it is the safe way to be wrong.
_JOIN_SIDE_INSET = 0.0005

# The moving assembly seen from the front, as (radius fraction, depth fraction) pairs: the frame lip sits
# slightly recessed, the surround's half-roll crests level with the baffle, and the cone proper starts
# behind it. The crest is the reason a driver reads as a driver — a bare cone reads as a funnel.
_SURROUND_PROFILE = (
    (1.00, 0.10),
    (0.96, 0.00),
    (0.90, 0.10),
)


def _ring(centre, half_w, half_h, y, sides, roundness=1.0, count=None):
    """One cross-section at depth `y`, as a list of vertices.

    `sides` None or 0 gives an ellipse. An integer gives an n-gon whose walls face the axes, sized so it
    touches the half_w/half_h bounds — 4 sides land exactly on the corners of the mouth rectangle, 8 give
    the familiar octagon with its corners cut off.

    `roundness` blends between the two: 0 is the flat-walled polygon, 1 the ellipse through the same
    bounds. Both are written as one radial function of the angle, which is what lets a single ring stack
    morph from a straight-edged mouth to a round throat. `count` oversamples the ring so the intermediate
    shapes have vertices to bend; it must be a multiple of `2 * sides` or the polygon's corners and wall
    centres stop landing on sample points and the flat walls come out faceted.

    A zero-size ring collapses to a single vertex, which is how both an apex and a flat cap are expressed.
    """
    cx, cz = centre
    if half_w <= 1e-9 and half_h <= 1e-9:
        return [(cx, y, cz)]

    if sides:
        # Walls face the axes, so the corners sit half a wedge round from them.
        wedge = math.pi / int(sides)
        count = count or int(sides)
    else:
        wedge = None
        count = count or _ELLIPSE_SEGMENTS

    ring = []
    for index in range(count):
        angle = (wedge or 0.0) + 2.0 * math.pi * index / count
        if wedge is None:
            radius = 1.0
        else:
            # Distance out to a flat wall, as a multiple of the ellipse's radius in that direction: 1 at
            # the middle of a wall, 1/cos(wedge) at a corner.
            polygon = 1.0 / math.cos(math.remainder(angle, 2.0 * wedge))
            radius = polygon + (1.0 - polygon) * roundness
        ring.append((
            cx + half_w * radius * math.cos(angle),
            y,
            cz + half_h * radius * math.sin(angle),
        ))

    return ring


def _morph_count(sides):
    """Vertices per ring for a flare that changes shape along its length.

    A multiple of `2 * sides`, so both the polygon's corners and the middles of its walls land on sample
    points; near `_ELLIPSE_SEGMENTS`, so the round end is as smooth as any other ellipse here.
    """
    period = 2 * int(sides)

    return period * max(1, round(_ELLIPSE_SEGMENTS / period))


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


def _flare_half(mouth, throat, flare, t):
    """Half-width and half-height of a flare `t` of the way from its mouth to its throat.

    The flare law lives here alone, so anything that needs to know how wide a horn is at some depth —
    the flare's own rings, and the join that opens two of them into one mouth — agrees with the walls
    the builder actually cut.

    Exponential is geometric interpolation: area grows exponentially with depth, so each linear
    dimension grows as ratio**t. At t=0 and t=1 both laws meet the mouth and the throat exactly, so
    switching laws never changes the sizes the spec declared.
    """
    ratio_w = throat[0] / mouth[0] if mouth[0] else 1.0
    ratio_h = throat[1] / mouth[1] if mouth[1] else 1.0

    if flare == EXPONENTIAL:
        scale_w, scale_h = ratio_w ** t, ratio_h ** t
    else:
        scale_w, scale_h = 1.0 + (ratio_w - 1.0) * t, 1.0 + (ratio_h - 1.0) * t

    return mouth[0] / 2.0 * scale_w, mouth[1] / 2.0 * scale_h


def _flare_rings(
    mouth, throat, front_y, depth, centre,
    profile=PYRAMID, throat_profile=None, sides=4, flare=LINEAR,
    cap_throat=True, cap_mouth=False, bore_depth=0.0,
):
    """Rings of a horn: `mouth` at the baffle, narrowing to `throat` at `depth`.

    `profile` is the mouth's cross-section and `throat_profile` the throat's, so a horn can have straight
    edges on the outside and be round where the driver bolts on — which is what a compression-driver horn
    is, since the throat is a round bolt flange. When the two differ the ring stack morphs from one shape
    to the other along the flare.

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
    throat_profile = throat_profile or profile
    mouth_round = 1.0 if profile == ELLIPTICAL else 0.0
    throat_round = 1.0 if throat_profile == ELLIPTICAL else 0.0

    if profile == ELLIPTICAL and throat_profile == ELLIPTICAL:
        ring_sides, count = None, None
    else:
        ring_sides = sides or 4
        # Extra vertices only where a shape actually changes; a flare with one cross-section throughout
        # stays at the minimum the shape needs.
        count = _morph_count(ring_sides) if mouth_round != throat_round else None

    steps = 1 if flare == LINEAR else _FLARE_STEPS

    def ring(half_w, half_h, y, roundness):
        return _ring(centre, half_w, half_h, y, ring_sides, roundness, count)

    rings = []
    if cap_mouth:
        rings.append(ring(0.0, 0.0, front_y, mouth_round))

    for step in range(steps + 1):
        t = step / steps
        half_w, half_h = _flare_half(mouth, throat, flare, t)
        rings.append(ring(
            half_w, half_h, front_y + depth * t,
            mouth_round + (throat_round - mouth_round) * t,
        ))

    back_y = front_y + depth
    if bore_depth > 0.0:
        back_y += bore_depth
        rings.append(ring(throat[0] / 2.0, throat[1] / 2.0, back_y, throat_round))

    if cap_throat:
        # A ring of zero size at the same depth fans the far end shut flat, rather than pointing it.
        rings.append(ring(0.0, 0.0, back_y, throat_round))

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


def _horn_interval(horn, axis, y):
    """A carved horn's extent on one baffle axis at absolute depth `y`, as (low, high).

    `axis` 0 is x and 1 is z, the order `at_m` uses. Measured on the *cutter's* span rather than the
    declared one: a horn's cutter starts `_CUTTER_OVERLAP` proud of the baffle and runs that much
    deeper, so the wall the boolean actually left is a few per cent off the flare the spec describes.
    A join sized off the declared flare would miss it by millimetres all the way down.
    """
    t = _horn_t(horn, y)
    half = _flare_half(horn["mouth"], horn["throat"], horn["flare"], t)[axis]
    centre = horn["centre"][axis]

    return centre - half, centre + half


def _horn_t(horn, y):
    """How far along its flare a carved horn is at absolute depth `y`, as 0 at the mouth and 1 at the
    throat."""
    span = horn["cut_depth"]

    return min(max((y - horn["cut_front_y"]) / span, 0.0), 1.0) if span else 0.0


def _horn_roundness(horn, y):
    """How round a carved horn's cross-section is at depth `y`: 0 straight-walled, 1 elliptical.

    The same blend `_flare_rings` walks from mouth to throat. A join needs it because a horn that is
    round at the throat has already begun curving where the wall between two of them ends, and a cutter
    that ignored that would cut its corners into wood no flare ever reached.
    """
    return horn["mouth_round"] + (horn["throat_round"] - horn["mouth_round"]) * _horn_t(horn, y)


def _horn_section(horn, y):
    """A carved horn's cross-section at depth `y`, as (centre, [half_x, half_z], roundness)."""
    half = _flare_half(horn["mouth"], horn["throat"], horn["flare"], _horn_t(horn, y))

    return horn["centre"], [half[0], half[1]], _horn_roundness(horn, y)


def _join_section(first, second, axis, y):
    """Two joined horns' common cross-section at depth `y`, in the same form as `_horn_section`.

    Across the join (`axis`) it runs from one horn's far edge to the other's — one wall round the pair,
    not two facing each other. Along it, only as far as both horns reach, stopping `_JOIN_SIDE_INSET`
    short of their side walls: a cutter face landing exactly on a flare wall is the surface pairing the
    boolean solver handles worst, and wood left standing is the safe way to be wrong.

    Returns None where the two horns have stopped leaving anything between them, which is not a join.
    """
    along = 1 - axis

    low, high = _horn_interval(first, axis, y)
    other_low, other_high = _horn_interval(second, axis, y)
    if min(high, other_high) - max(low, other_low) > 1e-9:
        return None
    span = (min(low, other_low) + _JOIN_SIDE_INSET, max(high, other_high) - _JOIN_SIDE_INSET)

    low, high = _horn_interval(first, along, y)
    other_low, other_high = _horn_interval(second, along, y)
    shared = (max(low, other_low) + _JOIN_SIDE_INSET, min(high, other_high) - _JOIN_SIDE_INSET)
    if shared[1] - shared[0] <= 1e-9:
        return None

    bounds = [shared, span] if axis else [span, shared]

    return (
        tuple((bound[0] + bound[1]) / 2.0 for bound in bounds),
        [(bound[1] - bound[0]) / 2.0 for bound in bounds],
        # The rounder horn wins: rounding pulls the corners in, so it leaves wood rather than taking it.
        max(_horn_roundness(first, y), _horn_roundness(second, y)),
    )


def _roundover_extra(distance, radius):
    """How far a mouth's roundover stands outside the flare, `distance` behind the baffle.

    A quarter circle: the full radius at the face, nothing at all by the time it reaches `radius` in,
    and tangent to the front face where it starts — which is what makes the corner read as rolled over
    rather than chamfered. In front of the face it stays at the radius, since a cutter reaching past
    the surface it breaks through is just a cutter.
    """
    if radius <= 0.0 or distance >= radius:
        return 0.0
    if distance <= 0.0:
        return radius

    return radius - math.sqrt(max(radius * radius - (radius - distance) ** 2, 0.0))


def _collar_rings(section, mouth_y, radius, count=None):
    """The roundover at an opening's mouth: the corner where the flat baffle meets the flare walls.

    A carved horn's mouth is otherwise a knife edge — the cutter meets the front face at whatever angle
    the flare happens to have. No wooden horn is built that way; the mouth is rolled over, and it is the
    edge a cabinet is looked at along, so it is worth the handful of rings.

    Its own volume rather than extra rings in the flare, because it belongs to the *opening* and a join
    has an opening the individual flares do not: one shared mouth around both horns. Cutters may overlap
    — `_carve` unions them.
    """
    if radius <= 0.0:
        return None

    rings = []
    for step in range(_MOUTH_ROUNDOVER_RINGS + 1):
        distance = -_CUTTER_OVERLAP + (radius + _CUTTER_OVERLAP) * step / _MOUTH_ROUNDOVER_RINGS
        y = mouth_y + distance
        placed = section(max(y, mouth_y))
        if placed is None:
            return None
        centre, half, roundness = placed
        extra = _roundover_extra(distance, radius)
        if step == 0:
            rings.append(_ring(centre, 0.0, 0.0, y, 4))
        rings.append(_ring(centre, half[0] + extra, half[1] + extra, y, 4, roundness, count))

    rings.append(_ring(centre, 0.0, 0.0, y, 4))

    return rings


def _join_gap(first, second, axis, y):
    """The wall between two joined horns at depth `y`: (low, high) across the join, or None if the two
    flares have run into each other and there is no wall left."""
    low, high = _horn_interval(first, axis, y)
    other_low, other_high = _horn_interval(second, axis, y)
    gap = (high, other_low) if high <= other_low else (other_high, low)

    return gap if gap[1] - gap[0] > 1e-9 else None


def _join_axis(first, second, y):
    """Which axis two horns are apart on at depth `y`: 0 for x, 1 for z, None if that is not the case.

    A join needs both — apart on one axis, so there is a wall between them, and overlapping on the
    other, so removing it opens one mouth rather than a slot into nothing. The validator rejects the
    other arrangements, so this is the same test again for a plan that was written by hand.
    """
    apart = None
    for axis in (0, 1):
        low, high = _horn_interval(first, axis, y)
        other_low, other_high = _horn_interval(second, axis, y)
        if min(high, other_high) - max(low, other_low) <= 1e-9:
            if apart is not None:
                return None
            apart = axis

    return apart


def _join_rings(first, second, back_y):
    """Two joined horns' **common section**, as a cutter reaching back to `back_y`.

    Where two horns share a mouth they are not two cavities with the wall between them knocked out —
    down to the split they are *one* horn, with one set of walls running the whole way round. Cutting
    only the strip between them leaves each horn's own cross-section standing, and every cross-section
    here narrows towards its throat, so near the side walls the two turn inwards and never meet: the
    render shows a shelf at each end of the septum instead of one flare carrying on into the next.

    So the cut is the two horns' outlines taken together at every depth — the full span across the join,
    the width they share along it — which is one continuous wall from one horn's far edge to the other's.
    Behind `back_y` the two horns' own flares take over again and the wall between them starts, its nose
    left by this cutter's far cap.

    Everything is measured off both horns at every depth rather than extruded straight back from the
    mouth plane: the gap between two exponential horns widens as they narrow, so a prism would undercut
    the flare walls sideways and still leave a fin of the wall it is meant to remove.

    Roundness follows the horns as well, since a horn that is round at its throat has already begun
    curving where a deep join ends; a plain rectangle that far in would take wood no flare ever reached.

    Behind `back_y` the wall between the two horns takes over, and it does not start at full thickness:
    the cutter carries on as a **wedge**, taking the whole gap between the two flares at `back_y` and none
    of it a wedge-length later. So the wall comes to a straight edge across the horns and thickens behind
    it — a splitter, which is what the part is — instead of presenting a blunt face.

    Both ends are closed by a zero-size ring at the same depth, the way `cap_throat` shuts a flare: the
    solver wants a closed volume. Returns None when the two horns have nothing between them to remove.
    """
    front_y = min(first["cut_front_y"], second["cut_front_y"])
    if back_y - front_y <= 1e-9:
        return None

    axis = _join_axis(first, second, front_y)
    if axis is None:
        return None
    along = 1 - axis

    steps = _FLARE_STEPS if EXPONENTIAL in (first["flare"], second["flare"]) else 1
    # The rounder of the two at the far end decides the sampling: a cross-section that stays square all
    # the way needs four vertices, one that curves needs enough of them to curve with.
    curved = max(_horn_roundness(first, back_y), _horn_roundness(second, back_y)) > 1e-9
    count = _morph_count(4) if curved else None

    rings = []
    centre = half = None
    for step in range(steps + 1):
        y = front_y + (back_y - front_y) * step / steps
        placed = _join_section(first, second, axis, y)
        if placed is None:
            return None

        centre, half, roundness = placed
        if step == 0:
            rings.append(_ring(centre, 0.0, 0.0, y, 4))
        rings.append(_ring(centre, half[0], half[1], y, 4, roundness, count))

    rings.append(_ring(centre, 0.0, 0.0, back_y, 4))

    return rings


def _wedge_rings(first, second, back_y):
    """The taper on the leading edge of the wall between two joined horns.

    The wall does not begin at full thickness: it comes to a straight edge across the horns and thickens
    behind it, which is how a splitter between two horn paths is made and what stops the pair reading as
    two holes with a blunt board between them. So this takes the whole gap between the two flares at the
    join and none of it a wedge-length deeper, leaving the wall wedge-shaped in section — while its edge
    stays a straight line from one side wall to the other.

    Its own closed volume rather than more rings on the common section: the two shapes meet at the same
    depth, and one ring stack passing from one to the other there is a slab of zero thickness — which the
    solver turns into non-manifold scrap. Overlapping closed volumes it handles, which is what
    `_carve`'s `use_self` is for.
    """
    axis = _join_axis(first, second, back_y)
    if axis is None:
        return None

    # At most as far as the shallower throat: past that there are no flares left for a wall to sit between.
    throat_y = min(horn["cut_front_y"] + horn["cut_depth"] for horn in (first, second))
    length = min(_JOIN_WEDGE_M, throat_y - back_y)
    if length <= 1e-9:
        return None

    # One cut per face of the wall. Taking a single slab out of the middle instead would thin the wall
    # from the inside: it would come to its edge at the *back*, two fins growing off the flares, which is
    # the shape upside down. The wall is thinnest at the front, so what has to come off is a taper along
    # each of its two faces.
    low = first if first["centre"][axis] < second["centre"][axis] else second
    high = second if low is first else first

    return [
        _wedge_side_rings(low, high, axis, back_y, length, 1.0),
        _wedge_side_rings(high, low, axis, back_y, length, -1.0),
    ]


def _wedge_side_rings(horn, other, axis, back_y, length, direction):
    """One face of that taper: what comes off the wall on `horn`'s side of it.

    It is `horn`'s own cross-section, pushed `direction` across the join by the layer being taken off —
    so the cut follows the flare it belongs to, rounding included, instead of being a shape of its own.
    A straight-sided strip does not survive here: where a horn has begun rounding towards its throat, a
    strip cut to its outer bounds takes wood the flare never reached and leaves a step along the wall.

    The layer is half the gap at the join, thinning to nothing a wedge-length deeper. Both faces meet at
    the middle of the gap where it starts, which is where the wall comes to its edge — a straight line
    across a symmetrical pair, since both faces are pushed the same distance.
    """
    rings = []
    centre = None
    # Starting a little in front of the join, inside what the common section has already taken out. Both
    # would otherwise close off in the same plane, and two coplanar caps leave the solver a scrap face.
    for step in range(-1, _JOIN_WEDGE_RINGS + 1):
        t = max(step, 0) / _JOIN_WEDGE_RINGS
        at_y = back_y + length * t
        y = at_y if step >= 0 else back_y - _CUTTER_OVERLAP
        gap = _join_gap(horn, other, axis, at_y)
        if gap is None:
            break

        placed, half, roundness = _horn_section(horn, at_y)
        centre = list(placed)
        # Never quite nothing: at the far end the cut would otherwise land exactly on the flare's own
        # wall, and coincident faces are what the solver drops.
        layer = max((gap[1] - gap[0]) / 2.0 * (1.0 - t), _JOIN_SIDE_INSET)
        # The facing wall moves across the join by the layer being taken off; the far one moves the other
        # way by a margin, so this cutter's back is inside the flare's own rather than exactly on it —
        # the whole cut would sit on the horn's surface otherwise, and coincident faces come out as holes.
        margin = 2.0 * _JOIN_SIDE_INSET
        centre[axis] += direction * (layer + margin) / 2.0
        half[axis] += (layer - margin) / 2.0
        # And short of the side walls, for the same reason from the other direction — twice the common
        # section's own margin, so the two cutters' walls do not land on each other either.
        half[1 - axis] -= 2.0 * _JOIN_SIDE_INSET

        if step < 0:
            rings.append(_ring(tuple(centre), 0.0, 0.0, y, 4))
        # Square where the wall comes to its edge, the flare's own roundness by the time the taper is
        # done. Rounded from the start, the cut would follow the flare inwards near the side walls and
        # stop short of the middle of the gap, leaving the edge blunt there and a flat band across it.
        rings.append(_ring(tuple(centre), half[0], half[1], y, 4, roundness * t, _morph_count(4)))

    if len(rings) < 3:
        return None
    rings.append(_ring(tuple(centre), 0.0, 0.0, rings[-1][0][1], 4))

    return rings


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


def _join(feature, state, placed, openings):
    """Add this horn's join to the cutters, if it has one and both sides can carry it.

    Everything a join needs is already stated by the two horns, so a spec that names a pair the builder
    cannot pair is a spec that means something else — it says so and builds the two horns unjoined,
    rather than inventing a cavity. `specs:validate` rejects all of these before a build gets here; the
    checks are repeated because a build plan can be written by hand.
    """
    join = feature.get("join")
    if not join:
        return

    partner = placed.get(join["with"])
    if not state["cut"]:
        print("sdwa5-3d: skipping the join on %s — an imported shell has its holes already"
              % feature["id"])

        return
    if partner is None or not partner.get("cut") or "mouth" not in partner:
        print("sdwa5-3d: skipping the join on %s — no carved horn %r before it"
              % (feature["id"], join["with"]))

        return

    rings = _join_rings(state, partner, state["front_y"] + join["depth_m"])
    if rings is None:
        print("sdwa5-3d: skipping the join on %s — nothing between it and %s to remove"
              % (feature["id"], join["with"]))

        return

    openings.append(_shell_geometry(rings))

    for side in _wedge_rings(state, partner, state["front_y"] + join["depth_m"]) or ():
        if side is not None:
            openings.append(_shell_geometry(side))

    # The pair's mouth is one opening, so it is rolled over as one: rounding each flare on its own would
    # leave the corner unrolled exactly where the two run together, which is the middle of what you see.
    axis = _join_axis(state, partner, state["cut_front_y"])
    collar = _collar_rings(
        lambda y: _join_section(state, partner, axis, y),
        state["front_y"], _MOUTH_ROUNDOVER_M, _morph_count(4),
    ) if axis is not None else None
    if collar is not None:
        openings.append(_shell_geometry(collar))
        state["mouth_rolled"] = partner["mouth_rolled"] = True


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
        state = {"centre": centre, "front_y": front, "depth": depth, "cut": cut}

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
                "throat_profile": feature.get("throat_profile"),
                "sides": feature.get("sides"),
                "flare": feature.get("flare") or LINEAR,
            }

            visible = min(driver, throat) if driver else None
            driver_depth = min(visible * 0.6, 0.12) if visible else 0.0
            state.update({
                "mouth": mouth, "throat": (throat, throat), "flare": shape["flare"],
                "cut_front_y": front - _CUTTER_OVERLAP, "cut_depth": depth + _CUTTER_OVERLAP,
                "mouth_round": 1.0 if shape["profile"] == ELLIPTICAL else 0.0,
                "throat_round":
                    1.0 if (shape["throat_profile"] or shape["profile"]) == ELLIPTICAL else 0.0,
            })

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

            _join(feature, state, placed, openings)

        placed[feature["id"]] = state

    # Mouth roundovers last, so a joined pair has already claimed its own — one roll round the shared
    # opening rather than one round each half of it.
    for state in placed.values():
        if not state.get("cut") or "mouth" not in state or state.get("mouth_rolled"):
            continue
        collar = _collar_rings(
            lambda y, horn=state: _horn_section(horn, y),
            state["front_y"], _MOUTH_ROUNDOVER_M, _morph_count(4),
        )
        if collar is not None:
            openings.append(_shell_geometry(collar))

    _carve(carve_into, plan["id"] + "-openings", openings)

    if objects:
        print("sdwa5-3d: %d baffle feature object(s) (%s)"
              % (len(objects), layout["provenance"]))

    return objects


def build_coverage_cone(plan, material_set):
    """The cabinet's nominal dispersion, as a wireframe cone in front of the baffle.

    Lives in this module rather than with the other markers in `geometry.py` because the ring-and-shell
    machinery above is what draws it, and a dispersion pattern is the audio side of a cabinet rather than
    part of its box. It is a marker in every other respect: built only when the spec says something, and
    invisible to renders.

    Two flags carry the whole thing:

    * `hide_render` is not cosmetic. `export_glb` passes `use_renderable=True` precisely so markers stay in
      the .blend and out of the .glb, and `tools/check-glb.py` compares the exported bounding box against
      the declared dimensions to 1e-4 m. A visible ten-metre cone would fail that on every axis.
    * `display_type = "WIRE"` is what makes it usable. Solid, a 10 m cone swallows the cabinet it belongs
      to; as a wireframe it is something to sight along, which is the only reason to draw it.

    The apex sits at the middle of the baffle. That is a simplification worth naming: a real pattern
    originates from the drivers, which are spread across the baffle and cross over at different distances,
    so the cone answers "roughly where does this cabinet throw" and not "what does the summed response do".
    """
    audio = plan.get("audio") or {}
    spread = audio.get("coverage_spread_m")
    throw = audio.get("coverage_throw_m")
    if not spread or not throw:
        return []

    dims = plan["geometry"]["dimensions_m"]
    front_y = -dims["depth"] / 2.0
    centre = (0.0, dims["height"] / 2.0)

    # A zero-size ring collapses to one vertex, which is how `_shell_geometry` expresses an apex — so the
    # cone is two rings: a point on the baffle and the pattern's spread out at the throw distance.
    rings = [
        _ring(centre, 0.0, 0.0, front_y, sides=None),
        _ring(centre, spread[0] / 2.0, spread[1] / 2.0, front_y - throw, sides=None),
    ]

    cone = _shell("%s-coverage" % plan["id"], rings, material_set[materials.COVERAGE])
    cone.hide_render = True
    cone.display_type = "WIRE"

    return [cone]
