import { App } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, type DefineComponent } from 'vue';

const { __seoState } = vi.hoisted(() => ({
    __seoState: { propsObj: null as Record<string, unknown> | null },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const { computed, reactive } = await import('vue');
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    // The composable's view of the page is controllable; the real App is
    // still mounted below so the module-level headManager exists.
    const propsObj = reactive<Record<string, unknown>>({ cspNonce: 'test-nonce' });
    __seoState.propsObj = propsObj;

    return { ...actual, usePage: () => reactive({ props: computed(() => propsObj) }) };
});

import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { SeoProps } from '@shared-cross/Types/seo';

const Host = defineComponent({
    name: 'Host',
    setup() {
        useSeoUpdater();

        return () => h('div', { 'data-testid': 'host' });
    },
});

function mountApp(): ReturnType<typeof mount> {
    return mount(App as DefineComponent, {
        attachTo: document.body,
        props: {
            initialPage: { component: 'Host', props: {}, url: '/current', version: '1' },
            resolveComponent: () => Host as DefineComponent,
        },
    });
}

function flushHead(): Promise<void> {
    // The head manager's Renderer.update is debounced by 1ms.
    return new Promise((resolve) => setTimeout(resolve, 20));
}

function setSeo(seo: SeoProps | null): void {
    if (__seoState.propsObj === null) {
        throw new Error('usePage mock not initialized');
    }

    if (seo === null) {
        delete __seoState.propsObj.seo;
    } else {
        __seoState.propsObj.seo = seo;
    }
}

describe('useSeoUpdater', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('renders the title, meta, canonical and robots tags from the seo props', async () => {
        const wrapper = mountApp();
        setSeo({
            title: 'Portfolio — Alex',
            description: 'Short pitch',
            og_url: 'https://example.com/',
            canonical_url: 'https://example.com/',
            robots: 'noindex',
        });
        await flushHead();

        expect(document.title).toBe('Portfolio — Alex');
        expect(document.querySelector('meta[name="description"]')?.getAttribute('content')).toBe(
            'Short pitch',
        );
        expect(document.querySelector('meta[property="og:url"]')?.getAttribute('content')).toBe(
            'https://example.com/',
        );
        expect(document.querySelector('link[rel="canonical"]')?.getAttribute('href')).toBe(
            'https://example.com/',
        );
        expect(document.querySelector('meta[name="robots"]')?.getAttribute('content')).toBe(
            'noindex',
        );

        wrapper.unmount();
    });

    it('applies the OG defaults: fallback image, 1200x630 and website type', async () => {
        const wrapper = mountApp();
        setSeo({ title: 'T', description: 'D' });
        await flushHead();

        expect(document.querySelector('meta[property="og:image"]')?.getAttribute('content')).toBe(
            '/images/og-default.png',
        );
        expect(
            document.querySelector('meta[property="og:image:width"]')?.getAttribute('content'),
        ).toBe('1200');
        expect(
            document.querySelector('meta[property="og:image:height"]')?.getAttribute('content'),
        ).toBe('630');
        expect(document.querySelector('meta[property="og:type"]')?.getAttribute('content')).toBe(
            'website',
        );

        wrapper.unmount();
    });

    it('inserts the JSON-LD blocks with the CSP nonce and replaces them on page change', async () => {
        const wrapper = mountApp();
        setSeo({ title: 'T', description: 'D', json_ld: [{ '@type': 'Person', name: 'Alex' }] });
        await flushHead();

        let scripts = document.querySelectorAll('script[data-seo-jsonld]');
        expect(scripts).toHaveLength(1);
        expect(scripts[0]?.getAttribute('nonce')).toBe('test-nonce');
        expect(JSON.parse(scripts[0]?.textContent ?? '')).toEqual({
            '@type': 'Person',
            name: 'Alex',
        });

        // Simulate a client-side navigation: the page props swap.
        setSeo({ title: 'T2', description: 'D2', json_ld: [{ '@type': 'WebSite', name: 'X' }] });
        await flushHead();

        scripts = document.querySelectorAll('script[data-seo-jsonld]');
        expect(scripts).toHaveLength(1);
        expect(JSON.parse(scripts[0]?.textContent ?? '')).toEqual({ '@type': 'WebSite', name: 'X' });
        expect(document.title).toBe('T2');
        expect(document.querySelector('meta[name="description"]')?.getAttribute('content')).toBe(
            'D2',
        );

        wrapper.unmount();
    });

    it('removes the JSON-LD scripts when no blocks are present', async () => {
        const wrapper = mountApp();
        setSeo({ title: 'T', description: 'D' });
        await flushHead();

        expect(document.querySelectorAll('script[data-seo-jsonld]')).toHaveLength(0);

        wrapper.unmount();
    });

    it('cleans up the head elements on unmount', async () => {
        const wrapper = mountApp();
        setSeo({ title: 'T', description: 'D', json_ld: [{ '@type': 'Person' }] });
        await flushHead();

        expect(document.querySelectorAll('script[data-seo-jsonld]')).toHaveLength(1);

        wrapper.unmount();
        await flushHead();

        expect(document.querySelectorAll('script[data-seo-jsonld]')).toHaveLength(0);
        expect(document.querySelectorAll('meta[property="og:title"]')).toHaveLength(0);
    });
});
