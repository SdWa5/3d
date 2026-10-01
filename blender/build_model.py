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

from lib import (  # noqa: E402  (after sys.path)
    bay,
    drivers,
    export,
    fixture,
    geometry,
    mast,
    materials,
    mesh_import,
    scaffold,
    truss,
)

# Shapes that are not loudspeaker cabinets, each with its own builder. A table rather than a chain of branches:
# there are five now, and the next one should be a line here rather than another arm. A builder returns its body, or
# its body and the parts that have to stay separate objects, such as a wind-up stand's sliding stages. Mirrors
# `App\Spec\Shape::isCabinet()`, which is the PHP side of the same question.
OPEN_FRAME_BUILDERS = {
    "truss": truss.build,
    "moving-head": fixture.build,
    "scaffold": scaffold.build,
    # A wind-up stand: legs, winch and adapter on a mast whose stages a scene slides down rather than squashes.
    "mast": mast.build,
    # A transporter, drawn as its own outline with its load bay caged inside it. Stated by the owner: the vans need
    # wire-type models so a pack can be planned, and a solid van would hide the rig it is there to carry.
    "load-bay": bay.build,
}


def build(plan):
    export.reset_scene()

    material_set = materials.build_set(plan["appearance"])
    collection = export.collection_for(plan["id"])

    extras = []

    open_frame = OPEN_FRAME_BUILDERS.get(plan["geometry"]["shape"])

    if open_frame is not None:
        # None of these has a shell, so none has what a shell carries: no grille to inset, no handle recesses, no
        # chamfer to round, no drivers behind a baffle and no coverage cone. Everything below the branch still
        # applies — the origin shift, the rigging markers and the estimated marker are about the device rather
        # than about its being a cabinet.
        built = open_frame(plan, material_set)
        body, parts = built if isinstance(built, tuple) else (built, [])
        collection.objects.link(body)
        extras += parts
    elif plan.get("mesh_override"):
        # A real mesh replaces the generated shell entirely — including the grille and handle
        # recesses, which it already has modelled far better than the builder could.
        body = mesh_import.load(plan, material_set[materials.CABINET])
        mesh_import.remove(body, plan)
        mesh_import.paint(body, plan)
        if body.name not in collection.objects:
            collection.objects.link(body)
            for other in bpy.context.scene.collection.objects:
                if other is body:
                    bpy.context.scene.collection.objects.unlink(body)
                    break
        front_height = plan["geometry"]["dimensions_m"]["height"]
        # An imported shell already has its baffle holes; the layout's own inset says how far back that
        # baffle sits inside the outer box.
        layout = plan.get("baffle_layout") or {}
        baffle_y = -plan["geometry"]["dimensions_m"]["depth"] / 2 + layout.get("inset_m", 0.0)
        carve_into = None
    else:
        body, front_y, front_height = geometry.build_body(plan, material_set)
        collection.objects.link(body)

        # Order matters: the chamfer rounds the raw box first, and the handle recesses are cut into it
        # afterwards with sharp edges. The other way round, the bevel ran on round the recesses and left
        # geometry that made the EXACT boolean carving the baffle drop a horn without a word: the TMS-4's
        # mid horn was never cut at any width from 0.45 to 0.51 m, and was with the bevel or the handles
        # taken away.
        geometry.add_chamfer(body, plan["geometry"]["chamfer_m"])
        geometry.cut_handles(plan, body, front_height)

        extras += geometry.build_grille(plan, material_set, front_y, front_height)

        # A photograph of the front, for a cabinet with no interior. Deliberately after the handle
        # recesses and the chamfer, because both reindex the mesh: the polygons are picked by facing
        # rather than by index, so the order is what makes that picking see the finished shell.
        #
        # Only reached through the generated-block branch at all. An override has no front plane this
        # builder knows about, and a spec with a baffle layout is refused by the validator, so neither
        # can arrive here carrying an image.
        front_image = plan.get("front_image")
        if front_image:
            cutout = bool(front_image.get("cutout"))
            image_material = materials.front_image(front_image["path"], cutout)
            if image_material is not None:
                backing = materials.front_image_backing(image_material, plan["appearance"]["color"]) if cutout else None
                geometry.apply_front_image(plan, body, image_material, backing)

        # A generated shell is solid, so its openings have to be cut into it — and they are cut at the
        # body's own front plane, not at the layout's inset, which only describes a CAD baffle.
        baffle_y = front_y
        carve_into = body

    if open_frame is None:
        # Deliberately outside the shell branches: an override supplies the shell and its baffle holes, but
        # nothing behind them, so the drivers and horns are exactly what it is missing.
        extras += drivers.build_features(plan, material_set, baffle_y, carve_into)
        extras += drivers.build_coverage_cone(plan, material_set)

    extras += geometry.build_castors(plan, material_set)
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
