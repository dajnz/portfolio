export interface SortableDragGuardProps {
    disabled: boolean;
    handle?: string;
    interactive_selector?: string;
}

export interface SortableDragGuardApi {
    onItemMouseDown: (event: MouseEvent, index: number) => void;
    onItemMouseUp: () => void;
    allowsDragStart: (index: number) => boolean;
}

export function useSortableDragGuard(options: SortableDragGuardProps): SortableDragGuardApi {
    // The browser fires `dragstart` at the drag SOURCE (the draggable item
    // element), not at the handle under the cursor — `event.target` is the
    // item, so the handle guard cannot inspect it at dragstart time. The
    // mousedown that started the gesture is recorded here instead: in
    // handle-mode a real drag is only allowed when the press began on the
    // handle; with `interactive_selector`, a press on a control (checkbox,
    // dots, buttons, inputs) rejects the drag — the control wins.
    let dragArmedFromHandle = false;
    let dragArmedIndex: number | null = null;
    let lastMousedownTarget: Element | null = null;

    function onItemMouseDown(event: MouseEvent, index: number): void {
        // Nested lists (the skills lists live inside the groups list's
        // items): the mousedown bubbles up to the OUTER list's items, whose
        // handle-mode arming must not see it — the innermost list owns its
        // item's gestures.
        event.stopPropagation();

        lastMousedownTarget = event.target as Element | null;

        if (options.disabled || options.handle === undefined) {
            return;
        }

        const handleElement = (event.target as Element | null)?.closest?.(options.handle);

        if (handleElement !== null && handleElement !== undefined) {
            dragArmedFromHandle = true;
            dragArmedIndex = index;
        }
    }

    function onItemMouseUp(): void {
        dragArmedFromHandle = false;
        dragArmedIndex = null;
        lastMousedownTarget = null;
    }

    function allowsDragStart(index: number): boolean {
        if (options.disabled) {
            return false;
        }

        // A drag started on one of the item's interactive controls (checkbox,
        // dots, buttons, inputs) is rejected — the control's own interaction
        // wins. The mousedown target is tracked because `dragstart` fires on
        // the item element, not on the element under the cursor.
        if (
            options.interactive_selector !== undefined &&
            lastMousedownTarget?.closest?.(options.interactive_selector) != null
        ) {
            lastMousedownTarget = null;
            return false;
        }

        lastMousedownTarget = null;

        if (options.handle === undefined) {
            return true;
        }

        const startedOnHandle = dragArmedFromHandle && dragArmedIndex === index;

        dragArmedFromHandle = false;
        dragArmedIndex = null;

        return startedOnHandle;
    }

    return { onItemMouseDown, onItemMouseUp, allowsDragStart };
}
