<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminSearchAutocomplete from '@shared-admin/AdminSearchAutocomplete.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import AdminTechSkillPicker from '@shared-admin/AdminTechSkillPicker.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

export type ProjectViewMode = 'table' | 'grid';
export type ProjectPublishedFilter = 'all' | 'published' | 'unpublished';

/** The picker's tag shape — the wire icon is normalized to a string. */
export interface PickerTag {
    id: number;
    name: string;
    icon: string;
}

const props = withDefaults(
    defineProps<{
        view_mode: ProjectViewMode;
        featured_only?: boolean;
        filter_tags?: PickerTag[];
        published_filter?: ProjectPublishedFilter;
        show_trashed?: boolean;
        test_id?: string;
    }>(),
    {
        featured_only: false,
        filter_tags: () => [],
        published_filter: 'all',
        show_trashed: false,
        test_id: 'project-filters-bar',
    },
);

const emit = defineEmits<{
    'update:view_mode': [mode: ProjectViewMode];
    'search-submit': [query: string];
    'update:featured_only': [checked: boolean];
    'update:filter_tags': [tags: PickerTag[]];
    'update:published_filter': [value: ProjectPublishedFilter];
    'update:show_trashed': [checked: boolean];
    create: [];
}>();

const { t } = useI18n();

function onPublishedChange(value: string | string[]): void {
    emit(
        'update:published_filter',
        (Array.isArray(value) ? value[0] : value) as ProjectPublishedFilter,
    );
}
</script>

<template>
  <AdminStickyActionBar :test_id="`${props.test_id}-filters-bar`">
    <div
      class="flex w-full flex-col gap-3"
      :data-testid="`${props.test_id}-filters`"
    >
      <div
        class="flex flex-wrap items-center gap-3"
        :data-testid="`${props.test_id}-filters-row-1`"
      >
        <div
          class="flex overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
          role="group"
          :aria-label="t('admin.projects.view_mode')"
          :data-testid="`${props.test_id}-view-toggle`"
        >
          <AdminBaseButton
            type="button"
            :variant="props.view_mode === 'table' ? 'primary' : 'ghost'"
            icon="heroicons:list-bullet"
            :aria-label="t('admin.projects.table_view')"
            :aria-pressed="props.view_mode === 'table'"
            :test_id="`${props.test_id}-table-view`"
            @click="emit('update:view_mode', 'table')"
          />
          <AdminBaseButton
            type="button"
            :variant="props.view_mode === 'grid' ? 'primary' : 'ghost'"
            icon="heroicons:squares-2x2"
            :aria-label="t('admin.projects.grid_view')"
            :aria-pressed="props.view_mode === 'grid'"
            :test_id="`${props.test_id}-grid-view`"
            @click="emit('update:view_mode', 'grid')"
          />
        </div>
        <div class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
          <AdminSearchAutocomplete
            :fetch_url="'/admin/projects'"
            :result_url_prefix="'/admin/projects/'"
            :placeholder="t('admin.projects.search_placeholder')"
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
          {{ t('admin.projects.add') }}
        </AdminBaseButton>
      </div>
      <div
        class="flex flex-wrap items-center justify-between gap-3"
        :data-testid="`${props.test_id}-filters-row-2`"
      >
        <AdminTechSkillPicker
          :model-value="props.filter_tags"
          :placeholder="t('admin.projects.filter_by_skills')"
          width="500px"
          :test_id="`${props.test_id}-tag-filter`"
          @update:model-value="(tags) => emit('update:filter_tags', tags)"
        />
        <AdminBaseDropdown
          :model-value="props.published_filter"
          :options="[
            { value: 'all', label: t('admin.lists.all_statuses') },
            { value: 'published', label: t('admin.projects.published') },
            { value: 'unpublished', label: t('admin.projects.unpublished') },
          ]"
          width="150px"
          :test_id="`${props.test_id}-published-filter`"
          @update:model-value="onPublishedChange"
        />
        <AdminBaseCheckbox
          :model-value="props.featured_only"
          :label="t('admin.projects.featured_only')"
          :test_id="`${props.test_id}-featured`"
          @update:model-value="(checked) => emit('update:featured_only', checked)"
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
