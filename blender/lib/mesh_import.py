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

import bpy

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
