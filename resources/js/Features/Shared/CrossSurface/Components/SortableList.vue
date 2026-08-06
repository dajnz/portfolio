<!-- SortableList (public-section-components.md:313-334) — native HTML5 drag-and-drop reordering, shared by public and admin surfaces via the `classes` prop (BEM by default, Tailwind in admin). Reused by the admin reorder lists in 4.2/5.2/6.2/7.2. -->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface SortableListClasses {
    container?: string;
    item?: string;
    placeholder?: string;
    handle?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: Array<Record<string, unknown>>;
        item_key?: string;
        disabled?: boolean;
        handle?: string;
        axis?: 'y' | 'x';
        classes?: SortableListClasses | null;
        test_id?: string;
    }>(),
    {
        item_key: 'id',
        disabled: false,
        handle: undefined,
        axis: 'y',
        classes: null,
        test_id: 'sortable-list',
    },
);

const emit = defineEmits<{
    'update:modelValue': [items: Array<Record<string, unknown>>];
    'drag-start': [item: Record<string, unknown>];
    'drag-end': [items: Array<Record<string, unknown>>];
}>();

// Local working copy — the visual order shifts live during a drag and the
// parent's `modelValue` is only updated on drop (optimistic-update pattern
// per error-handling.md; rollback is the parent's concern).
const items = ref<Array<Record<string, unknown>>>([...props.modelValue]);

let draggingIndex: number | null = null;
let draggedItem: Record<string, unknown> | null = null;

const isDragging = ref(false);

watch(
    () => props.modelValue,
    (next) => {
        if (!isDragging.value) {
            items.value = [...next];
        }
    },
);

function keyOf(item: Record<string, unknown>): unknown {
    return item[props.item_key];
}

function itemIndex(event: DragEvent): number {
    const target = event.target as Element | null;
    const element = target?.closest?.('[data-sortable-index]');

    if (!element) {
        return -1;
    }

    return Number(element.getAttribute('data-sortable-index'));
}

function onDragStart(event: DragEvent, index: number): void {
    if (props.disabled) {
        event.preventDefault();
        return;
    }

    const target = event.target as Element | null;

    // Handle-mode: only drags started on an element matching the handle
    // selector are allowed — the handle must be part of the dragged item.
    if (props.handle !== undefined) {
        const itemElement = target?.closest?.('[data-sortable-index]');
        const handleElement = target?.closest?.(props.handle);
        const startedOnHandle =
            handleElement !== null &&
            handleElement !== undefined &&
            itemElement?.contains(handleElement) === true;

        if (!startedOnHandle) {
            event.preventDefault();
            return;
        }
    }

    draggingIndex = index;
    draggedItem = items.value[index] ?? null;
    isDragging.value = true;

    event.dataTransfer?.setData('text/plain', String(keyOf(items.value[index])));
    event.dataTransfer?.setData('application/x-sortable-index', String(index));
    event.dataTransfer?.setData('application/x-sortable-axis', props.axis);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }

    if (draggedItem !== null) {
        emit('drag-start', draggedItem);
    }
}

function onDragOver(event: DragEvent): void {
    if (!isDragging.value || draggingIndex === null || props.disabled) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    const overIndex = itemIndex(event);

    if (overIndex === -1 || overIndex === draggingIndex) {
        return;
    }

    const overElement = (event.target as Element | null)?.closest?.('[data-sortable-index]');
    const rect = overElement?.getBoundingClientRect();
    const cursor = props.axis === 'y' ? event.clientY : event.clientX;
    const midPoint = rect
        ? props.axis === 'y'
            ? rect.top + rect.height / 2
            : rect.left + rect.width / 2
        : 0;
    const insertAfter = cursor > midPoint;

    let insertionIndex = overIndex + (insertAfter ? 1 : 0);

    // Account for the removal of the dragged item from its current slot.
    if (draggingIndex < insertionIndex) {
        insertionIndex -= 1;
    }

    items.value = reordered(draggingIndex, insertionIndex);
    draggingIndex = insertionIndex;
}

function reordered(from: number, to: number): Array<Record<string, unknown>> {
    const next = [...items.value];
    const [moved] = next.splice(from, 1);

    if (moved === undefined) {
        return items.value;
    }

    next.splice(to, 0, moved);

    return next;
}

function onDrop(event: DragEvent): void {
    if (!isDragging.value || draggingIndex === null) {
        return;
    }

    event.preventDefault();

    const result = [...items.value];
    finishDrag(result);
}

function onDragEnd(): void {
    if (!isDragging.value) {
        return;
    }

    // No drop happened (dropped outside or cancelled) — restore the
    // parent's order untouched.
    items.value = [...props.modelValue];
    finishDrag(items.value);
}

function finishDrag(result: Array<Record<string, unknown>>): void {
    draggingIndex = null;
    draggedItem = null;
    isDragging.value = false;

    emit('update:modelValue', result);
    emit('drag-end', result);
}

const containerAxisClass = computed(() =>
    props.axis === 'x' ? 'sortable-list--horizontal' : 'sortable-list--vertical',
);
</script>

<template>
  <ul
    class="sortable-list"
    :class="[containerAxisClass, { 'is-dragging': isDragging }, props.classes?.container]"
    :data-testid="props.test_id"
    @dragover="onDragOver"
    @drop="onDrop"
    @dragend="onDragEnd"
  >
    <li
      v-for="(item, index) in items"
      :key="String(keyOf(item))"
      class="sortable-list__item"
      :class="[
        {
          'is-dragging': isDragging && index === draggingIndex,
          'is-disabled': props.disabled,
        },
        props.classes?.item,
      ]"
      :data-sortable-index="index"
      :draggable="!props.disabled"
      :data-testid="`${props.test_id}-item-${String(keyOf(item))}`"
      @dragstart="onDragStart($event, index)"
    >
      <slot
        :item="item"
        :index="index"
      />
    </li>
  </ul>
</template>

<style scoped>
.sortable-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-flow: column;
    gap: var(--space-xs);
}

.sortable-list--horizontal {
    flex-flow: row wrap;
}

.sortable-list__item {
    transition: transform var(--transition-fast) var(--ease-default), opacity var(--transition-fast);
    touch-action: none;
}

.sortable-list__item.is-dragging {
    opacity: 0.4;
}

.sortable-list__item.is-disabled {
    cursor: not-allowed;
}
</style>
