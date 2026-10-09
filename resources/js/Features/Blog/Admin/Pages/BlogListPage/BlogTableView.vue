<!-- BlogTableView — the blog list's TABLE view variant (the selectable
     DataTable with the small lead-image preview before the title, the
     max-3-tags chips + "+N", the static Related count, the Published
     cell as the state-styled publish/unpublish toggle, the centered
     Views, the trashed restore/force-delete row actions and the bulk
     action bar slot). A presentational component: the row data and
     selection state come in as props, the interactions emit events —
     the page orchestrator (BlogListPage + useBlogRows) owns the state
     and the navigation. -->
<script setup lang="ts">
import type { ArticleTableRow, UnifiedTagChip } from '@blog/Admin/Types/article';
import { Icon } from '@iconify/vue';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import BulkActionBar from '@shared-admin/BulkActionBar.vue';
import DataTable from '@shared-admin/DataTable.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

import { tagChipVariant } from './useBlogRows';

const props = withDefaults(
    defineProps<{
        columns: Array<{
            key: string;
            label: string;
            width?: string;
            align?: 'left' | 'center' | 'right';
        }>;
        rows: Array<Record<string, unknown>>;
        selected_ids: Array<number | string>;
        trashed_ids: Array<number | string>;
        bulk_actions: Array<{
            label: string;
            key: string;
            variant?: 'primary' | 'secondary' | 'ghost' | 'icon-only' | 'outline' | 'danger';
        }>;
        show_trashed: boolean;
        has_active_filters: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'blog-list-page',
    },
);

const emit = defineEmits<{
    'update:selected_ids': [ids: Array<number | string>];
    'empty-action': [];
    'row-click': [row: Record<string, unknown>];
    'toggle-publication': [row: ArticleTableRow];
    'request-delete': [row: Record<string, unknown>];
    'request-force-delete': [row: Record<string, unknown>];
    restore: [row: Record<string, unknown>];
    'bulk-action': [key: string];
    'bulk-clear': [];
}>();

const { t } = useI18n();

// The Tags column collapses after `MAX_VISIBLE_TAGS` chips into a
// "+N" ellipsis badge — the projects table convention.
const MAX_VISIBLE_TAGS = 3;

function chipVariant(tag: UnifiedTagChip): 'primary' | 'default' {
    return tagChipVariant(tag);
}

function visibleTags(tags: UnifiedTagChip[]): UnifiedTagChip[] {
    return tags.slice(0, Math.max(1, MAX_VISIBLE_TAGS));
}

function hiddenTagCount(tags: UnifiedTagChip[]): number {
    return Math.max(0, tags.length - Math.max(1, MAX_VISIBLE_TAGS));
}

function formatViews(views: number): string {
    return views > 0 ? views.toLocaleString('en-US') : '—';
}

function previewUrl(row: ArticleTableRow): string {
    return `/assets/${row.lead_image ?? ''}`;
}
</script>

<template>
  <DataTable
    :columns="props.columns"
    :rows="props.rows"
    :sort_column="'id'"
    :sort_direction="'desc'"
    :selectable="true"
    :selected_ids="props.selected_ids"
    :trashed_ids="props.trashed_ids"
    :center_actions="true"
    :empty_mode="props.has_active_filters ? 'no-results' : 'empty'"
    :empty_message="
      props.has_active_filters ? t('admin.blog.no_articles_match') : t('admin.blog.no_articles')
    "
    :empty_action_label="props.has_active_filters ? t('common.clear_filters') : t('admin.blog.add')"
    :test_id="`${props.test_id}-table`"
    @update:selected_ids="(ids) => emit('update:selected_ids', ids)"
    @empty-action="emit('empty-action')"
    @row-click="(row) => emit('row-click', row)"
  >
    <template #bulk-actions>
      <BulkActionBar
        :selected_count="props.selected_ids.length"
        :actions="props.bulk_actions"
        :test_id="`${props.test_id}-bulk-bar`"
        @action="(action: string) => emit('bulk-action', action)"
        @clear="emit('bulk-clear')"
      />
    </template>
    <template #cell-title="{ row }">
      <div class="flex items-center gap-3">
        <!-- The small lead-image preview before the title — the
             projects list convention; the dashed photo placeholder
             covers articles without an image. -->
        <img
          v-if="(row as unknown as ArticleTableRow).lead_image"
          :src="previewUrl(row as unknown as ArticleTableRow)"
          alt=""
          class="h-10 w-16 shrink-0 rounded-md border border-slate-200 object-cover dark:border-slate-700"
          :data-testid="`${props.test_id}-preview-${String(row.id)}`"
        >
        <span
          v-else
          class="inline-flex h-10 w-16 shrink-0 items-center justify-center rounded-md border border-dashed border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600"
          aria-hidden="true"
          :data-testid="`${props.test_id}-preview-placeholder-${String(row.id)}`"
        >
          <Icon
            icon="heroicons:photo"
            class="h-5 w-5"
          />
        </span>
        <span class="font-semibold text-slate-900 dark:text-slate-100">
          {{ row.title }}
        </span>
      </div>
    </template>
    <template #cell-tags="{ row }">
      <!-- Static chips — at most 3, the remainder collapse into a "+N"
           ellipsis badge (the projects table convention); tag editing
           lives on the form page. -->
      <div class="flex flex-wrap gap-1">
        <AdminBaseBadge
          v-for="(tag, index) in visibleTags(row.tags as UnifiedTagChip[])"
          :key="`${tag.type}-${tag.id}`"
          :variant="chipVariant(tag)"
          size="sm"
          :test_id="`${props.test_id}-tags-${String(row.id)}-chip-${index}`"
        >
          {{ tag.name }}
        </AdminBaseBadge>
        <AdminBaseBadge
          v-if="hiddenTagCount(row.tags as UnifiedTagChip[]) > 0"
          variant="default"
          size="sm"
          :test_id="`${props.test_id}-tags-${String(row.id)}-ellipsis`"
        >
          +{{ hiddenTagCount(row.tags as UnifiedTagChip[]) }}
        </AdminBaseBadge>
      </div>
    </template>
    <template #cell-related="{ row }">
      <span
        class="font-mono text-slate-500 dark:text-slate-400"
        :data-testid="`${props.test_id}-related-${String(row.id)}`"
      >
        {{ String((row as unknown as ArticleTableRow).related_count) }}
      </span>
    </template>
    <template #cell-is_published="{ row }">
      <div @click.stop>
        <AdminBaseBadge
          v-if="props.show_trashed"
          variant="default"
          :test_id="`${props.test_id}-badge-trashed-${String(row.id)}`"
        >
          {{ t('admin.statuses.trashed') }}
        </AdminBaseBadge>
        <!-- The published cell is an active control (the projects
             convention): a state-styled button whose label is the action
             — Publish on drafts, Unpublish on live articles. -->
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
          @click="emit('toggle-publication', row as unknown as ArticleTableRow)"
        >
          {{
            row.is_published === true
              ? t('admin.lists.unpublish')
              : t('admin.lists.publish')
          }}
        </AdminBaseButton>
      </div>
    </template>
    <template #cell-views="{ row }">
      <span
        class="font-mono text-slate-500 dark:text-slate-400"
        :data-testid="`${props.test_id}-views-${String(row.id)}`"
      >
        {{ formatViews(Number(row.views)) }}
      </span>
    </template>
    <template #row-actions="{ row }">
      <!-- @click.stop — the actions must not bubble into the
           row's edit-navigation click handler. -->
      <div
        class="flex items-center gap-1"
        @click.stop
      >
        <template v-if="props.show_trashed">
          <AdminBaseButton
            type="button"
            variant="icon-outline-danger"
            icon="heroicons:arrow-path"
            :aria-label="t('admin.lists.restore')"
            :test_id="`${props.test_id}-restore-${String(row.id)}`"
            @click="emit('restore', row as Record<string, unknown>)"
          />
          <AdminBaseButton
            type="button"
            variant="icon-outline-danger"
            icon="heroicons:trash"
            :aria-label="t('admin.lists.delete_permanently')"
            :test_id="`${props.test_id}-force-delete-${String(row.id)}`"
            @click="emit('request-force-delete', row as Record<string, unknown>)"
          />
        </template>
        <AdminBaseButton
          v-else
          type="button"
          variant="icon-outline-danger"
          icon="heroicons:trash"
          :aria-label="t('common.delete')"
          :test_id="`${props.test_id}-delete-${String(row.id)}`"
          @click="emit('request-delete', row as Record<string, unknown>)"
        />
      </div>
    </template>
  </DataTable>
</template>
