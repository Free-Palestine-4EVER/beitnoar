import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import fs from 'node:fs';
import path from 'node:path';

function copyManifestPlugin() {
    return {
        name: 'copy-pwa-manifest',
        closeBundle() {
            const buildManifest = path.resolve('public/build/manifest.webmanifest');
            const rootManifest = path.resolve('public/manifest.webmanifest');
            if (fs.existsSync(buildManifest)) {
                fs.copyFileSync(buildManifest, rootManifest);
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
                compilerOptions: {
                    isCustomElement: (tag) => tag === 'model-viewer',
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            outDir: 'public',
            buildBase: '/',
            scope: '/',
            filename: 'sw.js',
            // Disable in dev to avoid confusion; test with `npm run build`
            devOptions: {
                enabled: false,
            },
            // Manifest configuration
            manifest: {
                name: 'Beit Elia Menu',
                short_name: 'Beit Elia',
                description: 'Restaurant menu for Beit Elia',
                theme_color: '#2E2D2B',
                background_color: '#F1EEE9',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                dir: 'auto',
                lang: 'en',
                icons: [
                    {
                        src: '/icons/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/maskable-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            // Workbox configuration
            workbox: {
                // Only precache Vite-built assets (JS, CSS)
                // Do NOT precache videos, large 3D models, or media
                globPatterns: [
                    'build/assets/app-*.{js,css}',
                ],
        // Don't precache model-viewer or its decoders. iOS loads them only after an AR tap;
        // Android opens its native Scene Viewer directly from the tap.
                // Navigation fallback for SPA deep links
                navigateFallback: null, // We handle this manually below
                // Runtime caching strategies
                runtimeCaching: [
                    // ─── SPA Navigation Fallback ───
                    // Serve the app shell for all navigation requests
                    // EXCEPT /admin, /livewire, /filament paths
                    {
                        urlPattern: ({ request, url }) => {
                            if (request.mode !== 'navigate') return false;
                            // Exclude admin/backend paths
                            const excluded = ['/admin', '/livewire', '/filament', '/login', '/api'];
                            return !excluded.some(path => url.pathname.startsWith(path));
                        },
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'beit-elia-pages-v1',
                            networkTimeoutSeconds: 3,
                            plugins: [
                                {
                                    cacheKeyWillBeUsed: async () => '/',
                                },
                            ],
                        },
                    },
                    // ─── A. VERSION FILE: Network First (critical for freshness) ───
                    {
                        urlPattern: /\/generated\/menu\/version\.json/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'beit-elia-version-v1',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    // ─── B. MENU JSON: Network First with cache fallback ───
                    {
                        urlPattern: /\/generated\/menu\/.+\.json/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'beit-elia-menu-v1',
                            networkTimeoutSeconds: 5,
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 300,
                                maxAgeSeconds: 7 * 24 * 60 * 60, // 7 days
                            },
                        },
                    },
                    // ─── C. PRODUCT/CATEGORY IMAGES: Cache First (bounded) ───
                    {
                        urlPattern: /\/storage\/(products|categories)\/.+\.(jpg|jpeg|png|webp|gif|svg)(\?.*)?$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'beit-elia-images-v3',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 300,
                                maxAgeSeconds: 365 * 24 * 60 * 60, // Media uses unique upload names.
                            },
                        },
                    },
                    // ─── D. GOOGLE FONTS: Cache First (rarely change) ───
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'beit-elia-google-fonts-css-v1',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'beit-elia-google-fonts-v1',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 365 * 24 * 60 * 60, // 1 year
                            },
                        },
                    },
                    // ─── E. PWA ICONS: Cache First ───
                    {
                        urlPattern: /\/icons\/.+\.png/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'beit-elia-icons-v1',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
                            },
                        },
                    },
                    // ─── F. 3D MODELS (GLB/USDZ): Stale While Revalidate (bounded) ───
                    {
                        urlPattern: /\/storage\/(?:products\/)?models\/.+\.(glb|usdz)(\?.*)?$/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'beit-elia-3d-models-v4',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                            expiration: {
                                maxEntries: 12,
                                maxAgeSeconds: 30 * 24 * 60 * 60, // Keep the device cache bounded for large models.
                            },
                        },
                    },
                    // ─── G. VIDEOS: Do NOT cache aggressively ───
                    // No runtime caching rule for .mp4 files.
                    // They rely on browser HTTP cache and lazy loading.
                ],
                // Do NOT precache the navigation fallback page; we cache it at runtime
                // Clean up old caches from previous SW versions
                cleanupOutdatedCaches: true,
                // Skip waiting so new SW activates immediately
                skipWaiting: true,
                clientsClaim: true,
            },
        }),
        copyManifestPlugin(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
