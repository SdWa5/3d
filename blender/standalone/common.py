"""Shared plumbing for the standalone models under `standalone/`.

A standalone model is a single hand-modelled loudspeaker built by its own bpy script rather than from a spec. Each
script owns its geometry and uses this module for everything around it: arguments, the scene skeleton, checks,
previews and output. The output keeps the library's promises, so `tools/check-glb.py` and the PHPUnit suite can
check a committed model the same way as a generated one.

    blender -b --factory-startup --python blender/standalone/build_<id>.py -- --out <dir> [--ref <dir>]

`--ref` points at the third-party drawings the model was traced from. With it the build packs them into the .blend
as true-scale image empties and renders ortho views for an overlay check. Without it neither happens, and that is
how the committed files are built: the drawings are not ours to publish.
"""

import json
import math
import os
import sys

import bmesh
import bpy
from mathutils import Matrix, Vector

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from lib import export, materials  # noqa: E402  (after sys.path)


def parse_args(argv):
    """Read `-- --out <dir> [--ref <dir>]` from Blender's argument list."""
    args = argv[argv.index("--") + 1:] if "--" in argv else []
    values = {"--out": None, "--ref": None}
    while args:
        flag = args.pop(0)
        if flag not in values or not args:
            raise SystemExit("usage: blender -b --factory-startup --python <script> -- --out <dir> [--ref <dir>]")
        values[flag] = args.pop(0)
    if values["--out"] is None:
        raise SystemExit("--out is required")
    os.makedirs(values["--out"], exist_ok=True)

    return values["--out"], values["--ref"]


class Model:
    """The scene skeleton: one root collection named after the model, sub-collections, and a root empty."""

    def __init__(self, model_id, collection_names):
        export.reset_scene()
        self.id = model_id
        self.scene = bpy.context.scene
        self.collection = export.collection_for(model_id)
        self.collections = {}
        for name in (*collection_names, "Preview setup"):
            self.collections[name] = bpy.data.collections.new(name)
            self.collection.children.link(self.collections[name])
        self.root = bpy.data.objects.new(model_id, None)
        self.root.empty_display_type = "PLAIN_AXES"
        self.root.empty_display_size = 0.1
        self.collection.objects.link(self.root)

    def material(self, name, hex_rgb, roughness=0.6, metallic=0.0):
        rgba = materials.hex_to_linear_rgba(hex_rgb)
        mat = bpy.data.materials.new(name)
        mat.use_nodes = True
        bsdf = mat.node_tree.nodes["Principled BSDF"]
        bsdf.inputs["Base Color"].default_value = rgba
        bsdf.inputs["Roughness"].default_value = roughness
        bsdf.inputs["Metallic"].default_value = metallic
        mat.diffuse_color = rgba
        mat.roughness = roughness
        mat.metallic = metallic

        return mat

    def mesh_object(self, name, bm, collection, mat):
        """Turn a bmesh into an object parented to the root; frees the bmesh."""
        bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
        me = bpy.data.meshes.new(name)
        bm.to_mesh(me)
        bm.free()
        obj = bpy.data.objects.new(name, me)
        obj.data.materials.append(mat)
        self.collections[collection].objects.link(obj)
        obj.parent = self.root

        return obj

    def readme(self, text):
        bpy.data.texts.new("README").write(text)

    def metadata(self, width, height, depth, provenance, source):
        """The same `sdwa5_metadata` a generated model carries, so `tools/check-glb.py` reads both alike."""
        self.root["sdwa5_metadata"] = json.dumps({
            "id": self.id,
            "dimensions_m": {"width": width, "height": height, "depth": depth},
            "origin": "bottom-center",
            "provenance": provenance,
            "source": source,
        }, sort_keys=True)
        self.root["sdwa5_id"] = self.id
        self.root["sdwa5_dimensions_m"] = [width, height, depth]

    def reference_image(self, collection, path, name, size, location, rows):
        """A drawing as a packed, half-transparent image empty. `rows` turns local +Z into the view axis."""
        img = bpy.data.images.load(path)
        img.pack()
        ref = bpy.data.objects.new(name, None)
        ref.empty_display_type = "IMAGE"
        ref.data = img
        ref.empty_display_size = size
        ref.empty_image_offset = (-0.5, -0.5)
        ref.empty_image_depth = "FRONT"
        ref.show_empty_image_only_axis_aligned = True
        ref.use_empty_image_alpha = True
        ref.color[3] = 0.5
        self.collections[collection].objects.link(ref)
        ref.parent = self.root
        ref.matrix_world = Matrix.Translation(location) @ Matrix(rows).to_4x4()

        return ref

    def hide_in_viewport(self, collection):
        self.collections[collection].hide_render = True
        bpy.context.view_layer.layer_collection.children[self.collection.name].children[collection].hide_viewport = True


def difference(obj, cutters):
    """Bake an EXACT boolean difference into obj's mesh, so the cutters can be deleted afterwards."""
    for i, cutter in enumerate(cutters):
        mod = obj.modifiers.new("cut%d" % i, "BOOLEAN")
        mod.operation = "DIFFERENCE"
        mod.solver = "EXACT"
        mod.object = cutter
    depsgraph = bpy.context.evaluated_depsgraph_get()
    me = bpy.data.meshes.new_from_object(obj.evaluated_get(depsgraph))
    old = obj.data
    obj.modifiers.clear()
    obj.data = me
    bpy.data.meshes.remove(old)
    me.name = obj.name


def bake_transforms(objects):
    """Move every object's transform into its mesh, leaving it at identity.

    `tools/check-glb.py` sums each node's translation and nothing else, which holds for a generated model and has to
    hold here too, so no exported node may carry a rotation.
    """
    for obj in objects:
        if obj.data.users > 1:
            obj.data = obj.data.copy()
        obj.data.transform(obj.matrix_world)
        obj.matrix_world = Matrix.Identity(4)


def check_parts(parts, width, height, depth, describe=None):
    """Print each part's extents, then assert every part is closed with positive volume and the union is the box.

    `describe(obj, lo, hi, volume_m3)` may format the line in the model's own units. Returns per-part volumes in m³.
    """
    bpy.context.view_layer.update()
    depsgraph = bpy.context.evaluated_depsgraph_get()
    lo = Vector((math.inf,) * 3)
    hi = Vector((-math.inf,) * 3)
    failures = []
    volumes = {}
    for obj in parts:
        evaluated = obj.evaluated_get(depsgraph)
        me = evaluated.to_mesh()
        bm = bmesh.new()
        bm.from_mesh(me)
        bm.transform(obj.matrix_world)
        evaluated.to_mesh_clear()
        manifold = all(e.is_manifold for e in bm.edges)
        volume = bm.calc_volume(signed=True)
        olo = Vector([min(v.co[i] for v in bm.verts) for i in range(3)])
        ohi = Vector([max(v.co[i] for v in bm.verts) for i in range(3)])
        bm.free()
        for i in range(3):
            lo[i] = min(lo[i], olo[i])
            hi[i] = max(hi[i], ohi[i])
        volumes[obj.name] = volume
        line = describe(obj, olo, ohi, volume) if describe else "%s %s..%s" % (obj.name, tuple(olo), tuple(ohi))
        print("PART %s  manifold %s" % (line, manifold))
        if not manifold or volume <= 0:
            failures.append(obj.name)

    size = hi - lo
    print("BBOX %.5f x %.5f x %.5f m, lo (%.5f, %.5f, %.5f)" % (*size, *lo))
    assert not failures, "not closed or empty: %s" % failures
    assert abs(size.x - width) < 1e-3 and abs(size.y - depth) < 1e-3 and abs(size.z - height) < 1e-3, "bounding box"
    assert abs(lo.z) < 1e-4 and abs(lo.x + width / 2) < 1e-3 and abs(lo.y + depth / 2) < 1e-3, "origin"

    return volumes


class Previews:
    """Cycles on the CPU against a white world, one sun, one camera that each render moves."""

    def __init__(self, model, out_dir, samples=48):
        self.model = model
        self.out_dir = out_dir
        scene = model.scene
        scene.render.engine = "CYCLES"
        scene.cycles.device = "CPU"
        scene.cycles.samples = samples
        scene.cycles.use_denoising = True
        scene.view_settings.view_transform = "Standard"
        world = scene.world or bpy.data.worlds.new("World")
        scene.world = world
        world.use_nodes = True
        world.node_tree.nodes["Background"].inputs["Color"].default_value = (1, 1, 1, 1)
        world.node_tree.nodes["Background"].inputs["Strength"].default_value = 1.0

        sun_data = bpy.data.lights.new("Sun", "SUN")
        sun_data.energy = 3.0
        sun_data.angle = math.radians(15)
        sun = bpy.data.objects.new("Sun", sun_data)
        model.collections["Preview setup"].objects.link(sun)
        sun.location = (-1.0, -1.5, 2.0)
        self.look_at(sun, (0, 0, 0.4))

        self.camera = bpy.data.objects.new("Preview camera", bpy.data.cameras.new("Preview camera"))
        model.collections["Preview setup"].objects.link(self.camera)
        scene.camera = self.camera

    @staticmethod
    def look_at(obj, target):
        obj.rotation_euler = (Vector(target) - obj.location).to_track_quat("-Z", "Y").to_euler()

    def ortho(self, path, res, scale, location, rotation, hidden=(), out_dir=None):
        self.camera.data.type = "ORTHO"
        self.camera.data.ortho_scale = scale
        self.camera.location = location
        self.camera.rotation_euler = rotation
        self._render(path, res, hidden, out_dir)

    def perspective(self, path, res, location, target, hidden=(), lens=50):
        self.camera.data.type = "PERSP"
        self.camera.data.lens = lens
        self.camera.location = location
        self.look_at(self.camera, target)
        self._render(path, res, hidden)

    def _render(self, path, res, hidden, out_dir=None):
        scene = self.model.scene
        for obj in hidden:
            obj.hide_render = True
        scene.render.resolution_x, scene.render.resolution_y = res
        scene.render.resolution_percentage = 100
        scene.render.filepath = os.path.join(out_dir or self.out_dir, path)
        bpy.ops.render.render(write_still=True)
        for obj in hidden:
            obj.hide_render = False
        print("RENDER %s" % scene.render.filepath)


def save(model, out_dir):
    """Write `<id>.blend` and `<id>.glb`. Only renderable objects reach the .glb, which leaves out cutters."""
    blend = os.path.join(out_dir, model.id + ".blend")
    export.save_blend(blend)
    print("SAVED %s" % blend)
    glb = os.path.join(out_dir, model.id + ".glb")
    export.export_glb(glb)
    print("SAVED %s" % glb)
