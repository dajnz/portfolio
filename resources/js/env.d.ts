/// <reference types="vite/client" />

// Plain `tsc` cannot parse SFCs — vue-tsc resolves the real component
// types instead of this declaration (tsconfig `include` lists .vue files
// for vue-tsc; vanilla tsc skips them and only needs the module to
// resolve, e.g. `@/app.vue`).
declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;

    export default component;
}
