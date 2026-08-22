import { type PageType, useAnalytics } from './useAnalytics';

/**
 * Declares the current page's analytics context (B289) and fires the
 * page view (analytics.md:48): called from a public page's setup, so
 * the `page_view` fires exactly once per page MOUNT — the initial
 * load included (the router's `finish` event does not fire for it)
 * — and carries the arriving page's context trivially. Prefetch
 * visits (usePrefetch) never mount the page, so they never count as
 * views. SSR-safe: the tracker is a no-op without `window`.
 */
export function usePageTracking(type: PageType | null, entityId: number | null = null): void {
    const analytics = useAnalytics();
    analytics.setPageContext(type, entityId);
    analytics.trackPageView();
}
