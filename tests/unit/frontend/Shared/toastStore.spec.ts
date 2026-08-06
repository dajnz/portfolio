import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { DEFAULT_TOAST_DURATIONS, useToastStore } from '@shared-cross/Stores/toastStore';

describe('toastStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts with an empty queue', () => {
        const store = useToastStore();

        expect(store.toasts).toEqual([]);
    });

    it('shows a success toast with the default 5 s auto-dismiss', () => {
        const store = useToastStore();

        const id = store.show('Saved');

        expect(store.toasts).toHaveLength(1);
        expect(store.toasts[0]).toMatchObject({
            id,
            variant: 'success',
            message: 'Saved',
            duration_ms: DEFAULT_TOAST_DURATIONS.success,
            dismissible: true,
        });
    });

    it('uses variant-specific default durations', () => {
        const store = useToastStore();

        store.show('Careful', { variant: 'warning' });
        store.show('Info', { variant: 'info' });
        store.show('Failed', { variant: 'error' });

        expect(store.toasts[0].duration_ms).toBe(DEFAULT_TOAST_DURATIONS.warning);
        expect(store.toasts[1].duration_ms).toBe(DEFAULT_TOAST_DURATIONS.info);
        expect(store.toasts[2].duration_ms).toBeNull();
    });

    it('auto-dismisses non-error toasts after their duration', () => {
        const store = useToastStore();

        store.show('Saved', { variant: 'success', duration_ms: 1000 });
        vi.advanceTimersByTime(999);
        expect(store.toasts).toHaveLength(1);

        vi.advanceTimersByTime(1);
        expect(store.toasts).toHaveLength(0);
    });

    it('keeps error toasts until dismissed', () => {
        const store = useToastStore();

        store.show('Failed', { variant: 'error' });
        vi.advanceTimersByTime(60_000);

        expect(store.toasts).toHaveLength(1);
    });

    it('respects explicit duration and dismissible overrides', () => {
        const store = useToastStore();

        store.show('Sticky', { variant: 'info', duration_ms: null, dismissible: false });

        expect(store.toasts[0]).toMatchObject({ duration_ms: null, dismissible: false });

        vi.advanceTimersByTime(60_000);
        expect(store.toasts).toHaveLength(1);
    });

    it('dismiss removes a toast by id', () => {
        const store = useToastStore();

        const first = store.show('One');
        store.show('Two');

        store.dismiss(first);

        expect(store.toasts.map((toast) => toast.message)).toEqual(['Two']);
    });

    it('dismissAll empties the queue', () => {
        const store = useToastStore();

        store.show('One');
        store.show('Two');

        store.dismissAll();

        expect(store.toasts).toEqual([]);
    });
});
