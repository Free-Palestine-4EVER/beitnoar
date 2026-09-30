#!/usr/bin/env python3
"""
Beit Elia - AR Dish Models Rescaler
Applies verified physical serving vessel dimensions from BeitElia-AR-Dimensions-for-zzz
to all 101 3D GLB & USDZ models in public/storage/models/
"""

import os
import sys
import glob
import time
import shutil
import subprocess
import csv
from PIL import Image
import trimesh
from pxr import Usd, UsdGeom, UsdShade, Sdf, Vt

WORKSPACE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODELS_DIR = os.path.join(WORKSPACE_DIR, "public/storage/models")
CSV_PATH = "/Users/hideyourkids/Desktop/BeitElia-AR-Dimensions-for-zzz (2)/dish_dimensions.csv"
VESSEL_CSV_PATH = "/Users/hideyourkids/Desktop/BeitElia-AR-Dimensions-for-zzz (2)/vessel_list.csv"
TEMP_WORK = "/tmp/usdz_work_dimensions"

os.makedirs(TEMP_WORK, exist_ok=True)

def load_dimensions():
    vessels = {}
    if os.path.exists(VESSEL_CSV_PATH):
        with open(VESSEL_CSV_PATH, newline='', encoding='utf-8') as f:
            for v in csv.DictReader(f):
                vessels[v['vessel'].strip()] = v

    id_to_dim = {}
    with open(CSV_PATH, newline='', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for r in reader:
            pid = int(r['id'].strip())
            shape = r['shape'].strip().lower()
            l = float(r['length_cm']) if r['length_cm'].strip() else None
            w = float(r['width_cm']) if r['width_cm'].strip() else None
            d = float(r['diameter_cm']) if r['diameter_cm'].strip() else None
            vessel_code = r['vessel'].strip()
            vessel_name = r['vessel_name'].strip()
            v_info = vessels.get(vessel_code, {})
            has_handles = "handle" in vessel_name.lower() or "handle" in v_info.get('name', '').lower()

            id_to_dim[pid] = {
                'id': pid,
                'name': r['name'].strip(),
                'shape': shape,
                'length_cm': l,
                'width_cm': w,
                'diameter_cm': d,
                'has_handles': has_handles,
                'vessel_code': vessel_code,
                'vessel_name': vessel_name,
            }

    # Dish 049 fallback (same fresh salad family)
    if 49 in id_to_dim:
        id_to_dim[49]['shape'] = 'oval'
        id_to_dim[49]['length_cm'] = 24.0
        id_to_dim[49]['width_cm'] = 16.5
        id_to_dim[49]['has_handles'] = False

    return id_to_dim

def calculate_scale(dim_info, ext):
    major = max(ext[0], ext[2])
    minor = min(ext[0], ext[2])
    
    shape = dim_info['shape']
    has_handles = dim_info['has_handles']
    d = dim_info['diameter_cm']
    l = dim_info['length_cm']
    w = dim_info['width_cm']

    if shape == 'round':
        target_m = (d if d else 20.0) / 100.0
        if has_handles:
            # minor axis is rim diameter (without handles)
            scale = target_m / minor if minor > 0 else 1.0
        else:
            # circular plate/board
            scale = target_m / ((major + minor) / 2.0) if (major + minor) > 0 else 1.0
    elif shape in ['oval', 'rect']:
        target_w = (w if w else 20.0) / 100.0
        target_l = (l if l else 30.0) / 100.0
        if has_handles:
            # handles are along major axis, so minor axis (width) has no handles and is exact
            scale = target_w / minor if minor > 0 else (target_l / major)
        else:
            if shape == 'rect' and l == w: # square plate
                scale = target_l / major if major > 0 else 1.0
            else:
                scale_w = target_w / minor if minor > 0 else None
                scale_l = target_l / major if major > 0 else None
                if scale_w and scale_l:
                    # average of length and width scales to minimize distortion
                    scale = (scale_w + scale_l) / 2.0
                else:
                    scale = scale_l or scale_w or 1.0
    else:
        scale = 1.0

    return scale

def build_usdz(mesh_geom, dish_id, usdz_path):
    tex_filename = f"{dish_id}_diffuse.jpg"
    tex_path = os.path.join(TEMP_WORK, tex_filename)
    mat = mesh_geom.visual.material
    tex = getattr(mat, 'baseColorTexture', None) or getattr(mat, 'image', None)
    if tex:
        tex.convert('RGB').save(tex_path, quality=95)
    else:
        Image.new('RGB', (32, 32), (210, 190, 170)).save(tex_path)

    usd_filename = f"{dish_id}.usdc"
    usd_path = os.path.join(TEMP_WORK, usd_filename)
    if os.path.exists(usd_path):
        os.remove(usd_path)

    stage = Usd.Stage.CreateNew(usd_path)
    UsdGeom.SetStageUpAxis(stage, UsdGeom.Tokens.y)
    UsdGeom.SetStageMetersPerUnit(stage, UsdGeom.LinearUnits.meters)

    root = UsdGeom.Xform.Define(stage, '/Root')
    stage.SetDefaultPrim(root.GetPrim())

    mesh = UsdGeom.Mesh.Define(stage, '/Root/Dish')
    mesh.CreatePointsAttr(Vt.Vec3fArray.FromNumpy(mesh_geom.vertices))
    mesh.CreateFaceVertexIndicesAttr(Vt.IntArray.FromNumpy(mesh_geom.faces.flatten()))
    mesh.CreateFaceVertexCountsAttr(Vt.IntArray([3] * len(mesh_geom.faces)))

    if hasattr(mesh_geom, 'vertex_normals') and mesh_geom.vertex_normals is not None:
        mesh.CreateNormalsAttr(Vt.Vec3fArray.FromNumpy(mesh_geom.vertex_normals))
        mesh.SetNormalsInterpolation(UsdGeom.Tokens.vertex)

    uvs = mesh_geom.visual.uv.copy()
    pv = UsdGeom.PrimvarsAPI(mesh).CreatePrimvar('st', Sdf.ValueTypeNames.TexCoord2fArray, UsdGeom.Tokens.vertex)
    pv.Set(Vt.Vec2fArray.FromNumpy(uvs))

    mat_path = '/Root/Materials/DishMat'
    usd_mat = UsdShade.Material.Define(stage, mat_path)

    pbr = UsdShade.Shader.Define(stage, f'{mat_path}/PBRShader')
    pbr.CreateIdAttr('UsdPreviewSurface')
    pbr.CreateInput('roughness', Sdf.ValueTypeNames.Float).Set(0.35)
    pbr.CreateInput('metallic', Sdf.ValueTypeNames.Float).Set(0.0)
    pbr.CreateInput('clearcoat', Sdf.ValueTypeNames.Float).Set(0.1)
    pbr.CreateInput('opacity', Sdf.ValueTypeNames.Float).Set(1.0)

    st_reader = UsdShade.Shader.Define(stage, f'{mat_path}/stReader')
    st_reader.CreateIdAttr('UsdPrimvarReader_float2')
    st_reader.CreateInput('varname', Sdf.ValueTypeNames.String).Set('st')
    st_reader.CreateOutput('result', Sdf.ValueTypeNames.Float2)

    tex_shader = UsdShade.Shader.Define(stage, f'{mat_path}/diffuseTex')
    tex_shader.CreateIdAttr('UsdUVTexture')
    tex_shader.CreateInput('file', Sdf.ValueTypeNames.Asset).Set(tex_filename)
    tex_shader.CreateInput('st', Sdf.ValueTypeNames.Float2).ConnectToSource(st_reader.ConnectableAPI(), 'result')
    tex_shader.CreateInput('wrapS', Sdf.ValueTypeNames.Token).Set('repeat')
    tex_shader.CreateInput('wrapT', Sdf.ValueTypeNames.Token).Set('repeat')
    tex_shader.CreateOutput('rgb', Sdf.ValueTypeNames.Float3)

    pbr.CreateInput('diffuseColor', Sdf.ValueTypeNames.Color3f).ConnectToSource(tex_shader.ConnectableAPI(), 'rgb')
    usd_mat.CreateSurfaceOutput().ConnectToSource(pbr.ConnectableAPI(), 'surface')

    UsdShade.MaterialBindingAPI.Apply(mesh.GetPrim()).Bind(usd_mat)
    stage.Save()
    del stage

    cmd = f"cd \"{TEMP_WORK}\" && /usr/bin/usdzip \"{usdz_path}\" \"{usd_filename}\" \"{tex_filename}\""
    subprocess.run(cmd, shell=True, check=True, capture_output=True)

    try:
        os.remove(usd_path)
        os.remove(tex_path)
    except Exception:
        pass

def main():
    print("=" * 80)
    print("APPLYING VERIFIED PHYSICAL DISH DIMENSIONS TO ALL 3D AR MODELS")
    print("=" * 80)

    id_to_dim = load_dimensions()
    print(f"Loaded dimensions for {len(id_to_dim)} menu items from CSV.")

    glb_files = sorted(glob.glob(os.path.join(MODELS_DIR, "dish_*.glb")))
    total = len(glb_files)
    print(f"Found {total} dish GLB files in {MODELS_DIR}")

    t0 = time.time()
    success = 0

    results = []

    for idx, glb_path in enumerate(glb_files, 1):
        dish_file = os.path.basename(glb_path)
        dish_id_str = os.path.splitext(dish_file)[0]
        try:
            pid = int(dish_id_str.replace("dish_", ""))
        except ValueError:
            pid = 0

        usdz_path = os.path.join(MODELS_DIR, f"{dish_id_str}.usdz")
        dim_info = id_to_dim.get(pid, {
            'id': pid,
            'name': dish_id_str,
            'shape': 'round',
            'diameter_cm': 20.0,
            'length_cm': 20.0,
            'width_cm': 20.0,
            'has_handles': False,
        })

        try:
            s = trimesh.load(glb_path)
            mesh_geom = list(s.geometry.values())[0]

            # 1. Center dish at (0, 0) on table, ground base to Y=0
            bounds = mesh_geom.bounds
            center_x = (bounds[0][0] + bounds[1][0]) / 2.0
            min_y = bounds[0][1]
            center_z = (bounds[0][2] + bounds[1][2]) / 2.0
            mesh_geom.vertices -= [center_x, min_y, center_z]

            old_major = max(mesh_geom.extents[0], mesh_geom.extents[2])
            old_minor = min(mesh_geom.extents[0], mesh_geom.extents[2])
            old_h = mesh_geom.extents[1]

            # 2. Scale uniformly to real physical dimensions
            scale = calculate_scale(dim_info, mesh_geom.extents)
            mesh_geom.vertices *= scale

            new_major = max(mesh_geom.extents[0], mesh_geom.extents[2])
            new_minor = min(mesh_geom.extents[0], mesh_geom.extents[2])
            new_h = mesh_geom.extents[1]

            # 3. Export GLB
            s.export(glb_path)

            # 4. Export USDZ
            build_usdz(mesh_geom, dish_id_str, usdz_path)

            success += 1
            results.append({
                'id': pid,
                'name': dim_info['name'],
                'scale': scale,
                'new_major_cm': new_major * 100,
                'new_minor_cm': new_minor * 100,
                'new_h_cm': new_h * 100,
            })

            if idx % 10 == 0 or idx == total:
                print(f"[{idx:3d}/{total:3d}] Processed {dish_id_str} ({dim_info['name'][:25]:25s}) -> {new_major*100:.1f}x{new_minor*100:.1f}cm [scale: {scale:.2f}x] ({time.time()-t0:.1f}s)")

        except Exception as e:
            print(f"ERROR on {dish_id_str}: {e}")

    # Also update hummus_preview if present
    preview_glb = os.path.join(MODELS_DIR, "hummus_preview.glb")
    preview_usdz = os.path.join(MODELS_DIR, "hummus_preview.usdz")
    if os.path.exists(preview_glb):
        try:
            s = trimesh.load(preview_glb)
            mesh_geom = list(s.geometry.values())[0]
            bounds = mesh_geom.bounds
            mesh_geom.vertices -= [(bounds[0][0]+bounds[1][0])/2.0, bounds[0][1], (bounds[0][2]+bounds[1][2])/2.0]
            scale = 0.20 / ((mesh_geom.extents[0] + mesh_geom.extents[2]) / 2.0)
            mesh_geom.vertices *= scale
            s.export(preview_glb)
            build_usdz(mesh_geom, "hummus_preview", preview_usdz)
            print("Successfully updated hummus_preview.glb and hummus_preview.usdz to 20cm.")
        except Exception as e:
            print(f"Error on preview: {e}")

    print("=" * 80)
    print(f"COMPLETED: Rescaled {success}/{total} dishes to authentic real-world dimensions in {time.time()-t0:.1f}s")
    print("=" * 80)

if __name__ == '__main__':
    main()
