<script setup lang="ts">
import { computed } from 'vue';

type ProgressVariant = 'default' | 'success' | 'danger';

const props = withDefaults(
    defineProps<{
        value?: number | null;
        indeterminate?: boolean;
        label?: string;
        variant?: ProgressVariant;
        show_percentage?: boolean;
        test_id?: string;
    }>(),
    {
        value: undefined,
        indeterminate: false,
        label: undefined,
        variant: 'default',
        show_percentage: true,
        test_id: 'progress-bar',
    },
);

const isIndeterminate = computed(
    () => props.indeterminate || props.value === null || props.value === undefined,
);

const roundedValue = computed(() => Math.round(props.value ?? 0));

const clampedValue = computed(() => Math.min(100, Math.max(0, roundedValue.value)));

const variantFillClass = computed(() => {
    const map: Record<ProgressVariant, string> = {
        default: 'bg-primary-600 dark:bg-primary-500',
        success: 'bg-success-500',
        danger: 'bg-danger-600',
    };

    return map[props.variant];
});
</script>

<template>
  <div :data-testid="props.test_id">
    <div
      v-if="props.label || (props.show_percentage && !isIndeterminate)"
      class="flex items-center justify-between mb-2"
    >
      <span
        v-if="props.label"
        class="text-sm font-semibold text-slate-700 dark:text-slate-200"
      >
        {{ props.label }}
      </span>
      <span
        v-if="props.show_percentage && !isIndeterminate"
        class="text-sm font-mono font-semibold text-primary-600 dark:text-primary-400"
      >
        {{ roundedValue }}%
      </span>
    </div>
    <div
      class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden"
      role="progressbar"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="isIndeterminate ? undefined : clampedValue"
      :aria-label="props.label || undefined"
    >
      <div
        v-if="isIndeterminate"
        class="h-full rounded-full w-2/5 animate-pulse"
        :class="variantFillClass"
      />
      <div
        v-else
        class="h-full rounded-full transition-all duration-500"
        :class="variantFillClass"
        :style="{ width: `${clampedValue}%` }"
      />
    </div>
  </div>
</template>
