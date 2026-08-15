import type { IconifyJSON } from '@iconify/types';
import { addCollection } from '@iconify/vue';
import { type Ref, ref } from 'vue';

// Offline registration of the public landing's icon sets (B78: no runtime
// calls to the Iconify API). devicon (skill icons) and simple-icons
// (contact brand icons) ship to the app only through this lazy dynamic
// import — statically importing the full JSONs (~1 MB + ~5.4 MB raw)
// would blow the landing page's JS budget (architecture.md Performance
// Budget). The `iconsReady` ref lets consumers render their `<Icon>`
// elements only after registration — a non-reactive storage would leave
// them empty until an unrelated re-render.

let registered = false;
const iconsReady = ref(false);

export function usePublicIconsReady(): Ref<boolean> {
    return iconsReady;
}

export function ensurePublicIcons(): void {
    if (registered || typeof window === 'undefined') {
        return;
    }

    registered = true;

    void Promise.all([
        import('@iconify-json/devicon/icons.json'),
        import('@iconify-json/simple-icons/icons.json'),
    ])
        .then(([devicon, simpleIcons]) => {
            addCollection((devicon as { default: IconifyJSON }).default);
            addCollection((simpleIcons as { default: IconifyJSON }).default);
            iconsReady.value = true;
        })
        .catch(() => {
            // Iconify falls back to its runtime fetch on an unregistered
            // name; the icons stay empty until a later registration.
            registered = false;
        });
}
