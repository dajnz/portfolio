<script setup lang="ts">
import { computed } from 'vue';

type ContainerSize = 'sm' | 'md' | 'lg' | 'xl' | 'content';

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

    /* Container-queries host: feature grids (e.g. the landing's featured
       cards) size their columns against the CONTENT width, not the
       viewport — so changing --layout-content keeps the breakpoints. */
    container-type: inline-size;
}

/* 768px mirrors the --bp-md token (var() in media queries is unsupported
   by the production CSS minifier). */
@media (width >= 768px) {
    .centered-container {
        padding-left: var(--container-padding-md);
        padding-right: var(--container-padding-md);
    }
}

/* The full/no-padding resets must come AFTER the media query — same
   specificity, later source order wins, so the desktop padding would
   otherwise leak into full-width containers (the landing's `main`). */
.centered-container--full {
    max-width: none;
    padding-left: 0;
    padding-right: 0;
}

.centered-container--sm {
    max-width: var(--layout-sm);
}

.centered-container--md {
    max-width: var(--layout-md);
}

.centered-container--lg {
    max-width: var(--layout-lg);
}

.centered-container--content {
    max-width: var(--layout-content);
}

.centered-container--xl {
    max-width: var(--layout-xl);
}

.centered-container--no-padding {
    padding-left: 0;
    padding-right: 0;
}
</style>
