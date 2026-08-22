<!-- AnalyticsMetricBlocks (admin-section-components.md:1326-1328, admin-section-ui.md:445-448) — clickable whole-time metric blocks: label, formatted value and an optional min/max line for the avg blocks (time on page / read time / completion rate). The selected block is highlighted (aria-pressed + data-state) and the detail area below follows it. -->
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
    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
    :data-testid="props.test_id"
  >
    <button
      v-for="block in props.blocks"
      :key="block.key"
      type="button"
      class="rounded-lg border bg-white p-5 text-left shadow-card transition-colors dark:bg-surface-dark-secondary"
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
      <span class="mb-1 block text-2xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
        {{ block.label }}
      </span>
      <span class="block text-2xl font-extrabold text-slate-900 dark:text-white">
        <AnalyticsRichValue
          :parts="block.value"
          :test_id="`${props.test_id}-block-${block.key}-value`"
        />
      </span>
      <span
        v-if="block.min !== undefined || block.max !== undefined"
        class="mt-2 flex gap-3 text-xs font-medium text-slate-400 dark:text-slate-500"
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
