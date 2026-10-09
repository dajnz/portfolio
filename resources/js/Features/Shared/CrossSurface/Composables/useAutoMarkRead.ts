import { type Ref, ref } from 'vue';

import { useI18n } from './useI18n';
import { useJsonApi } from './useJsonApi';
import { useToast } from './useToast';

export interface AutoMarkReadApi {
    markRead: () => void;
    /** Flips to true once the PATCH succeeds — the detail page binds its
     *  read/unread badge to it. */
    isRead: Ref<boolean>;
}

/**
 * Admin feedback mark-as-read: fires
 * `PATCH /admin/feedback/{feedback}/read` automatically on page open.
 * On failure a toast ("Could not mark as read") appears and the page
 * keeps rendering with the current read/unread badge.
 *
 * The endpoint returns a plain JSON envelope (`{data: {is_read}}`), so
 * the request goes through the shared JSON API client — an Inertia
 * `router.patch` would abort the visit with "a plain JSON response was
 * received".
 */
export function useAutoMarkRead(feedbackId: number): AutoMarkReadApi {
    const toast = useToast();
    const errorMessage = useI18n().t('admin.toasts.mark_read_failed');
    const isRead = ref(false);
    const { patch } = useJsonApi();

    async function markRead(): Promise<void> {
        try {
            await patch(`/admin/feedback/${feedbackId}/read`);
            isRead.value = true;
        } catch {
            toast.show(errorMessage, { variant: 'error' });
        }
    }

    // SSR-safe: the request is only dispatched in the browser.
    if (typeof window !== 'undefined') {
        void markRead();
    }

    return { markRead, isRead };
}
