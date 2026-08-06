import { router } from '@inertiajs/vue3';

/**
 * Cross-surface focus management (accessibility.md:56-74): on every
 * Inertia page visit, focus is moved to the new page's `<h1>` (made
 * programmatically focusable via `tabindex="-1"`), without scrolling.
 * Registered once at the app root — layouts do not remount on client-side
 * navigation, so `onMounted` alone would miss subsequent visits.
 */
export function useFocusManagement(): { cleanup: () => void } {
    if (typeof window === 'undefined') {
        return { cleanup: () => undefined };
    }

    const unsubscribe = router.on('finish', () => {
        const heading = document.querySelector('h1');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }
    });

    return { cleanup: unsubscribe };
}
