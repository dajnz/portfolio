import type { IconifyJSON } from '@iconify/types';
import { addCollection } from '@iconify/vue';
import { type Ref, ref } from 'vue';

// Offline registration of the blog public pages' icon sets (B78: no
// runtime calls to the Iconify API). The TechSkillTag chips render
// devicon glyphs; heroicons are already registered at the app entries
// (resources/js/iconRegistry.ts). The ~1 MB raw devicon payload ships
// to the app only through this lazy dynamic import — a static import
// would blow the page's JS budget (architecture.md Performance
// Budget). The `iconsReady` ref lets consumers render their `<Icon>`
// elements only after registration — a non-reactive storage would
// leave them empty until an unrelated re-render.

let registered = false;
const iconsReady = ref(false);

export function useBlogIconsReady(): Ref<boolean> {
    return iconsReady;
}

export function ensureBlogIcons(): void {
    if (registered || typeof window === 'undefined') {
        return;
    }

    registered = true;

    void import('@iconify-json/devicon/icons.json')
        .then((devicon) => {
            addCollection((devicon as { default: IconifyJSON }).default);
            iconsReady.value = true;
        })
        .catch(() => {
            // Iconify falls back to its runtime fetch on an unregistered
            // name; the icons stay empty until a later registration.
            registered = false;
        });
}
