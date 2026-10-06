import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/buyer/product-show.js',
                'resources/css/admin/dashboard.css',
                'resources/css/admin/auth/login.css',
                'resources/css/admin/auth/otp.css',
                'resources/css/buyer/home.css',
                'resources/css/buyer/product-show.css',
                'resources/css/category/show.css',
                'resources/css/emails/registration-otp.css',
                'resources/css/layouts/admin.css'
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        ViteImageOptimizer({
            test: /\.(jpe?g|png|gif|tiff|webp|svg|avif)$/i,
            exclude: undefined,
            include: undefined,
            includePublic: true,
            logStats: true,
            ansiColors: true,
            svg: {
                multipass: true,
            },
            png: {
                quality: 80,
            },
            jpeg: {
                quality: 80,
            },
            webp: {
                lossy: true,
                quality: 80,
            },
            avif: {
                lossy: true,
                quality: 75,
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});