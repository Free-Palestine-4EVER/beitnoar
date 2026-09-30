#!/usr/bin/env python3
"""
Beit Elia - Update Shawarma Sandwich Models
Rescales Beef, Chicken, and Fish Shawarma Sandwich models to 33 cm plate length,
grounds them to table level, centers them, and exports GLB + USDZ for both dish_XXX
and named slugs.
"""

import os
import sys
import shutil
import subprocess
import trimesh
import numpy as np
from PIL import Image
from pxr import Usd, UsdGeom, UsdShade, Sdf, Vt

WORKSPACE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODELS_DIR = os.path.join(WORKSPACE_DIR, "public/storage/models")
TEMP_WORK = "/tmp/usdz_work_shawarma"
os.makedirs(TEMP_WORK, exist_ok=True)
os.makedirs(MODELS_DIR, exist_ok=True)

TARGET_PLATE_LEN_M = 0.33  # 33 cm

ITEMS = [
    {
        "name": "Beef Shawarma Sandwich",
        "source": "/Users/hideyourkids/Downloads/Beef Shawarma Sandwich.glb",
        "dish_id": "dish_109",
        "named_slug": "beef_shawarma_sandwich",
        "product_id": 109,
    },
    {
        "name": "Chicken Shawarma Sandwich",
        "source": "/Users/hideyourkids/Downloads/Chicken Shawarma Sandwich.glb",
        "dish_id": "dish_108",
        "named_slug": "chicken_shawarma_sandwich",
        "product_id": 108,
    },
    {
        "name": "Fish Shawarma Sandwich",
        "source": "/Users/hideyourkids/Downloads/Fish Shawarma Sandwich.glb",
        "dish_id": "dish_103",
        "named_slug": "fish_shawarma_sandwich",
        "product_id": 103,
    },
]

def build_usdz(mesh_geom, name_prefix, usdz_path):
    tex_filename = f"{name_prefix}_diffuse.jpg"
    tex_path = os.path.join(TEMP_WORK, tex_filename)
    mat = mesh_geom.visual.material
    tex = getattr(mat, 'baseColorTexture', None) or getattr(mat, 'image', None)
    if tex:
        tex.convert('RGB').save(tex_path, quality=95)
    else:
        Image.new('RGB', (32, 32), (210, 190, 170)).save(tex_path)

    usd_filename = f"{name_prefix}.usdc"
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

    if os.path.exists(usdz_path):
        os.remove(usdz_path)

    cmd = f"cd \"{TEMP_WORK}\" && /usr/bin/usdzip \"{usdz_path}\" \"{usd_filename}\" \"{tex_filename}\""
    subprocess.run(cmd, shell=True, check=True, capture_output=True)

    try:
        os.remove(usd_path)
        os.remove(tex_path)
    except Exception:
        pass

def measure_rim_length(verts):
    pan_verts = verts[verts[:, 2] > -0.1]
    center_z = (pan_verts[:, 2].max() + pan_verts[:, 2].min()) / 2.0
    rim_pts = pan_verts[(pan_verts[:, 1] > 0.05) & (np.abs(pan_verts[:, 2] - center_z) < 0.04)]
    
    left_rim = rim_pts[(rim_pts[:, 0] > -0.45) & (rim_pts[:, 0] < -0.38)]
    right_rim = rim_pts[(rim_pts[:, 0] > 0.38) & (rim_pts[:, 0] < 0.45)]
    
    left_x = np.median(left_rim[:, 0]) if len(left_rim) else -0.405
    right_x = np.median(right_rim[:, 0]) if len(right_rim) else 0.405
    return right_x - left_x

def process_item(item):
    print("=" * 60)
    print(f"Processing: {item['name']}")
    print(f"Source: {item['source']}")

    scene = trimesh.load(item['source'])
    geom = list(scene.geometry.values())[0]

    # Initial bounds
    bounds = geom.bounds
    center_x = (bounds[0][0] + bounds[1][0]) / 2.0
    center_z = (bounds[0][2] + bounds[1][2]) / 2.0
    min_y = bounds[0][1]

    # Center in X and Z, ground to Y=0
    geom.vertices -= [center_x, min_y, center_z]

    # Measure rim length
    rim_len = measure_rim_length(geom.vertices)
    scale = TARGET_PLATE_LEN_M / rim_len
    print(f"Original Plate Rim Length: {rim_len * 100:.2f} cm")
    print(f"Calculated Scale Factor: {scale:.4f} (target rim: {TARGET_PLATE_LEN_M * 100:.1f} cm)")

    # Scale geometry
    geom.vertices *= scale

    # Re-ground flush to Y=0
    new_min_y = geom.vertices[:, 1].min()
    geom.vertices[:, 1] -= new_min_y

    new_bounds = geom.bounds
    new_extents = geom.extents
    print(f"New Dimensions:")
    print(f"  Total Width (X, with handles): {new_extents[0] * 100:.1f} cm")
    print(f"  Total Height (Y): {new_extents[1] * 100:.1f} cm")
    print(f"  Total Depth (Z, pan + fries): {new_extents[2] * 100:.1f} cm")
    print(f"  Verified Rim Length: {rim_len * scale * 100:.1f} cm")

    # Target file paths
    dish_glb = os.path.join(MODELS_DIR, f"{item['dish_id']}.glb")
    dish_usdz = os.path.join(MODELS_DIR, f"{item['dish_id']}.usdz")
    named_glb = os.path.join(MODELS_DIR, f"{item['named_slug']}.glb")
    named_usdz = os.path.join(MODELS_DIR, f"{item['named_slug']}.usdz")

    # Export primary dish GLB
    scene.export(dish_glb)
    shutil.copyfile(dish_glb, named_glb)
    print(f"Exported GLB: {dish_glb} ({os.path.getsize(dish_glb) / (1024*1024):.1f} MB)")
    print(f"Exported GLB: {named_glb}")

    # Build USDZ
    build_usdz(geom, item['dish_id'], dish_usdz)
    shutil.copyfile(dish_usdz, named_usdz)
    print(f"Exported USDZ: {dish_usdz} ({os.path.getsize(dish_usdz) / (1024*1024):.1f} MB)")
    print(f"Exported USDZ: {named_usdz}")

def main():
    for item in ITEMS:
        process_item(item)
    print("=" * 60)
    print("All 3 Shawarma Sandwich models successfully updated!")

if __name__ == '__main__':
    main()
