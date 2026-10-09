import { getCurrentScope, onScopeDispose, type Ref, ref } from 'vue';

import { useAnalytics } from './useAnalytics';

/**
 * Read-progress threshold — 0.76 is the first integer crossing of 75%
 * scroll depth.
 */
export const ARTICLE_READ_THRESHOLD = 0.76;

export interface ReadingProgress {
    /** Current scroll depth, 0..1. */
    progress: Ref<number>;
    cleanup: () => void;
}

/**
 * Scroll-position tracker for article pages: emits the `article_read`
 * analytics event exactly once when the reader reaches the threshold
 * scroll depth, with `{ article_id, percentage_completed }` metadata.
 *
 * Performance: the `scroll` listener is passive (never blocks scrolling)
 * and the measurement is coalesced to one `requestAnimationFrame` — the
 * `scrollHeight`/`innerHeight` layout reads run at most once per painted
 * frame, not per scroll event (which fires up to display frequency).
 */
export function useReadingProgress(
    articleId: number,
    threshold: number = ARTICLE_READ_THRESHOLD,
): ReadingProgress {
    const progress = ref(0);
    const analytics = useAnalytics();
    let fired = false;
    let ticking = false;

    if (typeof window === 'undefined') {
        return { progress, cleanup: () => undefined };
    }

    function update(): void {
        ticking = false;

        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        // A page that fits the viewport has no scroll depth — the read
        // event must not report 100% without the reader scrolling.
        const current = maxScroll > 0 ? Math.min(1, Math.max(0, window.scrollY / maxScroll)) : 0;
        progress.value = current;

        if (!fired && current >= threshold) {
            fired = true;
            analytics.track('article_read', {
                article_id: articleId,
                percentage_completed: Math.round(current * 100),
            });
        }
    }

    function onScroll(): void {
        if (ticking) {
            return;
        }

        ticking = true;
        requestAnimationFrame(update);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    update();

    function cleanup(): void {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
    }

    if (getCurrentScope()) {
        onScopeDispose(cleanup);
    }

    return { progress, cleanup };
}
