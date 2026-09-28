import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css',
                 'resources/js/app.js',
                 'resources/js/buyer/product-show.js',
                 'resources/css/admin/dashboard.css',
                 'resources/css/admin/auth/login.css',
                 'resources/css/admin/auth/otp.css',
                 'resources/css/buyer/home.css',
                 'resources/css/buyer/product-show.css',
                 'resources/css/category/show.css',
                 'resources/css/emails/registration-otp.css',
                 'resources/css/layouts/admin.css'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
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
