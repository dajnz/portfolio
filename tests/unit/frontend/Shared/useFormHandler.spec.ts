import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { postMock, putMock, visitMock, onMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    visitMock: vi.fn(),
    onMock: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock, put: putMock, visit: visitMock, on: onMock },
}));

import { useFormHandler } from '@shared-cross/Composables/useFormHandler';
import { useToastStore } from '@shared-cross/Stores/toastStore';

interface VisitOptions {
    preserveScroll: boolean;
    onSuccess: () => void;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (error: { status: number }) => void;
}

function lastVisitCall(mock: ReturnType<typeof vi.fn>): {
    url: string;
    data: Record<string, unknown>;
    options: VisitOptions;
} {
    const call = mock.mock.calls.at(-1) as unknown as [
        string,
        Record<string, unknown>,
        VisitOptions,
    ];

    return { url: call[0], data: call[1], options: call[2] };
}

describe('useFormHandler', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        putMock.mockClear();
        visitMock.mockClear();
    });

    it('submits with POST in create mode', () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog');

        handler.submit();

        const { url, options } = lastVisitCall(postMock);
        expect(url).toBe('/admin/blog');
        expect(options.preserveScroll).toBe(true);
        expect(options.onSuccess).toEqual(expect.any(Function));
        expect(options.onError).toEqual(expect.any(Function));
    });

    it('submits with PUT in edit mode', () => {
        const handler = useFormHandler({ title: 'Existing' }, '/admin/blog/1', { mode: 'edit' });

        handler.submit();

        expect(putMock).toHaveBeenCalledWith('/admin/blog/1', expect.anything(), expect.anything());
        expect(postMock).not.toHaveBeenCalled();
    });

    it('applies the transform function before submitting', () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog', {
            transform: (data) => ({ ...data, slug: 'generated-slug' }),
        });

        handler.submit();

        expect(lastVisitCall(postMock).data).toEqual({ title: 'New', slug: 'generated-slug' });
    });

    it('navigates to the redirect URL and clears the guard on success', async () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog', { redirectUrl: '/admin/blog/3' });

        handler.form.title = 'Changed';
        await nextTick();
        expect(handler.isDirty.value).toBe(true);

        handler.submit();
        lastVisitCall(postMock).options.onSuccess();

        expect(visitMock).toHaveBeenCalledWith('/admin/blog/3');
        expect(handler.isDirty.value).toBe(false);
    });

    it('resets the form on success without a redirect URL', () => {
        const handler = useFormHandler({ title: 'Initial', tags: ['vue'] }, '/admin/blog');

        handler.form.title = 'Changed';
        (handler.form.tags as string[]).push('inertia');

        handler.submit();
        lastVisitCall(postMock).options.onSuccess();

        expect(handler.form).toEqual({ title: 'Initial', tags: ['vue'] });
    });

    it('populates errors from a 422 response', () => {
        const handler = useFormHandler({ title: '' }, '/admin/blog');

        handler.submit();
        lastVisitCall(postMock).options.onError({ title: 'The title field is required.' });

        expect(handler.errors.value).toEqual({ title: 'The title field is required.' });
    });

    it('shows an error toast on 5xx responses', () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog');
        const toastStore = useToastStore();

        handler.submit();
        lastVisitCall(postMock).options.onHttpException({ status: 500 });

        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'Failed to save. Please try again.',
        });
    });

    it('does not toast for 4xx responses', () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog');
        const toastStore = useToastStore();

        handler.submit();
        lastVisitCall(postMock).options.onHttpException({ status: 422 });

        expect(toastStore.toasts).toHaveLength(0);
    });

    it('toggles isSubmitting around the visit', async () => {
        const handler = useFormHandler({ title: 'New' }, '/admin/blog');

        let observedDuringVisit = false;
        postMock.mockImplementationOnce(() => {
            observedDuringVisit = handler.isSubmitting.value;
        });

        const promise = handler.submit();

        expect(observedDuringVisit).toBe(true);
        expect(handler.isSubmitting.value).toBe(false);
        await expect(promise).resolves.toBeUndefined();
    });

    it('reset restores the initial values without losing nested structure', () => {
        const handler = useFormHandler({ title: 'Initial', translations: { en: { content: 'x' } } }, '/admin/blog');

        handler.form.title = 'Changed';
        (handler.form.translations as { en: { content: string } }).en.content = 'y';

        handler.reset();

        expect(handler.form).toEqual({ title: 'Initial', translations: { en: { content: 'x' } } });
    });
});
