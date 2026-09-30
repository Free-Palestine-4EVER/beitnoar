#!/usr/bin/env node

/**
 * Generate index.html for Firebase static hosting from Vite build manifest.
 *
 * Reads public/build/manifest.json and creates public/index.html with:
 * - Correct asset references (JS, CSS)
 * - PWA manifest link
 * - Apple touch icon
 * - Theme color meta tag
 * - Google Fonts preconnect
 *
 * Usage: node scripts/generate-index.mjs
 */

import { readFileSync, writeFileSync, existsSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');

const manifestPath = resolve(projectRoot, 'public/build/manifest.json');
if (!existsSync(manifestPath)) {
    console.error('Error: public/build/manifest.json not found. Run `npm run build` first.');
    process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'));

// Resolve entry points
const appJs = manifest['resources/js/app.js'];
const appCss = manifest['resources/css/app.css'];

if (!appJs || !appCss) {
    console.error('Error: Could not find app entry points in Vite manifest.');
    process.exit(1);
}

// Collect CSS files (from both CSS entry and JS entry's css array)
const cssFiles = new Set();
if (appCss.file) {
    cssFiles.add(`/build/${appCss.file}`);
}
if (appJs.css) {
    for (const css of appJs.css) {
        cssFiles.add(`/build/${css}`);
    }
}

const jsFile = `/build/${appJs.file}`;

// Generate CSS link tags
const cssLinks = Array.from(cssFiles)
    .map(href => `    <link rel="stylesheet" href="${href}">`)
    .join('\n');

// Generate HTML
const html = `<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Beit Elia — Menu</title>
    <meta name="theme-color" content="#2E2D2B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon-180x180.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300&family=Jost:wght@300;400;500&family=Tajawal:wght@300;400;500&display=swap" rel="stylesheet">
${cssLinks}
    <script type="module" crossorigin src="${jsFile}"></script>
</head>
<body>
    <div id="app"></div>
</body>
</html>
`;

const outputPath = resolve(projectRoot, 'public/index.html');
writeFileSync(outputPath, html, 'utf-8');

console.log(`✓ Generated ${outputPath}`);
console.log(`  CSS: ${Array.from(cssFiles).join(', ')}`);
console.log(`  JS: ${jsFile}`);
