import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useToast } from '@shared-cross/Composables/useToast';
import { useToastStore } from '@shared-cross/Stores/toastStore';

describe('useToast', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    it('exposes the store queue reactively', () => {
        const api = useToast();
        const store = useToastStore();

        store.show('Queued');

        expect(api.toasts.value).toHaveLength(1);
        expect(api.toasts.value[0].message).toBe('Queued');
    });

    it('show adds a toast through the store and returns its id', () => {
        const api = useToast();

        const id = api.show('Hello', { variant: 'info' });

        expect(api.toasts.value).toHaveLength(1);
        expect(api.toasts.value[0].id).toBe(id);
        expect(api.toasts.value[0].variant).toBe('info');
    });

    it('dismiss and dismissAll operate on the shared queue', () => {
        const api = useToast();
        const store = useToastStore();

        const id = api.show('One');
        api.show('Two');

        api.dismiss(id);
        expect(store.toasts).toHaveLength(1);

        api.dismissAll();
        expect(store.toasts).toHaveLength(0);
    });
});
