<script setup lang="ts">
import type { ResumeSectionKey, ResumeSectionsMap } from '@resume/Admin/Types/layout';
import SortableList from '@shared-cross/Components/SortableList/SortableList.vue';
import { nextTick } from 'vue';

import ResumeSectionRow from './ResumeSectionRow.vue';
import type { SectionRow } from './types';
import { useSectionReorder } from './useSectionReorder';
import { useSectionRows } from './useSectionRows';

const props = withDefaults(
    defineProps<{
        modelValue: ResumeSectionsMap;
        labels?: Partial<Record<ResumeSectionKey, string>>;
        test_id?: string;
    }>(),
    {
        labels: () => ({}),
        test_id: 'resume-section-editor',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: ResumeSectionsMap];
    toggle: [payload: { section_id: ResumeSectionKey; visible: boolean }];
    'move-up': [sectionId: ResumeSectionKey];
    'move-down': [sectionId: ResumeSectionKey];
}>();

const { visibleRows, hiddenRows, labelOf, renumbered, applyVisibleOrder } = useSectionRows(props);
const { canMoveUp, canMoveDown, toggleVisibility, moveUp, moveDown } = useSectionReorder(props, {
    visibleRows,
    hiddenRows,
    renumbered,
});

function asRow(item: Record<string, unknown>): SectionRow {
    return item as SectionRow;
}

async function onToggle(id: ResumeSectionKey, visible: boolean): Promise<void> {
    emit('toggle', { section_id: id, visible });
    emit('update:modelValue', toggleVisibility(id, visible));

    await nextTick();
    restoreToggleFocus(id);
}

// The row crosses between the two groups, so Vue unmounts the old
// checkbox — focus would fall back to the body without this.
function restoreToggleFocus(id: ResumeSectionKey): void {
    const checkbox = document.querySelector<HTMLInputElement>(
        `[data-testid="${props.test_id}-visible-${id}"]`,
    );

    checkbox?.focus({ preventScroll: true });
}

function onMoveUp(id: ResumeSectionKey): void {
    const next = moveUp(id);

    if (next === null) {
        return;
    }

    emit('move-up', id);
    emit('update:modelValue', next);
}

function onMoveDown(id: ResumeSectionKey): void {
    const next = moveDown(id);

    if (next === null) {
        return;
    }

    emit('move-down', id);
    emit('update:modelValue', next);
}

function onVisibleReorder(items: Array<Record<string, unknown>>): void {
    const next = applyVisibleOrder(items.map(asRow));

    if (next !== null) {
        emit('update:modelValue', next);
    }
}
</script>

<template>
  <div
    class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
    :data-testid="props.test_id"
  >
    <SortableList
      :model-value="visibleRows"
      item_key="id"
      interactive_selector="input[type='checkbox'], button"
      :classes="{ container: 'divide-y divide-slate-100 dark:divide-slate-700' }"
      :test_id="`${props.test_id}-visible-list`"
      @update:model-value="onVisibleReorder"
    >
      <template #default="{ item }">
        <ResumeSectionRow
          :row="asRow(item)"
          :label="labelOf(asRow(item).id)"
          :can_move_up="canMoveUp(asRow(item).id)"
          :can_move_down="canMoveDown(asRow(item).id)"
          :test_id="props.test_id"
          @toggle="onToggle(asRow(item).id, $event)"
          @move-up="onMoveUp(asRow(item).id)"
          @move-down="onMoveDown(asRow(item).id)"
        />
      </template>
    </SortableList>

    <div
      v-if="hiddenRows.length > 0"
      class="divide-y divide-slate-100 dark:divide-slate-700"
      :class="{ 'border-t border-slate-100 dark:border-slate-700': visibleRows.length > 0 }"
    >
      <ResumeSectionRow
        v-for="row in hiddenRows"
        :key="row.id"
        :row="row"
        :label="labelOf(row.id)"
        :can_move_up="false"
        :can_move_down="false"
        :test_id="props.test_id"
        @toggle="onToggle(row.id, $event)"
        @move-up="onMoveUp(row.id)"
        @move-down="onMoveDown(row.id)"
      />
    </div>
  </div>
</template>
