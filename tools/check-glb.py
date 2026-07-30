#!/usr/bin/env python3
"""Check that an exported .glb is internally consistent.

    python3 tools/check-glb.py build/glb/*.glb

Every model carries its declared dimensions in the glTF `extras`, so the file can be checked
against itself: the geometry's bounding box must equal `dimensions_m`, and the cabinet must sit on
the floor. That is the promise the whole library rests on — models from different specs only stack
and snap together if it holds — and a builder bug would otherwise be invisible until somebody
noticed a stack looking wrong.

Plain Python, no bpy: runnable without Blender, on the host or in the container.
"""

import glob
import json
import struct
import sys

TOLERANCE_M = 1e-4

_GLB_MAGIC = 0x46546C67
_CHUNK_JSON = 0x4E4F534A


def load_gltf_json(path):
    with open(path, "rb") as handle:
        magic, version, _length = struct.unpack("<III", handle.read(12))
        if magic != _GLB_MAGIC:
            raise ValueError("not a GLB file")
        if version != 2:
            raise ValueError("unexpected glTF version %d" % version)
        chunk_length, chunk_type = struct.unpack("<II", handle.read(8))
        if chunk_type != _CHUNK_JSON:
            raise ValueError("first chunk is not JSON")
        return json.loads(handle.read(chunk_length))


def bounding_box(gltf):
    """Union of every POSITION accessor, offset by its node's translation.

    Good enough because the builder never rotates or scales nodes — the glTF exporter bakes the
    Z-up to Y-up conversion into the coordinates themselves.
    """
    low = [float("inf")] * 3
    high = [float("-inf")] * 3
    for node in gltf.get("nodes", []):
        if "mesh" not in node:
            continue
        offset = node.get("translation", [0.0, 0.0, 0.0])
        for primitive in gltf["meshes"][node["mesh"]]["primitives"]:
            accessor = gltf["accessors"][primitive["attributes"]["POSITION"]]
            for axis in range(3):
                low[axis] = min(low[axis], accessor["min"][axis] + offset[axis])
                high[axis] = max(high[axis], accessor["max"][axis] + offset[axis])

    if low[0] == float("inf"):
        raise ValueError("no mesh geometry found")

    return low, high


def metadata_of(gltf):
    for node in gltf.get("nodes", []):
        extras = node.get("extras") or {}
        if "sdwa5_metadata" in extras:
            return json.loads(extras["sdwa5_metadata"])

    raise ValueError("no sdwa5_metadata in any node's extras")


def check(path):
    """Returns a list of problems; empty means the file is fine."""
    gltf = load_gltf_json(path)
    metadata = metadata_of(gltf)
    low, high = bounding_box(gltf)

    dims = metadata["dimensions_m"]
    # glTF is Y-up: x = width, y = height, z = depth.
    expected = [dims["width"], dims["height"], dims["depth"]]
    actual = [high[axis] - low[axis] for axis in range(3)]

    # An override mesh is real CAD and is allowed to differ from the declared size by the tolerance
    # the spec set; a generated model has no such excuse and is held to the tight default.
    override = metadata.get("mesh_override") or {}
    tolerance = float(override.get("tolerance_m", TOLERANCE_M)) if override else TOLERANCE_M

    problems = []
    for axis, label in enumerate(("width", "height", "depth")):
        if abs(actual[axis] - expected[axis]) > tolerance:
            problems.append(
                "%s is %.4f m but the spec says %.4f m" % (label, actual[axis], expected[axis])
            )

    # Only the default origin promises this; a cabinet with origin `rigging-point` hangs from its
    # hardware and a `geometric-center` one straddles zero on purpose.
    if metadata.get("origin") == "bottom-center" and abs(low[1]) > max(tolerance, TOLERANCE_M):
        problems.append("origin is bottom-center but the lowest point is at %.4f m" % low[1])

    # `provenance` is per field: the geometry is what this check is about.
    provenance = metadata.get("provenance") or {}
    if isinstance(provenance, dict):
        provenance = provenance.get("dimensions", "?")

    note = ""
    if override:
        deltas = [actual[i] - expected[i] for i in range(3)]
        worst = max(deltas, key=abs)
        note = "  [override %s, tolerance %.3f m, worst axis %+.4f m]" % (
            override.get("file", "?"), tolerance, worst)

    print("%s — %s, dims %s, %.4f × %.4f × %.4f m%s" % (
        path, metadata["id"], provenance, actual[0], actual[1], actual[2], note))
    for problem in problems:
        print("    FAIL: %s" % problem)

    return problems


def main(argv):
    paths = []
    for pattern in argv:
        paths.extend(sorted(glob.glob(pattern)) or [pattern])
    if not paths:
        print("usage: check-glb.py <glb> [<glb> ...]")
        return 2

    failed = 0
    for path in paths:
        try:
            if check(path):
                failed += 1
        except (OSError, ValueError) as error:
            print("%s — FAIL: %s" % (path, error))
            failed += 1

    print("\n%d file(s) checked, %d failed" % (len(paths), failed))

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
