<script setup lang="ts">
import { computed } from 'vue';

type ContainerSize = 'sm' | 'md' | 'lg' | 'xl';

const props = withDefaults(
    defineProps<{
        size?: ContainerSize;
        padding?: boolean;
        tag?: string;
        centered?: boolean;
        test_id?: string;
    }>(),
    {
        size: 'xl',
        padding: true,
        tag: 'div',
        centered: true,
        test_id: 'centered-container',
    },
);

const SIZE_MAX_WIDTHS: Record<ContainerSize, string> = {
    sm: 'max-w-container-sm',
    md: 'max-w-container-md',
    lg: 'max-w-container-lg',
    xl: 'max-w-container-xl',
};

const containerClass = computed(() => [
    props.centered ? `mx-auto ${SIZE_MAX_WIDTHS[props.size]}` : '',
    props.padding ? 'px-4 sm:px-6' : 'px-0',
]);
</script>

<template>
  <component
    :is="props.tag"
    class="w-full"
    :class="containerClass"
    :data-testid="props.test_id"
  >
    <slot />
  </component>
</template>
