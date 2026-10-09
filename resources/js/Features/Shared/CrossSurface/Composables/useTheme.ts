import { type Theme, useThemeStore } from '@shared-cross/Stores/themeStore';
import { storeToRefs } from 'pinia';
import type { Ref } from 'vue';
import { useAnalytics } from './useAnalytics';

/**
 * `theme` cookie semantics mirror the SSR blocking inline script in
 * `resources/views/app.blade.php` — the toggle must write the same
 * cookie so the next SSR request agrees.
 */
const THEME_COOKIE = 'theme';
const THEME_COOKIE_MAX_AGE = 31536000;

/**
 * Theme toggle handling: toggles the `dark` class on `<html>`, writes
 * the `theme` cookie, fires the `cta_click_theme` analytics event, and
 * keeps the themeStore in sync. No Inertia reload — the cookie alone
 * guarantees the next SSR render.
 */
export function useTheme(): {
    theme: Ref<Theme>;
    toggle: () => Theme;
} {
    const themeStore = useThemeStore();
    const analytics = useAnalytics();
    const { theme } = storeToRefs(themeStore);

    function applyToDocument(theme: Theme): void {
        if (typeof document === 'undefined') {
            return;
        }

        document.documentElement.classList.toggle('dark', theme === 'dark');
    }

    function persistCookie(theme: Theme): void {
        if (typeof document === 'undefined') {
            return;
        }

        // biome-ignore lint/suspicious/noDocumentCookie: spec-mandated `theme` cookie
        document.cookie = `${THEME_COOKIE}=${theme};path=/;max-age=${THEME_COOKIE_MAX_AGE};SameSite=Lax`;
    }

    function toggle(): Theme {
        const next = themeStore.toggle();
        applyToDocument(next);
        persistCookie(next);
        analytics.track('cta_click_theme');

        return next;
    }

    return { theme, toggle };
}
