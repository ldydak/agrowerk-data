import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                logger: {
                    warn: () => {} // Wycisza wszystkie ostrzeżenia
                }
            }
        }
    },
    resolve: {
        alias: {
            '~bootstrap': '/node_modules/bootstrap',
            '~jsvectormap': '/node_modules/jsvectormap',
            '~simplebar': '/node_modules/simplebar',
            '~flatpickr': '/node_modules/flatpickr'
        }
    },
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});