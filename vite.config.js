import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Admin: Blade pages + small enhancements (React islands are added per feature).
                'resources/scss/admin.scss',
                'resources/js/admin/app.js',
                'resources/js/admin/islands/content-fields.jsx',
                'resources/js/admin/islands/media-picker.jsx',
                'resources/js/admin/islands/page-builder.jsx',
                'resources/js/admin/islands/summary-editor.jsx',
                // Public website: React SPA.
                'resources/scss/public.scss',
                'resources/js/public/main.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses Sass @import and global functions; silence those upstream warnings.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
                quietDeps: true,
            },
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/react') || id.includes('node_modules/scheduler')) {
                        return 'react';
                    }
                },
            },
        },
    },
    server: {
        // Bind to IPv4: the dev-server origin is added to the Content-Security-Policy, and CSP
        // source expressions cannot contain IPv6 literals such as http://[::1]:5173.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{js,jsx}'],
        setupFiles: ['resources/js/test/setup.js'],
        globals: true,
    },
});
