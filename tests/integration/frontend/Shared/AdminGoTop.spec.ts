import AdminGoTop from '@shared-admin/AdminGoTop.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function setScrollY(value: number): void {
    Object.defineProperty(window, 'scrollY', { value, configurable: true });
}

describe('AdminGoTop', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.stubGlobal('scrollTo', vi.fn());
        setScrollY(0);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('starts hidden', () => {
        const wrapper = mount(AdminGoTop);

        const button = wrapper.get('[data-testid="go-top"]');
        expect(button.classes()).toContain('opacity-0');
        expect(button.classes()).toContain('pointer-events-none');
    });

    it('becomes visible once the scroll threshold is crossed', async () => {
        const wrapper = mount(AdminGoTop);

        setScrollY(500);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.get('[data-testid="go-top"]').classes()).not.toContain('opacity-0');
    });

    it('stays hidden below the threshold', async () => {
        const wrapper = mount(AdminGoTop);

        setScrollY(100);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.get('[data-testid="go-top"]').classes()).toContain('opacity-0');
    });

    it('honors a custom threshold', async () => {
        const wrapper = mount(AdminGoTop, {
            props: { threshold: 1000 },
        });

        setScrollY(500);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.get('[data-testid="go-top"]').classes()).toContain('opacity-0');
    });

    it('scrolls to the top on click', async () => {
        const wrapper = mount(AdminGoTop);
        setScrollY(500);
        window.dispatchEvent(new Event('scroll'));
        await new Promise((resolve) => setTimeout(resolve));

        await wrapper.get('[data-testid="go-top"]').trigger('click');

        expect(globalThis.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    });

    it('uses the provided aria label', () => {
        const wrapper = mount(AdminGoTop, {
            props: { label: 'Back to top' },
        });

        expect(wrapper.get('[data-testid="go-top"]').attributes('aria-label')).toBe('Back to top');
    });

    it('falls back to the real scroll_to_top translation', () => {
        const wrapper = mount(AdminGoTop);

        expect(wrapper.get('[data-testid="go-top"]').attributes('aria-label')).toBe(
            'Scroll to top',
        );
    });
});
