import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const require = createRequire(import.meta.url);

/**
 * MapLibre resolves its web worker at runtime as a sibling of its own module
 * URL, which the bundler cannot see. Without the worker the map still draws
 * raster tiles but every GeoJSON source stays empty: no markers, no clusters.
 * Emitting the worker (and the shared chunk it imports) next to the build
 * output restores that sibling lookup.
 */
function maplibreWorkerAssets() {
    const distDir = resolve(dirname(require.resolve('maplibre-gl/package.json')), 'dist');
    const files = ['maplibre-gl-worker.mjs', 'maplibre-gl-shared.mjs'];

    return {
        name: 'kebab-society-maplibre-worker',
        apply: 'build',
        generateBundle() {
            files.forEach((file) => {
                this.emitFile({
                    type: 'asset',
                    fileName: `assets/${file}`,
                    source: readFileSync(resolve(distDir, file), 'utf8'),
                });
            });
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        maplibreWorkerAssets(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    // MapLibre spawns its own web worker. Vite's dependency optimiser rewrites
    // the bundle but drops the worker entry, which silently breaks every
    // vector/GeoJSON layer in dev. Excluding it keeps the worker intact.
    optimizeDeps: {
        exclude: ['maplibre-gl'],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
