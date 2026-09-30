# Beit Elia Menu

The Beit Elia menu is a Vue 3 and Vite frontend backed by a Laravel application. The cPanel deployment runs the full application. The Vercel configuration in this repository builds a static performance preview of the customer-facing menu.

## Vercel preview

Import this GitHub repository into Vercel. The repository's `vercel.json` selects the `build:vercel` command and `dist` output directory, and configures deep links such as `/category/1` and `/product/1` to load the app shell.

The preview uses the menu snapshot in `vercel-preview/generated/menu/` and all 122 optimized product videos in `vercel-preview/storage/products/optimized/videos/`. Still product photos and AR model files are excluded from the Vercel output. Products without a video use the menu's generated plate artwork.

To build the same static output locally:

```sh
npm install
npm run build:vercel
```

The output is written to `dist/`. The preview's menu is a snapshot and does not connect to the Laravel database or admin panel.

## Laravel application

The Laravel backend, admin panel, and production deployment files remain in the repository. Local Laravel development requires PHP and Composer dependencies plus an application `.env` file. The public Vercel preview serves only the static menu build.
