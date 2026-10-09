import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { reloadMock, trackMock } = vi.hoisted(() => ({
    reloadMock: vi.fn(),
    trackMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    return { ...actual, router: { reload: reloadMock } };
});

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: trackMock, cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';

const LOCALE_COOKIE = 'locale';

function cookieValue(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));

    return match ? match[1] : null;
}

describe('LanguageSelector', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.cookie = `${LOCALE_COOKIE}=;path=/;max-age=0`;
        reloadMock.mockClear();
        trackMock.mockClear();
    });

    it('renders the active locale code on the button with the switch label', () => {
        const wrapper = mount(LanguageSelector, {
            props: { active_locale: 'ru' },
        });

        expect(wrapper.get('[data-testid="language-selector-button"]').text()).toBe('RU');
        // The current language is part of the accessible name.
        expect(wrapper.get('[data-testid="language-selector-button"]').attributes('aria-label')).toBe(
            'Switch language: Русский',
        );
    });

    it('opens the dropdown with the supported locales and marks the active one', async () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                supported_locales: [
                    { code: 'en', native_name: 'English' },
                    { code: 'ru', native_name: 'Русский' },
                ],
            },
        });

        await wrapper.get('[data-testid="language-selector-button"]').trigger('click');

        expect(wrapper.find('[data-testid="language-selector-dropdown"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="language-selector-option-en"]').attributes('aria-selected')).toBe('true');
        expect(wrapper.get('[data-testid="language-selector-option-en"]').attributes('aria-current')).toBe('true');
        expect(wrapper.get('[data-testid="language-selector-option-ru"]').attributes('aria-selected')).toBe('false');
        expect(wrapper.get('[data-testid="language-selector-option-ru"]').attributes('aria-current')).toBeUndefined();
        expect(wrapper.get('[data-testid="language-selector-option-en"]').text()).toContain('English');
    });

    it('selecting a locale sets the cookie and reloads the page without analytics by default', async () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                supported_locales: [
                    { code: 'en', native_name: 'English' },
                    { code: 'ru', native_name: 'Русский' },
                ],
            },
        });

        await wrapper.get('[data-testid="language-selector-button"]').trigger('click');
        await wrapper.get('[data-testid="language-selector-option-ru"]').trigger('click');

        expect(cookieValue(LOCALE_COOKIE)).toBe('ru');
        expect(reloadMock).toHaveBeenCalledExactlyOnceWith();
        expect(trackMock).not.toHaveBeenCalled();
    });

    it('fires cta_click_language with the target locale when tracking is enabled', async () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                supported_locales: [
                    { code: 'en', native_name: 'English' },
                    { code: 'ru', native_name: 'Русский' },
                ],
                track_locale_changes: true,
            },
        });

        await wrapper.get('[data-testid="language-selector-button"]').trigger('click');
        await wrapper.get('[data-testid="language-selector-option-ru"]').trigger('click');

        expect(trackMock).toHaveBeenCalledExactlyOnceWith('cta_click_language', {
            locale: 'ru',
        });
    });

    it('does not reload when the active locale is re-selected', async () => {
        const wrapper = mount(LanguageSelector, {
            props: { active_locale: 'en' },
        });

        await wrapper.get('[data-testid="language-selector-button"]').trigger('click');
        await wrapper.get('[data-testid="language-selector-option-en"]').trigger('click');

        expect(reloadMock).not.toHaveBeenCalled();
    });

    it('closes the dropdown on Escape and refocuses the trigger', async () => {
        const wrapper = mount(LanguageSelector, {
            props: { active_locale: 'en' },
        });

        await wrapper.get('[data-testid="language-selector-button"]').trigger('click');
        expect(wrapper.find('[data-testid="language-selector-dropdown"]').exists()).toBe(true);

        await wrapper.get('[data-testid="language-selector-button"]').trigger('keydown', {
            key: 'Escape',
        });

        expect(wrapper.find('[data-testid="language-selector-dropdown"]').exists()).toBe(false);
    });

    it('hides the selector when only one locale is enabled (i18n-and-theming.md:12)', () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                supported_locales: [{ code: 'en', native_name: 'English' }],
            },
        });

        expect(wrapper.find('[data-testid="language-selector-button"]').exists()).toBe(false);
    });

    it('renders the selector when a second locale is enabled', () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                supported_locales: [
                    { code: 'en', native_name: 'English' },
                    { code: 'ru', native_name: 'Русский' },
                ],
            },
        });

        expect(wrapper.find('[data-testid="language-selector-button"]').exists()).toBe(true);
    });

    it('applies the classes prop overrides in admin context', () => {
        const wrapper = mount(LanguageSelector, {
            props: {
                active_locale: 'en',
                classes: { button: 'h-9 rounded-lg border-slate-200 px-3', dropdown: 'mt-2', option: 'py-1' },
            },
        });

        expect(wrapper.get('[data-testid="language-selector-button"]').classes()).toContain(
            'rounded-lg',
        );
    });
});
