import { router } from '@inertiajs/vue3';
import { nextTick, type Ref, ref } from 'vue';

/**
 * Route-change announcement (a11y 13.6 review — replaces the former
 * focus-to-`<h1>` behaviour of accessibility.md:56-74). On every real
 * Inertia navigation the new page's `<h1>` (falling back to the document
 * title) is announced through a polite live region rendered by the app
 * root.
 *
 * Focus is deliberately NOT moved: the document stays at the top, so the
 * skip link remains the first tab stop after a navigation. Moving focus
 * to the `<h1>` (which sits inside `<main>`, after the skip link) made
 * the first Tab land on page content instead.
 *
 * Inertia fires `navigate` for the initial page render too; that first
 * event matches the current location and is skipped. Prefetches never
 * fire `navigate`, so hovering a card cannot trigger an announcement.
 */
export function useRouteAnnouncement(): { announcement: Ref<string>; cleanup: () => void } {
    if (typeof window === 'undefined') {
        return { announcement: ref(''), cleanup: () => undefined };
    }

    const announcement = ref('');
    let currentUrl = normalize(window.location.pathname + window.location.search);

    const unsubscribe = router.on('navigate', (event) => {
        const raw = event.detail?.page?.url ?? window.location.pathname;
        const url = normalize(String(raw));

        if (url === currentUrl) {
            return;
        }

        currentUrl = url;

        void nextTick(() => {
            const heading = document.querySelector('h1');

            announcement.value = heading?.textContent?.trim() ?? document.title;
        });
    });

    return { announcement, cleanup: unsubscribe };
}

/** Current path + query of a (possibly relative) URL, for comparison. */
function normalize(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);

        return parsed.pathname + parsed.search;
    } catch {
        return url;
    }
}
