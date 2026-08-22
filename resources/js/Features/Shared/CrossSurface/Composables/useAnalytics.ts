import { router } from '@inertiajs/vue3';

import { randomUuid } from '../Utils/randomUuid';

/**
 * The public page kinds reported with every event (B289) — the backend
 * attributes events to entities (blog article / project detail) from
 * this context, no URL matching.
 */
export type PageType =
    | 'landing'
    | 'blog_index'
    | 'blog_article'
    | 'projects_index'
    | 'project_detail'
    | 'experience';

export interface AnalyticsEvent {
    event_type: string;
    metadata: Record<string, unknown>;
    page_type: PageType | null;
    page_entity_id: number | null;
    page_url: string;
    session_id: string;
}

export interface AnalyticsTracker {
    track: (eventType: string, metadata?: Record<string, unknown>) => void;
    /** The page context for every event fired from now on (B289). */
    setPageContext: (type: PageType | null, entityId?: number | null) => void;
    cleanup: () => void;
}

const SESSION_STORAGE_KEY = 'analytics_session_id';
const MAX_BUFFER_SIZE = 80;
const MAX_BUFFER_SIZE_BYTES = 45 * 1024;
const FLUSH_INTERVAL_MS = 30_000;

// The page context is module-level so every tracker instance (the
// app-level registration and the per-component trackers) sees the
// page's context regardless of which instance set it.
let pageContext: { type: PageType | null; entityId: number | null } = {
    type: null,
    entityId: null,
};

/**
 * Session-scoped analytics tracker (analytics.md:45-114). Must be
 * registered exactly once at the app level (`app.vue`); components fire
 * events through `track()`. On the SSR side (no `window`) it degrades
 * to a no-op so the server render never touches browser storage.
 */
export function useAnalytics(): AnalyticsTracker {
    if (typeof window === 'undefined') {
        return {
            track: () => undefined,
            setPageContext: () => undefined,
            cleanup: () => undefined,
        };
    }

    const sessionId = getSessionId();
    const buffer: AnalyticsEvent[] = [];
    let pageStartTime = Date.now();
    let currentPageUrl = window.location.href;

    function setPageContext(type: PageType | null, entityId: number | null = null): void {
        pageContext = { type, entityId };
    }

    function track(eventType: string, metadata: Record<string, unknown> = {}): void {
        // Tracking is [Public]-only (analytics.md:3,38) — the admin
        // section must never emit events (the backend drops any strays
        // at aggregation, AggregateDailyMetricsUseCase). The URL is
        // read here so the departing page's `time_on_page` is judged
        // by its own path.
        if (window.location.pathname.startsWith('/admin')) {
            return;
        }

        buffer.push({
            event_type: eventType,
            metadata,
            page_type: pageContext.type,
            page_entity_id: pageContext.entityId,
            page_url: window.location.href,
            session_id: sessionId,
        });

        // B25 safety net: drop the newest events once the buffered batch
        // exceeds 45 KB so it never hits the backend's 50 KB payload cap.
        let bytes = 0;
        let dropFrom = -1;
        for (let i = 0; i < buffer.length; i++) {
            bytes += JSON.stringify(buffer[i]).length;
            if (bytes > MAX_BUFFER_SIZE_BYTES) {
                dropFrom = i;
                break;
            }
        }
        if (dropFrom >= 0) {
            buffer.splice(dropFrom);
        }

        if (buffer.length >= MAX_BUFFER_SIZE) {
            void flush();
        }
    }

    async function flush(useKeepalive = false): Promise<void> {
        if (buffer.length === 0) {
            return;
        }

        const payload = [...buffer];
        buffer.length = 0;

        try {
            await fetch('/api/analytics/track', {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' },
                keepalive: useKeepalive,
            });
        } catch {
            // The endpoint is implemented in step 11.1 (B33); failed
            // deliveries are dropped silently — never break the app for
            // analytics.
        }
    }

    const intervalId = setInterval(() => {
        void flush();
    }, FLUSH_INTERVAL_MS);

    const onBeforeUnload = (): void => {
        void flush(true);
    };
    window.addEventListener('beforeunload', onBeforeUnload);

    const onVisibilityChange = (): void => {
        if (document.visibilityState === 'hidden') {
            trackTimeOnPage();
            void flush(true);
        }
    };
    document.addEventListener('visibilitychange', onVisibilityChange);

    function trackTimeOnPage(): void {
        const elapsed = Math.floor((Date.now() - pageStartTime) / 1000);
        if (elapsed > 0) {
            track('time_on_page', { seconds: elapsed, page_url: currentPageUrl });
        }
    }

    // Time on the departing page + page view for the arriving one.
    const unsubscribeStart = router.on('start', trackTimeOnPage);
    const unsubscribeFinish = router.on('finish', routerFinish);

    function cleanup(): void {
        clearInterval(intervalId);
        window.removeEventListener('beforeunload', onBeforeUnload);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        unsubscribeStart();
        unsubscribeFinish();
    }

    function routerFinish(): void {
        pageStartTime = Date.now();
        currentPageUrl = window.location.href;
        track('page_view', { referrer: document.referrer, title: document.title });
    }

    return { track, setPageContext, cleanup };
}

function getSessionId(): string {
    let id = sessionStorage.getItem(SESSION_STORAGE_KEY);
    if (!id) {
        id = randomUuid();
        sessionStorage.setItem(SESSION_STORAGE_KEY, id);
    }

    return id;
}
