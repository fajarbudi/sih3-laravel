import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/peta.js'], // peta.js: Leaflet, hanya dimuat di beranda portal
            refresh: true,
            fonts: [
                // Inter diunduh saat build dan disajikan dari server sendiri (bukan Google Fonts),
                // dimuat lewat direktif @fonts di <head>.
                bunny('Inter', {
                    weights: [400, 500, 600],
                }),
                // Portal publik saja (dimuat lewat <x-layouts.tailwind :fonts>): geometris dan ramah, karya Tokotype Jakarta.
                bunny('Plus Jakarta Sans', {
                    alias: 'jakarta',
                    variable: '--font-jakarta',
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
