<!-- AnalyticsMetricBlocks — clickable whole-time metric blocks: label, formatted value and an optional min/max line for the avg blocks (time on page / read time / completion rate). The selected block is highlighted (aria-pressed + data-state) and the detail area below follows it. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { RichText } from '../Composables/useAnalytics';
import AnalyticsRichValue from './AnalyticsRichValue.vue';

export interface MetricBlock {
    key: string;
    label: string;
    value: number | string | RichText;
    min?: number | string | RichText;
    max?: number | string | RichText;
    selected: boolean;
}

const props = withDefaults(
    defineProps<{
        blocks: Array<MetricBlock>;
        test_id?: string;
    }>(),
    {
        test_id: 'analytics-metric-blocks',
    },
);

const emit = defineEmits<{
    select: [key: string];
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="grid grid-cols-2 gap-3 min-[360px]:grid-cols-3 sm:gap-4"
    :data-testid="props.test_id"
  >
    <button
      v-for="block in props.blocks"
      :key="block.key"
      type="button"
      class="rounded-lg border bg-white p-3 text-left shadow-card transition-colors sm:p-5 dark:bg-surface-dark-secondary cursor-pointer"
      :class="
        block.selected
          ? 'border-primary-500 ring-[3px] ring-primary-100 dark:ring-primary-900'
          : 'border-slate-200 hover:border-primary-300 dark:border-slate-700 dark:hover:border-primary-700'
      "
      :aria-pressed="block.selected"
      :data-state="block.selected ? 'selected' : 'default'"
      :data-testid="`${props.test_id}-block-${block.key}`"
      @click="emit('select', block.key)"
    >
      <span class="mb-1 block text-2xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        {{ block.label }}
      </span>
      <span class="block text-xl font-extrabold text-slate-900 sm:text-2xl dark:text-white">
        <AnalyticsRichValue
          :parts="block.value"
          :test_id="`${props.test_id}-block-${block.key}-value`"
        />
      </span>
      <span
        v-if="block.min !== undefined || block.max !== undefined"
        class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] font-medium text-slate-400 sm:text-xs dark:text-slate-500"
      >
        <span v-if="block.min !== undefined">
          {{ t('admin.analytics.min') }}
          <AnalyticsRichValue
            :parts="block.min"
            :test_id="`${props.test_id}-block-${block.key}-min`"
          />
        </span>
        <span v-if="block.max !== undefined">
          {{ t('admin.analytics.max') }}
          <AnalyticsRichValue
            :parts="block.max"
            :test_id="`${props.test_id}-block-${block.key}-max`"
          />
        </span>
      </span>
    </button>
  </div>
</template>
