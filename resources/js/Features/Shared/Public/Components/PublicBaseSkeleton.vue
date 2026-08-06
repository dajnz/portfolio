<script setup lang="ts">
import { computed } from 'vue';

type SkeletonShape = 'text' | 'rectangle' | 'circle' | 'pill';

const props = withDefaults(
    defineProps<{
        shape?: SkeletonShape;
        width?: string;
        height?: string;
        repeat?: number;
        animated?: boolean;
        test_id?: string;
    }>(),
    {
        shape: 'text',
        width: undefined,
        height: undefined,
        repeat: 1,
        animated: true,
        test_id: 'base-skeleton',
    },
);

// Deterministic pseudo-random widths for text lines (public-section-
// components.md:278) — computed by index so SSR and client renders match.
const TEXT_WIDTHS = ['40%', '60%', '75%', '50%', '90%'];

const lines = computed(() =>
    props.shape === 'text'
        ? Array.from({ length: Math.max(1, props.repeat) }, (_, index) => ({
              key: index,
              width: props.width ?? TEXT_WIDTHS[index % TEXT_WIDTHS.length],
          }))
        : [],
);
</script>

<template>
  <template v-if="props.shape === 'text'">
    <span
      v-for="line in lines"
      :key="line.key"
      class="skeleton skeleton--text"
      :class="{ 'skeleton--static': !props.animated }"
      :style="{ width: line.width, height: props.height ?? undefined }"
      aria-hidden="true"
      :data-testid="props.test_id"
    />
  </template>
  <div
    v-else
    class="skeleton"
    :class="[`skeleton--${props.shape}`, { 'skeleton--static': !props.animated }]"
    :style="{ width: props.width ?? undefined, height: props.height ?? undefined }"
    aria-hidden="true"
    :data-testid="props.test_id"
  />
</template>

<style scoped>
.skeleton {
    background: linear-gradient(
        90deg,
        var(--skeleton-base) 0%,
        var(--skeleton-shimmer) 50%,
        var(--skeleton-base) 100%
    );
    background-size: 200% 100%;
    animation: shimmer var(--skeleton-animation-duration) ease-in-out infinite;
    border-radius: var(--radius-md);
    color: transparent !important;
    user-select: none;
    pointer-events: none;
}

.skeleton--static {
    animation: none;
    background: var(--skeleton-base);
}

.skeleton--text {
    display: inline-block;
    height: 1em;
    margin-bottom: 0.5em;
    border-radius: var(--radius-sm);
}

.skeleton--circle {
    border-radius: var(--radius-full);
}

.skeleton--pill {
    display: inline-block;
    height: 1.5em;
    border-radius: var(--radius-full);
}

@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }

    100% {
        background-position: 200% 0;
    }
}
</style>
