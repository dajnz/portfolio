import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed, ref } from 'vue';

// ProjectsListPage — the shared delete/restore flow (ProjectsListPage.vue
// is the orchestrator). One ConfirmationDialog covers single + bulk soft
// and force deletes; single deletes/restores are Inertia visits (server
// flash toasts), bulk deletes POST through useJsonApi and refetch the
// list via the injected `applyFilters`.
export interface DeleteTarget {
    ids: number[];
    kind: 'soft' | 'force';
}

export function useProjectDeletes(options: {
    applyFilters: () => void;
    clearSelection: () => void;
}) {
    const { t } = useI18n();
    const toast = useToast();
    const { post } = useJsonApi();

    const deleteTarget = ref<DeleteTarget | null>(null);
    const isDeleting = ref(false);

    function requestDelete(row: Record<string, unknown>): void {
        deleteTarget.value = { ids: [Number(row.id)], kind: 'soft' };
    }

    function requestForceDelete(row: Record<string, unknown>): void {
        deleteTarget.value = { ids: [Number(row.id)], kind: 'force' };
    }

    function requestBulkDelete(ids: number[], kind: DeleteTarget['kind']): void {
        deleteTarget.value = { ids, kind };
    }

    function dismissDelete(): void {
        deleteTarget.value = null;
    }

    function confirmDelete(): void {
        const target = deleteTarget.value;

        if (target === null || isDeleting.value) {
            return;
        }

        isDeleting.value = true;

        if (target.ids.length === 1) {
            // Single deletes/restores are Inertia visits — the server
            // redirects back and flashes the success toast.
            const url =
                target.kind === 'force'
                    ? `/admin/projects/${target.ids[0]}/force`
                    : `/admin/projects/${target.ids[0]}`;

            router.delete(url, {
                onFinish: () => {
                    isDeleting.value = false;
                    deleteTarget.value = null;
                },
                onError: () => {
                    isDeleting.value = false;
                    deleteTarget.value = null;
                    toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
                },
            });
            return;
        }

        const url =
            target.kind === 'force'
                ? '/admin/projects/bulk-force-delete'
                : '/admin/projects/bulk-delete';

        post(url, { json: { ids: target.ids } })
            .then(() => {
                isDeleting.value = false;
                deleteTarget.value = null;
                options.clearSelection();
                options.applyFilters();
            })
            .catch(() => {
                isDeleting.value = false;
                deleteTarget.value = null;
                toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
            });
    }

    function restoreItem(row: Record<string, unknown>): void {
        router.patch(`/admin/projects/${Number(row.id)}/restore`, undefined, {
            onError: () => {
                toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
            },
        });
    }

    const deleteDialogTitle = computed(() => {
        if (deleteTarget.value === null) {
            return '';
        }

        const bulk = deleteTarget.value.ids.length > 1;
        const force = deleteTarget.value.kind === 'force';

        if (bulk && force) {
            return t('admin.projects.bulk_force_delete_confirm');
        }

        if (bulk) {
            return t('admin.projects.bulk_delete_confirm');
        }

        return force
            ? t('admin.projects.force_delete_confirm')
            : t('admin.projects.delete_confirm');
    });

    return {
        deleteTarget,
        isDeleting,
        deleteDialogTitle,
        requestDelete,
        requestForceDelete,
        requestBulkDelete,
        dismissDelete,
        confirmDelete,
        restoreItem,
    };
}
