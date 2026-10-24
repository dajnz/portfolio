import '../css/app.css';

import type { DefineComponent } from 'vue';

import { createPortfolioInertiaApp } from '@/createInertiaApp';

// Public entry — only public pages (and the shared error page) are part of
// this module graph. Admin pages are compiled into a separate entry
// (`admin.ts`) so anonymous visitors never download, or discover the names
// of, the admin chunks.
createPortfolioInertiaApp({
    ...import.meta.glob<DefineComponent>('./Features/*/Public/Pages/**/*.vue'),
    ...import.meta.glob<DefineComponent>('./Features/Shared/CrossSurface/Pages/**/*.vue'),
});
