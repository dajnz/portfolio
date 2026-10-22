import {
    clearAllPreviews,
    crossDragState,
    markCrossDragHandled,
    registerPreviewCleanup,
    resetCrossDrag,
    setCrossDragSource,
    unregisterPreviewCleanup,
} from '@shared-cross/Utils/crossSortableDrag';
import { onBeforeUnmount, onMounted, type Ref, ref } from 'vue';

import type { SortableItem } from './types';

export interface CrossListDropProps {
    disabled: boolean;
    item_key: string;
    list_id?: string;
    cross_scope?: string;
}

export interface CrossListDropDependencies {
    items: Ref<Array<SortableItem>>;
    keyOf: (item: SortableItem) => unknown;
    insertionIndexAt: (event: DragEvent) => number;
    containerElement: () => HTMLElement | null;
}

export interface CrossDropResult {
    items: Array<SortableItem>;
    item: SortableItem;
    from_list_id: string;
}

export interface CrossListDropApi {
    previewActive: Ref<boolean>;
    previewItemKey: Ref<unknown>;
    clearPreviews: () => void;
    resetPreview: () => void;
    setDragSource: (item: SortableItem) => void;
    handleDragOver: (event: DragEvent) => boolean;
    handleDragLeave: (event: DragEvent) => void;
    handleDrop: (event: DragEvent) => CrossDropResult | null;
    isCrossDragHandled: () => boolean;
    resetCrossDragState: () => void;
}

interface ActiveCrossDrag {
    sourceListId: string;
    item: SortableItem;
}

export function useCrossListDrop(
    props: CrossListDropProps,
    dependencies: CrossListDropDependencies,
): CrossListDropApi {
    // Cross-list drop preview: while a foreign-list drag hovers this list,
    // the dragged item is INSERTED into the local items at the hovered
    // position (semi-transparent) so the drop destination is visible — the
    // same feedback as the within-list live shift. The preview is removed on
    // dragleave/drop; the drop finalizes it. The flags are refs — the item
    // classes bind them.
    const previewActive = ref(false);
    const previewIndex = ref(-1);
    // The dragged item's key while its preview is shown — the class binding
    // matches the preview item by identity, not position.
    const previewItemKey = ref<unknown>(null);

    function clearPreview(): void {
        if (!previewActive.value) {
            return;
        }

        // The preview item is located BY KEY, not by `previewIndex` — a
        // foreign `modelValue` reset (a server re-render landing mid-drag)
        // could otherwise leave the flags pointing at a real item, and the
        // cleanup would remove the wrong row and duplicate the preview.
        const index = dependencies.items.value.findIndex(
            (item) => dependencies.keyOf(item) === previewItemKey.value,
        );

        if (index !== -1) {
            dependencies.items.value.splice(index, 1);
        }

        resetPreview();
    }

    function resetPreview(): void {
        previewActive.value = false;
        previewIndex.value = -1;
        previewItemKey.value = null;
    }

    function clearPreviews(): void {
        clearAllPreviews();
    }

    function activeCrossDrag(): ActiveCrossDrag | null {
        const cross = crossDragState();

        if (
            cross.sourceListId === null ||
            cross.crossScope !== props.cross_scope ||
            cross.sourceListId === props.list_id ||
            cross.item === null
        ) {
            return null;
        }

        return { sourceListId: cross.sourceListId, item: cross.item };
    }

    function setDragSource(item: SortableItem): void {
        if (props.list_id === undefined || props.cross_scope === undefined) {
            return;
        }

        setCrossDragSource(props.list_id, props.cross_scope, item);
    }

    function handleDragOver(event: DragEvent): boolean {
        if (props.disabled) {
            return false;
        }

        const cross = activeCrossDrag();

        if (cross === null) {
            return false;
        }

        // The browser only fires `drop` on targets whose `dragover` was
        // prevented — allow the cross-list drop and show the live preview.
        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        // Entering a list invalidates the previews of the lists the
        // cursor left behind (the dragleave is unreliable in practice) —
        // except this list's own, which is about to be (re)inserted.
        clearAllPreviews(clearPreview);
        movePreview(cross.item, event);

        return true;
    }

    function movePreview(item: SortableItem, event: DragEvent): void {
        // Live preview without DOM churn: insert the item once and only
        // MOVE it when the hovered position changes. Re-inserting on
        // every dragover would re-render the list and the empty-slot
        // content, retargeting the cursor and flapping endlessly.
        const insertionIndex = dependencies.insertionIndexAt(event);

        if (!previewActive.value) {
            // Defensive: drop any stale copies of the item before
            // inserting the preview (a leftover from a previous drag).
            const key = item[props.item_key];
            dependencies.items.value = dependencies.items.value.filter(
                (row) => dependencies.keyOf(row) !== key,
            );
            const insertAt = Math.min(insertionIndex, dependencies.items.value.length);
            dependencies.items.value.splice(insertAt, 0, item);
            previewIndex.value = insertAt;
            previewItemKey.value = key;
            previewActive.value = true;
            return;
        }

        // The insertion index is computed WITH the preview in place —
        // normalize it to the preview-less coordinate before comparing.
        const adjusted = insertionIndex > previewIndex.value ? insertionIndex - 1 : insertionIndex;

        if (adjusted !== previewIndex.value) {
            dependencies.items.value.splice(previewIndex.value, 1);
            dependencies.items.value.splice(adjusted, 0, item);
            previewIndex.value = adjusted;
        }
    }

    function handleDragLeave(event: DragEvent): void {
        // Remove the cross-list preview when the drag actually leaves this
        // list (moving between the list's children keeps it — the
        // relatedTarget stays inside the container).
        if (!previewActive.value) {
            return;
        }

        const related = event.relatedTarget as Node | null;

        if (related !== null && dependencies.containerElement()?.contains(related) === true) {
            return;
        }

        clearPreview();
    }

    function handleDrop(event: DragEvent): CrossDropResult | null {
        if (props.disabled || props.list_id === undefined || props.cross_scope === undefined) {
            return null;
        }

        const cross = activeCrossDrag();

        if (cross === null) {
            return null;
        }

        const itemKey = cross.item[props.item_key];

        // The live preview already inserted the item — only reject a drop of
        // an item that is present WITHOUT a preview (a genuine duplicate).
        if (
            !previewActive.value &&
            dependencies.items.value.some((item) => dependencies.keyOf(item) === itemKey)
        ) {
            return null;
        }

        event.preventDefault();

        const result = [...dependencies.items.value];

        if (!previewActive.value) {
            result.splice(dependencies.insertionIndexAt(event), 0, cross.item);
        }

        resetPreview();
        dependencies.items.value = result;
        markCrossDragHandled();

        return {
            items: result,
            item: cross.item,
            from_list_id: cross.sourceListId,
        };
    }

    function isCrossDragHandled(): boolean {
        return crossDragState().handled;
    }

    function resetCrossDragState(): void {
        resetCrossDrag();
    }

    onMounted(() => {
        registerPreviewCleanup(clearPreview);
    });

    onBeforeUnmount(() => {
        unregisterPreviewCleanup(clearPreview);
    });

    return {
        previewActive,
        previewItemKey,
        clearPreviews,
        resetPreview,
        setDragSource,
        handleDragOver,
        handleDragLeave,
        handleDrop,
        isCrossDragHandled,
        resetCrossDragState,
    };
}
