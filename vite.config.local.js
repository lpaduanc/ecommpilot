/**
 * Configuração Vite otimizada para desenvolvimento LOCAL (sem Docker)
 *
 * Para usar: npm run dev -- --config vite.config.local.js
 * Ou renomeie para vite.config.js se não for usar Docker
 */
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
        // Configuração otimizada para Windows/Linux/Mac nativos (sem Docker)
        host: 'localhost',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // File system events nativos (mais eficiente que polling)
            usePolling: false,
            ignored: ['**/vendor/**', '**/node_modules/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-router', 'pinia'],
                    'vendor-charts': ['apexcharts', 'vue3-apexcharts'],
                    'vendor-icons': ['@heroicons/vue/24/outline', '@heroicons/vue/24/solid'],
                    'vendor-http': ['axios'],
                },
            },
        },
        chunkSizeWarningLimit: 500,
        sourcemap: false,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
    },
});
