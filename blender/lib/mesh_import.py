"""Import an override mesh in place of the generated block.

Real CAD arrives in whatever units and orientation its author used, so the spec has to say which.
After importing, scaling and rotating, the result is checked against the spec's declared dimensions
and the build **fails** if they disagree beyond the tolerance.

Failing is the point. A silently mis-scaled cabinet still looks like a cabinet, and would quietly
poison every setup built from it — which is exactly what this repo exists to prevent. The spec is
the authority; the mesh has to agree with it or be reconciled.
"""

import math
import os

import bmesh
import bpy
from mathutils import Vector

from . import materials

# How far outside a paint box a face centroid may sit and still count as inside. A face lying exactly on one of
# the box's planes, the front face of a brace that starts at the front plane, has its centroid on that plane, and
# float noise must not decide which side it falls on.
_PAINT_EPSILON_M = 1e-5

_IMPORTERS = {
    ".obj": lambda path: bpy.ops.wm.obj_import(filepath=path),
    ".stl": lambda path: bpy.ops.wm.stl_import(filepath=path),
    ".ply": lambda path: bpy.ops.wm.ply_import(filepath=path),
    ".glb": lambda path: bpy.ops.import_scene.gltf(filepath=path),
    ".gltf": lambda path: bpy.ops.import_scene.gltf(filepath=path),
}


def _import_objects(path):
    """Import a file and return the mesh objects it added to the scene."""
    extension = os.path.splitext(path)[1].lower()
    before = set(bpy.data.objects)

    if extension == ".blend":
        with bpy.data.libraries.load(path, link=False) as (data_from, data_to):
            data_to.objects = list(data_from.objects)
        for obj in data_to.objects:
            if obj is not None:
                bpy.context.scene.collection.objects.link(obj)
    else:
        importer = _IMPORTERS.get(extension)
        if importer is None:
            raise SystemExit("sdwa5-3d: cannot import %r — unsupported format" % path)
        importer(path)

    added = [obj for obj in bpy.data.objects if obj not in before and obj.type == "MESH"]
    if not added:
        raise SystemExit("sdwa5-3d: %r contained no mesh" % path)

    return added


def _join(objects, name):
    for obj in bpy.data.objects:
        obj.select_set(False)
    for obj in objects:
        obj.select_set(True)
    bpy.context.view_layer.objects.active = objects[0]
    if len(objects) > 1:
        bpy.ops.object.join()

    joined = bpy.context.view_layer.objects.active
    joined.name = name
    joined.data.name = name

    return joined


def _bounds(obj):
    """World-space bounding box of a mesh object, as (min, max) triples."""
    matrix = obj.matrix_world
    corners = [matrix @ vert.co for vert in obj.data.vertices]
    low = [min(c[axis] for c in corners) for axis in range(3)]
    high = [max(c[axis] for c in corners) for axis in range(3)]

    return low, high


def load(plan, material):
    """Import the plan's override mesh, normalise it, and verify it against the declared size.

    Returns the single mesh object, positioned in bottom-center coordinates like a generated body.
    """
    override = plan["mesh_override"]
    path = override["path"]

    objects = _import_objects(path)
    obj = _join(objects, plan["id"])

    # Units first, then the author's stated rotation, then bake both into the mesh so later
    # measurements and the origin shift work on plain vertex coordinates.
    obj.scale = (override["unit_scale"],) * 3
    obj.rotation_euler = tuple(math.radians(angle) for angle in override["rotate_deg"])
    bpy.context.view_layer.objects.active = obj
    for other in bpy.data.objects:
        other.select_set(False)
    obj.select_set(True)
    bpy.ops.object.transform_apply(location=False, rotation=True, scale=True)

    low, high = _bounds(obj)
    actual = [high[axis] - low[axis] for axis in range(3)]

    dims = plan["geometry"]["dimensions_m"]
    expected = [dims["width"], dims["depth"], dims["height"]]
    tolerance = override["tolerance_m"]

    off = [
        (label, actual[axis], expected[axis])
        for axis, label in enumerate(("width (x)", "depth (y)", "height (z)"))
        if abs(actual[axis] - expected[axis]) > tolerance
    ]
    if off:
        detail = "\n".join(
            "    %-12s mesh %.4f m vs spec %.4f m  (off by %.4f m)"
            % (label, got, want, got - want)
            for label, got, want in off
        )
        raise SystemExit(
            "sdwa5-3d: mesh_override does not match the declared dimensions of %r\n"
            "%s\n"
            "  mesh: %s\n"
            "  Fix mesh_override.units or rotate_deg, correct the spec's dimensions, or raise\n"
            "  mesh_override.tolerance_m if the difference is genuinely acceptable."
            % (plan["id"], detail, path)
        )

    # Sit it in bottom-center coordinates, the frame every generated body uses.
    obj.location = (
        -(low[0] + high[0]) / 2.0,
        -(low[1] + high[1]) / 2.0,
        -low[2],
    )
    bpy.ops.object.transform_apply(location=True, rotation=False, scale=False)

    obj.data.materials.clear()
    obj.data.materials.append(material)

    print("sdwa5-3d: imported override mesh %s (%.3f x %.3f x %.3f m)"
          % (os.path.basename(path), actual[0], actual[1], actual[2]))

    return obj


def remove(obj, plan):
    """Cut away the parts of an imported mesh that the spec's `mesh_override.remove` prisms enclose.

    Each prism is its side-view polygon extruded across its x range, and each is subtracted in an exact boolean
    of its own. Runs before `paint()`, so a region painted afterwards sees the finished shell.

    Fails the build when the boolean leaves the face count unchanged, because then the CAD part is still there
    and the model would show what the spec says is gone. A boolean that eats the shell instead is caught by the
    bounding-box check in `tools/check-glb.py`.
    """
    removals = (plan.get("mesh_override") or {}).get("remove") or []
    if not removals:
        return

    dims = plan["geometry"]["dimensions_m"]
    front_y = -dims["depth"] / 2.0
    centre_z = dims["height"] / 2.0

    # An STL arrives as unconnected triangles whose winding is whatever the CAD exporter left. The exact solver
    # decides what is inside by that winding, and on the Flexy's raw import it took most of the shell for air and
    # threw it away, 2817 faces down to 523 with the floor gone. Welding the coincident corners and making the
    # normals consistent first keeps the shell whole, measured on 2026-10-01. Neither moves a vertex.
    shell = bmesh.new()
    shell.from_mesh(obj.data)
    bmesh.ops.remove_doubles(shell, verts=shell.verts[:], dist=1e-5)
    bmesh.ops.recalc_face_normals(shell, faces=shell.faces[:])
    shell.to_mesh(obj.data)
    shell.free()

    before = len(obj.data.polygons)
    # One boolean per prism rather than one joined cutter. Two prisms that overlap, as the Flexy's divider and its
    # planks do where they cross, make a cutter that intersects itself, and the solver left a sheet of the
    # cutter's own faces standing in the horn mouth where they met.
    for removal in removals:
        _subtract_prism(obj, plan["id"], removal, front_y, centre_z)

    prisms = [
        (removal["x_m"], [(front_y + setback, centre_z + z) for setback, z in removal["section_m"]])
        for removal in removals
    ]
    dropped = _drop_enclosed_shells(obj, prisms)

    after = len(obj.data.polygons)
    print("sdwa5-3d: removed %s from %s (%d faces before, %d after, %d enclosed shells dropped)"
          % (", ".join(r["id"] for r in removals), plan["id"], before, after, dropped))
    if after == before:
        raise SystemExit(
            "sdwa5-3d: mesh_override.remove of %r cut nothing from the mesh — check its frame" % plan["id"]
        )


def _subtract_prism(obj, device_id, removal, front_y, centre_z):
    """Subtract one `mesh_override.remove` prism from the mesh in an exact boolean."""
    x_from, x_to = removal["x_m"]
    section = [(front_y + setback, centre_z + z) for setback, z in removal["section_m"]]

    bm = bmesh.new()
    left = [bm.verts.new((x_from, y, z)) for y, z in section]
    right = [bm.verts.new((x_to, y, z)) for y, z in section]
    bm.faces.new(left)
    bm.faces.new(list(reversed(right)))
    for index in range(len(section)):
        following = (index + 1) % len(section)
        bm.faces.new((left[index], left[following], right[following], right[index]))
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces[:])

    name = "%s-%s" % (device_id, removal["id"])
    mesh = bpy.data.meshes.new(name)
    bm.to_mesh(mesh)
    bm.free()
    cutter = bpy.data.objects.new(name, mesh)
    bpy.context.scene.collection.objects.link(cutter)

    modifier = obj.modifiers.new(name, "BOOLEAN")
    modifier.operation = "DIFFERENCE"
    modifier.object = cutter
    modifier.solver = "EXACT"
    # The CAD builds its cross from solids that overlap each other and the walls, so the shell intersects
    # itself. Without self-intersection handling the solver reads those overlaps as inside-out and keeps them.
    modifier.use_self = True
    for other in bpy.data.objects:
        other.select_set(False)
    obj.select_set(True)
    bpy.context.view_layer.objects.active = obj
    bpy.ops.object.modifier_apply(modifier=modifier.name)

    bpy.data.objects.remove(cutter, do_unlink=True)
    bpy.data.meshes.remove(mesh)


def _inside_polygon(point, polygon, epsilon):
    """Whether a (y, z) point lies inside a polygon of (y, z) corners or within `epsilon` of its outline.

    The outline counts as inside on purpose. The exact boolean can leave a sheet of the cutter's own faces behind
    where the cut ran through a coincident face, a zero-thickness shell lying exactly on the prism's boundary, and
    an even-odd test alone would call every one of its vertices outside.
    """
    y, z = point
    inside = False
    count = len(polygon)
    for index in range(count):
        (ay, az), (by, bz) = polygon[index], polygon[(index - 1) % count]
        if (az > z) != (bz > z) and y < (by - ay) * (z - az) / (bz - az) + ay:
            inside = not inside
        length_sq = (by - ay) ** 2 + (bz - az) ** 2
        t = 0.0 if length_sq == 0.0 else max(0.0, min(1.0, ((y - ay) * (by - ay) + (z - az) * (bz - az)) / length_sq))
        if (y - ay - t * (by - ay)) ** 2 + (z - az - t * (bz - az)) ** 2 <= epsilon ** 2:
            return True
    return inside


def _drop_enclosed_shells(obj, prisms):
    """Delete every loose shell of the mesh that lies wholly inside the removal prisms.

    The boolean takes away what the prisms cut through, but a CAD file can also carry closed solids sitting inside
    the part being removed, such as the block buried in the Flexy's divider. The solver keeps those as they are, so
    once the divider is gone they float in the horn mouth. A shell whose every vertex is inside some prism was part
    of what the spec removes. Returns how many were dropped.
    """
    epsilon = _PAINT_EPSILON_M
    bm = bmesh.new()
    bm.from_mesh(obj.data)
    seen = set()
    doomed = []
    count = 0
    for start in bm.faces:
        if start.index in seen:
            continue
        shell, stack = [], [start]
        seen.add(start.index)
        while stack:
            face = stack.pop()
            shell.append(face)
            for edge in face.edges:
                for neighbour in edge.link_faces:
                    if neighbour.index not in seen:
                        seen.add(neighbour.index)
                        stack.append(neighbour)
        verts = {vert for face in shell for vert in face.verts}
        # Inside the union of the prisms rather than inside one of them: the piece where the Flexy's divider and
        # its back plank crossed lies across both, and that piece is exactly what was left floating.
        if all(
            any(
                x_from - epsilon <= vert.co.x <= x_to + epsilon
                and _inside_polygon((vert.co.y, vert.co.z), section, epsilon)
                for (x_from, x_to), section in prisms
            )
            for vert in verts
        ):
            doomed.extend(shell)
            count += 1
    if doomed:
        bmesh.ops.delete(bm, geom=doomed, context="FACES")
        bm.to_mesh(obj.data)
    bm.free()
    obj.data.update()
    return count


def paint(obj, plan):
    """Colour the parts of an imported mesh that the spec's `mesh_override.paint` regions enclose.

    A CAD shell is one object in one material, so a steel brace or a painted port tube in it renders as more of
    the cabinet. Each region is a box. The mesh is first cut at the box's six planes, so no face straddles its
    boundary. Every face whose centroid then lies inside the box takes the region's colour. Cutting only adds
    edges along those planes and moves no vertex, so the shape and the bounding box stay as they were.

    Runs on the body `load()` returned, which sits in bottom-center coordinates with its front at -Y. A region's
    `at_m` is measured from the centre of that front face and its setback from the front plane, as
    `PaintRegion` in the spec says.
    """
    regions = (plan.get("mesh_override") or {}).get("paint") or []
    if not regions:
        return

    dims = plan["geometry"]["dimensions_m"]
    front_y = -dims["depth"] / 2.0
    centre_z = dims["height"] / 2.0

    mesh = obj.data
    bm = bmesh.new()
    bm.from_mesh(mesh)

    for region in regions:
        (x, z), (width, height) = region["at_m"], region["size_m"]
        low = Vector((x - width / 2.0, front_y + region["setback_m"], centre_z + z - height / 2.0))
        high = Vector((x + width / 2.0, front_y + region["setback_m"] + region["depth_m"], centre_z + z + height / 2.0))

        for axis in range(3):
            for value in (low[axis], high[axis]):
                normal = Vector((0.0, 0.0, 0.0))
                normal[axis] = 1.0
                point = Vector((0.0, 0.0, 0.0))
                point[axis] = value
                bmesh.ops.bisect_plane(
                    bm,
                    geom=bm.verts[:] + bm.edges[:] + bm.faces[:],
                    plane_co=point,
                    plane_no=normal,
                )

        material = materials.feature("paint", region["color"], roughness=0.6)
        if material not in list(mesh.materials):
            mesh.materials.append(material)
        index = list(mesh.materials).index(material)

        painted = 0
        for face in bm.faces:
            centre = face.calc_center_median()
            if all(low[axis] - _PAINT_EPSILON_M <= centre[axis] <= high[axis] + _PAINT_EPSILON_M for axis in range(3)):
                face.material_index = index
                painted += 1

        print("sdwa5-3d: painted %d faces of %s %s" % (painted, plan["id"], region["id"]))
        if painted == 0:
            # A region that encloses nothing is a region measured in the wrong frame. Building on would ship a
            # model that silently lacks the part the spec says is there.
            raise SystemExit(
                "sdwa5-3d: mesh_override.paint %r of %r encloses no surface of the mesh" % (region["id"], plan["id"])
            )

    bm.to_mesh(mesh)
    bm.free()
    mesh.update()
