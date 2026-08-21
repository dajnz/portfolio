<!-- AnalyticsChart (admin-section-components.md:1334-1352, admin-section-ui.md:441-452, mockup analytics.html:217-233) — lightweight hand-drawn SVG chart (bar/line), no charting library. Multi-series mode via the `series` prop (one line per series + a legend with the series names — the CTA multi-chart): `series` is mutually exclusive with `data` — when both are given the runtime guard prefers `series` (documented; the page never passes both). A non-array `data` or `series` (the page passes `null` on fetch error) drives the error state. Chart data is set via textContent exclusively — never innerHTML or string concatenation (analytics.md:230): labels/values render through Vue interpolation into text nodes; only numeric coordinates are composed into attributes. States: loading (skeleton rect) / loaded (SVG with axes, labels, bars/lines) / empty ("No data available" centered) / error ("Could not load chart. [Retry]"). -->
<script setup lang="ts">
import AdminBaseSkeleton from '@shared-admin/AdminBaseSkeleton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

export interface ChartPoint {
    label: string;
    value: number;
}

export interface ChartSeries {
    name: string;
    points: Array<ChartPoint>;
}

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

// Mockup geometry (analytics.html:221-228): viewBox 640×260, 20 px side
// padding, value labels in the top 40 px, x-labels in the bottom 30 px.
const VIEWBOX_WIDTH = 640;
const VIEWBOX_HEIGHT = 260;
const PADDING_X = 20;
const TOP_AREA = 40;
const BOTTOM_AREA = 30;
const PLOT_HEIGHT = VIEWBOX_HEIGHT - TOP_AREA - BOTTOM_AREA;

// Six distinct hues — the CTA multi-chart legend (primary + semantic
// accents). Indexed by the series position.
const SERIES_COLORS = ['#5C87EF', '#22c55e', '#f59e0b', '#ef4444', '#8FB0F7', '#14b8a6'];

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

const maxValue = computed(() => {
    if (seriesMode.value) {
        return Math.max(
            1,
            ...chartSeries.value.flatMap((series) => series.points.map((point) => point.value)),
        );
    }

    return Math.max(1, ...chartData.value.map((point) => point.value));
});

const barWidth = computed(() => {
    const count = Math.max(1, chartData.value.length);
    const slot = VIEWBOX_WIDTH - PADDING_X * 2;

    return Math.min(30, slot / count - 8);
});

interface PointGeometry {
    x: number;
    y: number;
    barHeight: number;
    point: ChartPoint;
    index: number;
}

// Numeric coordinates only — the points string is built from numbers, the
// label/value text nodes carry the user data (textContent rule).
const geometries = computed<Array<PointGeometry>>(() => {
    const points = chartData.value;
    const count = Math.max(1, points.length);
    const slot = (VIEWBOX_WIDTH - PADDING_X * 2) / count;

    return points.map((point, index) => {
        const barHeight = (point.value / maxValue.value) * PLOT_HEIGHT;

        return {
            x: PADDING_X + slot * index + slot / 2,
            y: TOP_AREA + PLOT_HEIGHT - barHeight,
            barHeight,
            point,
            index,
        };
    });
});

const linePoints = computed(() =>
    geometries.value.map((geometry) => `${geometry.x},${geometry.y}`).join(' '),
);

interface LinePointGeometry {
    x: number;
    y: number;
}

const multiLineGeometries = computed<Array<Array<LinePointGeometry>>>(() =>
    chartSeries.value.map((series) => {
        const count = Math.max(1, series.points.length);
        const slot = (VIEWBOX_WIDTH - PADDING_X * 2) / count;

        return series.points.map((point, index) => ({
            x: PADDING_X + slot * index + slot / 2,
            y: TOP_AREA + PLOT_HEIGHT - (point.value / maxValue.value) * PLOT_HEIGHT,
        }));
    }),
);

const multiLinePoints = computed(() =>
    multiLineGeometries.value.map((line) => line.map((point) => `${point.x},${point.y}`).join(' ')),
);

// The x-axis labels come from the first series — the per-target series
// are aligned (same period rows), so the positions match.
const multiXLabels = computed(() => chartSeries.value[0]?.points ?? []);

const multiSlot = computed(() => {
    const count = Math.max(1, multiXLabels.value.length);

    return (VIEWBOX_WIDTH - PADDING_X * 2) / count;
});

function multiXPosition(index: number): number {
    return PADDING_X + multiSlot.value * index + multiSlot.value / 2;
}

function seriesColor(index: number): string {
    return SERIES_COLORS[index % SERIES_COLORS.length];
}

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
      <!-- Contract loading state: skeleton rectangle at the chart area
           dimensions (admin-section-components.md:715). -->
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
        <g
          v-for="(_, seriesIndex) in multiLineGeometries"
          :key="chartSeries[seriesIndex].name"
        >
          <polyline
            :points="multiLinePoints[seriesIndex]"
            fill="none"
            :stroke="seriesColor(seriesIndex)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </g>
        <g
          v-for="(point, index) in multiXLabels"
          :key="index"
        >
          <text
            :x="multiXPosition(index)"
            :y="VIEWBOX_HEIGHT - 10"
            text-anchor="middle"
            class="fill-slate-400 text-[10px] font-mono"
          >
            {{ point.label }}
          </text>
        </g>
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
        <template v-if="props.type === 'bar'">
          <g
            v-for="geometry in geometries"
            :key="geometry.index"
          >
            <rect
              :x="geometry.x - barWidth / 2"
              :y="geometry.y"
              :width="barWidth"
              :height="geometry.barHeight"
              rx="3"
              fill="#B9CFFB"
            />
            <text
              :x="geometry.x"
              :y="geometry.y - 8"
              text-anchor="middle"
              class="fill-slate-500 text-[10px] font-mono"
            >
              {{ geometry.point.value }}
            </text>
            <text
              :x="geometry.x"
              :y="VIEWBOX_HEIGHT - 10"
              text-anchor="middle"
              class="fill-slate-400 text-[10px] font-mono"
            >
              {{ geometry.point.label }}
            </text>
          </g>
        </template>
        <template v-else>
          <polyline
            :points="linePoints"
            fill="none"
            stroke="#5C87EF"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <g
            v-for="geometry in geometries"
            :key="geometry.index"
          >
            <circle
              :cx="geometry.x"
              :cy="geometry.y"
              r="3"
              fill="#5C87EF"
            />
            <text
              :x="geometry.x"
              :y="geometry.y - 8"
              text-anchor="middle"
              class="fill-slate-500 text-[10px] font-mono"
            >
              {{ geometry.point.value }}
            </text>
            <text
              :x="geometry.x"
              :y="VIEWBOX_HEIGHT - 10"
              text-anchor="middle"
              class="fill-slate-400 text-[10px] font-mono"
            >
              {{ geometry.point.label }}
            </text>
          </g>
        </template>
      </svg>
    </template>
  </div>
</template>
