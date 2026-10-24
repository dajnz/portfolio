import { fileURLToPath, URL } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { i18nKeyGate } from './devops/vite-plugins/i18n-key-gate.ts';
import { pageJsBudgetWarnings } from './devops/vite-plugins/page-js-budget-warnings.ts';
import { stripWoffFallbackFaces } from './devops/vite-plugins/strip-woff-fallback-faces.ts';
import { defineConfig } from 'vitest/config';

const featureAlias = (path: string) =>
    fileURLToPath(new URL(`./resources/js/Features/${path}`, import.meta.url));

export default defineConfig(({ isSsrBuild }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/js/admin.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
            fonts: [
                // Inter + JetBrains Mono are the only families in the
                // committed design tokens (docs/mockups/public/tokens.css):
                // Inter for body/headings on every surface, JetBrains Mono
                // for code. Instrument Sans was bundled but never referenced
                // by any token — every face was preloaded on every page for
                // nothing (step 13.3 performance record). Inter 800 dropped:
                // its 4 font-extrabold/black usages render 700 instead.
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
        // Build-time i18n gate (i18n-and-theming.md:145-147, ADR-0009): fails the
        // build when a literal t() key has no entry in either committed
        // catalog and when EN/RU drift. Runs for both client and SSR builds.
        i18nKeyGate(),
        // Build-time audits (devops/vite-plugins): per-page gzip budget
        // warnings and the woff-fallback stripping. Client builds only —
        // the SSR build writes a single server bundle under bootstrap/ssr
        // (no page chunks, no fonts CSS); each plugin re-checks `ssr` in
        // its hook as well.
        ...(isSsrBuild ? [] : [pageJsBudgetWarnings(), stripWoffFallbackFaces()]),
    ],
    server: {
        // The dev container resolves `localhost` to `::1` only — vite would
        // otherwise advertise `http://[::1]:5173` in public/hot, which the
        // host browser cannot reach. Bind all interfaces and advertise the
        // host-reachable localhost URL (devops.md:1226 — `pnpm run dev`).
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
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@blog': featureAlias('Blog'),
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
            // Feature-root alias for page imports in specs (mirrors
            // `@auth`; the Components-scoped alias cannot reach Pages).
            '@analytics': featureAlias('Analytics'),
            '@settings-admin': featureAlias('Settings/Admin/Components'),
            '@settings': featureAlias('Settings'),
            '@backup-admin': featureAlias('Backup/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@auth`; the Components-scoped alias cannot reach Pages).
            '@backup': featureAlias('Backup'),
            '@techskills-admin': featureAlias('TechSkills/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@techskills': featureAlias('TechSkills'),
            '@resume-admin': featureAlias('Resume/Admin/Components'),
            // Feature-root alias for page imports in specs (mirrors
            // `@portfolio`; the Components-scoped alias cannot reach Pages).
            '@resume': featureAlias('Resume'),
            '@shared-public': featureAlias('Shared/Public/Components'),
            '@shared-admin': featureAlias('Shared/Admin/Components'),
            '@shared-admin-extensions': featureAlias('Shared/Admin/EditorExtensions'),
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
            // The icon subsets under resources/js/iconSubsets are
            // generated from the strict icon manifests by
            // `pnpm run icons:build` (scripts/build-icon-subsets.mjs) and
            // are small enough (tens of KB) for vitest to load as-is —
            // no fixture aliasing needed.
        },
        coverage: {
            provider: 'v8',
            thresholds: {
                lines: 80,
            },
        },
    },
}));
