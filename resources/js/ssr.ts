import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createSSRApp, type DefineComponent, h } from 'vue';

import AppRoot from '@/app.vue';
import '@/iconRegistry';

// Inertia SSR entry — built by `vite build --ssr` into
// `bootstrap/ssr/ssr.js`. The server listens on port 3000 unless
// INERTIA_SSR_PORT overrides it. Page resolution mirrors app.ts:
// `<name>.vue` or `<dir>/<name>.vue` for directory-based complex pages.
function resolvePage(name: string) {
    const lastName = name.split('/').pop() ?? name;

    return resolvePageComponent(
        [`./Features/${name}.vue`, `./Features/${name}/${lastName}.vue`],
        import.meta.glob<DefineComponent>('./Features/**/*.vue'),
    );
}

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: (app) => renderToString(app),
            resolve: (name) => resolvePage(name),
            setup({ App, props, plugin }) {
                return createSSRApp({
                    render: () =>
                        h(AppRoot, { page: props.initialPage }, { default: () => h(App, props) }),
                })
                    .use(plugin)
                    .use(createPinia());
            },
            // Mirror the client title passthrough (app.ts).
            title: (title) => (title.trim() ? title : ''),
        }),
    { port: Number(process.env.INERTIA_SSR_PORT ?? 3000) },
);
