import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
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
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vendor chunks - separate core Vue libraries
                    'vendor-vue': ['vue', 'vue-router', 'pinia'],

                    // Chart library chunk - heavy dependency (~500kb)
                    'vendor-charts': ['apexcharts', 'vue3-apexcharts'],

                    // Icons chunk - frequently used across the app
                    'vendor-icons': ['@heroicons/vue/24/outline', '@heroicons/vue/24/solid'],

                    // Axios for API calls
                    'vendor-http': ['axios'],
                },
            },
        },
        // Increase chunk size warning limit to 500kb (default is 500kb)
        // Warn if any chunk exceeds this size for performance monitoring
        chunkSizeWarningLimit: 500,

        // Enable source maps for production debugging (optional, remove if not needed)
        sourcemap: false,

        // Minify with terser for better compression
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true, // Remove console.logs in production
                drop_debugger: true,
            },
        },
    },
});
