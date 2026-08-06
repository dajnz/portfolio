import { fileURLToPath, URL } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vitest/config';

const featureAlias = (path: string) =>
    fileURLToPath(new URL(`./resources/js/Features/${path}`, import.meta.url));

export default defineConfig(({ isSsrBuild }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@portfolio-public': featureAlias('Portfolio/Public'),
            '@portfolio-admin': featureAlias('Portfolio/Admin'),
            '@blog-public': featureAlias('Blog/Public'),
            '@blog-admin': featureAlias('Blog/Admin'),
            '@blog-shared': featureAlias('Blog/Shared'),
            '@projects-public': featureAlias('Projects/Public'),
            '@projects-admin': featureAlias('Projects/Admin'),
            '@experience-public': featureAlias('Experience/Public'),
            '@experience-admin': featureAlias('Experience/Admin'),
            '@experience-shared': featureAlias('Experience/Shared'),
            '@auth-admin': featureAlias('Auth/Admin'),
            '@analytics-admin': featureAlias('Analytics/Admin'),
            '@settings-admin': featureAlias('Settings/Admin'),
            '@backup-admin': featureAlias('Backup/Admin'),
            '@techskills-admin': featureAlias('TechSkills/Admin'),
            '@resume-admin': featureAlias('Resume/Admin'),
            '@shared-public': featureAlias('Shared/Public'),
            '@shared-admin': featureAlias('Shared/Admin'),
            '@shared-cross': featureAlias('Shared/CrossSurface'),
            // Root alias for anything outside the Features tree (lang JSON,
            // app entries). Listed last — the longer feature aliases above
            // must win prefix resolution (e.g. `@shared-cross/...`).
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        // The SSR build writes into `bootstrap/ssr` (laravel-vite-plugin
        // `ssrOutputDirectory`), which contains the committed `.gitignore`;
        // vite's default `emptyOutDir` would wipe it on every build.
        emptyOutDir: !isSsrBuild,
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (
                        id.includes('node_modules/vue') ||
                        id.includes('node_modules/pinia') ||
                        id.includes('node_modules/@inertiajs') ||
                        id.includes('node_modules/ziggy-js')
                    ) {
                        return 'vue-vendor';
                    }
                },
            },
        },
    },
    test: {
        environment: 'happy-dom',
        setupFiles: ['tests/Support/Frontend/setup.ts'],
        include: [
            'tests/unit/frontend/**/*.spec.ts',
            'tests/integration/frontend/**/*.spec.ts',
        ],
        coverage: {
            provider: 'v8',
            thresholds: {
                lines: 80,
            },
        },
    },
}));
