"""Scene setup, metadata and file output.

Metadata is attached as object custom properties and exported into the glTF `extras` block, so a
model stays self-describing after it leaves this repo: whoever opens the .glb can still read what
it is, what it weighs and whether its numbers were ever measured.
"""

import json
import os

import addon_utils
import bpy


def reset_scene():
    """Empty scene with metric units at 1 unit = 1 m.

    Called before every build so the result never depends on whatever was loaded before, and so
    dimensions in the spec mean exactly what they say in Blender.
    """
    bpy.ops.wm.read_factory_settings(use_empty=True)

    unit = bpy.context.scene.unit_settings
    unit.system = "METRIC"
    unit.scale_length = 1.0
    unit.length_unit = "METERS"

    # --factory-startup should have it enabled already; being explicit costs nothing and turns a
    # confusing "export operator missing" failure into a clear one.
    try:
        addon_utils.enable("io_scene_gltf2", default_set=False, persistent=True)
    except Exception as error:  # pragma: no cover - depends on the Blender build
        print("sdwa5-3d: could not enable the glTF exporter: %s" % error)


def collection_for(name):
    """A collection named after the device, linked into the scene.

    One collection per device is what lets the asset library offer the whole cabinet — shell,
    grille, markers — as a single draggable asset.
    """
    collection = bpy.data.collections.new(name)
    bpy.context.scene.collection.children.link(collection)

    return collection


def attach_metadata(obj, metadata):
    """Write the spec metadata onto an object.

    Stored twice on purpose: a JSON string that always round-trips through glTF intact, plus a few
    flat scalars that are convenient to read in Blender's UI and in drivers.
    """
    obj["sdwa5_metadata"] = json.dumps(metadata, sort_keys=True)
    obj["sdwa5_id"] = metadata["id"]
    obj["sdwa5_category"] = metadata["category"]
    obj["sdwa5_subtype"] = metadata["subtype"]
    obj["sdwa5_provenance"] = metadata["provenance"]
    obj["sdwa5_weight_kg"] = float(metadata["weight_kg"])
    obj["sdwa5_flyable"] = bool(metadata["flyable"])

    dims = metadata["dimensions_m"]
    obj["sdwa5_dimensions_m"] = [float(dims["width"]), float(dims["height"]), float(dims["depth"])]


def _ensure_dir(path):
    directory = os.path.dirname(path)
    if directory and not os.path.isdir(directory):
        os.makedirs(directory, exist_ok=True)


def export_glb(filepath):
    """Export the cabinet as a single binary glTF.

    `export_apply` bakes the chamfer modifier in, `export_extras` carries the metadata across, and
    glTF's own Y-up convention is applied by the exporter — the scene itself stays Z-up.

    `use_renderable` is what keeps the promise that a model's bounding box equals its declared
    dimensions: the rigging markers and the estimated tag are render-invisible, so they stay in the
    .blend where they are useful to snap to, and never end up as stray cubes in the .glb.
    """
    _ensure_dir(filepath)
    bpy.ops.export_scene.gltf(
        filepath=filepath,
        export_format="GLB",
        use_selection=False,
        use_renderable=True,
        export_apply=True,
        export_extras=True,
        export_yup=True,
        export_cameras=False,
        export_lights=False,
    )


def save_blend(filepath):
    _ensure_dir(filepath)
    bpy.ops.wm.save_as_mainfile(filepath=filepath, compress=True)


def parse_plan_argument(argv):
    """Read `-- --plan <file>` from Blender's argument list and load the JSON.

    Blender swallows everything before `--`, so the script's own arguments live after it.
    """
    if "--" in argv:
        argv = argv[argv.index("--") + 1:]
    else:
        argv = []

    if len(argv) < 2 or argv[0] != "--plan":
        raise SystemExit("usage: blender --background --python <script> -- --plan <plan.json>")

    with open(argv[1], "r", encoding="utf-8") as handle:
        return json.load(handle)
