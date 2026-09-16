import type { Page } from '@inertiajs/core';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import AppRoot from '@/app.vue';
import { useThemeStore } from '@shared-cross/Stores/themeStore';

function makePage(theme: unknown): Page {
    return {
        component: 'Portfolio/Public/Pages/LandingPage',
        url: '/',
        version: '1',
        props: { locale: 'en', theme, auth: null },
    } as unknown as Page;
}

function mountRoot(theme: unknown): void {
    mount(AppRoot, {
        props: { page: makePage(theme) },
        slots: { default: '<div data-testid="page" />' },
    });
}

describe('app root theme hydration', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.documentElement.classList.remove('dark');
    });

    afterEach(() => {
        document.documentElement.classList.remove('dark');
    });

    it('uses the boot-script document theme when the server prop is stale (first visit)', () => {
        // Fresh browser, dark system preference: the blocking script in
        // app.blade.php adds the `dark` class before hydration while the
        // server prop (no `theme` cookie yet) is still "light".
        document.documentElement.classList.add('dark');

        mountRoot('light');

        expect(useThemeStore().theme).toBe('dark');
    });

    it('keeps the server prop theme when it matches the document', () => {
        document.documentElement.classList.add('dark');

        mountRoot('dark');

        expect(useThemeStore().theme).toBe('dark');
    });

    it('stays light when neither the prop nor the document is dark', () => {
        mountRoot('light');

        expect(useThemeStore().theme).toBe('light');
    });
});
