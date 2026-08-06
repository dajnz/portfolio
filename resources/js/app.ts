import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, type DefineComponent, h } from 'vue';

import AppRoot from './app.vue';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Features/${name}.vue`,
            import.meta.glob<DefineComponent>('./Features/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(
                AppRoot,
                { page: props.initialPage },
                { default: () => h(App, props) }
            ),
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
