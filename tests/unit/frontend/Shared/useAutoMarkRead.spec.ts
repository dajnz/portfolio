import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAutoMarkRead } from '@shared-cross/Composables/useAutoMarkRead';
import { useToastStore } from '@shared-cross/Stores/toastStore';

describe('useAutoMarkRead', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.stubGlobal('fetch', vi.fn());
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';
    });

    function okResponse(): Promise<Response> {
        return Promise.resolve({ ok: true, json: async () => ({}) } as Response);
    }

    it('fires the mark-as-read JSON PATCH automatically on page open', () => {
        const fetchMock = vi.fn().mockReturnValue(okResponse());
        vi.mocked(fetch).mockImplementation(fetchMock);

        useAutoMarkRead(7);

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/feedback/7/read',
            expect.objectContaining({
                method: 'PATCH',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc123+def' }),
            }),
        );
    });

    it('shows an error toast when the PATCH fails', async () => {
        vi.mocked(fetch).mockResolvedValue({ ok: false, status: 500 } as Response);

        useAutoMarkRead(7);
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'Could not mark as read',
        });
    });

    it('flips isRead on success', async () => {
        vi.mocked(fetch).mockReturnValue(okResponse());

        const { isRead } = useAutoMarkRead(7);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(isRead.value).toBe(true);
    });

    it('returns a markRead handle for manual re-fires', async () => {
        const fetchMock = vi.fn().mockReturnValue(okResponse());
        vi.mocked(fetch).mockImplementation(fetchMock);

        const { markRead } = useAutoMarkRead(7);
        fetchMock.mockClear();

        markRead();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith('/admin/feedback/7/read', expect.anything());
    });
});
