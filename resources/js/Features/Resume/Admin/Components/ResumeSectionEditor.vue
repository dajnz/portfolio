<!-- ResumeSectionEditor — the resume layout's section visibility + ordering: a checkbox per section (visible toggle), up/down arrow buttons (the keyboard alternative) and native drag-and-drop — all within the visible group only. Visible sections render first (sorted by order), hidden sections snap to the bottom (also sorted, subdued styling, not draggable and not drop targets). Any reorder rewrites the five order values to their display positions (0–4, the permutation invariant — visible-only renumbering could collide with hidden values); toggling visibility keeps the order value. Emits `update:modelValue` on every change plus the contract's `toggle` / `move-up` / `move-down` events; the page auto-saves the changes. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import { computed, ref } from 'vue';

import {
    RESUME_SECTION_KEYS,
    type ResumeSectionKey,
    type ResumeSectionsMap,
} from '../Types/layout';

interface SectionRow {
    id: ResumeSectionKey;
    visible: boolean;
    order: number;
    /** Index within the visible group (-1 for hidden rows) — the drag
     *  handlers bind to it. */
    visibleIndex: number;
}

const props = withDefaults(
    defineProps<{
        modelValue: ResumeSectionsMap;
        /** Section id → localized label (Header / About Me / Skills / Experience / Contacts). */
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

const { t } = useI18n();
const { move, canMoveUp: canMoveIndexUp, canMoveDown: canMoveIndexDown } = useReorder();

// Visible sections first (sorted by order), then the hidden ones — the
// fixed five-section set, so the full list is the two groups in order.
function rowsByVisibility(visible: boolean): SectionRow[] {
    const rows = RESUME_SECTION_KEYS.filter(
        (id) => (props.modelValue[id]?.visible ?? false) === visible,
    )
        .sort((a, b) => props.modelValue[a].order - props.modelValue[b].order)
        .map((id) => ({ id, ...props.modelValue[id] }));

    return rows.map((row, index) => ({
        ...row,
        visibleIndex: visible ? index : -1,
    }));
}

const visibleRows = computed<SectionRow[]>(() => rowsByVisibility(true));
const hiddenRows = computed<SectionRow[]>(() => rowsByVisibility(false));

/** Reassigns the order values to the rows' display positions — a
 *  reorder always keeps the five unique 0–4 permutation. */
function renumberedMap(ordered: SectionRow[]): ResumeSectionsMap {
    const next = { ...props.modelValue };

    ordered.forEach((row, index) => {
        next[row.id] = { ...next[row.id], order: index };
    });

    return next;
}

function toggleVisibility(id: ResumeSectionKey, visible: boolean): void {
    emit('toggle', { section_id: id, visible });

    const next = { ...props.modelValue };
    next[id] = { ...next[id], visible };

    emit('update:modelValue', next);
}

function indexOfVisible(id: ResumeSectionKey): number {
    return visibleRows.value.findIndex((row) => row.id === id);
}

function canMoveUp(id: ResumeSectionKey): boolean {
    return canMoveIndexUp(indexOfVisible(id));
}

function canMoveDown(id: ResumeSectionKey): boolean {
    return canMoveIndexDown(indexOfVisible(id), visibleRows.value.length);
}

function moveVisibleRow(id: ResumeSectionKey, direction: -1 | 1): SectionRow[] {
    const list = [...visibleRows.value];
    const index = list.findIndex((row) => row.id === id);

    if (index === -1) {
        return list;
    }

    return move(list, index, direction) ?? list;
}

function moveUp(id: ResumeSectionKey): void {
    if (!canMoveUp(id)) {
        return;
    }

    emit('move-up', id);
    emit('update:modelValue', renumberedMap([...moveVisibleRow(id, -1), ...hiddenRows.value]));
}

function moveDown(id: ResumeSectionKey): void {
    if (!canMoveDown(id)) {
        return;
    }

    emit('move-down', id);
    emit('update:modelValue', renumberedMap([...moveVisibleRow(id, 1), ...hiddenRows.value]));
}
// During a drag the display order is a WORKING COPY — the visible rows
// shift live as the cursor moves, so the dragged row appears ghosted
// (opacity-40) in its potential slot (the SortableList pattern). The
// drop finalizes the working copy into the emitted map; dragend without
// a drop discards it.
const draggedId = ref<ResumeSectionKey | null>(null);
const dragRows = ref<SectionRow[] | null>(null);

const rows = computed<SectionRow[]>(() => {
    const ordered = dragRows.value ?? [...visibleRows.value, ...hiddenRows.value];

    return ordered.map((row, index) => ({
        ...row,
        visibleIndex: row.visible ? index : -1,
    }));
});

// The browser fires `dragstart` at the row, not at the element under the
// cursor — the mousedown target is recorded so a drag begun on the
// checkbox or an arrow button can be rejected (the control wins).
let lastMousedownTarget: Element | null = null;

function onRowMouseDown(event: MouseEvent): void {
    lastMousedownTarget = event.target as Element | null;
}

function onRowMouseUp(): void {
    lastMousedownTarget = null;
}

function onDragStart(event: DragEvent, index: number): void {
    if (lastMousedownTarget?.closest?.('input[type="checkbox"], button') != null) {
        lastMousedownTarget = null;
        event.preventDefault();
        return;
    }

    lastMousedownTarget = null;
    draggedId.value = visibleRows.value[index]?.id ?? null;

    if (draggedId.value === null) {
        return;
    }

    dragRows.value = [...visibleRows.value, ...hiddenRows.value];

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function onDragOver(event: DragEvent, index: number): void {
    if (draggedId.value === null || dragRows.value === null) {
        return;
    }

    const visible = dragRows.value.filter((row) => row.visible);
    const draggedIndex = visible.findIndex((row) => row.id === draggedId.value);

    if (draggedIndex === -1) {
        return;
    }

    // Every visible row accepts the drop — the dragged row included. The
    // live-shift parks the ghost directly under the cursor, so the release
    // lands on the dragged row; without preventDefault the browser treats
    // it as an invalid drop target and cancels the drop (the order snaps
    // back — the reorder is only ever "visual").
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    if (index === draggedIndex) {
        return;
    }

    // Insert before the hovered row unless the cursor is past its
    // vertical center, then account for the dragged row's own slot.
    const element = (event.target as Element | null)?.closest?.('[data-resume-row]');
    const rect = element?.getBoundingClientRect();
    const insertBefore =
        rect === undefined || rect === null || event.clientY <= rect.top + rect.height / 2;
    let insertionIndex = index + (insertBefore ? 0 : 1);

    if (draggedIndex < insertionIndex) {
        insertionIndex -= 1;
    }

    const nextVisible = [...visible];
    const [moved] = nextVisible.splice(draggedIndex, 1);
    nextVisible.splice(insertionIndex, 0, moved);

    dragRows.value = [...nextVisible, ...dragRows.value.filter((row) => !row.visible)];
}

function onDrop(): void {
    if (draggedId.value === null || dragRows.value === null) {
        return;
    }

    const current = [...visibleRows.value, ...hiddenRows.value];
    const sameOrder =
        current.length === dragRows.value.length &&
        dragRows.value.every((row, position) => current[position]?.id === row.id);

    // A drop without an actual move (e.g. back into the original slot)
    // must not rewrite the stored order values.
    if (!sameOrder) {
        emit('update:modelValue', renumberedMap(dragRows.value));
    }

    resetDrag();
}

function onDragEnd(): void {
    resetDrag();
}

function resetDrag(): void {
    draggedId.value = null;
    dragRows.value = null;
}
function rowLabel(id: ResumeSectionKey): string {
    return props.labels[id] ?? id;
}
</script>

<template>
  <div
    class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700"
    :data-testid="props.test_id"
  >
    <div
      v-for="row in rows"
      :key="row.id"
      class="flex items-center gap-3 bg-white px-4 py-3 dark:bg-surface-dark-secondary"
      :class="[
        row.visible ? '' : 'opacity-50',
        row.visible && row.id === draggedId ? 'opacity-40' : '',
      ]"
      :data-resume-row="row.visible ? '' : undefined"
      :draggable="row.visible || undefined"
      :data-testid="`${props.test_id}-row-${row.id}`"
      @mousedown="onRowMouseDown"
      @mouseup="onRowMouseUp"
      @dragstart="row.visible ? onDragStart($event, row.visibleIndex) : undefined"
      @dragover="row.visible ? onDragOver($event, row.visibleIndex) : undefined"
      @drop="row.visible ? onDrop() : undefined"
      @dragend="onDragEnd"
    >
      <span
        class="hidden cursor-grab text-sm text-slate-300 sm:block dark:text-slate-600"
        aria-hidden="true"
      >⠿</span>
      <AdminBaseCheckbox
        :model-value="row.visible"
        :label="rowLabel(row.id)"
        class="flex-1 select-none"
        :test_id="`${props.test_id}-visible-${row.id}`"
        @update:model-value="toggleVisibility(row.id, $event)"
      />
      <div class="flex shrink-0 items-center gap-1">
        <AdminBaseButton
          variant="icon-only"
          icon="heroicons:arrow-up"
          :disabled="!canMoveUp(row.id)"
          :aria-label="t('common.move_item_up', { item_name: rowLabel(row.id) })"
          :test_id="`${props.test_id}-up-${row.id}`"
          @click="moveUp(row.id)"
        />
        <AdminBaseButton
          variant="icon-only"
          icon="heroicons:arrow-down"
          :disabled="!canMoveDown(row.id)"
          :aria-label="t('common.move_item_down', { item_name: rowLabel(row.id) })"
          :test_id="`${props.test_id}-down-${row.id}`"
          @click="moveDown(row.id)"
        />
      </div>
    </div>
  </div>
</template>
