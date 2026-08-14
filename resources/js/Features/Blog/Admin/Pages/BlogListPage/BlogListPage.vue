<!-- BlogListPage (admin-section-ui.md:387-403, admin-section-components.md:1169-1201, blog.html) — the cursor-paginated blog management list: the page orchestrator wiring the composables (useBlogFilters — URL-synced filters; useBlogRows — row data, selection, bulk + the publish/unpublish quick action; useBlogDeletes — the delete/restore dialog flow) into the template: the filters bar (BlogFiltersBar), the TABLE view (BlogTableView — the selectable DataTable with the row click opening the edit form, the state-styled publish toggle, the static Related count, the max-3-tags chips + "+N" and the small lead-image preview), the GRID view (BlogGridView — BlogGridCard cards with lead images + the bulk action bar per view) and the ConfirmationDialog deletes. Quick actions talk to the JSON endpoints (`{data}` row responses) via useJsonApi with optimistic updates + revert; bulk + single deletes are the projects-list flows (Inertia visits with server flash toasts for singles). -->
<script setup lang="ts">
import type { AdminListMeta, ArticleTableRow } from '@blog/Admin/Types/article';
import { router, usePage } from '@inertiajs/vue3';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { ref } from 'vue';

import BlogFiltersBar from './BlogFiltersBar.vue';
import BlogGridView from './BlogGridView.vue';
import BlogTableView from './BlogTableView.vue';
import { useBlogDeletes } from './useBlogDeletes';
import { useBlogFilters } from './useBlogFilters';
import { useBlogRows } from './useBlogRows';

const props = withDefaults(
    defineProps<{
        articles?: ArticleTableRow[];
        meta?: AdminListMeta;
        tags?: Array<{ id: number; slug: string; name: string }>;
        years?: number[];
        test_id?: string;
    }>(),
    {
        articles: () => [],
        meta: undefined,
        tags: () => [],
        years: () => [],
        test_id: 'blog-list-page',
    },
);

const { t } = useI18n();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

// ── Composable wiring ──
const {
    filterTags,
    publishedFilter,
    yearFilter,
    showTrashed,
    viewMode,
    hasActiveFilters,
    onAutocompleteSubmit,
    onFilterTagsChange,
    onPublishedFilterChange,
    onYearFilterChange,
    onTrashedChange,
    clearFilters,
    navigate,
} = useBlogFilters();

const selectedIds = ref<Array<number | string>>([]);

const {
    requestDelete,
    requestForceDelete,
    requestBulkDelete,
    dismissDelete,
    confirmDelete,
    restoreItem,
    deleteTarget,
    isDeleting,
    deleteDialogTitle,
} = useBlogDeletes({
    applyFilters: clearFilters,
    clearSelection: () => {
        selectedIds.value = [];
    },
});

const {
    rows,
    trashedIds,
    tableRows,
    columns,
    bulkActions,
    onSelectedIdsChange,
    onBulkAction,
    onBulkClear,
    togglePublication,
} = useBlogRows({
    articles: () => props.articles,
    showTrashed,
    selectedIds,
    applyFilters: clearFilters,
    requestBulkDelete,
});

function openCreate(): void {
    router.visit('/admin/blog/create');
}

function openEdit(row: Record<string, unknown>): void {
    router.visit(`/admin/blog/${Number(row.id)}/edit`);
}

function emptyAction(): void {
    if (hasActiveFilters.value) {
        clearFilters();
    } else {
        openCreate();
    }
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/blog'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-6xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.blog.title') }}
      </h1>

      <BlogFiltersBar
        :view_mode="viewMode"
        :filter_tags="filterTags"
        :published_filter="publishedFilter"
        :year_filter="yearFilter"
        :years="props.years"
        :show_trashed="showTrashed"
        :test_id="props.test_id"
        @update:view_mode="(mode) => (viewMode = mode)"
        @search-submit="onAutocompleteSubmit"
        @update:filter_tags="onFilterTagsChange"
        @update:published_filter="onPublishedFilterChange"
        @update:year_filter="onYearFilterChange"
        @update:show_trashed="onTrashedChange"
        @create="openCreate"
      />

      <!-- Grid view variant — the same rows as article cards (title +
           tags + views + publish toggle + delete); not persisted (the
           toggle defaults to table each visit). -->
      <BlogGridView
        v-if="viewMode === 'grid'"
        :rows="rows"
        :bulk_actions="bulkActions"
        :selected_ids="selectedIds"
        :show_trashed="showTrashed"
        :has_active_filters="hasActiveFilters"
        :test_id="props.test_id"
        @bulk-action="onBulkAction"
        @bulk-clear="onBulkClear"
        @edit="openEdit"
        @toggle-publication="togglePublication"
        @request-delete="requestDelete"
        @request-force-delete="requestForceDelete"
        @restore="restoreItem"
        @empty-action="emptyAction"
      />

      <!-- Table view variant -->
      <BlogTableView
        v-else
        :columns="columns"
        :rows="tableRows"
        :selected_ids="selectedIds"
        :trashed_ids="trashedIds"
        :bulk_actions="bulkActions"
        :show_trashed="showTrashed"
        :has_active_filters="hasActiveFilters"
        :test_id="props.test_id"
        @update:selected_ids="onSelectedIdsChange"
        @empty-action="emptyAction"
        @row-click="openEdit"
        @toggle-publication="togglePublication"
        @request-delete="requestDelete"
        @request-force-delete="requestForceDelete"
        @restore="restoreItem"
        @bulk-action="onBulkAction"
        @bulk-clear="onBulkClear"
      />

      <PaginationControls
        v-if="props.meta !== undefined"
        :total="props.meta.total"
        :per_page="props.meta.per_page"
        :current_page="props.meta.current_page"
        :cursors="props.meta.cursors"
        :sort_column="props.meta.sort_column"
        :sort_direction="props.meta.sort_direction"
        :test_id="`${props.test_id}-pagination`"
        @navigate="(payload) => navigate(payload, props.meta?.cursors)"
      />

      <ConfirmationDialog
        :model-value="deleteTarget !== null"
        :title="deleteDialogTitle"
        :confirm_label="t('common.delete')"
        :is_loading="isDeleting"
        :test_id="`${props.test_id}-delete-dialog`"
        @update:model-value="(open) => {
          if (!open) dismissDelete();
        }"
        @confirm="confirmDelete"
      />
    </section>
  </AdminPageLayout>
</template>
