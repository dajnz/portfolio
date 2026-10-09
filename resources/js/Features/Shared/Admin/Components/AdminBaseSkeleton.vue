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
      class="bg-slate-200 dark:bg-slate-700 rounded h-3"
      :class="{ 'animate-pulse': props.animated }"
      :style="{ width: line.width, height: props.height ?? undefined }"
      aria-hidden="true"
      :data-testid="props.test_id"
    />
  </template>
  <div
    v-else
    class="bg-slate-200 dark:bg-slate-700"
    :class="[
      props.shape === 'rectangle' ? 'rounded-md' : 'rounded-full',
      { 'animate-pulse': props.animated },
    ]"
    :style="{ width: props.width ?? undefined, height: props.height ?? undefined }"
    aria-hidden="true"
    :data-testid="props.test_id"
  />
</template>
