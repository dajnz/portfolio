import type { DefineComponent } from 'vue';

import { createPortfolioInertiaApp } from '@/createInertiaApp';

// Admin entry — loaded only by the admin Blade shell. Keeping the admin pages
// out of the public module graph means anonymous visitors cannot enumerate
// the admin chunks from the public JavaScript.
createPortfolioInertiaApp({
    ...import.meta.glob<DefineComponent>('./Features/*/Admin/Pages/**/*.vue'),
    ...import.meta.glob<DefineComponent>('./Features/Shared/CrossSurface/Pages/**/*.vue'),
});
