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

const containerClass = computed(() => ({
    [`centered-container--${props.size}`]: props.centered,
    'centered-container--full': !props.centered,
    'centered-container--no-padding': !props.padding,
}));
</script>

<template>
  <component
    :is="props.tag"
    class="centered-container"
    :class="containerClass"
    :data-testid="props.test_id"
  >
    <slot />
  </component>
</template>

<style scoped>
.centered-container {
    width: 100%;
    margin: 0 auto;
    padding-left: var(--container-padding);
    padding-right: var(--container-padding);
}

.centered-container--full {
    max-width: none;
    padding-left: 0;
    padding-right: 0;
}

.centered-container--sm {
    max-width: var(--container-sm);
}

.centered-container--md {
    max-width: var(--container-md);
}

.centered-container--lg {
    max-width: var(--container-lg);
}

.centered-container--xl {
    max-width: var(--container-xl);
}

.centered-container--no-padding {
    padding-left: 0;
    padding-right: 0;
}

/* 768px mirrors the --bp-md token (var() in media queries is unsupported
   by the production CSS minifier). */
@media (width >= 768px) {
    .centered-container {
        padding-left: var(--container-padding-md);
        padding-right: var(--container-padding-md);
    }
}
</style>
