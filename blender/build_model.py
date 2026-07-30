"""Build one device model from a build plan.

Invoked by the PHP CLI, never by hand:

    blender --background --factory-startup --python blender/build_model.py -- --plan build/plans/<id>.json

Writes the .glb and .blend paths named in the plan's `outputs`.
"""

import os
import sys

import bpy

# Blender does not put the script's directory on sys.path, so the shared helpers need help.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from lib import export, geometry, materials, mesh_import  # noqa: E402  (after the sys.path fix)


def build(plan):
    export.reset_scene()

    material_set = materials.build_set(plan["appearance"])
    collection = export.collection_for(plan["id"])

    extras = []

    if plan.get("mesh_override"):
        # A real mesh replaces the generated shell entirely — including the grille and handle
        # recesses, which it already has modelled far better than the builder could.
        body = mesh_import.load(plan, material_set[materials.CABINET])
        if body.name not in collection.objects:
            collection.objects.link(body)
            for other in bpy.context.scene.collection.objects:
                if other is body:
                    bpy.context.scene.collection.objects.unlink(body)
                    break
        front_height = plan["geometry"]["dimensions_m"]["height"]
    else:
        body, front_y, front_height = geometry.build_body(plan, material_set)
        collection.objects.link(body)

        # Order matters: recesses are cut into the raw shell, then the chamfer rounds every edge
        # including the new ones.
        geometry.cut_handles(plan, body, front_height)
        geometry.add_chamfer(body, plan["geometry"]["chamfer_m"])

        extras += geometry.build_grille(plan, material_set, front_y, front_height)

    extras += geometry.build_rigging_markers(plan, material_set)
    extras += geometry.build_estimated_marker(plan, material_set)
    for obj in extras:
        collection.objects.link(obj)
        obj.parent = body

    # Everything was built in bottom-center coordinates; this puts the spec's chosen origin at
    # the world origin. Children inherit it through the parent, so only the body moves.
    body.location = geometry.origin_offset(plan)

    export.attach_metadata(body, plan["metadata"])

    export.export_glb(plan["outputs"]["glb"])
    export.save_blend(plan["outputs"]["blend"])

    print("sdwa5-3d: built %s (%d objects)" % (plan["id"], 1 + len(extras)))


if __name__ == "__main__":
    build(export.parse_plan_argument(list(sys.argv)))
