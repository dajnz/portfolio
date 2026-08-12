<!-- ProjectGridCard (projects.html — the grid view variant of the
     projects list) — one project card: preview image (or placeholder),
     title, tech-tag chips (icons + one-line ellipsis), the
     publish/unpublish toggle button (the card click opens the edit
     form — no dedicated edit button, user request 2026-08-12) and the
     quick actions (feature star, delete — trashed view: restore /
     delete permanently). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import TechTagList from '@projects-admin/TechTagList.vue';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

interface TechTag {
    id: number;
    name: string;
    icon: string | null;
}

interface GridProjectRow {
    id: number;
    title: string;
    slug: string;
    preview_image: string | null;
    is_published: boolean;
    is_featured: boolean;
    tech_tags: TechTag[];
}

const props = withDefaults(
    defineProps<{
        row: GridProjectRow;
        trashed?: boolean;
        test_id?: string;
    }>(),
    {
        trashed: false,
        test_id: 'project-grid-card',
    },
);

const emit = defineEmits<{
    edit: [row: Record<string, unknown>];
    'toggle-featured': [row: GridProjectRow];
    'toggle-publication': [row: GridProjectRow];
    delete: [row: Record<string, unknown>];
    'force-delete': [row: Record<string, unknown>];
    restore: [row: Record<string, unknown>];
}>();

const { t } = useI18n();

function previewUrl(row: GridProjectRow): string {
    return `/assets/${row.preview_image ?? ''}`;
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
      @click="emit('edit', props.row as unknown as Record<string, unknown>)"
    >
      <img
        v-if="props.row.preview_image"
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
      >
        <Icon
          icon="heroicons:photo"
          class="h-8 w-8"
        />
      </div>
    </div>

    <div class="space-y-2 p-4">
      <h3
        class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100"
        @click="emit('edit', props.row as unknown as Record<string, unknown>)"
      >
        {{ props.row.title }}
      </h3>

      <div
        v-if="props.row.tech_tags.length > 0"
        class="flex flex-wrap gap-1"
      >
        <TechTagList
          :tags="props.row.tech_tags"
          :test_id="`${props.test_id}-tags`"
        />
      </div>

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
             live cards (user request 2026-08-12) — same button as the
             table's published column. -->
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
              variant="icon-only"
              :icon="props.row.is_featured ? 'heroicons:star-solid' : 'heroicons:star'"
              :class="
                props.row.is_featured ? 'text-warning-500' : 'text-slate-300 dark:text-slate-600'
              "
              :aria-label="
                props.row.is_featured
                  ? t('admin.projects.unfeature_project')
                  : t('admin.projects.feature_project')
              "
              :aria-pressed="props.row.is_featured"
              :test_id="`${props.test_id}-feature`"
              @click="emit('toggle-featured', props.row)"
            />
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
