<script setup lang="ts">
import {
    clearAllPreviews,
    crossDragState,
    markCrossDragHandled,
    registerPreviewCleanup,
    resetCrossDrag,
    setCrossDragSource,
    unregisterPreviewCleanup,
} from '@shared-cross/Utils/crossSortableDrag';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface SortableListClasses {
    container?: string;
    item?: string;
    placeholder?: string;
    handle?: string;
}

export interface CrossDragInfo {
    /** The dragged item (shared reference with the source list). */
    item: Record<string, unknown>;
    /** `list_id` of the list the drag started in. */
    from_list_id: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: Array<Record<string, unknown>>;
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
    'update:modelValue': [items: Array<Record<string, unknown>>];
    'drag-start': [item: Record<string, unknown>];
    'drag-end': [items: Array<Record<string, unknown>>, cross?: CrossDragInfo | null];
}>();

// Local working copy — the visual order shifts live during a drag and the
// parent's `modelValue` is only updated on drop (optimistic-update pattern;
// rollback is the parent's concern).
const items = ref<Array<Record<string, unknown>>>([...props.modelValue]);

let draggingIndex: number | null = null;
let draggedItem: Record<string, unknown> | null = null;

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

// The container element — used by the dragleave preview cleanup (the
// relatedTarget check).
const ulEl = ref<HTMLElement | null>(null);

// The preview cleanup — the dragleave path (live) AND the module-level
// registry (a new drag or the source's dragend clears ALL previews, so
// hovered-but-left lists can never keep a stale copy). The preview item
// is located BY KEY, not by `previewIndex` — a foreign `props.modelValue`
// reset (a server re-render landing mid-drag) could otherwise leave the
// flags pointing at a real item, and the cleanup would remove the wrong
// row and duplicate the preview.
function clearPreview(): void {
    if (!previewActive.value) {
        return;
    }

    const index = items.value.findIndex((item) => keyOf(item) === previewItemKey.value);

    if (index !== -1) {
        items.value.splice(index, 1);
    }

    previewActive.value = false;
    previewIndex.value = -1;
    previewItemKey.value = null;
}

onMounted(() => {
    registerPreviewCleanup(clearPreview);
});

onBeforeUnmount(() => {
    unregisterPreviewCleanup(clearPreview);
});

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

const isDragging = ref(false);

function onItemMouseDown(event: MouseEvent, index: number): void {
    // Nested lists (the skills lists live inside the groups list's
    // items): the mousedown bubbles up to the OUTER list's items, whose
    // handle-mode arming must not see it — the innermost list owns its
    // item's gestures.
    event.stopPropagation();

    lastMousedownTarget = event.target as Element | null;

    if (props.disabled || props.handle === undefined) {
        return;
    }

    const handleElement = (event.target as Element | null)?.closest?.(props.handle);

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

watch(
    () => props.modelValue,
    (next) => {
        if (!isDragging.value) {
            items.value = [...next];

            // A props reset invalidates any in-flight preview: the items
            // no longer contain the preview item, but the flags/index
            // would still point at it — the next dragover would then
            // treat a REAL item as the preview slot (splicing it out,
            // duplicating the preview into ghost items). Reset the whole
            // preview state.
            previewActive.value = false;
            previewIndex.value = -1;
            previewItemKey.value = null;
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
    // Nested lists (the skills lists live inside the groups list's
    // items): the dragstart bubbles up to the OUTER list's items, whose
    // handle-mode guard would preventDefault() it (its arming never saw
    // the mousedown) and kill the drag before any dragover — the
    // innermost list owns its item's drag.
    event.stopPropagation();

    if (props.disabled) {
        event.preventDefault();
        return;
    }

    // A drag started on one of the item's interactive controls (checkbox,
    // dots, buttons, inputs) is rejected — the control's own interaction
    // wins. The mousedown target is tracked because `dragstart` fires on
    // the item element, not on the element under the cursor.
    if (
        props.interactive_selector !== undefined &&
        lastMousedownTarget?.closest?.(props.interactive_selector) != null
    ) {
        lastMousedownTarget = null;
        event.preventDefault();
        return;
    }

    lastMousedownTarget = null;

    // Handle-mode: only drags whose mousedown began on the handle are
    // allowed (the dragstart target is the item element, not the handle —
    // see onItemMouseDown).
    if (props.handle !== undefined) {
        const startedOnHandle = dragArmedFromHandle && dragArmedIndex === index;

        dragArmedFromHandle = false;
        dragArmedIndex = null;

        if (!startedOnHandle) {
            event.preventDefault();
            return;
        }
    }

    // A new drag invalidates every stale preview from the previous one.
    clearAllPreviews();

    draggingIndex = index;
    draggedItem = items.value[index] ?? null;
    isDragging.value = true;

    if (draggedItem !== null && props.list_id !== undefined && props.cross_scope !== undefined) {
        setCrossDragSource(props.list_id, props.cross_scope, draggedItem);
    }

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
    if (props.disabled) {
        return;
    }

    // Cross-list drag hovering this list: allow the drop (the browser
    // only fires `drop` on targets whose `dragover` was prevented) AND
    // show the live preview — the dragged item appears (semi-transparent)
    // at the hovered position.
    const cross = crossDragState();

    if (
        !isDragging.value &&
        cross.sourceListId !== null &&
        cross.crossScope === props.cross_scope &&
        cross.sourceListId !== props.list_id &&
        cross.item !== null
    ) {
        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        // Entering a list invalidates the previews of the lists the
        // cursor left behind (the dragleave is unreliable in practice) —
        // except this list's own, which is about to be (re)inserted.
        clearAllPreviews(clearPreview);

        // Live preview without DOM churn: insert the item once and only
        // MOVE it when the hovered position changes. Re-inserting on
        // every dragover would re-render the list and the empty-slot
        // content, retargeting the cursor and flapping endlessly.
        const insertionIndex = insertionIndexAt(event);

        if (!previewActive.value) {
            // Defensive: drop any stale copies of the item before
            // inserting the preview (a leftover from a previous drag).
            const key = cross.item[props.item_key];
            items.value = items.value.filter((item) => keyOf(item) !== key);
            const insertAt = Math.min(insertionIndex, items.value.length);
            items.value.splice(insertAt, 0, cross.item);
            previewIndex.value = insertAt;
            previewItemKey.value = key;
            previewActive.value = true;
        } else {
            // The insertion index is computed WITH the preview in place —
            // normalize it to the preview-less coordinate before comparing.
            const adjusted =
                insertionIndex > previewIndex.value ? insertionIndex - 1 : insertionIndex;

            if (adjusted !== previewIndex.value) {
                items.value.splice(previewIndex.value, 1);
                items.value.splice(adjusted, 0, cross.item);
                previewIndex.value = adjusted;
            }
        }

        return;
    }

    if (!isDragging.value || draggingIndex === null) {
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

function insertionIndexAt(event: DragEvent): number {
    const overIndex = itemIndex(event);

    if (overIndex === -1) {
        // Dropped on the container (empty list or below the items) —
        // append at the end.
        return items.value.length;
    }

    const overElement = (event.target as Element | null)?.closest?.('[data-sortable-index]');
    const rect = overElement?.getBoundingClientRect();
    const cursor = props.axis === 'y' ? event.clientY : event.clientX;
    const midPoint = rect
        ? props.axis === 'y'
            ? rect.top + rect.height / 2
            : rect.left + rect.width / 2
        : 0;

    return overIndex + (cursor > midPoint ? 1 : 0);
}

function onDragLeave(event: DragEvent): void {
    // Remove the cross-list preview when the drag actually leaves this
    // list (moving between the list's children keeps it — the
    // relatedTarget stays inside the container).
    if (!previewActive.value) {
        return;
    }

    const related = event.relatedTarget as Node | null;

    if (related !== null && ulEl.value?.contains(related) === true) {
        return;
    }

    clearPreview();
}

function onDrop(event: DragEvent): void {
    // Same-list drop.
    if (isDragging.value && draggingIndex !== null) {
        event.preventDefault();

        const result = [...items.value];
        finishDrag(result);
        return;
    }

    // Cross-list drop: a drag that started in another list with the same
    // scope (a skill moved into this group). The insertion position
    // mirrors the same-list midpoint logic (the live preview already
    // placed the item — the drop finalizes it); the parent is told where
    // the item came from via the second `drag-end` argument.
    if (props.disabled || props.list_id === undefined || props.cross_scope === undefined) {
        return;
    }

    const cross = crossDragState();

    if (
        cross.sourceListId === null ||
        cross.sourceListId === props.list_id ||
        cross.crossScope !== props.cross_scope ||
        cross.item === null
    ) {
        return;
    }

    const itemKey = cross.item[props.item_key];

    // The live preview already inserted the item — only reject a drop of
    // an item that is present WITHOUT a preview (a genuine duplicate).
    if (!previewActive.value && items.value.some((item) => keyOf(item) === itemKey)) {
        return;
    }

    event.preventDefault();

    const result = [...items.value];

    if (!previewActive.value) {
        result.splice(insertionIndexAt(event), 0, cross.item);
    }

    previewActive.value = false;
    previewIndex.value = -1;
    previewItemKey.value = null;
    items.value = result;

    markCrossDragHandled();
    resetDragLocal();

    emit('update:modelValue', result);
    emit('drag-end', result, {
        item: cross.item,
        from_list_id: cross.sourceListId,
    });
}

function onDragEnd(): void {
    // The drag is over — no hovered list may keep its preview copy.
    clearAllPreviews();

    // A cross-list drop already consumed the drag elsewhere — the source
    // list restores the server-confirmed order and stays silent (its own
    // order never changed; emitting would trigger a bogus reorder).
    if (crossDragState().handled) {
        items.value = [...props.modelValue];
        resetDragLocal();
        resetCrossDrag();
        return;
    }

    resetCrossDrag();

    if (!isDragging.value) {
        return;
    }

    // No drop happened (dropped outside or cancelled) — restore the
    // parent's order untouched.
    items.value = [...props.modelValue];
    finishDrag(items.value);
}

function resetDragLocal(): void {
    draggingIndex = null;
    draggedItem = null;
    isDragging.value = false;
}

function finishDrag(result: Array<Record<string, unknown>>): void {
    resetDragLocal();

    emit('update:modelValue', result);
    emit('drag-end', result, null);
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
    @dragleave="onDragLeave"
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
          // The cross-list preview — the dragged item's semi-transparent
          // placeholder inside the target list.
          'is-preview': previewActive && keyOf(item) === previewItemKey,
          'opacity-40': previewActive && keyOf(item) === previewItemKey,
        },
        props.classes?.item,
      ]"
      :data-sortable-index="index"
      :draggable="!props.disabled"
      :data-testid="`${props.test_id}-item-${String(keyOf(item))}`"
      @mousedown="onItemMouseDown($event, index)"
      @mouseup="onItemMouseUp"
      @dragstart="onDragStart($event, index)"
    >
      <slot
        :item="item"
        :index="index"
      />
    </li>
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
