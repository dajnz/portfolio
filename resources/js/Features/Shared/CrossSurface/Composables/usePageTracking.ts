import { type PageType, useAnalytics } from './useAnalytics';

/**
 * Declares the current page's analytics context (B289): the page type
 * and — for the entity pages (blog article, project detail) — the
 * entity id. Called from a public page's setup; every analytics event
 * fired while the page is current carries this context (the
 * `page_view` fires on `router.on('finish')` — after this setup ran,
 * so it already carries the arriving page's context; `time_on_page`
 * fires on navigation start with the departing page's context).
 */
export function usePageTracking(type: PageType | null, entityId: number | null = null): void {
    useAnalytics().setPageContext(type, entityId);
}
