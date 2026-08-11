import { router } from '@inertiajs/vue3';

/**
 * Cross-surface focus management (accessibility.md:56-74): on every
 * Inertia page visit, focus is moved to the new page's `<h1>` (made
 * programmatically focusable via `tabindex="-1"`), without scrolling.
 * Registered once at the app root — layouts do not remount on client-side
 * navigation, so `onMounted` alone would miss subsequent visits.
 *
 * B174: when the user is typing in a filter/search input (the active
 * element is still the input at `finish` time — state-preserving
 * navigations keep the field mounted), the h1 must NOT steal the focus —
 * a debounced search would otherwise jump to the heading mid-typing.
 */
export function useFocusManagement(): { cleanup: () => void } {
    if (typeof window === 'undefined') {
        return { cleanup: () => undefined };
    }

    const unsubscribe = router.on('finish', () => {
        const active = document.activeElement;

        if (
            active instanceof HTMLInputElement ||
            active instanceof HTMLTextAreaElement ||
            active instanceof HTMLSelectElement
        ) {
            return;
        }

        const heading = document.querySelector('h1');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }
    });

    return { cleanup: unsubscribe };
}
