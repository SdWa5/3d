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
        # Painted or coated plywood: matte, not metallic.
        CABINET: _principled(CABINET, cabinet_color, roughness=0.75),
        # Perforated steel grille reads darker and slightly metallic.
        GRILLE: _principled(GRILLE, grille_color, roughness=0.45, metallic=0.6),
        HANDLE: _principled(HANDLE, hex_to_linear_rgba("#1a1a1a"), roughness=0.5, metallic=0.4),
        # Driver cones are coated paper or fibre: very dark and almost entirely diffuse, which is what
        # makes a cone read as a cone rather than as a shiny funnel.
        CONE: _principled(CONE, hex_to_linear_rgba("#141414"), roughness=0.88),
        # Horn flares are moulded plastic or painted ply — lighter than the cabinet so the mouth reads
        # as an opening with something inside it.
        HORN: _principled(HORN, hex_to_linear_rgba("#3a3a3c"), roughness=0.55),
        RIGGING: _principled(RIGGING, hex_to_linear_rgba("#9a9a9a"), roughness=0.35, metallic=0.9),
        # Estimated marker glows so a guessed cabinet is impossible to miss in the viewport.
        ESTIMATED: _principled(
            ESTIMATED, hex_to_linear_rgba("#ff8800"), roughness=0.4, emission_strength=2.0
        ),
    }
