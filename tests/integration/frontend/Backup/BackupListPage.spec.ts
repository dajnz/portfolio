import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Drains the microtask queue — flushPromises from @vue/test-utils schedules
// a `setTimeout(0)` and hangs under vitest fake timers.
async function settle(): Promise<void> {
    for (let i = 0; i < 5; i += 1) {
        await Promise.resolve();
    }
}

const { pageProps, routerMock } = vi.hoisted(() => ({
    pageProps: {
        locale: 'en',
        supported_locales: ['en', 'ru'],
    },
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        reload: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: routerMock.get,
        post: routerMock.post,
        delete: routerMock.delete,
        reload: routerMock.reload,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

import BackupListPage from '@backup/Admin/Pages/BackupListPage/BackupListPage.vue';
import RestoreForm from '@backup/Admin/Components/RestoreForm.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

// Wire fixtures mirroring the OpenAPI BackupListPageProps shape
// (docs/openapi/inertia/backup.yaml).
const COMPLETED_45MB = {
    id: 1,
    filename: 'backup-2026-08-01-020000-k3.zip',
    size_bytes: 47185920,
    status: 'completed' as const,
    created_at: '2026-08-01T02:00:00+00:00',
};

const COMPLETED_1MB = {
    id: 2,
    filename: 'backup-2026-07-01-020000-k3.zip',
    size_bytes: 1048576,
    status: 'completed' as const,
    created_at: '2026-07-01T02:00:00+00:00',
};

const KEY_ENTRIES = [
    {
        id: 3,
        name: 'rotated-2026',
        created_at: '2026-07-01T00:00:00+00:00',
        is_active: true,
        backups_count: 12,
    },
];

// 44-char base64 raw key accepted by the DR upload's client-side checks.
const RAW_ENCRYPTION_KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

// The stored-key rows of the DR import-vs-reuse scenarios: one existing
// key before the upload; a fresh import adds a `disaster-key-*` row.
const ROTATED_KEY_ROW = {
    id: 3,
    name: 'rotated-2026',
    created_at: '2026-07-01T00:00:00+00:00',
    is_active: true,
    backups_count: 1,
};

const IMPORTED_KEY_ROW = {
    id: 4,
    name: 'disaster-key-abc12345',
    created_at: '2026-08-24T12:00:01+00:00',
    is_active: false,
    backups_count: 1,
};

function jsonResponse(payload: unknown, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => payload,
    };
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

function mountPage(props: Record<string, unknown> = {}) {
    const wrapper = mount(BackupListPage, {
        props: {
            backups: [],
            meta: { current_page: 1, from: null, to: null, last_page: 1, per_page: 12, total: 0 },
            encryption_key_prompt: false,
            test_id: 'backup-list-page',
            ...props,
        } as unknown as InstanceType<typeof BackupListPage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

/** Selects the first completed backup in the RestoreForm dropdown and
 *  submits it with the given password. */
async function fillRestoreForm(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper
        .get('[data-testid="backup-list-page-restore-backup"]')
        .trigger('click');
    await wrapper
        .get('[data-testid="backup-list-page-restore-backup-option-1"]')
        .trigger('click');
    await wrapper
        .get('[data-testid="backup-list-page-restore-password"]')
        .setValue('correct-password');
    await wrapper.get('[data-testid="backup-list-page-restore-submit"]').trigger('click');
    await settle();
}

/** Picks a `.zip` file in the DR dropzone. */
async function pickDisasterZip(wrapper: ReturnType<typeof mount>): Promise<void> {
    const zip = new File(['zip'], 'backup.zip', { type: 'application/zip' });
    const input = wrapper.get('[data-testid="backup-list-page-upload-file-input"]')
        .element as HTMLInputElement;
    Object.defineProperty(input, 'files', { value: [zip] });
    await input.dispatchEvent(new Event('change'));
    await settle();
}

/** Fills the DR form (zip + raw key + password) and submits it. */
async function submitDisasterUpload(wrapper: ReturnType<typeof mount>): Promise<void> {
    await pickDisasterZip(wrapper);

    await wrapper
        .get('[data-testid="backup-list-page-upload-key"]')
        .setValue(RAW_ENCRYPTION_KEY);
    await wrapper
        .get('[data-testid="backup-list-page-upload-password"]')
        .setValue('correct-password');
    await wrapper.get('[data-testid="backup-list-page-upload-submit"]').trigger('click');
    await settle();
}

/** Asserts the DR form inputs were cleared (successful restore). */
function expectDisasterFormCleared(wrapper: ReturnType<typeof mount>): void {
    expect(wrapper.text()).not.toContain('backup.zip');
    expect(
        (wrapper.get('[data-testid="backup-list-page-upload-key"]').element as HTMLInputElement)
            .value,
    ).toBe('');
    expect(
        (
            wrapper.get('[data-testid="backup-list-page-upload-password"]')
                .element as HTMLInputElement
        ).value,
    ).toBe('');
}

let mountedWrappers: Array<ReturnType<typeof mount>> = [];

describe('BackupListPage', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setActivePinia(createPinia());
        pageProps.locale = 'en';
        pageProps.supported_locales = ['en', 'ru'];
        routerMock.get.mockClear();
        routerMock.post.mockClear();
        routerMock.delete.mockClear();
        routerMock.reload.mockClear();
        document.body.innerHTML = '';
        vi.useFakeTimers();

        fetchMock = vi.fn().mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers = [];
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    /** Lands the DR upload redirect: the imported `backup-import-*` row
     *  (id 9) is still `completed` (worker lag), the keys refetch
     *  already sees the post-import state, and the submit-time fallback
     *  poll of the imported row starts (its first tick reports
     *  `processing`). */
    async function stageDisasterRestoreRedirect(
        wrapper: ReturnType<typeof mount>,
        options: {
            filename: string;
            keyRowsAfterRedirect: Array<typeof ROTATED_KEY_ROW>;
        },
    ): Promise<void> {
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('processing')));
            }

            return Promise.resolve(jsonResponse({ data: options.keyRowsAfterRedirect }));
        });

        await wrapper.setProps({
            backups: [
                {
                    id: 9,
                    filename: options.filename,
                    size_bytes: 52428800,
                    status: 'completed',
                    created_at: '2026-08-24T12:00:00+00:00',
                },
            ],
        });
        await settle();

        const postOptions = routerMock.post.mock.calls[0][2] as { onSuccess: () => void };
        postOptions.onSuccess();
        await settle();
    }

    /** Lets the running restore poll observe the record's completion. */
    async function completeRunningPoll(): Promise<void> {
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await vi.advanceTimersByTimeAsync(2000);
        await settle();
    }

    it('renders the page title and the derived stats card', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB, COMPLETED_1MB],
            meta: { current_page: 1, from: 1, to: 2, last_page: 1, per_page: 12, total: 2 },
        });
        await settle();

        expect(wrapper.get('[data-testid="backup-list-page"] h1').text()).toBe('Backup & Restore');

        // BackupStats — derived from the page props (mockup backups.html).
        const stats = wrapper.get('[data-testid="backup-list-page-stats"]');
        expect(stats.get('[data-testid="backup-list-page-stats-total"]').text()).toBe('2');
        expect(stats.get('[data-testid="backup-list-page-stats-size"]').text()).toBe('46 MB');
        expect(stats.get('[data-testid="backup-list-page-stats-encryption"]').text()).toContain(
            'Inactive',
        );
        expect(stats.get('[data-testid="backup-list-page-stats-last-backup"]').text()).toBe(
            '2026-08-01 02:00 UTC',
        );
    });

    it('renders the backup table rows with their formatted size and status badges', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB, COMPLETED_1MB],
            meta: { current_page: 1, from: 1, to: 2, last_page: 1, per_page: 12, total: 2 },
        });
        await settle();

        const rows = wrapper.findAll('[data-testid="backup-list-page-table"] tbody tr');
        expect(rows).toHaveLength(2);

        const first = rows[0];
        expect(first.text()).toContain('backup-2026-08-01-020000-k3.zip');
        expect(first.text()).toContain('45 MB');
        expect(first.text()).toContain('Completed');

        expect(rows[1].text()).toContain('backup-2026-07-01-020000-k3.zip');
    });

    it('renders the restore form with only the completed backups and the DR upload form', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB, COMPLETED_1MB],
            meta: { current_page: 1, from: 1, to: 2, last_page: 1, per_page: 12, total: 2 },
        });
        await settle();

        // The RestoreForm dropdown lists only completed backups.
        const restoreForm = wrapper.findComponent(RestoreForm);
        expect(restoreForm.props('backups')).toEqual([
            { id: 1, filename: COMPLETED_45MB.filename },
            { id: 2, filename: COMPLETED_1MB.filename },
        ]);

        expect(wrapper.find('[data-testid="backup-list-page-upload"]').exists()).toBe(true);
    });

    it('renders the encryption key list with the active badge and per-key backup counts', async () => {
        fetchMock.mockImplementation(() =>
            Promise.resolve(jsonResponse({ data: KEY_ENTRIES })),
        );

        const wrapper = mountPage();
        await settle();

        const keyRow = wrapper.get('[data-testid="backup-list-page-keys-key-3"]');
        expect(keyRow.text()).toContain('rotated-2026');
        expect(keyRow.text()).toContain('Active');
        expect(keyRow.text()).toContain('12 backups');

        // The active key feeds the BackupStats encryption line ("Active (k3)").
        expect(
            wrapper.get('[data-testid="backup-list-page-stats-encryption"]').text(),
        ).toContain('Active (k3)');
    });

    it('posts to /admin/backups on Create Backup Now and polls a pending row to completion', async () => {
        const wrapper = mountPage();
        await settle();

        await wrapper.get('[data-testid="backup-list-page-stats-create"]').trigger('click');
        await settle();

        expect(routerMock.post).toHaveBeenCalledWith('/admin/backups', undefined, expect.anything());

        // The redirect lands with the new row still pending — the page
        // picks it up and polls it (mockup backups.html data-flow).
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await wrapper.setProps({
            backups: [
                {
                    id: 9,
                    filename: 'backup-2026-08-24-020000-k3.zip',
                    size_bytes: 0,
                    status: 'pending',
                    created_at: '2026-08-24T02:00:00+00:00',
                },
            ],
        });
        await settle();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/backups/9/status',
            expect.objectContaining({ credentials: 'same-origin' }),
        );

        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        // The completion reload lands the completed row first, then the
        // "Backup created." toast fires (a toast shown before the reload
        // would be cleared when the visit starts).
        expect(routerMock.reload).toHaveBeenCalledTimes(1);

        const reloadOptions = routerMock.reload.mock.calls[0][0] as {
            onSuccess?: () => void;
        };
        reloadOptions.onSuccess?.();
        await settle();

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Backup created.',
            variant: 'success',
        });
    });

    it('disables the create button while a create is in flight', async () => {
        fetchMock.mockImplementation((url: string) =>
            url.includes('/status')
                ? Promise.resolve(jsonResponse(statusPayload('processing')))
                : Promise.resolve(jsonResponse({ data: [] })),
        );

        const wrapper = mountPage({
            backups: [
                {
                    id: 9,
                    filename: 'backup-2026-08-24-020000-k3.zip',
                    size_bytes: 0,
                    status: 'pending',
                    created_at: '2026-08-24T02:00:00+00:00',
                },
            ],
        });
        await settle();

        expect(
            wrapper
                .get('[data-testid="backup-list-page-stats-create"]')
                .attributes('aria-busy'),
        ).toBe('true');
    });

    it('submits the restore payload for the selected completed backup', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB],
            meta: { current_page: 1, from: 1, to: 1, last_page: 1, per_page: 12, total: 1 },
        });
        await settle();

        await fillRestoreForm(wrapper);

        expect(routerMock.post).toHaveBeenCalledWith(
            '/admin/backups/restore',
            { backup_id: 1, password: 'correct-password' },
            expect.anything(),
        );
    });

    it('polls the submitted restore row to completion and shows the success toast', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB],
            meta: { current_page: 1, from: 1, to: 1, last_page: 1, per_page: 12, total: 1 },
        });
        await settle();

        await fillRestoreForm(wrapper);

        // The redirect lands (worker-lag: the row still completed) — the
        // page polls the submitted record; processing → completed fires
        // the ADR-0018 success toast.
        const options = routerMock.post.mock.calls[0][2] as { onSuccess: () => void };
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('processing')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        options.onSuccess();
        await settle();

        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        // The completion needs no reload (the restored row's local copy
        // is patched instead) — the success toast fires right away.
        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Restore completed successfully.',
            variant: 'success',
        });
        expect(routerMock.reload).not.toHaveBeenCalled();
    });

    it('shows the server password validation error inline in the restore form', async () => {
        const wrapper = mountPage({ backups: [COMPLETED_45MB] });
        await settle();

        await wrapper
            .get('[data-testid="backup-list-page-restore-backup"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="backup-list-page-restore-backup-option-1"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="backup-list-page-restore-password"]')
            .setValue('wrong');
        await wrapper.get('[data-testid="backup-list-page-restore-submit"]').trigger('click');
        await settle();

        const options = routerMock.post.mock.calls[0][2] as {
            onError: (errors: Record<string, string>) => void;
        };
        options.onError({ password: 'The password is incorrect.' });
        await settle();

        expect(
            wrapper.get('[data-testid="backup-list-page-restore-password"]').attributes('aria-invalid'),
        ).toBe('true');
        expect(wrapper.text()).toContain('The password is incorrect.');
    });

    it('shows the failed restore error message inline from the status poll', async () => {
        const wrapper = mountPage({
            backups: [
                {
                    ...COMPLETED_45MB,
                    status: 'processing',
                },
            ],
        });
        await settle();

        fetchMock.mockImplementation((url: string) =>
            url.includes('/status')
                ? Promise.resolve(
                      jsonResponse(statusPayload('failed', { error_message: 'boom' })),
                  )
                : Promise.resolve(jsonResponse({ data: [] })),
        );

        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        expect(
            wrapper.get('[data-testid="backup-list-page-restore-error"]').text(),
        ).toBe('boom');
    });

    it('restores a processing backup-import row with the DR success toast and patches the badge', async () => {
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('processing')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        const wrapper = mountPage({
            backups: [
                {
                    id: 7,
                    filename: 'backup-import-2026-08-24-120000-k5.zip',
                    size_bytes: 52428800,
                    status: 'processing',
                    created_at: '2026-08-24T12:00:00+00:00',
                },
            ],
        });
        await settle();

        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await vi.advanceTimersByTimeAsync(2000);
        await settle();

        // No submit snapshot in this page lifetime — the import message
        // is the default (fresh-instance DR semantics). No reload after
        // the completion: the toast would be cleared by the visit start.
        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Backup restored successfully. The imported encryption key has been saved.',
            variant: 'success',
        });
        expect(routerMock.reload).not.toHaveBeenCalled();
        expect(
            wrapper.get('[data-testid="backup-list-page-table-status-7"]').text(),
        ).toContain('Completed');
    });

    it('falls back to polling the newest imported row when the DR upload redirect leaves it completed', async () => {
        // One stored key before the upload — the page loads it at mount.
        fetchMock.mockImplementation(() =>
            Promise.resolve(jsonResponse({ data: [ROTATED_KEY_ROW] })),
        );

        const wrapper = mountPage();
        await settle();
        await submitDisasterUpload(wrapper);

        expect(routerMock.post).toHaveBeenCalledWith(
            '/admin/backups/upload',
            expect.any(FormData),
            expect.anything(),
        );

        // The redirect lands with the imported row still `completed`
        // (worker lag). The import added a `disaster-key-*` row — the
        // keys refetch (props change) must already see it.
        const keyRowsAfterRedirect = [ROTATED_KEY_ROW, IMPORTED_KEY_ROW];
        await stageDisasterRestoreRedirect(wrapper, {
            filename: 'backup-import-2026-08-24-120000-k4--disaster-key-abc12345.zip',
            keyRowsAfterRedirect,
        });

        // The submit-time fallback: the imported row's id is unknown
        // client-side, so the newest `backup-import-*` row is polled.
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/backups/9/status',
            expect.objectContaining({ credentials: 'same-origin' }),
        );
    });

    it('toasts the imported-key message and clears the DR form once the fallback poll completes', async () => {
        fetchMock.mockImplementation(() =>
            Promise.resolve(jsonResponse({ data: [ROTATED_KEY_ROW] })),
        );

        const wrapper = mountPage();
        await settle();
        await submitDisasterUpload(wrapper);

        const keyRowsAfterRedirect = [ROTATED_KEY_ROW, IMPORTED_KEY_ROW];
        await stageDisasterRestoreRedirect(wrapper, {
            filename: 'backup-import-2026-08-24-120000-k4--disaster-key-abc12345.zip',
            keyRowsAfterRedirect,
        });

        // The poll completes — the count grew by one, so the finish
        // toast reports the fresh import.
        await completeRunningPoll();

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Backup restored successfully. The imported encryption key has been saved.',
            variant: 'success',
        });
        expectDisasterFormCleared(wrapper);
        expect(routerMock.reload).not.toHaveBeenCalled();
    });

    it('toasts the reused-key message and clears the DR form when the uploaded key matches a stored key', async () => {
        // The stored key stays the same before and after the upload —
        // the uploaded archive's key matched the stored one (no import).
        fetchMock.mockImplementation(() =>
            Promise.resolve(jsonResponse({ data: [ROTATED_KEY_ROW] })),
        );

        const wrapper = mountPage();
        await settle();
        await submitDisasterUpload(wrapper);

        await stageDisasterRestoreRedirect(wrapper, {
            filename: 'backup-import-2026-08-24-120000-k3--rotated-2026.zip',
            keyRowsAfterRedirect: [ROTATED_KEY_ROW],
        });

        // The poll completes — the count did not grow, so the finish
        // toast reports the reused key.
        await completeRunningPoll();

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Backup restored successfully. The matching existing encryption key was used.',
            variant: 'success',
        });
        expectDisasterFormCleared(wrapper);
        expect(routerMock.reload).not.toHaveBeenCalled();
    });

    it('rejects a non-zip DR file client-side', async () => {
        const wrapper = mountPage();
        await settle();

        const input = wrapper.get('[data-testid="backup-list-page-upload-file-input"]')
            .element as HTMLInputElement;
        const file = new File(['nope'], 'backup.txt', { type: 'text/plain' });
        Object.defineProperty(input, 'files', { value: [file] });
        await input.dispatchEvent(new Event('change'));
        await settle();

        expect(wrapper.text()).toContain('Please select a .zip backup file.');
        expect(routerMock.post).not.toHaveBeenCalled();
    });

    it('validates the DR key length and password client-side', async () => {
        const wrapper = mountPage();
        await settle();

        const zip = new File(['zip'], 'backup.zip', { type: 'application/zip' });
        const input = wrapper.get('[data-testid="backup-list-page-upload-file-input"]')
            .element as HTMLInputElement;
        Object.defineProperty(input, 'files', { value: [zip] });
        await input.dispatchEvent(new Event('change'));
        await settle();

        await wrapper.get('[data-testid="backup-list-page-upload-key"]').setValue('short');
        await wrapper.get('[data-testid="backup-list-page-upload-submit"]').trigger('click');
        await settle();

        expect(wrapper.text()).toContain('The encryption key must be at least 32 characters.');
        expect(routerMock.post).not.toHaveBeenCalled();

        await wrapper
            .get('[data-testid="backup-list-page-upload-key"]')
            .setValue('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
        await wrapper.get('[data-testid="backup-list-page-upload-submit"]').trigger('click');
        await settle();

        expect(wrapper.text()).toContain('Please enter your password.');
        expect(routerMock.post).not.toHaveBeenCalled();
    });

    it('posts the DR form as multipart and surfaces server errors inline', async () => {
        const wrapper = mountPage();
        await settle();

        const zip = new File(['zip'], 'backup.zip', { type: 'application/zip' });
        const input = wrapper.get('[data-testid="backup-list-page-upload-file-input"]')
            .element as HTMLInputElement;
        Object.defineProperty(input, 'files', { value: [zip] });
        await input.dispatchEvent(new Event('change'));
        await settle();

        await wrapper
            .get('[data-testid="backup-list-page-upload-key"]')
            .setValue('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
        await wrapper
            .get('[data-testid="backup-list-page-upload-password"]')
            .setValue('correct-password');
        await wrapper.get('[data-testid="backup-list-page-upload-submit"]').trigger('click');
        await settle();

        expect(routerMock.post).toHaveBeenCalledWith(
            '/admin/backups/upload',
            expect.any(FormData),
            expect.anything(),
        );
        const body = routerMock.post.mock.calls[0][1] as FormData;
        // jsdom FormData clones appended files — compare the wire fields,
        // not object identity.
        expect((body.get('file') as File).name).toBe('backup.zip');
        expect(body.get('key')).toBe('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
        expect(body.get('password')).toBe('correct-password');

        const options = routerMock.post.mock.calls[0][2] as {
            onError: (errors: Record<string, string>) => void;
        };
        options.onError({ file: 'The encryption key does not match this backup or the archive is corrupt.' });
        await settle();

        expect(wrapper.text()).toContain(
            'The encryption key does not match this backup or the archive is corrupt.',
        );
    });

    it('auto-opens the show-once key flow when encryption_key_prompt is set', async () => {
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        const wrapper = mountPage({ encryption_key_prompt: true });
        await settle();

        // The generation confirmation dialog opens automatically.
        const dialog = wrapper.get('[data-testid="backup-list-page-generate-dialog"]');
        expect(dialog.text()).toContain('A new encryption key will be created');

        fetchMock.mockImplementation((_url: string, init?: RequestInit) => {
            if (init?.method === 'POST') {
                return Promise.resolve(
                    jsonResponse(
                        {
                            data: {
                                id: 5,
                                name: 'initial-key',
                                key_value: 'QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo=',
                                is_active: true,
                                created_at: '2026-08-24T00:00:00+00:00',
                            },
                        },
                        201,
                    ),
                );
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await dialog.get('[data-testid="backup-list-page-generate-dialog-confirm"]').trigger('click');
        await settle();

        // The raw key is shown exactly once in the modal.
        const modal = wrapper.get('[data-testid="backup-list-page-key-modal"]');
        expect(modal.text()).toContain('QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo=');
        expect(modal.text()).toContain('Save this key offline. It will not be shown again.');
    });

    it('copies the generated key and acknowledges the show-once modal', async () => {
        const wrapper = mountPage();
        await settle();

        fetchMock.mockImplementation((url: string, init?: RequestInit) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            if (init?.method === 'POST') {
                return Promise.resolve(
                    jsonResponse(
                        {
                            data: {
                                id: 5,
                                name: 'rotated-2026',
                                key_value: 'QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo=',
                                is_active: true,
                                created_at: '2026-08-24T00:00:00+00:00',
                            },
                        },
                        201,
                    ),
                );
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        await wrapper.get('[data-testid="backup-list-page-keys-generate"]').trigger('click');
        await settle();

        await wrapper
            .get('[data-testid="backup-list-page-generate-dialog-confirm"]')
            .trigger('click');
        await settle();

        const copyButton = wrapper.get('[data-testid="backup-list-page-key-modal-copy"]');
        expect(copyButton.text()).toBe('Copy to clipboard');
        await copyButton.trigger('click');
        await settle();

        expect(
            wrapper.get('[data-testid="backup-list-page-key-modal-copy"]').text(),
        ).toBe('Copied!');

        await wrapper
            .get('[data-testid="backup-list-page-key-modal-acknowledged"]')
            .trigger('click');
        await settle();

        expect(wrapper.find('[data-testid="backup-list-page-key-modal"]').exists()).toBe(false);
    });

    it('refreshes the key counts when the backup list changes (create/delete visits)', async () => {
        let keyFetchCount = 0;
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('completed')));
            }

            keyFetchCount += 1;

            return Promise.resolve(
                jsonResponse({
                    data: keyFetchCount === 1
                        ? [{ id: 3, name: 'rotated-2026', created_at: '2026-07-01T00:00:00+00:00', is_active: true, backups_count: 0 }]
                        : [{ id: 3, name: 'rotated-2026', created_at: '2026-07-01T00:00:00+00:00', is_active: true, backups_count: 1 }],
                }),
            );
        });

        const wrapper = mountPage();
        await settle();

        expect(wrapper.text()).toContain('0 backups');

        // The Inertia visit after a create/delete merges the props — the
        // page component survives, so the keys must be re-fetched.
        await wrapper.setProps({ backups: [COMPLETED_45MB] });
        await settle();

        expect(keyFetchCount).toBe(2);
        expect(wrapper.text()).toContain('1 backup');
    });

    it('deletes a key only when no backups reference it, via the confirmation dialog', async () => {
        fetchMock.mockImplementation((_url: string, init?: RequestInit) => {
            if (init?.method === 'DELETE') {
                return Promise.resolve(jsonResponse(null, 204));
            }

            return Promise.resolve(
                jsonResponse({
                    data: [
                        { id: 3, name: 'rotated-2026', created_at: '2026-07-01T00:00:00+00:00', is_active: true, backups_count: 12 },
                        { id: 4, name: 'initial-key', created_at: '2026-03-15T00:00:00+00:00', is_active: false, backups_count: 0 },
                    ],
                }),
            );
        });

        const wrapper = mountPage();
        await settle();

        // The referenced key's Delete is disabled with the tooltip.
        const referencedDelete = wrapper.get(
            '[data-testid="backup-list-page-keys-key-3-delete"]',
        );
        expect(referencedDelete.attributes('disabled')).toBeDefined();
        expect(referencedDelete.attributes('title')).toBe('12 backups reference this key');

        await wrapper
            .get('[data-testid="backup-list-page-keys-key-4-delete"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="backup-list-page-delete-key-dialog-confirm"]')
            .trigger('click');
        await settle();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/backups/encryption-keys/4',
            expect.objectContaining({ method: 'DELETE' }),
        );

        // The 204 resolves (useJsonApi skips the empty-body parse) — no
        // "Failed to save..." toast, and the key list is re-fetched.
        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(0);
        expect(fetchMock.mock.calls.filter(([url]) => url === '/admin/backups/encryption-keys')).toHaveLength(2);
    });

    it('disables the download for non-completed backups', async () => {
        const wrapper = mountPage({
            backups: [
                {
                    id: 6,
                    filename: 'backup-2026-08-24-020000-k3.zip',
                    size_bytes: 0,
                    status: 'processing',
                    created_at: '2026-08-24T02:00:00+00:00',
                },
            ],
        });
        await settle();

        const download = wrapper.get('[data-testid="backup-list-page-table-download-6"]');
        expect(download.attributes('href')).toBe('/admin/backups/6/download');
        expect(download.attributes('aria-disabled')).toBe('true');
    });

    it('deletes a backup via the confirmation dialog', async () => {
        const wrapper = mountPage({ backups: [COMPLETED_45MB] });
        await settle();

        await wrapper.get('[data-testid="backup-list-page-table-delete-1"]').trigger('click');
        await wrapper
            .get('[data-testid="backup-list-page-delete-backup-dialog-confirm"]')
            .trigger('click');
        await settle();

        expect(routerMock.delete).toHaveBeenCalledWith(
            '/admin/backups/1',
            expect.anything(),
        );
    });
});
