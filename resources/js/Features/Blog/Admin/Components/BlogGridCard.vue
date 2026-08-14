<!-- BlogGridCard (blog.html — the grid view variant of the blog list):
     one article card — lead image (or the photo placeholder), title,
     unified tag chips (at most 3 + a "+N" ellipsis badge), views
     count, the publish/unpublish toggle button and the quick actions
     (edit via card click, delete — trashed view: restore / delete
     permanently). -->
<script setup lang="ts">
import type { ArticleTableRow, UnifiedTagChip } from '@blog/Admin/Types/article';
import { Icon } from '@iconify/vue';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        row: ArticleTableRow;
        trashed?: boolean;
        test_id?: string;
    }>(),
    {
        trashed: false,
        test_id: 'blog-grid-card',
    },
);

const emit = defineEmits<{
    edit: [row: Record<string, unknown>];
    'toggle-publication': [row: ArticleTableRow];
    delete: [row: Record<string, unknown>];
    'force-delete': [row: Record<string, unknown>];
    restore: [row: Record<string, unknown>];
}>();

const { t } = useI18n();

// The card collapses the tag chips after `MAX_VISIBLE_TAGS` into a
// "+N" ellipsis badge — the table convention (user request
// 2026-08-13, round 2).
const MAX_VISIBLE_TAGS = 3;

function visibleTags(tags: UnifiedTagChip[]): UnifiedTagChip[] {
    return tags.slice(0, Math.max(1, MAX_VISIBLE_TAGS));
}

function hiddenTagCount(tags: UnifiedTagChip[]): number {
    return Math.max(0, tags.length - Math.max(1, MAX_VISIBLE_TAGS));
}

function chipVariant(tag: UnifiedTagChip): 'primary' | 'default' {
    return tag.type === 'skill' ? 'primary' : 'default';
}

function formatViews(views: number): string {
    return views > 0 ? views.toLocaleString('en-US') : '—';
}

function previewUrl(row: ArticleTableRow): string {
    return `/assets/${row.lead_image ?? ''}`;
}
</script>

<template>
  <article
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-card transition-shadow hover:shadow-card-hover dark:border-slate-700 dark:bg-surface-dark-secondary"
    :class="{ 'opacity-60 italic': props.trashed }"
    :data-testid="props.test_id"
  >
    <div
      class="aspect-video w-full cursor-pointer overflow-hidden border-b border-slate-100 dark:border-slate-700"
      :data-testid="`${props.test_id}-image-block`"
      @click="emit('edit', props.row as unknown as Record<string, unknown>)"
    >
      <img
        v-if="props.row.lead_image"
        :src="previewUrl(props.row)"
        :alt="props.row.title"
        class="h-full w-full object-cover"
        loading="lazy"
        :data-testid="`${props.test_id}-image`"
      >
      <div
        v-else
        class="flex h-full w-full items-center justify-center bg-slate-50 text-slate-300 dark:bg-slate-900/50 dark:text-slate-600"
        aria-hidden="true"
        :data-testid="`${props.test_id}-image-placeholder`"
      >
        <Icon
          icon="heroicons:photo"
          class="h-8 w-8"
        />
      </div>
    </div>

    <div class="space-y-3 p-4">
      <h3
        class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100"
        :data-testid="`${props.test_id}-title`"
        @click="emit('edit', props.row as unknown as Record<string, unknown>)"
      >
        {{ props.row.title }}
      </h3>

      <div
        v-if="props.row.tags.length > 0"
        class="flex flex-wrap gap-1"
      >
        <AdminBaseBadge
          v-for="(tag, index) in visibleTags(props.row.tags)"
          :key="`${tag.type}-${tag.id}`"
          :variant="chipVariant(tag)"
          size="sm"
          :test_id="`${props.test_id}-tag-${index}`"
        >
          {{ tag.name }}
        </AdminBaseBadge>
        <AdminBaseBadge
          v-if="hiddenTagCount(props.row.tags) > 0"
          variant="default"
          size="sm"
          :test_id="`${props.test_id}-tag-ellipsis`"
        >
          +{{ hiddenTagCount(props.row.tags) }}
        </AdminBaseBadge>
      </div>

      <p class="font-mono text-xs text-slate-400 dark:text-slate-500">
        {{ t('admin.blog.views') }}: <span :data-testid="`${props.test_id}-views`">{{ formatViews(props.row.views) }}</span>
      </p>

      <div class="flex items-center justify-between pt-1">
        <template v-if="props.trashed">
          <AdminBaseBadge
            variant="default"
            :test_id="`${props.test_id}-badge-trashed`"
          >
            {{ t('admin.statuses.trashed') }}
          </AdminBaseBadge>
        </template>
        <!-- The publish/unpublish toggle replaces the state badge on
             live cards (the projects grid convention) — same button as
             the table's Published column action. -->
        <AdminBaseButton
          v-else
          type="button"
          variant="secondary"
          size="sm"
          :class="
            props.row.is_published
              ? 'bg-success-600! text-white! border-success-600! hover:bg-success-700! dark:border-success-600!'
              : 'border-primary-600! text-primary-600! hover:bg-primary-600! hover:text-white!'
          "
          :aria-pressed="props.row.is_published"
          :test_id="`${props.test_id}-publish-toggle`"
          @click="emit('toggle-publication', props.row)"
        >
          {{
            props.row.is_published ? t('admin.lists.unpublish') : t('admin.lists.publish')
          }}
        </AdminBaseButton>
        <div class="flex items-center gap-1">
          <template v-if="props.trashed">
            <AdminBaseButton
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:arrow-path"
              :aria-label="t('admin.lists.restore')"
              :test_id="`${props.test_id}-restore`"
              @click="emit('restore', props.row as unknown as Record<string, unknown>)"
            />
            <AdminBaseButton
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:trash"
              :aria-label="t('admin.lists.delete_permanently')"
              :test_id="`${props.test_id}-force-delete`"
              @click="emit('force-delete', props.row as unknown as Record<string, unknown>)"
            />
          </template>
          <template v-else>
            <AdminBaseButton
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:trash"
              :aria-label="t('common.delete')"
              :test_id="`${props.test_id}-delete`"
              @click="emit('delete', props.row as unknown as Record<string, unknown>)"
            />
          </template>
        </div>
      </div>
    </div>
  </article>
</template>
