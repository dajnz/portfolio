<!-- AnalyticsChartSeriesPlot — the multi-series plot of AnalyticsChart (the CTA multi-chart): one polyline per series + the point markers (a lone polyline would be invisible), the hover tooltip on each marker and the union x-axis labels. Owns the hover state and clears the tooltip when the data refreshes. User data renders as text nodes only (the textContent rule — no user data in attributes). -->
<script setup lang="ts">
import { ref, watch } from 'vue';

import {
    type ChartSeries,
    type HoveredMarker,
    type SeriesMarker,
    seriesColor,
    VIEWBOX_HEIGHT,
} from '../Composables/useAnalyticsChart';
import AnalyticsChartTooltip from './AnalyticsChartTooltip.vue';

const props = defineProps<{
    series: Array<ChartSeries>;
    lines: Array<string>;
    markers: Array<Array<SeriesMarker>>;
    x_labels: Array<string>;
    x_positions: Array<number>;
    x_label_indices: Array<number>;
    viewbox_width: number;
    test_id?: string;
}>();

const hoveredMarker = ref<HoveredMarker | null>(null);

function showPointTooltip(seriesIndex: number, pointIndex: number): void {
    const marker = props.markers[seriesIndex]?.[pointIndex];
    if (marker === undefined) {
        return;
    }

    hoveredMarker.value = {
        x: marker.x,
        y: marker.y,
        value: marker.value,
        name: props.series[seriesIndex].name,
        label: marker.label,
    };
}

function hidePointTooltip(): void {
    hoveredMarker.value = null;
}

// A data refresh can remove the hovered point — drop the stale tooltip.
watch(
    () => props.markers,
    () => {
        hoveredMarker.value = null;
    },
);
</script>

<template>
  <g
    v-for="(_, seriesIndex) in props.lines"
    :key="props.series[seriesIndex].name"
  >
    <polyline
      :points="props.lines[seriesIndex]"
      fill="none"
      :stroke="seriesColor(seriesIndex)"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <g
      v-for="(point, pointIndex) in props.markers[seriesIndex]"
      :key="pointIndex"
      :data-testid="`${props.test_id}-marker-${seriesIndex}-${pointIndex}`"
      @mouseenter="showPointTooltip(seriesIndex, pointIndex)"
      @mouseleave="hidePointTooltip"
    >
      <circle
        :cx="point.x"
        :cy="point.y"
        r="3"
        :fill="seriesColor(seriesIndex)"
      />
      <!-- The invisible hit area makes the small marker easy to
           hover (no user data in attributes — textContent rule). -->
      <circle
        :cx="point.x"
        :cy="point.y"
        r="10"
        fill="transparent"
      />
    </g>
  </g>

  <AnalyticsChartTooltip
    :marker="hoveredMarker"
    :viewbox_width="props.viewbox_width"
    :test_id="props.test_id"
  />

  <g
    v-for="(label, index) in props.x_labels"
    :key="index"
  >
    <text
      v-if="props.x_label_indices.includes(index)"
      :x="props.x_positions[index]"
      :y="VIEWBOX_HEIGHT - 10"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ label }}
    </text>
  </g>
</template>
