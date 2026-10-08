import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { onMock } = vi.hoisted(() => ({
    onMock: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { on: onMock },
}));

import { useRouteAnnouncement } from '@shared-cross/Composables/useRouteAnnouncement';

type NavigateEvent = { detail: { page: { url: string } } };

function navigateHandler(): ((event: NavigateEvent) => void) | undefined {
    const calls = onMock.mock.calls as unknown as Array<[string, (event: NavigateEvent) => void]>;
    const call = calls.find(([type]) => type === 'navigate');

    return call?.[1];
}

describe('useRouteAnnouncement', () => {
    beforeEach(() => {
        onMock.mockClear();
    });

    it('listens for Inertia navigate events', () => {
        useRouteAnnouncement();

        expect(onMock).toHaveBeenCalledWith('navigate', expect.any(Function));
    });

    it('announces the new page h1 on a navigation', async () => {
        document.body.innerHTML = '<h1>Blog</h1>';

        const { announcement } = useRouteAnnouncement();
        navigateHandler()?.({ detail: { page: { url: '/blog' } } });
        await nextTick();

        expect(announcement.value).toBe('Blog');
    });

    it('falls back to the document title when the page has no h1', async () => {
        document.body.innerHTML = '<p>no heading</p>';

        const { announcement } = useRouteAnnouncement();
        navigateHandler()?.({ detail: { page: { url: '/blog' } } });
        await nextTick();

        expect(announcement.value).toBe(document.title);
    });

    it('skips the initial navigation (same url as the boot location)', async () => {
        document.body.innerHTML = '<h1>Home</h1>';

        const { announcement } = useRouteAnnouncement();
        // The test environment boots at http://localhost/ → currentUrl '/'.
        navigateHandler()?.({ detail: { page: { url: '/' } } });
        await nextTick();

        expect(announcement.value).toBe('');
    });

    it('returns a cleanup that unsubscribes the listener', () => {
        const unsubscribeMock = vi.fn();
        onMock.mockReturnValueOnce(unsubscribeMock);

        const { cleanup } = useRouteAnnouncement();
        cleanup();

        expect(unsubscribeMock).toHaveBeenCalledOnce();
    });
});
