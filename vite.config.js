import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const localAppOrigins = new Set(['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000']);

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        origin: 'http://127.0.0.1:5173',
        cors: {
            origin: (origin, callback) => {
                callback(null, origin !== undefined && localAppOrigins.has(origin) ? origin : false);
            },
        },
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
