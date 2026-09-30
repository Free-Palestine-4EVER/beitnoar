# AR model sizing

`data/ar-dimensions/dish_dimensions.csv` and `vessel_list.csv` are the measured source for the model sizes. `scripts/rescale_glb_to_catalog.py` keeps GLB geometry and textures intact and updates the scene root scale. It makes a dry run by default; `--apply` requires a backup directory outside the model folder.

Product 59 shares `dish_010.glb` with product 10 in the original data, even though the catalog lists different serving vessels: product 10 is a 16 cm handled pan and product 59 is a 20 cm shallow bowl. Seed `dish_059.glb` from the existing 20 cm bowl model `dish_009.glb` before applying the sizing script. The migration `2026_09_29_203000_split_product_59_ar_model.php` then gives product 59 its own GLB path.

The product viewer intentionally uses the corrected GLB to generate Quick Look USDZ on iOS. That avoids an older stored USDZ bypassing the corrected GLB scale. Product 49 is unmeasured in the source catalog and must remain unchanged until a physical measurement is supplied.

If a later model cleanup resets scene-root transforms, rerun the sizing script with `--recalibrate-marked`. That option remeasures the current GLB bounds against the catalog instead of trusting the previous marker. Always use `--apply` with a new backup directory outside the public model folder.
