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

import BackupListPage from '@backup/Admin/Pages/BackupListPage.vue';
import RestoreForm from '@backup/Admin/Components/RestoreForm.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

// Wire fixtures mirroring the OpenAPI BackupListPageProps shape
// (docs/openapi/inertia/backup.yaml).
const COMPLETED_45MB = {
    id: 1,
    filename: 'backup-2026-08-01-020000-k3.zip',
    size_bytes: 47185920,
    status: 'completed',
    created_at: '2026-08-01T02:00:00+00:00',
};

const COMPLETED_1MB = {
    id: 2,
    filename: 'backup-2026-07-01-020000-k3.zip',
    size_bytes: 1048576,
    status: 'completed',
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

    it('renders the page title, derived stats, table rows and both restore forms', async () => {
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

        // The DataTable rows with status badges.
        const rows = wrapper.findAll('[data-testid="backup-list-page-table"] tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('backup-2026-08-01-020000-k3.zip');
        expect(rows[0].text()).toContain('45 MB');
        expect(rows[0].text()).toContain('Completed');

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

        expect(routerMock.reload).toHaveBeenCalled();
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

    it('submits the restore with the selected backup and password, then polls to success', async () => {
        const wrapper = mountPage({
            backups: [COMPLETED_45MB],
            meta: { current_page: 1, from: 1, to: 1, last_page: 1, per_page: 12, total: 1 },
        });
        await settle();

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

        expect(routerMock.post).toHaveBeenCalledWith(
            '/admin/backups/restore',
            { backup_id: 1, password: 'correct-password' },
            expect.anything(),
        );

        // The redirect lands (worker-lag: the row still completed) — the
        // page polls the submitted record; processing → completed fires
        // the B36 success toast and reloads.
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

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Restore completed successfully.',
            variant: 'success',
        });
        expect(routerMock.reload).toHaveBeenCalled();
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

    it('restores a processing backup-import row with the DR success toast', async () => {
        fetchMock.mockImplementation((url: string) => {
            if (url.includes('/status')) {
                return Promise.resolve(jsonResponse(statusPayload('processing')));
            }

            return Promise.resolve(jsonResponse({ data: [] }));
        });

        mountPage({
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

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Backup restored successfully. The imported encryption key has been saved.',
            variant: 'success',
        });
        expect(routerMock.reload).toHaveBeenCalled();
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
        await settle();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/backups/encryption-keys/4',
            expect.objectContaining({ method: 'DELETE' }),
        );
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
