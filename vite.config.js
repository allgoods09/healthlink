import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: [
                'app/View/Components/**',
                'resources/views/**',
                'routes/**',
            ],
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/.git/**',
                '**/bootstrap/cache/**',
                '**/mobile/**',
                '**/node_modules/**',
                '**/public/build/**',
                '**/storage/**',
                '**/vendor/**',
            ],
        },
    },
});
