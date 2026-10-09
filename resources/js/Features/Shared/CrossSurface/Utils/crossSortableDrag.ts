// Cross-list drag-and-drop coordination for SortableList. A skill drag
// started in one group's list must be accepted by another group's list
// and leave the SOURCE list untouched on `dragend` — the native DnD
// events fire on both lists (drop on the target, dragend on the source),
// so the two SortableList instances share this tiny module state instead
// of private component state. Plain module singletons — no reactivity
// needed; the components read/write it imperatively.
export interface CrossSortableState {
    /** `list_id` of the list the drag started in (null when inactive). */
    sourceListId: string | null;
    /** `cross_scope` of the source list — a drop is only accepted by
     *  lists with the same scope (group drags never land in skill lists
     *  and vice versa). */
    crossScope: string | null;
    /** The dragged item (shared object reference with the source list). */
    item: Record<string, unknown> | null;
    /** Set when the drag was already consumed by a target list's drop —
     *  the source list's `dragend` then resets without emitting. */
    handled: boolean;
}

let state: CrossSortableState = {
    sourceListId: null,
    crossScope: null,
    item: null,
    handled: false,
};

export function setCrossDragSource(
    sourceListId: string,
    crossScope: string,
    item: Record<string, unknown>,
): void {
    state = { sourceListId, crossScope, item, handled: false };
}

export function crossDragState(): CrossSortableState {
    return state;
}

export function markCrossDragHandled(): void {
    state.handled = true;
}

export function resetCrossDrag(): void {
    state = { sourceListId: null, crossScope: null, item: null, handled: false };
}

// Cross-list PREVIEW cleanup registry: while a foreign-list drag hovers a
// list, the list inserts the dragged item as a live preview. The
// browser's `dragleave` only reliably fires on the element the cursor
// LEAVES — hovering several lists in sequence (empty groups between the
// source and the drop target) left stale previews behind. Every list
// registers its preview cleanup here; a NEW dragstart and the SOURCE's
// dragend clear ALL previews, so nothing can outlive the drag.
type PreviewCleanup = () => void;

const previewCleanups = new Set<PreviewCleanup>();

export function registerPreviewCleanup(cleanup: PreviewCleanup): void {
    previewCleanups.add(cleanup);
}

export function unregisterPreviewCleanup(cleanup: PreviewCleanup): void {
    previewCleanups.delete(cleanup);
}

export function clearAllPreviews(except?: PreviewCleanup): void {
    for (const cleanup of previewCleanups) {
        if (cleanup !== except) {
            cleanup();
        }
    }
}
