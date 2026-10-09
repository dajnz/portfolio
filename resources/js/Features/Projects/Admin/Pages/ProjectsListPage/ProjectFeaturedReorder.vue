<!-- ProjectFeaturedReorder — the featured-projects reorder section of
     the projects list: a SortableList over the featured rows; the drag
     end emits the zero-indexed id order. The page orchestrator POSTs
     it to /admin/projects/reorder-featured (A4) and rolls the list
     back on failure. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import SortableList, { type CrossDragInfo } from '@shared-cross/Components/SortableList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import { ref, watch } from 'vue';

// A type alias (not an interface) — SortableList's `modelValue` is
// `Record<string, unknown>[]` and type aliases receive the implicit
// index signature interfaces lack.
export type FeaturedRow = {
    id: number;
    title: string;
    featured_sort_order: number | null;
};

const props = withDefaults(
    defineProps<{
        items: FeaturedRow[];
        test_id?: string;
    }>(),
    {
        test_id: 'project-featured-reorder',
    },
);

const emit = defineEmits<{
    reorder: [ids: number[]];
}>();

const { t } = useI18n();
const { move, canMoveUp, canMoveDown } = useReorder();

// Local working copy so keyboard moves re-render immediately
// (optimistic UI, accessibility.md:42); drag reorders are handled by
// SortableList's own working copy.
const orderedItems = ref<FeaturedRow[]>([...props.items]);

watch(
    () => props.items,
    (next) => {
        orderedItems.value = [...next];
    },
);

function onDragEnd(
    items: Array<Record<string, unknown>>,
    cross: CrossDragInfo | null | undefined,
): void {
    if (cross !== null && cross !== undefined) {
        return;
    }

    emit(
        'reorder',
        items.map((item) => Number(item.id)),
    );
}

function moveItem(index: number, direction: -1 | 1): void {
    const next = move(orderedItems.value, index, direction);

    if (next === null) {
        return;
    }

    orderedItems.value = next;
    emit(
        'reorder',
        next.map((item) => item.id),
    );
}
</script>

<template>
  <section
    class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="`${props.test_id}-featured-reorder`"
  >
    <h2 class="mb-3 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.featured_projects_order') }}
    </h2>
    <SortableList
      :model-value="orderedItems as unknown as Array<Record<string, unknown>>"
      item_key="id"
      handle=".featured-row-handle"
      axis="y"
      :test_id="`${props.test_id}-featured-list`"
      @drag-end="onDragEnd"
    >
      <template #default="{ item, index }">
        <div
          class="flex items-center gap-2 px-2 py-2.5"
          :data-testid="`${props.test_id}-featured-item-${String(item.id)}`"
        >
          <span
            class="featured-row-handle cursor-grab text-sm text-slate-300 dark:text-slate-600"
            aria-hidden="true"
          >
            ⠿
          </span>
          <span class="flex-1 text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ item.title }}
          </span>
          <span class="text-xs text-slate-400 dark:text-slate-500">
            {{ item.featured_sort_order }}
          </span>
          <span class="flex shrink-0 items-center gap-1">
            <AdminBaseButton
              variant="icon-only"
              icon="heroicons:chevron-up"
              :disabled="!canMoveUp(index)"
              :aria-disabled="!canMoveUp(index)"
              :aria-label="t('common.move_item_up', { item_name: String(item.title) })"
              :test_id="`${props.test_id}-featured-item-${String(item.id)}-move-up`"
              @click="moveItem(index, -1)"
            />
            <AdminBaseButton
              variant="icon-only"
              icon="heroicons:chevron-down"
              :disabled="!canMoveDown(index, orderedItems.length)"
              :aria-disabled="!canMoveDown(index, orderedItems.length)"
              :aria-label="t('common.move_item_down', { item_name: String(item.title) })"
              :test_id="`${props.test_id}-featured-item-${String(item.id)}-move-down`"
              @click="moveItem(index, 1)"
            />
          </span>
        </div>
      </template>
    </SortableList>
  </section>
</template>
