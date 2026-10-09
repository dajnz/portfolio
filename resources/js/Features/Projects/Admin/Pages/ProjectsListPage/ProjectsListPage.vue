<!-- ProjectsListPage (admin-section-ui.md:327-346, admin-section-components.md:957-991, projects.html) — the cursor-paginated projects management list: the page orchestrator wiring the composables (useProjectFilters — URL-synced filters; useProjectRows — row data, selection, bulk + quick actions; useProjectDeletes — the delete/restore dialog flow) into the template: the filters bar (ProjectFiltersBar), the selectable DataTable with the inline quick actions (feature star, tag chips + inline single-select picker, publish/unpublish), the BulkActionBar per view (publish/unpublish/delete vs restore/force-delete), the featured-projects reorder section (ProjectFeaturedReorder) and ConfirmationDialog deletes. Quick actions talk to the JSON endpoints (ADR-0004 — `{data}` row responses) via useJsonApi with optimistic updates + revert; bulk + reorder refetch/rollback the server props; single deletes/restores are Inertia visits (server flash toasts). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { router, usePage } from '@inertiajs/vue3';
import ProjectGridCard from '@projects-admin/ProjectGridCard.vue';
import TechTagList from '@projects-admin/TechTagList.vue';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import BulkActionBar from '@shared-admin/BulkActionBar.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { Cursor } from '@shared-cross/Utils/cursorNavigationParams';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, ref } from 'vue';

import ProjectFeaturedReorder, { type FeaturedRow } from './ProjectFeaturedReorder.vue';
import ProjectFiltersBar from './ProjectFiltersBar.vue';
import { useProjectDeletes } from './useProjectDeletes';
import { useProjectFilters } from './useProjectFilters';
import { type ProjectRow, useProjectRows } from './useProjectRows';

interface TechTag {
    id: number;
    name: string;
    icon: string | null;
}

interface ListMeta {
    total: number;
    per_page: number;
    current_page: number;
    sort_column: string;
    sort_direction: 'asc' | 'desc';
    cursors: { first: Cursor; last: Cursor; page: number };
}

const props = withDefaults(
    defineProps<{
        projects?: ProjectRow[];
        featured_projects?: ProjectRow[];
        meta?: ListMeta;
        test_id?: string;
    }>(),
    {
        projects: () => [],
        featured_projects: () => [],
        meta: undefined,
        test_id: 'projects-list-page',
    },
);

const { t } = useI18n();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

// ── Composable wiring ──
const {
    featuredOnly,
    filterTags,
    publishedFilter,
    showTrashed,
    viewMode,
    hasActiveFilters,
    onAutocompleteSubmit,
    onFeaturedOnlyChange,
    onFilterTagsChange,
    onPublishedFilterChange,
    onTrashedChange,
    clearFilters,
    navigate,
} = useProjectFilters();

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
} = useProjectDeletes({
    applyFilters: clearFilters,
    clearSelection: () => {
        selectedIds.value = [];
    },
});

const {
    rows,
    featuredRows,
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
} = useProjectRows({
    projects: () => props.projects,
    featuredProjects: () => props.featured_projects,
    showTrashed,
    selectedIds,
    applyFilters: clearFilters,
    requestBulkDelete,
});

const isLiveView = computed(() => !showTrashed.value);

function openCreate(): void {
    router.visit('/admin/projects/create');
}

function openEdit(row: Record<string, unknown>): void {
    router.visit(`/admin/projects/${Number(row.id)}/edit`);
}

function previewUrl(row: ProjectRow): string {
    return `/assets/${row.preview_image ?? ''}`;
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/projects'"
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
        {{ t('admin.projects.title') }}
      </h1>

      <ProjectFiltersBar
        :view_mode="viewMode"
        :featured_only="featuredOnly"
        :filter_tags="filterTags"
        :published_filter="publishedFilter"
        :show_trashed="showTrashed"
        :test_id="props.test_id"
        @update:view_mode="(mode) => (viewMode = mode)"
        @search-submit="onAutocompleteSubmit"
        @update:featured_only="onFeaturedOnlyChange"
        @update:filter_tags="onFilterTagsChange"
        @update:published_filter="onPublishedFilterChange"
        @update:show_trashed="onTrashedChange"
        @create="openCreate"
      />

      <!-- Grid view variant — the same rows as project cards (preview
           image + title + tags + publish toggle); not persisted (the
           toggle defaults to table each visit). -->
      <template v-if="viewMode === 'grid'">
        <BulkActionBar
          v-if="selectedIds.length > 0"
          :selected_count="selectedIds.length"
          :actions="bulkActions"
          :test_id="`${props.test_id}-bulk-bar`"
          @action="onBulkAction"
          @clear="onBulkClear"
        />
        <div
          v-if="rows.length > 0"
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
          :data-testid="`${props.test_id}-grid`"
        >
          <ProjectGridCard
            v-for="row in rows"
            :key="row.id"
            :row="row"
            :trashed="showTrashed"
            :test_id="`${props.test_id}-grid-card-${row.id}`"
            @edit="openEdit"
            @toggle-featured="(item) => toggleFeatured(item as ProjectRow)"
            @toggle-publication="(item) => togglePublication(item as ProjectRow)"
            @delete="requestDelete"
            @force-delete="requestForceDelete"
            @restore="restoreItem"
          />
        </div>
        <AdminEmptyState
          v-else
          :mode="hasActiveFilters ? 'no-results' : 'empty'"
          :message="
            hasActiveFilters ? t('admin.projects.no_projects_match') : t('admin.projects.no_projects')
          "
          :action_label="hasActiveFilters ? t('common.clear_filters') : t('admin.projects.add')"
          :test_id="`${props.test_id}-grid-empty`"
          @action="hasActiveFilters ? clearFilters() : openCreate()"
        />
      </template>

      <!-- Table view variant -->
      <template v-else>
        <DataTable
          :columns="columns"
          :rows="tableRows"
          :sort_column="'id'"
          :sort_direction="'desc'"
          :selectable="true"
          :selected_ids="selectedIds"
          :trashed_ids="trashedIds"
          :center_actions="true"
          :empty_mode="hasActiveFilters ? 'no-results' : 'empty'"
          :empty_message="
            hasActiveFilters ? t('admin.projects.no_projects_match') : t('admin.projects.no_projects')
          "
          :empty_action_label="hasActiveFilters ? t('common.clear_filters') : t('admin.projects.add')"
          :test_id="`${props.test_id}-table`"
          @update:selected_ids="onSelectedIdsChange"
          @empty-action="hasActiveFilters ? clearFilters() : openCreate()"
          @row-click="openEdit"
        >
          <template #bulk-actions>
            <BulkActionBar
              :selected_count="selectedIds.length"
              :actions="bulkActions"
              :test_id="`${props.test_id}-bulk-bar`"
              @action="onBulkAction"
              @clear="onBulkClear"
            />
          </template>
          <template #cell-preview_image="{ row }">
            <img
              v-if="row.preview_image"
              :src="previewUrl(row as unknown as ProjectRow)"
              alt=""
              class="h-10 w-16 rounded-md border border-slate-200 object-cover dark:border-slate-700"
              :data-testid="`${props.test_id}-preview-${String(row.id)}`"
            >
            <span
              v-else
              class="inline-flex h-10 w-16 items-center justify-center rounded-md border border-dashed border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600"
              aria-hidden="true"
              :data-testid="`${props.test_id}-preview-placeholder-${String(row.id)}`"
            >
              <Icon
                icon="heroicons:photo"
                class="h-5 w-5"
              />
            </span>
          </template>
          <template #cell-title="{ row }">
            <span class="font-semibold text-slate-900 dark:text-slate-100">
              {{ row.title }}
            </span>
          </template>
          <template #cell-tech_tags="{ row }">
            <!-- Static chips only (icons + one-line ellipsis) — tag
                 editing lives on the form page (user request
                 2026-08-12). -->
            <TechTagList
              :tags="(row.tech_tags as TechTag[])"
              :test_id="`${props.test_id}-tags-${String(row.id)}`"
            />
          </template>
          <template #cell-is_featured="{ row }">
            <div
              class="flex justify-center"
              @click.stop
            >
              <AdminBaseButton
                v-if="isLiveView"
                type="button"
                variant="icon-only"
                :icon="row.is_featured === true ? 'heroicons:star-solid' : 'heroicons:star'"
                :class="row.is_featured === true ? 'text-warning-500' : 'text-slate-300 dark:text-slate-600'"
                :aria-label="
                  row.is_featured === true
                    ? t('admin.projects.unfeature_project')
                    : t('admin.projects.feature_project')
                "
                :aria-pressed="row.is_featured === true"
                :test_id="`${props.test_id}-feature-${String(row.id)}`"
                @click="toggleFeatured(row as unknown as ProjectRow)"
              />
            </div>
          </template>
          <template #cell-is_published="{ row }">
            <div @click.stop>
              <AdminBaseBadge
                v-if="showTrashed"
                variant="default"
                :test_id="`${props.test_id}-badge-trashed-${String(row.id)}`"
              >
                {{ t('admin.statuses.trashed') }}
              </AdminBaseBadge>
              <!-- The published cell is an active control (like the
                   feature star): a state-styled button whose label is
                   the action — Publish on drafts, Unpublish on live
                   projects (user request 2026-08-12). -->
              <AdminBaseButton
                v-else
                type="button"
                variant="secondary"
                size="sm"
                :class="
                  row.is_published === true
                    ? 'bg-success-600! text-white! border-success-600! hover:bg-success-700! dark:border-success-600!'
                    : 'border-primary-600! text-primary-600! hover:bg-primary-600! hover:text-white!'
                "
                :aria-pressed="row.is_published === true"
                :test_id="`${props.test_id}-publish-toggle-${String(row.id)}`"
                @click="togglePublication(row as unknown as ProjectRow)"
              >
                {{
                  row.is_published === true
                    ? t('admin.lists.unpublish')
                    : t('admin.lists.publish')
                }}
              </AdminBaseButton>
            </div>
          </template>
          <template #row-actions="{ row }">
            <!-- @click.stop — the actions must not bubble into the
                 row's edit-navigation click handler. -->
            <div
              class="flex items-center gap-1"
              @click.stop
            >
              <template v-if="showTrashed">
                <AdminBaseButton
                  type="button"
                  variant="icon-outline-danger"
                  icon="heroicons:arrow-path"
                  :aria-label="t('admin.lists.restore')"
                  :test_id="`${props.test_id}-restore-${String(row.id)}`"
                  @click="restoreItem(row)"
                />
                <AdminBaseButton
                  type="button"
                  variant="icon-outline-danger"
                  icon="heroicons:trash"
                  :aria-label="t('admin.lists.delete_permanently')"
                  :test_id="`${props.test_id}-force-delete-${String(row.id)}`"
                  @click="requestForceDelete(row)"
                />
              </template>
              <AdminBaseButton
                v-else
                type="button"
                variant="icon-outline-danger"
                icon="heroicons:trash"
                :aria-label="t('common.delete')"
                :test_id="`${props.test_id}-delete-${String(row.id)}`"
                @click="requestDelete(row)"
              />
            </div>
          </template>
        </DataTable>
      </template>

      <!-- Featured projects reorder — SortableList in a separate
           section (drag reorder → POST /admin/projects/reorder-featured
           with the zero-indexed {ids}, ADR-0005). Only in the live view. -->
      <ProjectFeaturedReorder
        v-if="isLiveView && featuredRows.length > 0"
        :items="featuredRows as unknown as FeaturedRow[]"
        :test_id="props.test_id"
        @reorder="onReorderFeatured"
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
