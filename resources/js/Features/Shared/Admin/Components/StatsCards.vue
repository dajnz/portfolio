<!-- StatsCards (admin-section-components.md:687-698) — dashboard stat cards: label, value, optional trend per card; skeleton loading and per-card error states. -->
<script setup lang="ts">
import AdminBaseSkeleton from '@shared-admin/AdminBaseSkeleton.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

export interface StatCardData {
    label: string;
    /** Absent value renders the per-card error state (mockup dashboard.md:298-300). */
    value?: number | string;
    trend?: string;
    isLoading: boolean;
}

const props = withDefaults(
    defineProps<{
        stats: Array<StatCardData>;
        columns?: number;
        test_id?: string;
    }>(),
    {
        columns: 4,
        test_id: 'stats-cards',
    },
);

const emit = defineEmits<{
    retry: [stat: StatCardData];
}>();

const { t } = useI18n();

// Tailwind's JIT cannot generate dynamic `xl:grid-cols-N` utilities; the
// default 4-column layout uses the mockup classes verbatim, other column
// counts fall back to the 2-column layout at xl (dashboard.md:293).
const wideGridClass = computed(() => (props.columns === 4 ? 'xl:grid-cols-4' : ''));
</script>

<template>
  <div
    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
    :class="wideGridClass"
    :data-testid="props.test_id"
  >
    <div
      v-for="(stat, index) in props.stats"
      :key="`${stat.label}-${index}`"
      class="rounded-lg border border-slate-200 border-l-[3px] border-l-primary-600 bg-white p-5 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
      :data-testid="`${props.test_id}-card-${index}`"
    >
      <template v-if="stat.isLoading">
        <div class="space-y-2">
          <AdminBaseSkeleton
            shape="text"
            :width="'45%'"
            :test_id="`${props.test_id}-skeleton-label`"
          />
          <AdminBaseSkeleton
            shape="text"
            :width="'70%'"
            :test_id="`${props.test_id}-skeleton-value`"
          />
        </div>
      </template>
      <template v-else-if="stat.value === null || stat.value === undefined">
        <AdminEmptyState
          mode="error"
          :message="t('common.error')"
          :action_label="t('common.retry')"
          :test_id="`${props.test_id}-error-${index}`"
          @action="emit('retry', stat)"
        />
      </template>
      <template v-else>
        <div class="mb-1 text-2xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          {{ stat.label }}
        </div>
        <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
          {{ stat.value }}
        </div>
        <div
          v-if="stat.trend"
          class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
        >
          {{ stat.trend }}
        </div>
      </template>
    </div>
  </div>
</template>
