import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const output = path.join(root, 'dist');

rmSync(output, { recursive: true, force: true });
mkdirSync(output, { recursive: true });

const copy = (source, destination) => {
    cpSync(path.join(root, source), path.join(output, destination), { recursive: true });
};

for (const file of [
    'public/index.html',
    'public/manifest.webmanifest',
    'public/sw.js',
    'public/workbox-e41f7351.js',
    'public/favicon.ico',
    'public/favicon.png',
]) {
    copy(file, path.basename(file));
}

copy('public/build', 'build');
copy('public/icons', 'icons');
copy('vercel-preview/storage/products/optimized/videos', 'storage/products/optimized/videos');
copy('vercel-preview/generated/menu/version.json', 'generated/menu/version.json');

const menuPath = path.join(root, 'vercel-preview/generated/menu/menu.json');
const menu = JSON.parse(readFileSync(menuPath, 'utf8'));

function removeArModels(categories) {
    for (const category of categories) {
        for (const product of category.products ?? []) {
            product.image_url = null;
            product.video_poster_url = null;
            product.ar_enabled = false;
            product.has_ar = false;
            product.model_glb_url = null;
            product.model_usdz_url = null;
        }

        removeArModels(category.children ?? []);
    }
}

removeArModels(menu.categories ?? []);
mkdirSync(path.join(output, 'generated/menu'), { recursive: true });
writeFileSync(path.join(output, 'generated/menu/menu.json'), `${JSON.stringify(menu)}\n`);

console.log('Vercel static output assembled in dist/ (AR model URLs disabled).');
