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

from lib import export  # noqa: E402  (must follow the sys.path fix)


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


def build(plan):
    export.reset_scene()

    scene_collection = bpy.context.scene.collection
    cache = {}

    for placement in plan["placements"]:
        collection = _append_collection(placement["blend"], placement["device"], cache)

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
        instance["sdwa5_device"] = placement["device"]
        scene_collection.objects.link(instance)

    export.save_blend(plan["output"])

    print("sdwa5-3d: scene %s with %d cabinet(s) → %s"
          % (plan["scene_id"], len(plan["placements"]), plan["output"]))


if __name__ == "__main__":
    build(export.parse_plan_argument(list(sys.argv)))
