import { type ShowToastOptions, type Toast, useToastStore } from '@shared-cross/Stores/toastStore';
import { storeToRefs } from 'pinia';
import type { Ref } from 'vue';

export interface UseToastApi {
    toasts: Ref<Toast[]>;
    show: (message: string, options?: ShowToastOptions) => string;
    dismiss: (id: string) => void;
    dismissAll: () => void;
}

/**
 * Global toast API (error-handling.md:83-101) — thin accessor over the
 * `toastStore` queue. The toast containers (PublicBaseToastContainer /
 * AdminBaseToast) render the `toasts` array at layout level; session
 * `notification` flashes are picked up at the app root.
 */
export function useToast(): UseToastApi {
    const store = useToastStore();
    const { toasts } = storeToRefs(store);

    return {
        toasts,
        show: store.show,
        dismiss: store.dismiss,
        dismissAll: store.dismissAll,
    };
}
