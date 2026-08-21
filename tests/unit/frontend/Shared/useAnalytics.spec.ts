import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { onMock } = vi.hoisted(() => ({
    onMock: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { on: onMock },
}));

import { useAnalytics } from '@shared-cross/Composables/useAnalytics';

const SESSION_STORAGE_KEY = 'analytics_session_id';
const MAX_BUFFER_SIZE = 80;
const MAX_BUFFER_SIZE_BYTES = 45 * 1024;
const FLUSH_INTERVAL_MS = 30_000;

const fetchMock = vi.fn();

interface TrackedEvent {
    event_type: string;
    metadata: Record<string, unknown>;
}

function listener(type: string): () => void {
    const calls = onMock.mock.calls as unknown as Array<[string, () => void]>;
    const call = calls.find(([eventType]) => eventType === type);

    return call?.[1] as unknown as () => void;
}

function lastFetch(): { url: string; init: RequestInit; body: TrackedEvent[] } {
    const [url, init] = fetchMock.mock.calls.at(-1) as unknown as [string, RequestInit];

    return { url, init, body: JSON.parse(init.body as string) as TrackedEvent[] };
}

function fittingCount(events: Array<{ event_type: string; metadata: Record<string, unknown>; page_url: string; session_id: string }>): number {
    let bytes = 0;

    for (let i = 0; i < events.length; i++) {
        bytes += JSON.stringify(events[i]).length;
        if (bytes > MAX_BUFFER_SIZE_BYTES) {
            return i;
        }
    }

    return events.length;
}

let cleanups: Array<() => void> = [];

describe('useAnalytics', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        onMock.mockClear();
        cleanups = [];
        fetchMock.mockReset();
        fetchMock.mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', fetchMock);
        window.sessionStorage.clear();
    });

    afterEach(() => {
        for (const cleanup of cleanups) {
            cleanup();
        }
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('generates and reuses a session id from sessionStorage', () => {
        const first = useAnalytics();
        cleanups.push(first.cleanup);

        const storedId = sessionStorage.getItem(SESSION_STORAGE_KEY);
        expect(storedId).toBeTruthy();
        expect(crypto.randomUUID).toBeTruthy();

        const second = useAnalytics();
        cleanups.push(second.cleanup);

        expect(sessionStorage.getItem(SESSION_STORAGE_KEY)).toBe(storedId);
    });

    it('carries the declared page context in every event', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.setPageContext('blog_article', 42);
        analytics.track('page_view');
        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        expect(lastFetch().body[0]).toMatchObject({
            event_type: 'page_view',
            page_type: 'blog_article',
            page_entity_id: 42,
        });
    });

    it('shares the page context across tracker instances', () => {
        const page = useAnalytics();
        cleanups.push(page.cleanup);
        page.setPageContext('project_detail', 7);

        const component = useAnalytics();
        cleanups.push(component.cleanup);
        component.track('cta_click_github');
        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        expect(lastFetch().body[0]).toMatchObject({
            page_type: 'project_detail',
            page_entity_id: 7,
        });
    });

    it('resets the page context when a page without an entity is tracked', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.setPageContext('blog_article', 42);
        analytics.setPageContext('landing');
        analytics.track('page_view');
        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        expect(lastFetch().body[0]).toMatchObject({ page_type: 'landing', page_entity_id: null });
    });

    it('buffers events without sending anything', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.track('page_view');
        analytics.track('custom_event', { key: 'value' });

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('flushes the buffer on the 30 s interval', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.track('custom_event', { key: 'value' });
        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        expect(fetchMock).toHaveBeenCalledOnce();
        const { url, init, body } = lastFetch();
        expect(url).toBe('/api/analytics/track');
        expect(init.method).toBe('POST');
        expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json');
        expect(init.keepalive).toBeFalsy();
        expect(body).toHaveLength(1);
        expect(body[0]).toMatchObject({ event_type: 'custom_event', metadata: { key: 'value' } });
    });

    it('flushes immediately when the buffer reaches MAX_BUFFER_SIZE', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        for (let i = 0; i < MAX_BUFFER_SIZE; i++) {
            analytics.track('page_view');
        }

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(lastFetch().body).toHaveLength(MAX_BUFFER_SIZE);
    });

    it('drops the newest events once the buffer exceeds the 45 KB byte cap', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        const sessionId = sessionStorage.getItem(SESSION_STORAGE_KEY) as string;
        const makeEvent = (index: number) => ({
            event_type: 'big_event',
            metadata: { payload: 'x'.repeat(900), index },
            page_type: null,
            page_entity_id: null,
            page_url: window.location.href,
            session_id: sessionId,
        });

        for (let i = 0; i < 50; i++) {
            analytics.track('big_event', { payload: 'x'.repeat(900), index: i });
        }

        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        const expectedCount = fittingCount(Array.from({ length: 50 }, (_, i) => makeEvent(i)));
        const { body } = lastFetch();

        expect(body.length).toBeGreaterThan(0);
        expect(body.length).toBeLessThan(50);
        expect(body.length).toBe(expectedCount);
        // Oldest events are kept, the newest overflowing ones are dropped.
        expect(body[0]).toMatchObject({ metadata: { index: 0 } });
        expect(body.at(-1)).toMatchObject({ metadata: { index: expectedCount - 1 } });
    });

    it('flushes with keepalive on beforeunload', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.track('page_view');
        window.dispatchEvent(new Event('beforeunload'));

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(lastFetch().init.keepalive).toBe(true);
    });

    it('tracks time_on_page and flushes when the tab is hidden', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        vi.advanceTimersByTime(5000);
        Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
        document.dispatchEvent(new Event('visibilitychange'));

        expect(fetchMock).toHaveBeenCalledOnce();
        const { init, body } = lastFetch();
        expect(init.keepalive).toBe(true);
        expect(body.some((event) => event.event_type === 'time_on_page' && event.metadata.seconds === 5)).toBe(true);
    });

    it('tracks time_on_page when a navigation starts', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        vi.advanceTimersByTime(3000);
        listener('start')();

        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);
        const { body } = lastFetch();

        expect(body.some((event) => event.event_type === 'time_on_page' && event.metadata.seconds === 3)).toBe(true);
    });

    it('tracks page_view when a navigation finishes', () => {
        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        listener('finish')();

        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);
        const { body } = lastFetch();

        expect(body.some((event) => event.event_type === 'page_view' && event.metadata.referrer === document.referrer)).toBe(true);
    });

    it('swallows fetch failures silently', async () => {
        fetchMock.mockRejectedValue(new TypeError('Network error'));

        const analytics = useAnalytics();
        cleanups.push(analytics.cleanup);

        analytics.track('page_view');
        vi.advanceTimersByTime(FLUSH_INTERVAL_MS);

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    });

    it('cleanup stops the flush interval and removes listeners', () => {
        const analytics = useAnalytics();
        analytics.track('page_view');
        analytics.cleanup();

        vi.advanceTimersByTime(FLUSH_INTERVAL_MS * 2);
        window.dispatchEvent(new Event('beforeunload'));

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
