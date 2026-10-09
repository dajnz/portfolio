import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed, type Ref, ref, watch } from 'vue';

// ProjectsListPage — the row data and its mutations (ProjectsListPage.vue
// is the orchestrator). Owns the server-seeded working copies (rows +
// featured rows), the row selection, the bulk-action dispatch and the
// inline quick actions (feature star, publish/unpublish — optimistic with
// revert). The filter state lives in `useProjectFilters`; bulk
// deletes are delegated to the injected `requestBulkDelete` (the delete
// flows live in `useProjectDeletes`).
export interface ProjectRow {
    id: number;
    title: string;
    slug: string;
    preview_image: string | null;
    is_published: boolean;
    is_featured: boolean;
    featured_sort_order: number | null;
    tech_tags_count: number;
    tech_tags: Array<{ id: number; name: string; icon: string | null }>;
    created_at: string;
    updated_at: string;
}

export function useProjectRows(options: {
    projects: () => ProjectRow[];
    featuredProjects: () => ProjectRow[];
    showTrashed: Ref<boolean>;
    selectedIds: Ref<Array<number | string>>;
    applyFilters: () => void;
    requestBulkDelete: (ids: number[], kind: 'soft' | 'force') => void;
}) {
    const { t } = useI18n();
    const toast = useToast();
    const { post, patch } = useJsonApi();

    // Local working copies — quick actions update them optimistically and
    // revert on server errors; Inertia prop refreshes re-seed them.
    const rows = ref<ProjectRow[]>(options.projects().map((row) => ({ ...row })));
    const featuredRows = ref<ProjectRow[]>(options.featuredProjects().map((row) => ({ ...row })));

    watch(
        () => options.projects(),
        (next) => {
            rows.value = next.map((row) => ({ ...row }));
        },
    );
    watch(
        () => options.featuredProjects(),
        (next) => {
            featuredRows.value = next.map((row) => ({ ...row }));
        },
    );

    const selectedIds = options.selectedIds;

    const trashedIds = computed<Array<number | string>>(() =>
        options.showTrashed.value ? rows.value.map((row) => row.id) : [],
    );

    const tableRows = computed<Array<Record<string, unknown>>>(() =>
        rows.value.map((row) => ({ ...row })),
    );

    const columns = computed(() => [
        { key: 'preview_image', label: t('admin.projects.preview'), width: '112px' },
        { key: 'title', label: t('common.title') },
        { key: 'tech_tags', label: t('admin.projects.tech_tags') },
        { key: 'is_featured', label: t('admin.projects.featured'), width: '88px' },
        { key: 'is_published', label: t('admin.projects.published'), width: '112px' },
    ]);

    const bulkActions = computed(() =>
        options.showTrashed.value
            ? [
                  {
                      label: t('admin.lists.restore'),
                      key: 'restore',
                      variant: 'primary' as const,
                  },
                  {
                      label: t('admin.lists.delete_permanently'),
                      key: 'force-delete',
                      variant: 'danger' as const,
                  },
              ]
            : [
                  { label: t('admin.lists.publish'), key: 'publish', variant: 'primary' as const },
                  {
                      label: t('admin.lists.unpublish'),
                      key: 'unpublish',
                      variant: 'secondary' as const,
                  },
                  { label: t('common.delete'), key: 'delete', variant: 'danger' as const },
              ],
    );

    function onSelectedIdsChange(ids: Array<number | string>): void {
        selectedIds.value = ids;
    }

    function onBulkAction(key: string): void {
        const ids = selectedIds.value.map(Number);

        switch (key) {
            case 'publish':
                void bulkMutation('/admin/projects/bulk-publish', ids);
                break;
            case 'unpublish':
                void bulkMutation('/admin/projects/bulk-unpublish', ids);
                break;
            case 'delete':
                options.requestBulkDelete(ids, 'soft');
                break;
            case 'restore':
                void bulkMutation('/admin/projects/bulk-restore', ids);
                break;
            case 'force-delete':
                options.requestBulkDelete(ids, 'force');
                break;
        }
    }

    function onBulkClear(): void {
        selectedIds.value = [];
    }

    async function bulkMutation(url: string, ids: number[]): Promise<void> {
        try {
            await post(url, { json: { ids } });
            selectedIds.value = [];
            options.applyFilters();
        } catch {
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        }
    }

    function replaceRow(updated: ProjectRow): void {
        const index = rows.value.findIndex((row) => row.id === updated.id);

        if (index !== -1) {
            rows.value[index] = { ...updated };
        }
    }

    async function toggleFeatured(row: ProjectRow): Promise<void> {
        const target = !row.is_featured;
        const previous = { ...row };

        rows.value = rows.value.map((item) =>
            item.id === row.id ? { ...item, is_featured: target } : item,
        );

        try {
            const response = await patch<{ data: ProjectRow }>(
                `/admin/projects/${row.id}/${target ? 'feature' : 'unfeature'}`,
            );
            replaceRow(response.data);

            // The featured reorder section follows the star — a newly
            // featured project joins the list (at its server-assigned
            // order), an unfeatured one leaves it.
            if (target) {
                if (!featuredRows.value.some((item) => item.id === row.id)) {
                    featuredRows.value = [...featuredRows.value, response.data];
                }
            } else {
                featuredRows.value = featuredRows.value.filter((item) => item.id !== row.id);
            }
        } catch {
            replaceRow(previous);
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        }
    }

    async function togglePublication(row: ProjectRow): Promise<void> {
        const target = !row.is_published;
        const previous = { ...row };

        rows.value = rows.value.map((item) =>
            item.id === row.id ? { ...item, is_published: target } : item,
        );

        try {
            const response = await patch<{ data: ProjectRow }>(
                `/admin/projects/${row.id}/${target ? 'publish' : 'unpublish'}`,
            );
            replaceRow(response.data);
        } catch {
            replaceRow(previous);
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        }
    }

    function onReorderFeatured(ids: number[]): void {
        post('/admin/projects/reorder-featured', { json: { ids } }).catch(() => {
            // Roll back to the server order on failure — the props still
            // hold the last confirmed order.
            featuredRows.value = options.featuredProjects().map((row) => ({ ...row }));
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        });
    }

    return {
        rows,
        featuredRows,
        selectedIds,
        trashedIds,
        tableRows,
        columns,
        bulkActions,
        onSelectedIdsChange,
        onBulkAction,
        onBulkClear,
        toggleFeatured,
        togglePublication,
        onReorderFeatured,
    };
}
