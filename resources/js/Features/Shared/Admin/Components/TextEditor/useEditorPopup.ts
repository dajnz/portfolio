/**
 * The TextEditor's shared config-popup state machine: one popup at a time,
 * opened by every option-bearing toolbar control (the toolbar is a single
 * non-wrapping row).
 *
 * The popup is `position: fixed`, anchored to the CLICKED BUTTON's
 * viewport rect at open time — the sticky toolbar keeps the buttons
 * visible while the editor box scrolls, so a wrapper-relative popup
 * would land off-screen above the content. An outside click closes the
 * popup — but NOT clicks on the toolbar (its buttons manage the popup
 * themselves: same-button toggles it, another button switches it) nor on
 * the popup itself; capture phase, so the check runs before any element's
 * own handler. `Escape` closes it. The table grid's arrow/Enter keys are
 * the popup's own listener (it owns the grid state) — see EditorConfigPopup.
 */
import { type ComputedRef, computed, onBeforeUnmount, onMounted, type Ref, ref } from 'vue';

export type EditorPopupMode = 'heading' | 'link' | 'language' | 'callout' | 'table';

/** The widest popup content (the link row) — the clamp bound. */
const POPUP_MAX_WIDTH = 300;

export interface UseEditorPopupOptions {
    /** The popup's root element — forwarded by EditorConfigPopup. */
    popupElement: () => HTMLElement | null;
    /** The toolbar row — clicks on it must not close the popup. */
    toolbarElement: () => HTMLElement | null;
}

export interface UseEditorPopupApi {
    mode: Ref<EditorPopupMode | null>;
    /** The fixed viewport anchor — `{left, top}` in px. */
    position: ComputedRef<{ left: number; top: number }>;
    /** Opens the popup (or closes it when the same mode is active). */
    toggle: (mode: EditorPopupMode, event?: MouseEvent) => void;
    close: () => void;
}

export function useEditorPopup(options: UseEditorPopupOptions): UseEditorPopupApi {
    const mode = ref<EditorPopupMode | null>(null);
    const left = ref(8);
    const top = ref(52);

    const position = computed(() => ({ left: left.value, top: top.value }));

    function toggle(next: EditorPopupMode, event?: MouseEvent): void {
        if (mode.value === next) {
            close();
            return;
        }

        mode.value = next;
        positionFromEvent(event);
    }

    function positionFromEvent(event?: MouseEvent): void {
        if (event === undefined) {
            return;
        }

        const buttonRect = (event.currentTarget as HTMLElement).getBoundingClientRect();

        top.value = buttonRect.bottom + 4;
        left.value = Math.min(
            Math.max(8, buttonRect.left),
            Math.max(8, window.innerWidth - POPUP_MAX_WIDTH - 8),
        );
    }

    function close(): void {
        mode.value = null;
    }

    function onDocumentClickCapture(event: MouseEvent): void {
        if (mode.value === null) {
            return;
        }

        const target = event.target as Node;

        if (
            options.popupElement()?.contains(target) === true ||
            options.toolbarElement()?.contains(target) === true
        ) {
            return;
        }

        close();
    }

    function onDocumentKeydownCapture(event: KeyboardEvent): void {
        if (mode.value !== null && event.key === 'Escape') {
            close();
        }
    }

    onMounted(() => {
        document.addEventListener('click', onDocumentClickCapture, true);
        document.addEventListener('keydown', onDocumentKeydownCapture, true);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('click', onDocumentClickCapture, true);
        document.removeEventListener('keydown', onDocumentKeydownCapture, true);
    });

    return { mode, position, toggle, close };
}
