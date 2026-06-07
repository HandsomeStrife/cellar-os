import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', { weights: [400, 500, 600, 700] }),
                bunny('Playfair Display', { weights: [400, 500, 600, 700] }),
                bunny('JetBrains Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        cors: true,
        hmr: {
            host: 'cellar-os.cerberus.local',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
