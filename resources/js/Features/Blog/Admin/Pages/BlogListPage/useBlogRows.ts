import type { ArticleTableRow, UnifiedTagChip } from '@blog/Admin/Types/article';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed, type Ref, ref, watch } from 'vue';

// BlogListPage — the row data and its mutations (BlogListPage.vue is the
// orchestrator). Owns the server-seeded working copies, the row selection,
// the bulk-action dispatch and the inline quick action (the
// publish/unpublish PATCH — JSON with optimistic updates + revert, the
// `{data}` row responses of the backend). The filter state lives in
// `useBlogFilters`; bulk deletes are delegated to the injected
// `requestBulkDelete` (the delete flows live in `useBlogDeletes`). The
// Related column is a static count — per-row related management lives on
// the form page only.
export function useBlogRows(options: {
    articles: () => ArticleTableRow[];
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
    const rows = ref<ArticleTableRow[]>(options.articles().map((row) => ({ ...row })));

    watch(
        () => options.articles(),
        (next) => {
            rows.value = next.map((row) => ({ ...row }));
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
        { key: 'title', label: t('common.title') },
        { key: 'tags', label: t('admin.blog.tags') },
        { key: 'related', label: t('admin.blog.related'), align: 'center' as const },
        {
            key: 'is_published',
            label: t('admin.blog.published'),
            width: '150px',
            align: 'center' as const,
        },
        { key: 'views', label: t('admin.blog.views'), width: '96px', align: 'center' as const },
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
                void bulkMutation('/admin/blog/bulk-publish', ids);
                break;
            case 'unpublish':
                void bulkMutation('/admin/blog/bulk-unpublish', ids);
                break;
            case 'delete':
                options.requestBulkDelete(ids, 'soft');
                break;
            case 'restore':
                void bulkMutation('/admin/blog/bulk-restore', ids);
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

    function replaceRow(updated: ArticleTableRow): void {
        const index = rows.value.findIndex((row) => row.id === updated.id);

        if (index !== -1) {
            rows.value[index] = { ...updated };
        }
    }

    async function togglePublication(row: ArticleTableRow): Promise<void> {
        const target = !row.is_published;
        const previous = { ...row };

        rows.value = rows.value.map((item) =>
            item.id === row.id ? { ...item, is_published: target } : item,
        );

        try {
            const response = await patch<{ data: ArticleTableRow }>(
                `/admin/blog/${row.id}/${target ? 'publish' : 'unpublish'}`,
            );
            replaceRow(response.data);
        } catch {
            replaceRow(previous);
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        }
    }

    return {
        rows,
        selectedIds,
        trashedIds,
        tableRows,
        columns,
        bulkActions,
        onSelectedIdsChange,
        onBulkAction,
        onBulkClear,
        togglePublication,
    };
}

export function tagChipVariant(tag: UnifiedTagChip): 'primary' | 'default' {
    return tag.type === 'skill' ? 'primary' : 'default';
}
