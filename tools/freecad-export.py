"""Export the solid geometry of a FreeCAD or STEP file to a mesh Blender can read.

Run through `ddev mesh-convert`, which supplies FreeCAD in a container — see meshes/README.md.

    python3 tools/freecad-export.py <input.FCStd|.step> <output.stl|.obj>

Only real solids are exported. A FreeCAD document is full of datum planes and axes, and exporting
those inflates the mesh's bounding box — which matters here because `mesh_override` checks the
result against the spec's declared dimensions and refuses a mismatch. A `PartDesign::Body` is
preferred when present: it is the finished solid, so exporting it avoids also emitting every
intermediate pad and pocket on top of each other.

The bounding box is printed in millimetres, because that is what you need in order to pick
`units` and `rotate_deg` for the spec.
"""

import os
import sys

import FreeCAD
import Mesh

# FreeCAD.open only understands its own .FCStd; exchange formats go through the Import module.
_EXCHANGE = (".step", ".stp", ".iges", ".igs", ".brep", ".brp")


def _open(source):
    if os.path.splitext(source)[1].lower() in _EXCHANGE:
        import Import

        doc = FreeCAD.newDocument("import")
        Import.insert(source, doc.Name)
        doc.recompute()

        return doc

    return FreeCAD.open(source)


def _targets(doc):
    """The objects worth exporting, most specific choice first."""
    bodies = [o for o in doc.Objects if getattr(o, "TypeId", "") == "PartDesign::Body"]
    if bodies:
        return bodies, "PartDesign bodies"

    solids = [
        o for o in doc.Objects
        if hasattr(o, "Shape") and o.Shape and not o.Shape.isNull() and o.Shape.Solids
    ]
    if solids:
        return solids, "objects with solids"

    # Last resort: anything with a shape at all. A sheet-metal or surface model lands here.
    shapes = [o for o in doc.Objects if hasattr(o, "Shape") and o.Shape and not o.Shape.isNull()]

    return shapes, "objects with any shape (no solids found)"


def _bounds(objects):
    box = None
    for obj in objects:
        shape_box = obj.Shape.BoundBox
        box = shape_box if box is None else box.united(shape_box)

    return box


def main(source, target):
    if not os.path.isfile(source):
        raise SystemExit("sdwa5-3d: no such file: %s" % source)

    doc = _open(source)
    objects, how = _targets(doc)
    if not objects:
        raise SystemExit("sdwa5-3d: %s contains no geometry" % source)

    print("sdwa5-3d: exporting %d %s: %s"
          % (len(objects), how, ", ".join(o.Name for o in objects[:6])))

    box = _bounds(objects)
    if box is not None:
        print("sdwa5-3d: bounding box %.1f x %.1f x %.1f mm (x, y, z)"
              % (box.XLength, box.YLength, box.ZLength))
        print("sdwa5-3d: pick mesh_override.units and rotate_deg so this maps to "
              "width x depth x height")

    Mesh.export(objects, target)
    print("sdwa5-3d: wrote %s" % target)


if __name__ == "__main__":
    if len(sys.argv) < 3:
        raise SystemExit("usage: freecad-export.py <input.FCStd|.step> <output.stl|.obj>")
    main(sys.argv[1], sys.argv[2])
