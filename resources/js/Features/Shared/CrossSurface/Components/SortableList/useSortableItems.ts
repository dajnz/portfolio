import { type Ref, ref } from 'vue';

import type { SortableItem } from './types';

export interface SortableItemsProps {
    modelValue: Array<SortableItem>;
    item_key: string;
    axis: 'y' | 'x';
}

export interface SortableItemsApi {
    items: Ref<Array<SortableItem>>;
    keyOf: (item: SortableItem) => unknown;
    resetFromModel: () => void;
    reordered: (from: number, to: number) => Array<SortableItem>;
    itemIndexAt: (event: DragEvent) => number;
    insertionIndexAt: (event: DragEvent) => number;
}

export function useSortableItems(options: SortableItemsProps): SortableItemsApi {
    // Local working copy — the visual order shifts live during a drag and the
    // parent's `modelValue` is only updated on drop (optimistic-update
    // pattern; rollback is the parent's concern).
    const items = ref<Array<SortableItem>>([...options.modelValue]);

    function keyOf(item: SortableItem): unknown {
        return item[options.item_key];
    }

    function resetFromModel(): void {
        items.value = [...options.modelValue];
    }

    function reordered(from: number, to: number): Array<SortableItem> {
        const next = [...items.value];
        const [moved] = next.splice(from, 1);

        if (moved === undefined) {
            return items.value;
        }

        next.splice(to, 0, moved);

        return next;
    }

    function itemIndexAt(event: DragEvent): number {
        const target = event.target as Element | null;
        const element = target?.closest?.('[data-sortable-index]');

        if (!element) {
            return -1;
        }

        return Number(element.getAttribute('data-sortable-index'));
    }

    function insertionIndexAt(event: DragEvent): number {
        const overIndex = itemIndexAt(event);

        if (overIndex === -1) {
            // Dropped on the container (empty list or below the items) —
            // append at the end.
            return items.value.length;
        }

        const overElement = (event.target as Element | null)?.closest?.('[data-sortable-index]');
        const rect = overElement?.getBoundingClientRect();
        const cursor = options.axis === 'y' ? event.clientY : event.clientX;
        const midPoint = rect
            ? options.axis === 'y'
                ? rect.top + rect.height / 2
                : rect.left + rect.width / 2
            : 0;

        return overIndex + (cursor > midPoint ? 1 : 0);
    }

    return { items, keyOf, resetFromModel, reordered, itemIndexAt, insertionIndexAt };
}
