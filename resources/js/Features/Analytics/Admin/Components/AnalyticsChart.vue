<!-- AnalyticsChart — the root of the lightweight hand-drawn SVG chart (bar/line), no charting library. Orchestrates the states (loading / loaded / empty / error) and the mode switch; the geometry lives in `useAnalyticsChart`, the marks in the plot sub-components (AnalyticsChartBarPlot / AnalyticsChartLinePlot / AnalyticsChartSeriesPlot + AnalyticsChartTooltip). Multi-series mode via the `series` prop (one line per series + a legend with the series names — the CTA multi-chart): `series` is mutually exclusive with `data` — when both are given the runtime guard prefers `series` (the page never passes both). A non-array `data` or `series` (the page passes `null` on fetch error) drives the error state. Every multi-series point renders a visible marker (a lone polyline would be invisible) with a hover tooltip — the series name + the point's value. Chart data is set via textContent exclusively — never innerHTML or string concatenation: labels/values render through Vue interpolation into text nodes; only numeric coordinates are composed into attributes. -->
<script setup lang="ts">
import AdminBaseSkeleton from '@shared-admin/AdminBaseSkeleton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

import {
    type ChartPoint,
    type ChartSeries,
    seriesColor,
    useAnalyticsChart,
    VIEWBOX_HEIGHT,
    VIEWBOX_WIDTH,
} from '../Composables/useAnalyticsChart';
import AnalyticsChartBarPlot from './AnalyticsChartBarPlot.vue';
import AnalyticsChartLinePlot from './AnalyticsChartLinePlot.vue';
import AnalyticsChartSeriesPlot from './AnalyticsChartSeriesPlot.vue';

type ChartType = 'bar' | 'line';

const props = withDefaults(
    defineProps<{
        type?: ChartType;
        data?: Array<ChartPoint> | null;
        series?: Array<ChartSeries> | null;
        height?: number;
        is_loading?: boolean;
        title?: string;
        test_id?: string;
    }>(),
    {
        type: 'bar',
        data: () => [],
        series: undefined,
        height: 300,
        is_loading: false,
        title: undefined,
        test_id: 'analytics-chart',
    },
);

const { t } = useI18n();

// The error state fires when the active data source is unusable: `data`
// is not an array (the single-series contract guard), or `series` was
// explicitly given as a non-array (the multi-series error signal — the
// page passes `null` on fetch failure). A plain missing `series` keeps
// single-series behavior.
const isError = computed(
    () =>
        !Array.isArray(props.data) || (props.series !== undefined && !Array.isArray(props.series)),
);

// The mutual-exclusivity guard: any array `series` (even alongside
// `data`) switches the chart to multi-series mode — series wins.
const seriesMode = computed(() => Array.isArray(props.series));

const chartData = computed(() => (Array.isArray(props.data) ? props.data : []));
const chartSeries = computed(() => (Array.isArray(props.series) ? props.series : []));

const isEmpty = computed(() =>
    seriesMode.value
        ? chartSeries.value.every((series) => series.points.length === 0)
        : chartData.value.length === 0,
);

const {
    barWidth,
    geometries,
    linePoints,
    unionLabels,
    multiLinePoints,
    multiLinePointMarkers,
    multiXPosition,
} = useAnalyticsChart(chartData, chartSeries);

// The union x-axis label positions of the multi-series plot.
const xPositions = computed(() => unionLabels.value.map((_, index) => multiXPosition(index)));

// Multi-series always renders lines, so the aria label follows the
// series mode regardless of the `type` prop (single-series only).
const svgLabel = computed(() =>
    t(
        seriesMode.value || props.type === 'line'
            ? 'admin.analytics.chart_aria_line'
            : 'admin.analytics.chart_aria_bar',
        { subject: props.title ?? '' },
    ),
);
</script>

<template>
  <div
    class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <h2
      v-if="props.title"
      class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400"
    >
      {{ props.title }}
    </h2>

    <template v-if="props.is_loading">
      <div
        :style="{ height: `${props.height}px` }"
        :data-testid="`${props.test_id}-loading`"
      >
        <AdminBaseSkeleton
          shape="rectangle"
          width="100%"
          :height="`${props.height}px`"
          :test_id="`${props.test_id}-skeleton`"
        />
      </div>
    </template>

    <template v-else-if="isError">
      <div
        class="flex items-center justify-center"
        :style="{ height: `${props.height}px` }"
        :data-testid="`${props.test_id}-error`"
      >
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          {{ t('admin.analytics.could_not_load_chart') }}
        </p>
      </div>
    </template>

    <template v-else-if="isEmpty">
      <div
        class="flex items-center justify-center"
        :style="{ height: `${props.height}px` }"
        :data-testid="`${props.test_id}-empty`"
      >
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          {{ t('admin.analytics.no_data') }}
        </p>
      </div>
    </template>

    <template v-else-if="seriesMode">
      <svg
        :viewBox="`0 0 ${VIEWBOX_WIDTH} ${VIEWBOX_HEIGHT}`"
        class="w-full"
        :style="{ height: `${props.height}px` }"
        role="img"
        :aria-label="svgLabel"
        :data-testid="`${props.test_id}-svg`"
      >
        <AnalyticsChartSeriesPlot
          :series="chartSeries"
          :lines="multiLinePoints"
          :markers="multiLinePointMarkers"
          :x_labels="unionLabels"
          :x_positions="xPositions"
          :test_id="props.test_id"
        />
      </svg>
      <ul
        class="mt-4 flex flex-wrap gap-x-6 gap-y-2"
        :data-testid="`${props.test_id}-legend`"
      >
        <li
          v-for="(entry, index) in chartSeries"
          :key="entry.name"
          class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300"
          :data-testid="`${props.test_id}-legend-item-${index}`"
        >
          <span
            class="inline-block h-2.5 w-2.5 rounded-full"
            :style="{ backgroundColor: seriesColor(index) }"
          />
          <span>{{ entry.name }}</span>
        </li>
      </ul>
    </template>

    <template v-else>
      <svg
        :viewBox="`0 0 ${VIEWBOX_WIDTH} ${VIEWBOX_HEIGHT}`"
        class="w-full"
        :style="{ height: `${props.height}px` }"
        role="img"
        :aria-label="svgLabel"
        :data-testid="`${props.test_id}-svg`"
      >
        <AnalyticsChartBarPlot
          v-if="props.type === 'bar'"
          :geometries="geometries"
          :bar_width="barWidth"
        />
        <AnalyticsChartLinePlot
          v-else
          :line_points="linePoints"
          :geometries="geometries"
        />
      </svg>
    </template>
  </div>
</template>
