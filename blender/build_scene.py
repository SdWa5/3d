"""Assemble a scene from placed devices.

Invoked by the PHP CLI:

    blender --background --factory-startup --python blender/build_scene.py -- --plan build/plans/_scene-<id>.json

Each device's collection is appended once and then instanced per placement, so a 14-cabinet sub wall
costs one copy of the geometry rather than fourteen. All positions are already absolute — the PHP side
resolved stacking and repetition, so nothing here does arithmetic.
"""

import math
import os
import sys

import bpy

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from lib import export, materials  # noqa: E402  (must follow the sys.path fix)


def _append_collection(blend_path, device_id, cache):
    """Append a device's collection once and reuse it for every later placement."""
    if device_id in cache:
        return cache[device_id]

    with bpy.data.libraries.load(blend_path, link=False) as (data_from, data_to):
        if device_id not in data_from.collections:
            raise SystemExit(
                "sdwa5-3d: %s contains no collection named %r — rebuild the model"
                % (blend_path, device_id)
            )
        data_to.collections = [device_id]

    collection = data_to.collections[0]
    # Kept out of the scene itself: only the instances below should be visible.
    collection.use_fake_user = True
    cache[device_id] = collection

    return collection


def _cranked(collection, scale_z, cache):
    """The collection to instance for a stand cranked to `scale_z` of its full height, and the scale to give it.

    **A mast slides its stages, so it gets a collection of its own per height and no scale.** Stretching the
    instance would squash the legs, the collars and the winch with the mast, and the real stand keeps its base and
    loses height only between its stages. Stage k of N moves down by k/N of what the stand loses, so every joint
    keeps the same overlap. The copies share their mesh data with the appended model, so a cranked stand costs a
    handful of objects and no geometry.

    A collection without stages, such as a tower still drawn as a box, keeps the stretch it always had.
    """
    stages = [obj for obj in collection.all_objects if "sdwa5_stage" in obj]
    if not stages or abs(scale_z - 1.0) < 1e-9:
        return collection, scale_z

    key = (collection.name, round(scale_z, 6))
    if key in cache:
        return cache[key], 1.0

    body = next(obj for obj in collection.all_objects if "sdwa5_mast_stages" in obj)
    full = float(body["sdwa5_dimensions_m"][1])
    count = int(body["sdwa5_mast_stages"])
    drop = full * (1.0 - scale_z)

    cranked = bpy.data.collections.new("%s@%.3fm" % (collection.name, full * scale_z))
    copies = {obj: obj.copy() for obj in collection.all_objects}
    for original, copy in copies.items():
        cranked.objects.link(copy)
        if original.parent in copies:
            copy.parent = copies[original.parent]
        if "sdwa5_stage" in original:
            copy.location.z -= drop * original["sdwa5_stage"] / count
    cranked.instance_offset = collection.instance_offset
    cranked.use_fake_user = True
    cache[key] = cranked

    return cranked, 1.0


def _fault_cages(plan, scene_collection):
    """Draw a red cage around every cabinet the geometry checks object to.

    **A render has to show it, so it cannot be a viewport setting.** The coverage cone next door uses
    `display_type = "WIRE"`, which Cycles ignores entirely — fine for something you sight along in the
    viewport and useless for CVR-5, whose whole argument is that a picture beats a sentence. A Wireframe
    modifier turns the cage's edges into real bars, so it renders from any camera.

    A cage rather than a recolour, because a placement here is an empty instancing a linked collection and an
    empty takes no material. Recolouring would mean copying the collection per faulted cabinet. A cage is also
    the better picture: it says "this one" without hiding the thing it is pointing at.

    The boxes are world-space and come from the plan. The PHP side already knows them — the same
    `worldBox()` the checks were computed from — and asking Blender to re-derive the bounds of a collection
    instance would be a second opinion nobody needs.
    """
    cages = plan.get("fault_cages") or []
    if not cages:
        return

    material = materials.fault_material()

    for cage in cages:
        low, high = cage["min"], cage["max"]
        mesh = bpy.data.meshes.new("fault-%s" % cage["placement"])
        verts = [
            (low[0], low[1], low[2]), (high[0], low[1], low[2]),
            (high[0], high[1], low[2]), (low[0], high[1], low[2]),
            (low[0], low[1], high[2]), (high[0], low[1], high[2]),
            (high[0], high[1], high[2]), (low[0], high[1], high[2]),
        ]
        faces = [
            (0, 1, 2, 3), (4, 5, 6, 7), (0, 1, 5, 4),
            (1, 2, 6, 5), (2, 3, 7, 6), (3, 0, 4, 7),
        ]
        mesh.from_pydata(verts, [], faces)
        mesh.update()
        mesh.materials.append(material)

        obj = bpy.data.objects.new("fault-%s" % cage["placement"], mesh)
        wire = obj.modifiers.new(name="cage", type="WIREFRAME")
        # 25 mm bars: thick enough to read at 960 x 540, thin enough not to bury a 0.4 m top.
        wire.thickness = 0.025
        wire.use_replace = True
        obj["sdwa5_fault"] = cage["placement"]
        scene_collection.objects.link(obj)


def build(plan):
    export.reset_scene()

    scene_collection = bpy.context.scene.collection
    cache = {}
    cranked = {}

    for placement in plan["placements"]:
        collection = _append_collection(placement["blend"], placement["device"], cache)
        collection, scale_z = _cranked(collection, placement.get("scale_z", 1.0), cranked)

        instance = bpy.data.objects.new(placement["placement_id"], None)
        instance.instance_type = "COLLECTION"
        instance.instance_collection = collection
        instance.location = tuple(placement["position_m"])
        # `rotation_euler_deg` is already in Blender's XYZ order — pitch about X, then roll about Y,
        # then yaw about Z. The PHP side decides the rotation in a different order (roll the cabinet in
        # its own frame, then tilt it down, then aim it) and converts, because the two orders disagree
        # for a cabinet that is both rolled off a half turn and tilted. The bare `pitch_deg`/`roll_deg`/
        # `yaw_deg` fallback is what plans written before that conversion existed carry; they only ever
        # held rolls of 0 or 180, where the two orders happen to coincide.
        # The position has already been raised so a rotated cabinet still rests on its slot.
        rotation = placement.get("rotation_euler_deg") or (
            placement.get("pitch_deg", 0.0),
            placement.get("roll_deg", 0.0),
            placement["yaw_deg"],
        )
        instance.rotation_euler = tuple(math.radians(angle) for angle in rotation)
        # A truss tower cranked below its full extension. A mast has already slid its stages down and is
        # instanced at full scale. A tower still drawn as a box is stretched instead. That scale is in the
        # instance's own frame, so it shortens the column along its height whatever the rotation, and its
        # bottom stays on the slot.
        instance.scale = (1.0, 1.0, scale_z)
        instance["sdwa5_device"] = placement["device"]
        scene_collection.objects.link(instance)

    _fault_cages(plan, scene_collection)

    export.save_blend(plan["output"])

    faults = plan.get("faults") or []
    print("sdwa5-3d: scene %s with %d cabinet(s)%s → %s"
          % (plan["scene_id"], len(plan["placements"]),
             "" if not faults else ", %d marked faulty" % len(faults),
             plan["output"]))


if __name__ == "__main__":
    build(export.parse_plan_argument(list(sys.argv)))
