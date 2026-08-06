import { router } from '@inertiajs/vue3';

import { useI18n } from './useI18n';
import { useToast } from './useToast';

export interface AutoMarkReadApi {
    markRead: () => void;
}

/**
 * Admin feedback mark-as-read (admin-section-ui.md:119): fires
 * `PATCH /admin/feedback/{feedback}/read` automatically on page open.
 * On failure a toast ("Could not mark as read") appears and the page
 * keeps rendering with the current read/unread badge.
 */
export function useAutoMarkRead(feedbackId: number): AutoMarkReadApi {
    const toast = useToast();
    const errorMessage = useI18n().t('admin.toasts.mark_read_failed');

    function markRead(): void {
        router.patch(
            `/admin/feedback/${feedbackId}/read`,
            {},
            {
                preserveState: true,
                onError: () => {
                    toast.show(errorMessage, { variant: 'error' });
                },
                onHttpException: () => {
                    toast.show(errorMessage, { variant: 'error' });
                },
            },
        );
    }

    // Fires automatically on page open; SSR never triggers the request.
    if (typeof window !== 'undefined') {
        markRead();
    }

    return { markRead };
}
