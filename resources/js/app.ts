import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, type DefineComponent, h } from 'vue';

import AppRoot from '@/app.vue';
import '@/iconRegistry';

// Inertia page names resolve to `<name>.vue` or — for directory-based
// complex pages (5.2 rework — SkillsListPage) — to `<dir>/<name>.vue`.
// `resolvePageComponent` tries each candidate in order.
function resolvePage(name: string) {
    const lastName = name.split('/').pop() ?? name;

    return resolvePageComponent(
        [`./Features/${name}.vue`, `./Features/${name}/${lastName}.vue`],
        import.meta.glob<DefineComponent>('./Features/**/*.vue'),
    );
}

createInertiaApp({
    resolve: (name) => resolvePage(name),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(AppRoot, { page: props.initialPage }, { default: () => h(App, props) }),
        })
            .use(plugin)
            .use(createPinia())
            .mount(el);
    },
    // Page titles are fully localized server-side (`__('seo.title_*')` per
    // seo.md:33-45); an empty title keeps the Blade `<title inertia>`
    // default (config('app.name')).
    title: (title) => (title.trim() ? title : ''),
});
