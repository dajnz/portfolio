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
                bunny('Inter', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            // Convention (user-approved 2026-08-06, step 3.4 rework):
            // components live in `{Feature}/{Area}/Components/` — pages
            // land in `{Feature}/{Area}/Pages/` (Inertia glob resolution),
            // aux modules (editor extensions, etc.) in sibling dirs.
            '@portfolio-public': featureAlias('Portfolio/Public/Components'),
            '@portfolio-admin': featureAlias('Portfolio/Admin/Components'),
            '@blog-public': featureAlias('Blog/Public/Components'),
            '@blog-admin': featureAlias('Blog/Admin/Components'),
            '@blog-shared': featureAlias('Blog/Shared'),
            '@projects-public': featureAlias('Projects/Public/Components'),
            '@projects-admin': featureAlias('Projects/Admin/Components'),
            '@experience-public': featureAlias('Experience/Public/Components'),
            '@experience-admin': featureAlias('Experience/Admin/Components'),
            '@experience-shared': featureAlias('Experience/Shared'),
            '@auth-admin': featureAlias('Auth/Admin/Components'),
            '@analytics-admin': featureAlias('Analytics/Admin/Components'),
            '@settings-admin': featureAlias('Settings/Admin/Components'),
            '@backup-admin': featureAlias('Backup/Admin/Components'),
            '@techskills-admin': featureAlias('TechSkills/Admin/Components'),
            '@resume-admin': featureAlias('Resume/Admin/Components'),
            '@shared-public': featureAlias('Shared/Public/Components'),
            '@shared-admin': featureAlias('Shared/Admin/Components'),
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
