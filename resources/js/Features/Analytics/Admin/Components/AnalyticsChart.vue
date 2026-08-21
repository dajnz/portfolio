<!-- AnalyticsChart (admin-section-components.md:1317-1334, admin-section-ui.md:441-452, mockup analytics.html:217-233) — lightweight hand-drawn SVG chart (bar/line), no charting library. Chart data is set via textContent exclusively — never innerHTML or string concatenation (analytics.md:200): labels/values render through Vue interpolation into text nodes; only numeric coordinates are composed into attributes. States: loading (skeleton rect) / loaded (SVG with axes, labels, bars/lines) / empty ("No data available" centered) / error ("Could not load chart. [Retry]"). -->
<script setup lang="ts">
import AdminBaseSkeleton from '@shared-admin/AdminBaseSkeleton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

export interface ChartPoint {
    label: string;
    value: number;
}

type ChartType = 'bar' | 'line';

const props = withDefaults(
    defineProps<{
        type?: ChartType;
        data?: Array<ChartPoint>;
        height?: number;
        is_loading?: boolean;
        title?: string;
        test_id?: string;
    }>(),
    {
        type: 'bar',
        data: () => [],
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

const isError = computed(() => !Array.isArray(props.data));
const maxValue = computed(() => Math.max(1, ...(props.data ?? []).map((point) => point.value)));

const barWidth = computed(() => {
    const count = Math.max(1, (props.data ?? []).length);
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
    const points = props.data ?? [];
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

const svgLabel = computed(() =>
    t(props.type === 'bar' ? 'admin.analytics.chart_aria_bar' : 'admin.analytics.chart_aria_line', {
        subject: props.title ?? '',
    }),
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

    <template v-else-if="(props.data ?? []).length === 0">
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
