<!-- BlogGridView — the blog list's GRID view variant (the same rows as
     article cards via BlogGridCard: title + tags + views + publish
     toggle + delete; the view mode is not persisted — the toggle
     defaults to table each visit). A presentational component: the
     rows and bulk actions come in as props, the interactions emit
     events — the page orchestrator (BlogListPage + useBlogRows)
     owns the state and the navigation. -->
<script setup lang="ts">
import type { ArticleTableRow } from '@blog/Admin/Types/article';
import BlogGridCard from '@blog-admin/BlogGridCard.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import BulkActionBar from '@shared-admin/BulkActionBar.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        rows: ArticleTableRow[];
        bulk_actions: Array<{
            label: string;
            key: string;
            variant?: 'primary' | 'secondary' | 'ghost' | 'icon-only' | 'outline' | 'danger';
        }>;
        selected_ids: Array<number | string>;
        show_trashed: boolean;
        has_active_filters: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'blog-list-page',
    },
);

const emit = defineEmits<{
    'bulk-action': [key: string];
    'bulk-clear': [];
    edit: [row: Record<string, unknown>];
    'toggle-publication': [row: ArticleTableRow];
    'request-delete': [row: Record<string, unknown>];
    'request-force-delete': [row: Record<string, unknown>];
    restore: [row: Record<string, unknown>];
    'empty-action': [];
}>();

const { t } = useI18n();
</script>

<template>
  <div>
    <BulkActionBar
      v-if="props.selected_ids.length > 0"
      :selected_count="props.selected_ids.length"
      :actions="props.bulk_actions"
      :test_id="`${props.test_id}-bulk-bar`"
      @action="(key: string) => emit('bulk-action', key)"
      @clear="emit('bulk-clear')"
    />
    <div
      v-if="props.rows.length > 0"
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
      :data-testid="`${props.test_id}-grid`"
    >
      <BlogGridCard
        v-for="row in props.rows"
        :key="row.id"
        :row="row"
        :trashed="props.show_trashed"
        :test_id="`${props.test_id}-grid-card-${row.id}`"
        @edit="(item: Record<string, unknown>) => emit('edit', item)"
        @toggle-publication="(item: ArticleTableRow) => emit('toggle-publication', item)"
        @delete="(item: Record<string, unknown>) => emit('request-delete', item)"
        @force-delete="(item: Record<string, unknown>) => emit('request-force-delete', item)"
        @restore="(item: Record<string, unknown>) => emit('restore', item)"
      />
    </div>
    <AdminEmptyState
      v-else
      :mode="props.has_active_filters ? 'no-results' : 'empty'"
      :message="
        props.has_active_filters ? t('admin.blog.no_articles_match') : t('admin.blog.no_articles')
      "
      :action_label="props.has_active_filters ? t('common.clear_filters') : t('admin.blog.add')"
      :test_id="`${props.test_id}-grid-empty`"
      @action="emit('empty-action')"
    />
  </div>
</template>
