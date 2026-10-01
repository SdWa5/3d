"""Shared material set for every generated model.

Materials are created per build with fixed names, so every cabinet in the library reacts to
light the same way and a colour scheme can later be changed in one place instead of per model.
Hex colours from the specs are sRGB; Blender works in linear, so they are converted.
"""

import bpy

CABINET = "sdwa5-cabinet"
GRILLE = "sdwa5-grille"
HANDLE = "sdwa5-handle"
RIGGING = "sdwa5-rigging"
CONE = "sdwa5-cone"
HORN = "sdwa5-horn"
ESTIMATED = "sdwa5-estimated"
COVERAGE = "sdwa5-coverage"
FAULT = "sdwa5-fault"
VEHICLE_OUTLINE = "sdwa5-vehicle-outline"
BAY = "sdwa5-bay"
BAY_FLOOR = "sdwa5-bay-floor"
FRONT_IMAGE = "sdwa5-front-image"
HARDWARE = "sdwa5-hardware"


def hex_to_linear_rgba(value, alpha=1.0):
    """'#rrggbb' to a linear RGBA tuple.

    The sRGB transfer function is applied per channel; skipping it makes every model noticeably
    too bright, which matters because the whole point is that they all match.
    """
    text = value.lstrip("#")
    if len(text) != 6:
        raise ValueError("expected a #rrggbb colour, got %r" % value)

    channels = []
    for offset in (0, 2, 4):
        srgb = int(text[offset:offset + 2], 16) / 255.0
        if srgb <= 0.04045:
            channels.append(srgb / 12.92)
        else:
            channels.append(((srgb + 0.055) / 1.055) ** 2.4)

    return (channels[0], channels[1], channels[2], alpha)


def _principled(name, color, roughness, metallic=0.0, emission_strength=0.0):
    material = bpy.data.materials.get(name)
    if material is None:
        material = bpy.data.materials.new(name)
    material.use_nodes = True

    bsdf = material.node_tree.nodes.get("Principled BSDF")
    if bsdf is None:
        bsdf = material.node_tree.nodes.new("ShaderNodeBsdfPrincipled")
        output = material.node_tree.nodes.get("Material Output")
        if output is not None:
            material.node_tree.links.new(bsdf.outputs["BSDF"], output.inputs["Surface"])

    bsdf.inputs["Base Color"].default_value = color
    bsdf.inputs["Roughness"].default_value = roughness
    bsdf.inputs["Metallic"].default_value = metallic
    if emission_strength > 0.0:
        bsdf.inputs["Emission Color"].default_value = color
        bsdf.inputs["Emission Strength"].default_value = emission_strength

    # Viewport colour, so the solid-shading view is readable too.
    material.diffuse_color = color
    material.roughness = roughness

    return material


def build_set(appearance):
    """Create the material set for one device from its `appearance` plan section.

    Returns a dict keyed by the module-level material name constants.
    """
    cabinet_color = hex_to_linear_rgba(appearance["color"])
    grille_color = hex_to_linear_rgba(appearance["grille"]["color"] or appearance["color"])

    return {
        # Every part of a cabinet is one colour for now — `appearance.color`, whatever that says. The parts
        # still have their own materials so a future change can differentiate them again without
        # restructuring anything, but they no longer differ by hue.
        #
        # What they differed by, and why it was dropped: the horn flares were #3a3a3c against a #141414
        # cabinet, deliberately lighter "so the mouth reads as an opening with something inside it", and the
        # handles were #1a1a1a. On the 18sound that horn is 0.362 m wide across a 0.466 m cabinet, so it
        # dominated the baffle and read as a differently-coloured panel rather than as a flare. Shape and
        # shading carry that distinction well enough on their own.
        #
        # `metallic` is dropped with the colour, not kept: it changes apparent shade more than roughness
        # does, so a metallic handle at the cabinet's own colour would still not match it. `roughness` is
        # kept, because it changes how sharp a highlight is rather than what colour the surface is.
        CABINET: _principled(CABINET, cabinet_color, roughness=0.75),
        GRILLE: _principled(GRILLE, grille_color, roughness=0.45),
        HANDLE: _principled(HANDLE, cabinet_color, roughness=0.5),
        # Coated paper or fibre: almost entirely diffuse, which is what makes a cone read as a cone rather
        # than as a shiny funnel.
        CONE: _principled(CONE, cabinet_color, roughness=0.88),
        # Moulded plastic or painted ply.
        HORN: _principled(HORN, cabinet_color, roughness=0.55),
        RIGGING: _principled(RIGGING, hex_to_linear_rgba("#9a9a9a"), roughness=0.35, metallic=0.9),
        # Black powder coat, as on a wind-up stand's legs, collars and winch. A fixed colour rather than the
        # device's own, because the same stand is chrome in its mast and black in its base.
        HARDWARE: _principled(HARDWARE, hex_to_linear_rgba("#1c1c1e"), roughness=0.6),
        # Estimated marker glows so a guessed cabinet is impossible to miss in the viewport.
        ESTIMATED: _principled(
            ESTIMATED, hex_to_linear_rgba("#ff8800"), roughness=0.4, emission_strength=2.0
        ),
        # **A transporter's cage, in three tiers of loudness.** The outline is the vehicle and wants to recede; the
        # bay is the volume somebody is packing and wants to be read; the floor between the wheel arches is the
        # part that decides whether a cabinet goes in at all, so it shouts. All three are emissive because a cage
        # in a dark corner of a frame is a cage nobody sees.
        VEHICLE_OUTLINE: _principled(
            VEHICLE_OUTLINE, hex_to_linear_rgba("#4a4a52"), roughness=0.5, emission_strength=0.4
        ),
        BAY: _principled(BAY, hex_to_linear_rgba("#33aaff"), roughness=0.4, emission_strength=3.0),
        BAY_FLOOR: _principled(BAY_FLOOR, hex_to_linear_rgba("#ffcc22"), roughness=0.4, emission_strength=4.0),
        # The coverage cone is drawn as a wireframe, so what this mostly decides is its viewport colour.
        COVERAGE: _principled(
            COVERAGE, hex_to_linear_rgba("#33aaff"), roughness=0.5, emission_strength=1.0
        ),
    }


def front_image(path):
    """A photograph of the cabinet's front, as an image texture on its own material.

    Given its own material rather than being mixed into the cabinet one, because only the front face
    carries it. Everything else on the block keeps `CABINET`, so a cabinet with no photograph and a
    cabinet with one differ in exactly one face.

    The image is packed into the .blend, and that carries more weight than it looks. Without it the
    .blend references a path outside the repository, so opening it anywhere else shows a pink
    cabinet — breakage that only turns up on somebody else's machine. It is also what makes the asset
    library correct: `build_library.py` appends each finished .blend with `bpy.data.libraries.load`,
    and a packed image travels with its material while a path reference would not.

    The fixed material name is safe for the same two reasons. `models:build` runs one Blender process
    per model, so two cabinets never share a process, and Blender renumbers duplicate names on append.

    Returns None when the file cannot be loaded, so the caller falls back to the plain front rather
    than exporting a model with a broken texture.
    """
    try:
        image = bpy.data.images.load(path, check_existing=True)
    except RuntimeError:
        return None

    image.pack()

    material = bpy.data.materials.get(FRONT_IMAGE)
    if material is None:
        material = bpy.data.materials.new(FRONT_IMAGE)
    material.use_nodes = True
    tree = material.node_tree

    bsdf = tree.nodes.get("Principled BSDF")
    if bsdf is None:
        bsdf = tree.nodes.new("ShaderNodeBsdfPrincipled")
        output = tree.nodes.get("Material Output")
        if output is not None:
            tree.links.new(bsdf.outputs["BSDF"], output.inputs["Surface"])

    texture = tree.nodes.get("sdwa5-front-texture")
    if texture is None:
        texture = tree.nodes.new("ShaderNodeTexImage")
        texture.name = "sdwa5-front-texture"
    texture.image = image
    # The photograph is the whole front and must not tile. Without this a UV rounding error at the
    # edge repeats the opposite side of the image across the seam.
    texture.extension = "EXTEND"

    tree.links.new(texture.outputs["Color"], bsdf.inputs["Base Color"])

    # A photograph already carries its own shading, so a glossy highlight on top of it reads as a
    # wet cabinet. Almost fully rough, and never metallic.
    bsdf.inputs["Roughness"].default_value = 0.9
    bsdf.inputs["Metallic"].default_value = 0.0
    # No specular at all. Roughness alone only spreads the highlight, and spread over a large face
    # under the studio's three 10 m area lights it lays a grey veil across the whole image. Measured
    # on PSL's 9 m deco panel, whose black ground rendered at 84 to 115 of 255 at the default 0.5
    # and at black with 0.
    bsdf.inputs["Specular IOR Level"].default_value = 0.0

    material.diffuse_color = (0.5, 0.5, 0.5, 1.0)
    material.roughness = 0.9

    return material


def fault_material():
    """The cage drawn around a cabinet the geometry checks object to.

    Standalone rather than part of {@see build_set}, because it belongs to a *scene* and not to a device: it has
    no `appearance` section behind it and asking for one would mean handing this a cabinet's colours to build a
    marker that must not look like any cabinet.

    Emissive and hard, because the one thing it must never do is look like part of the rig or get lost in a dark
    corner of the frame. Reused by name if the scene already has one, so a rig with six faults has one material.
    """
    existing = bpy.data.materials.get(FAULT)

    return existing or _principled(
        FAULT, hex_to_linear_rgba("#ff1133"), roughness=0.3, emission_strength=6.0
    )
