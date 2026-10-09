<script setup lang="ts">
import { computed } from 'vue';

type CardPadding = 'none' | 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        padding?: CardPadding;
        hoverable?: boolean;
        test_id?: string;
    }>(),
    {
        padding: 'md',
        hoverable: false,
        test_id: 'base-card',
    },
);

const paddingClass = computed(() => {
    const map: Record<CardPadding, string> = {
        none: '',
        sm: 'p-3',
        md: 'p-5',
        lg: 'p-6',
    };

    return map[props.padding];
});
</script>

<template>
  <div
    class="bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-lg shadow-card"
    :class="[paddingClass, { 'hover:shadow-card-hover transition-shadow': props.hoverable }]"
    :data-testid="props.test_id"
  >
    <div
      v-if="$slots.header"
      class="mb-4 border-b border-slate-100 dark:border-slate-700 pb-4"
    >
      <slot name="header" />
    </div>
    <slot />
    <div
      v-if="$slots.footer"
      class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
