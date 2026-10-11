import type { Page } from '@inertiajs/core';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { routerOn, unsubscribers } = vi.hoisted(() => {
    const unsubscribers: Array<ReturnType<typeof vi.fn>> = [];
    const routerOn = vi.fn(() => {
        const unsubscribe = vi.fn();
        unsubscribers.push(unsubscribe);

        return unsubscribe;
    });

    return { routerOn, unsubscribers };
});

vi.mock('@inertiajs/vue3', () => ({
    router: { on: routerOn },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import AppRoot from '@/app.vue';

function makePage(): Page {
    return {
        component: 'Portfolio/Public/Pages/LandingPage',
        url: '/',
        version: '1',
        props: { locale: 'en', theme: 'light', auth: null },
    } as unknown as Page;
}

describe('app root Inertia listener cleanup', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        routerOn.mockClear();
        unsubscribers.length = 0;
    });

    it('unsubscribes every registered Inertia listener on unmount', () => {
        const wrapper = mount(AppRoot, {
            props: { page: makePage() },
            slots: { default: '<div data-testid="page" />' },
        });

        expect(routerOn).toHaveBeenCalled();
        expect(unsubscribers).toHaveLength(routerOn.mock.calls.length);

        wrapper.unmount();

        for (const unsubscribe of unsubscribers) {
            expect(unsubscribe).toHaveBeenCalledTimes(1);
        }
    });
});
