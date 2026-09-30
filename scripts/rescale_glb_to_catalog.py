#!/usr/bin/env python3
"""Rescale Beitelia GLB files to the dish-dimension catalog without touching mesh data.

This edits only the active scene's root transform in each GLB's JSON chunk. Binary
geometry, materials, textures, animation data, and all other chunks are preserved.
Run with --dry-run first. Applying changes requires an explicit backup directory.
"""

import argparse
import csv
import hashlib
import json
import math
import os
import re
import shutil
import struct
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path


def mat_mul(a, b):
    return [[sum(a[r][k] * b[k][c] for k in range(4)) for c in range(4)] for r in range(4)]


def node_matrix(node):
    if "matrix" in node:
        m = node["matrix"]
        return [[m[c * 4 + r] for c in range(4)] for r in range(4)]
    t = node.get("translation", [0.0, 0.0, 0.0])
    s = node.get("scale", [1.0, 1.0, 1.0])
    x, y, z, w = node.get("rotation", [0.0, 0.0, 0.0, 1.0])
    r = [
        [1 - 2 * (y * y + z * z), 2 * (x * y - z * w), 2 * (x * z + y * w)],
        [2 * (x * y + z * w), 1 - 2 * (x * x + z * z), 2 * (y * z - x * w)],
        [2 * (x * z - y * w), 2 * (y * z + x * w), 1 - 2 * (x * x + y * y)],
    ]
    return [
        [r[0][0] * s[0], r[0][1] * s[1], r[0][2] * s[2], t[0]],
        [r[1][0] * s[0], r[1][1] * s[1], r[1][2] * s[2], t[1]],
        [r[2][0] * s[0], r[2][1] * s[1], r[2][2] * s[2], t[2]],
        [0.0, 0.0, 0.0, 1.0],
    ]


def transform_point(m, p):
    v = [p[0], p[1], p[2], 1.0]
    return [sum(m[r][c] * v[c] for c in range(4)) for r in range(3)]


def load_glb(path):
    raw = path.read_bytes()
    if len(raw) < 20 or raw[:4] != b"glTF":
        raise ValueError("not a GLB file")
    magic, version, total_length = struct.unpack_from("<4sII", raw, 0)
    if version != 2 or total_length != len(raw):
        raise ValueError("invalid GLB v2 header/length")
    chunks = []
    offset = 12
    document = None
    while offset < len(raw):
        length, kind = struct.unpack_from("<II", raw, offset)
        offset += 8
        chunk = raw[offset : offset + length]
        if len(chunk) != length:
            raise ValueError("truncated GLB chunk")
        chunks.append((kind, chunk))
        if kind == 0x4E4F534A:
            document = json.loads(chunk.decode("utf-8").rstrip(" \x00"))
        offset += length
    if document is None:
        raise ValueError("missing JSON chunk")
    return document, chunks


def save_glb(path, document, chunks):
    json_chunk = json.dumps(document, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    json_chunk += b" " * ((-len(json_chunk)) % 4)
    out = bytearray(b"glTF" + struct.pack("<II", 2, 0))
    for kind, chunk in chunks:
        if kind == 0x4E4F534A:
            chunk = json_chunk
        out += struct.pack("<II", len(chunk), kind) + chunk
    struct.pack_into("<I", out, 8, len(out))
    fd, temp_name = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=str(path.parent))
    try:
        with os.fdopen(fd, "wb") as out_file:
            out_file.write(out)
            out_file.flush()
            os.fsync(out_file.fileno())
        os.chmod(temp_name, path.stat().st_mode)
        os.replace(temp_name, path)
    finally:
        if os.path.exists(temp_name):
            os.unlink(temp_name)


def load_catalog(csv_path, vessel_csv):
    if csv_path.suffix.lower() == ".json":
        data = json.loads(csv_path.read_text(encoding="utf-8"))
        return {int(key): value for key, value in data.items()}
    vessel_names = {}
    if vessel_csv and vessel_csv.exists():
        with vessel_csv.open(newline="", encoding="utf-8-sig") as f:
            vessel_names = {r["vessel"].strip(): r.get("name", "") for r in csv.DictReader(f)}
    result = {}
    with csv_path.open(newline="", encoding="utf-8-sig") as f:
        for row in csv.DictReader(f):
            pid = int(row["id"])
            try:
                length = float(row["length_cm"]) if row["length_cm"].strip() else None
                width = float(row["width_cm"]) if row["width_cm"].strip() else None
                diameter = float(row["diameter_cm"]) if row["diameter_cm"].strip() else None
            except ValueError:
                continue
            vessel_name = row.get("vessel_name", "")
            vessel_code = row.get("vessel", "").strip()
            result[pid] = {
                "id": pid,
                "name": row.get("name", ""),
                "shape": row.get("shape", "").strip().lower(),
                "length_cm": length,
                "width_cm": width,
                "diameter_cm": diameter,
                "has_handles": "handle" in (vessel_name + " " + vessel_names.get(vessel_code, "")).lower(),
                "confirm": row.get("confirm", "").strip(),
            }
    return result


def active_roots(document):
    scenes = document.get("scenes", [])
    if not scenes:
        raise ValueError("GLB has no scenes")
    scene_index = document.get("scene", 0)
    return scenes[scene_index].get("nodes", [])


def collect_bounds(document):
    roots = active_roots(document)
    nodes = document.get("nodes", [])
    accessors = document.get("accessors", [])
    meshes = document.get("meshes", [])
    min_all = [float("inf")] * 3
    max_all = [float("-inf")] * 3
    found = 0

    def visit(index, parent, ancestry):
        nonlocal found
        if index in ancestry:
            raise ValueError("node hierarchy contains a cycle")
        node = nodes[index]
        world = mat_mul(parent, node_matrix(node))
        if "mesh" in node:
            mesh = meshes[node["mesh"]]
            for primitive in mesh.get("primitives", []):
                accessor_index = primitive.get("attributes", {}).get("POSITION")
                if accessor_index is None:
                    continue
                accessor = accessors[accessor_index]
                lo, hi = accessor.get("min"), accessor.get("max")
                if lo is None or hi is None:
                    raise ValueError("POSITION accessor is missing min/max bounds")
                found += 1
                for mask in range(8):
                    p = [hi[a] if mask & (1 << a) else lo[a] for a in range(3)]
                    q = transform_point(world, p)
                    for axis in range(3):
                        min_all[axis] = min(min_all[axis], q[axis])
                        max_all[axis] = max(max_all[axis], q[axis])
        for child in node.get("children", []):
            visit(child, world, ancestry | {index})

    identity = [[1.0 if r == c else 0.0 for c in range(4)] for r in range(4)]
    for root in roots:
        visit(root, identity, set())
    if not found:
        raise ValueError("no POSITION accessor bounds found in active scene")
    return roots, min_all, max_all


def dimensions_cm(info, extents):
    major, minor = sorted((extents[0], extents[2]), reverse=True)
    shape = info["shape"]
    if shape == "round":
        target = info["diameter_cm"]
        if target is None:
            raise ValueError("round catalog entry has no diameter")
        factor = (target / 100.0) / (minor if info["has_handles"] else (major + minor) / 2.0)
    elif shape in ("oval", "rect"):
        length, width = info["length_cm"], info["width_cm"]
        if length is None or width is None:
            raise ValueError("catalog entry is missing length/width")
        if info["has_handles"]:
            factor = (width / 100.0) / minor
        elif shape == "rect" and math.isclose(length, width, rel_tol=0.0, abs_tol=1e-8):
            factor = (length / 100.0) / major
        else:
            factor = ((length / 100.0) / major + (width / 100.0) / minor) / 2.0
    else:
        raise ValueError("unknown catalog shape: " + repr(shape))
    if not math.isfinite(factor) or factor <= 0:
        raise ValueError("invalid computed scale factor")
    return factor, major * factor * 100.0, minor * factor * 100.0


def scale_root(node, factor, marker):
    if "matrix" in node:
        matrix = node["matrix"]
        for column in range(3):
            for row in range(3):
                matrix[column * 4 + row] *= factor
    else:
        scale = node.setdefault("scale", [1.0, 1.0, 1.0])
        node["scale"] = [value * factor for value in scale]
    extras = node.setdefault("extras", {})
    if not isinstance(extras, dict):
        extras = {"originalExtras": extras}
        node["extras"] = extras
    extras["beiteliaPhysicalDimensions"] = marker


def sha256(path):
    h = hashlib.sha256()
    with path.open("rb") as f:
        for block in iter(lambda: f.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--models", required=True, type=Path)
    parser.add_argument("--catalog", required=True, type=Path)
    parser.add_argument("--vessels", type=Path)
    parser.add_argument("--apply", action="store_true", help="write resized GLBs")
    parser.add_argument(
        "--recalibrate-marked",
        action="store_true",
        help="recompute catalog scale for files that already have a sizing marker",
    )
    parser.add_argument("--backup-dir", type=Path, help="required with --apply; keep outside public web root")
    args = parser.parse_args()
    if args.apply and not args.backup_dir:
        parser.error("--apply requires --backup-dir")
    if args.apply and (args.backup_dir == args.models or args.models in args.backup_dir.parents):
        parser.error("backup directory must be outside the public models directory")

    catalog = load_catalog(args.catalog, args.vessels)
    files = sorted(args.models.glob("dish_*.glb"))
    changed = skipped = errors = 0
    if args.apply:
        args.backup_dir.mkdir(parents=True, exist_ok=False)
    print("MODE:", "APPLY WITH BACKUP" if args.apply else "DRY RUN")
    print("MODEL FILES:", len(files), "CATALOG ENTRIES:", len(catalog))
    for path in files:
        match = re.fullmatch(r"dish_(\d+)\.glb", path.name)
        if not match:
            skipped += 1
            print("SKIP", path.name, "unrecognized filename")
            continue
        pid = int(match.group(1))
        info = catalog.get(pid)
        if not info or info["shape"] not in ("round", "oval", "rect") or not any(
            info[k] is not None for k in ("diameter_cm", "length_cm", "width_cm")
        ):
            skipped += 1
            print("SKIP", path.name, "no catalog dimensions")
            continue
        try:
            document, chunks = load_glb(path)
            roots, lo, hi = collect_bounds(document)
            if len(roots) != 1:
                raise ValueError("active scene has multiple root nodes; refusing ambiguous global resize")
            root_node = document["nodes"][roots[0]]
            previous_marker = root_node.get("extras", {}).get("beiteliaPhysicalDimensions")
            if previous_marker and not args.recalibrate_marked:
                skipped += 1
                print("SKIP", path.name, "already has Beitelia dimension marker")
                continue
            ext = [hi[i] - lo[i] for i in range(3)]
            factor, new_major, new_minor = dimensions_cm(info, ext)
            old_major, old_minor = sorted((ext[0] * 100.0, ext[2] * 100.0), reverse=True)
            if args.apply:
                digest = sha256(path)
                backup = args.backup_dir / path.name
                shutil.copy2(path, backup)
                marker = {
                    "catalogId": pid,
                    "target": {"shape": info["shape"], "lengthCm": info["length_cm"], "widthCm": info["width_cm"], "diameterCm": info["diameter_cm"]},
                    "factor": factor,
                    "originalSha256": previous_marker.get("originalSha256", digest) if isinstance(previous_marker, dict) else digest,
                    "sizingInputSha256": digest,
                    "appliedAt": datetime.now(timezone.utc).isoformat(),
                }
                if isinstance(previous_marker, dict):
                    marker["previousFactor"] = previous_marker.get("factor")
                    marker["previousAppliedAt"] = previous_marker.get("appliedAt")
                scale_root(root_node, factor, marker)
                save_glb(path, document, chunks)
                changed += 1
                state = "APPLIED"
            else:
                changed += 1
                state = "WOULD APPLY"
            target = info["diameter_cm"] if info["shape"] == "round" else info["length_cm"]
            target_w = "" if info["shape"] == "round" else f" x {info['width_cm']:g}"
            print(f"{state} {path.name}: {old_major:.2f} x {old_minor:.2f} cm -> {new_major:.2f} x {new_minor:.2f} cm; catalog {target:g}{target_w} cm; factor {factor:.6f}")
        except Exception as exc:
            errors += 1
            print("ERROR", path.name, type(exc).__name__ + ":", exc)
    print(f"SUMMARY: {changed} {'updated' if args.apply else 'ready'}, {skipped} skipped, {errors} errors")
    if args.apply:
        print("BACKUPS:", args.backup_dir)
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
