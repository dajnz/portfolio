<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import SortableList from '@shared-cross/Components/SortableList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import { onMounted, ref, watch } from 'vue';

import type { EngineeringDetailRow } from './EngineeringDetailItem.vue';
import EngineeringDetailItem from './EngineeringDetailItem.vue';

const props = withDefaults(
    defineProps<{
        modelValue: EngineeringDetailRow[];
        locale: string;
        error?: string | null;
        /** The page's server-side error map — per-row field errors are
         *  extracted here (`detail_items.{index}.title.{locale}` etc.). */
        errors?: Record<string, string>;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        error: null,
        errors: () => ({}),
        disabled: false,
        test_id: 'engineering-details-list',
    },
);

const emit = defineEmits<{
    'update:modelValue': [rows: EngineeringDetailRow[]];
    add: [];
    remove: [index: number];
    reorder: [rows: EngineeringDetailRow[]];
}>();

const { t } = useI18n();
const { move, canMoveUp, canMoveDown } = useReorder();

const expandedUids = ref<Set<string>>(new Set());
let knownUids = new Set<string>();
let suppressHeaderClickUntil = 0;

onMounted(() => {
    knownUids = new Set(props.modelValue.map((row) => row.uid));
});

// New rows (the page's `add`) open expanded; a prop refresh re-creates
// every uid but keeps the count — nothing expands then.
watch(
    () => props.modelValue,
    (rows) => {
        if (rows.length > knownUids.size) {
            const next = new Set(expandedUids.value);

            for (const row of rows) {
                if (!knownUids.has(row.uid)) {
                    next.add(row.uid);
                }
            }

            expandedUids.value = next;
        }

        knownUids = new Set(rows.map((row) => row.uid));
    },
);

function isExpanded(uid: string): boolean {
    return expandedUids.value.has(uid);
}

function expand(uid: string): void {
    expandedUids.value = new Set(expandedUids.value).add(uid);
}

function collapse(uid: string): void {
    const next = new Set(expandedUids.value);
    next.delete(uid);
    expandedUids.value = next;
}

function expandAll(): void {
    expandedUids.value = new Set(props.modelValue.map((row) => row.uid));
}

function collapseAll(): void {
    expandedUids.value = new Set();
}

// A drag ends with a click on the drop point — clicks straight after a
// drag must not collapse the row under the mouse (150 ms window).
function onDragStart(): void {
    suppressHeaderClickUntil = Date.now() + 150;
}

function onHeaderClick(uid: string): void {
    if (Date.now() < suppressHeaderClickUntil) {
        return;
    }

    collapse(uid);
}

function removeAt(index: number): void {
    emit('remove', index);
}

// Keyboard alternative to drag reordering.
function moveItem(index: number, direction: -1 | 1): void {
    const next = move(props.modelValue, index, direction);

    if (next === null) {
        return;
    }

    emit('update:modelValue', next);
}

function fieldError(index: number, field: 'icon' | 'title' | 'description'): string | null {
    return (
        props.errors[`detail_items.${index}.${field}.${props.locale}`] ??
        props.errors[`detail_items.${index}.${field}`] ??
        null
    );
}

function compactTitle(row: EngineeringDetailRow): string {
    for (const locale of [props.locale, 'en', 'ru']) {
        const value = row.title[locale]?.trim();

        if (value !== undefined && value !== '') {
            return value;
        }
    }

    return t('admin.projects.detail_untitled');
}
</script>

<template>
  <div :data-testid="props.test_id">
    <!-- Sticky header (the section heading) with Expand all / Collapse
         all — same pattern as the screenshots gallery. -->
    <div
      class="sticky top-[130px] z-30 mb-1.5 flex items-center justify-between bg-white py-1 dark:bg-surface-dark-secondary"
      :data-testid="`${props.test_id}-header`"
    >
      <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
        {{ t('admin.projects.engineering_details') }}
      </span>
      <div class="flex items-center gap-1">
        <AdminBaseButton
          type="button"
          variant="ghost"
          size="sm"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-expand-all`"
          @click="expandAll"
        >
          {{ t('admin.projects.expand_all') }}
        </AdminBaseButton>
        <AdminBaseButton
          type="button"
          variant="ghost"
          size="sm"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-collapse-all`"
          @click="collapseAll"
        >
          {{ t('admin.projects.collapse_all') }}
        </AdminBaseButton>
      </div>
    </div>
    <SortableList
      :model-value="props.modelValue as unknown as Array<Record<string, unknown>>"
      item_key="uid"
      handle=".engineering-detail-handle"
      :disabled="props.disabled"
      :classes="{
        container: 'space-y-3',
      }"
      :test_id="`${props.test_id}-sortable`"
      @update:model-value="
        (rows) => emit('update:modelValue', rows as unknown as EngineeringDetailRow[])
      "
      @drag-start="onDragStart"
    >
      <template #default="{ item, index }">
        <div
          class="rounded-xl border border-slate-200 dark:border-slate-700"
          :data-testid="`${props.test_id}-item-${index}`"
        >
          <!-- Compact row — a click (except on the drag knob or the
               delete button) expands; the x sits at the right -->
          <div
            v-if="!isExpanded((item as unknown as EngineeringDetailRow).uid)"
            class="flex cursor-pointer items-center gap-3 p-3"
            :data-testid="`${props.test_id}-item-${index}-collapsed`"
            @click="expand((item as unknown as EngineeringDetailRow).uid)"
          >
            <span
              class="engineering-detail-handle cursor-grab text-sm text-slate-300 dark:text-slate-600"
              aria-hidden="true"
              @click.stop
            >
              ⠿
            </span>
            <Icon
              :icon="(item as unknown as EngineeringDetailRow).icon ?? 'heroicons:cog-6-tooth'"
              class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500"
              aria-hidden="true"
            />
            <span
              class="flex-1 truncate text-sm font-medium text-slate-700 dark:text-slate-200"
              :data-testid="`${props.test_id}-item-${index}-compact-title`"
            >
              {{ compactTitle(item as unknown as EngineeringDetailRow) }}
            </span>
            <span
              class="flex shrink-0 items-center gap-1"
              @click.stop
            >
              <AdminBaseButton
                variant="icon-only"
                icon="heroicons:chevron-up"
                :disabled="props.disabled || !canMoveUp(index)"
                :aria-disabled="props.disabled || !canMoveUp(index)"
                :aria-label="t('common.move_item_up', { item_name: compactTitle(item as unknown as EngineeringDetailRow) })"
                :test_id="`${props.test_id}-item-${index}-move-up`"
                @click="moveItem(index, -1)"
              />
              <AdminBaseButton
                variant="icon-only"
                icon="heroicons:chevron-down"
                :disabled="props.disabled || !canMoveDown(index, props.modelValue.length)"
                :aria-disabled="props.disabled || !canMoveDown(index, props.modelValue.length)"
                :aria-label="t('common.move_item_down', { item_name: compactTitle(item as unknown as EngineeringDetailRow) })"
                :test_id="`${props.test_id}-item-${index}-move-down`"
                @click="moveItem(index, 1)"
              />
            </span>
            <button
              type="button"
              class="select-none inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950 dark:hover:text-danger-400"
              :aria-label="t('admin.projects.remove_detail_item')"
              :data-testid="`${props.test_id}-item-${index}-remove`"
              @click.stop="removeAt(index)"
            >
              <svg
                class="icon"
                viewBox="0 0 24 24"
                style="width: 1em; height: 1em"
                aria-hidden="true"
              >
                <line
                  x1="18"
                  y1="6"
                  x2="6"
                  y2="18"
                />
                <line
                  x1="6"
                  y1="6"
                  x2="18"
                  y2="18"
                />
              </svg>
            </button>
          </div>
          <div v-else>
            <div
              class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-1.5 dark:border-slate-700"
              :data-testid="`${props.test_id}-item-${index}-expanded`"
              @click="onHeaderClick((item as unknown as EngineeringDetailRow).uid)"
            >
              <span
                class="engineering-detail-handle cursor-grab text-sm text-slate-300 dark:text-slate-600"
                aria-hidden="true"
                @click.stop
              >
                ⠿
              </span>
              <span class="flex-1 text-xs text-slate-400 dark:text-slate-500">
                {{ t('admin.projects.click_to_collapse') }}
              </span>
              <span
                class="flex shrink-0 items-center gap-1"
                @click.stop
              >
                <AdminBaseButton
                  variant="icon-only"
                  icon="heroicons:chevron-up"
                  :disabled="props.disabled || index === 0"
                  :aria-disabled="props.disabled || index === 0"
                  :aria-label="t('common.move_item_up', { item_name: compactTitle(item as unknown as EngineeringDetailRow) })"
                  :test_id="`${props.test_id}-item-${index}-move-up-expanded`"
                  @click="moveItem(index, -1)"
                />
                <AdminBaseButton
                  variant="icon-only"
                  icon="heroicons:chevron-down"
                  :disabled="props.disabled || index === props.modelValue.length - 1"
                  :aria-disabled="props.disabled || index === props.modelValue.length - 1"
                  :aria-label="t('common.move_item_down', { item_name: compactTitle(item as unknown as EngineeringDetailRow) })"
                  :test_id="`${props.test_id}-item-${index}-move-down-expanded`"
                  @click="moveItem(index, 1)"
                />
              </span>
              <button
                type="button"
                class="select-none inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950 dark:hover:text-danger-400"
                :aria-label="t('admin.projects.remove_detail_item')"
                :data-testid="`${props.test_id}-item-${index}-remove-expanded`"
                @click.stop="removeAt(index)"
              >
                <svg
                  class="icon"
                  viewBox="0 0 24 24"
                  style="width: 1em; height: 1em"
                  aria-hidden="true"
                >
                  <line
                    x1="18"
                    y1="6"
                    x2="6"
                    y2="18"
                  />
                  <line
                    x1="6"
                    y1="6"
                    x2="18"
                    y2="18"
                  />
                </svg>
              </button>
            </div>
            <div class="p-4">
              <EngineeringDetailItem
                :model-value="item as unknown as EngineeringDetailRow"
                :locale="props.locale"
                :icon_error="fieldError(index, 'icon')"
                :title_error="fieldError(index, 'title')"
                :description_error="fieldError(index, 'description')"
                :disabled="props.disabled"
                :test_id="`${props.test_id}-item-${index}`"
                @update:model-value="
                  (row) => {
                    const next = [...props.modelValue];
                    next[index] = row;
                    emit('update:modelValue', next);
                  }
                "
              />
            </div>
          </div>
        </div>
      </template>
    </SortableList>
    <AdminBaseButton
      type="button"
      variant="secondary"
      size="sm"
      icon="heroicons:plus"
      icon_position="left"
      :disabled="props.disabled"
      :test_id="`${props.test_id}-add`"
      class="mt-3"
      @click="emit('add')"
    >
      {{ t('admin.projects.add_detail') }}
    </AdminBaseButton>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400"
      role="alert"
      :data-testid="`${props.test_id}-error`"
    >
      {{ props.error }}
    </p>
  </div>
</template>
