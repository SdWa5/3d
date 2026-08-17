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


def _add_aim_lines(lines):
    """Draw a thin glowing rod along each cabinet's aim, plus a marker where it lands.

    These are the one thing in a render that is *not* physical, so they are unmistakably emissive
    rather than trying to look like an object.
    """
    if not lines:
        return

    material = bpy.data.materials.new("sdwa5-aim")
    material.use_nodes = True
    bsdf = material.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = (1.0, 0.10, 0.06, 1.0)
    bsdf.inputs["Emission Color"].default_value = (1.0, 0.10, 0.06, 1.0)
    bsdf.inputs["Emission Strength"].default_value = 12.0
    material.diffuse_color = (1.0, 0.10, 0.06, 1.0)

    for line in lines:
        start = Vector(line["start"])
        end = Vector(line["end"])
        span = end - start
        if span.length < 1e-6:
            continue

        bpy.ops.mesh.primitive_cylinder_add(radius=0.008, depth=span.length,
                                            location=tuple(start + span / 2))
        rod = bpy.context.active_object
        rod.name = "aim-%s" % line["placement_id"]
        # A cylinder points along +Z; swing that onto the aim direction.
        rod.rotation_euler = span.to_track_quat("Z", "Y").to_euler()
        rod.data.materials.append(material)

        if line.get("hits_floor"):
            bpy.ops.mesh.primitive_cylinder_add(radius=0.06, depth=0.004,
                                                location=(end.x, end.y, 0.004))
            spot = bpy.context.active_object
            spot.name = "aim-spot-%s" % line["placement_id"]
            spot.data.materials.append(material)


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


def _add_labels(labels, camera):
    """Draw a text label for each thing named, and a legend block beside the scene.

    **A pack render carries three vehicle cages and twenty-five cabinets and said nowhere which was which.** Stated
    by the owner: it needs Beschriftungen and a legend. This is the annotation half of that — like the aim lines
    above, it is drawn at *render* time rather than built into the `.blend`, so the same assembled scene can be drawn
    with labels or without and neither is the canonical one.

    **Every label faces the camera**, because text lying flat on the ground is unreadable from a three-quarter view
    and text on a fixed axis is unreadable from half the presets. The render plan states where the camera is, so each
    label is turned to face it — the same trick `_aim` does for lights, plus a half turn, since a font's readable
    side is its +Z and `_aim` points −Z at its target.

    Emissive and unlit, so a label in a shadow is still a label.
    """
    if not labels:
        return

    material = bpy.data.materials.new("sdwa5-label")
    material.use_nodes = True
    bsdf = material.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = (1.0, 1.0, 1.0, 1.0)
    bsdf.inputs["Emission Color"].default_value = (1.0, 1.0, 1.0, 1.0)
    bsdf.inputs["Emission Strength"].default_value = 4.0
    material.diffuse_color = (1.0, 1.0, 1.0, 1.0)

    eye = Vector(camera["location"])

    for index, label in enumerate(labels):
        curve = bpy.data.curves.new("label-%d" % index, type="FONT")
        curve.body = label["text"]
        curve.size = label.get("size", 0.12)
        # Centred on its anchor, so a label sits over the thing it names rather than starting there.
        curve.align_x = "CENTER"
        curve.align_y = "BOTTOM"
        # A little depth, so the text catches light from more than one direction and never vanishes edge-on.
        curve.extrude = 0.004

        obj = bpy.data.objects.new("label-%d" % index, curve)
        obj.location = tuple(label["at"])
        obj.data.materials.append(material)
        bpy.context.scene.collection.objects.link(obj)

        to_eye = eye - Vector(label["at"])
        if to_eye.length > 1e-6:
            obj.rotation_euler = to_eye.to_track_quat("Z", "Y").to_euler()


def render(plan):
    bpy.ops.wm.open_mainfile(filepath=plan["scene_blend"])

    _add_ground(plan["ground"])
    _add_camera(plan["camera"])
    _add_lights(plan["lighting"])
    _add_aim_lines(plan.get("aim_lines") or [])
    _add_labels(plan.get("labels") or [], plan["camera"])
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

    print("sdwa5-3d: rendered %s (%s camera, %s lighting, %d samples, %d aim line(s)) → %s" % (
        plan["scene_id"],
        plan["camera"]["preset"],
        plan["lighting"]["preset"],
        plan["render"]["samples"],
        len(plan.get("aim_lines") or []),
        plan["output"],
    ))


if __name__ == "__main__":
    render(export.parse_plan_argument(list(sys.argv)))
