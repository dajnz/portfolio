import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PublicGoTop from '@shared-public/PublicGoTop.vue';

function setScrollY(value: number): void {
    Object.defineProperty(window, 'scrollY', { value, configurable: true });
}

describe('PublicGoTop', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.stubGlobal('scrollTo', vi.fn());
        setScrollY(0);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('starts hidden', () => {
        const wrapper = mount(PublicGoTop);

        expect(wrapper.get('[data-testid="go-top"]').classes()).toContain('is-hidden');
    });

    it('becomes visible once the scroll threshold is crossed', async () => {
        const wrapper = mount(PublicGoTop);

        setScrollY(500);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.get('[data-testid="go-top"]').classes()).not.toContain('is-hidden');
    });

    it('stays hidden below the threshold', async () => {
        const wrapper = mount(PublicGoTop);

        setScrollY(100);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.get('[data-testid="go-top"]').classes()).toContain('is-hidden');
    });

    it('scrolls to the top on click', async () => {
        const wrapper = mount(PublicGoTop);
        setScrollY(500);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        await wrapper.get('[data-testid="go-top"]').trigger('click');

        expect(globalThis.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    });

    it('uses the provided aria label', () => {
        const wrapper = mount(PublicGoTop, {
            props: { label: 'Back to top' },
        });

        expect(wrapper.get('[data-testid="go-top"]').attributes('aria-label')).toBe('Back to top');
    });
});
