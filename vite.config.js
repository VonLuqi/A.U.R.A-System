import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

/**
 * Dev: prefer same-origin via `php artisan serve` + `npm run dev` (Vite HMR).
 * If the browser hits Vite directly (5173), `/api` is proxied to Laravel so
 * session + XSRF cookies share the effective host (Etapa C §5.8).
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/app/private/**'],
        },
        proxy: {
            '/api': {
                target: process.env.VITE_DEV_PROXY_TARGET || 'http://127.0.0.1:8000',
                changeOrigin: true,
                secure: false,
            },
        },
    },
});
