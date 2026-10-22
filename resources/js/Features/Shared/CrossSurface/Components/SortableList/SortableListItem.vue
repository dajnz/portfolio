<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        index: number;
        item_test_id: string;
        is_dragging?: boolean;
        is_preview?: boolean;
        disabled?: boolean;
        item_class?: string;
    }>(),
    {
        is_dragging: false,
        is_preview: false,
        disabled: false,
        item_class: undefined,
    },
);

const emit = defineEmits<{
    mousedown: [event: MouseEvent, index: number];
    mouseup: [];
    dragstart: [event: DragEvent, index: number];
}>();

const itemClass = computed(() => [
    {
        'is-dragging': props.is_dragging,
        'is-disabled': props.disabled,
        'is-preview': props.is_preview,
        'opacity-40': props.is_preview,
    },
    props.item_class,
]);
</script>

<template>
  <li
    class="sortable-list__item"
    :class="itemClass"
    :data-sortable-index="props.index"
    :draggable="!props.disabled"
    :data-testid="props.item_test_id"
    @mousedown="emit('mousedown', $event, props.index)"
    @mouseup="emit('mouseup')"
    @dragstart="emit('dragstart', $event, props.index)"
  >
    <slot />
  </li>
</template>

<style scoped>
.sortable-list__item {
    transition: transform var(--transition-fast) var(--ease-default), opacity var(--transition-fast);
    touch-action: pan-y;
}

.sortable-list__item.is-dragging {
    opacity: 0.4;
}

.sortable-list__item.is-disabled {
    cursor: not-allowed;
}
</style>
