import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, type DefineComponent, h } from 'vue';

import AppRoot from '@/app.vue';
import '@/iconRegistry';

type Pages = Record<string, () => Promise<DefineComponent>>;

// Inertia page names resolve to `<name>.vue` or — for directory-based
// complex pages — to `<dir>/<name>.vue`. `resolvePageComponent` tries
// each candidate in order.
export function createPortfolioInertiaApp(pages: Pages): void {
    function resolvePage(name: string) {
        const lastName = name.split('/').pop() ?? name;

        return resolvePageComponent(
            [`./Features/${name}.vue`, `./Features/${name}/${lastName}.vue`],
            pages,
        );
    }

    createInertiaApp({
        resolve: (name) => resolvePage(name),
        setup({ el, App, props, plugin }) {
            createApp({
                render: () =>
                    h(AppRoot, { page: props.initialPage }, { default: () => h(App, props) }),
            })
                .use(plugin)
                .use(createPinia())
                .mount(el);
        },
        // Page titles are fully localized server-side (`__('seo.title_*')`);
        // an empty title keeps the Blade `<title inertia>` default
        // (config('app.name')).
        title: (title) => (title.trim() ? title : ''),
    });
}
