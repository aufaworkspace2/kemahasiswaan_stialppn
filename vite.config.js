import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                local('Inter', { variants: [{ src: 'resources/fonts/inter-latin-variable.woff2', weight: '100 900' }], preload: true, optimizedFallbacks: false }),
                local('Manrope', { variants: [{ src: 'resources/fonts/manrope-latin-variable.woff2', weight: '200 800' }], preload: true, optimizedFallbacks: false }),
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
