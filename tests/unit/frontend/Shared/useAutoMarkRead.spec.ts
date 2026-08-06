import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { patchMock } = vi.hoisted(() => ({
    patchMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { patch: patchMock },
}));

import { useAutoMarkRead } from '@shared-cross/Composables/useAutoMarkRead';
import { useToastStore } from '@shared-cross/Stores/toastStore';

interface PatchOptions {
    preserveState: boolean;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (error: { status: number }) => void;
}

function lastPatchCall(): { url: string; data: Record<string, unknown>; options: PatchOptions } {
    const calls = patchMock.mock.calls as unknown as Array<
        [string, Record<string, unknown>, PatchOptions]
    >;
    const call = calls.at(-1);

    if (call === undefined) {
        throw new Error('mark-as-read PATCH was never called');
    }

    return { url: call[0], data: call[1], options: call[2] };
}

describe('useAutoMarkRead', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        patchMock.mockClear();
    });

    it('fires the mark-as-read PATCH automatically on page open', () => {
        useAutoMarkRead(7);

        const { url, data, options } = lastPatchCall();
        expect(url).toBe('/admin/feedback/7/read');
        expect(data).toEqual({});
        expect(options.preserveState).toBe(true);
    });

    it('shows an error toast when the PATCH fails with a validation error', () => {
        useAutoMarkRead(7);

        lastPatchCall().options.onError({ message: 'nope' });

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'Could not mark as read',
        });
    });

    it('shows an error toast when the PATCH hits an HTTP exception', () => {
        useAutoMarkRead(7);

        lastPatchCall().options.onHttpException({ status: 500 });

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0].message).toBe('Could not mark as read');
    });

    it('returns a markRead handle for manual re-fires', () => {
        const { markRead } = useAutoMarkRead(7);

        patchMock.mockClear();
        markRead();

        expect(patchMock).toHaveBeenCalledExactlyOnceWith('/admin/feedback/7/read', expect.anything(), expect.anything());
    });
});
