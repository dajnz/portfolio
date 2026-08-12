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
    server: {
        // The dev container resolves `localhost` to `::1` only — vite would
        // otherwise advertise `http://[::1]:5173` in public/hot, which the
        // host browser cannot reach. Bind all interfaces and advertise the
        // host-reachable localhost URL (devops.md:1226 — `npm run dev`).
        host: true,
        hmr: {
            host: 'localhost',
        },
    },
    resolve: {
        alias: {
            // Convention (user-approved 2026-08-06, step 3.4 rework):
            // components live in `{Feature}/{Area}/Components/` — pages
            // land in `{Feature}/{Area}/Pages/` (Inertia glob resolution),
            // aux modules (editor extensions, etc.) in sibling dirs.
            '@portfolio-public': featureAlias('Portfolio/Public/Components'),
            '@portfolio-admin': featureAlias('Portfolio/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@auth`; the Components-scoped alias cannot reach Pages).
            '@portfolio': featureAlias('Portfolio'),
            '@blog-public': featureAlias('Blog/Public/Components'),
            '@blog-admin': featureAlias('Blog/Admin/Components'),
            '@blog-shared': featureAlias('Blog/Shared'),
            '@projects-public': featureAlias('Projects/Public/Components'),
            '@projects-admin': featureAlias('Projects/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@projects': featureAlias('Projects'),
            '@experience-public': featureAlias('Experience/Public/Components'),
            '@experience-admin': featureAlias('Experience/Admin/Components'),
            '@experience-shared': featureAlias('Experience/Shared'),
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@experience': featureAlias('Experience'),
            '@auth-admin': featureAlias('Auth/Admin/Components'),
            '@auth': featureAlias('Auth'),
            '@analytics-admin': featureAlias('Analytics/Admin/Components'),
            '@settings-admin': featureAlias('Settings/Admin/Components'),
            '@settings': featureAlias('Settings'),
            '@backup-admin': featureAlias('Backup/Admin/Components'),
            '@techskills-admin': featureAlias('TechSkills/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@techskills': featureAlias('TechSkills'),
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
        alias: {
            // The vendored icon-set JSONs are large (heroicons ~1500
            // icons / 1.6 MB, simple-icons ~3700 icons / 5.4 MB,
            // devicon ~1000 icons). setup.ts statically registers
            // heroicons + devicon for every test file, so the real
            // modules would be transformed once per file; simple-icons
            // is loaded on demand by AdminIconPicker and costs ~700 ms
            // per file even when mocked (the runner parses the real JSON
            // before substituting). All are aliased to tiny fixtures
            // covering every icon name used in the frontend + tests —
            // the picker specs verify behavior, not the vendored data.
            // Test-only aliases: app builds keep the real sets.
            '@iconify-json/heroicons/icons.json': fileURLToPath(
                new URL('./tests/Support/Frontend/fixtures/heroicons.json', import.meta.url),
            ),
            '@iconify-json/devicon/icons.json': fileURLToPath(
                new URL('./tests/Support/Frontend/fixtures/devicon.json', import.meta.url),
            ),
            '@iconify-json/simple-icons/icons.json': fileURLToPath(
                new URL('./tests/Support/Frontend/fixtures/simple-icons.json', import.meta.url),
            ),
        },
        coverage: {
            provider: 'v8',
            thresholds: {
                lines: 80,
            },
        },
    },
}));
