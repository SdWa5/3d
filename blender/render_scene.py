"""Render an assembled scene to a PNG.

Invoked by the PHP CLI:

    blender --background --factory-startup --python blender/render_scene.py -- --plan build/plans/_render-<id>.json

Camera and light positions are already absolute — RenderPlan on the PHP side framed the scene from its
own bounding box, so nothing here decides where anything goes.
"""

import os
import sys

import bpy
from mathutils import Vector

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from lib import export  # noqa: E402  (must follow the sys.path fix)


def _aim(obj, target):
    """Point an object's −Z axis at a world position, which is how cameras and lights aim."""
    direction = Vector(target) - obj.location
    if direction.length == 0:
        return
    obj.rotation_euler = direction.to_track_quat("-Z", "Y").to_euler()


def _add_ground(config):
    if not config["enabled"]:
        return

    bpy.ops.mesh.primitive_plane_add(size=config["size"], location=(0.0, 0.0, 0.0))
    ground = bpy.context.active_object
    ground.name = "ground"

    material = bpy.data.materials.new("sdwa5-ground")
    material.use_nodes = True
    bsdf = material.node_tree.nodes["Principled BSDF"]
    red, green, blue = config["color"]
    bsdf.inputs["Base Color"].default_value = (red, green, blue, 1.0)
    bsdf.inputs["Roughness"].default_value = 0.92
    ground.data.materials.append(material)


def _add_camera(config):
    data = bpy.data.cameras.new("sdwa5-camera")
    data.lens = config["lens_mm"]

    camera = bpy.data.objects.new("sdwa5-camera", data)
    camera.location = tuple(config["location"])
    bpy.context.scene.collection.objects.link(camera)
    _aim(camera, config["target"])
    bpy.context.scene.camera = camera


def _add_lights(config):
    for light in config["lights"]:
        data = bpy.data.lights.new(light["name"], light["kind"])
        data.energy = light["energy"]
        data.color = tuple(light["color"])
        if light["kind"] == "AREA":
            data.size = light["size"]
            data.shape = "SQUARE"

        obj = bpy.data.objects.new(light["name"], data)
        obj.location = tuple(light["location"])
        bpy.context.scene.collection.objects.link(obj)
        _aim(obj, light["target"])


def _set_world(config):
    scene = bpy.context.scene
    world = scene.world
    if world is None:
        world = bpy.data.worlds.new("sdwa5-world")
        scene.world = world
    world.use_nodes = True

    background = world.node_tree.nodes.get("Background")
    if background is not None:
        red, green, blue = config["color"]
        background.inputs[0].default_value = (red, green, blue, 1.0)


def render(plan):
    bpy.ops.wm.open_mainfile(filepath=plan["scene_blend"])

    _add_ground(plan["ground"])
    _add_camera(plan["camera"])
    _add_lights(plan["lighting"])
    _set_world(plan["world"])

    scene = bpy.context.scene
    # Cycles on CPU: the container has no GPU, and EEVEE Next needs one. 17 boxes render in seconds.
    scene.render.engine = "CYCLES"
    scene.cycles.device = "CPU"
    scene.cycles.samples = plan["render"]["samples"]
    scene.cycles.use_denoising = True
    scene.render.resolution_x, scene.render.resolution_y = plan["render"]["resolution"]
    scene.render.resolution_percentage = 100
    scene.render.image_settings.file_format = "PNG"
    scene.render.filepath = plan["output"]

    directory = os.path.dirname(plan["output"])
    if directory and not os.path.isdir(directory):
        os.makedirs(directory, exist_ok=True)

    bpy.ops.render.render(write_still=True)

    print("sdwa5-3d: rendered %s (%s camera, %s lighting, %d samples) → %s" % (
        plan["scene_id"],
        plan["camera"]["preset"],
        plan["lighting"]["preset"],
        plan["render"]["samples"],
        plan["output"],
    ))


if __name__ == "__main__":
    render(export.parse_plan_argument(list(sys.argv)))
