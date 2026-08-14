<!-- BlogFiltersBar — the sticky filters bar of the blog list
     (view toggle + search + New Article in the upper row; the unified
     tag filter, published status, year and Show-trashed in the lower
     row). Pure value+emit contract — the page orchestrator applies
     the filters. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBlogTagPicker from '@shared-admin/AdminBlogTagPicker.vue';
import AdminSearchAutocomplete from '@shared-admin/AdminSearchAutocomplete.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

export type BlogViewMode = 'table' | 'grid';
export type BlogPublishedFilter = 'all' | 'published' | 'unpublished';

/** The unified picker's tag shape (article tags + skills). */
export interface BlogTagFilter {
    id: number;
    name: string;
    type: 'tag' | 'skill';
}

const props = withDefaults(
    defineProps<{
        view_mode: BlogViewMode;
        filter_tags?: BlogTagFilter[];
        published_filter?: BlogPublishedFilter;
        year_filter?: string;
        years?: number[];
        show_trashed?: boolean;
        test_id?: string;
    }>(),
    {
        filter_tags: () => [],
        published_filter: 'all',
        year_filter: '',
        years: () => [],
        show_trashed: false,
        test_id: 'blog-filters-bar',
    },
);

const emit = defineEmits<{
    'update:view_mode': [mode: BlogViewMode];
    'search-submit': [query: string];
    'update:filter_tags': [tags: BlogTagFilter[]];
    'update:published_filter': [value: BlogPublishedFilter];
    'update:year_filter': [value: string];
    'update:show_trashed': [checked: boolean];
    create: [];
}>();

const { t } = useI18n();

// The year filter's explicit "All years" option (value '') — selecting
// it after a year was picked resets the filter (user request
// 2026-08-13); without the option the dropdown could never return to
// the unfiltered state.
const yearOptions = computed(() => [
    { value: '', label: t('admin.lists.all_years') },
    ...props.years.map((year) => ({ value: String(year), label: String(year) })),
]);

function onPublishedChange(value: string | string[]): void {
    emit(
        'update:published_filter',
        (Array.isArray(value) ? value[0] : value) as BlogPublishedFilter,
    );
}

function onYearChange(value: string | string[]): void {
    emit('update:year_filter', Array.isArray(value) ? (value[0] ?? '') : value);
}
</script>

<template>
  <AdminStickyActionBar :test_id="`${props.test_id}-filters-bar`">
    <div
      class="flex w-full flex-col gap-3"
      :data-testid="`${props.test_id}-filters`"
    >
      <div
        class="flex items-center gap-3"
        :data-testid="`${props.test_id}-filters-row-1`"
      >
        <div
          class="flex overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
          role="group"
          :aria-label="t('admin.blog.view_mode')"
          :data-testid="`${props.test_id}-view-toggle`"
        >
          <AdminBaseButton
            type="button"
            :variant="props.view_mode === 'table' ? 'primary' : 'ghost'"
            icon="heroicons:list-bullet"
            :aria-label="t('admin.blog.table_view')"
            :aria-pressed="props.view_mode === 'table'"
            :test_id="`${props.test_id}-table-view`"
            @click="emit('update:view_mode', 'table')"
          />
          <AdminBaseButton
            type="button"
            :variant="props.view_mode === 'grid' ? 'primary' : 'ghost'"
            icon="heroicons:squares-2x2"
            :aria-label="t('admin.blog.grid_view')"
            :aria-pressed="props.view_mode === 'grid'"
            :test_id="`${props.test_id}-grid-view`"
            @click="emit('update:view_mode', 'grid')"
          />
        </div>
        <div class="min-w-0 flex-1">
          <AdminSearchAutocomplete
            :fetch_url="'/admin/blog'"
            :result_url_prefix="'/admin/blog/'"
            :placeholder="t('admin.blog.search_placeholder')"
            :test_id="`${props.test_id}-search`"
            @submit="(query) => emit('search-submit', query)"
          />
        </div>
        <AdminBaseButton
          variant="primary"
          icon="heroicons:plus"
          icon_position="left"
          :test_id="`${props.test_id}-add`"
          @click="emit('create')"
        >
          {{ t('admin.blog.add') }}
        </AdminBaseButton>
      </div>
      <div
        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2"
        :data-testid="`${props.test_id}-filters-row-2`"
      >
        <AdminBlogTagPicker
          :model-value="props.filter_tags"
          :placeholder="t('admin.blog.filter_by_tags')"
          width="500px"
          :test_id="`${props.test_id}-tag-filter`"
          @update:model-value="(tags) => emit('update:filter_tags', tags)"
        />
        <AdminBaseDropdown
          :model-value="props.published_filter"
          :options="[
            { value: 'all', label: t('admin.lists.all_statuses') },
            { value: 'published', label: t('admin.blog.published') },
            { value: 'unpublished', label: t('admin.statuses.unpublished') },
          ]"
          width="150px"
          :test_id="`${props.test_id}-published-filter`"
          @update:model-value="onPublishedChange"
        />
        <AdminBaseDropdown
          :model-value="props.year_filter"
          :options="yearOptions"
          :placeholder="t('admin.lists.all_years')"
          width="120px"
          :test_id="`${props.test_id}-year-filter`"
          @update:model-value="onYearChange"
        />
        <AdminBaseCheckbox
          :model-value="props.show_trashed"
          :label="t('admin.lists.show_trashed')"
          :test_id="`${props.test_id}-trashed`"
          @update:model-value="(checked) => emit('update:show_trashed', checked)"
        />
      </div>
    </div>
  </AdminStickyActionBar>
</template>
