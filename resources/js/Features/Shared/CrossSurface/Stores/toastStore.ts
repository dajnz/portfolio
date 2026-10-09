import { defineStore } from 'pinia';
import { ref } from 'vue';

import { randomUuid } from '../Utils/randomUuid';

export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: string;
    variant: ToastVariant;
    message: string;
    /** `null` persists until dismissed (error toasts). */
    duration_ms: number | null;
    dismissible: boolean;
}

export interface ShowToastOptions {
    variant?: ToastVariant;
    duration_ms?: number | null;
    dismissible?: boolean;
}

export const DEFAULT_TOAST_DURATIONS: Record<ToastVariant, number | null> = {
    success: 5000,
    error: null,
    warning: 8000,
    info: 5000,
};

/**
 * In-memory toast queue. Cleared on page navigation (wired at the app
 * root); never persisted.
 */
export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);

    function show(message: string, options: ShowToastOptions = {}): string {
        const variant = options.variant ?? 'success';
        const toast: Toast = {
            id: randomUuid(),
            variant,
            message,
            duration_ms:
                options.duration_ms === undefined
                    ? DEFAULT_TOAST_DURATIONS[variant]
                    : options.duration_ms,
            dismissible: options.dismissible ?? true,
        };

        toasts.value.push(toast);

        if (toast.duration_ms !== null) {
            window.setTimeout(() => dismiss(toast.id), toast.duration_ms);
        }

        return toast.id;
    }

    function dismiss(id: string): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function dismissAll(): void {
        toasts.value = [];
    }

    return { toasts, show, dismiss, dismissAll };
});
