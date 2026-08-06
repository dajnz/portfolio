import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { trackMock } = vi.hoisted(() => ({
    trackMock: vi.fn(),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: trackMock, cleanup: vi.fn() }),
}));

import { useTheme } from '@shared-cross/Composables/useTheme';
import { useThemeStore } from '@shared-cross/Stores/themeStore';

const THEME_COOKIE = 'theme';

function cookieValue(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));

    return match ? match[1] : null;
}

describe('useTheme', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.documentElement.classList.remove('dark');
        document.cookie = `${THEME_COOKIE}=;path=/;max-age=0`;
        trackMock.mockClear();
    });

    it('toggles the theme store, the html class, the cookie and fires analytics', () => {
        const { toggle } = useTheme();
        const store = useThemeStore();

        const next = toggle();

        expect(next).toBe('dark');
        expect(store.theme).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(cookieValue(THEME_COOKIE)).toBe('dark');
        expect(trackMock).toHaveBeenCalledExactlyOnceWith('cta_click_theme');
    });

    it('removes the dark class and rewrites the cookie when toggling back', () => {
        const { toggle } = useTheme();

        toggle();
        toggle();

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(cookieValue(THEME_COOKIE)).toBe('light');
        expect(trackMock).toHaveBeenCalledTimes(2);
    });

    it('persists the theme cookie across toggles', () => {
        const { toggle } = useTheme();

        toggle();
        expect(cookieValue(THEME_COOKIE)).toBe('dark');

        toggle();
        expect(cookieValue(THEME_COOKIE)).toBe('light');
    });

    it('exposes the reactive store theme', () => {
        const { theme } = useTheme();
        const store = useThemeStore();

        store.setTheme('dark');

        expect(theme.value).toBe('dark');
    });
});
