import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { trackMock } = vi.hoisted(() => ({
    trackMock: vi.fn(),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: trackMock, cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ThemeSwitch from '@shared-cross/Components/ThemeSwitch.vue';

const THEME_COOKIE = 'theme';

function cookieValue(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));

    return match ? match[1] : null;
}

describe('ThemeSwitch', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.documentElement.classList.remove('dark');
        document.cookie = `${THEME_COOKIE}=;path=/;max-age=0`;
        trackMock.mockClear();
    });

    it('renders the light state with the moon icon and the switch-to-dark label', () => {
        const wrapper = mount(ThemeSwitch);

        expect(wrapper.get('[data-testid="theme-switch"]').attributes('data-state')).toBe('light');
        expect(wrapper.get('[data-testid="theme-switch-button"]').attributes('aria-label')).toBe(
            'Switch to dark mode',
        );
    });

    it('toggles to dark: html class, cookie, icon state and analytics event', async () => {
        const wrapper = mount(ThemeSwitch);

        await wrapper.get('[data-testid="theme-switch-button"]').trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(cookieValue(THEME_COOKIE)).toBe('dark');
        expect(wrapper.get('[data-testid="theme-switch"]').attributes('data-state')).toBe('dark');
        expect(wrapper.get('[data-testid="theme-switch-button"]').attributes('aria-label')).toBe(
            'Switch to light mode',
        );
        expect(trackMock).toHaveBeenCalledExactlyOnceWith('cta_click_theme');
    });

    it('toggles back to light on the second click', async () => {
        const wrapper = mount(ThemeSwitch);

        await wrapper.get('[data-testid="theme-switch-button"]').trigger('click');
        await wrapper.get('[data-testid="theme-switch-button"]').trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(cookieValue(THEME_COOKIE)).toBe('light');
        expect(trackMock).toHaveBeenCalledTimes(2);
    });

    it('applies the classes prop overrides in admin context', () => {
        const wrapper = mount(ThemeSwitch, {
            props: {
                classes: {
                    button: 'h-9 w-9 rounded-lg text-slate-500',
                    icon_light: 'h-5 w-5',
                    icon_dark: 'h-5 w-5',
                },
            },
        });

        expect(wrapper.get('[data-testid="theme-switch-button"]').classes()).toContain(
            'rounded-lg',
        );
    });
});
