<script lang="ts">
export type { CrossDragInfo } from './types';
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import SortableListItem from './SortableListItem.vue';
import type { CrossDragInfo, SortableItem, SortableListClasses } from './types';
import { useCrossListDrop } from './useCrossListDrop';
import { useSortableDrag } from './useSortableDrag';
import { useSortableDragGuard } from './useSortableDragGuard';
import { useSortableItems } from './useSortableItems';

const props = withDefaults(
    defineProps<{
        modelValue: Array<SortableItem>;
        item_key?: string;
        disabled?: boolean;
        /** Handle-mode drag guard: when set, drags only start from a
         *  mousedown on an element matching this selector. Without it the
         *  whole item is draggable (the skill rows drag from anywhere
         *  except `interactive_selector` controls). */
        handle?: string;
        /** Selector of the item's interactive controls (checkbox, dots,
         *  buttons, inputs...). A drag started on a matching element is
         *  rejected — the control's own interaction wins. */
        interactive_selector?: string;
        axis?: 'y' | 'x';
        classes?: SortableListClasses | null;
        test_id?: string;
        /** Unique list identifier — enables cross-list drops (the second
         *  arg of `drag-end` carries the source). */
        list_id?: string;
        /** Lists sharing a scope accept each other's drops (e.g. all
         *  skill lists across groups); lists without a scope never do. */
        cross_scope?: string;
    }>(),
    {
        item_key: 'id',
        disabled: false,
        handle: undefined,
        interactive_selector: undefined,
        axis: 'y',
        classes: null,
        test_id: 'sortable-list',
        list_id: undefined,
        cross_scope: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [items: Array<SortableItem>];
    'drag-start': [item: SortableItem];
    'drag-end': [items: Array<SortableItem>, cross?: CrossDragInfo | null];
}>();

// The container element — used by the dragleave and document-dragover
// checks that hide the preview when the cursor leaves the list.
const ulEl = ref<HTMLElement | null>(null);

const { items, keyOf, resetFromModel, reordered, itemIndexAt, insertionIndexAt } =
    useSortableItems(props);
const guard = useSortableDragGuard(props);
const { onItemMouseDown, onItemMouseUp } = guard;

const {
    previewActive,
    previewItemKey,
    clearPreviews,
    clearPreview,
    resetPreview,
    setDragSource,
    handleDragOver: handleCrossDragOver,
    handleDragLeave,
    handleDrop: handleCrossDrop,
    isCrossDragHandled,
    resetCrossDragState,
} = useCrossListDrop(props, {
    items,
    keyOf,
    insertionIndexAt,
    containerElement: () => ulEl.value,
});

const {
    isDragging,
    draggingIndex,
    handleDragStart,
    handleDragOver,
    handleDrop,
    handleDragEnd,
    restoreOrderAfterLeave,
    reset: resetDrag,
} = useSortableDrag(props, {
    items,
    keyOf,
    guard,
    itemIndexAt,
    insertionIndexAt,
    reordered,
    clearPreviews,
    setDragSource,
    resetFromModel,
});

watch(
    () => props.modelValue,
    (next) => {
        if (isDragging.value) {
            return;
        }

        items.value = [...next];

        // A props reset invalidates any in-flight preview: the items
        // no longer contain the preview item, but the flags/index
        // would still point at it — the next dragover would then
        // treat a REAL item as the preview slot (splicing it out,
        // duplicating the preview into ghost items). Reset the whole
        // preview state.
        resetPreview();
    },
);

function isInsideList(event: DragEvent): boolean {
    const element = ulEl.value;
    const target = event.target as Node | null;

    return element !== null && target !== null && element.contains(target);
}

// `dragleave` alone is unreliable: moving the live preview can leave the
// cursor outside the list without firing it (and its `relatedTarget` check
// misses edge cases). A document-level `dragover` reports where the cursor
// actually is — anything outside the list hides the preview and restores
// the same-list order.
function onDocumentDragOver(event: DragEvent): void {
    if (isInsideList(event)) {
        return;
    }

    restoreOrderAfterLeave();
    clearPreview();
}

watch(
    [isDragging, previewActive],
    ([dragging, previewing]) => {
        if (dragging || previewing) {
            document.addEventListener('dragover', onDocumentDragOver);
            return;
        }

        document.removeEventListener('dragover', onDocumentDragOver);
    },
    { flush: 'sync' },
);

onBeforeUnmount(() => {
    document.removeEventListener('dragover', onDocumentDragOver);
});

function onDragStart(event: DragEvent, index: number): void {
    // Nested lists (the skills lists live inside the groups list's
    // items): the dragstart bubbles up to the OUTER list's items, whose
    // handle-mode guard would preventDefault() it (its arming never saw
    // the mousedown) and kill the drag before any dragover — the
    // innermost list owns its item's drag.
    event.stopPropagation();

    const draggedItem = handleDragStart(event, index);

    if (draggedItem !== null) {
        emit('drag-start', draggedItem);
    }
}

function onDragOver(event: DragEvent): void {
    if (!isDragging.value && handleCrossDragOver(event)) {
        return;
    }

    handleDragOver(event);
}

function onDrop(event: DragEvent): void {
    // Same-list drop.
    const localResult = handleDrop(event);

    if (localResult !== null) {
        emit('update:modelValue', localResult);
        emit('drag-end', localResult, null);
        return;
    }

    // Cross-list drop: a drag that started in another list with the same
    // scope (a skill moved into this group). The insertion position mirrors
    // the same-list midpoint logic (the live preview already placed the
    // item — the drop finalizes it); the parent is told where the item came
    // from via the second `drag-end` argument.
    const crossResult = handleCrossDrop(event);

    if (crossResult === null) {
        return;
    }

    emit('update:modelValue', crossResult.items);
    emit('drag-end', crossResult.items, {
        item: crossResult.item,
        from_list_id: crossResult.from_list_id,
    });
}

function onDragEnd(): void {
    // The drag is over — no hovered list may keep its preview copy.
    clearPreviews();

    // A cross-list drop already consumed the drag elsewhere — the source
    // list restores the server-confirmed order and stays silent (its own
    // order never changed; emitting would trigger a bogus reorder).
    if (isCrossDragHandled()) {
        resetFromModel();
        resetDrag();
        resetCrossDragState();
        return;
    }

    resetCrossDragState();

    const result = handleDragEnd();

    if (result !== null) {
        emit('update:modelValue', result);
        emit('drag-end', result, null);
    }
}

const containerAxisClass = computed(() =>
    props.axis === 'x' ? 'sortable-list--horizontal' : 'sortable-list--vertical',
);
</script>

<template>
  <ul
    ref="ulEl"
    class="sortable-list"
    :class="[containerAxisClass, { 'is-dragging': isDragging }, props.classes?.container]"
    :data-testid="props.test_id"
    @dragover="onDragOver"
    @dragleave="handleDragLeave"
    @drop="onDrop"
    @dragend="onDragEnd"
  >
    <SortableListItem
      v-for="(item, index) in items"
      :key="String(keyOf(item))"
      :index="index"
      :item_test_id="`${props.test_id}-item-${String(keyOf(item))}`"
      :is_dragging="isDragging && index === draggingIndex"
      :is_preview="previewActive && keyOf(item) === previewItemKey"
      :disabled="props.disabled"
      :item_class="props.classes?.item"
      @mousedown="onItemMouseDown($event, index)"
      @mouseup="onItemMouseUp"
      @dragstart="onDragStart($event, index)"
    >
      <slot
        :item="item"
        :index="index"
      />
    </SortableListItem>
    <!-- Empty-state content (the empty-group message lives INSIDE the
         drop target so cross-group drops land on it). -->
    <slot
      v-if="items.length === 0"
      name="empty"
    />
  </ul>
</template>

<style scoped>
.sortable-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-flow: column;
    overflow: hidden;
}

.sortable-list--horizontal {
    flex-flow: row wrap;
}
</style>
