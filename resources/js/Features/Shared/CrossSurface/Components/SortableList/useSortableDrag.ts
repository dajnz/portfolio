import { type Ref, ref } from 'vue';

import type { SortableItem } from './types';
import type { SortableDragGuardApi } from './useSortableDragGuard';

export interface SortableDragProps {
    disabled: boolean;
    axis: 'y' | 'x';
}

export interface SortableDragDependencies {
    items: Ref<Array<SortableItem>>;
    keyOf: (item: SortableItem) => unknown;
    guard: SortableDragGuardApi;
    itemIndexAt: (event: DragEvent) => number;
    insertionIndexAt: (event: DragEvent) => number;
    reordered: (from: number, to: number) => Array<SortableItem>;
    clearPreviews: () => void;
    setDragSource: (item: SortableItem) => void;
    resetFromModel: () => void;
}

export interface SortableDragApi {
    isDragging: Ref<boolean>;
    draggingIndex: Ref<number | null>;
    handleDragStart: (event: DragEvent, index: number) => SortableItem | null;
    handleDragOver: (event: DragEvent) => void;
    handleDrop: (event: DragEvent) => Array<SortableItem> | null;
    handleDragEnd: () => Array<SortableItem> | null;
    reset: () => void;
}

export function useSortableDrag(
    props: SortableDragProps,
    dependencies: SortableDragDependencies,
): SortableDragApi {
    const isDragging = ref(false);
    const draggingIndex = ref<number | null>(null);

    function handleDragStart(event: DragEvent, index: number): SortableItem | null {
        if (!dependencies.guard.allowsDragStart(index)) {
            event.preventDefault();
            return null;
        }

        const draggedItem = dependencies.items.value[index];

        if (draggedItem === undefined) {
            return null;
        }

        // A new drag invalidates every stale preview from the previous one.
        dependencies.clearPreviews();

        draggingIndex.value = index;
        isDragging.value = true;
        dependencies.setDragSource(draggedItem);

        event.dataTransfer?.setData('text/plain', String(dependencies.keyOf(draggedItem)));
        event.dataTransfer?.setData('application/x-sortable-index', String(index));
        event.dataTransfer?.setData('application/x-sortable-axis', props.axis);

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
        }

        return draggedItem;
    }

    function handleDragOver(event: DragEvent): void {
        if (props.disabled || !isDragging.value || draggingIndex.value === null) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        const overIndex = dependencies.itemIndexAt(event);

        if (overIndex === -1 || overIndex === draggingIndex.value) {
            return;
        }

        let insertionIndex = dependencies.insertionIndexAt(event);

        // Account for the removal of the dragged item from its current slot.
        if (draggingIndex.value < insertionIndex) {
            insertionIndex -= 1;
        }

        dependencies.items.value = dependencies.reordered(draggingIndex.value, insertionIndex);
        draggingIndex.value = insertionIndex;
    }

    function handleDrop(event: DragEvent): Array<SortableItem> | null {
        if (!isDragging.value || draggingIndex.value === null) {
            return null;
        }

        event.preventDefault();

        const result = [...dependencies.items.value];
        reset();

        return result;
    }

    function handleDragEnd(): Array<SortableItem> | null {
        if (!isDragging.value) {
            return null;
        }

        // No drop happened (dropped outside or cancelled) — restore the
        // parent's order untouched.
        dependencies.resetFromModel();

        const result = [...dependencies.items.value];
        reset();

        return result;
    }

    function reset(): void {
        draggingIndex.value = null;
        isDragging.value = false;
    }

    return {
        isDragging,
        draggingIndex,
        handleDragStart,
        handleDragOver,
        handleDrop,
        handleDragEnd,
        reset,
    };
}
