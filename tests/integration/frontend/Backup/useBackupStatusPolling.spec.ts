import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';

import {
    type BackupPollingHandle,
    useBackupStatusPolling,
} from '@backup/Admin/Composables/useBackupStatusPolling';

// Drains the microtask queue — flushPromises from @vue/test-utils schedules
// a `setTimeout(0)` and hangs under vitest fake timers.
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i += 1) {
        await Promise.resolve();
    }
}

function jsonResponse(payload: unknown) {
    return { ok: true, json: async () => payload };
}

function statusPayload(status: string, extra: Record<string, unknown> = {}) {
    return {
        data: {
            id: 1,
            status,
            progress: null,
            completed_at: null,
            error_message: null,
            ...extra,
        },
    };
}

function createHost() {
    let api: BackupPollingHandle | null = null;

    const Host = defineComponent({
        setup() {
            api = useBackupStatusPolling();

            return () => null;
        },
    });

    const wrapper = mount(Host);

    return { api: api as unknown as BackupPollingHandle, wrapper };
}

describe('useBackupStatusPolling', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock = vi.fn().mockResolvedValue(jsonResponse(statusPayload('processing')));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('polls the status endpoint every 2 seconds while processing', async () => {
        const { api, wrapper } = createHost();

        api.start({ id: 7 });

        await settle();
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/admin/backups/7/status');
        expect(api.isPolling.value).toBe(true);

        await vi.advanceTimersByTimeAsync(2000);
        expect(fetchMock).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(4000);
        expect(fetchMock).toHaveBeenCalledTimes(4);

        wrapper.unmount();
    });

    it('fires onCompleted when a create poll reaches completed and stops', async () => {
        const { api, wrapper } = createHost();
        const onCompleted = vi.fn();

        fetchMock
            .mockResolvedValueOnce(jsonResponse(statusPayload('pending')))
            .mockResolvedValueOnce(jsonResponse(statusPayload('processing')))
            .mockResolvedValueOnce(jsonResponse(statusPayload('completed')));

        api.start({ id: 1, onCompleted });

        await settle();
        await vi.advanceTimersByTimeAsync(2000);
        await settle();
        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        expect(onCompleted).toHaveBeenCalledTimes(1);
        expect(onCompleted).toHaveBeenCalledWith(
            expect.objectContaining({ id: 1, status: 'completed' }),
        );
        expect(api.isPolling.value).toBe(false);

        await vi.advanceTimersByTimeAsync(4000);
        expect(fetchMock).toHaveBeenCalledTimes(3);

        wrapper.unmount();
    });

    it('fires onFailed with the error message and stops', async () => {
        const { api, wrapper } = createHost();
        const onFailed = vi.fn();

        fetchMock.mockResolvedValue(
            jsonResponse(statusPayload('failed', { error_message: 'boom' })),
        );

        api.start({ id: 1, onFailed });

        await settle();

        expect(onFailed).toHaveBeenCalledWith(expect.objectContaining({ error_message: 'boom' }));
        expect(api.errorMessage.value).toBe('boom');
        expect(api.isPolling.value).toBe(false);

        wrapper.unmount();
    });

    it('exposes the progress value of processing responses', async () => {
        const { api, wrapper } = createHost();

        fetchMock.mockResolvedValue(jsonResponse(statusPayload('processing', { progress: 40 })));

        api.start({ id: 1 });

        await settle();
        expect(api.progress.value).toBe(40);

        wrapper.unmount();
    });

    it('keeps polling a completed row in restore mode until a transition is observed', async () => {
        const { api, wrapper } = createHost();
        const onCompleted = vi.fn();

        fetchMock
            // Worker lag: the record is still `completed` — not started.
            .mockResolvedValueOnce(jsonResponse(statusPayload('completed')))
            .mockResolvedValueOnce(jsonResponse(statusPayload('completed')))
            .mockResolvedValueOnce(jsonResponse(statusPayload('processing')))
            .mockResolvedValueOnce(jsonResponse(statusPayload('completed')));

        api.start({ id: 1, restore: true, onCompleted });

        await settle();
        expect(onCompleted).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);
        await settle();
        expect(onCompleted).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);
        await settle();
        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        expect(onCompleted).toHaveBeenCalledTimes(1);
        expect(api.isPolling.value).toBe(false);

        wrapper.unmount();
    });

    it('treats a 404 as completed in restore mode (the restored DB swapped the row out)', async () => {
        const { api, wrapper } = createHost();
        const onCompleted = vi.fn();

        fetchMock
            .mockResolvedValueOnce(jsonResponse(statusPayload('processing')))
            .mockResolvedValueOnce({ ok: false, status: 404 });

        api.start({ id: 1, restore: true, onCompleted });

        await settle();
        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        expect(onCompleted).toHaveBeenCalledTimes(1);
        expect(api.isPolling.value).toBe(false);

        wrapper.unmount();
    });

    it('stops silently on a 404 in create mode', async () => {
        const { api, wrapper } = createHost();
        const onCompleted = vi.fn();
        const onFailed = vi.fn();
        const onStopped = vi.fn();

        fetchMock
            .mockResolvedValueOnce(jsonResponse(statusPayload('pending')))
            .mockResolvedValueOnce({ ok: false, status: 404 });

        api.start({ id: 1, onCompleted, onFailed, onStopped });

        await settle();
        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        expect(onCompleted).not.toHaveBeenCalled();
        expect(onFailed).not.toHaveBeenCalled();
        expect(onStopped).toHaveBeenCalledTimes(1);
        expect(api.isPolling.value).toBe(false);

        wrapper.unmount();
    });

    it('keeps polling on transient transport errors and stops at the max ticks cap', async () => {
        const { api, wrapper } = createHost();
        const onStopped = vi.fn();

        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        api.start({ id: 1, intervalMs: 100, maxTicks: 3, onStopped });

        await settle();
        await vi.advanceTimersByTimeAsync(300);
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(onStopped).toHaveBeenCalledTimes(1);
        expect(api.isPolling.value).toBe(false);

        wrapper.unmount();
    });

    it('stops polling on unmount', async () => {
        const { api, wrapper } = createHost();

        api.start({ id: 1 });

        await settle();
        wrapper.unmount();

        const calls = fetchMock.mock.calls.length;
        await vi.advanceTimersByTimeAsync(6000);
        await settle();

        expect(fetchMock.mock.calls.length).toBe(calls);
        expect(api.isPolling.value).toBe(false);
    });
});
