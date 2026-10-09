<!-- AnalyticsDetailArea — the dashboard's detail section below the metric blocks: the span dropdown + the chart for the chart blocks (single-series or the CTA multi-series), the RankingsTable + the variant dropdown for the rankings blocks. Pure view — the state and the fetch wiring come in as the `detail` API from `useAnalyticsDashboardDetail` (the page orchestrates). The chart component is code-split via defineAsyncComponent; chart data flows through textContent exclusively. -->
<script setup lang="ts">
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import { defineAsyncComponent } from 'vue';
import type { UseAnalyticsDashboardDetail } from '../Composables/useAnalyticsDashboardDetail';
import RankingsTable from './RankingsTable.vue';

const AnalyticsChart = defineAsyncComponent(() => import('@analytics-admin/AnalyticsChart.vue'));

defineProps<{
    detail: UseAnalyticsDashboardDetail;
    test_id?: string;
}>();
</script>

<template>
  <template v-if="detail.activeConfig.value?.kind === 'chart' || detail.activeConfig.value?.kind === 'chart-multi'">
    <div class="mb-3 mt-6 flex items-center justify-end">
      <AdminBaseDropdown
        :model-value="detail.span.value"
        :options="detail.spanOptions()"
        :test_id="`${test_id}-span`"
        @update:model-value="detail.onSpanChange"
      />
    </div>
    <AnalyticsChart
      :type="detail.chartType.value"
      :data="detail.chartData.value"
      :series="detail.chartSeriesProp.value"
      :is_loading="detail.chartIsLoading.value"
      :title="detail.chartTitle.value"
      :test_id="`${test_id}-chart`"
    />
  </template>

  <RankingsTable
    v-else-if="detail.activeConfig.value?.kind === 'rankings'"
    :columns="detail.rankingColumns.value"
    :rows="detail.rankingRowsView.value"
    :variant="detail.variant.value"
    :variant_options="detail.rankingVariantOptions.value"
    :is_loading="detail.rankingsLoading.value"
    :error="detail.rankingsError.value"
    :row_key="detail.rankingRowKey.value"
    :test_id="`${test_id}-rankings`"
    @update:variant="detail.onVariantChange"
    @retry="detail.onRankingsRetry"
  />
</template>
